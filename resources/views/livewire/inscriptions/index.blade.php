<div>
    <!-- HEADER -->
    <x-header title="Inscripciones Realizadas" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" class="btn-primary" />
        </x-slot:actions>
    </x-header>
    {{-- if return with message --}}
    @if (session()->has('success'))
        <x-alert icon="o-information-circle" title="{{ session('success') }}" class="alert-success" dismissible />
    @endif

    <!-- TABLE  -->
    <x-card>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 sticky top-0 z-20 backdrop-blur-md
        pb-1 border-b border-black/20 dark:border-white/20">
            <x-select wire:model.live="inscription_id" label="Inscripciones a" :options="$inscriptions"
                option-value="id" option-label="description" />
            <x-select wire:model.live="career_id" label="Carrera" :options="$careers" />
            <x-select wire:model.live="subject_id" label="Materia" :options="$subjects" placeholder="TODAS" />
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" selectable wire:model="selectedRows"
                striped>

            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">

        <div class="flex flex-col gap-4">
            <div class="p-4 border rounded-lg border-error/20 bg-error/5">
                <div class="flex items-start gap-3 mb-4">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6 text-error" />
                    <p class="text-sm text-error">
                        Al confirmar en el menú desplegable, las inscripciones seleccionadas serán eliminadas permanentemente.
                    </p>
                </div>
                <x-dropdown label="Confirmar Eliminación" icon="o-trash" class="w-full btn-error">
                    <x-menu-item title="Eliminar seleccionados" icon="o-trash"
                        wire:click="deleteSelected" />
                </x-dropdown>
            </div>
        </div>

    </x-drawer>

</div>