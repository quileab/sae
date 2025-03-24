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

    public $temp = [];

    private $isAdmin = false;
    private $admin_id = null;

    public function mount()
    {
        // if user is NOT admin, principal, administrative return back
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }
        $this->user = auth()->user();
        $this->inscriptions = \App\Models\Configs::where('group', 'inscriptions')->get();
        $this->inscription_id = $this->inscriptions->first()->id ?? null;
        $this->careers = \App\Models\Career::where('allow_enrollments', true)
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
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        // search inscriptions by configs_id order by user_id
        $inscriptions = \App\Models\Inscriptions::with('user', 'subject')
            ->where('user_id', '>', 1000)
            ->where('configs_id', $this->inscription_id)
            ->where('subject_id', $this->subject_id)
            ->when($this->search, function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->where('user_id', 'like', '%' . $search . '%')
                        ->orWhere('subject_id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        //dd($inscriptions->take(10)->get());
        return $inscriptions->get();
    }

    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

    public function updatedCareerId($value): void
    {
        $this->subjects = \App\Models\Subject::where('career_id', $value)->get();
        $this->subject_id = $this->subjects->first()->id;
        $this->items();
        $this->skipMount();
    }


}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Inscripciones Realizadas" progress-indicator>
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
            <x-select wire:model.lazy="subject_id" label="Materia" :options="$subjects" />
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>

            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">

        <div class="grid grid-cols-2 gap-2">
            acciones a realizar TODO
        </div>

    </x-drawer>

</div>