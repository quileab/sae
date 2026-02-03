<?php

namespace App\Livewire;

use App\Models\UserPayments;
use Livewire\Component;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class OnlinePayment extends Component
{
    public UserPayments $userPayment;

    public $preferenceId = null;

    public $error = null;

    public function mount($userPaymentId)
    {
        $this->userPayment = UserPayments::find($userPaymentId);
        $this->createAndDispatchPreference();
    }

    public function createAndDispatchPreference()
    {
        $accessToken = config('mercadopago.access_token');
        if (empty($accessToken)) {
            $this->error = 'Mercado Pago Access Token is not set.';

            return;
        }

        try {
            MercadoPagoConfig::setAccessToken($accessToken);
            $client = new PreferenceClient;
            $request_options = new \MercadoPago\Client\Common\RequestOptions;
            $idempotencyKey = uniqid();
            $request_options->setCustomHeaders(['X-Idempotency-Key: '.$idempotencyKey]);

            $preference = $client->create([
                'items' => [
                    [
                        'title' => $this->userPayment->title,
                        'quantity' => 1,
                        'unit_price' => (float) ($this->userPayment->amount - $this->userPayment->paid),
                        'currency_id' => 'ARS',
                    ],
                ],
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.pending'),
                ],
                'external_reference' => $this->userPayment->id,
                'notification_url' => route('mercadopago.webhook'),
            ], $request_options);

            if ($preference->id) {
                $this->preferenceId = $preference->id;
            } else {
                $this->error = 'Preference ID returned from API was null.';
            }

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.online-payment');
    }
}
