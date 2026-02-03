<div class="grid gap-0">
    {{-- <x-menu-sub title="Bookmarks"
        icon="{{ session()->has('user_id') || session()->has('career_id') || session()->has('subject_id') ? 's-bookmark' : 'o-bookmark'}}">
        --}}
        @foreach (['user_id', 'career_id', 'subject_id', 'cycle_id'] as $key)
            @if(session()->get($key, false))
                <div class="w-full">
                    <x-button label="{{ session($key . '_name') }}" icon="o-bookmark" class="btn-xs text-primary" responsive
                        wire:click="clearBookmark('{{ $key }}')" />
                </div>
            @endif
        @endforeach
        {{--
    </x-menu-sub> --}}
</div>