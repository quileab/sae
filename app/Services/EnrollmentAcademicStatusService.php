<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Support\Collection;

class EnrollmentAcademicStatusService
{
    public const PROMOTION_MIN_ATTENDANCE = 75;

    public const PROMOTION_MIN_EV = 8;

    public const PROMOTION_MIN_TP = 6;

    public const REGULAR_MIN_EV = 4;

    public const REGULAR_MIN_TP = 4;

    /**
     * Calcula el estado académico para un conjunto de inscripciones de forma eficiente.
     *
     * @param  Collection<int, Enrollment>|Enrollment  $enrollments
     */
    public function calculate($enrollments, ?int $cycle = null): array
    {
        $isSingle = $enrollments instanceof Enrollment;
        $enrollments = $isSingle ? collect([$enrollments]) : $enrollments;

        if ($enrollments->isEmpty()) {
            return [];
        }

        $cycle ??= session('cycle_id') ? (int) session('cycle_id') : null;

        // Agrupar por materia para optimizar queries de sesiones de clase
        $subjectIds = $enrollments->pluck('subject_id')->unique();
        $userIds = $enrollments->pluck('user_id')->unique();

        // Obtener todas las calificaciones relevantes de una sola vez
        $allGrades = Grade::whereIn('user_id', $userIds)
            ->whereHas('classSession', function ($q) use ($subjectIds) {
                $q->whereIn('subject_id', $subjectIds);
            })
            ->with('classSession')
            ->get()
            ->groupBy(['user_id', 'classSession.subject_id']);

        // Obtener conteos de clases por materia y año
        $classCounts = [];
        foreach ($subjectIds as $subjectId) {
            $relevantEnrollments = $enrollments->where('subject_id', $subjectId);
            foreach ($relevantEnrollments as $enrollment) {
                if (! $enrollment->created_at) {
                    continue;
                }

                $enrollmentCycle = $cycle ?? $enrollment->created_at->year;
                $startDate = AcademicCycle::startDate($enrollmentCycle);
                $cacheKey = "{$subjectId}_{$enrollmentCycle}_{$startDate->timestamp}";

                if (! isset($classCounts[$cacheKey])) {
                    $classCounts[$cacheKey] = ClassSession::where('subject_id', $subjectId)
                        ->where('unit', '!=', '0')
                        ->whereYear('date', $enrollmentCycle)
                        ->where('date', '>=', $startDate)
                        ->where('date', '<=', now())
                        ->count();
                }
            }
        }

        $results = [];
        foreach ($enrollments as $enrollment) {
            $enrollmentCycle = $cycle ?? $enrollment->created_at->year;
            $results[$enrollment->id] = $this->computeSingle(
                $enrollment,
                $allGrades->get($enrollment->user_id)?->get($enrollment->subject_id) ?? collect(),
                $classCounts,
                $enrollmentCycle
            );
        }

        return $isSingle ? $results[$enrollments->first()->id] : $results;
    }

    private function computeSingle(Enrollment $enrollment, Collection $grades, array $classCounts, int $cycle): array
    {
        if (! $enrollment->created_at) {
            return ['status' => 'Sin Fecha', 'color' => 'badge-ghost', 'attendance' => 0];
        }

        $startDate = AcademicCycle::startDate($cycle);
        $cacheKey = "{$enrollment->subject_id}_{$cycle}_{$startDate->timestamp}";
        $totalClasses = $classCounts[$cacheKey] ?? 0;

        if ($totalClasses === 0) {
            return ['status' => 'Sin Datos', 'color' => 'badge-ghost', 'attendance' => 0];
        }

        // Filtrar grados por ciclo y fecha de inicio en febrero (ya vienen filtrados por materia/usuario)
        $relevantGrades = $grades->filter(function ($grade) use ($startDate, $cycle) {
            return substr($grade->classSession->date, 0, 4) == $cycle && $grade->classSession->date >= $startDate;
        });

        $totalAttendance = $relevantGrades->sum('attendance');
        $attendancePercentage = ($totalAttendance / $totalClasses);

        $avgEv = $this->resolveEffectiveEvaluations($relevantGrades)->avg('grade') ?? 0;
        $avgTp = $relevantGrades->where('type', Grade::TYPE_PRACTICAL_WORK)->avg('grade') ?? 0;

        $isLibre = $attendancePercentage < self::PROMOTION_MIN_ATTENDANCE;
        $isPromovido = $attendancePercentage >= self::PROMOTION_MIN_ATTENDANCE
            && $avgEv >= self::PROMOTION_MIN_EV
            && $avgTp >= self::PROMOTION_MIN_TP;
        $isRegular = $attendancePercentage >= self::PROMOTION_MIN_ATTENDANCE
            && $avgEv >= self::REGULAR_MIN_EV
            && $avgTp >= self::REGULAR_MIN_TP;

        if ($isLibre) {
            return ['status' => 'Libre', 'color' => 'badge-error', 'attendance' => round($attendancePercentage)];
        }

        if ($isPromovido) {
            return ['status' => 'Promovido', 'color' => 'badge-success', 'attendance' => round($attendancePercentage)];
        }

        if ($isRegular) {
            return ['status' => 'Regular', 'color' => 'badge-info', 'attendance' => round($attendancePercentage)];
        }

        return ['status' => 'En Proceso', 'color' => 'badge-warning', 'attendance' => round($attendancePercentage)];
    }

    /**
     * Devuelve las calificaciones de evaluación efectivas, reemplazando la nota
     * de cada evaluación por la de su recuperatorio enlazado cuando exista.
     *
     * @param  Collection<int, Grade>  $grades
     * @param  callable|null  $isEvaluation  Predicado que determina qué grade cuenta como evaluación.
     * @return Collection<int, Grade>
     */
    public function resolveEffectiveEvaluations(Collection $grades, ?callable $isEvaluation = null): Collection
    {
        $isEvaluation ??= fn (Grade $grade) => $grade->isEvaluation();

        $evaluations = $grades->filter($isEvaluation);
        $recoveries = $grades->where('type', Grade::TYPE_RECOVERY)->keyBy('recovered_grade_id');

        $effective = $evaluations->map(function (Grade $grade) use ($recoveries) {
            if ($recovery = $recoveries->get($grade->id)) {
                return (clone $grade)->setAttribute('grade', $recovery->grade);
            }

            return $grade;
        });

        $covered = $evaluations->pluck('id');
        $orphans = $recoveries
            ->filter(fn (Grade $recovery) => ! $recovery->recovered_grade_id || ! $covered->contains($recovery->recovered_grade_id))
            ->values();

        return $effective->merge($orphans);
    }
}
