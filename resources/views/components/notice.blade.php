@props([
    'variant' => 'info',
    'text' => '',
    'title' => '',
    'label' => null,
])

{{--
    An advisory reduced to an icon that opens a modal with the whole message.

    A tooltip was not enough: these notes decide whether a teacher sends student
    names to an external model, and truncating them into a hover bubble is the
    wrong trade.

    The modal is teleported to <body> on purpose. DaisyUI positions .modal with
    `position: fixed`, but any ancestor with a transform/filter becomes the
    containing block and the modal ends up centered on that container instead of
    the screen. Teleporting escapes those ancestors, and since the source stays
    in place the trigger keeps its position and its Alpine scope.

    Pass `label` to render a visible text trigger next to the icon (keeps the
    affordance discoverable). Pass rich HTML as the slot for long-form help; the
    plain `text` prop stays for one-paragraph notes.
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

    $icon = $icons[$variant] ?? $icons['info'];
    $color = $colors[$variant] ?? $colors['info'];
    $richContent = trim($slot->toHtml());
@endphp

<span
    class="inline-flex align-middle"
    data-notice
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        aria-label="{{ $title }}: más información"
        aria-haspopup="dialog"
        class="{{ $color }} cursor-pointer focus-visible:ring-2 focus-visible:ring-current {{ $label ? 'btn btn-xs btn-ghost gap-1 normal-case font-normal' : 'rounded-full' }}"
        x-on:click="open = true; $nextTick(() => { if ($refs.close) { $refs.close.focus(); } })"
    >
        <x-mary-icon :name="$icon" class="w-4 h-4" />
        @if($label)
            <span class="text-xs">{{ $label }}</span>
        @endif
    </button>

    <template x-teleport="body">
        <div
            class="modal"
            x-bind:class="{ 'modal-open': open }"
            x-on:click.self="open = false"
            role="dialog"
            aria-modal="true"
            aria-label="{{ $title }}"
        >
            <div class="modal-box max-w-lg">
                <h3 class="flex items-center gap-2 font-bold text-lg">
                    <x-mary-icon :name="$icon" class="w-5 h-5 {{ $color }}" />
                    {{ $title }}
                </h3>
                <div class="py-4 text-sm {{ $richContent === '' ? 'whitespace-pre-line' : '' }}">
                    @if($richContent === '')
                        {{ $text }}
                    @else
                        {!! $richContent !!}
                    @endif
                </div>
                <div class="modal-action">
                    <button type="button" class="btn btn-sm" x-ref="close" x-on:click="open = false">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </template>
</span>
