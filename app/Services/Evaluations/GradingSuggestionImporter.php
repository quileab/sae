<?php

namespace App\Services\Evaluations;

use App\Enums\GradingFlag;
use App\Enums\GradingSuggestionState;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;

/**
 * Turns a filled grading file into proposals a teacher can review.
 *
 * The one rule this service exists to keep: it only ever writes
 * grading_suggestions. block_scores and score stay untouched, so no assistant
 * output can become a mark no matter what arrives here.
 *
 * Re-importing the same file is a no-op. A different file for the same block
 * overwrites in place and pushes the previous payload into history, so the
 * revision count says whether the model was rerun.
 */
final class GradingSuggestionImporter
{
    public const AGENT_FALLBACK = 'sin identificar';

    /**
     * @return array{created: int, updated: int, unchanged: int, rejected: array<int, string>}
     */
    public function import(OnlineEvaluation $evaluation, ParsedGradingFile $parsed): array
    {
        $report = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => []];

        if (! $parsed->isValid() || $parsed->markRows === []) {
            return $report;
        }

        $attempts = EvaluationAttempt::with('user')
            ->where('online_evaluation_id', $evaluation->id)
            ->whereIn('id', $this->attemptIds($parsed->markRows))
            ->get()
            ->keyBy('id');

        $rubrics = $this->rubrics($evaluation);
        $blocks = GradingFileBuilder::gradableBlocksIn($this->content($evaluation));
        $grouped = $this->groupByAttemptAndBlock($parsed->markRows);

        foreach ($grouped as $group) {
            $attemptId = $group['attempt'];
            $blockId = $group['block'];
            $rows = $group['rows'];
            $ref = $group['ref'];
            $attempt = $attempts->get($attemptId);

            if ($attempt === null) {
                $report['rejected'][] = sprintf('ref %s no pertenece a esta evaluación.', $ref);

                continue;
            }

            if (! array_key_exists($blockId, $blocks)) {
                $report['rejected'][] = sprintf('la pregunta %s de %s no es de desarrollo.', $blockId, $ref);

                continue;
            }

            $outcome = $this->store($attempt, $blockId, $rows, $rubrics[$blockId] ?? null, (float) $blocks[$blockId]['points']);

            $report[$outcome]++;
        }

        return $report;
    }

    /**
     * Store a proposal and its rubric together.
     *
     * @return array{created: int, updated: int, unchanged: int, rejected: array<int, string>}
     */
    public function applyRubric(OnlineEvaluation $evaluation, ParsedGradingFile $parsed): array
    {
        $report = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => []];

        if (! $parsed->isValid() || $parsed->rubricRows === []) {
            return $report;
        }

        $blocks = GradingFileBuilder::gradableBlocksIn($this->content($evaluation));
        $byBlock = [];

        foreach ($parsed->rubricRows as $row) {
            $byBlock[$row['block']][] = $row;
        }

        $content = $this->content($evaluation);
        $changed = false;

        foreach ($byBlock as $blockId => $rows) {
            if (! array_key_exists($blockId, $blocks)) {
                $report['rejected'][] = sprintf('la pregunta %s no existe o no es de desarrollo.', $blockId);

                continue;
            }

            $rubric = Rubric::make([
                'source' => Rubric::SOURCE_AGENT,
                'criteria' => array_map(fn (array $row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'points' => $row['points'],
                ], $rows),
            ]);

            // A rubric that does not add up would make every later mark
            // unauditable, so it is refused rather than stored.
            $problems = $rubric->problems((float) $blocks[$blockId]['points']);

            if ($problems !== []) {
                $report['rejected'][] = sprintf('%s: %s', $blockId, implode(' ', $problems));

                continue;
            }

            if ($this->attachRubric($content, $blockId, $rubric->toArray())) {
                $changed = true;
                $report['created']++;
            } else {
                $report['unchanged']++;
            }
        }

        // Attempt snapshots are deliberately left alone: RubricResolver lets
        // attempts without proposals adopt this rubric and keeps the frozen
        // copy only where proposals were already measured against an older one.
        if ($changed) {
            $evaluation->update(['content' => $content]);
        }

        return $report;
    }

    /**
     * Rows for one attempt and one question collapse into a single proposal.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function store(EvaluationAttempt $attempt, string $blockId, array $rows, ?array $rubric, float $blockPoints): string
    {
        $rubric = $rubric === null ? null : Rubric::make($rubric);
        $maxima = [];

        if ($rubric !== null) {
            foreach ($rubric->criteria as $criterion) {
                $maxima[$criterion['key']] = $criterion['points'];
            }
        }

        $criteria = [];
        $flags = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $total += $row['points'];

            if ($row['key'] === null) {
                $criteria[] = [
                    'key' => null,
                    'label' => 'Valoración general',
                    'points_awarded' => round($row['points'], 2),
                    'points_max' => $blockPoints,
                    'justification' => $row['justification'],
                ];

                continue;
            }

            $flags = [...$flags, ...array_map(
                fn (GradingFlag $flag) => $flag->value,
                $row['flags'],
            )];
            $criteria[] = [
                'key' => $row['key'],
                'label' => $this->labelFor($rubric, $row['key']),
                'points_awarded' => round($row['points'], 2),
                'points_max' => $maxima[$row['key']] ?? null,
                'justification' => $row['justification'],
            ];
        }

        $payload = [
            'criteria' => $criteria,
            'total_points' => round($total, 2),
            'max_points' => $blockPoints,
            'flags' => array_values(array_unique($flags)),
            'rubric_version' => $rubric?->version ?? 1,
            'rubric_hash' => $rubric?->hash(),
        ];

        $existing = GradingSuggestion::where('evaluation_attempt_id', $attempt->id)
            ->where('block_id', $blockId)
            ->first();

        if ($existing === null) {
            GradingSuggestion::create($payload + [
                'evaluation_attempt_id' => $attempt->id,
                'block_id' => $blockId,
                'state' => GradingSuggestionState::Proposed->value,
                'revision' => 1,
                'history' => [],
                'agent' => self::AGENT_FALLBACK,
            ]);

            return 'created';
        }

        if ($this->samePayload($existing, $payload)) {
            return 'unchanged';
        }

        $history = [...($existing->history ?? []), [
            'revision' => $existing->revision,
            'state' => $existing->state->value,
            'total_points' => $existing->total_points,
            'criteria' => $existing->criteria,
            'flags' => $existing->flags,
            // Carried forward because a rejection reason is the only record of
            // *why* the teacher disagreed, which is what tells us a rubric
            // criterion is being misapplied.
            'disagreement_reason' => $existing->disagreement_reason,
            'settled_at' => $existing->updated_at?->toIso8601String(),
        ]];

        $existing->update($payload + [
            'revision' => $existing->revision + 1,
            'history' => $history,
            // A proposal that had been settled goes back for review: the model
            // was rerun and the teacher never saw this number.
            'state' => GradingSuggestionState::Proposed->value,
            'final_points' => null,
            'disagreement_reason' => null,
        ]);

        return 'updated';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function samePayload(GradingSuggestion $existing, array $payload): bool
    {
        return $existing->total_points == $payload['total_points']
            && $existing->rubric_hash === $payload['rubric_hash']
            && $existing->flags == $payload['flags']
            && $existing->criteria == $payload['criteria'];
    }

    private function labelFor(?Rubric $rubric, string $key): string
    {
        foreach ($rubric?->criteria ?? [] as $criterion) {
            if ($criterion['key'] === $key) {
                return $criterion['label'];
            }
        }

        return $key;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{attempt: int, block: string, ref: string, rows: array<int, array<string, mixed>>}>
     */
    private function groupByAttemptAndBlock(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $attemptId = (int) $row['ref'];
            $identity = $attemptId.'/'.$row['block'];

            $grouped[$identity] ??= [
                'attempt' => $attemptId,
                'block' => $row['block'],
                'ref' => $row['ref'],
                'rows' => [],
            ];

            $grouped[$identity]['rows'][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function attemptIds(array $rows): array
    {
        return array_values(array_unique(array_map(
            fn (array $row) => (int) $row['ref'],
            $rows,
        )));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rubrics(OnlineEvaluation $evaluation): array
    {
        return RubricResolver::current($evaluation);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(OnlineEvaluation $evaluation): array
    {
        return $evaluation->content ?? [];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed> the content, modified in place
     */
    private function attachRubric(array &$content, string $blockId, array $rubric): bool
    {
        $changed = false;

        foreach ($content['sections'] ?? [] as $sIndex => $section) {
            foreach ($section['blocks'] ?? [] as $bIndex => $block) {
                if (($block['id'] ?? null) !== $blockId) {
                    continue;
                }

                if (($content['sections'][$sIndex]['blocks'][$bIndex]['rubric'] ?? null) == $rubric) {
                    return false;
                }

                $content['sections'][$sIndex]['blocks'][$bIndex]['rubric'] = $rubric;
                $changed = true;
            }
        }

        return $changed;
    }
}
