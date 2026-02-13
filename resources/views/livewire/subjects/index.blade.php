<div>
    <!-- HEADER -->
    <x-header title="Materias" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-3">
                <x-select wire:model.live="career_id" :options="$careers" icon="o-academic-cap" />
                <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/subject" />
            <x-button label="EDITOR RÁPIDO" icon="o-table-cells" class="btn-outline" link="/subjects-table" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        <x-table :headers="$headers" :rows="$subjects" :sort-by="$sortBy" striped link="/subject/{id}">
            @scope('actions', $subject)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-chevron-up-down" class="btn-ghost btn-sm" />
                </x-slot:trigger>

                <x-button icon="o-academic-cap" label="Administrar Contenido" link="{{ route('subjects.content-manager', ['subject' => $subject->id]) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-bookmark" label="Recordar" wire:click="bookmark({{ $subject['id'] }})" spinner
                    class="btn-ghost btn-sm text-lime-500" />
                {{-- <x-button icon="o-trash" wire:click="delete({{ $subject['id'] }})" wire:confirm="Are you sure?"
                    spinner class="btn-ghost btn-sm text-red-500" /> --}}
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

</div>