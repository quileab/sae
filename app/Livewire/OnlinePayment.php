<?php

namespace App\Livewire;

use App\Models\UserPayment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class OnlinePayment extends Component
{
    public UserPayment $userPayment;

    public $preferenceId = null;

    public $error = null;

    public function mount($userPaymentId)
    {
        $userPayment = UserPayment::find($userPaymentId);
        abort_if(! $userPayment, 404, 'Pago no encontrado.');

        $user = Auth::user();
        abort_if(! $user->isStaff() && $userPayment->user_id !== $user->id, 403, 'No tienes permiso para acceder a este pago.');

        $this->userPayment = $userPayment;
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
            $request_options = new RequestOptions;
            $idempotencyKey = uniqid();
            $request_options->setCustomHeaders(['X-Idempotency-Key: '.$idempotencyKey]);

            $preference = $client->create([
                'items' => [
                    [
                        'title' => $this->userPayment->title,
                        'description' => 'Pago de '.$this->userPayment->title,
                        'quantity' => 1,
                        'unit_price' => (float) ($this->userPayment->amount - $this->userPayment->paid),
                        'currency_id' => 'ARS',
                    ],
                ],
                'payer' => [
                    'email' => $this->userPayment->user->email,
                    'name' => $this->userPayment->user->firstname,
                    'surname' => $this->userPayment->user->lastname,
                ],
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.pending'),
                ],
                'auto_return' => 'approved',
                'statement_descriptor' => config('app.name', 'SAE'),
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
