<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Models\Subject;
use App\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use Toast;

    public bool $drawer = false;

    public $roles = [];

    public $data = [
        'id' => null,
        'subject_id' => null,
        'date' => '',
        'teacher_id' => null,
        'class_number' => 1,
        'unit' => 1,
        'type' => '',
        'content' => '',
        'activities' => '',
        'observations' => '',
    ];

    public $user;

    public $subject;

    public $subjects = [];

    public function mount($id = null)
    {
        $user_id = session('user_id') ?? auth()->user()->id;

        $this->user = User::find($user_id);
        $this->subjects = $this->user->subjects;
        if ($id !== null) {
            $class_session = ClassSession::find($id);
            if ($this->subjects->contains($class_session->subject_id)) {
                $this->data = $class_session->toArray();
            } else {
                $this->redirect('/class-sessions');
            }
        } else {
            $this->data['teacher_id'] = $user_id;
            $subject = Subject::find(session('subject_id'));
            if ($subject) {
                $this->data['subject_id'] = $subject->id;
                $this->data['class_number'] = ClassSession::where('subject_id', $subject->id)->count() + 1;
            }
            $this->data['date'] = date('Y-m-d');
        }
    }

    public function save()
    {
        ClassSession::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Registro guardado');
        $this->redirect('/class-sessions');
    }

    public function delete()
    {
        $session = ClassSession::find($this->data['id']);
        if ($session) {
            $session->delete();
            $this->success('Registro eliminado');
            $this->redirect('/class-sessions');
        }
    }

    public function render()
    {
        return view('livewire.class_sessions.crud');
    }
}
