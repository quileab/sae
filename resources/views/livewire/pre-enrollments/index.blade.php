<div class="p-4">
    <h1 class="text-2xl font-bold mb-4">Preinscripciones {{ \App\Services\AcademicCycle::enrollmentCycle() }}</h1>
    <div class="flex flex-col sm:flex-row gap-4 mb-4 items-start sm:items-end justify-between">
        <div class="flex gap-2 items-end">
            <x-select label="Estado" wire:model.live="statusFilter" :options="[['id'=>'','name'=>'Todos'],['id'=>'submitted','name'=>'Enviado'],['id'=>'validated','name'=>'Validado'],['id'=>'waitlisted','name'=>'En espera'],['id'=>'observed','name'=>'Observado'],['id'=>'rejected','name'=>'Rechazado'],['id'=>'enrolled','name'=>'Matriculado']]" placeholder="Filtrar por estado" />
            <x-input label="Ciclo" wire:model.live="cycleFilter" type="number" />
            <x-button label="Exportar CSV" icon="o-arrow-down-tray" wire:click="exportCsv" class="btn-primary" spinner />
        </div>
        <div class="flex flex-wrap gap-2 items-center bg-base-200 p-2 rounded-lg">
            <div class="text-sm font-semibold mr-2">Totales:</div>
            <div class="badge badge-primary">{{ $currentLabel }}: {{ $currentCount }}</div>
            <div class="badge badge-neutral">Total: {{ $totalCount }}</div>
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
    ]" :rows="$preEnrollments" :row-decoration="$rowDecoration" :link="false" with-pagination>
        @scope('cell_name', $row) {{ ($row->payload['lname'] ?? '') }}, {{ ($row->payload['fname'] ?? '') }} @endscope
        @scope('cell_doc', $row) {{ $row->doc_type }} {{ $row->doc_number }} @endscope
        @scope('cell_escuela', $row) {{ $row->payload['escuela'] ?? '-' }} @endscope
        @scope('cell_status', $row) <x-badge :value="$row->status" /> @endscope
        @scope('actions', $row)
            <x-button icon="o-eye" wire:click="openDrawer({{ $row->id }})" class="btn-xs btn-ghost" />
        @endscope
    </x-table>
    

    <x-qb-drawer wire:model="drawer" title="Detalle preinscripción" right separator with-close-button class="lg:w-1/2">
        @if ($selected)
            <div class="space-y-3">
                <div class="flex justify-between items-center mb-4">
                    <div class="join">
                        <x-button label="Validar" wire:click="updateStatus({{ $selected->id }}, 'validated')" class="btn-success join-item btn-sm" />
                        <x-button label="Matricular" wire:click="updateStatus({{ $selected->id }}, 'enrolled')" class="btn-primary join-item btn-sm" />
                        <x-button label="Observar" wire:click="updateStatus({{ $selected->id }}, 'observed')" class="btn-warning join-item btn-sm" />
                        <x-button label="En espera" wire:click="updateStatus({{ $selected->id }}, 'waitlisted')" class="btn-warning join-item btn-sm" />
                        <x-button label="Rechazar" wire:click="updateStatus({{ $selected->id }}, 'rejected')" class="btn-error join-item btn-sm" />
                    </div>
                    
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button icon="o-trash" class="btn-outline btn-error btn-sm" />
                        </x-slot:trigger>
                        <x-menu-item title="¡Confirmar borrado!" wire:click="delete({{ $selected->id }})" class="text-error font-bold" icon="o-trash" />
                    </x-dropdown>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="font-bold">Ciclo:</span> {{ $selected->cycle_id }}</div>
                    <div><span class="font-bold">Estado:</span> <x-badge :value="$selected->status" /></div>
                    <div><span class="font-bold">Carrera:</span> {{ $selected->career->name ?? '-' }}</div>
                    <div><span class="font-bold">Doc:</span> {{ $selected->doc_type }} {{ $selected->doc_number }}</div>
                    <div><span class="font-bold">Email:</span> {{ $selected->email }}</div>
                    <div><span class="font-bold">Tel:</span> {{ $selected->phone }}</div>
                </div>
                <div class="divider my-2"></div>
                <h3 class="font-bold">Payload SIGAE</h3>
                <div class="bg-base-200 rounded p-3 text-xs max-h-96 overflow-y-auto">
                    @foreach ($selected->payload as $k => $v)
                        <div class="flex justify-between border-b border-base-300 py-1"><span class="font-semibold">{{ $k }}</span><span>{{ is_bool($v) ? ($v ? 'Sí' : 'No') : (is_array($v) ? json_encode($v) : $v) }}</span></div>
                    @endforeach
                </div>

            </div>
        @endif
    </x-qb-drawer>
</div>
