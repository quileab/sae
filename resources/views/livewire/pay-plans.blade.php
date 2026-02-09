<div class="p-4 space-y-6">
    <x-header title="{{ __('Planes de Pago') }}" subtitle="{{ __('Administrar estructuras de pago y cuotas') }}" progress-indicator>
        <x-slot:actions>
            <x-button wire:click="openCreateMasterForm" icon="o-plus" label="{{ __('Nuevo Plan') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Plans Selection Bar --}}
    <div class="flex flex-wrap gap-2 p-3 bg-base-200 rounded-xl shadow-sm border border-base-300">
        @forelse ($this->allPlansMasters as $plan)
            <div class="join shadow-sm">
                <button wire:click="payPlanChanged('{{ $plan->id }}')" 
                    @class([
                        'join-item btn btn-sm px-4',
                        'btn-primary' => $payPlan == $plan->id,
                        'btn-ghost bg-base-100' => $payPlan != $plan->id,
                    ])>
                    {{ $plan->title }}
                </button>
                <x-button icon="o-pencil-square" wire:click="populateMasterData('{{ $plan->id }}')" 
                    @class([
                        'join-item btn btn-sm btn-square',
                        'btn-primary brightness-90' => $payPlan == $plan->id,
                        'btn-ghost bg-base-100 border-l border-base-300' => $payPlan != $plan->id,
                    ]) 
                    tooltip="{{ __('Editar Plan') }}" />
            </div>
        @empty
            <div class="text-sm text-gray-500 italic p-1">{{ __('No hay planes creados') }}</div>
        @endforelse
    </div>

    {{-- Installments Grid --}}
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <x-icon name="o-list-bullet" class="w-5 h-5 text-primary" />
                {{ __('Cuotas del Plan') }}
            </h2>
            <x-button wire:click="openCreateDetailForm" icon="o-plus" label="{{ __('Nueva Cuota') }}" class="btn-outline btn-primary btn-sm" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            @forelse ($this->currentPlansDetails as $detail)
                <div class="group relative bg-base-100 rounded-xl shadow-sm border border-base-300 overflow-hidden hover:shadow-md transition-all duration-200">
                    <div class="bg-primary/10 px-3 py-2 border-b border-base-300 flex justify-between items-center">
                        <span class="text-xs font-bold truncate pr-4">{{ $detail->title }}</span>
                        <x-button icon="o-pencil" wire:click="populateDetailData({{ $detail->id }})" class="btn-ghost btn-xs btn-square opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div class="p-3 space-y-2">
                        <div class="flex items-center text-[10px] text-gray-500 gap-1">
                            <x-icon name="o-calendar" class="w-3 h-3" />
                            {{ $detail->date->format('d/m/Y') }}
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-mono font-bold text-primary">$ {{ number_format($detail->amount, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-10 bg-base-200/50 rounded-2xl border-2 border-dashed border-base-300 text-center">
                    <x-icon name="o-information-circle" class="w-10 h-10 mx-auto text-gray-400 mb-2" />
                    <div class="text-gray-500 font-medium">{{ __('Este plan no tiene cuotas configuradas') }}</div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Formulario de Planes --}}
    <x-modal wire:model="updatePayPlanForm" title="{{ $masterId == 0 ? __('Nuevo Plan de Pago') : __('Editar Plan') }}" separator>
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="{{ __('Descripción del Plan') }}" wire:model="masterTitle" placeholder="{{ __('Ej: Plan 2026 - Cuotas Mensuales') }}" />
                
                @if ($masterId == '0')
                    <x-input label="{{ __('Precio de Cuotas por Defecto') }}" type="number" step="0.01" wire:model.live="defaultAmount" prefix="$" hint="{{ __('Cambiará el importe de todas las filas') }}" />
                @endif
            </div>

            @if ($masterId == '0')
                <div class="border rounded-xl overflow-hidden bg-base-200/30">
                    <div class="max-h-[50vh] overflow-y-auto">
                        <table class="table table-xs w-full">
                            <thead class="sticky top-0 bg-base-200 z-10 shadow-sm">
                                <tr>
                                    <th class="px-1">{{ __('Mes-Año') }}</th>
                                    <th class="px-1 w-24">{{ __('Importe') }}</th>
                                    <th class="px-1 w-32">{{ __('Fecha') }}</th>
                                    <th class="text-center px-1 w-20">{{ __('Acciones') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($planDetails as $index => $detail)
                                    <tr wire:key="detail-row-{{ $index }}" class="hover:bg-base-200/50 transition-colors">
                                        <td class="p-1">
                                            <x-input wire:model="planDetails.{{ $index }}.title" placeholder="{{ __('Descripción') }}" class="input-sm w-full" />
                                        </td>
                                        <td class="p-1">
                                            <x-input type="number" step="0.01" wire:model="planDetails.{{ $index }}.amount" class="input-sm font-mono w-full text-right" />
                                        </td>
                                        <td class="p-1">
                                            <x-input type="date" wire:model="planDetails.{{ $index }}.date" class="input-sm w-full px-1" />
                                        </td>
                                        <td class="p-1">
                                            <div class="flex justify-center">
                                                <x-button icon="o-plus" wire:click="addDetailRow({{ $index }})" class="btn-xs btn-circle btn-ghost" />
                                                <x-button icon="o-trash" wire:click="removeDetailRow({{ $index }})" class="btn-xs btn-circle btn-ghost text-error" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if ($masterId == '0')
                    <x-button label="{{ __('Guardar Plan') }}" wire:click="createMasterData" class="btn-primary" spinner="createMasterData" />
                @else
                    <x-button label="{{ __('Actualizar') }}" wire:click="updateMasterData({{ $masterId }})" class="btn-primary" spinner="updateMasterData" />
                    
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button label="{{ __('Eliminar') }}" icon="o-trash" class="btn-error btn-outline" />
                        </x-slot:trigger>
                        <x-menu-item title="{{ __('Confirmar Eliminación') }}" icon="o-check" wire:click="deleteMasterData({{ $masterId }})" class="text-error" />
                    </x-dropdown>
                @endif
                <x-button label="{{ __('Cerrar') }}" @click="$wire.updatePayPlanForm = false" class="btn-ghost" />
            </div>
        </x-slot:actions>
    </x-modal>

    {{-- Formulario de Cuotas Individuales --}}
    <x-modal wire:model="updatePaymentForm" title="{{ $detailId == 0 ? __('Nueva Cuota') : __('Editar Cuota') }}" separator>
        <div class="space-y-4">
            @if($detailId != 0)
                <div class="p-2 bg-base-200 rounded-lg text-xs font-mono text-gray-500">
                    ID: {{ $detailId }}
                </div>
            @endif
            
            <x-input label="{{ __('Descripción') }}" wire:model="detailTitle" placeholder="{{ __('Ej: Cuota 01') }}" />
            
            <div class="grid grid-cols-2 gap-4">
                <x-input label="{{ __('Importe') }}" type="number" step="0.01" wire:model="detailAmount" prefix="$" />
                <x-input label="{{ __('Fecha Vencimiento') }}" type="date" wire:model="detailDate" />
            </div>
        </div>

        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if ($detailId == '0')
                    <x-button label="{{ __('Crear Cuota') }}" wire:click="createDetailData" class="btn-primary" spinner="createDetailData" />
                @else
                    <x-button label="{{ __('Actualizar') }}" wire:click="updateDetailData({{ $detailId }})" class="btn-primary" spinner="updateDetailData" />
                    
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button label="{{ __('Eliminar') }}" icon="o-trash" class="btn-error btn-outline" />
                        </x-slot:trigger>
                        <x-menu-item title="{{ __('Confirmar Eliminación') }}" icon="o-check" wire:click="deleteDetailData({{ $detailId }})" class="text-error" />
                    </x-dropdown>
                @endif
                <x-button label="{{ __('Cerrar') }}" @click="$wire.updatePaymentForm = false" class="btn-ghost" />
            </div>
        </x-slot:actions>
    </x-modal>
</div>