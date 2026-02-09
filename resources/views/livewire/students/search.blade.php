<div>
    <x-choices
        wire:model.live="selectedUserId"
        :options="$this->users"
        search-function="searchUsers"
        option-label="name"
        option-value="id"
        placeholder="{{ __('Buscar estudiante...') }}"
        searchable
        icon="o-magnifying-glass"
        no-result-text="{{ __('No se encontraron estudiantes') }}"
        class="w-full"
        single
    />
</div>