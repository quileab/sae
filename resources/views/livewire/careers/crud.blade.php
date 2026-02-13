<div>
    <x-card title="Carrera" shadow separator>
        <x-form wire:submit="save" no-separator>    
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-input label="ID" type="number" wire:model="data.id" />
                <x-input label="Resolución" type="text" wire:model="data.resolution" />
            </div>
            <x-input label="Carrera" type="text" wire:model="data.name" />
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-toggle label="Permitir inscripciones" wire:model="data.allow_enrollments" />
                <x-toggle label="Permitir evaluaciones" wire:model="data.allow_evaluations" />
            </div>

            <x-slot:actions>
                @if($data['id'])
                    <x-dropdown label="ELIMINAR" icon="o-trash" class="btn-error btn-outline">
                        <x-menu-item title="Confirmar eliminación" icon="o-trash" wire:click="delete" spinner class="text-error" />
                    </x-dropdown>
                @endif
                <x-button label="Cancelar" link="/careers" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>