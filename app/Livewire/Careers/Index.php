<?php

namespace App\Livewire\Careers;

use App\Models\Career;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Nombre', 'class' => 'w-full'],
            ['key' => 'resolution', 'label' => 'Resol.', 'class' => 'w-32'],
            ['key' => 'allow_enrollments', 'label' => 'Matric.', 'sortable' => false],
            ['key' => 'allow_evaluations', 'label' => 'Eval.', 'sortable' => false],
        ];
    }

    public function careers(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        return Career::get()
            ->sortBy($this->sortBy)
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    $fullSearch = Str::of($item['name'].' '.$item['id'])->lower()->ascii();

                    return str_contains($fullSearch, $search);
                });
            })->take(20);
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'career_id', 'value' => $id]);
    }

    public function render()
    {
        return view('livewire.careers.index', [
            'careers' => $this->careers(),
            'headers' => $this->headers(),
        ]);
    }
}
