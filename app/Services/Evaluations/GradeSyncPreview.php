<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\EvaluationAttempt;
use App\Models\Grade;
use App\Models\OnlineEvaluation;
use Illuminate\Support\Collection;

/**
 * Computes the final grade per student and describes what would change in the
 * subject gradebook, without touching the database.
 *
 * The gradebook holds manual evaluation notes that teachers type by hand, so a
 * blind `updateOrCreate` would silently overwrite real marks. This service
 * classifies every row instead: automatic writes, rows that already match, and
 * manual rows that need an explicit opt-in.
 */
class GradeSyncPreview
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_UNCHANGED = 'unchanged';

    public const ACTION_CONFLICT = 'conflict_manual';

    public const ACTION_SKIPPED = 'skipped';

    /**
     * Marker written into `grades.comments` for rows this engine owns.
     */
    public const PROVENANCE_PREFIX = 'Evaluación Online:';

    /**
     * @return array<int, array{
     *     action: string,
     *     user_id: int,
     *     user_name: string,
     *     grade_id: int|null,
     *     current_grade: float|null,
     *     current_comments: string|null,
     *     proposed_grade: float|null
     * }>
     */
    public function build(OnlineEvaluation $evaluation): array
    {
        if (! $evaluation->class_session_id) {
            return [];
        }

        $attempts = EvaluationAttempt::where('online_evaluation_id', $evaluation->id)
            ->whereIn('status', [
                AttemptStatus::Submitted->value,
                AttemptStatus::Graded->value,
            ])
            ->get()
            ->groupBy('user_id');

        if ($attempts->isEmpty()) {
            return [];
        }

        $existing = $this->existingGrades($evaluation);

        $rows = [];

        foreach ($attempts as $userId => $userAttempts) {
            $finalGrade = FinalGradeResolver::resolve($evaluation, $userAttempts);

            if ($finalGrade === null) {
                // Nothing graded yet: the teacher has to grade it first.
                $rows[] = $this->row(self::ACTION_SKIPPED, $userAttempts, null);

                continue;
            }

            $rows[] = $this->classify($evaluation, $userAttempts, $existing[$userId] ?? null, $finalGrade);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, EvaluationAttempt>  $userAttempts
     * @return array<string, mixed>
     */
    private function classify(OnlineEvaluation $evaluation, Collection $userAttempts, ?Grade $existing, float $finalGrade): array
    {
        if ($existing === null) {
            return $this->row(self::ACTION_CREATE, $userAttempts, null, $finalGrade);
        }

        $isOurs = str_starts_with((string) $existing->comments, self::PROVENANCE_PREFIX);

        if (! $isOurs) {
            // A note a teacher typed by hand. Never overwrite without consent.
            return $this->row(self::ACTION_CONFLICT, $userAttempts, $existing, $finalGrade);
        }

        $alreadyMatches = (float) $existing->grade === $finalGrade
            && (bool) $existing->approved === ($finalGrade >= $evaluation->passing_score);

        return $this->row(
            $alreadyMatches ? self::ACTION_UNCHANGED : self::ACTION_UPDATE,
            $userAttempts,
            $existing,
            $finalGrade
        );
    }

    /**
     * @param  Collection<int, EvaluationAttempt>  $userAttempts
     * @return array<string, mixed>
     */
    private function row(string $action, Collection $userAttempts, ?Grade $existing, ?float $finalGrade = null): array
    {
        $attempt = $userAttempts->first();

        return [
            'action' => $action,
            'user_id' => (int) ($attempt->user_id ?? 0),
            'user_name' => $this->userName($attempt),
            'grade_id' => $existing?->id,
            'current_grade' => $existing !== null ? (float) $existing->grade : null,
            'current_comments' => $existing?->comments,
            'proposed_grade' => $finalGrade,
        ];
    }

    private function userName(?EvaluationAttempt $attempt): string
    {
        $user = $attempt?->user;

        return $user?->fullname ?? 'Alumno '.$attempt?->user_id;
    }

    /**
     * @return Collection<int, Grade>
     */
    private function existingGrades(OnlineEvaluation $evaluation): Collection
    {
        return Grade::where('class_session_id', $evaluation->class_session_id)
            ->where('type', Grade::TYPE_EVALUATION)
            ->whereIn('user_id', $this->candidateUserIds($evaluation))
            ->get()
            ->keyBy('user_id');
    }

    /**
     * @return array<int, int>
     */
    private function candidateUserIds(OnlineEvaluation $evaluation): array
    {
        return EvaluationAttempt::where('online_evaluation_id', $evaluation->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
