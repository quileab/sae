<?php

namespace Tests\Feature\Livewire\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\EvaluationStatus;
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
 * Slice 1: the settings that silently never persisted, and the editor controls
 * that were bound to the wrong target.
 */
class EvaluationEditorTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Base de Datos']);
    }

    #[Test]
    public function the_model_persists_every_moodle_setting(): void
    {
        $evaluation = OnlineEvaluation::create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'title' => 'Test',
            'status' => EvaluationStatus::Draft->value,
            'duration_minutes' => 30,
            'passing_score' => 7.5,
            'max_attempts' => 3,
            'grading_method' => 'average',
            'shuffle_questions' => true,
            'content' => ['sections' => []],
        ]);

        $fresh = $evaluation->fresh();

        $this->assertEquals(7.5, (float) $fresh->passing_score);
        $this->assertSame(3, $fresh->max_attempts);
        $this->assertSame('average', $fresh->grading_method);
        $this->assertTrue($fresh->shuffle_questions);
    }

    #[Test]
    public function the_attempt_model_persists_the_attempt_number(): void
    {
        $evaluation = OnlineEvaluation::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
        ]);

        $attempt = EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(),
            'answers' => [],
        ]);

        $this->assertSame(2, $attempt->fresh()->attempt_number);
    }

    #[Test]
    public function the_editor_saves_the_advanced_settings_from_the_public_properties(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('title', 'Parcial 1')
            ->set('duration_minutes', 45)
            ->set('status', 'published')
            ->set('passing_score', 8)
            ->set('max_attempts', 4)
            ->set('grading_method', 'average')
            ->set('shuffle_questions', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('online_evaluations', [
            'subject_id' => $this->subject->id,
            'title' => 'Parcial 1',
            'duration_minutes' => 45,
            'status' => 'published',
            'passing_score' => 8,
            'max_attempts' => 4,
            'grading_method' => 'average',
            'shuffle_questions' => 1,
        ]);
    }

    #[Test]
    public function the_editor_rejects_an_out_of_range_passing_score(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('title', 'Parcial 1')
            ->set('passing_score', 15)
            ->call('save')
            ->assertHasErrors(['passing_score']);

        $this->assertDatabaseMissing('online_evaluations', ['title' => 'Parcial 1']);
    }

    #[Test]
    public function removing_an_option_clears_a_dangling_correct_answer(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        $component = Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('sections', self::singleChoiceSection('B'));

        // Remove option "B", which was the correct answer.
        $component->call('removeOption', 0, 0, 1);

        $this->assertNull($component->get('sections')[0]['blocks'][0]['correct_option']);
    }

    #[Test]
    public function removing_an_option_drops_it_from_the_checkbox_answer_key(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        $component = Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('sections', [[
                'id' => 'sec_1',
                'title' => 'Sección',
                'intro' => '',
                'blocks' => [[
                    'id' => 'blk_1',
                    'type' => 'checkboxes',
                    'prompt' => 'Pregunta',
                    'points' => 1,
                    'options' => [
                        ['key' => 'A', 'text' => 'A'],
                        ['key' => 'B', 'text' => 'B'],
                        ['key' => 'C', 'text' => 'C'],
                    ],
                    'correct_options' => ['A', 'B'],
                ]],
            ]]);

        $component->call('removeOption', 0, 0, 1);

        $this->assertSame(['A'], $component->get('sections')[0]['blocks'][0]['correct_options']);
    }

    #[Test]
    public function adding_options_after_removing_one_does_not_duplicate_a_key(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        $component = Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('sections', [[
                'id' => 'sec_1',
                'title' => 'Sección',
                'intro' => '',
                'blocks' => [[
                    'id' => 'blk_1',
                    'type' => 'multiple_choice',
                    'prompt' => 'Pregunta',
                    'points' => 1,
                    'options' => [
                        ['key' => 'A', 'text' => 'A'],
                        ['key' => 'B', 'text' => 'B'],
                        ['key' => 'C', 'text' => 'C'],
                    ],
                    'correct_option' => 'A',
                ]],
            ]]);

        // Drop the middle option, then add one back.
        $component->call('removeOption', 0, 0, 1)
            ->call('addOption', 0, 0);

        $keys = array_column($component->get('sections')[0]['blocks'][0]['options'], 'key');

        $this->assertSame($keys, array_unique($keys), 'Option keys must stay unique.');
        $this->assertSame(['A', 'C', 'B'], $keys);
    }

    #[Test]
    public function adding_an_option_when_all_options_were_removed_does_not_error(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        $component = Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('sections', self::singleChoiceSection('A'));

        $component->call('removeOption', 0, 0, 1)
            ->call('removeOption', 0, 0, 0)
            ->call('addOption', 0, 0);

        $options = $component->get('sections')[0]['blocks'][0]['options'];

        $this->assertCount(1, $options);
        $this->assertSame('A', $options[0]['key']);
    }

    #[Test]
    public function saving_normalises_the_content_shape(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        session(['current_subject_id' => $this->subject->id]);

        Volt::actingAs($teacher)
            ->test('evaluations.editor')
            ->set('title', 'Sin normalizar')
            ->set('sections', [[
                'id' => 'sec_1',
                'title' => 'Sección',
                'blocks' => [['type' => 'multiple_choice', 'prompt' => 'P']],
            ]])
            ->call('save');

        $saved = OnlineEvaluation::where('title', 'Sin normalizar')->firstOrFail();

        $block = $saved->content['sections'][0]['blocks'][0];

        $this->assertArrayHasKey('id', $block, 'normalize() must backfill the block id.');
        $this->assertSame(1, $block['points'], 'normalize() must backfill the default points.');
        $this->assertSame([], $block['options'], 'normalize() must backfill a multiple_choice options array.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function singleChoiceSection(string $correct): array
    {
        return [[
            'id' => 'sec_1',
            'title' => 'Sección',
            'intro' => '',
            'blocks' => [[
                'id' => 'blk_1',
                'type' => 'multiple_choice',
                'prompt' => 'Pregunta',
                'points' => 1,
                'options' => [
                    ['key' => 'A', 'text' => 'A'],
                    ['key' => 'B', 'text' => 'B'],
                ],
                'correct_option' => $correct,
                'explanation' => '',
            ]],
        ]];
    }
}
