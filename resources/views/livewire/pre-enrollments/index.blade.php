<div class="p-4 space-y-4">
    {{-- Encabezado principal: Título y Filtros / Acciones a la derecha --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-base-300">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                Preinscripciones {{ \App\Services\AcademicCycle::enrollmentCycle() }}
            </h1>
        </div>

        {{-- "Estado, Ciclo y Exportar CSV" al final del título --}}
        <div class="flex flex-wrap items-end gap-2">
            <x-select label="Estado" wire:model.live="statusFilter" :options="[['id'=>'','name'=>'Todos'],['id'=>'submitted','name'=>'Enviado'],['id'=>'validated','name'=>'Validado'],['id'=>'waitlisted','name'=>'En espera'],['id'=>'observed','name'=>'Observado'],['id'=>'rejected','name'=>'Rechazado'],['id'=>'enrolled','name'=>'Matriculado']]" placeholder="Filtrar por estado" class="select-sm" />
            <x-select label="Arancel" wire:model.live="paymentFilter" :options="[['id'=>'','name'=>'Todos'],['id'=>'paid','name'=>'Cobrado'],['id'=>'unpaid','name'=>'Impago']]" class="select-sm" />
            <x-input label="Ciclo" wire:model.live="cycleFilter" type="number" class="input-sm w-24" />
            <x-button label="Exportar CSV" icon="o-arrow-down-tray" wire:click="exportCsv" class="btn-primary btn-sm" spinner />
        </div>
    </div>

    {{-- Barra de Herramientas: Buscador por Nombre/DNI + Tarjetas de Totales y Caja --}}
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        {{-- En el espacio que queda: Buscador por nombre / DNI --}}
        <div class="w-full lg:max-w-md">
            <x-input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Buscar por DNI, apellido, nombre o email..." 
                icon="o-magnifying-glass" 
                class="w-full"
                clearable
            />
        </div>

        {{-- Totales y Acceso a Arqueo de Caja Diario / Rango de fechas --}}
        <div class="flex flex-wrap gap-2 items-center justify-start lg:justify-end">
            <div class="bg-base-200 p-2 rounded-lg flex flex-wrap gap-2 items-center text-xs">
                <span class="font-semibold text-base-content/70">Totales:</span>
                <span class="badge badge-primary">{{ $currentLabel }}: {{ $currentCount }}</span>
                <span class="badge badge-neutral">Total: {{ $totalCount }}</span>
            </div>

            <div class="bg-base-200 p-2 rounded-lg flex flex-wrap gap-2 items-center text-xs">
                <div class="font-bold flex items-center gap-1 text-base-content/80">
                    <x-icon name="o-banknotes" class="w-4 h-4 text-success" />
                    Caja:
                </div>
                <span class="badge badge-success text-xs font-semibold">Cobrado: ${{ number_format($boxTotalCollected, 2) }}</span>
                @if($boxTotalRefunded > 0)
                    <span class="badge badge-error text-xs font-semibold">Devuelto: ${{ number_format($boxTotalRefunded, 2) }}</span>
                @endif
                <span class="badge badge-info text-xs font-bold">Saldo: ${{ number_format($boxBalance, 2) }}</span>

                <x-button 
                    label="Arqueo / Listado" 
                    icon="o-calendar" 
                    wire:click="openBoxModal" 
                    class="btn-xs btn-primary ml-1" 
                    tooltip="Ver arqueo de caja por rango de fechas" 
                />
            </div>
        </div>
    </div>

    @php
        $rowDecoration = [
            'bg-success/10 font-medium' => fn($row) => $row->status === 'validated',
            'bg-primary/10 font-medium' => fn($row) => $row->status === 'enrolled',
            'bg-warning/10' => fn($row) => in_array($row->status, ['observed', 'waitlisted']),
            'bg-error/10 text-error/80' => fn($row) => $row->status === 'rejected',
            'bg-base-200/50' => fn($row) => $row->status === 'submitted',
        ];
    @endphp

    <x-table :headers="[
        ['key'=>'name','label'=>'Apellido y Nombre'],
        ['key'=>'doc','label'=>'Documento'],
        ['key'=>'escuela','label'=>'Escuela'],
        ['key'=>'email','label'=>'Email'],
        ['key'=>'status','label'=>'Estado'],
        ['key'=>'payment','label'=>'Arancel / Caja'],
    ]" :rows="$preEnrollments" :row-decoration="$rowDecoration" :link="false" with-pagination>
        @scope('cell_name', $row) {{ ($row->payload['lname'] ?? '') }}, {{ ($row->payload['fname'] ?? '') }} @endscope
        @scope('cell_doc', $row) {{ $row->doc_type }} {{ $row->doc_number }} @endscope
        @scope('cell_escuela', $row) {{ $row->payload['escuela'] ?? '-' }} @endscope
        @scope('cell_status', $row) <x-badge :value="$row->status" /> @endscope
        @scope('cell_payment', $row)
            @php
                $hasCharge = $row->isPaid();
                $hasRefund = $row->isRefunded();
            @endphp
            @if($hasRefund)
                <span class="badge badge-warning text-xs font-semibold">Devuelto</span>
            @elseif($hasCharge)
                <span class="badge badge-success text-xs font-semibold">Abonado</span>
            @else
                <span class="badge badge-ghost text-xs text-base-content/50">Pendiente</span>
            @endif
        @endscope
        @scope('actions', $row)
            <div class="flex items-center gap-1">
                @if(!$row->isPaid())
                    <x-button icon="o-currency-dollar" wire:click="openChargeModal({{ $row->id }})" class="btn-xs btn-success" tooltip="Cobrar arancel" />
                @endif
                <x-button icon="o-eye" wire:click="openDrawer({{ $row->id }})" class="btn-xs btn-ghost" tooltip="Ver detalle" />
            </div>
        @endscope
    </x-table>

    {{-- Drawer de Detalle --}}
    <x-qb-drawer wire:model="drawer" title="Detalle preinscripción" right separator with-close-button class="lg:w-1/2">
        @if ($selected)
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <div class="join">
                        <x-button label="Validar" wire:click="updateStatus({{ $selected->id }}, 'validated')" class="btn-success join-item btn-sm" />
                        <x-button label="Observar" wire:click="updateStatus({{ $selected->id }}, 'observed')" class="btn-warning join-item btn-sm" />
                        <x-button label="En espera" wire:click="updateStatus({{ $selected->id }}, 'waitlisted')" class="btn-warning join-item btn-sm" />
                        <x-button label="Rechazar" wire:click="updateStatus({{ $selected->id }}, 'rejected')" class="btn-error join-item btn-sm" />
                        <x-dropdown class="join-item">
                            <x-slot:trigger>
                                <x-button label="Matricular" class="btn-primary join-item btn-sm" />
                            </x-slot:trigger>
                            <x-menu-item title="¡Confirmar matriculación!" wire:click="updateStatus({{ $selected->id }}, 'enrolled')" class="text-primary font-bold" icon="o-academic-cap" />
                        </x-dropdown>
                    </div>
                    
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button icon="o-trash" class="btn-error btn-sm" />
                        </x-slot:trigger>
                        <x-menu-item title="¡Confirmar borrado!" wire:click="delete({{ $selected->id }})" class="text-error font-bold" icon="o-trash" />
                    </x-dropdown>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm bg-base-200/50 p-3 rounded-lg">
                    <div><span class="font-bold">Ciclo:</span> {{ $selected->cycle_id }}</div>
                    <div><span class="font-bold">Estado:</span> <x-badge :value="$selected->status" /></div>
                    <div><span class="font-bold">Carrera:</span> {{ $selected->career->name ?? '-' }}</div>
                    <div><span class="font-bold">Doc:</span> {{ $selected->doc_type }} {{ $selected->doc_number }}</div>
                    <div><span class="font-bold">Email:</span> {{ $selected->email }}</div>
                    <div><span class="font-bold">Tel:</span> {{ $selected->phone }}</div>
                </div>

                {{-- Sección de Caja de Preinscripción --}}
                <div class="card bg-base-200/60 border border-base-300 p-4 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-base flex items-center gap-2">
                            <x-icon name="o-banknotes" class="w-5 h-5 text-primary" />
                            <span>Arancel de Preinscripción</span>
                        </div>
                        @if(!$selected->isPaid())
                            <x-button label="Cobrar Arancel" icon="o-currency-dollar" wire:click="openChargeModal({{ $selected->id }})" class="btn-success btn-sm" />
                        @endif
                    </div>

                    @if($selected->payments->isNotEmpty())
                        <div class="space-y-2 mt-2">
                            @foreach($selected->payments as $payment)
                                <div class="flex items-center justify-between p-2.5 rounded-lg border {{ $payment->type === 'refund' ? 'bg-error/10 border-error/30' : 'bg-base-100 border-base-300' }}">
                                    <div>
                                        <div class="font-semibold text-sm flex items-center gap-2">
                                            @if($payment->type === 'refund')
                                                <span class="badge badge-error badge-sm">DEVOLUCIÓN</span>
                                            @else
                                                <span class="badge badge-success badge-sm">COBRO</span>
                                            @endif
                                            <span>Recibo Nº {{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</span>
                                            <span class="text-xs text-base-content/60 font-normal">({{ $payment->created_at->format('d/m/Y H:i') }})</span>
                                        </div>
                                        <div class="text-xs text-base-content/70 mt-0.5">
                                            {{ $payment->concept }} — <b>${{ number_format($payment->amount, 2) }}</b> ({{ strtoupper($payment->payment_method) }})
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('pre-enrollments.receipt', $payment->id) }}" target="_blank" class="btn btn-ghost btn-xs text-primary" title="Ver Recibo PDF">
                                            <x-icon name="o-document-text" class="w-4 h-4" />
                                            Recibo
                                        </a>

                                        {{-- Permitir devolución si es un cobro y aún no tiene reembolso --}}
                                        @if($payment->type === 'charge' && !$payment->isRefunded())
                                            <x-button label="Devolver" icon="o-arrow-path-rounded-square" wire:click="openRefundModal({{ $payment->id }})" class="btn-error btn-xs" tooltip="Devolver dinero y generar recibo invertido" />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm text-base-content/60 italic py-1">
                            El aspirante aún no tiene pagos de arancel registrados.
                        </div>
                    @endif
                </div>

                <div class="divider my-1"></div>
                
                <h3 class="font-bold text-sm">Payload SIGAE</h3>
                <div class="bg-base-200 rounded p-3 text-xs max-h-72 overflow-y-auto">
                    @foreach ($selected->payload as $k => $v)
                        <div class="flex justify-between border-b border-base-300 py-1">
                            <span class="font-semibold">{{ $k }}</span>
                            <span>{{ is_bool($v) ? ($v ? 'Sí' : 'No') : (is_array($v) ? json_encode($v) : $v) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-qb-drawer>

    {{-- Modal para Cobrar Preinscripción --}}
    <template x-teleport="body">
        <x-modal wire:model="chargeModal" title="Cobro de Arancel de Preinscripción" separator>
            @if($selected)
                <div class="space-y-4">
                    <div class="bg-base-200 p-3 rounded-lg text-sm">
                        <div><b>Aspirante:</b> {{ ($selected->payload['lname'] ?? '') }}, {{ ($selected->payload['fname'] ?? '') }}</div>
                        <div><b>Documento:</b> {{ $selected->doc_type }} {{ $selected->doc_number }}</div>
                        <div><b>Carrera / Nivel:</b> {{ $selected->career?->name ?? 'Secundario' }}</div>
                        <div><b>Ciclo Lectivo:</b> {{ $selected->cycle_id }}</div>
                    </div>

                    <x-input label="Concepto del Recibo" wire:model="chargeConcept" placeholder="Ej: Cobro de Preinscripción Ciclo 2026" />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="Monto a Cobrar ($)" wire:model="chargeAmount" type="number" step="0.01" prefix="$" />
                        <x-select label="Forma de Pago" wire:model="chargePaymentMethod" :options="[
                            ['id' => 'cash', 'name' => 'Efectivo'],
                            ['id' => 'transfer', 'name' => 'Transferencia Bancaria'],
                            ['id' => 'card', 'name' => 'Tarjeta Débito/Crédito'],
                        ]" />
                    </div>

                    <x-textarea label="Observaciones (Opcional)" wire:model="chargeNotes" placeholder="Detalle adicional o comprobante..." rows="2" />
                </div>
            @endif

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.chargeModal = false" class="btn-ghost" />
                <x-button label="Confirmar y Emitir Recibo" icon="o-check" wire:click="processCharge" class="btn-success" spinner="processCharge" />
            </x-slot:actions>
        </x-modal>
    </template>

    {{-- Modal para Devolución (Contra-asiento con Concepto Invertido) --}}
    <template x-teleport="body">
        <x-modal wire:model="refundModal" title="Devolución de Arancel (Contra-asiento)" separator>
            @if($paymentToRefund)
                <div class="space-y-4">
                    <x-alert icon="o-exclamation-triangle" class="alert-warning text-sm">
                        Se generará un contra-asiento de egreso en la caja y un nuevo <b>Recibo de Devolución</b> con concepto invertido asociado al recibo original Nº {{ str_pad($paymentToRefund->id, 6, '0', STR_PAD_LEFT) }}.
                    </x-alert>

                    <div class="bg-base-200 p-3 rounded-lg text-sm space-y-1">
                        <div><b>Aspirante:</b> {{ $paymentToRefund->payer_name }} ({{ $paymentToRefund->doc_type }}: {{ $paymentToRefund->doc_number }})</div>
                        <div><b>Recibo Original a devolver:</b> Nº {{ str_pad($paymentToRefund->id, 6, '0', STR_PAD_LEFT) }}</div>
                        <div><b>Monto a Reintegrar:</b> <span class="font-bold text-error text-base">${{ number_format($paymentToRefund->amount, 2) }}</span></div>
                    </div>

                    <x-input label="Motivo de la Devolución (Opcional)" wire:model="refundReason" placeholder="Opcional..." />

                    <x-select label="Forma de Devolución" wire:model="refundPaymentMethod" :options="[
                        ['id' => 'cash', 'name' => 'Efectivo'],
                        ['id' => 'transfer', 'name' => 'Transferencia'],
                    ]" />
                </div>
            @endif

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.refundModal = false" class="btn-ghost" />
                <x-button label="Confirmar Devolución y Emitir Recibo" icon="o-arrow-path-rounded-square" wire:click="processRefund" class="btn-error" spinner="processRefund" />
            </x-slot:actions>
        </x-modal>
    </template>

    {{-- Modal para Arqueo de Caja Diario / Rango de Fechas Teletransportado a body --}}
    <template x-teleport="body">
        <x-modal wire:model="boxModal" title="Arqueo y Movimientos de Caja de Preinscripción" class="backdrop-blur" box-class="max-w-4xl" separator>
            <div class="space-y-4">
                {{-- Filtros de fecha --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-base-200/60 p-3 rounded-lg">
                    <x-input label="Desde fecha" type="date" wire:model.live="boxStartDate" />
                    <x-input label="Hasta fecha" type="date" wire:model.live="boxEndDate" />
                </div>

                {{-- Resumen numérico del rango filtrado --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="stat bg-success/10 border border-success/20 rounded-xl p-3">
                        <div class="stat-title text-xs font-semibold text-success">Total Cobrado (Ingresos)</div>
                        <div class="stat-value text-xl font-bold text-success">${{ number_format($auditTotalCharges, 2) }}</div>
                    </div>
                    <div class="stat bg-error/10 border border-error/20 rounded-xl p-3">
                        <div class="stat-title text-xs font-semibold text-error">Total Reintegrado (Egresos)</div>
                        <div class="stat-value text-xl font-bold text-error">${{ number_format($auditTotalRefunds, 2) }}</div>
                    </div>
                    <div class="stat bg-info/10 border border-info/20 rounded-xl p-3">
                        <div class="stat-title text-xs font-semibold text-info">Saldo del Período</div>
                        <div class="stat-value text-xl font-bold text-info">${{ number_format($auditBalance, 2) }}</div>
                    </div>
                </div>

                {{-- Listado de movimientos del período --}}
                <div class="overflow-x-auto max-h-96 overflow-y-auto border border-base-300 rounded-lg">
                    <table class="table table-zebra table-sm w-full">
                        <thead class="bg-base-200 sticky top-0 z-10">
                            <tr>
                                <th>Recibo Nº</th>
                                <th>Fecha/Hora</th>
                                <th>Tipo</th>
                                <th>Aspirante / DNI</th>
                                <th>Concepto</th>
                                <th>Método</th>
                                <th class="text-right">Monto</th>
                                <th class="text-center">Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditPayments as $movement)
                                <tr>
                                    <td class="font-mono font-bold">{{ str_pad($movement->id, 6, '0', STR_PAD_LEFT) }}</td>
                                    <td class="text-xs whitespace-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($movement->type === 'charge')
                                            <span class="badge badge-success badge-xs">COBRO</span>
                                        @else
                                            <span class="badge badge-error badge-xs">DEVOLUCIÓN</span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-semibold">{{ $movement->payer_name }}</div>
                                        <div class="text-base-content/60">{{ $movement->doc_type }} {{ $movement->doc_number }}</div>
                                    </td>
                                    <td class="text-xs max-w-xs truncate" title="{{ $movement->concept }}">{{ $movement->concept }}</td>
                                    <td class="text-xs uppercase">{{ $movement->payment_method }}</td>
                                    <td class="text-right font-mono font-bold {{ $movement->type === 'refund' ? 'text-error' : 'text-success' }}">
                                        {{ $movement->type === 'refund' ? '-' : '+' }}${{ number_format($movement->amount, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('pre-enrollments.receipt', $movement->id) }}" target="_blank" class="btn btn-ghost btn-xs text-primary" title="Ver Recibo PDF">
                                            <x-icon name="o-document-text" class="w-4 h-4" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-sm py-6 text-base-content/50 italic">
                                        No se encontraron movimientos registrados en el rango de fechas seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <x-slot:actions>
                <div class="flex items-center justify-between w-full">
                    <x-dropdown top>
                        <x-slot:trigger>
                            <x-button label="Exportar a CSV" icon="o-arrow-down-tray" class="btn-primary" />
                        </x-slot:trigger>
                        <x-menu-item title="Separado por Comas (,)" wire:click="exportBoxCsv('comma')" icon="o-document-text" />
                        <x-menu-item title="Separado por Punto y Coma (;)" wire:click="exportBoxCsv('semicolon')" icon="o-table-cells" />
                        <x-menu-item title="Separado por Tabulador (Tab)" wire:click="exportBoxCsv('tab')" icon="o-bars-3-bottom-left" />
                    </x-dropdown>

                    <x-button label="Cerrar" @click="$wire.boxModal = false" class="btn-secondary" />
                </div>
            </x-slot:actions>
        </x-modal>
    </template>
</div>
