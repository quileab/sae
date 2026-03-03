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
        if (empty($data['value'])) {
            return;
        }

        switch ($data['type']) {
            case 'user_id':
                $user = User::find($data['value']);
                if ($user) {
                    $this->shortName = substr($user['lastname'].' '.$user['firstname'], 0, 30);
                }
                break;
            case 'career_id':
                $career = Career::find($data['value']);
                if ($career) {
                    $this->shortName = substr($career['id'].' '.$career['name'], 0, 30);
                }
                break;
            case 'subject_id':
                $subject = Subject::find($data['value']);
                if ($subject) {
                    $this->shortName = substr($subject['id'].' '.$subject['name'], 0, 30);
                }
                break;
            case 'cycle_id':
                $this->shortName = $data['value'];
                break;
        }

        if ($this->shortName) {
            session()->put($data['type'], $data['value']);
            session()->put($data['type'].'_name', $this->shortName);
        }
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
