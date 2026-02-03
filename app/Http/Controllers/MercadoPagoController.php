<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoController extends Controller
{
    public function success(Request $request)
    {
        // Logic for successful payment
        return redirect('/my-payment-plan')->with('success', '¡Pago realizado con éxito!');
    }

    public function failure(Request $request)
    {
        // Logic for failed payment
        return redirect('/my-payment-plan')->with('error', 'El pago falló. Por favor, intenta de nuevo.');
    }

    public function pending(Request $request)
    {
        // Logic for pending payment
        return redirect('/my-payment-plan')->with('info', 'El pago está pendiente de procesamiento.');
    }

    public function webhook(Request $request)
    {
        ini_set('max_execution_time', 300);

        \Illuminate\Support\Facades\Log::info('MercadoPago Webhook Received', ['data' => $request->all()]);

        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        // Handle both "payment" (v1) and "topic" (legacy) webhooks
        $paymentId = $request->input('data.id') ?? $request->input('id');
        $type = $request->input('type') ?? $request->input('topic');

        if ($type !== 'payment') {
            \Illuminate\Support\Facades\Log::info('MercadoPago Webhook: Ignored notification type', ['type' => $type]);

            // Always return 200 to acknowledge receipt, otherwise MP keeps retrying
            return response()->json(['status' => 'success', 'message' => 'Ignored type'], 200);
        }

        if (! $paymentId) {
            \Illuminate\Support\Facades\Log::warning('MercadoPago Webhook: No payment ID provided');

            return response()->json(['status' => 'error', 'message' => 'Payment ID not provided'], 400);
        }

        try {
            $client = new \MercadoPago\Client\Payment\PaymentClient;
            $payment = $client->get($paymentId);

            if ($payment && $payment->status == 'approved') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
                    // Idempotency Check
                    $existingRecord = \App\Models\PaymentRecord::where('description', 'LIKE', '%ID: '.$payment->id.'%')->first();
                    if ($existingRecord) {
                        \Illuminate\Support\Facades\Log::info('MercadoPago Webhook: Payment already recorded', ['payment_id' => $payment->id]);

                        return;
                    }

                    $userPayment = \App\Models\UserPayments::lockForUpdate()->find($payment->external_reference);

                    if ($userPayment) {
                        // Prevent overpayment logic if needed, but primarily record the payment
                        $userPayment->paid += $payment->transaction_amount;
                        $userPayment->save();

                        \App\Models\PaymentRecord::create([
                            'userpayments_id' => $userPayment->id,
                            'user_id' => $userPayment->user_id,
                            'paymentBox' => 'Mercado Pago',
                            'description' => 'Pago online via Mercado Pago - ID: '.$payment->id,
                            'paymentAmount' => $payment->transaction_amount,
                        ]);

                        \Illuminate\Support\Facades\Log::info('MercadoPago Webhook: Payment recorded successfully', ['payment_id' => $payment->id, 'user_payment_id' => $userPayment->id]);
                    } else {
                        \Illuminate\Support\Facades\Log::error('MercadoPago Webhook: UserPayment not found for external_reference', ['external_reference' => $payment->external_reference]);
                    }
                });
            }

            return response()->json(['status' => 'success'], 200);

        } catch (MPApiException $e) {
            \Illuminate\Support\Facades\Log::error('MercadoPago Webhook MPApiException', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MercadoPago Webhook Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
