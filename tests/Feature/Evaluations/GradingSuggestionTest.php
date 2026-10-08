<?php

namespace Tests\Feature\Evaluations;

use App\Enums\GradingFlag;
use App\Enums\GradingSuggestionState;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\Rubric;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A grading suggestion is a proposal, never a mark.
 *
 * These cover the two promises that keep the feature safe: the rubric an
 * assistant graded against is frozen with the attempt, and nothing the assistant
 * produces writes block_scores on its own.
 */
class GradingSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 2201, 'career_id' => $career->id, 'name' => 'Gestión']);
    }

    #[Test]
    public function starting_an_attempt_freezes_the_rubrics_alongside_the_content(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'content' => $this->contentWithRubric(),
        ]);

        $this->actingAs($student)->get("/evaluations/{$evaluation->id}/take")->assertOk();

        $attempt = EvaluationAttempt::sole();

        $this->assertSame(['q1'], array_keys($attempt->rubric_snapshot));
        $this->assertSame(
            'Distingue dato de información',
            $attempt->rubric_snapshot['q1']['criteria'][0]['label'],
        );
        $this->assertSame(
            $evaluation->content['sections'][0]['blocks'][0]['rubric']['source'],
            $attempt->rubric_snapshot['q1']['source'],
        );
    }

    #[Test]
    public function blocks_without_a_rubric_are_left_out_of_the_snapshot(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'content' => $this->contentWithRubric(withRubric: false),
        ]);

        $this->actingAs($student)->get("/evaluations/{$evaluation->id}/take")->assertOk();

        $this->assertSame([], EvaluationAttempt::sole()->rubric_snapshot);
    }

    #[Test]
    public function a_suggestion_never_writes_block_scores_or_the_attempt_score(): void
    {
        $attempt = $this->submittedAttempt();
        $before = ['block_scores' => $attempt->block_scores, 'score' => $attempt->score];

        GradingSuggestion::factory()->create([
            'evaluation_attempt_id' => $attempt->id,
            'block_id' => 'q1',
            'total_points' => 1.5,
        ]);

        $attempt->refresh();

        $this->assertSame($before['block_scores'], $attempt->block_scores);
        $this->assertSame($before['score'], (string) $attempt->score);
    }

    #[Test]
    public function it_keeps_one_live_suggestion_per_block(): void
    {
        $attempt = $this->submittedAttempt();

        GradingSuggestion::factory()->create(['evaluation_attempt_id' => $attempt->id, 'block_id' => 'q1']);

        $this->expectException(UniqueConstraintViolationException::class);

        GradingSuggestion::factory()->create(['evaluation_attempt_id' => $attempt->id, 'block_id' => 'q1']);
    }

    #[Test]
    public function it_resolves_its_state_flags_and_flags_known_enums_only(): void
    {
        $suggestion = GradingSuggestion::factory()
            ->forAttempt($this->submittedAttempt())
            ->create(['flags' => ['instruction_injection', 'inventado', 'excellent']]);

        $this->assertSame(GradingSuggestionState::Proposed, $suggestion->state);
        $this->assertSame('Propuesta', $suggestion->state_label);
        $this->assertTrue($suggestion->isPending());
        $this->assertSame(
            [GradingFlag::InstructionInjection, GradingFlag::Excellent],
            $suggestion->flagList(),
            'Unknown flag values are dropped rather than crashing the view.',
        );
        $this->assertTrue($suggestion->requiresAttention());
    }

    #[Test]
    public function an_excellent_answer_does_not_need_attention(): void
    {
        $suggestion = GradingSuggestion::factory()
            ->forAttempt($this->submittedAttempt())
            ->flagged(GradingFlag::Excellent)
            ->create();

        $this->assertFalse($suggestion->requiresAttention());
    }

    #[Test]
    public function settled_suggestions_drop_out_of_the_pending_queue(): void
    {
        $attempt = $this->submittedAttempt();

        GradingSuggestion::factory()->pending()->create(['evaluation_attempt_id' => $attempt->id, 'block_id' => 'q1']);
        GradingSuggestion::factory()->accepted()->create(['evaluation_attempt_id' => $attempt->id, 'block_id' => 'q2']);

        $this->assertSame(1, GradingSuggestion::pending()->count());
        $this->assertSame(2, $attempt->gradingSuggestions()->count());
    }

    #[Test]
    public function the_grading_modal_shows_the_rubric_the_attempt_was_served(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $content = $this->contentWithRubric();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
            'content' => $content,
        ]);

        $attempt = EvaluationAttempt::factory()->submitted()->create([
            'online_evaluation_id' => $evaluation->id,
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'answers' => ['q1' => ['text' => 'Un remito es un dato; el balance del mes es información.']],
            'content_snapshot' => $content,
            'rubric_snapshot' => Rubric::snapshotFor($content),
        ]);

        $this->actingAs($teacher);

        Volt::test('evaluations.results', ['id' => $evaluation->id])
            ->call('openGrading', $attempt->id)
            ->assertSee('Rúbrica')
            ->assertSee('Distingue dato de información')
            ->assertSee('propuesta por agente');
    }

    #[Test]
    public function a_rubric_that_does_not_add_up_is_surfaced_in_the_modal(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $content = ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Sección 1',
            'blocks' => [[
                'id' => 'q1',
                'type' => 'open_text',
                'prompt' => 'Explique el concepto.',
                'points' => 1.5,
                'rubric' => ['criteria' => [['key' => 'c1', 'label' => 'Criterio descuadrado', 'points' => 0.5]]],
            ]],
        ]]];

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
            'content' => $content,
        ]);

        $attempt = EvaluationAttempt::factory()->submitted()->create([
            'online_evaluation_id' => $evaluation->id,
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'content_snapshot' => $content,
            'rubric_snapshot' => Rubric::snapshotFor($content),
        ]);

        $this->actingAs($teacher);

        Volt::test('evaluations.results', ['id' => $evaluation->id])
            ->call('openGrading', $attempt->id)
            ->assertSee('Los criterios suman 0.50 y la pregunta vale 1.50.');
    }

    private function submittedAttempt(): EvaluationAttempt
    {
        return EvaluationAttempt::factory()->submitted()->create([
            'online_evaluation_id' => OnlineEvaluation::factory()->openFor()->create([
                'subject_id' => $this->subject->id,
                'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            ])->id,
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'block_scores' => ['q1' => 0.0],
            'score' => 0.0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentWithRubric(bool $withRubric = true): array
    {
        $block = ['id' => 'q1', 'type' => 'open_text', 'prompt' => 'Explique el concepto.', 'points' => 1.5];

        if ($withRubric) {
            $block['rubric'] = [
                'source' => 'agent',
                'criteria' => [
                    ['key' => 'c1', 'label' => 'Distingue dato de información', 'points' => 1.0],
                    ['key' => 'c2', 'label' => 'Aplica el proceso al caso', 'points' => 0.5],
                ],
            ];
        }

        return ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Sección 1',
            'blocks' => [$block],
        ]]];
    }
}
