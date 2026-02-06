<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Configs;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Indexpdfs extends Component
{
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
        if (! $this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }

        $this->inscriptions = Configs::where('group', 'inscriptions')->get();
        if ($this->inscriptions->isNotEmpty()) {
            $this->inscription_id = $this->inscriptions->first()->id;
        }

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

    public function updatedInscriptionId()
    {
        $this->selected = [];
    }

    public function updatedCareerId()
    {
        $this->selected = [];
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

        $pathToStorage = storage_path('app');
        $pathToFiles = '/private/private/inscriptions';

        if ($this->user->hasAnyRole(['admin', 'principal', 'administrative'])) {
            $filter = "insc-*-{$this->career_id}-{$this->inscription_id}-*.pdf";
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

        $users = User::whereIn('id', array_unique($userIds))->get()->pluck('fullname', 'id');
        $careers = Career::whereIn('id', array_unique($careerIds))->pluck('name', 'id');

        $inscripts = [];
        $id = 0;
        foreach ($files as $file) {
            $key = ++$id;
            $filename = basename($file);
            $parts = explode('-', $filename);

            if (count($parts) < 4) {
                continue;
            }

            $userId = $parts[1];
            $careerId = $parts[2];
            $configInscriptionId = $parts[3];

            $username = $users->get($userId, '🚫 '.$filename);
            $careerName = $careers->get($careerId, '🚫 Carrera/Curso');
            $inscriptionName = $this->inscriptions->firstWhere('id', $configInscriptionId)->description ?? $configInscriptionId;

            $inscripts[$key] = [
                'id' => $key,
                'filename' => $file,
                'fullname' => $username,
                'career' => $careerName,
                'inscription' => $inscriptionName,
                'pdflink' => $filename,
            ];
        }

        $collection = collect($inscripts);

        if ($this->search) {
            $collection = $collection->filter(function ($item) {
                return Str::contains(Str::lower($item['fullname']), Str::lower($this->search)) ||
                       Str::contains(Str::lower($item['career']), Str::lower($this->search));
            });
        }

        return $collection->sortBy(['career', 'fullname']);
    }

    public function deleteSelected()
    {
        $deletedCount = 0;
        $pathToStorage = storage_path('app');
        $pathToFiles = '/private/private/inscriptions/';

        $allItems = $this->items();

        foreach ($this->selected as $selectedId) {
            $itemToDelete = $allItems->firstWhere('id', $selectedId);

            if ($itemToDelete && ! empty($itemToDelete['pdflink'])) {
                $filePath = $pathToStorage.$pathToFiles.$itemToDelete['pdflink'];

                if (File::exists($filePath)) {
                    File::delete($filePath);
                    $deletedCount++;
                }
            }
        }

        $this->info($deletedCount.' archivo(s) eliminado(s).');
        $this->selected = [];
        $this->drawer = false;
    }

    public function render()
    {
        return view('livewire.inscriptions.indexpdfs', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
