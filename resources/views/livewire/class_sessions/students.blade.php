<div>
    <!-- HEADER -->
    <x-header title="Estudiantes">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        {{-- class session data --}}
        <div class="flex items-center  text-lg text-primary">
            <x-icon name="o-calendar" class="text-warning" />
            <span class="mx-2">{{ \Carbon\Carbon::parse($class_session->date ?? now())->format('d/m/Y') ?? '-' }}</span>
            <x-icon name="o-cube" class="text-warning" />
            <span class="mx-2">{{ $class_session->class_number ?? '-' }} » {{ $class_session->unit ?? '-' }}</span>
            <x-icon name="o-academic-cap" class="text-warning" />
            <span class="mx-2">{{ $class_session->content ?? 'CLASE INEXISTENTE' }}</span>
        </div>

        <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
            {{-- loop index --}}
            @scope('cell_row_id', $item)
            {{ $loop->index + 1 }}
            @endscope

            {{-- actions --}}
            @scope('actions', $item)
            <div class="flex items-center align-middle mr-4 gap-2">
                <x-button label="100" icon="o-percent-badge" class="text-success btn-ghost btn-sm"
                    wire:click="attendanceSet({{ $item }}, 100)" />
                <x-button label="Registro" icon="o-user-circle" class="text-yellow-600 btn-ghost btn-sm"
                    wire:click="attendance({{ $item }})" />
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button icon="o-chevron-down" class="btn-ghost btn-sm" />
                    </x-slot:trigger>

                    <x-button label="LISTA" icon="o-document-text" class="btn-primary"
                        link="/printClassbooks/subject/{{ $item->id }}" external no-wire-navigate />
                    <x-menu-item title="Chat" icon="o-chat-bubble-left" class="text-yellow-600" />

                </x-dropdown>
            </div>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        {{-- Show data of current selected item: lastname firstname --}}
        <div class="flex items-center gap-4 text-lg">
            <div class="flex items-center text-lg mb-4">
                <x-icon name="o-user-circle" />
                {{ $data['lastname'] ?? '' }}, {{ $data['firstname'] ?? '' }}
            </div>
        </div>

        <x-input label="Asistencia" wire:model="grades.attendance" type="number" min="0" max="100" inline
            class="w-full" />
        <div class="grid grid-cols-3 items-center gap-4 mt-2">
            <x-button label="Ausente" icon="o-x-mark" class="btn-error btn-outline btn-sm"
                wire:click="$set('grades.attendance', 0)" />
            <x-button label="50" icon="o-check" class="btn-warning btn-outline btn-sm"
                wire:click="$set('grades.attendance', 50)" />
            <x-button label="100" icon="o-check" class="btn-success btn-outline btn-sm"
                wire:click="$set('grades.attendance', 100)" />
        </div>
        <div class="flex items-center gap-4 mt-4">
            <x-input label="Calificación" wire:model="grades.grade" type="number" min="0" max="100" class="w-24"
                inline />
            <x-checkbox label="Aprueba" wire:model="grades.approved" hint="Notas no numéricas" />
        </div>
        <div class="grid items-center gap-4 mt-4">
            <x-input label="Observaciones" wire:model="grades.comments" type="text" placeholder="Observaciones" hint="Comience con Ev: o TP: para indicar el TIPO (Evaluación o Trabajo Practico), de esta manera el sistema podrá calcular el promedio de notas" class="w-full" />
        </div>
        <x-slot:actions>
            <x-dropdown>
                <x-slot:trigger>
                    <x-button label="Desmatricular" icon="o-exclamation-triangle" class="btn-warning" />
                </x-slot:trigger>
                <x-menu-item title="ACEPTAR" icon="o-user-minus" class="bg-error" wire:click="deregister()" />
            </x-dropdown>
            <x-button label="GUARDAR" icon="o-check" class="btn-primary" wire:click="saveGrade" spinner="saveGrade" />
        </x-slot:actions>
    </x-drawer>
</div>