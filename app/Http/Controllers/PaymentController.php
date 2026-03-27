<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Merchant;
use App\Models\Client;
use App\Models\Brands;
use Illuminate\Http\Request;
use Auth;
use Stripe\Stripe;
use Stripe\Exception\AuthenticationException as StripeAuthenticationException;
use Square\SquareClient;
use Square\Environment;
use Square\Exceptions\ApiException as SquareApiException;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    function __construct(){
        $this->middleware('permission:payment|create payment|edit payment|delete payment', ['only' => ['index','show']]);
        $this->middleware('permission:create payment', ['only' => ['create','store']]);
        $this->middleware('permission:edit payment', ['only' => ['edit','update']]);
        $this->middleware('permission:delete payment', ['only' => ['destroy']]);
        $this->middleware('permission:mark as paid', ['only' => ['paid']]);
    }

    public function index(Request $request){
        $data = Payment::where('show_status', 0);
        if($request->status != null){
            $data = $data->where('status', $request->status);
        }
        if($request->name != null){
            $name = $request->name;
            $data = $data->whereHas('client', function($q) use ($name){
                $q->where('name', 'like', '%' . $name . '%');
            });
        }
        if($request->email != null){
            $email = $request->email;
            $data = $data->whereHas('client', function($q) use ($email){
                $q->where('email', 'like', '%' . $email . '%');
            });
        }
        if($request->phone != null){
            $phone = $request->phone;
            $data = $data->whereHas('client', function($q) use ($phone){
                $q->where('phone', 'like', '%' . $phone . '%');
            });
        }
        $data = $data->orderBy('id', 'desc')->paginate(20);
        return view('payment.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $brands = Brands::where('status', 0)->get();
        $merhant = Merchant::where('status', 0)->get();
        return view('payment.create', compact('brands', 'merhant'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'brand_name' => 'required',
            'package' => 'required',
            'price' => 'required',
        ]);

        $client = Client::where('email', $request->email)->first();
        $client_id = 0;

        if($client == null){
            $data = Client::create($request->all());
            $client_id = $data->id;
        }else{
            $client_id = $client->id;
        }

        $payment = new Payment();
        $payment->package = $request->package;
        $payment->price = $request->price;
        $payment->description = $request->description;
        $payment->client_id = $client_id;
        $payment->unique_id = bin2hex(random_bytes(14));
        $payment->merchant = $request->merchant;
        $payment->user_id = Auth::user()->id;
        $payment->save();
        return redirect()->route('payment.show', [$payment->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Payment::find($id);
        return view('payment.show', compact('data'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Payment $payment)
    {
        //
    }
    
    public function delete($id){
        $payment = Payment::find($id);
        $payment->show_status = 1;
        $payment->save();
        return redirect()->back()->with('success', 'Invoice Deleted Successfully');   
    }
    
    public function paid(Request $request){
        $id = $request->id;
        $payment = Payment::find($id);
        $payment->status = 2;
        $payment->return_response = $request->source;
        $payment->save();
        return response()->json(['status' => true, 'message' => 'Invoice # ' . $payment->id .' Paid Successfully']);
    }

    /**
     * Check merchant credentials
     */
    public function checkCredentials(Request $request, $id)
    {
        try {
            $request->validate([
                'type' => 'required|integer',
                'public_key' => 'required|string',
                'sandbox' => 'required|integer',
                'square_location_id' => 'nullable|string|required_if:type,6'
            ]);

            $type = $request->type;
            $publicKey = $request->public_key;
            $privateKey = $request->private_key;
            $sandbox = $request->sandbox;
            $squareLocationId = $request->square_location_id;

            switch ($type) {
                case '0': // Stripe
                    return $this->checkStripeCredentials($publicKey, $privateKey, $sandbox);
                    
                case '4': // Authorize.Net
                    return $this->checkAuthorizeCredentials($publicKey, $privateKey, $sandbox);
                    
                case '5': // PayPal
                    return $this->checkPayPalCredentials($publicKey, $privateKey, $sandbox);
                    
                case '6': // Square
                    return $this->checkSquareCredentials($publicKey, $privateKey, $sandbox, $squareLocationId);
                
                case '7': // PayKings / TG
                    return $this->checkPayKingsCredentials($publicKey, $privateKey, $sandbox);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported merchant type'
                    ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Credentials check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Stripe credentials
     */
    private function checkStripeCredentials($publicKey, $privateKey, $sandbox)
    {
        try {
            // Set Stripe API key
            Stripe::setApiKey($privateKey);
            
            // Set API version
            Stripe::setApiVersion('2023-10-16');
            
            // First, validate the API key by retrieving account info
            $account = \Stripe\Account::retrieve();
            
            // Also verify publishable key format
            if (!preg_match('/^pk_(test|live)_/', $publicKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid publishable key format. Key should start with pk_test_ or pk_live_'
                ]);
            }
            
            // Check if sandbox matches key type
            $isTestKey = strpos($publicKey, 'pk_test_') === 0;
            $isSandbox = ($sandbox == 1);
            
            if (($isSandbox && !$isTestKey) || (!$isSandbox && $isTestKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Key type mismatch. ' . ($isSandbox ? 'Sandbox' : 'Production') . ' mode selected but using ' . ($isTestKey ? 'test' : 'live') . ' key.'
                ]);
            }
            
            // Get account information with multiple fallback options
            $accountName = '';
            
            // Try to get business profile name
            if (isset($account->business_profile) && isset($account->business_profile->name) && !empty($account->business_profile->name)) {
                $accountName = $account->business_profile->name;
            } 
            // Try to get settings dashboard name
            elseif (isset($account->settings) && isset($account->settings->dashboard) && isset($account->settings->dashboard->display_name)) {
                $accountName = $account->settings->dashboard->display_name;
            }
            // Try to get email
            elseif (isset($account->email) && !empty($account->email)) {
                $accountName = $account->email;
            }
            // Try to get the account ID
            elseif (isset($account->id)) {
                $accountName = $account->id;
            }
            // Default fallback
            else {
                $accountName = 'Stripe Account';
            }
            
            // Additional: Check if it's a connected account or platform account
            $accountType = '';
            if (isset($account->type)) {
                $accountType = ' (' . ucfirst($account->type) . ' Account)';
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Stripe credentials are valid! Connected to: ' . $accountName . $accountType
            ]);
            
        } catch (StripeAuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Stripe API key: ' . $e->getMessage()
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe API error: ' . $e->getMessage()
            ]);
        } catch (\Exception $e) {
            \Log::error('Stripe connection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Stripe connection error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Check Square credentials - Using direct HTTP API calls (Most Reliable)
     */
    private function checkSquareCredentials($publicKey, $privateKey, $sandbox, $squareLocationId)
    {
        try {
            // Determine API endpoint
            $apiUrl = $sandbox == 1 
                ? 'https://connect.squareupsandbox.com'
                : 'https://connect.squareup.com';
            
            // Try to fetch locations to validate credentials
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $privateKey,
                'Content-Type' => 'application/json',
                'Square-Version' => '2024-01-17' // Use latest version
            ])->get($apiUrl . '/v2/locations');
            
            if ($response->successful()) {
                $data = $response->json();
                $locations = $data['locations'] ?? [];
                
                // Check if the location ID exists
                $locationExists = false;
                $locationName = '';
                $availableLocations = [];
                
                if (count($locations) > 0) {
                    foreach ($locations as $location) {
                        $locName = $location['name'] ?? 'Unnamed';
                        $locId = $location['id'];
                        $availableLocations[] = $locName . ' (' . $locId . ')';
                        
                        if ($squareLocationId && $locId == $squareLocationId) {
                            $locationExists = true;
                            $locationName = $locName;
                        }
                    }
                }
                
                if ($squareLocationId && !$locationExists) {
                    $message = 'Square credentials are valid, but Location ID "' . $squareLocationId . '" not found.';
                    if (count($availableLocations) > 0) {
                        $message .= ' Available locations: ' . implode(', ', $availableLocations);
                    } else {
                        $message .= ' No locations found for this account.';
                    }
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ]);
                }
                
                $locationMessage = '';
                if ($locationExists) {
                    $locationMessage = ' ✓ Location found: ' . $locationName;
                } elseif (count($availableLocations) > 0) {
                    $locationMessage = ' ℹ️ Available locations: ' . implode(', ', $availableLocations);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Square credentials are valid!' . $locationMessage
                ]);
                
            } else {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['detail'] ?? $error['errors'][0]['detail'] ?? 'Authentication failed';
                
                if ($response->status() == 401) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Square access token. Please check your credentials.'
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Square error: ' . $errorMessage
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Square connection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Square connection error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Check Authorize.Net credentials using official SDK
     */
    private function checkAuthorizeCredentials($publicKey, $privateKey, $sandbox)
    {
        try {
            $merchantAuthentication = new \net\authorize\api\contract\v1\MerchantAuthenticationType();
            $merchantAuthentication->setName($publicKey);
            $merchantAuthentication->setTransactionKey($privateKey);

            $request = new \net\authorize\api\contract\v1\GetMerchantDetailsRequest();
            $request->setMerchantAuthentication($merchantAuthentication);

            $controller = new \net\authorize\api\controller\GetMerchantDetailsController($request);

            $environment = $sandbox == 1
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

            $response = $controller->executeWithApiResponse($environment);

            if ($response != null) {
                if ($response->getMessages()->getResultCode() == "Ok") {
                    return response()->json([
                        'success' => true,
                        'message' => 'Authorize.Net credentials are valid!'
                    ]);
                } else {
                    $error = $response->getMessages()->getMessage()[0]->getText();

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid credentials: ' . $error
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No response from Authorize.Net'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check PayPal credentials
     */
    private function checkPayPalCredentials($publicKey, $privateKey, $sandbox)
    {
        try {
            $apiUrl = $sandbox == 1
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com';
            
            // Get access token
            $auth = base64_encode($publicKey . ':' . $privateKey);
            
            $tokenResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post($apiUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);
            
            if ($tokenResponse->successful()) {
                $token = $tokenResponse->json()['access_token'];
                
                // Try to get account info to validate
                $accountResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])->get($apiUrl . '/v1/identity/oauth2/userinfo?schema=paypalv1.1');
                
                if ($accountResponse->successful()) {
                    $userInfo = $accountResponse->json();
                    return response()->json([
                        'success' => true,
                        'message' => 'PayPal credentials are valid! Connected to: ' . ($userInfo['email'] ?? $userInfo['user_id'] ?? 'PayPal account')
                    ]);
                } else {
                    // Token is valid but we couldn't get user info
                    return response()->json([
                        'success' => true,
                        'message' => 'PayPal credentials are valid! (Token obtained successfully)'
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PayPal credentials: ' . ($tokenResponse->json()['error_description'] ?? 'Authentication failed')
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PayPal connection error: ' . $e->getMessage()
            ]);
        }
    }

    public function checkPayKingsCredentials($securityKey)
    {
        if (empty($securityKey)) {
            return response()->json([
                'success' => false,
                'message' => 'PayKings security key is required'
            ]);
        }

        try {
            // Minimal test request
            $query = [
                'security_key' => $securityKey,
                'type' => 'auth',
                'amount' => '0.01',
                'ccnumber' => '4111111111111111',
                'ccexp' => date('my', strtotime('+10 years')),
                'ipaddress' => '127.0.0.1',
                'orderid' => 'test123',
                'orderdescription' => 'Check Key',
                'firstname' => 'Test',
                'lastname' => 'User',
                'address1' => '123 Test St',
                'city' => 'Test City',
                'state' => 'TS',
                'zip' => '12345',
                'country' => 'US',
                'email' => 'test@example.com',
            ];

            // Send request to PayKings
            $response = Http::asForm()->post('https://paykings.transactiongateway.com/api/transact.php', $query);

            // Parse URL-encoded response
            parse_str($response->body(), $result);

            // Check if the key is valid
            if (isset($result['response']) && in_array($result['response'], [1, 2])) {
                return response()->json([
                    'success' => true,
                    'message' => 'PayKings security key is valid'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid security key: ' . ($result['responsetext'] ?? 'Unknown error')
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
}
