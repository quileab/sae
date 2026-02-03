<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
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
        $this->user = User::find(session('user_id')) ?: auth()->user();
        $this->cycle = session('cycle_id');
        $this->subjects = $this->user->subjects;

        try {
            $this->subject_id = session('subject_id') ?: ($this->subjects->isNotEmpty() ? $this->subjects->first()->id : null);
        } catch (\Exception $e) {
            $this->subject_id = null;
        }
    }

    public function updatedSubjectId($value): void
    {
        $this->info('Materia Seleccionada.'.$value, position: 'toast-top toast-center');
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $value]);
    }

    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
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

    public function items(): Collection
    {
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $this->subject_id]);
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
        return view('livewire.class_sessions.index', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
