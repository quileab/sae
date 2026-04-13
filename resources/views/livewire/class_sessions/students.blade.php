<div>
    <!-- HEADER -->
    <x-header title="Estudiantes" subtitle="{{ $this->subject->name ?? '' }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
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

        <x-table :headers="$headers" :rows="$this->items" :sort-by="$sortBy" striped>
            {{-- loop index --}}
            @scope('cell_row_id', $item, $loop)
            {{ $loop->index + 1 }}
            @endscope

            {{-- attendance --}}
            @scope('cell_attendance', $item)
            <div class="flex items-center gap-2">
                <div class="w-12">
                    @if($item->attendance > 0)
                        <x-badge value="{{ $item->attendance }}%" class="badge-primary font-bold" />
                    @else
                        <x-badge value="Ausente" class="badge-ghost text-error opacity-50 italic" />
                    @endif
                </div>
                <div class="flex items-center gap-1 border-l border-white/10 pl-2">
                    <x-button label="100" class="btn-xs {{ $item->attendance == 100 ? 'btn-success' : 'btn-outline btn-success' }}"
                        wire:click="attendanceSet({{ $item->id }}, 100)" 
                        wire:target="attendanceSet({{ $item->id }}, 100)" spinner tooltip="100%" />
                    <x-button label="50" class="btn-xs {{ $item->attendance == 50 ? 'btn-warning' : 'btn-outline btn-warning' }}"
                        wire:click="attendanceSet({{ $item->id }}, 50)" 
                        wire:target="attendanceSet({{ $item->id }}, 50)" spinner tooltip="50%" />
                    <x-button label="X" class="btn-xs {{ ($item->attendance == 0 && !is_null($item->attendance)) ? 'btn-error' : 'btn-outline btn-error' }}"
                        wire:click="attendanceSet({{ $item->id }}, 0)" 
                        wire:target="attendanceSet({{ $item->id }}, 0)" spinner tooltip="Ausente" />
                    <x-button icon="o-pencil-square" class="btn-xs btn-ghost text-primary"
                        wire:click="attendance({{ $item->id }})" tooltip="Editar detalle" />
                    <x-button icon="o-eye" class="btn-xs btn-ghost text-info"
                        wire:click="viewProfile({{ $item->id }})" tooltip="Ver Perfil" />
                </div>
            </div>
            @endscope
        </x-table>
    </x-card>

    <!-- PROFILE MODAL -->
    <x-modal wire:model="profileModal" class="backdrop-blur" title="Perfil del Estudiante">
        @if($studentProfile)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-4 border-b border-base-300 pb-4">
                    <x-avatar :placeholder="strtoupper(substr($studentProfile->firstname, 0, 1))" class="!w-16 !rounded-lg bg-primary text-white text-2xl" />
                    <div>
                        <h2 class="text-2xl font-bold">{{ $studentProfile->lastname }}, {{ $studentProfile->firstname }}</h2>
                        <p class="text-sm opacity-70">DNI: {{ $studentProfile->id }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-list-item :item="$studentProfile" no-hover no-separator>
                        <x-slot:avatar>
                            <x-icon name="o-envelope" class="text-primary" />
                        </x-slot:avatar>
                        <x-slot:value>Email</x-slot:value>
                        <x-slot:sub-value>{{ $studentProfile->email }}</x-slot:sub-value>
                        <x-slot:actions>
                            <x-button icon="o-clipboard" class="btn-ghost btn-xs" @click="navigator.clipboard.writeText('{{ $studentProfile->email }}'); $wire.success('Email copiado')" />
                        </x-slot:actions>
                    </x-list-item>

                    <x-list-item :item="$studentProfile" no-hover no-separator>
                        <x-slot:avatar>
                            <x-icon name="o-phone" class="text-success" />
                        </x-slot:avatar>
                        <x-slot:value>Teléfono</x-slot:value>
                        <x-slot:sub-value>{{ $studentProfile->phone ?: 'No registrado' }}</x-slot:sub-value>
                        <x-slot:actions>
                            @if($studentProfile->phone)
                                <x-button icon="o-clipboard" class="btn-ghost btn-xs" @click="navigator.clipboard.writeText('{{ $studentProfile->phone }}'); $wire.success('Teléfono copiado')" />
                            @endif
                        </x-slot:actions>
                    </x-list-item>
                </div>

                <div class="bg-base-200 rounded-lg p-4">
                    <h3 class="font-bold mb-2 flex items-center gap-2">
                        <x-icon name="o-academic-cap" class="w-4 h-4" />
                        Carreras Inscriptas
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($studentProfile->careers as $career)
                            <x-badge :value="$career->name" class="badge-outline" />
                        @empty
                            <p class="text-sm opacity-50 italic">Sin carreras asignadas</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button label="Marcar" icon="o-bookmark" wire:click="bookmark({{ $studentProfile?->id }})" class="btn-outline btn-sm" />
            <x-button label="Ir al Chat" icon="o-chat-bubble-left" link="/chat?user_id={{ $studentProfile?->id }}" class="btn-primary btn-sm" />
            <x-button label="Cerrar" @click="$wire.profileModal = false" class="btn-sm" />
        </x-slot:actions>
    </x-modal>

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