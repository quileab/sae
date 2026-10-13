<?php

namespace Tests\Unit\Services\Evaluations;

use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use App\Services\Evaluations\RubricResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One rule decides which rubric governs a question: the evaluation's current
 * rubric, except where an attempt already holds proposals measured against an
 * older one, which stay frozen so they remain auditable.
 */
class RubricResolverTest extends TestCase
{
    #[Test]
    public function it_reads_the_current_rubrics_from_the_evaluation(): void
    {
        $rubrics = RubricResolver::current($this->evaluation(['q1' => 1.0, 'q2' => 0.5]));

        $this->assertSame(['q1', 'q2'], array_keys($rubrics));
    }

    #[Test]
    public function an_attempt_without_proposals_adopts_a_rubric_saved_after_it_was_submitted(): void
    {
        $evaluation = $this->evaluation(['q1' => 1.0, 'q2' => 0.5]);
        $attempt = $this->attempt(frozen: ['q1' => 1.0], measuredBlocks: []);

        $this->assertSame(['q1', 'q2'], array_keys(RubricResolver::forAttempt($attempt, $evaluation)));
    }

    #[Test]
    public function an_attempt_keeps_its_frozen_rubric_where_proposals_were_already_measured(): void
    {
        $evaluation = $this->evaluation(['q1' => 1.0]);
        $attempt = $this->attempt(frozen: ['q1' => 0.5], measuredBlocks: ['q1']);

        $rubric = RubricResolver::forAttempt($attempt, $evaluation)['q1'];

        $this->assertSame(0.5, $rubric['criteria'][0]['points']);
    }

    /**
     * @param  array<string, float>  $rubricPoints  block id => points of its single criterion
     */
    private function evaluation(array $rubricPoints): OnlineEvaluation
    {
        return new OnlineEvaluation(['content' => ['sections' => [[
            'id' => 's1',
            'blocks' => $this->blocks($rubricPoints),
        ]]]]);
    }

    /**
     * @param  array<string, float>  $frozen
     * @param  array<int, string>  $measuredBlocks
     */
    private function attempt(array $frozen, array $measuredBlocks): EvaluationAttempt
    {
        $attempt = new EvaluationAttempt;
        $attempt->rubric_snapshot = collect($frozen)
            ->map(fn (float $points, string $id) => $this->rubric($points))
            ->all();
        $attempt->setRelation('gradingSuggestions', collect($measuredBlocks)
            ->map(fn (string $id) => (new GradingSuggestion)->forceFill(['block_id' => $id])));

        return $attempt;
    }

    /**
     * @param  array<string, float>  $rubricPoints
     * @return array<int, array<string, mixed>>
     */
    private function blocks(array $rubricPoints): array
    {
        return collect($rubricPoints)->map(fn (float $points, string $id) => [
            'id' => $id,
            'type' => 'open_text',
            'points' => $points,
            'rubric' => $this->rubric($points),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function rubric(float $points): array
    {
        return ['source' => 'agent', 'criteria' => [['key' => 'c1', 'label' => 'Criterio', 'points' => $points]]];
    }
}
