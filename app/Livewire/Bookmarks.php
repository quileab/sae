<?php

namespace App\Livewire;

use App\Models\Book;
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

        // Security check: Role-based permissions for bookmarking
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor']);
        $isTeacher = $user->hasRole('teacher');

        if (! $isAdmin) {
            // Students can only bookmark themselves
            if ($data['type'] === 'user_id') {
                if ($data['value'] != $user->id && ! $isTeacher) {
                    return;
                }
            }

            // For subjects and careers, verify enrollment/ownership
            if (in_array($data['type'], ['subject_id', 'career_id'])) {
                if ($data['type'] === 'subject_id' && ! $user->hasSubject($data['value'])) {
                    return;
                }
                if ($data['type'] === 'career_id' && ! $user->careers()->where('career_id', $data['value'])->exists()) {
                    return;
                }
            }
        }

        switch ($data['type']) {
            case 'user_id':
                $user = User::find($data['value']);
                if ($user) {
                    $this->shortName = substr($user['lastname'].' '.$user['firstname'], 0, 30);
                }
                break;
            case 'book_id':
                $book = Book::find($data['value']);
                if ($book) {
                    $this->shortName = substr($book->title, 0, 30);
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

    #[On('refresh-bookmarks')]
    public function refresh(): void
    {
        // Este método simplemente dispara el re-renderizado del componente
    }

    public function render()
    {
        return view('livewire.bookmarks');
    }
}
