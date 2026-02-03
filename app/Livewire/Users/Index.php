<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $search = '';

    public string $filterRole = '';

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $row_decoration;

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
            ['key' => 'fullname', 'label' => 'Apellido y Nombre', 'class' => 'w-full'],
            ['key' => 'phone', 'label' => 'Tel.', 'sortable' => false],
            ['key' => 'email', 'label' => 'E-mail', 'sortable' => false],
            ['key' => 'role', 'label' => 'Rol', 'sortable' => false],
        ];
    }

    public function users(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        return User::get()
            ->sortBy($this->sortBy)
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    $fullSearch = Str::of($item['lastname'].', '.$item['firstname'].$item['id'])->lower()->ascii();

                    return $fullSearch->contains($search);
                });
            })->take(20);
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
    }

    public function render()
    {
        $row_decoration = [
            'text-red-500' => fn (User $user) => $user->enabled === false,
        ];

        return view('livewire.users.index', [
            'users' => $this->users(),
            'headers' => $this->headers(),
            'row_decoration' => $row_decoration,
        ]);
    }
}
