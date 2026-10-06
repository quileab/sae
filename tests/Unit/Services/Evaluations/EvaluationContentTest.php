<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\EvaluationContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EvaluationContentTest extends TestCase
{
    #[Test]
    public function a_correct_multiple_choice_answer_earns_full_points(): void
    {
        $block = ['id' => 'b1', 'type' => 'multiple_choice', 'points' => 4, 'correct_option' => 'B'];

        $this->assertSame(4.0, EvaluationContent::gradeBlock($block, 'B')['score']);
        $this->assertSame(0.0, EvaluationContent::gradeBlock($block, 'A')['score']);
        $this->assertFalse(EvaluationContent::gradeBlock($block, 'B')['requires_manual']);
    }

    #[Test]
    public function an_unanswered_multiple_choice_is_worth_zero(): void
    {
        $block = ['id' => 'b1', 'type' => 'multiple_choice', 'points' => 4, 'correct_option' => 'B'];

        $this->assertSame(0.0, EvaluationContent::gradeBlock($block, null)['score']);
    }

    #[Test]
    public function true_false_accepts_the_string_false(): void
    {
        $block = ['id' => 'b1', 'type' => 'true_false', 'points' => 1, 'correct_option' => 'false'];

        $this->assertSame(1.0, EvaluationContent::gradeBlock($block, 'false')['score']);
        $this->assertSame(0.0, EvaluationContent::gradeBlock($block, 'true')['score']);
    }

    #[Test]
    public function checkboxes_award_proportional_credit_and_penalise_wrong_picks(): void
    {
        $block = ['id' => 'b1', 'type' => 'checkboxes', 'points' => 4, 'correct_options' => ['A', 'B', 'C', 'D']];

        $this->assertSame(4.0, EvaluationContent::gradeBlock($block, ['A', 'B', 'C', 'D'])['score']);
        $this->assertSame(2.0, EvaluationContent::gradeBlock($block, ['A', 'B'])['score']);
        $this->assertSame(0.0, EvaluationContent::gradeBlock($block, [])['score']);
    }

    #[Test]
    public function checkboxes_never_score_below_zero(): void
    {
        $block = ['id' => 'b1', 'type' => 'checkboxes', 'points' => 4, 'correct_options' => ['A']];

        // One right plus two wrong nets negative, so the block floors at zero.
        $this->assertSame(0.0, EvaluationContent::gradeBlock($block, ['A', 'X', 'Y'])['score']);
    }

    #[Test]
    public function a_matching_block_awards_credit_per_correct_pair(): void
    {
        $block = [
            'id' => 'b1',
            'type' => 'matching',
            'points' => 4,
            'pairs' => [['prompt' => 'P1', 'answer' => 'A1'], ['prompt' => 'P2', 'answer' => 'A2']],
        ];

        $this->assertSame(4.0, EvaluationContent::gradeBlock($block, [0 => 'A1', 1 => 'A2'])['score']);
        $this->assertSame(2.0, EvaluationContent::gradeBlock($block, [0 => 'A1', 1 => 'wrong'])['score']);
    }

    #[Test]
    public function short_text_compares_case_and_whitespace_insensitively(): void
    {
        $block = ['id' => 'b1', 'type' => 'short_text', 'points' => 2, 'correct_answer' => 'Paris'];

        $this->assertSame(2.0, EvaluationContent::gradeBlock($block, '  PARIS ')['score']);
        $this->assertFalse(EvaluationContent::gradeBlock($block, 'paris')['requires_manual']);
    }

    #[Test]
    public function short_text_without_an_expected_answer_defers_to_manual_grading(): void
    {
        $block = ['id' => 'b1', 'type' => 'short_text', 'points' => 2];

        $result = EvaluationContent::gradeBlock($block, 'cualquier cosa');

        $this->assertSame(0.0, $result['score']);
        $this->assertTrue($result['requires_manual']);
    }

    #[Test]
    public function open_text_always_defers_to_manual_grading(): void
    {
        $block = ['id' => 'b1', 'type' => 'open_text', 'points' => 10];

        $result = EvaluationContent::gradeBlock($block, ['text' => 'mi respuesta', 'variant' => 'A']);

        $this->assertTrue($result['requires_manual']);
        $this->assertSame(0.0, $result['score']);
    }

    #[Test]
    public function an_unknown_block_type_defers_to_manual_grading_instead_of_throwing(): void
    {
        $result = EvaluationContent::gradeBlock(['id' => 'b1', 'type' => 'inventado', 'points' => 5], 'algo');

        $this->assertTrue($result['requires_manual']);
    }

    #[Test]
    public function normalize_wraps_legacy_flat_content_in_a_single_section(): void
    {
        $legacy = [['type' => 'multiple_choice', 'points' => 3]];

        $normalized = EvaluationContent::normalize($legacy);

        $this->assertCount(1, $normalized['sections']);
        $this->assertSame('Evaluación', $normalized['sections'][0]['title']);
        $this->assertCount(1, $normalized['sections'][0]['blocks']);
    }

    #[Test]
    public function normalize_backfills_the_required_shape_per_block_type(): void
    {
        $normalized = EvaluationContent::normalize([
            'sections' => [[
                'blocks' => [
                    ['type' => 'multiple_choice'],
                    ['type' => 'checkboxes'],
                    ['type' => 'table_design'],
                ],
            ]],
        ]);

        [$multiple, $checkboxes, $table] = $normalized['sections'][0]['blocks'];

        $this->assertArrayHasKey('id', $multiple);
        $this->assertSame(1, $multiple['points']);
        $this->assertSame([], $multiple['options']);
        $this->assertNull($multiple['correct_option']);

        $this->assertSame([], $checkboxes['correct_options']);

        $this->assertSame(['1:1', '1:N', 'N:N'], $table['relation_types']);
        $this->assertSame([], $table['tables']);
        $this->assertSame([], $table['relations']);
    }

    #[Test]
    public function normalize_preserves_existing_values(): void
    {
        $normalized = EvaluationContent::normalize([
            'sections' => [[
                'title' => 'Sección',
                'blocks' => [[
                    'id' => 'keep-me',
                    'type' => 'multiple_choice',
                    'points' => 7,
                    'correct_option' => 'C',
                ]],
            ]],
        ]);

        $block = $normalized['sections'][0]['blocks'][0];

        $this->assertSame('keep-me', $block['id']);
        $this->assertSame(7, $block['points']);
        $this->assertSame('C', $block['correct_option']);
    }

    #[Test]
    #[DataProvider('emptyAnswerCases')]
    public function empty_answers_score_zero_without_requesting_manual_review(mixed $answer): void
    {
        $result = EvaluationContent::gradeBlock(
            ['id' => 'b1', 'type' => 'multiple_choice', 'points' => 5, 'correct_option' => 'A'],
            $answer
        );

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['requires_manual']);
    }

    public static function emptyAnswerCases(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero string' => ['0'],
            'zero int' => [0],
            'false' => [false],
            'empty array' => [[]],
        ];
    }
}
