<div>
  @if($plansError)
    <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4">
      Error: No se pudo cargar la configuración de planes de pago (Tabla 'plans_masters' no encontrada).
    </x-alert>
  @endif

  {{-- CRUD Plans Form --}}
  <x-modal wire:model="openModal" title="{{ $user->lastname }}, {{ $user->firstname }}" subtitle="ID: {{ $userId }}"
    separator>
    <div class="flex items-center justify-evenly">
      <x-select wire:model.live="selectedPlan" label="{{ __('Seleccionar Plan') }}" :options="$payPlans"
        option-value="id" option-label="title" />

      {{-- checkbox to combine with other plans --}}
      <x-checkbox wire:model="combinePlans" label="{{ __('Combinar con otros planes') }}" />
    </div>

    <x-slot:actions>
      <x-button label="{{ __('Salir') }}" wire:click="$toggle('openModal')" wire:loading.attr="disabled"
        class="btn-secondary" />
      <x-button label="{{ __('Asignar Plan') }}" wire:click="assignPayPlan" class="btn-primary" />
    </x-slot:actions>
  </x-modal>

  {{-- Payment Entry Form --}}
  <x-modal wire:model="paymentModal" title="{{ $user->lastname }}, {{ $user->firstname }}" subtitle="ID: {{ $userId }}"
    separator>
    <p>{{ __('Pagando:') }} <strong>{{ $paymentDescription }}</strong> » {{ __('Valor:') }} <strong>$
        {{ number_format($paymentAmountPaid, 2) }}</strong></p>

    @if(auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']))
      <x-input type="number" wire:model.defer="paymentAmountInput" placeholder="{{ __('Ingresar monto') }}" />
    @endif

    <x-slot:actions>
      @if(auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']))
        <x-button label="{{ __('Ingresar Pago') }}" wire:click="registerUserPayment" class="btn-success" />
      @endif
      <x-button label="{{ __('Salir') }}" wire:click="$toggle('paymentModal')" wire:loading.attr="disabled"
        class="btn-secondary" />
    </x-slot:actions>
  </x-modal>

  {{-- Amount Modification/Deletion Form --}}
  <x-modal wire:model="modifyPaymentModal"
    title="{{ __('ID de Pago:') }} {{ $paymentId }} » {{ $user->lastname }}, {{ $user->firstname }}" subtitle=""
    separator>
    <div class="flex items-center justify-evenly">
      <p>{{ __('Modificar monto a pagar:') }} <strong>{{ $paymentDescription }}</strong>
        <br />{{ __('A Pagar:') }} <strong>$ {{ number_format($paymentAmountPaid, 2) }}</strong>
        <br />{{ __('Pagado:') }} <strong>$ {{ number_format($totalPaid, 2) }}</strong>
      </p>
      <x-input type="number" wire:model.defer="totalDebt" />
      <x-button label="{{ __('Modificar') }}" wire:click="modifyAmount({{$paymentId}})" class="btn-primary" />
    </div>

    <x-slot:actions>
      <x-button label="{{ __('Eliminar') }}" wire:click="deletePayment({{ $paymentId }})" class="btn-error" />
      <x-button label="{{ __('Salir') }}" wire:click="$toggle('modifyPaymentModal')" wire:loading.attr="disabled"
        class="btn-secondary" />
    </x-slot:actions>
  </x-modal>

  <div class="mb-2 overflow-hidden bg-gray-200/10 rounded-md shadow-md">
    <div class="flex items-center justify-between w-full px-4 py-2">
      <h1 class="text-xl">
        <strong>{{ ucfirst($user->lastname) }}</strong>,
        {{ ucfirst($user->firstname) }}
        <small class="text-primary">(# {{ $user->id }})</small>
      </h1>

      @if(auth()->user()->hasRole('student') && $nextPaymentToPay)
        <div class="flex items-center space-x-4">
          <div class="text-right">
            <p><strong>Pagar Cuota: {{ $nextPaymentToPay->title }}</strong></p>
            <p>${{ number_format($nextPaymentToPay->amount - $nextPaymentToPay->paid, 2) }}</p>
          </div>
          <livewire:online-payment :userPaymentId="$nextPaymentToPay->id" />
        </div>
      @endif

      {{-- Assign new payment plan --}}
      <div>
        @if(auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']))
          @if ($hasCounter > 0)
            @if ($totalPaid < $totalDebt)
              <x-button wire:click="addPaymentToUser" icon="o-currency-dollar" label="{{ __('Ingresar Pago') }}"
                class="btn-success" />&nbsp;
            @endif
            <a href="{{ route('payments-details', $user->id) }}" class="ml-2">
              <x-button icon="o-list-bullet" label="{{ __('Ver Pagos') }}" class="btn-primary" />
            </a>
          @endif
          <x-button wire:click="$set('openModal',true)" icon="o-plus-circle" label="{{ __('Agregar Plan') }}"
            class="btn-primary" />
        @endif
      </div>
    </div>

    @if (session('success'))
      <x-alert icon="o-information-circle" class="alert-success">
        {{ session('success') }} - El pago puede tardar unos minutos en actualizarse.
      </x-alert>
    @endif

    @if (session('error'))
      <x-alert icon="o-exclamation-triangle" class="alert-error">
        {{ session('error') }}
      </x-alert>
    @endif

    @if (session('info'))
      <x-alert icon="o-bell" class="alert-info">
        {{ session('info') }}
      </x-alert>
    @endif

    <div class="container px-3 mx-auto my-3 md:px-6">
      @foreach ($userPayments as $userPayment)
        @if(auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']))
          <button wire:click="handleInstallmentClick({{$userPayment}})">
            <div class="inline-block w-32 m-1 overflow-hidden text-sm uppercase bg-gray-700 rounded-md shadow-lg">
              <div class="{{$userPayment->bgColor}} w-full text-center p-1">
                {{ $userPayment->title }}
                <p class="text-xs">{{ \Carbon\Carbon::parse($userPayment->date)->format('m-Y') }}</p>
              </div>
              <div class="px-2 py-1">
                <div class="text-right">
                  <p class="text-base">$ {{ number_format($userPayment->paid, 2) }}</p>
                  <p class="{{$userPayment->textColor}} text-xs">$ {{ number_format($userPayment->amount, 2) }}</p>
                </div>
              </div>
            </div>
          </button>
        @else
          <div class="inline-block w-32 m-1 overflow-hidden text-sm uppercase bg-gray-700 rounded-md shadow-lg">
            <div class="{{$userPayment->bgColor}} w-full text-center p-1">
              {{ $userPayment->title }}
              <p class="text-xs">{{ \Carbon\Carbon::parse($userPayment->date)->format('m-Y') }}</p>
            </div>
            <div class="px-2 py-1">
              <div class="text-right">
                <p class="text-base">$ {{ number_format($userPayment->paid, 2) }}</p>
                <p class="{{$userPayment->textColor}} text-xs">$ {{ number_format($userPayment->amount, 2) }}</p>
              </div>
            </div>
          </div>
        @endif
      @endforeach
    </div>
    <div class="container px-3 py-1 mx-auto my-3 text-lg text-right bg-gray-300/10 md:px-6">
      <p>{{ __('Deuda Total') }} <span class="inline-block font-bold bg-gray-200/10 w-44">$
          {{ number_format($totalDebt, 2) }}</span>
      </p>
      <p>{{ __('Total Pagado') }} <span class="inline-block font-bold bg-gray-200/10 w-44">$
          {{ number_format($totalPaid, 2) }}</span>
      </p>
      <p>{{ __('Saldo') }} <span class="inline-block font-bold bg-gray-100/10 w-44">$
          {{ number_format($totalDebt - $totalPaid, 2) }}</span></p>
    </div>
    <div x-data="{}" @open-receipt.window="window.open($event.detail.url, '_blank')"></div>
  </div>
</div>