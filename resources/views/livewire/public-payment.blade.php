<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\UserPayments;
use Mary\Traits\Toast;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

new #[Layout('layouts.guest')] class extends Component {
    use Toast;

    public string $email = '';
    public string $student_id = '';
    public ?User $user = null;
    public ?UserPayments $nextPayment = null;
    public ?string $preferenceId = null;
    public ?string $checkoutUrl = null;
    public ?string $error = null;

    public function search()
    {
        $this->validate([
            'email' => 'required|email',
            'student_id' => 'required|numeric',
        ]);

        $this->user = User::where('email', $this->email)
            ->where('id', $this->student_id)
            ->first();

        if (!$this->user) {
            $this->error('No se encontró ningún estudiante con esos datos.');
            $this->nextPayment = null;
            $this->preferenceId = null;
            $this->checkoutUrl = null;
            return;
        }

        $this->nextPayment = UserPayments::where('user_id', $this->user->id)
            ->whereRaw('paid < amount')
            ->orderBy('date')
            ->first();

        if ($this->nextPayment) {
            $this->createPreference();
        } else {
            $this->info('No tienes cuotas pendientes de pago.');
        }
    }

    public function createPreference()
    {
        $accessToken = config('mercadopago.access_token');
        if (empty($accessToken)) {
            $this->error = 'El sistema de pagos no está configurado correctamente.';
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
                        'title' => $this->nextPayment->title,
                        'quantity' => 1,
                        'unit_price' => (float) ($this->nextPayment->amount - $this->nextPayment->paid),
                        'currency_id' => 'ARS',
                    ],
                ],
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.pending'),
                ],
                'payer' => [
                    'email' => $this->user->email,
                    'first_name' => $this->user->firstname,
                    'last_name' => $this->user->lastname,
                    'identification' => [
                        'type' => 'DNI',
                        'number' => (string) $this->student_id,
                    ],
                ],
                'external_reference' => (string) $this->nextPayment->id,
                'notification_url' => route('mercadopago.webhook'),
            ], $request_options);

            if ($preference->id) {
                $this->preferenceId = $preference->id;
                // Use sandbox URL for local/testing, production URL otherwise
                $this->checkoutUrl = app()->isLocal()
                    ? $preference->sandbox_init_point
                    : $preference->init_point;
            } else {
                $this->error = 'Error al generar el ID de preferencia.';
            }

        } catch (\Exception $e) {
            $this->error = 'Error: ' . $e->getMessage();
        }
    }

    public function maskName($name)
    {
        if (strlen($name) <= 2) return $name;
        return substr($name, 0, 1) . '...' . substr($name, -1);
    }

    public function with(): array
    {
        return [
            'maskedFirstname' => $this->user ? $this->maskName($this->user->firstname) : '',
            'maskedLastname' => $this->user ? $this->maskName($this->user->lastname) : '',
        ];
    }
}; ?>

<div class="flex items-center justify-center min-h-screen bg-base-200">
    <div class="w-full max-w-md p-6">
        <x-card shadow class="bg-base-100">
            <div class="mb-6 text-center">
                <x-icon name="o-credit-card" class="w-12 h-12 mb-2 text-primary" />
                <h2 class="text-2xl font-bold">Pago Online</h2>
                <p class="text-sm text-gray-500">Consulta y paga tu próxima cuota</p>
            </div>

            @if(!$user)
                <form wire:submit.prevent="search" class="space-y-4">
                    <x-input label="Email" wire:model="email" icon="o-envelope" placeholder="tu@email.com" />
                    <x-input label="ID Estudiante / DNI" wire:model="student_id" icon="o-identification" placeholder="Número de ID o DNI" />
                    
                    <x-button label="Buscar" type="submit" class="w-full btn-primary" spinner="search" />
                </form>
            @else
                <div class="space-y-6">
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="text-xs font-semibold uppercase text-gray-500">Estudiante</div>
                        <div class="text-lg font-bold">
                            {{ $maskedLastname }}, {{ $maskedFirstname }}
                        </div>
                    </div>

                    @if($nextPayment)
                        <div class="p-4 border border-primary/20 rounded-lg bg-primary/5">
                            <div class="text-xs font-semibold uppercase text-primary/70 text-right">Cuota a Pagar</div>
                            <div class="text-xl font-bold">{{ $nextPayment->title }}</div>
                            
                            <div class="mt-2 text-3xl font-black text-primary text-right">
                                $ {{ number_format($nextPayment->amount - $nextPayment->paid, 2, ',', '.') }}
                            </div>
                        </div>

                        {{-- Diagnóstico temporal --}}
                        @if(config('app.debug'))
                            <div class="mt-2 text-[10px] text-gray-400 font-mono text-center">
                                preferenceId: {{ $preferenceId ?: 'null' }}
                            </div>
                        @endif

                        @if($error)
                            <x-alert icon="o-exclamation-triangle" class="alert-error">
                                {{ $error }}
                            </x-alert>
                        @endif

                        @if($checkoutUrl)
                            <a href="{{ $checkoutUrl }}"
                               target="_blank"
                               class="btn btn-primary w-full gap-2 mt-4">
                                <x-icon name="o-credit-card" class="w-5 h-5" />
                                Pagar con Mercado Pago
                            </a>
                        @elseif($preferenceId)
                            <x-button label="Generando enlace de pago..." class="w-full btn-disabled mt-4" loading />
                        @endif


                    @else
                        <x-alert icon="o-check-circle" class="alert-success">
                            No tienes cuotas pendientes de pago.
                        </x-alert>
                    @endif

                    <x-button label="Buscar otro estudiante" wire:click="$set('user', null)" class="w-full btn-ghost btn-sm" />
                </div>
            @endif
        </x-card>
    </div>
</div>
