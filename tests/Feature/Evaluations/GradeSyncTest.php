<?php

namespace Tests\Feature\Evaluations;

use App\Models\Career;
use App\Models\ClassSession;
use App\Models\EvaluationAttempt;
use App\Models\Grade;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradeSyncPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gradebook sync must never silently overwrite a note a teacher typed by hand.
 */
class GradeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Base de Datos']);

        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $this->session = ClassSession::create([
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'teacher_id' => $teacher->id,
            'class_number' => 1,
            'unit' => 'U1',
            'type' => 'regular',
            'content' => '',
        ]);
    }

    #[Test]
    public function the_preview_writes_nothing(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);

        Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades')
            ->assertSet('showSyncModal', true);

        $this->assertDatabaseCount('grades', 0);
    }

    #[Test]
    public function confirming_writes_the_final_grade(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);

        Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades')
            ->set('syncUserIds', [$student->id])
            ->call('confirmSyncGrades')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grades', [
            'user_id' => $student->id,
            'class_session_id' => $this->session->id,
            'type' => Grade::TYPE_EVALUATION,
            'grade' => 8,
        ]);
    }

    #[Test]
    public function a_manual_grade_is_flagged_as_a_conflict_and_left_alone(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);

        Grade::create([
            'user_id' => $student->id,
            'class_session_id' => $this->session->id,
            'type' => Grade::TYPE_EVALUATION,
            'grade' => 4,
            'approved' => false,
            'attendance' => true,
            'comments' => 'Exámen Parcial APROBADO Calificación 9 (nueve)',
        ]);

        $component = Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades');

        $preview = $component->get('syncPreview');

        $this->assertSame(GradeSyncPreview::ACTION_CONFLICT, $preview[0]['action']);
        $this->assertSame(4.0, $preview[0]['current_grade']);
        $this->assertSame(8.0, $preview[0]['proposed_grade']);

        // Confirm with nothing selected: the manual note must survive.
        $component->set('syncUserIds', [])
            ->call('confirmSyncGrades')
            ->assertHasNoErrors();

        $grade = Grade::where('user_id', $student->id)->firstOrFail();

        $this->assertEquals(4.0, (float) $grade->grade);
        $this->assertSame('Exámen Parcial APROBADO Calificación 9 (nueve)', $grade->comments);
    }

    #[Test]
    public function a_manual_grade_is_overwritten_only_when_explicitly_confirmed(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);

        Grade::create([
            'user_id' => $student->id,
            'class_session_id' => $this->session->id,
            'type' => Grade::TYPE_EVALUATION,
            'grade' => 4,
            'approved' => false,
            'attendance' => true,
            'comments' => 'Nota escrita a mano',
        ]);

        Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades')
            ->set('syncUserIds', [$student->id])
            ->call('confirmSyncGrades')
            ->assertHasNoErrors();

        $grade = Grade::where('user_id', $student->id)->firstOrFail();

        $this->assertEquals(8.0, (float) $grade->grade);
        $this->assertStringStartsWith(GradeSyncPreview::PROVENANCE_PREFIX, (string) $grade->comments);
    }

    #[Test]
    public function a_grade_already_synced_by_this_engine_is_reported_as_unchanged(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);

        Grade::create([
            'user_id' => $student->id,
            'class_session_id' => $this->session->id,
            'type' => Grade::TYPE_EVALUATION,
            'grade' => 8,
            'approved' => true,
            'attendance' => true,
            'comments' => GradeSyncPreview::PROVENANCE_PREFIX.' '.$evaluation->title,
        ]);

        $component = Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades');

        $this->assertSame(GradeSyncPreview::ACTION_UNCHANGED, $component->get('syncPreview')[0]['action']);
        $this->assertSame([], $component->get('syncUserIds'));
    }

    #[Test]
    public function an_attempt_awaiting_manual_grading_is_not_synced(): void
    {
        [$evaluation, $student] = $this->scenario();

        EvaluationAttempt::factory()->submitted(7)->create([
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
        ]);

        $component = Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades');

        $this->assertSame(GradeSyncPreview::ACTION_SKIPPED, $component->get('syncPreview')[0]['action']);
        $this->assertDatabaseCount('grades', 0);
    }

    #[Test]
    public function a_student_cannot_trigger_the_sync(): void
    {
        [$evaluation, $student] = $this->scenario();
        $this->gradedAttempt($evaluation, $student, 8);
        $this->studentOfSubject();

        Volt::actingAs($student)
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades')
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 0);
    }

    #[Test]
    public function the_preview_refuses_without_a_class_session(): void
    {
        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher()->id,
            'class_session_id' => null,
        ]);

        $student = $this->studentOfSubject();
        $this->gradedAttempt($evaluation, $student, 9);

        Volt::actingAs($this->teacher())
            ->test('evaluations.results', ['id' => $evaluation->id])
            ->call('previewSyncGrades')
            ->assertSet('showSyncModal', false);

        $this->assertDatabaseCount('grades', 0);
    }

    /**
     * @return array{0: OnlineEvaluation, 1: User}
     */
    private function scenario(): array
    {
        $teacher = $this->teacher();

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'class_session_id' => $this->session->id,
            'teacher_id' => $teacher->id,
            'passing_score' => 6,
        ]);

        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        return [$evaluation, $student];
    }

    private function gradedAttempt(OnlineEvaluation $evaluation, User $student, float $score): EvaluationAttempt
    {
        return EvaluationAttempt::factory()->graded($score)->create([
            'user_id' => $student->id,
            'online_evaluation_id' => $evaluation->id,
        ]);
    }

    private function studentOfSubject(): User
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        return $student;
    }

    private function teacher(): User
    {
        return User::firstWhere('role', 'teacher') ?? tap(
            User::factory()->create(['role' => 'teacher']),
            fn (User $teacher) => $teacher->enrollments()->create([
                'subject_id' => $this->subject->id,
                'status' => 'active',
            ])
        );
    }
}
