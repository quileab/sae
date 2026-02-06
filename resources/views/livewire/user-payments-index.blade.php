<div>
    <div class="flex justify-between gap-2 flex-wrap">
        <x-header title="{{ __('Control de Pagos') }}" subtitle="{{ __('Administrar pagos y planes de pago') }}" progress-indicator />

        <x-input icon="o-magnifying-glass" wire:model.live.debounce.500ms="search" placeholder="{{ __('Buscar...') }}"
            class="w-72 flex-1" />
        <x-select wire:model.live="perPage" label="{{ __('Mostrar') }}" inline
            :options="[['id' => 10, 'name' => 10], ['id' => 25, 'name' => 25], ['id' => 50, 'name' => 50], ['id' => 100, 'name' => 100]]" />

    </div>


    <x-table :headers="[['key' => 'id', 'label' => __('ID')], ['key' => 'full_name', 'label' => __('Usuario')], ['key' => 'email', 'label' => __('Email')]]" :rows="$this->users" striped link="user-payments/{id}" with-pagination>
    </x-table>
</div>