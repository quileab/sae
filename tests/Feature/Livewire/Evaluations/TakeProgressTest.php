<?php

namespace Tests\Feature\Livewire\Evaluations;

use App\Models\Career;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\EvaluationContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The take screen's navigator and progress counters.
 */
class TakeProgressTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Base de Datos']);

        $this->student = User::factory()->create(['role' => 'student']);
        $this->student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);
    }

    #[Test]
    public function the_navigator_numbers_every_block_across_sections(): void
    {
        $component = $this->takeComponent($this->twoSectionContent());

        $index = $component->instance()->questionIndex();

        $this->assertCount(3, $index);
        $this->assertSame([1, 2, 3], array_column($index, 'number'));
        $this->assertSame('Parte 1', $index[0]['section']);
        $this->assertSame('Parte 2', $index[1]['section']);
    }

    #[Test]
    public function answered_blocks_are_flagged_in_the_navigator(): void
    {
        $evaluation = $this->evaluation($this->twoSectionContent());
        $component = $this->takeComponent($this->twoSectionContent());

        $component->set('answers', ['b1' => 'A']);

        $index = $component->instance()->questionIndex();

        $this->assertTrue($index[0]['answered']);
        $this->assertFalse($index[1]['answered']);
        $this->assertFalse($index[2]['answered']);
    }

    #[Test]
    public function the_progress_counter_matches_the_navigator(): void
    {
        $component = $this->takeComponent($this->twoSectionContent());

        $this->assertSame(3, $component->instance()->totalBlocks());
        $this->assertSame(0, $component->instance()->answeredCount());

        $component->set('answers', ['b1' => 'A', 'b2' => 'algo']);

        $this->assertSame(2, $component->instance()->answeredCount());
    }

    #[Test]
    public function the_internal_metadata_key_never_counts_as_an_answer(): void
    {
        $component = $this->takeComponent($this->twoSectionContent());

        $component->set('answers', ['_metadata' => ['shuffled_content' => []]]);

        $this->assertSame(0, $component->instance()->answeredCount());
    }

    #[Test]
    public function an_evaluation_with_no_questions_reports_zero_blocks(): void
    {
        $component = $this->takeComponent(['sections' => []]);

        $this->assertSame(0, $component->instance()->totalBlocks());
        $this->assertSame(0, $component->instance()->answeredCount());
    }

    #[Test]
    #[DataProvider('answerShapes')]
    public function answered_detection_handles_every_block_shape(array $block, mixed $answer, bool $expected): void
    {
        $this->assertSame($expected, EvaluationContent::isAnswered($block, $answer));
    }

    public static function answerShapes(): array
    {
        return [
            'multiple choice answered' => [['type' => 'multiple_choice'], 'A', true],
            'multiple choice empty' => [['type' => 'multiple_choice'], null, false],
            'open text with text' => [['type' => 'open_text'], ['text' => 'respuesta'], true],
            'open text with only a variant' => [['type' => 'open_text'], ['variant' => 'A'], true],
            'open text empty' => [['type' => 'open_text'], ['text' => ''], false],
            'short text' => [['type' => 'short_text'], 'x', true],
            'true false answered' => [['type' => 'true_false'], 'false', true],
            'checkboxes with picks' => [['type' => 'checkboxes'], ['A'], true],
            'checkboxes empty' => [['type' => 'checkboxes'], [], false],
            'matching partly filled' => [['type' => 'matching'], ['A1', ''], true],
            'matching blank' => [['type' => 'matching'], ['' => ''], false],
            'matching empty' => [['type' => 'matching'], [], false],
            'table design with tables' => [['type' => 'table_design'], ['tables' => [['name' => 'X']]], true],
            'table design with only relations' => [['type' => 'table_design'], ['relations' => [['from' => 'A']]], true],
            'table design empty' => [['type' => 'table_design'], ['tables' => [], 'relations' => []], false],
            'unknown type' => [['type' => 'raro'], 'algo', true],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function evaluation(array $content): OnlineEvaluation
    {
        return OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'content' => $content,
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function takeComponent(array $content)
    {
        return Volt::actingAs($this->student)
            ->test('evaluations.take', ['id' => $this->evaluation($content)->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function twoSectionContent(): array
    {
        return [
            'sections' => [
                [
                    'id' => 'sec_1',
                    'title' => 'Parte 1',
                    'intro' => '',
                    'blocks' => [[
                        'id' => 'b1',
                        'type' => 'multiple_choice',
                        'prompt' => 'Uno',
                        'points' => 1,
                        'options' => [['key' => 'A', 'text' => 'a']],
                        'correct_option' => 'A',
                    ]],
                ],
                [
                    'id' => 'sec_2',
                    'title' => 'Parte 2',
                    'intro' => '',
                    'blocks' => [
                        ['id' => 'b2', 'type' => 'short_text', 'prompt' => 'Dos', 'points' => 1],
                        ['id' => 'b3', 'type' => 'open_text', 'prompt' => 'Tres', 'points' => 1],
                    ],
                ],
            ],
        ];
    }
}
