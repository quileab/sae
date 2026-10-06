<div class="space-y-4">
    @php
        // Initialize as array if not set
        if (!isset($answers[$block['id']]) || !is_array($answers[$block['id']])) {
            $answers[$block['id']] = [];
        }
        
        $matches = array_column($block['pairs'] ?? [], 'answer');
        sort($matches);
    @endphp
    <div class="grid grid-cols-1 gap-4 items-center">
        @foreach($block['pairs'] as $index => $pair)
            <div class="flex items-center space-x-3 p-3 bg-base-100 border border-base-200 rounded-lg">
                <div class="flex-1 text-sm font-medium">{{ $pair['prompt'] }}</div>
                <div class="text-base-300">
                    <x-icon name="o-arrow-right" class="w-5 h-5" />
                </div>
                <div class="flex-1">
                    <select class="select select-bordered select-sm w-full"
                            wire:model.live.debounce.400ms="answers.{{ $block['id'] }}.{{ $index }}">
                        <option value="">Seleccione una opción...</option>
                        @foreach($matches as $match)
                            <option value="{{ $match }}">{{ $match }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach
    </div>
</div>
