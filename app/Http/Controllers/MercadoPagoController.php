<?php

namespace App\Http\Controllers;

use App\Models\PaymentRecord;
use App\Models\UserPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoController extends Controller
{
    public function success(Request $request)
    {
        // Logic for successful payment
        $redirectUrl = Auth::check() ? '/my-payment-plan' : '/pago-online';

        return redirect($redirectUrl)->with('success', '¡Pago realizado con éxito!');
    }

    public function failure(Request $request)
    {
        // Logic for failed payment
        $redirectUrl = Auth::check() ? '/my-payment-plan' : '/pago-online';

        return redirect($redirectUrl)->with('error', 'El pago falló. Por favor, intenta de nuevo.');
    }

    public function pending(Request $request)
    {
        // Logic for pending payment
        $redirectUrl = Auth::check() ? '/my-payment-plan' : '/pago-online';

        return redirect($redirectUrl)->with('info', 'El pago está pendiente de procesamiento.');
    }

    public function webhook(Request $request)
    {
        ini_set('max_execution_time', 300);

        Log::info('MercadoPago Webhook Received', [
            'headers' => $request->headers->all(),
            'data' => $request->all(),
            'query' => $request->query(),
        ]);

        // 1. Signature Verification
        if (! $this->verifySignature($request)) {
            Log::warning('MercadoPago Webhook: Invalid signature');

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        // Handle both "payment" (v1) and "topic" (legacy) webhooks
        $paymentId = $request->input('data.id') ?? $request->input('id');
        $type = $request->input('type') ?? $request->input('topic');

        if ($type !== 'payment') {
            Log::info('MercadoPago Webhook: Ignored notification type', ['type' => $type]);

            // Always return 200 to acknowledge receipt, otherwise MP keeps retrying
            return response()->json(['status' => 'success', 'message' => 'Ignored type'], 200);
        }

        if (! $paymentId) {
            Log::warning('MercadoPago Webhook: No payment ID provided');

            return response()->json(['status' => 'error', 'message' => 'Payment ID not provided'], 400);
        }

        try {
            $client = app(PaymentClient::class);
            $payment = $client->get($paymentId);

            if ($payment && $payment->status == 'approved') {
                DB::transaction(function () use ($payment) {
                    // Idempotency Check using agnostic column
                    $existingRecord = PaymentRecord::where('paymentBox', 'Mercado Pago')
                        ->where('transaction_id', $payment->id)
                        ->first();

                    if ($existingRecord) {
                        Log::info('MercadoPago Webhook: Payment already recorded', ['payment_id' => $payment->id]);

                        return;
                    }

                    $userPayment = UserPayment::lockForUpdate()->find($payment->external_reference);

                    if ($userPayment) {
                        $userPayment->paid += $payment->transaction_amount;
                        $userPayment->save();

                        PaymentRecord::create([
                            'userpayments_id' => $userPayment->id,
                            'user_id' => $userPayment->user_id,
                            'paymentBox' => 'Mercado Pago',
                            'description' => 'Pago online via Mercado Pago - ID: '.$payment->id,
                            'paymentAmount' => $payment->transaction_amount,
                            'transaction_id' => $payment->id,
                        ]);

                        Log::info('MercadoPago Webhook: Payment recorded successfully', ['payment_id' => $payment->id, 'user_payment_id' => $userPayment->id]);
                    } else {
                        Log::error('MercadoPago Webhook: UserPayment not found for external_reference', ['external_reference' => $payment->external_reference]);
                    }
                });
            }

            return response()->json(['status' => 'success'], 200);

        } catch (MPApiException $e) {
            Log::error('MercadoPago Webhook MPApiException', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('MercadoPago Webhook Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify the Mercado Pago webhook signature.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('mercadopago.webhook_secret');

        if (! $secret) {
            // If no secret is configured, we might be in testing or local env without webhooks setup
            // In production, this should be mandatory.
            return app()->isLocal() || app()->runningUnitTests();
        }

        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');

        if (! $xSignature || ! $xRequestId) {
            return false;
        }

        // Extract "data.id" from the query params (Mercado Pago sends it there for signature)
        $dataId = $request->query('data_id') ?? $request->query('data.id') ?? $request->query('id');

        // Parse x-signature (ts=...,v1=...)
        $parts = explode(',', $xSignature);
        $ts = null;
        $v1 = null;

        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $key = trim($kv[0]);
                $value = trim($kv[1]);
                if ($key === 'ts') {
                    $ts = $value;
                } elseif ($key === 'v1') {
                    $v1 = $value;
                }
            }
        }

        if (! $ts || ! $v1) {
            return false;
        }

        // Generate the manifest string: id:[data.id_url];request-id:[x-request-id_header];ts:[ts_header];
        $manifest = "id:$dataId;request-id:$xRequestId;ts:$ts;";

        // Create HMAC SHA256
        $expectedHash = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expectedHash, $v1);
    }
}
