<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Traits\AuthorizesAccess;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use AuthorizesAccess, Toast;

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

    #[Url]
    public $subject_id = null;

    public function mount($id = null)
    {
        $user = auth()->user();

        if ($id !== null) {
            $class_session = ClassSession::findOrFail($id);
            // Security check
            $this->authorizeSubject($class_session->subject_id);

            $this->data = $class_session->toArray();
            $this->subject_id = $class_session->subject_id;
        } else {
            if (! $this->subject_id) {
                $this->redirect('/class-sessions');

                return;
            }

            $this->authorizeSubject($this->subject_id);

            $this->data['teacher_id'] = $user->id;
            $this->data['subject_id'] = $this->subject_id;
            $this->data['date'] = date('Y-m-d');
            $this->data['class_number'] = ClassSession::where('subject_id', $this->subject_id)->count() + 1;
        }
    }

    #[Computed]
    public function subjects()
    {
        return auth()->user()->subjects;
    }

    public function updatedData($value, $key)
    {
        if ($key === 'date' && $this->data['id'] === null && $this->data['subject_id']) {
            $year = date('Y', strtotime($value));
            $this->data['class_number'] = ClassSession::where('subject_id', $this->data['subject_id'])
                ->whereYear('date', $year)
                ->count() + 1;
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
