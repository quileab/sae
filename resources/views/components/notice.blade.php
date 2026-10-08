@props([
    'variant' => 'info',
    'text' => '',
    'position' => 'right',
])

{{--
    An advisory reduced to an icon: the message shows on hover and on keyboard
    focus through the DaisyUI tooltip, and on click for touch. Use it for the
    "good to know" notes; anything the teacher has to read in full (parse errors,
    a rubric that does not add up) belongs in the page body.
--}}
@php
    $icons = [
        'info' => 'o-information-circle',
        'warning' => 'o-exclamation-triangle',
        'danger' => 'o-exclamation-circle',
        'success' => 'o-check-circle',
    ];

    $colors = [
        'info' => 'text-info',
        'warning' => 'text-warning',
        'danger' => 'text-error',
        'success' => 'text-success',
    ];
@endphp

<span
    class="tooltip tooltip-{{ $position }} tooltip-{{ $variant }} inline-flex align-middle"
    data-tip="{{ $text }}"
>
    <button
        type="button"
        aria-label="{{ $text }}"
        class="{{ $colors[$variant] ?? $colors['info'] }} cursor-help rounded-full focus-visible:ring-2 focus-visible:ring-current"
        x-on:click="$el.closest('.tooltip').classList.toggle('tooltip-open')"
    >
        <x-mary-icon :name="$icons[$variant] ?? $icons['info']" class="w-4 h-4" />
    </button>
</span>