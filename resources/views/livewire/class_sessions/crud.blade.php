<div>
    <x-card title="Clase {{ $data['id'] !== null ? $data['id'] : 'Nueva' }}" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive icon="o-ellipsis-vertical"
                class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
        <x-form wire:submit="save" no-separator>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <x-input label="Fecha" type="date" wire:model="data.date" />
                <x-input label="Clase #" type="number" wire:model="data.class_number" />
                <x-input label="Unidad" type="number" wire:model="data.unit"
                    hint="Utilice 0 para indicar que no hubo clase" />
                <x-input label="Tipo" type="text" wire:model="data.type" list="types" />
                <datalist id="types">
                    <option value="Expositivo"></option>
                    <option value="Teórico"></option>
                    <option value="Práctico"></option>
                    <option value="Teórico-Práctico"></option>
                    <option value="Evaluativo"></option>
                    <option value="Introductorio"></option>
                </datalist>
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-textarea label="Contenido" wire:model="data.content" rows="6" />
                <x-textarea label="Actividades" wire:model="data.activities" rows="6" />
            </div>
            <div class="grid grid-cols-1 gap-2">
                <x-textarea label="Observaciones" wire:model="data.observations" rows="2" />
            </div>

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>

    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button separator with-close-button close-on-escape
        class="lg:w-1/3">

        <x-slot:actions>
            <x-dropdown label="ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash"
                    class="bg-error text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>