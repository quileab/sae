<?php

namespace App\Livewire\ClassSessions;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Students extends Component
{
    use AuthorizesAccess, Toast;

    public string $search = '';

    public string $filterRole = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'lastname', 'direction' => 'asc'];

    private $role_student = 'student';

    public $class_session;

    public $grades = [];

    public $data = [];

    #[Url(as: 'subject_id')]
    public $subject_id = null;

    #[Computed]
    public function subject()
    {
        return \App\Models\Subject::find($this->subject_id);
    }

    public function mount($id = null)
    {
        $this->authorizeStaff();
        $user = auth()->user();

        if ($id !== null) {
            $this->class_session = ClassSession::findOrFail($id);
            if (! $this->subject_id) {
                $this->subject_id = $this->class_session->subject_id;
            }
        } else {
            // Si no hay subject_id por URL, intentar sesión o primera materia disponible
            if (! $this->subject_id) {
                $this->subject_id = session('subject_id') ?? ($user->subjects->first()->id ?? null);
            }

            if (! $this->subject_id) {
                $this->redirect('/class-sessions');

                return;
            }

            $this->class_session = new ClassSession;
            $this->class_session->id = null;
            $this->class_session->subject_id = $this->subject_id;
            $this->class_session->teacher_id = $user->id;
            $this->class_session->date = now();
            $this->class_session->class_number = 0;
            $this->class_session->unit = '';
            $this->class_session->content = '';
        }

        $this->authorizeSubject($this->subject_id);

        if ($this->class_session->subject_id != $this->subject_id) {
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

    #[Computed]
    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        $query = User::query()
            ->select('users.id', 'users.lastname', 'users.firstname', 'users.email', 'users.phone')
            ->leftJoin('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->leftJoin('grades', function ($join) {
                $join->on('users.id', '=', 'grades.user_id')
                    ->where('grades.class_session_id', '=', $this->class_session->id);
            })
            ->where('enrollments.subject_id', $this->subject_id)
            ->where('enrollments.status', 'active')
            ->where('users.role', $this->role_student)
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->addSelect('grades.grade as grade', 'grades.attendance as attendance');

        if ($this->search) {
            $query->where(function ($q) use ($search) {
                $q->where('lastname', 'like', '%'.$search.'%')
                    ->orWhere('firstname', 'like', '%'.$search.'%');
            });
        }

        return $query->get();
    }

    public function attendance($userId): void
    {
        if (isset($this->class_session->id) == false) {
            $this->error('No se ha seleccionado una clase.');

            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error('Usuario no encontrado.');
            return;
        }

        $this->data = $user->toArray();
        try {
            $grade = Grade::where('user_id', $userId)
                ->where('class_session_id', $this->class_session->id)
                ->first();
            $this->grades = $grade ? $grade->toArray() : [
                'user_id' => $userId,
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'approved' => 0,
                'comments' => '',
            ];
        } catch (\Throwable $th) {
            $this->grades = [
                'user_id' => $userId,
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'approved' => 0,
                'comments' => '',
            ];
        }
        $this->drawer = true;
    }

    public function attendanceSet($userId, $value): void
    {
        $this->attendance($userId);
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

        $attendance = (int) $this->grades['attendance'];
        $gradeValue = (int) $this->grades['grade'];
        $approved = (int) ($this->grades['approved'] ?? 0);
        $comments = trim($this->grades['comments'] ?? '');

        // Economy/Optimization: If all values are zero/empty, delete the record to save space
        if ($attendance === 0 && $gradeValue === 0 && $approved === 0 && empty($comments)) {
            Grade::where('user_id', $this->data['id'])
                ->where('class_session_id', $this->class_session->id)
                ->delete();
        } else {
            Grade::updateOrCreate(
                ['user_id' => $this->data['id'], 'class_session_id' => $this->class_session->id],
                [
                    'user_id' => $this->data['id'],
                    'class_session_id' => $this->class_session->id,
                    'attendance' => $attendance,
                    'grade' => $gradeValue,
                    'approved' => $approved,
                    'comments' => $comments,
                ]
            );
        }

        $this->drawer = false;
        $this->success('Registrado.');
        
        // Refrescar la propiedad computada para reflejar el cambio (especialmente si se eliminó)
        unset($this->items);
    }

    public bool $profileModal = false;

    public $studentProfile = null;

    public function viewProfile($userId): void
    {
        $this->studentProfile = User::with('careers')->find($userId);
        if ($this->studentProfile) {
            $this->profileModal = true;
        } else {
            $this->error('Estudiante no encontrado.');
        }
    }

    public function bookmark($id): void
    {
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
        $this->success('Usuario marcado como contexto actual.');
    }

    public function deregister(): void
    {
        Enrollment::where('user_id', $this->data['id'])
            ->where('subject_id', $this->subject_id)
            ->delete();
        $this->success('Estudiante desmatriculado.');
        $this->drawer = false;
    }

    public function render()
    {
        return view('livewire.class_sessions.students', [
            'headers' => $this->headers(),
        ]);
    }
}
