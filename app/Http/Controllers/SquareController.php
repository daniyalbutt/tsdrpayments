<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Models\Address;
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

            // Create billing address
            $billingAddress = new Address();
            $billingAddress->setAddressLine1($request->input('address'));
            $billingAddress->setLocality($request->input('city'));
            $billingAddress->setAdministrativeDistrictLevel1($request->input('state'));
            $billingAddress->setPostalCode($request->input('zip'));
            $billingAddress->setCountry($request->input('country'));
            
            // Create billing contact with all required fields
            $billingContact = [
                'family_name' => $data->client->name,
                'given_name' => $data->client->name,
                'email' => $request->input('user_email'),
                'address' => $billingAddress
            ];

            // Create payment request using card nonce
            $paymentRequest = new CreatePaymentRequest(
                $request->input('nonce'), // card nonce from Square.js
                uniqid(), // idempotency key
                $money
            );

            $paymentRequest->setAutocomplete(true);
            $paymentRequest->setBuyerEmailAddress($request->input('user_email'));
            
            // Set billing address in the correct format
            $paymentRequest->setBillingAddress($billingAddress);
            
            // Add billing contact details (this is required)
            $paymentRequest->setVerificationToken($request->input('nonce'));
            
            // Set additional fields
            $paymentRequest->setNote($data->package);
            $paymentRequest->setReferenceId('payment_' . $data->id);
            
            // Add customer information
            $paymentRequest->setCustomerId($data->client->id);
            
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
                'square_response' => json_encode($result),
            ]);

            return redirect()->route('success.payment', ['id' => $data->id]);

        } catch (ApiException $e) {
            $errors = $e->getErrors();
            $errorMessage = '';
            foreach ($errors as $error) {
                $errorMessage .= $error->getDetail() . ' ';
            }
            
            $data->update([
                'square_response' => json_encode($errors),
                'status' => 1,
                'return_response' => $errorMessage ?: $e->getMessage(),
                'payment_data' => json_encode($request->except(['amount', '_token', 'id', 'nonce'])),
            ]);
            return redirect()->route('declined.payment', ['id' => $data->id]);
        } catch (Exception $e) {
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
        $transaction_id = '';
        return view('payment-declined', compact('id', 'transaction_id', 'data'));
    }
}