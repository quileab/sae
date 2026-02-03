<div>
    <!-- HEADER -->
    <x-header title="Carreras">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        <x-table :headers="$headers" :rows="$careers" :sort-by="$sortBy" striped link="/career/{id}">
            @scope('cell_allow_enrollments', $career)
            <x-icon :name="$career['allow_enrollments'] ? 'o-check' : 'o-x-mark'" :class="$career['allow_enrollments'] ? 'text-success' : 'text-error'" />
            @endscope

            @scope('cell_allow_evaluations', $career)
            <x-icon :name="$career['allow_evaluations'] ? 'o-check' : 'o-x-mark'" :class="$career['allow_evaluations'] ? 'text-success' : 'text-error'" />
            @endscope

            @scope('actions', $career)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-chevron-up-down" class="btn-ghost btn-sm" />
                </x-slot:trigger>

                <x-button icon="o-bookmark" wire:click="bookmark({{ $career['id'] }})" spinner
                    class="btn-ghost btn-sm text-lime-500" />
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right separator with-close-button class="lg:w-1/3">
        <x-input placeholder="buscar..." wire:model.live.debounce="search" icon="o-magnifying-glass"
            @keydown.enter="$wire.drawer = false" />

        <x-slot:actions>
            <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/career" />
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>