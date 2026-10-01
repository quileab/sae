@props([
    'id' => null,
    'right' => false,
    'title' => null,
    'subtitle' => null,
    'separator' => false,
    'withCloseButton' => false,
    'closeOnEscape' => false,
    'withoutTrapFocus' => false,
])

@php
    $modelName = $attributes->wire('model');
    $uuid = $id ?? $modelName->value() ?? 'qb-drawer-' . uniqid();
@endphp

<div x-data="{
        open: @if($modelName->value) @entangle($modelName).live @else false @endif,
        init() {
            this.$watch('open', value => {
                document.body.style.overflow = value ? 'hidden' : '';
            });
        },
        destroy() {
            document.body.style.overflow = '';
        }
    }">
    
    <template x-teleport="body">
        <div x-show="open"
            x-cloak
            @if($closeOnEscape) @keydown.window.escape="open = false" @endif
            @if(!$withoutTrapFocus) x-trap="open" x-bind:inert="!open" @endif
            class="fixed inset-0 z-[9999] overflow-hidden"
            aria-labelledby="{{ $uuid }}-title"
            role="dialog"
            aria-modal="true">

            <!-- Backdrop -->
            <div x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity cursor-pointer"></div>

            <!-- Panel Wrapper -->
            <div class="absolute inset-0 pointer-events-none flex {{ $right ? 'justify-end pl-6' : 'justify-start pr-6' }}">
                <!-- Panel -->
                <div x-show="open"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="{{ $right ? 'translate-x-full' : '-translate-x-full' }}"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="{{ $right ? 'translate-x-full' : '-translate-x-full' }}"
                    {{ $attributes->except('wire:model')->class(['relative pointer-events-auto w-screen max-w-full bg-base-100 shadow-2xl flex flex-col h-full border-base-300', $right ? 'border-l' : 'border-r']) }}>

                    <!-- Header -->
                    @if($title || $withCloseButton)
                    <div class="px-6 py-4 bg-base-200/80 border-b border-base-300 flex items-center justify-between shrink-0">
                        <div>
                            @if($title)
                            <h3 class="text-lg font-bold text-base-content" id="{{ $uuid }}-title">{{ $title }}</h3>
                            @endif
                            @if($subtitle)
                            <div class="flex items-center gap-2 text-sm text-primary font-semibold mt-0.5">
                                <span>{{ $subtitle }}</span>
                            </div>
                            @endif
                        </div>
                        
                        @if($withCloseButton)
                        <button type="button" class="btn btn-ghost btn-sm btn-circle" @click="open = false">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        @endif
                    </div>
                    @endif

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                        {{ $slot }}
                    </div>

                    <!-- Footer / Actions -->
                    @if(isset($actions))
                    <div class="px-6 py-4 bg-base-200/80 {{ $separator ? 'border-t border-base-300' : '' }} flex items-center justify-between shrink-0">
                        {{ $actions }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
