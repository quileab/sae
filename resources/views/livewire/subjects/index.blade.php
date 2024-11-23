<?php

use App\Models\Subject;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $career_id;
    public $careers;

    public function mount(): void
    {
        $this->careers=\App\Models\Career::all();
        // if session career_id is set, use it
        if (session()->has('career_id')) {
            $this->career_id = session('career_id');
        }
        else {
            $this->career_id = $this->careers->first()->id;
        }
    }

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    // Delete action
    public function delete($id): void
    {
        $this->warning("Will delete #$id", 'It is fake.', position: 'toast-bottom');
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Nombre', 'class' => 'w-full'],
        ];
    }

    public function subjects(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos

        return Subject::get()
            ->where('career_id', $this->career_id)
            ->sortBy($this->sortBy) // Ordenar por el campo especificado
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    // Normalizar los caracteres latinos y convertir a minúsculas
                    $fullSearch = Str::of($item['name'] . ' ' . $item['id'])->lower()->ascii();
                    // Comparar la búsqueda normalizada con el texto del item
                    return $fullSearch->contains($search);
                });
            });
    }

    public function with(): array
    {
        return [
            'subjects' => $this->subjects(),
            'headers' => $this->headers()
        ];
    }

    public function updated($career_id, $value)
    {
        $this->subjects();
    }

    public function bookmark($id): void
    {
        // Notifica al componente Bookmarks
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $id]);
    }
}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Materias" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-choices-offline
                label="Carrera"
                wire:model.live="career_id"
                :options="$careers"
                placeholder="Buscar..."
                single
                searchable />
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" @click="$wire.drawer = true" responsive icon="o-funnel" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        <x-table :headers="$headers" :rows="$subjects" :sort-by="$sortBy">
            @scope('actions', $subject)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-chevron-up-down" class="btn-ghost btn-sm" />
                </x-slot:trigger>

                <x-button icon="o-bookmark" wire:click="bookmark({{ $subject['id'] }})" spinner class="btn-ghost btn-sm text-lime-500" />
                <x-button icon="o-trash" wire:click="delete({{ $subject['id'] }})" wire:confirm="Are you sure?" spinner class="btn-ghost btn-sm text-red-500" />
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button class="lg:w-1/3">
        <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />

        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
