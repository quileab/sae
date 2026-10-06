<?php

namespace Tests\Feature\Evaluations;

use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke coverage: every evaluations screen must render for its authorised role.
 */
class EvaluationPagesRenderTest extends TestCase
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
    public function the_teacher_index_renders(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacherOwnsSubject($teacher);

        $this->actingAs($teacher)->get('/evaluations')->assertOk();
    }

    #[Test]
    public function the_editor_renders_in_create_mode(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacherOwnsSubject($teacher);

        $this->actingAs($teacher)
            ->get('/evaluations/create?subject_id='.$this->subject->id)
            ->assertOk()
            ->assertSee('Configuración Avanzada', false);
    }

    #[Test]
    public function the_editor_renders_in_edit_mode_with_the_moodle_values(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacherOwnsSubject($teacher);

        $evaluation = OnlineEvaluation::factory()->openFor()->withAttemptsAllowed(3)->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
            'passing_score' => 7,
            'grading_method' => 'average',
        ]);

        $this->actingAs($teacher)
            ->get("/evaluations/{$evaluation->id}/edit")
            ->assertOk()
            ->assertSee('Promedio de intentos');
    }

    #[Test]
    public function the_results_screen_renders_for_the_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacherOwnsSubject($teacher);

        $evaluation = OnlineEvaluation::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher)->get("/evaluations/{$evaluation->id}/results")->assertOk();
    }

    #[Test]
    public function the_take_screen_renders_in_preview_for_the_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->teacherOwnsSubject($teacher);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertOk()
            ->assertSee('Vista Previa');
    }

    #[Test]
    public function the_take_screen_renders_for_an_enrolled_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
        ]);

        $this->actingAs($student)->get("/evaluations/{$evaluation->id}/take")->assertOk();
    }

    #[Test]
    public function a_student_is_blocked_from_a_draft_evaluation(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);

        $evaluation = OnlineEvaluation::factory()->draft()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
        ]);

        $this->actingAs($student)->get("/evaluations/{$evaluation->id}/take")->assertForbidden();
    }

    #[Test]
    public function the_results_screen_renders_for_a_student_with_a_graded_attempt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
        ]);

        EvaluationAttempt::factory()->graded(8)->create([
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
        ]);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/results")
            ->assertOk()
            ->assertSee('Calificación Final');
    }

    #[Test]
    public function a_student_without_attempts_is_blocked_from_the_results(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
        ]);

        $this->actingAs($student)->get("/evaluations/{$evaluation->id}/results")->assertForbidden();
    }

    private function teacherOwnsSubject(User $teacher): void
    {
        $teacher->enrollments()->create([
            'subject_id' => $this->subject->id,
            'status' => 'active',
        ]);
    }
}
