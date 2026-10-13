<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use Illuminate\Support\Collection;

/**
 * Validates and previews a parsed assistant grading file against the cohort.
 *
 * Pure review: checks whether reference attempts exist, questions belong to the
 * evaluation, points are within range, and whether questions already carry marks.
 * Writes nothing to the database.
 */
class GradingReviewer
{
    /**
     * @param  Collection<int, EvaluationAttempt>  $attempts
     * @param  array<string, array<string, mixed>>  $blocks
     * @return array{
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     rows: array<int, array<string, mixed>>,
     *     rubric_rows: array<int, array<string, mixed>>,
     * }
     */
    public function review(
        OnlineEvaluation $evaluation,
        ParsedGradingFile $parsed,
        Collection $attempts,
        array $blocks,
    ): array {
        $messages = $parsed->errorMessages();

        if ($parsed->evaluationId() !== null && $parsed->evaluationId() !== $evaluation->id) {
            $messages[] = sprintf(
                'El archivo dice ser de la evaluación %d, no de la %d.',
                $parsed->evaluationId(),
                $evaluation->id,
            );
        }

        $attemptsByKey = $attempts->keyBy('id');
        $rows = [];

        foreach ($parsed->markRows as $index => $mark) {
            /** @var EvaluationAttempt|null $attempt */
            $attempt = $attemptsByKey->get((int) $mark['ref']);
            $blockPoints = (float) ($blocks[$mark['block']]['points'] ?? 0);

            $status = 'ok';
            $detail = '';

            if ($attempt === null) {
                $status = 'desconocido';
                $detail = 'No hay ningún intento con ese ref en esta evaluación.';
            } elseif (! array_key_exists($mark['block'], $blocks)) {
                $status = 'error';
                $detail = 'La pregunta no existe.';
            } elseif ($mark['points'] < 0 || $mark['points'] > $blockPoints) {
                $status = 'error';
                $detail = sprintf('Fuera de rango: la pregunta vale %.2f.', $blockPoints);
            } elseif ($this->alreadyMarked($attempt, $mark['block'])) {
                $status = 'sobrescribe';
                $detail = 'Ya tiene una nota cargada a mano.';
            }

            $rows[] = [
                'line' => $index + 1,
                'ref' => $mark['ref'],
                'student' => $attempt?->user?->fullname ?? '—',
                'block' => $mark['block'],
                'key' => $mark['key'] ?? '—',
                'points' => $mark['points'],
                'max' => $blockPoints,
                'flags' => array_map(fn ($flag) => $flag->value, $mark['flags']),
                'justification' => $mark['justification'],
                'status' => $status,
                'detail' => $detail,
            ];
        }

        $rubricRows = [];
        foreach ($parsed->rubricRows as $rIndex => $rRow) {
            $blockPoints = (float) ($blocks[$rRow['block']]['points'] ?? 0);
            $rubricRows[] = [
                'line' => $rIndex + 1,
                'block' => $rRow['block'],
                'key' => $rRow['key'] ?? '—',
                'label' => $rRow['label'] ?? '—',
                'points' => (float) ($rRow['points'] ?? 0),
                'levels' => $rRow['levels'] ?? [],
                'block_points' => $blockPoints,
            ];
        }

        return [
            'errors' => $messages,
            'warnings' => array_column($parsed->warnings, 'message'),
            'rows' => $rows,
            'rubric_rows' => $rubricRows,
        ];
    }

    /**
     * Whether a block already carries a mark entered or completed.
     */
    public function alreadyMarked(EvaluationAttempt $attempt, string $block): bool
    {
        if ($attempt->status !== AttemptStatus::Submitted) {
            return true;
        }

        return (float) ($attempt->block_scores[$block] ?? 0) > 0;
    }
}
