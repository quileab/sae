<?php

namespace App\Services\Evaluations;

use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;

/**
 * The single answer to "which rubric governs this question?".
 *
 * The rubric stored in the evaluation content is the source of truth. An attempt
 * only keeps its own frozen copy for the questions that already carry assistant
 * proposals: those numbers were produced against a specific rubric, and showing
 * them next to a different one would make them unauditable. Everywhere else the
 * attempt simply adopts the current rubric, since nothing was measured yet.
 */
final class RubricResolver
{
    /**
     * Rubrics currently defined on the evaluation, keyed by block id.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function current(OnlineEvaluation $evaluation): array
    {
        return Rubric::snapshotFor($evaluation->content ?? []);
    }

    /**
     * Rubrics that apply to one attempt, keyed by block id.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forAttempt(EvaluationAttempt $attempt, ?OnlineEvaluation $evaluation = null): array
    {
        $evaluation ??= $attempt->evaluation;

        $rubrics = $evaluation !== null ? self::current($evaluation) : [];

        $frozen = $attempt->rubric_snapshot ?? [];

        if ($frozen === []) {
            return $rubrics;
        }

        $measured = $attempt->gradingSuggestions->pluck('block_id');

        foreach ($measured as $blockId) {
            if (isset($frozen[$blockId])) {
                $rubrics[$blockId] = $frozen[$blockId];
            }
        }

        return $rubrics;
    }
}
