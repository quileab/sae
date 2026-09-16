<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class ClassSessionList extends Component
{
    use AuthorizesAccess, Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];

    #[Url]
    public $subject_id = null;

    #[Url]
    public $cycle_id = null;

    public function mount(): void
    {
        $user = auth()->user();

        // Cycle ID initialization (URL > Session > Current Year)
        if (! $this->cycle_id) {
            $this->cycle_id = session('cycle_id') ?? $this->getCycleId();
        }

        $subjects = $this->subjects;

        // Subject Context Persistence (URL > Session > First Available)
        if (! $this->subject_id) {
            $this->subject_id = session('current_subject_id');
        }

        if (! $this->subject_id && $subjects->isNotEmpty()) {
            $this->subject_id = $subjects->first()->id;
        }

        // Security check: If a subject_id is provided, verify ownership
        if ($this->subject_id && ! $user->hasSubject($this->subject_id)) {
            $this->subject_id = $subjects->isNotEmpty() ? $subjects->first()->id : null;
        }

        // Persist the choice if valid
        if ($this->subject_id) {
            session()->put('current_subject_id', $this->subject_id);
        }
    }

    #[Computed]
    public function subjects()
    {
        return auth()->user()->subjects;
    }

    #[Computed]
    public function cycle()
    {
        return $this->cycle_id ?: $this->getCycleId();
    }

    public function updatedSubjectId($value): void
    {
        session()->put('current_subject_id', $value);
        $this->info('Materia Seleccionada: '.$this->subjects->find($value)->name, position: 'toast-top toast-center');
    }

    public function clear(): void
    {
        $this->reset(['search', 'drawer']);
        $this->success('Filtros limpiados.', position: 'toast-bottom');
    }

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

    #[Computed]
    public function items(): Collection
    {
        if (! $this->subject_id) {
            return collect();
        }

        $search = Str::of($this->search)->lower()->ascii();
        $query = ClassSession::whereYear('date', $this->cycle)
            ->where('subject_id', $this->subject_id);

        if ($this->search) {
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', '%'.$search.'%')
                    ->orWhere('activities', 'like', '%'.$search.'%');
            });
        }

        return $query->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();
    }

    public function render()
    {
        return view('livewire.class_sessions.class-session-list', [
            'headers' => $this->headers(),
        ]);
    }
}
