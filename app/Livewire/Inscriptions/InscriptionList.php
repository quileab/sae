<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Config as ConfigModel;
use App\Models\Inscription;
use App\Models\Subject;
use App\Models\User;
use App\Services\InscriptionPdfService;
use App\Services\SubjectOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class InscriptionList extends Component
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

    public array $selectedRows = [];

    public function mount()
    {
        $this->user = auth()->user();
        // if user is NOT admin, principal, administrative or teacher return back
        if (! $this->user->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'teacher'])) {
            return redirect()->back();
        }

        $this->inscriptions = ConfigModel::where('group', 'inscriptions')->get();
        if ($this->inscriptions->isNotEmpty()) {
            $this->inscription_id = $this->inscriptions->first()->id;
        }

        if ($this->user->hasRole('teacher')) {
            // Intentar recuperar de la sesión si no hay selección
            if (! $this->subject_id) {
                $this->subject_id = session('current_subject_id');
            }

            $this->subjects = app(SubjectOptions::class)
                ->searchWith($this->teacherSubjectsQuery(), '', [$this->subject_id]);

            if ($this->subjects !== []) {
                $subjectIds = array_column($this->subjects, 'id');

                // Si la materia de la sesión no es válida para este profesor, elegir la primera
                if (! $this->subject_id || ! in_array((int) $this->subject_id, $subjectIds, true)) {
                    $this->subject_id = $this->subjects[0]['id'];
                }

                $this->career_id = Subject::find($this->subject_id)?->career_id;
                session()->put('current_subject_id', $this->subject_id);
            }
        } else {
            $this->careers = Career::where('allow_enrollments', true)
                ->where('allow_evaluations', true)->get();

            if ($this->careers->isEmpty()) {
                // return error message
                $this->warning('No se han encontrado Carreras.');
                $this->career_id = null;
            } else {
                $this->career_id = $this->careers->first()->id;
                $this->updatedCareerId($this->career_id);
            }
        }
    }

    public function updatedSubjectId($value)
    {
        session()->put('current_subject_id', $value);
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'user.fullname', 'label' => 'Apellido y Nombre'],
            ['key' => 'subject.name', 'label' => 'Materia'],
            ['key' => 'value', 'label' => 'Valor'],
            ['key' => 'configs_id', 'label' => 'Inscripto a', 'sortable' => false],
            ['key' => 'pdf', 'label' => 'PDF', 'sortable' => false],
        ];
    }

    #[Computed]
    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();
        // search inscriptions by configs_id order by user_id
        $query = Inscription::with('user', 'subject')
            ->where('user_id', '!=', Inscription::CATALOG_USER_ID)
            ->where('configs_id', $this->inscription_id);

        if ($this->user->hasRole('teacher')) {
            $query->whereHas('subject', function ($query) {
                $query->whereHas('classSessions', function ($query) {
                    $query->where('teacher_id', $this->user->id);
                });
            });
        }

        $query->when($this->subject_id, function ($query) {
            return $query->where('subject_id', $this->subject_id);
        }, function ($query) {
            if (! $this->user->hasRole('teacher')) {
                return $query->whereHas('subject', function ($query) {
                    $query->where('career_id', $this->career_id);
                });
            }
        })
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($query) use ($search) {
                        $query->where('firstname', 'like', '%'.$search.'%')
                            ->orWhere('lastname', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                });
            });

        // Handle sorting for relationship columns
        if ($this->sortBy['column'] === 'user.fullname') {
            $query->join('users', 'inscriptions.user_id', '=', 'users.id')
                ->orderBy('users.lastname', $this->sortBy['direction'])
                ->orderBy('users.firstname', $this->sortBy['direction'])
                ->select('inscriptions.*');
        } elseif ($this->sortBy['column'] === 'subject.name') {
            $query->join('subjects', 'inscriptions.subject_id', '=', 'subjects.id')
                ->orderBy('subjects.name', $this->sortBy['direction'])
                ->select('inscriptions.*');
        } else {
            $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        }

        return $query->get()->each(function (Inscription $inscription): void {
            $inscription->pdf_file = InscriptionPdfService::fileName(
                $inscription->user_id,
                (int) $this->career_id,
                (string) $this->inscription_id,
            );
            $inscription->pdf_exists = InscriptionPdfService::exists(
                $inscription->user_id,
                (int) $this->career_id,
                (string) $this->inscription_id,
            );
        });
    }

    public function updatedCareerId($value): void
    {
        $this->subject_id = null;
        $this->searchSubjects();
    }

    public function searchSubjects(string $value = ''): void
    {
        $service = app(SubjectOptions::class);

        $query = $this->user->hasRole('teacher')
            ? $this->teacherSubjectsQuery()
            : $service->queryFor($this->user);

        if ($this->career_id && ! $this->user->hasRole('teacher')) {
            $query->where('career_id', $this->career_id);
        }

        $this->subjects = $service->searchWith($query, $value, [$this->subject_id]);
    }

    protected function teacherSubjectsQuery(): Builder
    {
        return Subject::query()
            ->whereHas('classSessions', fn (Builder $query) => $query->where('teacher_id', $this->user->id));
    }

    public function deleteSelected(): void
    {
        if (! $this->user->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            $this->warning('No tienes permisos para realizar esta acción.');

            return;
        }

        if (empty($this->selectedRows)) {
            $this->warning('No hay elementos seleccionados.');

            return;
        }

        Inscription::whereIn('id', $this->selectedRows)->delete();
        $this->selectedRows = [];
        $this->drawer = false;
        $this->success('Inscripciones eliminadas correctamente.');
    }

    public function render()
    {
        return view('livewire.inscriptions.inscription-list', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
