<?php

use App\Models\Career;
use App\Models\Configs;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
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
    public array $selected = [];

    public $temp;
    public $user;


    public function mount()
    {
        $this->user = auth()->user();
        if (!$this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }

        $this->inscriptions = Configs::where('group', 'inscriptions')->get();
        $this->inscription_id = $this->inscriptions->first()->id ?? null;

        $this->careers = Career::where('allow_enrollments', true)
            ->where('allow_evaluations', true)
            ->get();

        if ($this->careers->isEmpty()) {
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
        $this->info('Cargando...', timeout: 500);
        $this->selected = [];

        $pathToStorage = storage_path('app');
        $pathToFiles = '/private/private/inscriptions';
        $filter = "insc-*";

        if ($this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            $filter = "insc-*-$this->career_id-$this->inscription_id-.pdf";
        } else {
            $filter = "insc-{$this->user->id}-*";
        }

        $this->temp = $filter;
        $files = glob("$pathToStorage/$pathToFiles/$filter");

        if (empty($files)) {
            return collect();
        }

        $userIds = [];
        $careerIds = [];
        foreach ($files as $file) {
            $parts = explode('-', basename($file));
            if (count($parts) > 2) {
                $userIds[] = $parts[1];
                $careerIds[] = $parts[2];
            }
        }

        $users = User::whereIn('id', array_unique($userIds))->pluck('fullname', 'id');
        $careers = Career::whereIn('id', array_unique($careerIds))->pluck('name', 'id');

        $inscripts = [];
        $id = 0;
        foreach ($files as $file) {
            $key = ++$id;
            $filename = basename($file);
            $parts = explode('-', $filename);

            if (count($parts) < 4) continue;

            $userId = $parts[1];
            $careerId = $parts[2];
            $configInscriptionId = $parts[3];

            $username = $users->get($userId, '🚫 ' . $filename);
            $careerName = $careers->get($careerId, '🚫 Carrera/Curso');

            $inscripts[$key] = [
                'id' => $key,
                'filename' => $file,
                'fullname' => $username,
                'career' => $careerName,
                'inscription' => $configInscriptionId,
                'pdflink' => $filename,
            ];
        }

        return collect($inscripts)->sortBy(['career', 'fullname']);
    }


    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

    public function deleteSelected()
    {
        $deletedCount = 0;
        $pathToStorage = storage_path('app');
        $pathToFiles = '/private/private/inscriptions/';

        $allItems = $this->items();

        foreach ($this->selected as $selectedId) {
            $itemToDelete = $allItems->firstWhere('id', $selectedId);

            if ($itemToDelete && !empty($itemToDelete['pdflink'])) {
                $filePath = $pathToStorage . $pathToFiles . $itemToDelete['pdflink'];

                if (File::exists($filePath)) {
                    File::delete($filePath);
                    $deletedCount++;
                }
            }
        }

        $this->info($deletedCount . ' archivo(s) eliminado(s).');
        $this->selected = [];
        $this->drawer = false;
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
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped wire:model="selected" selectable
                @row-selection="console.log($event.detail)">
                @scope('actions', $item)
                <x-button label="PDF" link="pdf/{{ $item['pdflink'] }}" external icon="s-document"
                    class="btn-error text-red-700 btn-ghost w-32" />
                @endscope
            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        <x-dropdown label="Eliminar" class="btn-error" right>
            <x-menu-item title="Confirmar" wire:click="deleteSelected" spinner="deleteSelected" icon="o-trash" />
        </x-dropdown>
    </x-drawer>

</div>