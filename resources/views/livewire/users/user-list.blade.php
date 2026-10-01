<div>
    <!-- HEADER -->
    <x-header title="Usuarios">
        <x-slot:middle class="!justify-end flex items-center gap-2">
            <x-select :options="$this->statuses" wire:model.live="filterStatus" placeholder="Todos los estados" placeholder-value=""
                icon="o-flag" class="select-sm w-44" />
            <x-input placeholder="Buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" class="input-sm" />
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
            link="/user/{id}" with-pagination>
            @scope('cell_fullname', $user)
            <div class="flex items-center gap-2">
                <x-avatar :image="$user->avatar_url" class="!w-8 !h-8" />
                <span>{{ $user['fullname'] ?? $user->fullname }}</span>
            </div>
            @endscope
            @scope('cell_role', $user)
            {{ \App\Models\User::getRoleName($user->role) }}
            @endscope
            @scope('cell_status', $user)
            <span class="badge badge-sm {{ $user->status_badge }}">{{ $user->status_label }}</span>
            @endscope
            @scope('actions', $user)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-chevron-up-down" class="btn-ghost btn-sm" />
                </x-slot:trigger>

                <x-button icon="o-academic-cap" wire:click="selectForEnrollment({{ $user['id'] }})" spinner
                    class="btn-ghost btn-sm text-lime-500" title="Asignar Materias" />
                
                <x-button icon="o-currency-dollar" link="{{ route('user-payments', $user->id) }}"
                    class="btn-ghost btn-sm text-emerald-500" title="Registrar Pago" />
            </x-dropdown>
            @endscope
        </x-table>
    </x-card>

</div>