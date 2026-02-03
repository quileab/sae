<div class="p-2">
  {{-- Formulario de Planes --}}
  <x-modal wire:model="updatePayPlanForm" title="Plan de Pago » {{ $masterId }}" subtitle="" separator>
    <x-input label="Descripción" wire:model="masterTitle" />

    <x-slot:actions>
      <div class="flex justify-between">
        @if ($masterId == '0')
          <x-button label="Guardar" wire:click="createMasterData" wire:loading.attr="disabled" class="btn-primary" />
        @else
          <x-dropdown>
            <x-slot:trigger>
              <x-button label="Eliminar" class="btn-error" />
            </x-slot:trigger>
            <x-menu-item title="Confirmar Eliminación" wire:click="deleteMasterData({{ $masterId }})" />
          </x-dropdown>
          <x-button label="Actualizar" wire:click="updateMasterData({{ $masterId }})" wire:loading.attr="disabled"
            class="btn-primary" />
        @endif
        <x-button label="Cerrar" wire:click="$toggle('updatePayPlanForm')" class="btn-secondary" />
      </div>
    </x-slot:actions>
  </x-modal>

  {{-- Formulario de Cuotas --}}
  <x-modal wire:model="updatePaymentForm" title="Detalles de Pago" subtitle="" separator>
    <div class="flex justify-between">
      <span class="mb-3 text-lg">
        ID de Cuota: <strong>{{ $detailId }}</strong>
      </span>
      <span class="mb-3">
        Fecha
        <x-input type="date" wire:model="detailDate" />
      </span>
    </div>
    <x-input label="Descripción" wire:model="detailTitle" />
    <x-input label="Importe" type="number" wire:model="detailAmount" />

    <x-slot:actions>
      <div class="flex justify-between">
        @if ($detailId == '0')
          <x-button label="Guardar" wire:click="createDetailData" wire:loading.attr="disabled" class="btn-primary" />
        @else
          <x-dropdown>
            <x-slot:trigger>
              <x-button label="Eliminar" class="btn-error" />
            </x-slot:trigger>
            <x-menu-item title="Confirmar Eliminación" wire:click="deleteDetailData({{ $detailId }})" />
          </x-dropdown>
          <x-button label="Actualizar" wire:click="updateDetailData({{ $detailId }})" wire:loading.attr="disabled"
            class="btn-primary" />
        @endif
        <x-button label="Cerrar" wire:click="$toggle('updatePaymentForm')" class="btn-secondary" />
      </div>
    </x-slot:actions>
  </x-modal>

  <div class="flex flex-wrap rounded-md w-full shadow-lg p-2 mb-2 bg-gray-100/10">
    <x-button wire:click="openCreateMasterForm" icon="o-currency-dollar" label="Nuevo Plan" class="btn-primary" />
    @foreach ($this->allPlansMasters as $plan) {{-- Use the computed property --}}
      <!-- Plans Master -->

      @php
        $color = ($payPlan == $plan->id) ? 'blue' : 'gray';
      @endphp

      <div class="inline-flex shadow-md mx-1">
        <button wire:click="payPlanChanged('{{ $plan->id }}')"
          class="text-left bg-{{ $color }}-700 hover:bg-{{ $color }}-600 text-gray-50 py-0 px-2 rounded-l">
          {{ $plan->title }}
        </button>
        {{-- **EDIT ICON** --}}
        <button wire:click="populateMasterData('{{ $plan->id }}')"
          class="bg-{{ $color }}-600 hover:bg-{{ $color }}-500 text-{{ $color }}-300 font-bold py-0 px-1 rounded-r">
          <small>#{{ $plan->id }}</small>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
            <path fill-rule="evenodd"
              d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
              clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    @endforeach
  </div>

  {{-- List payments of selected plan --}}
  <div class="flex flex-wrap w-full p-2">
    <x-button wire:click="openCreateDetailForm" icon="o-currency-dollar" label="Nueva Cuota" class="btn-primary" />

    @foreach ($this->currentPlansDetails as $detail) {{-- Use the computed property --}}
      <div class="md:w-36 sm:w-full rounded overflow-hidden shadow-lg bg-gray-50/10 m-1">
        <div class="flex justify-between bg-blue-700">
          <div class="mx-2 my-1">
            <span class="font-bold text-sm">{{ $detail->title }}</span>
          </div>
          <div class="mx-2 my-1">
            <button wire:click="populateDetailData({{ $detail->id }})">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                <path fill-rule="evenodd"
                  d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                  clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
        <p class="w-full text-right p-2">$ {{ $detail->amount }}</p>
      </div>
    @endforeach
  </div>
</div>