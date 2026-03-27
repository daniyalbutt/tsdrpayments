<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
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
                'environment' => $data->merchants->sandbox ? 'sandbox' : 'production',
            ]);

            $paymentsApi = $client->getPaymentsApi();

            // Prepare Money object
            $money = new Money();
            $money->setAmount((int)($data->price * 100)); // cents
            $money->setCurrency('USD');

            // Create payment request using card nonce
            $paymentRequest = new CreatePaymentRequest(
                $request->input('nonce'), // card nonce from Square.js
                uniqid(), // idempotency key
                $money
            );

            $paymentRequest->setAutocomplete(true);
            $paymentRequest->setBuyerEmailAddress($request->input('user_email'));
            $paymentRequest->setBillingAddress([
                'address_line_1' => $request->input('address'),
                'locality' => $request->input('city'),
                'administrative_district_level_1' => $request->input('state'),
                'postal_code' => $request->input('zip'),
                'country' => $request->input('country')
            ]);
            $paymentRequest->setNote($data->package);

            // Call createPayment
            $response = $paymentsApi->createPayment($paymentRequest);

            if ($response->isError()) {
                throw new Exception(json_encode($response->getErrors()));
            }

            $result = $response->getResult()->getPayment();

            $data->update([
                'status' => 2,
                'return_response' => json_encode($result),
                'payment_data' => json_encode($payment_data),
            ]);

            return redirect()->route('success.payment', ['id' => $data->id]);

        } catch (ApiException $e) {
            $data->update([
                'square_response' => json_encode($e->getErrors()),
                'status' => 1,
                'return_response' => $e->getMessage(),
                'payment_data' => $request->except(['amount', '_token', 'id']),
            ]);
            return redirect()->route('declined.payment', ['id' => $data->id]);
        } catch (Exception $e) {
            $data->update([
                'square_response' => json_encode([]),
                'status' => 1,
                'return_response' => $e->getMessage(),
                'payment_data' => $request->except(['amount', '_token', 'id']),
            ]);
            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    public function successPayment($id)
    {
        $data = Payment::find($id);
        $transaction_id = '';
        if ($data->status == 2) {
            $transaction_id = json_decode($data->return_response)->id ?? '';
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