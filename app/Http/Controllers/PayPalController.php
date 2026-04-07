<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Payment;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller
{
    /**
     * Build a PayPal client using the merchant's own keys (same pattern as Stripe).
     */
    private function makeClient(Payment $data): PayPalClient
    {
        $mode = ($data->merchants->sandbox == 1) ? 'sandbox' : 'live';

        config([
            'paypal.mode'                        => $mode,
            'paypal.' . $mode . '.client_id'     => trim($data->merchants->public_key),
            'paypal.' . $mode . '.client_secret' => trim($data->merchants->private_key),
            'paypal.' . $mode . '.app_id'        => '',
            'paypal.payment_action'              => 'Sale',
            'paypal.currency'                    => 'USD',
            'paypal.notify_url'                  => '',
            'paypal.locale'                      => 'en_US',
            'paypal.validate_ssl'                => true,
            'paypal.webhook_id'                  => '',
        ]);

        $client = new PayPalClient;
        $client->setApiCredentials(config('paypal'));
        $client->getAccessToken();

        return $client;
    }

    /**
     * Step 1 — Create a PayPal order and redirect the user to PayPal.
     * Route: POST /paypal/create/{id}
     */
    public function createOrder(Request $request, $id)
    {
        $data = Payment::find($id);

        if (!$data || $data->status != 0) {
            return redirect()->route('declined.payment', ['id' => $id]);
        }

        try {
            $client = $this->makeClient($data);

            $order = $client->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $data->id,
                    'description'  => $data->package ?? 'Payment',
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => number_format($data->price, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => route('paypal.capture', ['id' => $data->id]),
                    'cancel_url' => route('paypal.cancel',  ['id' => $data->id]),
                    'brand_name' => $data->client->brand->name ?? config('app.name'),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

            // Store PayPal order ID so we can capture it on return
            $data->update([
                'authorize_response' => json_encode(['paypal_order_id' => $order['id']]),
            ]);

            // Redirect user to PayPal approval page
            foreach ($order['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return redirect()->away($link['href']);
                }
            }

            throw new Exception('PayPal approval URL not found in response.');

        } catch (Exception $e) {
            $data->update([
                'status'          => 1,
                'return_response' => $e->getMessage(),
                'payment_data'    => json_encode($request->all()),
            ]);

            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    /**
     * Step 2 — PayPal redirects back here after user approves.
     * Route: GET /paypal/capture/{id}
     */
    public function captureOrder(Request $request, $id)
    {
        $data = Payment::find($id);

        if (!$data || $data->status != 0) {
            return redirect()->route('declined.payment', ['id' => $id]);
        }

        try {
            $stored        = json_decode($data->authorize_response, true);
            $paypalOrderId = $stored['paypal_order_id'] ?? $request->query('token');

            if (!$paypalOrderId) {
                throw new Exception('PayPal order ID missing.');
            }

            $client   = $this->makeClient($data);
            $response = $client->capturePaymentOrder($paypalOrderId);

            if (
                isset($response['status']) &&
                $response['status'] === 'COMPLETED'
            ) {
                $data->update([
                    'status'          => 2,
                    'return_response' => 'Payment Successful - ' . $response['id'],
                    'authorize_response' => json_encode($response),
                    'payment_data'    => json_encode($request->all()),
                ]);

                return redirect()->route('success.payment', ['id' => $data->id]);
            }

            // Captured but not COMPLETED (e.g. PENDING)
            $data->update([
                'status'          => 1,
                'return_response' => 'PayPal payment status: ' . ($response['status'] ?? 'UNKNOWN'),
                'authorize_response' => json_encode($response),
                'payment_data'    => json_encode($request->all()),
            ]);

            return redirect()->route('declined.payment', ['id' => $data->id]);

        } catch (Exception $e) {
            $data->update([
                'status'          => 1,
                'return_response' => $e->getMessage(),
                'payment_data'    => json_encode($request->all()),
            ]);

            return redirect()->route('declined.payment', ['id' => $data->id]);
        }
    }

    /**
     * User cancelled on PayPal's page.
     * Route: GET /paypal/cancel/{id}
     */
    public function cancelOrder(Request $request, $id)
    {
        $data = Payment::find($id);

        if ($data && $data->status == 0) {
            $data->update([
                'status'          => 1,
                'return_response' => 'Payment cancelled by user.',
                'payment_data'    => json_encode($request->all()),
            ]);
        }

        return redirect()->route('declined.payment', ['id' => $id]);
    }
}