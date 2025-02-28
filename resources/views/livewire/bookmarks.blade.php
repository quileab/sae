<?php

use Livewire\Volt\Component;
//use Mary\Traits\Toast;
use Livewire\Attributes\On;

new class extends Component {
    //use Toast;

    #[On('bookmarked')]
    public function updateBookmark($data): void
    {
        //dd($data);
        switch ($data['type']) {
            case 'user_id':
                $user = \App\Models\User::find($data['value']);
                $this->shortName = substr($user['lastname'] . ' ' . $user['firstname'], 0, 20);
                break;
            case 'career_id':
                $career = \App\Models\Career::find($data['value']);
                $this->shortName = substr($career['id'] . ' ' . $career['name'], 0, 20);
                break;
            case 'subject_id':
                $subject = \App\Models\Subject::find($data['value']);
                $this->shortName = substr($subject['id'] . ' ' . $subject['name'], 0, 20);
                break;
            case 'cycle_id':
                $this->shortName = $data['value'];
                break;
        }
        // Si necesitas sincronizar con la sesión:
        session()->put($data['type'], $data['value']);
        session()->put($data['type'] . '_name', $this->shortName);
        //$this->success('bookmarked.' . $data['type'], position: 'toast-bottom');
    }

    public function clearBookmark($type): void
    {
        if ($type == 'cycle_id') {
            
            return;
        }
        session()->forget($type);
        session()->forget($type . '_name');
        //$this->success('User bookmark cleared.', position: 'toast-bottom');
    }
    //
}; ?>

<div class="grid gap-0">
    {{-- <x-menu-sub title="Bookmarks"
        icon="{{ session()->has('user_id') || session()->has('career_id') || session()->has('subject_id') ? 's-bookmark' : 'o-bookmark'}}">
        --}}
        @foreach (['user_id', 'career_id', 'subject_id', 'cycle_id'] as $key)
            @if(session()->get($key, false))
                <div class="w-full">
                    <x-button label="{{ session($key . '_name') }}" icon="o-bookmark-slash" class="btn-xs text-primary"
                        wire:click="clearBookmark('{{ $key }}')" />
                </div>
            @endif
        @endforeach
        {{--
    </x-menu-sub> --}}
</div>