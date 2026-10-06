@props([
    'block' => [],
    'number' => 1,
    'answer' => null,
    'indexed' => true,
])

@php
    $answered = App\Services\Evaluations\EvaluationContent::isAnswered($block, $answer);
    $type = App\Enums\EvaluationBlockType::tryFrom($block['type'] ?? '');
    $blockId = $block['id'] ?? '';
    $points = (float) ($block['points'] ?? 1);

@endphp

<div
    wire:key="question-{{ $blockId }}"
    id="q-{{ $blockId }}"
    class="card bg-base-100 border {{ $answered ? 'border-success/40' : 'border-base-200' }} shadow-sm scroll-mt-20"
>
    <div class="card-body p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                @if($indexed)
                    <span class="badge badge-neutral badge-sm shrink-0 mt-0.5 tabular-nums">{{ $number }}</span>
                @endif
                <div class="min-w-0">
                    <h3 class="font-semibold leading-snug">
                        {{ $block['prompt'] ?? 'Sin consigna' }}
                    </h3>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        @if($type)
                            <span class="badge badge-ghost badge-xs">{{ $type->shortLabel() }}</span>
                        @endif
                        <span class="text-xs text-base-content/60">{{ rtrim(rtrim(number_format($points, 2, ',', '.'), '0'), ',') }} pts</span>
                        @if($answered)
                            <span class="badge badge-success badge-xs gap-1">
                                <x-icon name="o-check" class="w-3 h-3" />
                                Respondida
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            @if($points > 0 && ! $answered)
                <span class="text-xs text-base-content/40 shrink-0 hidden sm:block">Sin responder</span>
            @endif
        </div>

        <div class="mt-4">
            @include('livewire.evaluations.blocks.'.($type?->value ?? 'unknown'), ['block' => $block])
        </div>
    </div>
</div>