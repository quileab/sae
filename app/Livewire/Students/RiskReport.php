<?php

namespace App\Livewire\Students;

use App\Enums\EnrollmentStatus;
use App\Models\Career;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentAcademicStatusService;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class RiskReport extends Component
{
    use AuthorizesAccess, Toast;

    #[Url]
    public $subject_id = null;

    #[Url]
    public $career_id = null;

    #[Url]
    public $cycle = null;

    public bool $profileModal = false;

    public $studentProfile = null;

    public function viewProfile($userId): void
    {
        $user = auth()->user();

        // IDOR Prevention: Ensure the target user is a student in a subject related to the current user
        $query = Enrollment::where('user_id', $userId)
            ->where('status', EnrollmentStatus::Active->value);

        if ($user->hasRole('teacher')) {
            $query->whereIn('subject_id', $user->subjects->pluck('id'));
        }

        if (! $query->exists()) {
            abort(403, 'No tienes permiso para ver el perfil de este estudiante.');
        }

        $this->studentProfile = User::with('careers')->find($userId);
        if ($this->studentProfile) {
            $this->profileModal = true;
        } else {
            $this->error('Estudiante no encontrado.');
        }
    }

    public function mount(): void
    {
        $this->authorizeStaff();
        $this->cycle = $this->cycle ?? session('cycle_id') ?? date('Y');

        if (! $this->subject_id) {
            $this->subject_id = session('current_subject_id');
        }

        if ($this->subject_id) {
            $subject = Subject::find($this->subject_id);
            if ($subject) {
                $this->career_id = $subject->career_id;
                session()->put('current_subject_id', $this->subject_id);
            }
        }
    }

    public function updatedSubjectId($value): void
    {
        if ($value) {
            session()->put('current_subject_id', $value);
        } else {
            session()->forget('current_subject_id');
        }
    }

    public function updatedCareerId(): void
    {
        // Al cambiar de carrera, limpiar la materia seleccionada
        $this->subject_id = null;
        session()->forget('current_subject_id');
    }

    public function updatedCycle(): void
    {
        // Al cambiar de ciclo, resetear filtros de carrera y materia
        $this->career_id = null;
        $this->subject_id = null;
        session()->forget('current_subject_id');
    }

    #[Computed]
    public function careers()
    {
        $user = auth()->user();
        $query = Career::where('allow_enrollments', true)
            ->where('allow_evaluations', true);

        if ($user->hasRole('teacher')) {
            $teacherSubjectIds = $user->subjects->pluck('id');

            return $query->whereHas('subjects', function ($q) use ($teacherSubjectIds) {
                $q->whereIn('id', $teacherSubjectIds);
            })->orderBy('name')->get();
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function subjects()
    {
        $user = auth()->user();
        $query = Subject::query();

        if ($this->career_id) {
            $query->where('career_id', $this->career_id);
        }

        if ($user->hasRole('teacher')) {
            $query->whereIn('id', $user->subjects->pluck('id'));
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Construye el reporte de estudiantes en riesgo usando EnrollmentAcademicStatusService
     * como fuente de verdad para asistencia, y calculando promedios de notas en el mismo
     * batch de grades para evitar queries adicionales.
     *
     * Umbrales alineados con EnrollmentAcademicStatusService::computeSingle():
     *   - Asistencia < 75%  → Libre (riesgo por asistencia)
     *   - Promedio EV < 4   → Riesgo académico
     *   - Promedio TP < 4   → Riesgo académico
     *
     * @return Collection<int, array{id: int, fullname: string, career: string, subject: string, attendance: int, avg_ev: float, avg_tp: float, reasons: list<string>, risk_level: string}>
     */
    #[Computed]
    public function riskStudents(): Collection
    {
        $user = auth()->user();
        $cycle = (int) $this->cycle;
        $startDate = Carbon::create($cycle, 2, 1)->startOfDay();

        // Cargar IDs de materias del profesor una sola vez (evita N+1)
        $teacherSubjectIds = $user->hasRole('teacher')
            ? $user->subjects->pluck('id')
            : null;

        $enrollmentQuery = Enrollment::where('status', EnrollmentStatus::Active->value)
            ->whereYear('created_at', $cycle)
            ->with(['user', 'subject.career']);

        if ($this->subject_id) {
            $enrollmentQuery->where('subject_id', $this->subject_id);
        } elseif ($this->career_id) {
            $enrollmentQuery->whereHas('subject', fn ($q) => $q->where('career_id', $this->career_id));
        }

        if ($teacherSubjectIds) {
            $enrollmentQuery->whereIn('subject_id', $teacherSubjectIds);
        }

        $service = app(EnrollmentAcademicStatusService::class);
        $report = collect();

        // Procesar en bloques para evitar agotar la memoria (OOM) en "Todas las carreras"
        $enrollmentQuery->chunk(300, function ($chunk) use ($service, $cycle, $startDate, $report) {
            $enrollments = $chunk->filter(
                fn ($e) => $e->user && $e->user->role === 'student'
            );

            if ($enrollments->isEmpty()) {
                return;
            }

            $academicStatuses = $service->calculate($enrollments, $cycle);

            $userIds = $enrollments->pluck('user_id')->unique();
            $subjectIds = $enrollments->pluck('subject_id')->unique();

            $gradesByUserAndSubject = Grade::whereIn('user_id', $userIds)
                ->whereHas('classSession', function ($q) use ($subjectIds, $cycle, $startDate) {
                    $q->whereIn('subject_id', $subjectIds)
                        ->whereYear('date', $cycle)
                        ->where('date', '>=', $startDate)
                        ->where('date', '<=', now());
                })
                ->with('classSession')
                ->get()
                ->groupBy(['user_id', fn ($g) => $g->classSession->subject_id]);

            foreach ($enrollments as $enrollment) {
                $status = $academicStatuses[$enrollment->id] ?? null;

                if (! $status || $status['status'] === 'Sin Datos') {
                    continue;
                }

                $student = $enrollment->user;
                $attendancePercentage = $status['attendance'];
                $reasons = [];

                if ($status['status'] === 'Sin Fecha') {
                    $reasons[] = 'Sin actividad (No iniciado)';
                    $report->push([
                        'id' => $student->id,
                        'fullname' => $student->fullname,
                        'career' => $enrollment->subject->career->name ?? 'N/A',
                        'subject' => $enrollment->subject->name,
                        'attendance' => 0,
                        'avg_ev' => 0,
                        'avg_tp' => 0,
                        'reasons' => $reasons,
                        'risk_level' => 'Atención',
                    ]);

                    continue;
                }

                $studentGrades = $gradesByUserAndSubject[$student->id][$enrollment->subject_id] ?? collect();
                $avgEv = app(EnrollmentAcademicStatusService::class)
                    ->resolveEffectiveEvaluations($studentGrades)
                    ->avg('grade') ?? 0;
                $avgTp = $studentGrades->where('type', 'practical_work')->avg('grade') ?? 0;

                if ($attendancePercentage < 75) {
                    $reasons[] = 'Asist. '.$attendancePercentage.'%';
                }

                if ($avgEv > 0 && $avgEv < 4) {
                    $reasons[] = 'Bajo Prom. EV ('.round($avgEv, 1).')';
                }

                if ($avgTp > 0 && $avgTp < 4) {
                    $reasons[] = 'Bajo Prom. TP ('.round($avgTp, 1).')';
                }

                if ($studentGrades->isEmpty() && $attendancePercentage === 0) {
                    $reasons[] = 'Sin actividad (Abandono?)';
                }

                if (empty($reasons)) {
                    continue;
                }

                $riskLevel = ($attendancePercentage < 50 || count($reasons) >= 2)
                    ? 'Crítico'
                    : 'Atención';

                $report->push([
                    'id' => $student->id,
                    'fullname' => $student->fullname,
                    'career' => $enrollment->subject->career->name ?? 'N/A',
                    'subject' => $enrollment->subject->name,
                    'attendance' => $attendancePercentage,
                    'avg_ev' => round($avgEv, 1),
                    'avg_tp' => round($avgTp, 1),
                    'reasons' => $reasons,
                    'risk_level' => $riskLevel,
                ]);
            }
        });

        return $report->sortByDesc(fn ($item) => $item['risk_level'] === 'Crítico' ? 2 : 1)->values();
    }

    public function render()
    {
        return view('livewire.students.risk-report');
    }
}
