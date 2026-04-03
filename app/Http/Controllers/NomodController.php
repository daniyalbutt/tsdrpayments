<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;

class NomodController extends Controller
{
    public function processPayment(Request $request)
    {
        $request->validate([
            'user_name'  => 'required|string|max:255',
            'user_email' => 'required|email|max:255',
            'amount'     => 'required|numeric|min:0.01',
        ], [
            'user_name.required'  => 'Full name is required.',
            'user_email.required' => 'Email address is required.',
            'user_email.email'    => 'Please enter a valid email address.',
            'amount.required'     => 'Amount is required.',
        ]);

        $input_request = $request->input();
        $data = Payment::find($request->input('id'));

        if ($data->status == 0) {
            try {
                $nameParts = explode(' ', trim($request->input('user_name')), 2);
                $firstName = $nameParts[0] ?? '';
                $lastName  = $nameParts[1] ?? '';

                $payload = [
                    'merchant_name'    => $data->merchants->name, // or a fixed merchant name
                    'amount'           => number_format($data->price, 2, '.', ''),
                    'currency'         => 'USD', // adjust as needed
                    'description'      => $data->package ?? 'Payment',
                    'customer'         => [
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'email'      => $request->input('user_email'),
                    ],
                    'reference_id'     => (string) $data->id,
                ];

                $response = $this->callNomodApi(
                    $data->merchants->public_key,
                    $payload
                );

                // Nomod returns HTTP 201 on success
                if (isset($response['id'])) {
                    $data->update([
                        'status'          => 2,
                        'return_response' => json_encode($response),
                        'payment_data'    => json_encode($input_request),
                    ]);

                    return redirect()->route('success.payment', ['id' => $data->id]);

                } else {
                    $data->update([
                        'status'          => 1,
                        'return_response' => $response['message'] ?? 'Transaction Declined',
                        'payment_data'    => json_encode($input_request),
                        'square_response' => json_encode($response),
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

    private function callNomodApi(string $apiKey, array $payload): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.nomod.com/v1/invoices');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-KEY: ' . $apiKey,
        ]);

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Nomod API.');
        }

        return $decoded;
    }

    public function successPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';

        if ($data->status == 2) {
            $decoded = json_decode($data->return_response, true);
            $transaction_id = $decoded['id'] ?? ''; // Nomod returns invoice id
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