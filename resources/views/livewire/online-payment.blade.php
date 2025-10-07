<?php

use Livewire\Volt\Component;
use App\Models\UserPayments;
use Illuminate\Support\Facades\Http;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

new class extends Component {
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
            $client = new PreferenceClient();
            $request_options = new \MercadoPago\Client\Common\RequestOptions();
            $idempotencyKey = uniqid();
            $request_options->setCustomHeaders(["X-Idempotency-Key: " . $idempotencyKey]);

            $preference = $client->create([
                'items' => [
                    [
                        'title' => $this->userPayment->title,
                        'quantity' => 1,
                        'unit_price' => $this->userPayment->amount - $this->userPayment->paid,
                        'currency_id' => 'ARS'
                    ]
                ],
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.pending')
                ],
                'external_reference' => $this->userPayment->id,
                'notification_url' => route('mercadopago.webhook')
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
}; ?>

<div>
    <a
        href="{{ $preferenceId ? 'https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=' . $preferenceId : '#' }}"
        @if(!$preferenceId) disabled @endif
        class="btn btn-primary"
    >
        <x-icon name="o-currency-dollar" class="w-5 h-5" />
        {{ __('Pagar con Mercado Pago') }}
    </a>
    @if($error)
        <p class="text-red-500">{{ $error }}</p>
    @endif
</div>