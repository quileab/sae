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
            'text-red-500' => fn(\App\Models\User $user) => $user->enabled === false,
        ];
    @endphp
    <x-card>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" striped :row-decoration="$row_decoration"
            link="/user/{id}">
            @scope('cell_role', $user)
            {{ \App\Models\User::getRoleName($user->role) }}
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