<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\EvaluationGradingMethod;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use Illuminate\Support\Collection;

/**
 * Reduces a student's graded attempts to a single final grade.
 *
 * Only attempts the teacher has already graded count: an attempt awaiting manual
 * grading has no trustworthy score yet.
 */
final class FinalGradeResolver
{
    /**
     * @param  Collection<int, EvaluationAttempt>  $attempts
     */
    public static function resolve(OnlineEvaluation $evaluation, Collection $attempts): ?float
    {
        $graded = $attempts
            ->filter(fn ($attempt) => $attempt->status === AttemptStatus::Graded)
            ->sortBy('attempt_number')
            ->values();

        if ($graded->isEmpty()) {
            return null;
        }

        $method = $evaluation->grading_method;

        return match ($method) {
            EvaluationGradingMethod::Average->value => round((float) $graded->avg('score'), 2),
            EvaluationGradingMethod::First->value => (float) $graded->first()->score,
            EvaluationGradingMethod::Last->value => (float) $graded->last()->score,
            default => (float) $graded->max('score'),
        };
    }
}
