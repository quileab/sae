<div class="space-y-3">
    @if(!empty($block['variants']))
        <div class="mb-4 space-y-2 bg-base-200 p-4 rounded-lg">
            <div class="font-bold text-sm mb-2">Selecciona la consigna que vas a responder:</div>
            @foreach($block['variants'] as $variant)
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio"
                           class="radio radio-primary radio-sm mt-1"
                           wire:model.live.debounce.400ms="answers.{{ $block['id'] }}.variant"
                           value="{{ $variant['key'] }}">
                    <div>
                        <span class="font-bold">{{ $variant['title'] }}:</span>
                        <span class="text-sm">{{ $variant['prompt'] }}</span>
                    </div>
                </label>
            @endforeach
        </div>
    @endif
    <x-textarea
        wire:model.live.debounce.800ms="answers.{{ $block['id'] }}.text"
        rows="4"
        placeholder="Desarrolla tu respuesta aquí..."
    />
</div>
