<?php

namespace Tests\Unit\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Services\Evaluations\AttemptGrader;
use App\Services\Evaluations\EvaluationContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttemptGraderTest extends TestCase
{
    private AttemptGrader $grader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grader = new AttemptGrader;
    }

    #[Test]
    public function it_normalizes_the_raw_total_onto_a_ten_point_scale(): void
    {
        $content = self::content([
            self::block('b1', 'multiple_choice', 3, ['correct_option' => 'A']),
            self::block('b2', 'multiple_choice', 7, ['correct_option' => 'A']),
        ]);

        $result = $this->grader->grade($content, ['b1' => 'A', 'b2' => 'A']);

        $this->assertSame(10.0, $result['score']);
        $this->assertSame(10.0, $result['max_score']);
        $this->assertSame(['b1' => 3.0, 'b2' => 7.0], $result['block_scores']);
    }

    #[Test]
    public function partial_answers_scale_proportionally(): void
    {
        $content = self::content([
            self::block('b1', 'multiple_choice', 3, ['correct_option' => 'A']),
            self::block('b2', 'multiple_choice', 7, ['correct_option' => 'A']),
        ]);

        $result = $this->grader->grade($content, ['b1' => 'A', 'b2' => 'B']);

        $this->assertSame(3.0, $result['score'], '3 of 10 raw points is a 3.0 mark.');
    }

    #[Test]
    public function an_empty_evaluation_scores_zero_rather_than_dividing_by_zero(): void
    {
        $result = $this->grader->grade(['sections' => []], []);

        $this->assertSame(0.0, $result['score']);
        $this->assertSame(0.0, $result['max_score']);
        $this->assertSame([], $result['block_scores']);
    }

    #[Test]
    public function an_open_text_block_defers_the_attempt_to_manual_grading(): void
    {
        $content = self::content([
            self::block('b1', 'multiple_choice', 5, ['correct_option' => 'A']),
            self::block('b2', 'open_text', 5),
        ]);

        $result = $this->grader->grade($content, ['b1' => 'A', 'b2' => ['text' => 'respuesta']]);

        $this->assertTrue($result['requires_manual']);
        $this->assertSame(AttemptStatus::Submitted, $result['status']);
        $this->assertSame(5.0, $result['score'], 'The auto-graded half still scores.');
    }

    #[Test]
    public function a_fully_automatic_evaluation_is_graded_immediately(): void
    {
        $content = self::content([
            self::block('b1', 'multiple_choice', 5, ['correct_option' => 'A']),
        ]);

        $result = $this->grader->grade($content, ['b1' => 'A']);

        $this->assertFalse($result['requires_manual']);
        $this->assertSame(AttemptStatus::Graded, $result['status']);
    }

    #[Test]
    public function it_grades_blocks_across_every_section(): void
    {
        $content = [
            'sections' => [
                ['blocks' => [self::block('b1', 'multiple_choice', 2, ['correct_option' => 'A'])]],
                ['blocks' => [self::block('b2', 'multiple_choice', 2, ['correct_option' => 'B'])]],
            ],
        ];

        $result = $this->grader->grade($content, ['b1' => 'A', 'b2' => 'B']);

        $this->assertSame(['b1' => 2.0, 'b2' => 2.0], $result['block_scores']);
        $this->assertSame(10.0, $result['score']);
    }

    #[Test]
    public function it_ignores_answers_for_blocks_that_no_longer_exist(): void
    {
        $content = self::content([self::block('b1', 'multiple_choice', 10, ['correct_option' => 'A'])]);

        $result = $this->grader->grade($content, ['b1' => 'A', 'ghost' => 'Z']);

        $this->assertSame(['b1' => 10.0], $result['block_scores']);
    }

    #[Test]
    public function it_normalizes_the_internal_metadata_key_out_of_the_grade(): void
    {
        // take.blade.php stashes the shuffled content under answers._metadata.
        $content = self::content([self::block('b1', 'multiple_choice', 10, ['correct_option' => 'A'])]);

        $result = $this->grader->grade($content, [
            '_metadata' => ['shuffled_content' => $content],
            'b1' => 'A',
        ]);

        $this->assertSame(['b1' => 10.0], $result['block_scores']);
    }

    #[Test]
    #[DataProvider('normalizationCases')]
    public function it_normalizes(float $earned, float $max, float $expected): void
    {
        $this->assertSame($expected, $this->grader->normalize($earned, $max));
    }

    public static function normalizationCases(): array
    {
        return [
            'full marks' => [10.0, 10.0, 10.0],
            'half marks' => [5.0, 10.0, 5.0],
            'nothing' => [0.0, 10.0, 0.0],
            'over-earning is clamped by the caller' => [15.0, 10.0, 15.0],
            'zero denominator' => [5.0, 0.0, 0.0],
            'fractional points' => [1.5, 4.0, 3.75],
        ];
    }

    #[Test]
    public function it_flattens_blocks_across_sections(): void
    {
        $blocks = $this->grader->blocks([
            'sections' => [
                ['blocks' => [self::block('a', 'multiple_choice', 1)]],
                ['blocks' => [self::block('b', 'multiple_choice', 1), self::block('c', 'true_false', 1)]],
            ],
        ]);

        $this->assertSame(['a', 'b', 'c'], array_column($blocks, 'id'));
    }

    #[Test]
    public function it_tolerates_legacy_content_without_sections(): void
    {
        $legacy = [self::block('b1', 'multiple_choice', 10, ['correct_option' => 'A'])];

        $result = $this->grader->grade($legacy, ['b1' => 'A']);

        $this->assertSame(10.0, $result['score']);
    }

    #[Test]
    public function the_max_score_matches_the_sum_of_block_points(): void
    {
        $content = self::content([
            self::block('b1', 'multiple_choice', 2.5),
            self::block('b2', 'open_text', 7.5),
        ]);

        $this->assertSame(10.0, EvaluationContent::getMaxScore($content));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function block(string $id, string $type, float $points, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'prompt' => 'P',
            'points' => $points,
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function content(array $blocks): array
    {
        return ['sections' => [['id' => 'sec_1', 'title' => 'S', 'blocks' => $blocks]]];
    }
}
