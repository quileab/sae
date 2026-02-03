<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
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

    public $career_id;

    public $careers;

    public function mount(): void
    {
        $this->careers = \App\Models\Career::all();
        // if session career_id is set, use it
        if (session()->has('career_id')) {
            $this->career_id = session('career_id');
        } elseif ($this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }
    }

    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function delete($id): void
    {
        $this->warning("Will delete #$id", 'It is fake.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => 'Nombre', 'class' => 'w-full'],
        ];
    }

    public function subjects(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        return Subject::get()
            ->where('career_id', $this->career_id)
            ->sortBy($this->sortBy)
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    $fullSearch = Str::of($item['name'].' '.$item['id'])->lower()->ascii();

                    return $fullSearch->contains($search);
                });
            });
    }

    public function updated($career_id, $value)
    {
        $this->subjects();
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'subject_id', 'value' => $id]);
    }

    public function render()
    {
        return view('livewire.subjects.index', [
            'subjects' => $this->subjects(),
            'headers' => $this->headers(),
        ]);
    }
}
