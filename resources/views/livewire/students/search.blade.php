<div>
    <x-input 
        wire:model.live.debounce.300ms="search" 
        icon="o-magnifying-glass" 
        placeholder="{{ __('Buscar por nombre, apellido o ID...') }}" 
        clearable 
    />

    @if($search)
        <div class="mt-2 bg-base-100 border border-base-300 rounded-lg shadow-xl max-h-64 overflow-y-auto z-50 absolute w-full md:w-80">
            @forelse($this->users as $user)
                <div 
                    wire:click="selectUser({{ $user['id'] }})" 
                    class="p-3 hover:bg-base-200 cursor-pointer border-b border-base-200 last:border-0 flex justify-between items-center transition-colors"
                >
                    <span class="font-medium">{{ $user['name'] }}</span>
                    <x-icon name="o-chevron-right" class="w-4 h-4 opacity-50" />
                </div>
            @empty
                <div class="p-4 text-center text-gray-500 italic">
                    {{ __('No se encontraron resultados') }}
                </div>
            @endforelse
        </div>
    @endif
</div>
