<?php

namespace App\Livewire\ClassSessions;

use App\Enums\EnrollmentStatus;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\JustifiedAbsence;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentAcademicStatusService;
use App\Traits\AuthorizesAccess;
use Carbon\Carbon;
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

    public $evaluationChoices = [];

    #[Url(as: 'subject_id')]
    public $subject_id = null;

    #[Computed]
    public function subject()
    {
        return Subject::find($this->subject_id);
    }

    #[Computed]
    public function justifications(): array
    {
        if (! $this->class_session || ! $this->class_session->date) {
            return [];
        }

        $date = Carbon::parse($this->class_session->date)->toDateString();
        $studentIds = $this->items()->pluck('id')->toArray();

        if (empty($studentIds)) {
            return [];
        }

        return JustifiedAbsence::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id')
            ->toArray();
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
                $this->subject_id = session('current_subject_id') ?? ($user->subjects->first()->id ?? null);
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

        // Persistir el contexto de la materia
        if ($this->subject_id) {
            session()->put('current_subject_id', $this->subject_id);
        }

        $this->authorizeSubject($this->subject_id);

        if ($this->class_session->subject_id != $this->subject_id) {
            $this->redirect('/class-sessions');
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'row_id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'fullname', 'label' => 'Estudiante'],
            ['key' => 'academic_status', 'label' => '% Asist. Gral.', 'sortable' => false],
            ['key' => 'attendance', 'label' => 'Asistencia', 'sortable' => false],
        ];
    }

    #[Computed]
    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii();

        $query = User::query()
            ->select('users.id', 'users.lastname', 'users.firstname', 'users.email', 'users.phone', 'enrollments.id as enrollment_id')
            ->leftJoin('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->leftJoin('grades', function ($join) {
                $join->on('users.id', '=', 'grades.user_id')
                    ->where('grades.class_session_id', '=', $this->class_session->id);
            })
            ->where('enrollments.subject_id', $this->subject_id)
            ->where('enrollments.status', EnrollmentStatus::Active->value)
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

    #[Computed]
    public function enrollments(): Collection
    {
        return Enrollment::where('subject_id', $this->subject_id)->get()->keyBy('id');
    }

    public function attendance($userId): void
    {
        if (isset($this->class_session->id) == false) {
            $this->error('No se ha seleccionado una clase.');

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error('Usuario no encontrado.');

            return;
        }

        // IDOR Prevention: Ensure the user is actually enrolled in this subject and is a student
        $isEnrolled = Enrollment::where('user_id', $userId)
            ->where('subject_id', $this->subject_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->exists();

        if (! $isEnrolled || $user->role !== 'student') {
            abort(403, 'El usuario no está matriculado en esta materia o no es un estudiante válido.');
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
                'type' => 'regular',
                'approved' => 0,
                'comments' => '',
                'recovered_grade_id' => null,
            ];
        } catch (\Throwable $th) {
            $this->grades = [
                'user_id' => $userId,
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'type' => 'regular',
                'approved' => 0,
                'comments' => '',
                'recovered_grade_id' => null,
            ];
        }
        $this->loadEvaluationChoices($userId);
        $this->drawer = true;
    }

    protected function loadEvaluationChoices(int $userId): void
    {
        $this->evaluationChoices = Grade::where('user_id', $userId)
            ->where('type', Grade::TYPE_EVALUATION)
            ->whereHas('classSession', function ($q) {
                $q->where('subject_id', $this->subject_id);
            })
            ->with('classSession')
            ->get()
            ->map(function (Grade $grade) {
                $date = $grade->classSession?->date ? Carbon::parse($grade->classSession->date)->format('d/m/Y') : 's/f';

                return [
                    'id' => $grade->id,
                    'name' => "EV {$date} - Nota: {$grade->grade}",
                ];
            })
            ->toArray();
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
            'grades.type' => ['required', 'string', 'in:regular,evaluation,practical_work,recuperatory'],
            'grades.recovered_grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'grades.comments' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance = (int) $this->grades['attendance'];
        $gradeValue = (int) $this->grades['grade'];
        $type = $this->grades['type'];
        $approved = (int) ($this->grades['approved'] ?? 0);
        $comments = trim($this->grades['comments'] ?? '');
        $recoveredGradeId = $type === 'recuperatory' ? ($this->grades['recovered_grade_id'] ?? null) : null;

        // Economy/Optimization: If all values are zero/empty and the type is regular, delete the record to save space
        if ($type === 'regular' && $attendance === 0 && $gradeValue === 0 && $approved === 0 && empty($comments)) {
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
                    'type' => $type,
                    'approved' => $approved,
                    'comments' => $comments,
                    'recovered_grade_id' => $recoveredGradeId,
                ]
            );
        }
        $this->drawer = false;
        $this->success('Registrado.');

        // Refrescar la propiedad computada para reflejar el cambio (especialmente si se eliminó)
        unset($this->items);
        unset($this->justifications);
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

    public function calculateApprovals(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $enrollments = Enrollment::where('subject_id', $this->subject_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->get();

        $service = app(EnrollmentAcademicStatusService::class);
        $allStatusData = $service->calculate($enrollments);

        $approvedCount = 0;

        foreach ($enrollments as $enrollment) {
            $statusData = $allStatusData[$enrollment->id] ?? null;

            // Si el estado es "Promovido", marcamos como completada
            if ($statusData && $statusData['status'] === 'Promovido') {
                $enrollment->update(['status' => EnrollmentStatus::Completed->value]);
                $approvedCount++;
            }
        }

        if ($approvedCount > 0) {
            $this->success("¡Proceso finalizado! Se han aprobado $approvedCount alumnos automáticamente.");
        } else {
            $this->warning('No se encontraron nuevos alumnos que cumplan con los requisitos de promoción (Asistencia >= 75%, Evaluaciones >= 8 y Trabajos Prácticos >= 6).');
        }

        unset($this->items);
        unset($this->justifications);
    }

    public function render()
    {
        return view('livewire.class_sessions.students', [
            'headers' => $this->headers(),
        ]);
    }
}
