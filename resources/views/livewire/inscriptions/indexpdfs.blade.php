<div>
    <!-- HEADER -->
    <x-header title="Inscripciones PDF Realizadas" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>
    {{-- if return with message --}}
    @if (session()->has('success'))
        <x-alert icon="o-information-circle" title="{{ session('success') }}" class="alert-success" dismissible />
    @endif

    <!-- TABLE  -->
    <x-card>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 sticky top-0 z-20 backdrop-blur-md
        pb-1 border-b border-black/20 dark:border-white/20">
            <x-select wire:model.lazy="inscription_id" label="Inscripciones a" :options="$inscriptions"
                option-value="id" option-label="description" />
            <x-select wire:model.lazy="career_id" label="Carrera" :options="$careers" />
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped wire:model="selected" selectable
                @row-selection="console.log($event.detail)">
                @scope('actions', $item)
                <x-button label="PDF" link="pdf/{{ $item['pdflink'] }}" external icon="s-document"
                    class="btn-error text-red-700 btn-ghost w-32" />
                @endscope
            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        <x-dropdown label="Eliminar" class="btn-error" right>
            <x-menu-item title="Confirmar" wire:click="deleteSelected" spinner="deleteSelected" icon="o-trash" />
        </x-dropdown>
    </x-drawer>

</div>