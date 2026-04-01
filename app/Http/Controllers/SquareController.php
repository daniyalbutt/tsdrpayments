<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Models\Address;
use Square\Models\VerificationDetails;
use Square\Models\BillingContact;
use Square\Exceptions\ApiException;

class SquareController extends Controller
{
    public function paymentSquare(Request $request)
    {
        $payment_data = $request->all();
        $data = Payment::find($request->input('id'));

        if ($data->status != 0) {
            return redirect()->back()->with('error', 'Payment already processed.');
        }

        try {
            // Initialize SquareClient
            $client = new SquareClient([
                'accessToken' => $data->merchants->private_key,
                'environment' => $data->merchants->sandbox == 1 ? 'sandbox' : 'production',
            ]);

            $paymentsApi = $client->getPaymentsApi();

            // Prepare Money object
            $money = new Money();
            $money->setAmount((int)($data->price * 100)); // cents
            $money->setCurrency('USD');

            // Create payment request using card nonce
            $paymentRequest = new CreatePaymentRequest(
                $request->input('nonce'),
                uniqid('payment_' . $data->id . '_', true),
                $money
            );

            // Set required fields
            $paymentRequest->setAutocomplete(true);
            $paymentRequest->setLocationId($data->merchants->square_location_id);
            $paymentRequest->setReferenceId('invoice_' . $data->id);
            
            // Set customer email
            if ($request->input('user_email')) {
                $paymentRequest->setBuyerEmailAddress($request->input('user_email'));
            }
            
            // Set note
            if ($data->package) {
                $paymentRequest->setNote($data->package);
            }
            
            // Create billing address
            $address = new Address();
            $address->setAddressLine1($request->input('address'));
            $address->setLocality($request->input('city'));
            $address->setPostalCode($request->input('zip') ?: '00000');
            $address->setCountry($request->input('country'));
            
            if ($request->input('state')) {
                $address->setAdministrativeDistrictLevel1($request->input('state'));
            }
            
            $paymentRequest->setBillingAddress($address);
            
            // Create billing contact for verification (CRITICAL FOR SQUARE)
            $billingContact = new BillingContact();
            $billingContact->setGivenName($request->input('user_name'));
            $billingContact->setFamilyName('');
            $billingContact->setEmail($request->input('user_email'));
            $billingContact->setPhone('');
            
            // Create verification details
            $verificationDetails = new VerificationDetails();
            $verificationDetails->setBillingContact($billingContact);
            
            // Set verification details
            $paymentRequest->setVerificationDetails($verificationDetails);
            
            // Set customer ID if available
            if ($data->client_id) {
                $paymentRequest->setCustomerId((string)$data->client_id);
            }
            
            // Set shipping address (same as billing)
            $paymentRequest->setShippingAddress($address);
            
            // Add metadata
            $paymentRequest->setMetadata([
                'invoice_id' => (string)$data->id,
                'package' => $data->package ?? '',
                'customer_name' => $request->input('user_name')
            ]);

            // Call createPayment
            $response = $paymentsApi->createPayment($paymentRequest);

            if ($response->isError()) {
                $errors = $response->getErrors();
                $errorMsg = '';
                foreach ($errors as $error) {
                    $errorMsg .= $error->getDetail() . ' ';
                }
                throw new Exception($errorMsg);
            }

            $result = $response->getResult()->getPayment();

            $data->update([
                'status' => 2,
                'return_response' => json_encode($result),
                'payment_data' => json_encode($payment_data),
                'square_response' => json_encode($result),
            ]);

            return redirect()->route('success.payment', ['id' => $data->id]);

        } catch (ApiException $e) {
            $errors = $e->getErrors();
            $errorMessage = '';
            foreach ($errors as $error) {
                $errorMessage .= $error->getDetail() . ' ';
            }
            
            \Log::error('Square API Error: ' . $errorMessage);
            \Log::error('Square API Error Details: ' . json_encode($errors));
            
            $data->update([
                'square_response' => json_encode($errors),
                'status' => 1,
                'return_response' => $errorMessage ?: $e->getMessage(),
                'payment_data' => json_encode($request->except(['amount', '_token', 'id', 'nonce'])),
            ]);
            
            return redirect()->route('declined.payment', ['id' => $data->id]);
        } catch (Exception $e) {
            \Log::error('Square Exception: ' . $e->getMessage());
            \Log::error('Square Exception Trace: ' . $e->getTraceAsString());
            
            $data->update([
                'square_response' => json_encode([]),
                'status' => 1,
                'return_response' => $e->getMessage(),
                'payment_data' => json_encode($request->except(['amount', '_token', 'id', 'nonce'])),
            ]);
            
            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    public function successPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';
        if ($data->status == 2) {
            $paymentData = json_decode($data->return_response);
            $transaction_id = $paymentData->id ?? '';
        }
        return view('payment-success', compact('id', 'transaction_id', 'data'));
    }

    public function declinedPayment($id)
    {
        $data = Payment::find($id);
        return view('payment-declined', compact('id', 'data'));
    }
}