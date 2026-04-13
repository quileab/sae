<div>
    {{-- Otros marcadores (Legacy/Temporal) --}}
    <div class="mt-2 space-y-1">
        @foreach (['user_id', 'book_id', 'career_id', 'subject_id'] as $key)
            @if(session()->get($key, false))
                <x-button label="{{ session($key . '_name') }}" icon="o-bookmark" 
                    class="btn-xs text-primary w-full justify-start" 
                    wire:click="clearBookmark('{{ $key }}')" />
            @endif
        @endforeach
    </div>
</div>
