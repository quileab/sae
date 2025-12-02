<div>
    <x-modal wire:model="showModal" x-on:close-event-modal.window="$wire.set('showModal', false)" wire:ignore.self
        title="{{ isset($this->eventId) ? 'Editar Evento' : 'Crear Evento' }}">
        <x-form wire:submit.prevent="save" no-separator>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <div class="md:col-span-2">
                    <x-input label="Título" wire:model="title" :disabled="$isReadOnly" />
                </div>
                <div>
                    <x-input type="color" label="Color" wire:model="color" list="colors" :disabled="$isReadOnly" />
                    <datalist id="colors">
                        <option value="#cc0000">Rojo</option>
                        <option value="#bb8300">Anaranjado</option>
                        <option value="#cccc00">Amarillo</option>
                        <option value="#007000">Verde</option>
                        <option value="#0000cc">Azul</option>
                        <option value="#600060">Lila</option>
                    </datalist>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-input type="datetime-local" label="Inicio" wire:model="start" :disabled="$isReadOnly" />
                <x-input type="datetime-local" label="Fin" wire:model="end" :disabled="$isReadOnly" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">

                @if(Auth::user()->hasAnyRole(['teacher', 'admin', 'principal']))
                    <x-select label="Carrera" wire:model.live="career_id" :options="$careers" option-value="id"
                        option-label="name" placeholder="Seleccione una carrera" :disabled="$isReadOnly" />

                    @if($career_id)
                        <x-select label="Materia" wire:model="subject_id" :options="$subjects" option-value="id"
                            option-label="name" placeholder="Seleccione una materia" :disabled="$isReadOnly" />
                    @endif
                @endif
            </div>
            <x-textarea label="Descripción" wire:model="description" :disabled="$isReadOnly" />
            @if(Auth::user()->hasAnyRole(['admin', 'director', 'administrative']))
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <x-select label="Presidente" wire:model="presidente_id" :options="$teachers" option-value="id" option-label="name" placeholder="Seleccione un presidente" :disabled="$isReadOnly" />
                <x-select label="Vocal 1" wire:model="vocal1_id" :options="$teachers" option-value="id" option-label="name" placeholder="Seleccione un vocal" :disabled="$isReadOnly" />
                <x-select label="Vocal 2" wire:model="vocal2_id" :options="$teachers" option-value="id" option-label="name" placeholder="Seleccione un vocal" :disabled="$isReadOnly" />
            </div>
            @endif

            <x-slot:actions>
                <div class="flex justify-between items-center w-full">
                    <div class="flex gap-2">
                        @if (isset($this->eventId))
                            <x-dropdown>
                                <x-slot:trigger>
                                    <x-button icon="o-trash" class="btn-error" :disabled="$isReadOnly" />
                                </x-slot:trigger>
                                <x-menu-item title="Eliminar"
                                    wire:click="delete"
                                    class="bg-error text-white" />
                            </x-dropdown>
                            <x-button icon="o-document-duplicate" wire:click="duplicate" class="btn-primary" tooltip="Duplicar" />
                        @endif
                    </div>
                    <x-button label="Guardar" type="submit" class="btn-primary" :disabled="$isReadOnly" />
                </div>
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>