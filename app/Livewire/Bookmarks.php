<?php

namespace App\Livewire;

use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Bookmarks extends Component
{
    public $shortName;

    #[On('bookmarked')]
    public function updateBookmark($data): void
    {
        switch ($data['type']) {
            case 'user_id':
                $user = User::find($data['value']);
                $this->shortName = substr($user['lastname'].' '.$user['firstname'], 0, 30);
                break;
            case 'career_id':
                $career = Career::find($data['value']);
                $this->shortName = substr($career['id'].' '.$career['name'], 0, 30);
                break;
            case 'subject_id':
                $subject = Subject::find($data['value']);
                $this->shortName = substr($subject['id'].' '.$subject['name'], 0, 30);
                break;
            case 'cycle_id':
                $this->shortName = $data['value'];
                break;
        }
        // Si necesitas sincronizar con la sesión:
        session()->put($data['type'], $data['value']);
        session()->put($data['type'].'_name', $this->shortName);
    }

    public function clearBookmark($type): void
    {
        if ($type == 'cycle_id' || Auth::user()->hasAnyRole(['student', 'teacher', 'basic_user'])) {
            return;
        }
        session()->forget($type);
        session()->forget($type.'_name');
    }

    public function render()
    {
        return view('livewire.bookmarks');
    }
}
