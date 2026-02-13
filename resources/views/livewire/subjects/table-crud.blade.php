<div>
    <x-header title="Editor Rápido de Materias" subtitle="Carrera ID: {{ $career_id }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-select wire:model.live="career_id" :options="$careers" icon="o-academic-cap" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="VOLVER" icon="o-arrow-left" link="/subjects" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <div class="overflow-x-auto">
            <table class="table table-zebra table-xs">
                <thead>
                    <tr>
                        <th class="w-24 px-1">ID</th>
                        <th class="px-1">Nombre de la Materia</th>
                        <th class="w-32 px-1">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $index => $subject)
                        <tr wire:key="subject-{{ $subject['original_id'] }}">
                            <td class="p-1">
                                <x-input wire:model.defer="subjects.{{ $index }}.id" class="input-xs" />
                            </td>
                            <td class="p-1">
                                <x-input wire:model.defer="subjects.{{ $index }}.name" class="input-xs" />
                            </td>
                            <td class="p-1">
                                <div class="flex gap-1">
                                    <x-button icon="o-check" class="btn-xs btn-success" wire:click="save({{ $index }})" spinner />
                                    
                                    <x-dropdown>
                                        <x-slot:trigger>
                                            <x-button icon="o-trash" class="btn-xs btn-error btn-outline" />
                                        </x-slot:trigger>
                                        <x-menu-item title="Confirmar" icon="o-trash" wire:click="delete({{ $index }})" class="text-error" />
                                    </x-dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if(empty($subjects))
                        <tr>
                            <td colspan="3" class="text-center py-4 text-gray-500">
                                No hay materias para esta carrera.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <x-button label="AÑADIR FILA" icon="o-plus" class="btn-primary w-full" wire:click="add" spinner />
        </div>
    </x-card>
</div>
