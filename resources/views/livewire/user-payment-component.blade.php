<?php

use App\Models\User;
use App\Models\UserPayments;
use App\Models\PlansMaster;
use App\Models\PaymentRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component {
  use Toast;

  public $userId;
  public $user;
  public $openModal = false;
  public $selectedPlan;
  public $payPlans;
  public $combinePlans = false;
  public $paymentModal = false;
  public $paymentDescription;
  public $paymentAmountPaid;
  public $paymentAmountInput;
  public $modifyPaymentModal = false;
  public $paymentId;
  public $totalPaid;
  public $totalDebt;
  public $hasCounter;
  public $userPayments;
  public $plansError = false;

  public $nextPaymentToPay = null;

  public function mount($user = null)
  {
    $this->userId = $user ?? auth()->id();
    $this->user = User::find($this->userId);
    $this->loadData();
    if ($this->payPlans->isNotEmpty()) {
      $this->selectedPlan = $this->payPlans->first()->id;
    }

    if (auth()->user()->hasRole('student')) {
      $this->findNextPayment();
    }
  }

  public function findNextPayment()
  {
    $pending = UserPayments::where('user_id', $this->userId)
      ->whereRaw('paid < amount')
      ->get();

    if ($pending->isNotEmpty()) {
      $this->nextPaymentToPay = $pending->sortBy('date')->first();
    }
  }

  public function loadData()
  {
    $this->userPayments = UserPayments::where('user_id', $this->userId)->get();
    $this->totalDebt = $this->userPayments->sum('amount');
    $this->totalPaid = $this->userPayments->sum('paid');
    
    try {
        $this->payPlans = PlansMaster::all();
    } catch (\Illuminate\Database\QueryException $e) {
        $this->plansError = true;
        $this->payPlans = collect();
    }

    $this->hasCounter = $this->userPayments->count();

    foreach ($this->userPayments as $userPayment) {
      $textColor = ($userPayment->paid == $userPayment->amount) ? 'text-green-200' : 'text-blue-200';
      $textColor = ($userPayment->paid < $userPayment->amount && $userPayment->paid > 0) ? 'text-amber-200' : $textColor;
      $bgColor = ($userPayment->paid == $userPayment->amount) ? 'bg-green-700' : 'bg-blue-700';
      $bgColor = ($userPayment->paid < $userPayment->amount && $userPayment->paid > 0) ? 'bg-amber-600' : $bgColor;
      $userPayment->textColor = $textColor;
      $userPayment->bgColor = $bgColor;
    }
  }

  public function assignPayPlan()
  {
    if (empty($this->selectedPlan)) {
      $this->error('Debe seleccionar un plan.');
      return;
    }

    $planMaster = PlansMaster::with('details')->find($this->selectedPlan);

    if (!$planMaster) {
      $this->error('El plan seleccionado no es válido.');
      return;
    }

    foreach ($planMaster->details as $detail) {
      UserPayments::create([
        'user_id' => $this->userId,
        'amount' => $detail->amount,
        'paid' => 0,
        'date' => $detail->date,
        'title' => $detail->title,
      ]);
    }

    $this->openModal = false;
    $this->loadData();
    $this->success('Plan asignado.');
  }

  public function addPaymentToUser()
  {
    $pending = UserPayments::where('user_id', $this->userId)
      ->whereRaw('paid < amount')
      ->get();

    if ($pending->isNotEmpty()) {
      $paymentToPay = $pending->sortBy('date')->first();
      $this->paymentId = $paymentToPay->id;
      $this->paymentDescription = $paymentToPay->title;
      $this->paymentAmountPaid = $paymentToPay->amount - $paymentToPay->paid;
      $this->paymentModal = true;
    } else {
      $this->info('No hay cuotas pendientes de pago.');
    }
  }

  public function registerUserPayment()
  {
    $paymentRecord = null;

    DB::transaction(function () use (&$paymentRecord) {
      $amountToDistribute = $this->paymentAmountInput;
      $currentInstallment = UserPayments::find($this->paymentId);

      if (!$currentInstallment) {
        return;
      }

      // Get all pending installments for the user, starting from the current one
      $installments = UserPayments::where('user_id', $this->userId)
        ->where('date', '>=', $currentInstallment->date)
        ->whereRaw('paid < amount')
        ->orderBy('date', 'asc')
        ->get();

      Carbon::setLocale(config('app.locale'));
      $description = '';

      foreach ($installments as $installment) {
        if ($amountToDistribute <= 0) {
          break;
        }

        $remainingOnInstallment = $installment->amount - $installment->paid;
        $paymentForThisInstallment = min($amountToDistribute, $remainingOnInstallment);

        $installment->paid += $paymentForThisInstallment;
        $installment->save();

        $amountToDistribute -= $paymentForThisInstallment;

        $descriptionPrefix = '';
        if ($paymentForThisInstallment < $remainingOnInstallment) {
          $descriptionPrefix = 'par. ';
        }

        // Append the formatted date to the description
        $description .= $descriptionPrefix . ucfirst(Carbon::parse($installment->date)->translatedFormat('M\'y')) . ' - ';
      }

      // Remove the trailing ' - ' from the description
      $description = rtrim($description, ' - ');

      // Create a single payment record for the total amount paid
      $paymentRecord = PaymentRecord::create([
        'userpayments_id' => $this->paymentId, // ID of the installment that triggered the payment
        'user_id' => $this->userId,
        'paymentBox' => Auth::user()->name,
        'description' => $description,
        'paymentAmount' => $this->paymentAmountInput,
      ]);
    });

    if ($paymentRecord) {
      $this->dispatch('open-receipt', url: route('payments.receipt', $paymentRecord));
    }

    $this->paymentModal = false;
    $this->loadData();
    $this->success('Pago registrado.');
  }

  public function handleInstallmentClick($userPayment)
  {
    if ($userPayment['paid'] < $userPayment['amount']) {
      // Open payment modal
      $this->paymentId = $userPayment['id'];
      $this->paymentDescription = $userPayment['title'];
      $this->paymentAmountPaid = $userPayment['amount'] - $userPayment['paid'];
      $this->paymentModal = true;
    } else {
      // Open modify modal
      $this->paymentId = $userPayment['id'];
      $this->paymentDescription = $userPayment['title'];
      $this->paymentAmountPaid = $userPayment['amount'];
      $this->totalPaid = $userPayment['paid'];
      $this->modifyPaymentModal = true;
    }
  }

  public function modifyAmount($paymentId)
  {
    $payment = UserPayments::find($paymentId);
    if ($payment) {
      $payment->amount = $this->totalDebt;
      $payment->save();
    }
    $this->modifyPaymentModal = false;
    $this->loadData();
    $this->info('Monto modificado.');
  }

  public function deletePayment($paymentId)
  {
    UserPayments::destroy($paymentId);
    $this->modifyPaymentModal = false;
    $this->loadData();
    $this->error('Pago eliminado.');
  }
}; ?>

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