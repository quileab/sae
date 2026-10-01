<?php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       ($el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
?>
<nav aria-label="Pagination Navigation" class="flex items-center justify-between">
    <div class="flex justify-between flex-1 sm:hidden">
        <span>
            @if($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-base-content/60 bg-base-100 border border-base-300 cursor-default leading-5 rounded-lg">
                    {{ __('pagination.previous') }}
                </span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-primary bg-base-100 border border-base-300 rounded-lg hover:bg-primary/5 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary active:bg-primary/10 active:text-primary transition ease-in-out duration-150">
                    {{ __('pagination.previous') }}
                </button>
            @endif
        </span>
        <span>
            @if($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-primary bg-base-100 border border-base-300 rounded-lg hover:bg-primary/5 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary active:bg-primary/10 active:text-primary transition ease-in-out duration-150">
                    {{ __('pagination.next') }}
                </button>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-base-content/60 bg-base-100 border border-base-300 cursor-default leading-5 rounded-lg">
                    {{ __('pagination.next') }}
                </span>
            @endif
        </span>
    </div>

    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-base-content/60 leading-5">
                <span>{{ __('Showing') }}</span>
                <span class="font-semibold text-base-content">{{ $paginator->firstItem() }}</span>
                <span>{{ __('to') }}</span>
                <span class="font-semibold text-base-content">{{ $paginator->lastItem() }}</span>
                <span>{{ __('of') }}</span>
                <span class="font-semibold text-base-content">{{ $paginator->total() }}</span>
                <span>{{ __('results') }}</span>
            </p>
        </div>

        <div>
            <span class="relative z-0 inline-flex rtl:flex-row-reverse rounded-lg shadow-sm">
                @if($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-base-content/60 bg-base-100 border border-base-300 cursor-default rounded-l-lg leading-5">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-primary bg-base-100 border border-base-300 rounded-l-lg leading-5 hover:bg-primary/5 hover:text-primary focus:z-10 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 active:bg-primary/10 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('pagination.previous') }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif

                @foreach($elements as $element)
                    @if(is_string($element))
                        <span aria-disabled="true">
                            <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-base-content/60 bg-base-100 border border-base-300 cursor-default leading-5">{{ $element }}</span>
                        </span>
                    @endif
                    @if(is_array($element))
                        @foreach($element as $page => $url)
                            @if($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-white bg-primary border border-primary cursor-default leading-5">{{ $page }}</span>
                                </span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-base-content bg-base-100 border border-base-300 leading-5 hover:bg-primary/5 hover:text-primary focus:z-10 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 active:bg-primary/10 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-primary bg-base-100 border border-base-300 rounded-r-lg leading-5 hover:bg-primary/5 hover:text-primary focus:z-10 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 active:bg-primary/10 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('pagination.next') }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-base-content/60 bg-base-100 border border-base-300 cursor-default rounded-r-lg leading-5">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </div>
</nav>
