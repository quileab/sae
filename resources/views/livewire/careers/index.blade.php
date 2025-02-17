<?php

use App\Models\Career;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Nombre', 'class' => 'w-full'],
            ['key' => 'resolution', 'label' => 'Resol.', 'class' => 'w-32'],
            ['key' => 'allow_enrollments', 'label' => 'Matric.', 'sortable' => false],
            ['key' => 'allow_evaluations', 'label' => 'Eval.', 'sortable' => false],
        ];
    }

    public function careers(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        return Career::get()
        ->sortBy($this->sortBy) // Asegúrate de que $this->sortBy sea válido
        ->when($this->search, function (Collection $collection) use ($search) {
            return $collection->filter(function ($item) use ($search) {
                // Normalizar los caracteres latinos y convertir a minúsculas
                $fullSearch = Str::of($item['name'] . ' ' . $item['id'])->lower()->ascii();
                return str_contains($fullSearch, $search);
            });
        })->take(20);
    }

    public function with(): array
    {
        return [
            'careers' => $this->careers(),
            'headers' => $this->headers()
        ];
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'career_id', 'value' => $id]);
    }
}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Carreras" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        <x-table :headers="$headers" :rows="$careers" :sort-by="$sortBy"
            striped link="/career/{id}"
            >
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

                <x-button icon="o-bookmark" wire:click="bookmark({{ $career['id'] }})" spinner class="btn-ghost btn-sm text-lime-500" />
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right separator with-close-button class="lg:w-1/3">
        <x-input placeholder="buscar..." wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />

        <x-slot:actions>
            <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/career" />
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
