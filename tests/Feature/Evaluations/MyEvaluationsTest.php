<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\StudentEvaluationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /evaluations branches by role: staff manage, students see their own list.
 */
class MyEvaluationsTest extends TestCase
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
    public function a_student_reaches_their_own_list(): void
    {
        $this->actingAs($this->student)
            ->get('/evaluations')
            ->assertOk()
            ->assertSee('Mis Evaluaciones')
            ->assertDontSee('Nueva Evaluación');
    }

    #[Test]
    public function a_student_only_sees_published_evaluations_of_their_subjects(): void
    {
        $this->publishedEvaluation('Visible');
        $this->draftEvaluation('Borrador oculto');

        $otherSubject = Subject::create(['id' => 1102, 'career_id' => $this->subject->career_id, 'name' => 'Otra']);

        OnlineEvaluation::factory()->published()->create([
            'subject_id' => $otherSubject->id,
            'teacher_id' => $this->teacherId(),
        ]);

        $this->actingAs($this->student)
            ->get('/evaluations')
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Borrador oculto')
            ->assertDontSee('Otra');
    }

    #[Test]
    public function an_available_evaluation_is_actionable(): void
    {
        $evaluation = $this->publishedEvaluation('Disponible ahora', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::AVAILABLE, $state['key']);
        $this->assertTrue($state['actionable']);
    }

    #[Test]
    public function an_upcoming_evaluation_is_not_actionable(): void
    {
        $evaluation = $this->publishedEvaluation('Todavía no', [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::UPCOMING, $state['key']);
        $this->assertFalse($state['actionable']);
    }

    #[Test]
    public function an_expired_evaluation_is_not_actionable(): void
    {
        $evaluation = $this->publishedEvaluation('Vencida', [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::EXPIRED, $state['key']);
        $this->assertFalse($state['actionable']);
    }

    #[Test]
    public function an_in_progress_attempt_is_resumable(): void
    {
        $evaluation = $this->publishedEvaluation('En curso', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);

        EvaluationAttempt::factory()->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
            'answers' => ['b1' => 'A'],
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::IN_PROGRESS, $state['key']);
        $this->assertTrue($state['actionable']);
        $this->assertSame(1, $state['answered']);
        $this->assertSame(1, $state['total']);
    }

    #[Test]
    public function an_attempt_awaiting_grading_is_not_actionable_but_shows_results(): void
    {
        $evaluation = $this->publishedEvaluation('Esperando', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);

        EvaluationAttempt::factory()->submitted(7)->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::PENDING_GRADING, $state['key']);
        $this->assertFalse($state['actionable']);

        $this->actingAs($this->student)
            ->get("/evaluations/{$evaluation->id}/results")
            ->assertOk()
            ->assertSee('Esperando corrección');
    }

    #[Test]
    public function a_graded_attempt_shows_the_mark(): void
    {
        $evaluation = $this->publishedEvaluation('Corregida', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);

        EvaluationAttempt::factory()->graded(8.5)->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertSame(StudentEvaluationState::GRADED, $state['key']);

        $this->actingAs($this->student)
            ->get('/evaluations')
            ->assertOk()
            ->assertSee('8.50');
    }

    #[Test]
    public function a_graded_evaluation_cannot_be_retried_once_the_attempts_run_out(): void
    {
        $evaluation = $this->publishedEvaluation('Sin reintentos', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
            'max_attempts' => 1,
        ]);

        EvaluationAttempt::factory()->graded(9)->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
        ]);

        $this->assertFalse($this->stateFor($evaluation)['actionable']);
    }

    #[Test]
    public function a_graded_evaluation_offers_a_retry_while_attempts_remain(): void
    {
        $evaluation = $this->publishedEvaluation('Con reintento', [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
            'max_attempts' => 3,
        ]);

        EvaluationAttempt::factory()->graded(9)->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
        ]);

        $state = $this->stateFor($evaluation);

        $this->assertTrue($state['actionable']);
        $this->assertSame(2, $state['attempts_remaining']);
    }

    #[Test]
    public function a_student_cannot_manage_evaluations(): void
    {
        $this->actingAs($this->student)->get('/evaluations/create')->assertForbidden();
    }

    #[Test]
    public function the_staff_view_still_renders_for_a_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $this->actingAs($teacher)
            ->get('/evaluations')
            ->assertOk()
            ->assertSee('Nueva Evaluación');
    }

    #[Test]
    public function a_student_never_appears_in_the_staff_subject_list(): void
    {
        // Regression guard: the branch must not leak the management UI.
        $this->publishedEvaluation('Visible');

        $component = Volt::actingAs($this->student)->test('evaluations.index');

        $this->assertTrue($component->get('isStudent'));
        $this->assertSame([], $component->get('subjects'));
        $this->assertCount(0, $component->instance()->evaluations());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedEvaluation(string $title, array $overrides = []): OnlineEvaluation
    {
        return OnlineEvaluation::factory()->published()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacherId(),
            'title' => $title,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draftEvaluation(string $title, array $overrides = []): OnlineEvaluation
    {
        return OnlineEvaluation::factory()->draft()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacherId(),
            'title' => $title,
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFor(OnlineEvaluation $evaluation): array
    {
        return app(StudentEvaluationState::class)->describe(
            $evaluation,
            $this->student,
            $evaluation->attempts()->orderByDesc('attempt_number')->first()
        );
    }

    private function teacherId(): int
    {
        return User::factory()->create(['role' => 'teacher'])->id;
    }

    #[Test]
    public function the_attempt_status_enum_stays_consistent_with_the_state_resolver(): void
    {
        // Guards against the state resolver drifting from the enum.
        $evaluation = $this->publishedEvaluation('Coherente');

        EvaluationAttempt::factory()->create([
            'user_id' => $this->student->id,
            'online_evaluation_id' => $evaluation->id,
            'status' => AttemptStatus::InProgress->value,
        ]);

        $this->assertTrue(
            $this->stateFor($evaluation)['key'] === StudentEvaluationState::IN_PROGRESS
        );
    }
}
