<?php

use App\Models\User;
use App\Models\PaymentRecord;
use App\Models\UserPayments;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Livewire\WithPagination;

new class extends Component {
  use Toast, WithPagination;

  public $openModal = false;
  public $updating = false;
  public $perPage = 10;
  public $user;

  public function mount($user)
  {
    if (auth()->user()->hasRole('student') && auth()->id() != $user) {
        abort(403, 'No tienes permiso para ver esta página.');
    }

    $this->user = User::find($user);
  }

  public function save()
  {
    // Logic to save or update a payment record
    // This seems to be a placeholder, as there is no form in the modal
    $this->openModal = false;
    $this->success('Registro guardado.');
  }

          public function cancelPayment($paymentId)
          {
              DB::transaction(function () use ($paymentId) {
                  $payment = PaymentRecord::find($paymentId);
      
                  if ($payment && $payment->description != 'CANCELADO') {
                      $amountToRevert = $payment->paymentAmount;
      
                      $userpayments = UserPayments::where('user_id', $payment->userpayments->user_id)
                                                  ->where('paid', '>', 0)
                                                  ->orderBy('date', 'desc')
                                                  ->get();
      
                      foreach ($userpayments as $userpayment) {
                          if ($amountToRevert <= 0) {
                              break;
                          }
      
                          $paidAmount = $userpayment->paid;
                          $revertAmount = min($amountToRevert, $paidAmount);
      
                          $userpayment->paid -= $revertAmount;
                          $userpayment->save();
      
                          $amountToRevert -= $revertAmount;
                      }
      
                      $payment->description = 'CANCELADO';
                      $payment->paymentAmount = 0;
                      $payment->save();
      
                      $this->success('Pago cancelado.');
                  } else {
                      $this->error('Pago no encontrado o ya cancelado.');
                  }
              });
          }  public function with(): array
  {
    return [
      'payments' => PaymentRecord::whereHas('userpayments', function ($query) {
        $query->where('user_id', $this->user->id);
      })
        ->paginate($this->perPage),
    ];
  }
}; ?>

<div>
  {{-- CRUD Books Form --}}
  <x-modal wire:model="openModal" title="@if ($updating) {{ __('Actualizando') }} @else {{ __('Nuevo') }} @endif" subtitle="" separator>
    <x-slot:menu>
      @if ($errors->any())
        <div class="text-yellow-300">{{ __('Verifique la información ingresada') }}</div>
      @endif
      <x-button wire:click="save" wire:loading.attr="disabled" wire:target="save"
        label="@if ($updating) {{ __('Actualizar') }} @else {{ __('Guardar') }} @endif" class="btn-primary" />
      <x-button wire:click="$set('openModal',false)" label="{{ __('Cancelar') }}" class="btn-secondary" />
    </x-slot:menu>
  </x-modal>


  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <x-table :headers="[['key' => 'created_at', 'label' => __('Fecha')], ['key' => 'id', 'label' => __('ID')], ['key' => 'description', 'label' => __('Descripción')], ['key' => 'paymentAmount', 'label' => __('Monto'), 'class' => 'text-right'], ['key' => 'actions', 'label' => '']]" :rows="$payments" striped>
      <x-slot:actions>
        <h1 class="flex item">
          <strong>{{ $user->lastname }}</strong>, {{ $user->firstname }}
          » {{ $user->id }}
        </h1>
        <div class="flex item center">
          <span class="mt-3">{{ __('Mostrar') }}&nbsp;</span>
          <x-select wire:model.live="perPage"
            class="mr-4 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </x-select>
        </div>
      </x-slot:actions>
              @scope('cell_paymentAmount', $payment)
                  ${{ number_format($payment->paymentAmount, 2) }}
              @endscope
      
              @scope('actions', $payment)
        <div class="flex items-center">
        @if($payment->paymentAmount > 0)
            <a href="{{ route('payments.receipt', $payment->id) }}" target="_blank">
                <x-button icon="o-document-text" class="btn-ghost btn-sm" />
            </a>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'director', 'administrative']) && $payment->paymentAmount > 0)
            <x-dropdown>
              <x-slot:trigger>
                <x-button icon="o-ellipsis-horizontal" class="btn-ghost btn-sm" />
              </x-slot:trigger>
              <x-menu-item title="Cancelar" wire:click="cancelPayment('{{ $payment->id }}')" />
            </x-dropdown>
        @endif
        </div>
      @endscope
      <x-slot:no-data>
        No hay registros para mostrar.
      </x-slot:no-data>
      </x-table>
  </div>

</div>