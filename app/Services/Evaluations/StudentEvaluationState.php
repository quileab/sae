<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\EvaluationStatus;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides what a student should see for an evaluation: whether it is open, how
 * far along they are, and what they can do next.
 *
 * Keeping this out of the component means the same rules drive the list, the
 * card badges and the "can I still sit this" decision.
 */
class StudentEvaluationState
{
    public const UPCOMING = 'upcoming';

    public const AVAILABLE = 'available';

    public const IN_PROGRESS = 'in_progress';

    public const PENDING_GRADING = 'pending_grading';

    public const GRADED = 'graded';

    public const EXPIRED = 'expired';

    public const CLOSED = 'closed';

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     badge: string,
     *     actionable: bool,
     *     attempts_used: int,
     *     attempts_allowed: int,
     *     answered: int,
     *     total: int
     * }
     */
    public function describe(OnlineEvaluation $evaluation, User $student, ?EvaluationAttempt $latestAttempt = null): array
    {
        $attemptsUsed = (int) ($latestAttempt?->attempt_number ?? 0);
        $attemptsAllowed = (int) $evaluation->max_attempts;
        $remaining = $attemptsAllowed > 0 ? $attemptsAllowed - $attemptsUsed : null;

        $counts = $this->progress($evaluation, $latestAttempt);

        if ($latestAttempt && ! $latestAttempt->isFinished()) {
            return $this->state(
                self::IN_PROGRESS,
                'En curso',
                'badge-info',
                true,
                $attemptsUsed,
                $attemptsAllowed,
                $remaining,
                $counts
            );
        }

        if ($latestAttempt?->status === AttemptStatus::Submitted) {
            return $this->state(
                self::PENDING_GRADING,
                'Esperando corrección',
                'badge-warning',
                false,
                $attemptsUsed,
                $attemptsAllowed,
                $remaining,
                $counts
            );
        }

        if ($latestAttempt?->status === AttemptStatus::Graded) {
            $canRetry = $this->canRetry($evaluation, $attemptsUsed);

            return $this->state(
                self::GRADED,
                'Corregida',
                'badge-success',
                $canRetry,
                $attemptsUsed,
                $attemptsAllowed,
                $remaining,
                $counts
            );
        }

        // No usable attempt yet: the lifecycle status decides.
        if ($evaluation->status === EvaluationStatus::Draft) {
            return $this->state(self::CLOSED, 'No disponible', 'badge-ghost', false, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
        }

        if ($evaluation->status === EvaluationStatus::Closed) {
            return $this->state(self::CLOSED, 'Cerrada', 'badge-error', false, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
        }

        if ($evaluation->starts_at && now()->lt($evaluation->starts_at)) {
            return $this->state(self::UPCOMING, 'Próxima', 'badge-info', false, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
        }

        if ($evaluation->ends_at && now()->gt($evaluation->ends_at)) {
            return $this->state(self::EXPIRED, 'Vencida', 'badge-error', false, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
        }

        if ($remaining !== null && $remaining <= 0) {
            return $this->state(self::EXPIRED, 'Sin intentos', 'badge-error', false, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
        }

        return $this->state(self::AVAILABLE, 'Disponible', 'badge-primary', true, $attemptsUsed, $attemptsAllowed, $remaining, $counts);
    }

    /**
     * Published evaluations for the subjects the student is inscribed in,
     * each with its latest attempt already eager loaded.
     *
     * @return Collection<int, OnlineEvaluation>
     */
    public function forStudent(User $student)
    {
        $subjectIds = $student->enrollments()->pluck('subject_id');

        return OnlineEvaluation::query()
            ->whereIn('subject_id', $subjectIds)
            ->published()
            ->with(['subject', 'attempts' => fn ($query) => $query
                ->where('user_id', $student->id)
                ->orderByDesc('attempt_number'),
            ])
            ->orderByRaw('ends_at IS NULL, ends_at ASC')
            ->get();
    }

    private function canRetry(OnlineEvaluation $evaluation, int $attemptsUsed): bool
    {
        if ($evaluation->ends_at && now()->gt($evaluation->ends_at)) {
            return false;
        }

        return $evaluation->max_attempts <= 0 || $attemptsUsed < $evaluation->max_attempts;
    }

    /**
     * @return array{answered: int, total: int}
     */
    private function progress(OnlineEvaluation $evaluation, ?EvaluationAttempt $attempt): array
    {
        $total = 0;

        foreach ($evaluation->content['sections'] ?? [] as $section) {
            $total += count($section['blocks'] ?? []);
        }

        $blocksById = [];

        foreach ($evaluation->content['sections'] ?? [] as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                if (isset($block['id'])) {
                    $blocksById[$block['id']] = $block;
                }
            }
        }

        $answered = 0;

        foreach ($attempt?->answers ?? [] as $blockId => $answer) {
            if (! isset($blocksById[$blockId])) {
                continue;
            }

            if (EvaluationContent::isAnswered($blocksById[$blockId], $answer)) {
                $answered++;
            }
        }

        return ['answered' => $answered, 'total' => $total];
    }

    /**
     * @param  array{answered: int, total: int}  $counts
     * @return array<string, mixed>
     */
    private function state(
        string $key,
        string $label,
        string $badge,
        bool $actionable,
        int $attemptsUsed,
        int $attemptsAllowed,
        ?int $remaining,
        array $counts,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'badge' => $badge,
            'actionable' => $actionable,
            'attempts_used' => $attemptsUsed,
            'attempts_allowed' => $attemptsAllowed,
            'attempts_remaining' => $remaining,
            'answered' => $counts['answered'],
            'total' => $counts['total'],
        ];
    }
}
