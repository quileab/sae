<div>
    @teleport('body')
        <x-modal wire:model="showModal" x-on:close-event-modal.window="$wire.set('showModal', false)" wire:ignore.self
            title="{{ isset($this->eventId) ? 'Editar Evento' : 'Crear Evento' }}"
            separator
            class="backdrop-blur"
            box-class="max-w-4xl">
            <x-form wire:submit.prevent="save" no-separator>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    {{-- COLUMNA IZQUIERDA: DETALLES DEL EVENTO (7 cols) --}}
                    <div class="lg:col-span-7 space-y-4">
                        {{-- Título --}}
                        <x-input label="Título del Evento" icon="o-calendar-days" wire:model="title" placeholder="Ej. Mesa de Examen Final, Acto Institucional..." :disabled="$isReadOnly" />

                        {{-- Descripción --}}
                        <x-textarea label="Descripción / Observaciones" wire:model="description" placeholder="Detalles adicionales sobre el evento, aula asignada, requisitos..." rows="4" :disabled="$isReadOnly" />

                        {{-- Horarios: Inicio y Fin --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-base-200/40 p-3 rounded-xl border border-base-200">
                            <x-input type="datetime-local" label="Fecha y Hora Inicio" icon="o-clock" wire:model="start" :disabled="$isReadOnly" class="input-sm" />
                            <x-input type="datetime-local" label="Fecha y Hora Fin" icon="o-clock" wire:model="end" :disabled="$isReadOnly" class="input-sm" />
                        </div>

                        {{-- Selector Rápido de Color --}}
                        <div>
                            <div class="text-xs font-semibold text-base-content/70 mb-1.5 flex items-center gap-1">
                                <x-icon name="o-paint-brush" class="w-3.5 h-3.5 text-primary" />
                                <span>Color de etiqueta</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach([
                                    '#0C4767' => 'Institucional',
                                    '#0284c7' => 'Celeste',
                                    '#16a34a' => 'Verde',
                                    '#eab308' => 'Amarillo',
                                    '#ea580c' => 'Naranja',
                                    '#dc2626' => 'Rojo',
                                    '#9333ea' => 'Púrpura',
                                ] as $hex => $name)
                                    <button type="button"
                                        wire:click="$set('color', '{{ $hex }}')"
                                        class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110 flex items-center justify-center {{ $color === $hex ? 'ring-2 ring-primary ring-offset-2 scale-110 border-white' : 'border-transparent opacity-80 hover:opacity-100' }}"
                                        style="background-color: {{ $hex }};"
                                        title="{{ $name }}">
                                        @if($color === $hex)
                                            <x-icon name="o-check" class="w-3.5 h-3.5 text-white stroke-[3]" />
                                        @endif
                                    </button>
                                @endforeach
                                <div class="flex items-center ml-2 border-l border-base-content/10 pl-2">
                                    <input type="color" wire:model.live="color" class="w-7 h-7 rounded cursor-pointer border-0 bg-transparent" title="Color personalizado" :disabled="$isReadOnly" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA: DESTINATARIOS Y TRIBUNAL (5 cols) --}}
                    <div class="lg:col-span-5 space-y-4 bg-base-200/30 p-4 rounded-xl border border-base-200">
                        {{-- Público Objetivo --}}
                        @if(Auth::user()->hasAnyRole(['admin', 'director', 'administrative', 'principal']))
                            <div class="space-y-1.5">
                                <div class="text-xs font-semibold text-base-content/70 flex items-center gap-1">
                                    <x-icon name="o-user-group" class="w-3.5 h-3.5 text-primary" />
                                    <span>Público Objetivo</span>
                                </div>
                                <div class="join w-full">
                                    <button type="button" wire:click="$set('target', 'all')"
                                        class="btn btn-sm join-item flex-1 gap-1 {{ $target === 'all' ? 'btn-primary' : 'btn-outline' }}" :disabled="$isReadOnly">
                                        <x-icon name="o-globe-americas" class="w-4 h-4" /> Todos
                                    </button>
                                    <button type="button" wire:click="$set('target', 'students')"
                                        class="btn btn-sm join-item flex-1 gap-1 {{ $target === 'students' ? 'btn-primary' : 'btn-outline' }}" :disabled="$isReadOnly">
                                        <x-icon name="o-academic-cap" class="w-4 h-4" /> Alumnos
                                    </button>
                                    <button type="button" wire:click="$set('target', 'teachers')"
                                        class="btn btn-sm join-item flex-1 gap-1 {{ $target === 'teachers' ? 'btn-primary' : 'btn-outline' }}" :disabled="$isReadOnly">
                                        <x-icon name="o-briefcase" class="w-4 h-4" /> Docentes
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Carrera y Materia --}}
                        @if(Auth::user()->hasAnyRole(['teacher', 'admin', 'principal']))
                            <div class="space-y-3 pt-1">
                                <x-select label="Carrera Asignada" icon="o-academic-cap" wire:model.live="career_id" :options="$careers" option-value="id"
                                    option-label="name" placeholder="Todas / General" :disabled="$isReadOnly" class="select-sm" />

                                @if($career_id)
                                    <x-select label="Materia Específica" icon="o-book-open" wire:model="subject_id" :options="$subjects" option-value="id"
                                        option-label="name" placeholder="Seleccione materia..." :disabled="$isReadOnly" class="select-sm" />
                                @endif
                            </div>
                        @endif

                        {{-- Mesa de Examen / Tribunal Docente --}}
                        @if(Auth::user()->hasAnyRole(['admin', 'director', 'administrative', 'principal']))
                            <div class="pt-2 border-t border-base-content/10 space-y-2">
                                <div class="text-xs font-bold text-base-content/80 flex items-center justify-between">
                                    <span class="flex items-center gap-1">
                                        <x-icon name="o-identification" class="w-3.5 h-3.5 text-secondary" />
                                        <span>Tribunal Evaluador (Opcional)</span>
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    <x-select icon="o-user" label="Presidente" wire:model="presidente_id" :options="$teachers" option-value="id" option-label="name" placeholder="Seleccione presidente" :disabled="$isReadOnly" class="select-xs" />
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <x-select icon="o-user" label="Vocal 1" wire:model="vocal1_id" :options="$teachers" option-value="id" option-label="name" placeholder="Vocal 1" :disabled="$isReadOnly" class="select-xs" />
                                        <x-select icon="o-user" label="Vocal 2" wire:model="vocal2_id" :options="$teachers" option-value="id" option-label="name" placeholder="Vocal 2" :disabled="$isReadOnly" class="select-xs" />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ACCIONES INFERIORES --}}
                <x-slot:actions>
                    <div class="flex justify-between items-center w-full">
                        <div class="flex items-center gap-2">
                            @if (isset($this->eventId))
                                <x-dropdown>
                                    <x-slot:trigger>
                                        <x-button icon="o-trash" label="Eliminar" class="btn-error btn-outline btn-sm" :disabled="$isReadOnly" />
                                    </x-slot:trigger>
                                    <x-menu-item title="Confirmar eliminación"
                                        wire:click="delete"
                                        icon="o-trash"
                                        class="bg-error text-white font-bold" />
                                </x-dropdown>
                                <x-button icon="o-document-duplicate" label="Duplicar" wire:click="duplicate" class="btn-ghost btn-sm" tooltip="Duplicar este evento" />
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <x-button label="Cancelar" icon="o-x-mark" class="btn-ghost btn-sm" @click="$wire.showModal = false" />
                            <x-button label="{{ isset($this->eventId) ? 'Actualizar Evento' : 'Guardar Evento' }}" icon="o-check" type="submit" class="btn-primary btn-sm" :disabled="$isReadOnly" spinner="save" />
                        </div>
                    </div>
                </x-slot:actions>
            </x-form>
        </x-modal>
    @endteleport
</div>
