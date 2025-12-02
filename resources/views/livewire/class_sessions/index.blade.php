<?php

use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];
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
        $this->info('Materia Seleccionada.' . $value, position: 'toast-top toast-center');
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
        $query = ClassSession::whereYear('date', $this->cycle)
            ->where('subject_id', $this->subject_id);

        if ($this->search) {
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', '%' . $search . '%')
                    ->orWhere('activities', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();

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
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <x-select label="Materia" icon="o-queue-list" :options="$subjects" option-label="fullname"
                        wire:model.lazy="subject_id" inline />
                    <div class="text-right">
                        <x-button label="LISTA {{ $subject_id }}" icon="o-document-text" class="btn-primary"
                            link="/printClassbooks/{{ $subject_id }}" external no-wire-navigate />
                        <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/class-session" />
                    </div>
                </div>
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
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        <div class="grid grid-cols-2 gap-2">
            <x-button label="Nuevo Tema" icon="o-plus" class="btn-success" link="/class-session" />
            @if($subject_id)
                <x-button label="Administrar Contenidos" icon="o-book-open"
                    link="{{ route('subjects.content-manager', ['subject' => $subject_id]) }}" class="btn-primary" />
            @endif
            <x-button label="Asistencia" icon="o-document-text" class="btn-sm btn-info"
                link="/print/student-attendance-report/{{ $subject_id }}" external no-wire-navigate />
            <x-button label="Calificaciones" icon="o-academic-cap" class="btn-sm btn-info"
                link="/print/student-grades-report/{{ $subject_id }}" external no-wire-navigate />
        </div>
    </x-drawer>
</div>