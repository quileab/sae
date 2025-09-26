<div>
    <x-modal wire:model="showModal" x-on:close-event-modal.window="$wire.set('showModal', false)" wire:ignore.self
        title="{{ isset($this->eventId) ? 'Editar Evento' : 'Crear Evento' }}">
        <x-form wire:submit.prevent="save">
            <x-input label="Título" wire:model="title" :disabled="$isReadOnly" />
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-input type="datetime-local" label="Inicio" wire:model="start" :disabled="$isReadOnly" />
                <x-input type="datetime-local" label="Fin" wire:model="end" :disabled="$isReadOnly" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-input type="color" label="Color" wire:model="color" list="colors" :disabled="$isReadOnly" />
                <datalist id="colors">
                    <option value="#cc0000">Rojo</option>
                    <option value="#bb8300">Anaranjado</option>
                    <option value="#cccc00">Amarillo</option>
                    <option value="#007000">Verde</option>
                    <option value="#0000cc">Azul</option>
                    <option value="#600060">Lila</option>
                </datalist>

                @if(Auth::user()->hasAnyRole(['teacher', 'admin', 'principal']))
                    <x-select label="Carrera" wire:model.live="career_id" :options="$careers" option-value="id"
                        option-label="name" placeholder="Seleccione una carrera" :disabled="$isReadOnly" />

                    @if($career_id)
                        <x-select label="Materia" wire:model="subject_id" :options="$subjects" option-value="id"
                            option-label="name" placeholder="Seleccione una materia" :disabled="$isReadOnly" />
                    @endif
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Guardar" type="submit" class="btn-primary" :disabled="$isReadOnly" />
                @if (isset($this->eventId))
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button icon="o-trash" class="btn-error" :disabled="$isReadOnly" />
                        </x-slot:trigger>
                        <x-menu-item title="Eliminar"
                            wire:click="delete"
                            wire:confirm="¿Está seguro que desea eliminar este evento?" />
                    </x-dropdown>
                @endif
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>