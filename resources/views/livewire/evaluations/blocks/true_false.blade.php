<div class="space-y-3">
    @php
        $options = [
            ['key' => 'true', 'text' => 'Verdadero'],
            ['key' => 'false', 'text' => 'Falso']
        ];
    @endphp
    @foreach($options as $op)
        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition hover:border-primary/50
            {{ isset($answers[$block['id']]) && $answers[$block['id']] === $op['key'] ? 'border-primary bg-primary/5' : 'border-base-200 bg-base-100' }}">
            <input type="radio"
                   class="radio radio-primary radio-sm mr-3"
                   wire:model.live.debounce.400ms="answers.{{ $block['id'] }}"
                   value="{{ $op['key'] }}">
            <span class="text-sm font-medium">
                {{ $op['text'] }}
            </span>
        </label>
    @endforeach
</div>
