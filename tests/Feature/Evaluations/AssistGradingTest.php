<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradingFileBuilder;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The assisted grading dialog generates a file and reads one back.
 *
 * The point of this slice is that review writes nothing: a teacher can walk the
 * whole round trip and stop before any mark moves, so every test here asserts
 * block_scores and score are untouched afterwards.
 */
class AssistGradingTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private OnlineEvaluation $evaluation;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 6601, 'career_id' => $career->id, 'name' => 'Gestión']);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Docente, Apellido']);
        $this->teacher->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Parcial 3 - Gestión',
            'content' => $this->content(),
        ]);
    }

    #[Test]
    public function the_button_only_shows_with_attempts_waiting(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->assertDontSee('Corrección asistida');

        $this->attempt();

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->assertSee('Corrección asistida');
    }

    #[Test]
    public function it_opens_on_the_question_that_still_needs_a_rubric(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->assertSet('showAssistModal', true)
            ->assertSet('assistBlockId', 'q2')
            ->assertSee('q2')
            ->assertSee('sin rúbrica');
    }

    #[Test]
    public function it_builds_nothing_until_asked(): void
    {
        $this->attempt();
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->assertSet('assistMarkdown', null);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown')
            ->tap(fn ($c) => dump('summary='.json_encode($c->get('assistSummary')), 'blockId='.var_export($c->get('assistBlockId'), true)))
            ->assertSet('assistSummary.attempts', 1)
            ->assertSet('assistSummary.blocks', 1)
            ->assertSet('assistSummary.has_rubric', false)
            ->assertSee('todavía no tiene rúbrica');
    }

    #[Test]
    public function it_reports_when_there_is_nothing_pending(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown')
            ->assertSet('assistMarkdown', null);
    }

    #[Test]
    public function the_generated_file_can_be_downloaded(): void
    {
        $this->attempt();
        $this->actingAs($this->teacher);

        $this->assertStringStartsWith(
            'correccion-parcial-3-gestion-',
            GradingFileBuilder::filename($this->evaluation),
        );

        // Livewire 4 has no download harness, so the action itself is covered by
        // the guard test below and the filename by the unit above.
        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown')
            ->call('downloadAssistMarkdown')
            ->assertSet('assistMarkdown', fn ($value) => str_contains($value, 'SAE:NOTAS:FIN'));
    }

    #[Test]
    public function downloading_before_generating_is_refused(): void
    {
        $this->attempt();
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->call('downloadAssistMarkdown')
            ->assertSet('assistMarkdown', null);
    }

    #[Test]
    public function reviewing_marks_produces_rows_without_touching_any_grade(): void
    {
        $attempt = $this->attempt();
        $before = ['scores' => $attempt->block_scores, 'score' => (string) $attempt->score];

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistBlockId', 'q1')
            ->call('buildAssistMarkdown')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 1.5]))
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'ok')
            ->assertSet('assistErrors', []);

        $attempt->refresh();

        $this->assertSame($before['scores'], $attempt->block_scores);
        $this->assertSame($before['score'], (string) $attempt->score);
        $this->assertSame(0, GradingSuggestion::count(), 'Review must not create suggestions either.');
    }

    #[Test]
    public function the_preview_shows_the_student_the_score_and_the_reason(): void
    {
        $attempt = $this->attempt(name: 'Pérez, Juan');

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistBlockId', 'q1')
            ->call('buildAssistMarkdown')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 1.0], justification: 'Define el concepto pero no lo aplica.'))
            ->call('reviewPasted')
            ->assertSee('Pérez, Juan')
            ->assertSee('Define el concepto pero no lo aplica.')
            ->assertSee('1 legible');
    }

    #[Test]
    public function it_flags_a_row_naming_a_ref_that_does_not_exist(): void
    {
        $attempt = $this->attempt();

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', <<<MD
                <!-- SAE id={$this->evaluation->id} modo=grading -->
                <!-- SAE:RUBRICA:INICIO -->
                | bloque | variante | clave | criterio | puntaje_max | niveles |
                | --- | --- | --- | --- | --- | --- |
                | q1 | — | c1 | Criterio | 1.5 | |
                <!-- SAE:RUBRICA:FIN -->
                <!-- SAE:NOTAS:INICIO -->
                | ref | bloque | clave | puntaje | flags | justificacion |
                | --- | --- | --- | --- | --- | --- |
                | 99999 | q1 | c1 | 1.5 | | De otro lado. |
                <!-- SAE:NOTAS:FIN -->
                MD)
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'desconocido')
            ->assertSee('No hay ningún intento con ese ref');
    }

    #[Test]
    public function a_stored_zero_is_not_treated_as_a_teacher_mark(): void
    {
        // The auto-grader stores 0 for every open_text block, so a stored zero has
        // to stay "not marked yet" or every row would be flagged as an overwrite.
        $attempt = $this->attempt(blockScores: ['q1' => 0.0]);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistBlockId', 'q1')
            ->call('buildAssistMarkdown')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 1.0]))
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'ok');
    }

    #[Test]
    public function it_flags_a_score_above_what_the_question_is_worth(): void
    {
        $attempt = $this->attempt();

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistBlockId', 'q1')
            ->call('buildAssistMarkdown')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 9.0]))
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'error')
            ->assertSee('la pregunta vale 1.50');
    }

    #[Test]
    public function it_warns_when_a_row_would_overwrite_a_mark_a_teacher_entered(): void
    {
        // The realistic case: a teacher credited this one question by hand and
        // left the attempt pending because the others still need marking.
        $attempt = $this->attempt(blockScores: ['q1' => 1.5]);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 0.5]))
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'sobrescribe')
            ->assertSee('Ya tiene una nota cargada a mano')
            ->assertSee('1 pisa una nota final');
    }

    #[Test]
    public function it_refuses_a_file_built_for_another_evaluation(): void
    {
        $attempt = $this->attempt();

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', str_replace(
                '<!-- SAE id='.$this->evaluation->id,
                '<!-- SAE id=999999',
                $this->filled($attempt, 'q1', ['c1' => 1.0]),
            ))
            ->call('reviewPasted')
            ->assertSee('El archivo dice ser de la evaluación 999999');
    }

    #[Test]
    public function editing_after_a_review_clears_the_stale_preview(): void
    {
        $attempt = $this->attempt();

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $this->filled($attempt, 'q1', ['c1' => 1.0]))
            ->call('reviewPasted')
            ->assertSet('assistPreview.0.status', 'ok')
            ->set('assistPasted', 'cambiado a mano')
            ->assertSet('assistPreview', [])
            ->assertSet('assistErrors', []);
    }

    #[Test]
    public function it_shows_the_parser_errors_when_the_model_invents_structure(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', 'Re.intento > sin tablas y con_acentos raros, nada de esto se puede leer.')
            ->call('reviewPasted')
            ->assertSee('Falta el bloque');
    }

    #[Test]
    public function a_teacher_of_another_subject_cannot_open_the_dialog(): void
    {
        $this->attempt();

        $intruder = User::factory()->create(['role' => 'teacher']);
        $otherSubject = Subject::create([
            'id' => 6602,
            'career_id' => $this->subject->career_id,
            'name' => 'Otra materia',
        ]);
        $intruder->enrollments()->create(['subject_id' => $otherSubject->id, 'status' => 'active']);

        Volt::actingAs($intruder)
            ->test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->assertForbidden()
            ->assertSet('showAssistModal', false);
    }

    #[Test]
    public function exporting_answers_is_denied_to_a_student_at_the_policy_level(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['subject_id' => $this->subject->id, 'status' => 'active']);

        $this->assertFalse(
            Gate::forUser($student)->allows('exportGrading', $this->evaluation),
            'Sending student answers to an external grader is a staff-only action.',
        );
    }

    #[Test]
    public function exporting_answers_follows_the_same_rules_as_grading(): void
    {
        $this->assertTrue(Gate::forUser($this->teacher)->allows('exportGrading', $this->evaluation));
        $this->assertTrue(
            Gate::forUser(User::factory()->create(['role' => 'admin']))->allows('exportGrading', $this->evaluation),
        );
    }

    #[Test]
    public function closing_clears_the_generated_markdown(): void
    {
        $this->attempt();

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown')
            ->call('closeAssist')
            ->assertSet('showAssistModal', false)
            ->assertSet('assistMarkdown', null)
            ->assertSet('assistSummary', [])
            ->assertSet('assistPasted', null);
    }

    /**
     * A paste that fills the marks table for one question, the way a model would.
     *
     * @param  array<string, float>  $pointsByCriterion
     */
    private function filled(EvaluationAttempt $attempt, string $block, array $pointsByCriterion, string $justification = 'Correcto.'): string
    {
        $ref = GradingFileBuilder::reference($attempt);
        $rows = [];

        foreach ($pointsByCriterion as $key => $points) {
            $rows[] = sprintf('| %s | %s | %s | %s | | %s |', $ref, $block, $key, $points, $justification);
        }

        return implode("\n", [
            sprintf('<!-- SAE id=%d modo=grading -->', $this->evaluation->id),
            '',
            '<!-- SAE:RUBRICA:INICIO -->',
            '| bloque | variante | clave | criterio | puntaje_max | niveles |',
            '| --- | --- | --- | --- | --- | --- |',
            '| q1 | — | c1 | Criterio uno | 1.5 | 0: nada |',
            '<!-- SAE:RUBRICA:FIN -->',
            '',
            '<!-- SAE:NOTAS:INICIO -->',
            '| ref | bloque | clave | puntaje | flags | justificacion |',
            '| --- | --- | --- | --- | --- | --- |',
            ...$rows,
            '<!-- SAE:NOTAS:FIN -->',
            '',
        ]);
    }

    /**
     * @param  array<string, float>  $blockScores
     */
    private function attempt(
        AttemptStatus $status = AttemptStatus::Submitted,
        string $name = 'Alumno, Demo',
        array $blockScores = [],
        float $score = 0.0,
    ): EvaluationAttempt {
        [$lastname, $firstname] = array_pad(explode(', ', $name), 2, 'Alumno');

        return EvaluationAttempt::create([
            'user_id' => User::factory()->create([
                'role' => 'student',
                'name' => $name,
                'firstname' => $firstname,
                'lastname' => $lastname,
            ])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => $status->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'score' => $score,
            'block_scores' => $blockScores,
            'answers' => [
                'q1' => ['text' => 'El remito es un dato; el total del mes es información.'],
                'q2' => ['text' => 'Una consulta puntual no es un reporte.'],
            ],
            'content_snapshot' => $this->evaluation->content,
            'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(): array
    {
        return ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Parte 1',
            'blocks' => [
                [
                    'id' => 'q1',
                    'type' => 'open_text',
                    'prompt' => 'Explique la diferencia entre dato e información.',
                    'points' => 1.5,
                    'rubric' => [
                        'source' => 'agent',
                        'criteria' => [['key' => 'c1', 'label' => 'Criterio uno', 'points' => 1.5]],
                    ],
                ],
                [
                    'id' => 'q2',
                    'type' => 'open_text',
                    'prompt' => 'Diferencie una consulta de un reporte.',
                    'points' => 1.0,
                ],
            ],
        ]]];
    }
}
