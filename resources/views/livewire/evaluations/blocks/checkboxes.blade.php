<div class="space-y-3">
    @php
        // Initialize as array if not set
        if (!isset($answers[$block['id']]) || !is_array($answers[$block['id']])) {
            $answers[$block['id']] = [];
        }
    @endphp
    @foreach($block['options'] as $op)
        <label class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition hover:border-primary/50
            {{ in_array($op['key'], $answers[$block['id']]) ? 'border-primary bg-primary/5' : 'border-base-200 bg-base-100' }}">
            <input type="checkbox"
                   class="checkbox checkbox-primary checkbox-sm mt-0.5 mr-3"
                   wire:model.live.debounce.400ms="answers.{{ $block['id'] }}"
                   value="{{ $op['key'] }}">
            <span class="text-sm font-medium">
                <span class="font-bold mr-1">{{ $op['key'] }}.</span> {{ $op['text'] }}
            </span>
        </label>
    @endforeach
</div>
