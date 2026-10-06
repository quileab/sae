<div class="space-y-3">
    @foreach($block['options'] as $op)
        <label class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition hover:border-primary/50
            {{ isset($answers[$block['id']]) && $answers[$block['id']] === $op['key'] ? 'border-primary bg-primary/5' : 'border-base-200 bg-base-100' }}">
            <input type="radio"
                   class="radio radio-primary radio-sm mt-0.5 mr-3"
                   wire:model.live.debounce.400ms="answers.{{ $block['id'] }}"
                   value="{{ $op['key'] }}">
            <span class="text-sm font-medium">
                <span class="font-bold mr-1">{{ $op['key'] }}.</span> {{ $op['text'] }}
            </span>
        </label>
    @endforeach
</div>
