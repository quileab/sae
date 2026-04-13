<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Configs;
use App\Models\Inscriptions;
use App\Models\Subject;
use App\Models\User;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use AuthorizesAccess, Toast;

    public string $search = '';

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public $inscription_id = null;

    #[Url]
    public $career_id = null;

    #[Url]
    public $user_id = null;

    public $subject_id = null;

    public $type = 'csv-1';

    public $types = [
        [
            'id' => 'csv-1',
            'name' => 'Acepta solo un valor',
        ],
        [
            'id' => 'csv-n',
            'name' => 'Acepta varios valores',
        ],
    ];

    public $temp = [];

    public $subjects = [];

    public $pdfExists = false;

    public $pdfFileName = '';

    private $admin_id = null;

    public function mount()
    {
        $user = $this->targetUser;

        if ($user->enabled == false) {
            return;
        }

        // Default career if not in URL
        if (! $this->career_id && $this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }

        if ($this->inscriptions->isEmpty()) {
            $this->warning('No se han encontrado Inscripciones abiertas.');
            $this->inscription_id = null;
        } else {
            $this->inscription_id = $this->inscriptions->first()->id;
        }

        $this->checkPdf();
    }

    #[Computed]
    public function targetUser()
    {
        return $this->getTargetUser($this->user_id);
    }

    #[Computed]
    public function inscriptions()
    {
        if ($this->targetUser->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            return Configs::where('group', 'inscriptions')->get();
        }

        return Configs::where('group', 'inscriptions')
            ->where('value', 'true')
            ->get();
    }

    #[Computed]
    public function careers()
    {
        if ($this->targetUser->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            return Career::where('allow_enrollments', true)
                ->where('allow_evaluations', true)->get();
        }

        return $this->targetUser->careers ?? collect();
    }

    public function checkPdf(): void
    {
        if ($this->targetUser && $this->career_id && $this->inscription_id) {
            $this->pdfFileName = "insc-{$this->targetUser->id}-{$this->career_id}-{$this->inscription_id}-.pdf";
            $this->pdfExists = Storage::exists("private/inscriptions/{$this->pdfFileName}");
        } else {
            $this->pdfExists = false;
        }
    }

    public function updatedCareerId(): void
    {
        $this->subjects = [];
        $this->checkPdf();
    }

    public function updatedInscriptionId(): void
    {
        $this->subjects = [];
        $this->checkPdf();
    }

    public function boot()
    {
        $admin = User::where('name', 'admin')->first();
        if ($admin) {
            $this->admin_id = $admin->id;
        }
    }

    // Table headers
    public function headers(): array
    {
        $labelSubject = Configs::where('id', 'label_subject')->value('value') ?? 'Materia';

        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => $labelSubject],
            ['key' => 'value', 'label' => 'Valor', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        if (count($this->inscriptions) === 0) {
            return collect([]);
        }
        $this->info('Cargando...', timeout: 2000);
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        $subjects = Subject::where('name', '!=', '')
            ->where('career_id', $this->career_id)
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('id', 'like', '%'.$search.'%');
                });
            })->get();

        // get all inscriptions from each subject using where in
        $inscriptions = Inscriptions::where('configs_id', $this->inscription_id)
            ->where('user_id', $this->admin_id)
            ->whereIn('subject_id', $subjects->pluck('id')
                ->toArray())->get()->keyBy('subject_id')
            ->toArray();

        $selected = [];
        if ($this->targetUser->hasRole('student')) {
            $selected = Inscriptions::where('configs_id', $this->inscription_id)
                ->where('user_id', $this->targetUser->id)
                ->whereIn('subject_id', $subjects->pluck('id')
                    ->toArray())->get()->keyBy('subject_id')
                ->toArray();
        }

        foreach ($subjects as $subject) {
            $subject->value = $inscriptions[$subject->id]['value'] ?? null;

            // Preserve current selection if it exists in local state, otherwise load from DB
            if (isset($this->subjects[$subject->id]) && array_key_exists('selected', $this->subjects[$subject->id])) {
                $subject->selected = $this->subjects[$subject->id]['selected'];
            } else {
                $subject->selected = $selected[$subject->id]['value'] ?? null;
            }

            // Sync with public $subjects property for wire:model
            if (!isset($this->subjects[$subject->id])) {
                $this->subjects[$subject->id] = [
                    'value' => $subject->value,
                    'selected' => $subject->selected,
                ];
            } else {
                // If it's already set (e.g. from previous request), only update the static 'value' from DB
                $this->subjects[$subject->id]['value'] = $subject->value;
            }
        }

        $this->skipMount();

        return $subjects;
    }

    public function save()
    {
        if (! $this->career_id || ! $this->inscription_id) {
            $this->warning('Debe seleccionar una Carrera e Inscripción.');

            return;
        }

        $isAdmin = auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative']);
        // which user Admin or Student?
        $isAdmin ? $user = $this->admin_id : $user = $this->targetUser->id;

        foreach ($this->subjects as $subject_id => $value) {
            // dd($subject_id, $value);
            $inscription = Inscriptions::firstOrNew([
                'user_id' => $user,
                'subject_id' => $subject_id,
                'configs_id' => $this->inscription_id,
            ]);
            // check if not admin
            ! $isAdmin ? $save_value = $value['selected'] : $save_value = $value['value'];

            if ($save_value == '') {
                $inscription->delete();
            } else {
                $inscription->fill([
                    'type' => $this->type,
                    'value' => $save_value ?? null,
                ])->save();
            }
        }
        if (auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            $this->success('Inscripciones actualizadas');
        }
        // prevent reload / render
        $this->skipRender();
    }

    public function saveAndConfirm()
    {
        if (! $this->career_id || ! $this->inscription_id) {
            $this->warning('Debe seleccionar una Carrera e Inscripción para confirmar.');

            return;
        }

        $this->save();

        return redirect()->route('inscriptionsSavePDF', [
            'student' => $this->targetUser->id,
            'career' => $this->career_id,
            'inscription' => $this->inscription_id,
        ]);
    }

    public function clearSelection($subject_id)
    {
        if (isset($this->subjects[$subject_id])) {
            $this->subjects[$subject_id]['selected'] = null;
        }
    }

    public function render()
    {
        return view('livewire.inscriptions.crud', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
