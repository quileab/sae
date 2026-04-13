<?php

namespace App\Livewire\Subjects;

use App\Models\Career;
use App\Models\Subject;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use AuthorizesAccess, Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    #[Url]
    public $career_id = null;

    public function mount(): void
    {
        $this->authorizeStaff();

        if (! $this->career_id && $this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }
    }

    #[Computed]
    public function careers()
    {
        return Career::all();
    }

    #[Computed]
    public function subjects(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        return Subject::where('career_id', $this->career_id)
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('id', 'like', '%'.$search.'%');
                });
            })
            ->get()
            ->sortBy($this->sortBy['column'], SORT_REGULAR, $this->sortBy['direction'] === 'desc');
    }

    public function clear(): void
    {
        $this->reset(['search', 'drawer']);
        $this->success('Filtros limpiados.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Nombre', 'class' => 'w-full'],
        ];
    }

    public function render()
    {
        return view('livewire.subjects.index', [
            'headers' => $this->headers(),
            'subjects' => $this->subjects,
        ]);
    }
}
