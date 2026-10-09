<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Slice 0: the grading panel is an overlay, not a replacement.
 *
 * The list and the KPIs stay rendered while grading, and the dialog carries
 * prev/next navigation in table order so the teacher never loses her place.
 */
class GradingOverlayTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    private User $teacher;

    /** @var array<int, EvaluationAttempt> */
    private array $attempts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 8901, 'career_id' => $career->id, 'name' => 'Gestión']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacher->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'content' => $this->content(),
        ]);

        foreach (['Uno', 'Dos', 'Tres'] as $lastname) {
            $this->attempts[] = EvaluationAttempt::create([
                'user_id' => User::factory()->create(['role' => 'student', 'lastname' => $lastname])->id,
                'online_evaluation_id' => $this->evaluation->id,
                'attempt_number' => 1,
                'status' => AttemptStatus::Submitted->value,
                'started_at' => now()->subHour(),
                'submitted_at' => now(),
                'block_scores' => ['q1' => 0],
                'score' => 0.0,
                'answers' => ['q1' => ['text' => 'Respuesta.']],
                'content_snapshot' => $this->evaluation->content,
                'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
            ]);
        }
    }

    #[Test]
    public function opening_grading_reports_position_and_neighbors_in_table_order(): void
    {
        $this->actingAs($this->teacher);
        [$first, $second, $third] = $this->attempts;

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $second->id)
            ->assertSet('gradingPosition', 2)
            ->assertSet('gradingTotal', 3)
            ->assertSet('gradingPrevId', $first->id)
            ->assertSet('gradingNextId', $third->id)
            ->assertSee('2 de 3');
    }

    #[Test]
    public function edges_have_no_neighbor_on_the_missing_side(): void
    {
        $this->actingAs($this->teacher);
        [$first, , $third] = $this->attempts;

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $first->id)
            ->assertSet('gradingPrevId', null)
            ->assertSet('gradingPosition', 1)
            ->assertSee('1 de 3');

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $third->id)
            ->assertSet('gradingNextId', null)
            ->assertSet('gradingPosition', 3)
            ->assertSee('3 de 3');
    }

    #[Test]
    public function navigating_keeps_the_dialog_open_on_the_new_attempt(): void
    {
        $this->actingAs($this->teacher);
        [, $second, $third] = $this->attempts;

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $second->id)
            ->call('openGrading', $third->id)
            ->assertSet('gradingAttemptId', $third->id)
            ->assertSet('gradingPosition', 3)
            ->assertSet('gradingPrevId', $second->id);
    }

    #[Test]
    public function the_list_and_kpis_stay_visible_while_grading(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempts[0]->id)
            ->assertSee('Alumnos corregidos')
            ->assertSee('Detalle de Intentos por Estudiante')
            ->assertSee('Corrigiendo:');
    }

    #[Test]
    public function closing_resets_the_navigation_state(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempts[0]->id)
            ->call('closeGrading')
            ->assertSet('gradingAttemptId', null)
            ->assertSet('gradingPrevId', null)
            ->assertSet('gradingNextId', null)
            ->assertSet('gradingPosition', null)
            ->assertSet('gradingTotal', null);
    }

    #[Test]
    public function a_blank_attempt_id_is_a_no_op(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', null)
            ->assertSet('gradingAttemptId', null);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(): array
    {
        return ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Parte 1',
            'blocks' => [[
                'id' => 'q1',
                'type' => 'open_text',
                'prompt' => 'Explique.',
                'points' => 1.5,
            ]],
        ]]];
    }
}
