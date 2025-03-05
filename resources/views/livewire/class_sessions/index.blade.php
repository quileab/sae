<?php

use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'date', 'direction' => 'asc'];
    public $subjects = [];
    public $subject_id = null;
    public $user;
    public $cycle;

    public function mount(): void
    {
        $this->user = \App\Models\User::find(session('user_id')) ?: auth()->user();
        $this->cycle = session('cycle_id');
        $this->subjects = $this->user->subjects;

        try {
            $this->subject_id = session('subject_id') ?: $this->subject_id = $this->subjects->first()->id;
        } catch (\Exception $e) {
            $this->subject_id = null;
        }
    }

    public function updatedSubjectId($value): void
    {
        $this->items();
        $this->info('Materia Seleccionada.' . $value);
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $value]);
    }

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
            ['key' => 'date', 'label' => 'Fecha', 'class' => 'w-64'],
            ['key' => 'type', 'label' => 'Clase', 'class' => 'w-10'],
            ['key' => 'content', 'label' => 'Contenido', 'class' => 'w-full'],
            ['key' => 'unit', 'label' => 'Unidad', 'sortable' => false],
            ['key' => 'students', 'label' => 'Asistencia/Notas', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $this->subject_id]);
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        // return collection of items that belongs to user, subject and content matches search
        return ClassSession::whereYear('date', $this->cycle)
            ->where('subject_id', $this->subject_id)
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    // Normalizar los caracteres latinos y convertir a minúsculas
                    $fullSearch = Str::of($item['content'])->lower()->ascii();
                    return str_contains($fullSearch, $search);
                });
            })
            ->get()
            ->sortBy($this->sortBy['column'], SORT_REGULAR, $this->sortBy['direction'])
            //->take(20)
        ;
    }

    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

}; ?>

<div wire:init="items">
    <!-- HEADER -->
    <x-header title="Libro de Temas">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>
    <!-- TABLE  -->
    <x-card>
        <div class="flex justify-between items-center">
            @if($subjects->count() == 0)
                <x-alert title="Sin materias" description="No tienes materias asignadas." icon="o-exclamation-triangle"
                    class="alert-warning" />
            @else
                <x-select label="Materia" icon="o-queue-list" :options="$subjects" option-label="fullname"
                    wire:model.lazy="subject_id" inline />
                <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/class-session" />
            @endif
        </div>

        <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped link="/class-session/{id}">
            @scope('cell_date', $item)
            {{ Carbon\Carbon::parse($item->date)->format('d/m/Y') }}
            @endscope
            @scope('cell_students', $item)
            <x-button label="Reg." icon="o-user-group" link="/class-sessions/students/{{ $item->id }}"
                class="btn-primary btn-sm" />
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