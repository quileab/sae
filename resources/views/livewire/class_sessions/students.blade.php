<div>
    <!-- HEADER -->
    <x-header title="Estudiantes" subtitle="{{ $this->subject->name ?? '' }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        @if(auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative']))
        <x-slot:actions>
            <x-button label="Calcular Aprobaciones" icon="o-calculator" class="btn-primary" 
                wire:confirm="¿Estás seguro de que deseas calcular y cerrar la condición de los alumnos promovidos para esta materia? Los alumnos con promedio de evaluaciones >= 8, trabajos prácticos >= 6 y asistencia >= 75% serán marcados como 'Aprobados (Completada)'"
                wire:click="calculateApprovals" spinner />
        </x-slot:actions>
        @endif
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

            {{-- academic status --}}
            @scope('cell_academic_status', $item)
            @php
                $enrollment = $this->enrollments->get($item->enrollment_id);
                $status = $enrollment?->academic_status;
            @endphp
            @if($status)
                @if(in_array($status['status'], ['Sin Fecha', 'Sin Datos']))
                    <x-badge :value="$status['status']" :class="$status['color'] . ' badge-outline'" />
                @else
                    <x-badge value="{{ $status['attendance'] }}%" :class="$status['color'] . ' badge-outline'" />
                @endif
            @endif
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
                @if(isset($this->justifications[$item->id]))
                    <span class="tooltip" data-tip="Justificación: {{ $this->justifications[$item->id]['description'] }}">
                        <span class="badge badge-info badge-sm gap-1 text-white py-0.5 px-2">
                            <x-icon name="o-document-text" class="w-3.5 h-3.5" />
                            Justificado
                        </span>
                    </span>
                @endif
            </div>
            @endscope

            {{-- actions --}}
            @scope('actions', $item)
            <div class="flex items-center gap-1 transition-opacity duration-200"
                wire:loading.class="opacity-50 pointer-events-none"
                wire:target="attendanceSet({{ $item->id }}, 100), attendanceSet({{ $item->id }}, 50), attendanceSet({{ $item->id }}, 0)">
                <x-button label="100" class="btn-xs {{ $item->attendance == 100 ? 'btn-success' : 'btn-outline btn-success' }}"
                    wire:click="attendanceSet({{ $item->id }}, 100)" 
                    wire:target="attendanceSet({{ $item->id }}, 100)" tooltip="100%" />
                <x-button label="50" class="btn-xs {{ $item->attendance == 50 ? 'btn-warning' : 'btn-outline btn-warning' }}"
                    wire:click="attendanceSet({{ $item->id }}, 50)" 
                    wire:target="attendanceSet({{ $item->id }}, 50)" tooltip="50%" />
                <x-button label="X" class="btn-xs {{ ($item->attendance == 0 && !is_null($item->attendance)) ? 'btn-error' : 'btn-outline btn-error' }}"
                    wire:click="attendanceSet({{ $item->id }}, 0)" 
                    wire:target="attendanceSet({{ $item->id }}, 0)" tooltip="Ausente" />
                
                <div class="border-l border-base-300 ml-1 pl-1 flex gap-1">
                    <x-button icon="o-pencil-square" class="btn-xs btn-ghost text-primary"
                        wire:click="attendance({{ $item->id }})" tooltip="Editar detalle" />
                </div>
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

        <div class="mt-4">
            <x-select label="Tipo de Nota" wire:model="grades.type" :options="[
                ['id' => 'regular', 'name' => 'Clase Normal (Asistencia)'],
                ['id' => 'evaluation', 'name' => 'Evaluación / Examen'],
                ['id' => 'practical_work', 'name' => 'Trabajo Práctico'],
                ['id' => 'recuperatory', 'name' => 'Evaluación / Recuperatorio'],
            ]" option-value="id" option-label="name" />
        </div>

        @if(($grades['type'] ?? null) === 'recuperatory')
            <div class="mt-4">
                <x-select label="Recupera a" wire:model="grades.recovered_grade_id" :options="$evaluationChoices"
                    option-value="id" option-label="name" placeholder="Seleccione la evaluación a recuperar" />
            </div>
        @endif

        <div class="grid items-center gap-4 mt-4">
            <x-input label="Observaciones" wire:model="grades.comments" type="text" placeholder="Observaciones" hint="Ya no es necesario anteponer EV o TP, use el selector superior" class="w-full" />
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