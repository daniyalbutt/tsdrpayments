<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use Square\SquareClient;
use Square\Environments;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Models\Address;

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
            $client = new SquareClient(
                token: $data->merchants->private_key,
                options: [
                    'baseUrl' => $data->merchants->sandbox == 1
                        ? Environments::Sandbox->value
                        : Environments::Production->value,
                ]
            );

            // ✅ v45+ way — access payments directly
            $paymentsApi = $client->payments;

            // Prepare Money object
            $money = new Money();
            $money->setAmount((int)($data->price * 100));
            $money->setCurrency('USD');

            // Create payment request
            $paymentRequest = new CreatePaymentRequest(
                sourceId: $request->input('nonce'),
                idempotencyKey: uniqid('payment_' . $data->id . '_', true),
                amountMoney: $money
            );

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

            $paymentRequest->setMetadata([
                'invoice_id'    => (string)$data->id,
                'package'       => $data->package ?? '',
                'customer_name' => $request->input('user_name'),
            ]);

            // ✅ v45+ way — call createPayment on $client->payments
            $response = $paymentsApi->createPayment($paymentRequest);

            $result = $response->getPayment();

            $data->update([
                'status'          => 2,
                'return_response' => json_encode($result),
                'payment_data'    => json_encode($payment_data),
                'square_response' => json_encode($result),
            ]);

            return redirect()->route('success.payment', ['id' => $data->id]);

        } catch (\Square\Exceptions\SquareException $e) {
            \Log::error('Square API Error: ' . $e->getMessage());

            $data->update([
                'square_response' => json_encode($e->getMessage()),
                'status'          => 1,
                'return_response' => $e->getMessage(),
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