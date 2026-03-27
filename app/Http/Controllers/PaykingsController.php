<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;

class PaykingsController extends Controller
{
    public function processPayment(Request $request)
    {
        $request->merge([
            'cardnumber' => preg_replace('/\s+/', '', $request->input('cardnumber'))
        ]);
        $request->validate([
            'user_name'   => 'required|string|max:255',
            'user_email'  => 'required|email|max:255',
            'cardnumber'  => 'required|digits_between:13,19',
            'exp_month'   => 'required|digits:2|between:1,12',
            'exp_year'    => 'required|digits:2',
            'cvv'         => 'required|digits_between:3,4',
            'owner'       => 'required|string|max:255',
            'country'     => 'required|string|max:100',
            'city'        => 'required|string|max:100',
            'zip'         => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'state'       => 'nullable|string|max:100',
            'set_state'   => 'nullable|string|max:100',
        ], [
            'user_name.required'  => 'Full name is required.',
            'user_email.required' => 'Email address is required.',
            'user_email.email'    => 'Please enter a valid email address.',
            'cardnumber.required' => 'Card number is required.',
            'cardnumber.digits_between' => 'Card number must be between 13 and 19 digits.',
            'exp_month.required'  => 'Expiry month is required.',
            'exp_month.digits'    => 'Expiry month must be 2 digits.',
            'exp_month.between'   => 'Expiry month must be between 01 and 12.',
            'exp_year.required'   => 'Expiry year is required.',
            'exp_year.digits'     => 'Expiry year must be 2 digits.',
            'cvv.required'        => 'CVV is required.',
            'cvv.digits_between'  => 'CVV must be 3 or 4 digits.',
            'owner.required'      => 'Name on card is required.',
            'country.required'    => 'Country is required.',
            'city.required'       => 'City is required.',
            'zip.required'        => 'ZIP code is required.',
            'address.required'    => 'Address is required.',
        ]);

        // Validate card is not expired
        $currentYear  = (int) date('y');
        $currentMonth = (int) date('n');
        $expMonth     = (int) $request->input('exp_month');
        $expYear      = (int) $request->input('exp_year');

        if ($expYear < $currentYear || ($expYear == $currentYear && $expMonth < $currentMonth)) {
            return back()->withErrors(['exp_month' => 'Your card has expired.'])->withInput();
        }

        // Validate at least one of state or set_state is present
        if (empty($request->input('state')) && empty($request->input('set_state'))) {
            return back()->withErrors(['state' => 'State is required.'])->withInput();
        }

        $input_request = $request->input();
        $data = Payment::find($request->input('id'));

        if ($data->status == 0) {
            try {
                $nameParts = explode(' ', trim($request->input('owner')), 2);
                $firstName = $nameParts[0] ?? '';
                $lastName  = $nameParts[1] ?? '';

                $cardNumber = preg_replace('/\s+/', '', $request->input('cardnumber'));
                $expMonth   = str_pad($request->input('exp_month'), 2, '0', STR_PAD_LEFT);
                $expYear    = $request->input('exp_year');
                $ccexp      = $expMonth . $expYear;

                $query = http_build_query([
                    'security_key'     => $data->merchants->public_key,
                    'ccnumber'         => $cardNumber,
                    'ccexp'            => $ccexp,
                    'cvv'              => $request->input('cvv'),
                    'amount'           => number_format($data->price, 2, '.', ''),
                    'ipaddress'        => $request->ip(),
                    'orderid'          => $data->id,
                    'orderdescription' => $data->package ?? 'Payment',
                    'tax'              => '0.00',
                    'shipping'         => '0.00',
                    'ponumber'         => '',
                    'firstname'        => $firstName,
                    'lastname'         => $lastName,
                    'company'          => '',
                    'address1'         => $request->input('address'),
                    'address2'         => '',
                    'city'             => $request->input('city'),
                    'state'            => $request->input('state') ?? $request->input('set_state'),
                    'zip'              => $request->input('zip'),
                    'country'          => $request->input('country'),
                    'phone'            => '',
                    'fax'              => '',
                    'email'            => $request->input('user_email'),
                    'website'          => '',
                    'type'             => 'sale',
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://paykings.transactiongateway.com/api/transact.php');
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);

                $rawResponse = curl_exec($ch);

                if ($rawResponse === false) {
                    throw new Exception('cURL Error: ' . curl_error($ch));
                }

                curl_close($ch);

                parse_str($rawResponse, $responseData);

                // 1 = Approved, 2 = Declined, 3 = Error
                if (($responseData['response'] ?? null) == 1) {
                    $data->update([
                        'status'          => 2,
                        'return_response' => json_encode($responseData),
                        'payment_data'    => json_encode($input_request),
                    ]);

                    return redirect()->route('success.payment', ['id' => $data->id]);

                } else {
                    $data->update([
                        'status'          => 1,
                        'return_response' => $responseData['responsetext'] ?? 'Transaction Declined',
                        'payment_data'    => json_encode($input_request),
                        'square_response' => json_encode($responseData),
                    ]);

                    return redirect()->route('declined.payment', ['id' => $data->id]);
                }

            } catch (Exception $e) {
                $data->update([
                    'status'          => 1,
                    'return_response' => $e->getMessage(),
                    'payment_data'    => json_encode($input_request),
                ]);

                return redirect()->route('declined.payment', ['id' => $data->id]);
            }

        } else {
            if ($data->status == 2) {
                return redirect()->route('success.payment', ['id' => $data->id]);
            }
            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    public function successPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';

        if ($data->status == 2) {
            $decoded = json_decode($data->return_response, true);
            $transaction_id = $decoded['transactionid'] ?? '';
        }

        return view('payment-success', compact('id', 'transaction_id', 'data'));
    }

    public function declinedPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';
        return view('payment-declined', compact('id', 'transaction_id'));
    }
}