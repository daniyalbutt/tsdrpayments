<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use SquareConnect\ApiClient;
use SquareConnect\Configuration;
use SquareConnect\Api\PaymentsApi;
use SquareConnect\Model\CreatePaymentRequest;
use SquareConnect\Model\Money;
use SquareConnect\Model\Address;

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
            // v3 square/connect configuration
            $config = Configuration::getDefaultConfiguration()
                ->setAccessToken($data->merchants->private_key);

            if ($data->merchants->sandbox == 1) {
                $config->setHost('https://connect.squareupsandbox.com');
            } else {
                $config->setHost('https://connect.squareup.com');
            }

            $apiClient  = new ApiClient($config);
            $paymentsApi = new PaymentsApi($apiClient);

            // Money
            $money = new Money();
            $money->setAmount((int)($data->price * 100));
            $money->setCurrency('USD');

            // Payment request
            $paymentRequest = new CreatePaymentRequest();
            $paymentRequest->setSourceId($request->input('nonce'));
            $paymentRequest->setIdempotencyKey(uniqid('payment_' . $data->id . '_', true));
            $paymentRequest->setAmountMoney($money);
            $paymentRequest->setAutocomplete(true);
            $paymentRequest->setLocationId($data->merchants->square_location_id);
            $paymentRequest->setReferenceId('invoice_' . $data->id);
            $paymentRequest->setBuyerEmailAddress($request->input('user_email'));
            $paymentRequest->setNote($data->package);

            // Billing address
            $address = new Address();
            $address->setAddressLine1($request->input('address'));
            $address->setLocality($request->input('city'));
            $address->setPostalCode($request->input('zip') ?: '00000');
            $address->setCountry($request->input('country'));

            if ($request->input('state')) {
                $address->setAdministrativeDistrictLevel1($request->input('state'));
            }

            $paymentRequest->setBillingAddress($address);

            $response = $paymentsApi->createPayment($paymentRequest);
            $result    = $response->getPayment();

            $data->update([
                'status'          => 2,
                'return_response' => json_encode($result),
                'payment_data'    => json_encode($payment_data),
                'square_response' => json_encode($result),
            ]);

            return redirect()->route('success.payment', ['id' => $data->id]);

        } catch (\SquareConnect\ApiException $e) {
            $errorBody = $e->getResponseBody();
            $errorMsg  = isset($errorBody->errors) 
                ? collect($errorBody->errors)->pluck('detail')->implode(', ') 
                : $e->getMessage();

            \Log::error('Square API Error: ' . $errorMsg);

            $data->update([
                'square_response' => json_encode($errorBody),
                'status'          => 1,
                'return_response' => $errorMsg,
                'payment_data'    => json_encode($request->except(['amount', '_token', 'id', 'nonce'])),
            ]);

            return redirect()->route('declined.payment', ['id' => $data->id]);

        } catch (Exception $e) {
            \Log::error('Square Exception: ' . $e->getMessage());

            $data->update([
                'square_response' => json_encode([]),
                'status'          => 1,
                'return_response' => $e->getMessage(),
                'payment_data'    => json_encode($request->except(['amount', '_token', 'id', 'nonce'])),
            ]);

            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    public function successPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';
        if ($data->status == 2) {
            $paymentData    = json_decode($data->return_response);
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