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

    public $temp;
    public $user;


    public function mount()
    {
        // if user is NOT admin, principal, administrative return back
        // $user->hasAnyRole(['admin', 'principal', 'administrative'])
        $this->user = auth()->user();
        if (!$this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }
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
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'fullname', 'label' => 'Apellido y Nombre'],
            ['key' => 'career', 'label' => 'Carrera', 'sortable' => false],
            ['key' => 'inscription', 'label' => 'Inscripto a', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $this->info('Cargando...', timeout: 2000);
        $inscripts = [];

        $pathToStorage = storage_path('app'); // == Storage::path('');
        $pathToFiles = '/private/private/inscriptions';
        $filter = "insc-*";
        // Check if Logged User has "elevated" Roles
        if (auth()->user()->hasAnyRole(['admin', 'principal', 'administrative'])) {
            // file filter insc-{user_id}-{career_id}-{inscription_id}.pdf
            $filter = "insc-*-$this->career_id-$this->inscription_id-.pdf";
        } // Check for "self" registrations
        else {
            $filter = "insc-" . auth()->user()->id . "-*";
        }
        $this->temp = $filter;
        $files = [];
        foreach (glob("$pathToStorage/$pathToFiles/$filter") as $nombre_fichero) {
            $files[] = "$pathToFiles/" . basename($nombre_fichero);
            //echo "$pathToFiles/".basename($nombre_fichero)."<br/>";
        }
        foreach ($files as $key => $file) {
            //$files[$key] = str_replace('private/private/inscriptions/', 'files/private/', $file);
            $files[$key] = basename($file);
            $user_id = explode('-', $files[$key])[1];
            $career_id = explode('-', $files[$key])[2];
            $config_incription_id = explode('-', $files[$key])[3];
            $user = \App\Models\User::find($user_id);
            $user != null ? $username = $user->fullname : $username = 'No encontrado';
            // Get career name
            $career = $this->careers->where('id', $career_id)->first();
            $career != null ? $career = $career->name : $career = '🚫Carrera/Curso';
            $inscripts[$key]['filename'] = $file;
            $inscripts[$key]['fullname'] = $username;
            $inscripts[$key]['career'] = $career;
            $inscripts[$key]['inscription'] = $config_incription_id; //\App\Models\Config::find($config_incription_id);
            $inscripts[$key]['pdflink'] = $files[$key];
            $inscripts[$key]['checked'] = false;
            //dd($inscripts);
        }
        // convertir en collection y ordenar por carrera y usuario
        $inscriptions = collect($inscripts)->sortBy(['career', 'user']);
        // return to array
        //$inscripts = $inscripts->toArray();

        return $inscriptions;
    }

    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Inscripciones PDF Realizadas" progress-indicator>
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
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 sticky top-0 z-20 backdrop-blur-md
        pb-1 border-b border-black/20 dark:border-white/20">
            <x-select wire:model.lazy="inscription_id" label="Inscripciones a" :options="$inscriptions"
                option-value="id" option-label="description" />
            <x-select wire:model.lazy="career_id" label="Carrera" :options="$careers" />
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
                @scope('actions', $item)
                <x-button label="PDF" link="pdf/{{ $item['pdflink'] }}" external icon="s-document"
                    class="btn-error text-red-700 btn-ghost w-32" />
                @endscope
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