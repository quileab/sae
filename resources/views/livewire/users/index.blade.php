<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';
    public string $filterRole = '';
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $row_decoration;

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    // Delete action
    public function delete($id): void
    {
        $this->warning("Will delete #$id", 'It is fake.', position: 'toast-bottom');
    }

    // Table headers
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
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos

        return User::get()
            ->sortBy($this->sortBy) // Ordenar por el campo especificado
            ->when($this->search, function (Collection $collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    // Normalizar los caracteres latinos y convertir a minúsculas
                    $fullSearch = Str::of($item['lastname'] . ', ' . $item['firstname'] . $item['id'])->lower()->ascii();
                    // Comparar la búsqueda normalizada con el texto del item
                    return $fullSearch->contains($search);
                });
            })->take(20);
    }

    public function with(): array
    {
        return [
            'users' => $this->users(),
            'headers' => $this->headers()
        ];
    }

    public function bookmark($id): void
    {
        // Notifica al componente Bookmarks
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
    }

}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Usuarios">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="NUEVO" link="/user/" responsive icon="o-user-plus" class="btn-success" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    @php
        $row_decoration = [
            'text-red-500' => fn(User $user) => $user->enabled === false,
        ];
    @endphp
    <x-card>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" striped :row-decoration="$row_decoration"
            link="/user/{id}">
            @scope('cell_role', $user)
            {{ User::getRoleName($user->role) }}
            @endscope
            @scope('actions', $user)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-chevron-up-down" class="btn-ghost btn-sm" />
                </x-slot:trigger>

                <x-button icon="o-bookmark" wire:click="bookmark({{ $user['id'] }})" spinner
                    class="btn-ghost btn-sm text-lime-500" />
                {{-- <x-button icon="o-trash" wire:click="delete({{ $user['id'] }})" wire:confirm="Are you sure?"
                    spinner class="btn-ghost btn-sm text-red-500" /> --}}
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

</div>