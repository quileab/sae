<?php

use Livewire\Volt\Component;
//use Mary\Traits\Toast;
use Livewire\Attributes\On; 

new class extends Component {
    //use Toast;


    #[On('bookmarked')]    
    public function updateBookmark($data): void
    {
        switch ($data['type']) {
            case 'user_id':
                $user = \App\Models\User::find($data['value']);
                $this->shortName = substr($user['lastname'].' '.$user['firstname'], 0, 20);
                break;
            case 'career_id':
                $career = \App\Models\Career::find($data['value']);
                $this->shortName = substr($career['id'].' '.$career['name'], 0, 20);
                break;
            case 'subject_id':
                $subject = \App\Models\Subject::find($data['value']);
                $this->shortName = substr($subject['id'].' '.$subject['name'], 0, 20);
                break;
        }
        // Si necesitas sincronizar con la sesión:
        session()->put($data['type'], $data['value']);
        session()->put($data['type'].'_name', $this->shortName);
        //$this->success('User bookmarked.', position: 'toast-bottom');
    }

    public function clearBookmark($type): void
    {
        session()->forget($type);
        session()->forget($type.'_name');
        //$this->success('User bookmark cleared.', position: 'toast-bottom');
    }
    //
}; ?>

<div class="grid gap-0">
    @foreach (['user_id', 'career_id', 'subject_id'] as $key)
        @if(session()->get($key,false))
        <x-button label="{{ session($key.'_name') }}" icon-right="o-bookmark" class="bg-blue-500/20 btn-sm"
            wire:click="clearBookmark('{{ $key }}')" />
        @endif
    @endforeach
</div>
