<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

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

        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        $paymentId = $request->input('data.id');

        if (!$paymentId) {
            return response()->json(['status' => 'error', 'message' => 'Payment ID not provided'], 400);
        }

        try {
            $client = new \MercadoPago\Client\Payment\PaymentClient();
            $payment = $client->get($paymentId);

            if ($payment && $payment->status == 'approved') {
                $userPayment = \App\Models\UserPayments::find($payment->external_reference);

                if ($userPayment && $userPayment->paid < $userPayment->amount) {
                    $userPayment->paid += $payment->transaction_amount;
                    $userPayment->save();

                    \App\Models\PaymentRecord::create([
                        'userpayments_id' => $userPayment->id,
                        'user_id' => $userPayment->user_id,
                        'paymentBox' => 'Mercado Pago',
                        'description' => 'Pago online via Mercado Pago - ID: ' . $payment->id,
                        'paymentAmount' => $payment->transaction_amount,
                    ]);
                }
            }

            return response()->json(['status' => 'success'], 200);

        } catch (MPApiException $e) {
            // Log error
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            // Log error
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}