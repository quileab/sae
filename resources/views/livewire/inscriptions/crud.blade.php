<?php

use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
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
            'name' => 'Acepta solo un valor'
        ],
        [
            'id' => 'csv-n',
            'name' => 'Acepta varios valores'
        ]
    ];

    public $temp = [];

    private $isAdmin = false;
    private $admin_id = null;

    public function mount()
    {
        $this->user = \App\Models\User::find(session('user_id')) ?: auth()->user();
        $this->isAdmin = $this->user->hasAnyRole(['admin', 'principal', 'administrative']);
        if ($this->isAdmin) {
            $this->inscriptions = \App\Models\Configs::where('group', 'inscriptions')->get();
            $this->careers = \App\Models\Career::where('allow_enrollments', true)
                ->where('allow_evaluations', true)->get();
        } else {
            $this->inscriptions = \App\Models\Configs::where('group', 'inscriptions')
                ->where('value', 'true')
                ->get();
            $this->careers = $this->user->careers ?? [];
        }
        $this->inscription_id = $this->inscriptions->first()->id ?? null;

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
        $this->admin_id = \App\Models\User::where('name', 'admin')->first()->id;
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
        if (count($this->inscriptions) === 0 ) {
            return collect([]);
        }
        $this->info('Cargando...', timeout: 2000);
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        $subjects = \App\Models\Subject::where('name', '!=', '')
            ->where('career_id', $this->career_id)
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%');
                });
            })->get();

        // get all inscriptions from each subject using where in 
        $inscriptions = \App\Models\Inscriptions::where('configs_id', $this->inscription_id)
            ->where('user_id', $this->admin_id)
            ->whereIn('subject_id', $subjects->pluck('id')
                ->toArray())->get()->keyBy('subject_id')
            ->toArray();

        if ($this->user->hasRole('student')) {
            $selected = \App\Models\Inscriptions::where('configs_id', $this->inscription_id)
                ->where('user_id', $this->user->id)
                ->whereIn('subject_id', $subjects->pluck('id')
                    ->toArray())->get()->keyBy('subject_id')
                ->toArray();
        }

        foreach ($subjects as $subject) {
            $subject->value = $inscriptions[$subject->id]['value'] ?? null;
            $subject->selected = $selected[$subject->id]['value'] ?? null;
        }

        $this->subjects = $subjects->keyBy('id')->toArray();
        //dump($this->admin_id, $this->subjects, $subjects, $inscriptions);
        return $subjects;
    }

    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

    public function save()
    {
        foreach ($this->subjects as $subject_id => $value) {
            // dd($subject_id, $value);
            $inscription = \App\Models\Inscriptions::firstOrNew([
                'user_id' => $this->user->id,
                'subject_id' => $subject_id,
                'configs_id' => $this->inscription_id
            ]);
            // check if user is student
            if ($this->user->hasRole('student')) {
                $save_value = $value['selected'];
            } else {
                $save_value = $value['value'];
            }

            if ($save_value == '') {
                $inscription->delete();
            } else {
                $inscription->fill([
                    'type' => $this->type,
                    'value' => $save_value ?? null
                ])->save();
            }
        }
        $this->success('Inscripciones actualizadas');
        // prevent reload / render
        $this->skipRender();
    }

}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Inscripciones {{ $user->name }}" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>
    {{-- if return with message --}}
    @if (session()->has('success'))
        <x-alert icon="o-information-circle" title="{{ session('success') }}" class="alert-success" dismissible />
    @endif

    <!-- TABLE  -->
    <x-card>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 sticky top-0 z-20 backdrop-blur-md
        pb-1 border-b border-black/20 dark:border-white/20">
            <x-select wire:model.lazy="inscription_id" label="Inscripciones a" :options="$inscriptions"
                option-value="id" option-label="description" />
            <x-select wire:model.lazy="career_id" label="Carrera" :options="$careers" />
            <div class="grid grid-cols-2 gap-2">
                @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                    <x-select label="Tipo" wire:model.lazy="type" :options="$types" />
                @else
                    <x-button label="Confirmar" @click="$wire.drawer = true" responsive icon="o-bars-3"
                        class="btn-secondary mt-7" />
                @endif
                <x-button label="Guardar" icon="o-check" class="btn-primary mt-7" wire:click="save" />
            </div>
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
                @scope('cell_value', $item, $user, $subjects, $type)
                @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                    <x-input icon="o-cube" :key="$item->id" wire:model="subjects.{{ $item->id }}.value" />
                @else
                                @php
                                    $values = array_map(function ($item) {
                                        return ['id' => $item, 'name' => $item];
                                    }, explode(',', $subjects[$item->id]['value']));
                                @endphp

                                {{-- if type csv-1 add single to x-choices --}}
                                @if($type == 'csv-1')
                                    <x-choices wire:model="subjects.{{ $item->id }}.selected" :options="$values" :key="uniqid()"
                                        class="w-full" single />
                                @else
                                    <x-choices wire:model="subjects.{{ $item->id }}.selected" :options="$values" :key="uniqid()"
                                        class="w-full" />
                                @endif

                @endif
                @endscope
            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">

        <div class="grid grid-cols-2 gap-2">
            <x-button label="Previsualizar" icon="o-eye" class="btn-warning"
                link="/inscriptionsPDF/{{ $user->id }}/{{ $career_id }}/{{ $inscription_id }}" external />
            <x-button label="Enviar" icon="o-paper-airplane" class="btn-success"
                link="/inscriptionsSavePDF/{{ $user->id }}/{{ $career_id }}/{{ $inscription_id }}" />
        </div>

    </x-drawer>

</div>