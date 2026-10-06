<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\EvaluationAttempt;

/**
 * The single place where an attempt is turned into block scores and a final mark.
 *
 * Both the student submission flow and the teacher's manual grading funnel
 * through here, so the /10 normalisation can never drift between them.
 *
 * Grading always runs against the attempt's content snapshot. A teacher editing
 * the evaluation after students submitted must not change the denominator or
 * orphan the stored block scores.
 */
class AttemptGrader
{
    public const SCALE = 10;

    /**
     * @return array{
     *     block_scores: array<string, float>,
     *     score: float,
     *     max_score: float,
     *     status: AttemptStatus,
     *     requires_manual: bool
     * }
     */
    public function grade(array $content, array $answers): array
    {
        $blockScores = [];
        $rawScore = 0.0;
        $requiresManual = false;

        foreach ($this->blocks($content) as $block) {
            $blockId = $block['id'] ?? null;

            if ($blockId === null) {
                continue;
            }

            $result = EvaluationContent::gradeBlock($block, $answers[$blockId] ?? null);

            $blockScores[$blockId] = round((float) $result['score'], 2);
            $rawScore += $result['score'];
            $requiresManual = $requiresManual || $result['requires_manual'];
        }

        $maxScore = EvaluationContent::getMaxScore($content);

        return [
            'block_scores' => $blockScores,
            'score' => $this->normalize($rawScore, $maxScore),
            'max_score' => round($maxScore, 2),
            'status' => $requiresManual ? AttemptStatus::Submitted : AttemptStatus::Graded,
            'requires_manual' => $requiresManual,
        ];
    }

    /**
     * Auto-grade an attempt from the student's answers and persist the result.
     *
     * @param  array<string, mixed>  $answers
     * @return array{attempt: EvaluationAttempt, score: float, block_scores: array<string, float>, status: AttemptStatus, requires_manual: bool}
     */
    public function submit(EvaluationAttempt $attempt, array $answers): array
    {
        $result = $this->grade($this->contentFor($attempt), $answers);

        $attempt->update([
            'answers' => $answers,
            'status' => $result['status']->value,
            'submitted_at' => $attempt->submitted_at ?? now(),
            'score' => $result['score'],
            'block_scores' => $result['block_scores'],
        ]);

        return ['attempt' => $attempt, ...$result];
    }

    /**
     * Persist a teacher's manual marks, normalising them on the same scale.
     *
     * @param  array<string, float|int|string|null>  $blockScores
     */
    public function applyManualMarks(EvaluationAttempt $attempt, array $blockScores, ?string $feedback = null): EvaluationAttempt
    {
        $content = $this->contentFor($attempt);
        $earned = array_sum(array_map('floatval', $blockScores));

        $attempt->update([
            'block_scores' => $blockScores,
            'score' => $this->normalize($earned, EvaluationContent::getMaxScore($content)),
            'status' => AttemptStatus::Graded->value,
            'feedback' => $feedback,
        ]);

        return $attempt;
    }

    /**
     * The content this attempt must be graded against.
     *
     * @return array<string, mixed>
     */
    public function contentFor(EvaluationAttempt $attempt): array
    {
        if ($attempt->content_snapshot !== null) {
            return $attempt->content_snapshot;
        }

        return $attempt->evaluation?->content ?? [];
    }

    /**
     * Scale a raw point total onto the /10 reporting scale.
     */
    public function normalize(float $earned, float $maxScore): float
    {
        return $maxScore > 0 ? round(($earned / $maxScore) * self::SCALE, 2) : 0.0;
    }

    /**
     * Flatten every section into the list of blocks to grade.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blocks(array $content): array
    {
        $blocks = [];

        foreach (EvaluationContent::normalize($content)['sections'] ?? [] as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }
}
