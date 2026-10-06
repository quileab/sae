<div class="space-y-3">
    <x-input
        wire:model.live.debounce.600ms="answers.{{ $block['id'] }}"
        placeholder="Escribe tu respuesta aquí..."
    />
</div>
