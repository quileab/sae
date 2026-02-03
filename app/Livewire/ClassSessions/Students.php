<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Students extends Component
{
    use Toast;

    public string $search = '';

    public string $filterRole = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'lastname', 'direction' => 'asc'];

    private $role_student = 'student';

    public $class_session;

    public $grades = [];

    public $data = [];

    public function mount($id = null)
    {
        // check if user is logged in and has teacher role
        if (! auth()->user()->hasAnyRole(['teacher', 'admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }

        if (session()->get('subject_id', false) == false) {
            $this->redirect('/class-sessions');
        }
        if ($id !== null) {
            $this->class_session = ClassSession::find($id);
        } else {
            $this->class_session = new ClassSession;
            $this->class_session->id = null;
            $this->class_session->subject_id = session('subject_id');
            $this->class_session->teacher_id = session('user_id');
            $this->class_session->date = now();
            $this->class_session->class_number = 0;
            $this->class_session->unit = '';
            $this->class_session->content = '';
        }
        // check if class session subject_id belongs to this user
        if (
            $this->class_session->subject_id != session('subject_id') ||
            auth()->user()->hasSubject(session('subject_id') == false)
        ) {
            $this->redirect('/class-sessions');
        }
    }

    public function delete($id): void
    {
        $this->warning("Will delete #$id", 'It is fake.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'row_id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'lastname', 'label' => 'Apellido', 'class' => 'w-64'],
            ['key' => 'firstname', 'label' => 'Nombre', 'class' => 'w-full'],
            ['key' => 'attendance', 'label' => 'Asistencia', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();
        $subjectId = session('subject_id');

        $query = User::query()
            ->select('users.id', 'users.lastname', 'users.firstname', 'users.email', 'users.phone')
            ->leftJoin('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->leftJoin('grades', function ($join) {
                $join->on('users.id', '=', 'grades.user_id')
                    ->where('grades.class_session_id', '=', $this->class_session->id);
            })
            ->where('enrollments.subject_id', $subjectId)
            ->where('enrollments.status', 'active')
            ->where('users.role', $this->role_student)
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->addSelect('grades.grade as grade', 'grades.attendance as attendance');

        return $query->get();
    }

    public function attendance($item): void
    {
        if (isset($this->class_session->id) == false) {
            $this->error('No se ha seleccionado una clase.');

            return;
        }
        $this->data = $item;
        try {
            $grade = Grade::where('user_id', $item['id'])
                ->where('class_session_id', $this->class_session->id)
                ->first();
            $this->grades = $grade ? $grade->toArray() : [
                'user_id' => $item['id'],
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'approved' => 0,
                'comments' => '',
            ];
        } catch (\Throwable $th) {
            $this->grades = [
                'user_id' => $item['id'],
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'approved' => 0,
                'comments' => '',
            ];
        }
        $this->drawer = true;
    }

    public function attendanceSet($item, $value): void
    {
        $this->attendance($item);
        $this->saveGrade($value);
    }

    public function saveGrade($value = null): void
    {
        if ($value !== null) {
            $this->grades['attendance'] = $value;
        }
        $this->validate([
            'grades.attendance' => ['required', 'integer', 'min:0', 'max:100'],
            'grades.grade' => ['required', 'integer', 'min:0', 'max:10'],
            'grades.comments' => ['nullable', 'string', 'max:255'],
        ]);

        Grade::updateOrCreate(
            ['user_id' => $this->data['id'], 'class_session_id' => $this->class_session->id],
            [
                'user_id' => $this->data['id'],
                'class_session_id' => $this->class_session->id,
                'attendance' => $this->grades['attendance'],
                'grade' => $this->grades['grade'],
                'approved' => $this->grades['approved'],
                'comments' => $this->grades['comments'],
            ]
        );
        $this->drawer = false;
        $this->success('Registrado.');
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
    }

    public function deregister(): void
    {
        Enrollment::where('user_id', $this->data['id'])
            ->where('subject_id', session('subject_id'))
            ->delete();
        $this->success('Estudiante desmatriculado.');
        $this->drawer = false;
    }

    public function render()
    {
        return view('livewire.class_sessions.students', [
            'items' => $this->items(),
            'headers' => $this->headers(),
        ]);
    }
}
