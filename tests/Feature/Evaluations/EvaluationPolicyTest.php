<?php

namespace Tests\Feature\Evaluations;

use App\Models\Career;
use App\Models\ClassSession;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Authorization and the availability window.
 */
class EvaluationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Subject $ownSubject;

    private Subject $otherSubject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->ownSubject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Mi Materia']);
        $this->otherSubject = Subject::create(['id' => 1102, 'career_id' => $career->id, 'name' => 'Materia Ajena']);
    }

    #[Test]
    public function a_teacher_cannot_edit_an_evaluation_of_a_subject_they_do_not_teach(): void
    {
        $teacher = $this->teacherOf($this->otherSubject);
        $evaluation = $this->evaluationIn($this->ownSubject);

        $this->actingAs($teacher)
            ->get("/evaluations/{$evaluation->id}/edit")
            ->assertForbidden();
    }

    #[Test]
    public function a_teacher_cannot_delete_an_evaluation_of_a_subject_they_do_not_teach(): void
    {
        $teacher = $this->teacherOf($this->otherSubject);
        $evaluation = $this->evaluationIn($this->ownSubject);

        session(['current_subject_id' => $this->otherSubject->id]);

        Volt::actingAs($teacher)
            ->test('evaluations.index')
            ->call('deleteEvaluation', $evaluation->id)
            ->assertForbidden();

        $this->assertDatabaseHas('online_evaluations', ['id' => $evaluation->id]);
    }

    #[Test]
    public function a_teacher_of_the_subject_can_edit_the_evaluation(): void
    {
        $teacher = $this->teacherOf($this->ownSubject);
        $evaluation = $this->evaluationIn($this->ownSubject);

        $this->actingAs($teacher)
            ->get("/evaluations/{$evaluation->id}/edit")
            ->assertOk();
    }

    #[Test]
    public function an_administrative_user_can_edit_any_evaluation(): void
    {
        $evaluation = $this->evaluationIn($this->ownSubject);

        $this->actingAs(User::factory()->create(['role' => 'administrative']))
            ->get("/evaluations/{$evaluation->id}/edit")
            ->assertOk();
    }

    #[Test]
    public function a_student_cannot_open_the_management_index(): void
    {
        $student = $this->studentOf($this->ownSubject);

        $this->actingAs($student)->get('/evaluations')->assertForbidden();
    }

    #[Test]
    public function a_student_of_another_subject_cannot_take_the_evaluation(): void
    {
        $student = $this->studentOf($this->otherSubject);
        $evaluation = $this->evaluationIn($this->ownSubject);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertForbidden();
    }

    #[Test]
    public function a_student_cannot_take_an_evaluation_before_it_opens(): void
    {
        $student = $this->studentOf($this->ownSubject);
        $evaluation = $this->evaluationIn($this->ownSubject, [
            'status' => 'published',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertForbidden();
    }

    #[Test]
    public function a_student_cannot_take_an_evaluation_after_it_closes(): void
    {
        $student = $this->studentOf($this->ownSubject);
        $evaluation = $this->evaluationIn($this->ownSubject, [
            'status' => 'published',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertForbidden();
    }

    #[Test]
    public function a_student_cannot_take_a_closed_evaluation(): void
    {
        $student = $this->studentOf($this->ownSubject);
        $evaluation = $this->evaluationIn($this->ownSubject, ['status' => 'closed']);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertForbidden();
    }

    #[Test]
    public function an_open_evaluation_is_available_to_an_enrolled_student(): void
    {
        $student = $this->studentOf($this->ownSubject);
        $evaluation = $this->evaluationIn($this->ownSubject, [
            'status' => 'published',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertTrue($evaluation->isAvailableFor($student));
    }

    #[Test]
    public function a_student_enrolled_only_through_the_class_session_counts_as_enrolled(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = $this->teacherOf($this->ownSubject);

        $session = ClassSession::create([
            'subject_id' => $this->ownSubject->id,
            'date' => now()->toDateString(),
            'teacher_id' => $teacher->id,
            'class_number' => 1,
            'unit' => 'U1',
            'type' => 'regular',
            'content' => '',
        ]);

        // Enrolled in the class session but with no standalone subject enrolment.
        $student->enrollments()->create([
            'subject_id' => $this->ownSubject->id,
            'class_session_id' => $session->id,
            'status' => 'active',
        ]);

        $evaluation = $this->evaluationIn($this->ownSubject, [
            'status' => 'published',
            'class_session_id' => $session->id,
        ]);

        $this->actingAs($student)
            ->get("/evaluations/{$evaluation->id}/take")
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function evaluationIn(Subject $subject, array $overrides = []): OnlineEvaluation
    {
        return OnlineEvaluation::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacherOf($subject)->id,
            ...$overrides,
        ]);
    }

    private function teacherOf(Subject $subject): User
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        return $teacher;
    }

    private function studentOf(Subject $subject): User
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        return $student;
    }
}
