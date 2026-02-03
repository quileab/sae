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
    <x-table :headers="[['key' => 'created_at', 'label' => __('Fecha')], ['key' => 'id', 'label' => __('ID')], ['key' => 'description', 'label' => __('Descripción')], ['key' => 'paymentAmount', 'label' => __('Monto'), 'class' => 'text-right'], ['key' => 'actions', 'label' => '']]" :rows="$this->payments" striped>
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