<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Configs;
use App\Models\Inscriptions;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $search = '';

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public bool $drawer = false;

    public $inscriptions = [];

    public $inscription_id = null;

    public $careers = [];

    public $career_id = null;

    public $subjects = [];

    public $subject_id = null;

    public $temp = [];

    private $isAdmin = false;

    private $admin_id = null;

    public $user;

    public function mount()
    {
        // if user is NOT admin, principal, administrative return back
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }
        $this->user = auth()->user();
        $this->inscriptions = Configs::where('group', 'inscriptions')->get();
        if ($this->inscriptions->isNotEmpty()) {
            $this->inscription_id = $this->inscriptions->first()->id;
        }
        $this->careers = Career::where('allow_enrollments', true)
            ->where('allow_evaluations', true)->get();

        if ($this->careers->isEmpty()) {
            // return error message
            $this->warning('No se han encontrado Carreras.');
            $this->career_id = null;
        } else {
            $this->career_id = $this->careers->first()->id;
        }
        $this->updatedCareerId($this->career_id);
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'user.fullname', 'label' => 'Apellido y Nombre'],
            ['key' => 'subject.name', 'label' => 'Materia'],
            ['key' => 'configs_id', 'label' => 'Inscripto a', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();
        // search inscriptions by configs_id order by user_id
        $inscriptions = Inscriptions::with('user', 'subject')
            ->where('user_id', '>', 1000)
            ->where('configs_id', $this->inscription_id)
            ->where('subject_id', $this->subject_id)
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->where('user_id', 'like', '%'.$search.'%')
                        ->orWhere('subject_id', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction']);

        return $inscriptions->get();
    }

    public function updatedCareerId($value): void
    {
        $this->subjects = Subject::where('career_id', $value)->get();
        if ($this->subjects->isNotEmpty()) {
            $this->subject_id = $this->subjects->first()->id;
        } else {
            $this->subject_id = null;
        }
        // $this->items(); // No need to call this here as it's called in render/with
    }

    public function render()
    {
        return view('livewire.inscriptions.index', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
