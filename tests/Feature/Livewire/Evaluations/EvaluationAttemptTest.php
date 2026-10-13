<?php

namespace Tests\Feature\Livewire\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Slice 1: attempt numbering, the content snapshot and answer persistence.
 */
class EvaluationAttemptTest extends TestCase
{
    use RefreshDatabase;
    use VariantContent;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Base de Datos']);
    }

    #[Test]
    public function starting_an_attempt_stores_a_content_snapshot(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
        ]);

        $original = $evaluation->content;

        Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $attempt = EvaluationAttempt::where('user_id', $student->id)->firstOrFail();

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(
            self::canonical($original),
            self::canonical($attempt->content_snapshot),
            'The attempt must freeze the evaluation content it was graded against.'
        );
    }

    #[Test]
    public function starting_an_attempt_stores_the_questions_it_was_served(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
            'content' => $this->variantContent(),
        ]);

        Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $served = array_column($evaluation->content['sections'][0]['blocks'], 'id');

        $attempt = EvaluationAttempt::where('user_id', $student->id)->firstOrFail();
        $snapshotIds = array_column($attempt->content_snapshot['sections'][0]['blocks'], 'id');

        $this->assertCount(1, $snapshotIds, 'A drawn group serves one question per student.');
        $this->assertContains($snapshotIds[0], $served, 'And it must be one the student could have been given.');
        $this->assertCount(
            1,
            $attempt->rubric_snapshot,
            'Rubrics are frozen for what the student received, not for the alternatives they never saw.'
        );
        $this->assertSame(
            [$snapshotIds[0]],
            array_keys($attempt->rubric_snapshot),
            'The frozen rubric belongs to the served question.'
        );
    }

    #[Test]
    public function a_retry_draws_again_instead_of_repeating_the_first_paper(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
            'max_attempts' => 0,
            'content' => $this->variantContent(),
        ]);

        $served = [];

        for ($pass = 1; $pass <= 12; $pass++) {
            $attempt = EvaluationAttempt::where('user_id', $student->id)->orderByDesc('id')->first();

            if ($attempt === null || $attempt->isFinished()) {
                Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id])
                    ->call('submitAttempt');
            }

            $served[] = EvaluationAttempt::where('user_id', $student->id)
                ->orderByDesc('id')
                ->firstOrFail()
                ->content_snapshot['sections'][0]['blocks'][0]['id'];
        }

        // Twelve passes over four alternatives: getting the same one every time
        // would mean the draw is not happening at all.
        $this->assertGreaterThan(1, count(array_unique($served)), 'A retry must not be a rehearsal of the first paper.');
    }

    #[Test]
    public function a_second_evaluation_pass_grants_a_second_attempt_with_the_right_number(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->withAttemptsAllowed(2)->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
        ]);

        // First attempt, already graded.
        EvaluationAttempt::factory()->graded()->create([
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
        ]);

        Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $attempts = EvaluationAttempt::where('user_id', $student->id)
            ->where('online_evaluation_id', $evaluation->id)
            ->orderBy('attempt_number')
            ->get();

        $this->assertCount(2, $attempts);
        $this->assertSame(2, $attempts->last()->attempt_number);
        $this->assertSame(AttemptStatus::InProgress, $attempts->last()->status);
    }

    #[Test]
    public function an_in_progress_attempt_is_resumed_instead_of_duplicated(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->withAttemptsAllowed(3)->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
        ]);

        EvaluationAttempt::factory()->create([
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
            'answers' => ['whatever' => 'kept'],
        ]);

        Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $this->assertSame(1, EvaluationAttempt::where('user_id', $student->id)->count());
    }

    #[Test]
    public function save_progress_persists_answers(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
        ]);

        $component = Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $blockId = $evaluation->content['sections'][0]['blocks'][0]['id'];

        $component->set('answers', [$blockId => 'B'])
            ->call('saveProgress')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluation_attempts', [
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
            'status' => AttemptStatus::InProgress->value,
        ]);

        $attempt = EvaluationAttempt::where('user_id', $student->id)->first();
        $this->assertSame('B', $attempt->answers[$blockId]);
    }

    #[Test]
    public function submitting_scores_the_attempt_and_marks_it_graded_when_fully_automatic(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
            'content' => OnlineEvaluationFactoryContent::singleChoice('A'),
        ]);

        $component = Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);
        $blockId = $evaluation->content['sections'][0]['blocks'][0]['id'];

        $component->set('answers', [$blockId => 'A'])->call('submitAttempt');

        $attempt = EvaluationAttempt::where('user_id', $student->id)->firstOrFail();

        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertEquals(10.0, (float) $attempt->score);
        $this->assertNotNull($attempt->submitted_at);
    }

    #[Test]
    public function submitting_an_open_text_block_defers_grading_to_the_teacher(): void
    {
        $student = $this->enrolledStudent();
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
            'content' => OnlineEvaluationFactoryContent::openText(),
        ]);

        $component = Volt::actingAs($student)->test('evaluations.take', ['id' => $evaluation->id]);

        $component->set('answers', ['essay' => ['text' => 'Mi respuesta']])
            ->call('submitAttempt');

        $attempt = EvaluationAttempt::where('user_id', $student->id)->firstOrFail();

        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
    }

    #[Test]
    public function the_attempt_status_accessor_returns_the_enum(): void
    {
        $evaluation = OnlineEvaluation::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
        ]);

        $inProgress = EvaluationAttempt::factory()->create([
            'user_id' => $this->teacher()->id,
            'online_evaluation_id' => $evaluation->id,
        ]);
        $graded = EvaluationAttempt::factory()->graded()->create([
            'user_id' => $this->teacher()->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 2,
        ]);

        $this->assertSame(AttemptStatus::InProgress, $inProgress->status);
        $this->assertFalse($inProgress->isFinished());
        $this->assertSame('badge-ghost', $inProgress->status_badge);
        $this->assertSame('En curso', $inProgress->status_label);

        $this->assertSame(AttemptStatus::Graded, $graded->status);
        $this->assertTrue($graded->isFinished());
        $this->assertSame('badge-success', $graded->status_badge);
    }

    private function teacher(): User
    {
        return User::factory()->create(['role' => 'teacher']);
    }

    /**
     * MySQL normalises JSON object key order, so deep comparisons have to ignore it.
     */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);

        $result = array_map(self::canonical(...), $value);

        if (! $isList) {
            ksort($result);
        }

        return $result;
    }

    private function enrolledStudent(): User
    {
        $student = User::factory()->create(['role' => 'student']);

        $student->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);

        return $student;
    }
}

/**
 * One group offering four alternatives on the same theme, which is what a
 * teacher writes to stop two neighbours receiving the same paper.
 */
trait VariantContent
{
    /**
     * @return array<string, mixed>
     */
    private function variantContent(): array
    {
        return ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Unidad 1: Entidades',
            'draw' => 1,
            'blocks' => array_map(fn (int $number) => [
                'id' => 'v'.$number,
                'type' => 'open_text',
                'prompt' => 'Alternativa '.$number.'.',
                'points' => 1,
                'rubric' => [
                    'source' => 'teacher',
                    'criteria' => [
                        ['key' => 'c1', 'label' => 'Explica', 'points' => 1, 'levels' => []],
                    ],
                ],
            ], range(1, 4)),
        ]]];
    }
}

/**
 * Content fixtures that are awkward to express as factory states.
 */
final class OnlineEvaluationFactoryContent
{
    public static function singleChoice(string $correct): array
    {
        return [
            'sections' => [[
                'id' => 'sec_1',
                'title' => 'Sección',
                'blocks' => [[
                    'id' => 'blk_1',
                    'type' => 'multiple_choice',
                    'prompt' => 'Pregunta',
                    'points' => 1,
                    'options' => [
                        ['key' => 'A', 'text' => 'Correcta'],
                        ['key' => 'B', 'text' => 'Incorrecta'],
                    ],
                    'correct_option' => $correct,
                ]],
            ]],
        ];
    }

    public static function openText(): array
    {
        return [
            'sections' => [[
                'id' => 'sec_1',
                'title' => 'Sección',
                'blocks' => [[
                    'id' => 'essay',
                    'type' => 'open_text',
                    'prompt' => 'Desarrollá',
                    'points' => 10,
                ]],
            ]],
        ];
    }
}
