<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Configs;
use App\Models\Inscriptions;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
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

    public $user = null;

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

    private $admin_id = null;

    public function mount()
    {
        $this->user = User::find(session('user_id')) ?: auth()->user();
        if ($this->user->enabled == false) {
            return;
        }
        // si es admin, principal o administrative
        if ($this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            $this->inscriptions = Configs::where('group', 'inscriptions')->get();
            $this->careers = Career::where('allow_enrollments', true)
                ->where('allow_evaluations', true)->get();

            $admin = User::where('name', 'admin')->first();
            if ($admin) {
                $this->admin_id = $admin->id;
            }
        } else {
            $this->inscriptions = Configs::where('group', 'inscriptions')
                ->where('value', 'true')
                ->get();
            $this->careers = $this->user->careers ?? [];
        }

        if ($this->inscriptions->isNotEmpty()) {
            $this->inscription_id = $this->inscriptions->first()->id;
        }

        if ($this->careers->isEmpty()) {
            // return error message
            $this->warning('No se han encontrado Carreras.');
            $this->career_id = null;
        } else {
            $this->career_id = $this->careers->first()->id;
        }
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
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Materia'],
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
        if ($this->user->hasRole('student')) {
            $selected = Inscriptions::where('configs_id', $this->inscription_id)
                ->where('user_id', $this->user->id)
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
        }

        $this->subjects = $subjects->keyBy('id')->toArray();
        // dump($this->admin_id, $this->subjects, $subjects, $inscriptions);
        $this->skipMount();

        return $subjects;
    }

    public function save()
    {
        $isAdmin = auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']);
        // which user Admin or Student?
        $isAdmin ? $user = $this->admin_id : $user = $this->user->id;

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
        $this->success('Inscripciones actualizadas');
        // prevent reload / render
        $this->skipRender();
    }

    public function saveAndConfirm()
    {
        $this->save();

        return redirect()->route('inscriptionsSavePDF', [
            'student' => $this->user->id,
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

    public function preview()
    {
        $this->save();
        $this->drawer = false;

        $url = route('inscriptionsPDF', [
            'student' => $this->user->id,
            'career' => $this->career_id,
            'inscription' => $this->inscription_id,
        ]);

        $this->js("window.open('$url', '_blank')");
    }

    public function render()
    {
        return view('livewire.inscriptions.crud', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
