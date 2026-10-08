<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\GradingFlag;
use App\Enums\GradingSuggestionState;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradingFileParser;
use App\Services\Evaluations\GradingSuggestionImporter;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * This is the only place where an assistant proposal becomes a mark.
 *
 * The rules under test: accepting goes through AttemptGrader so the /10
 * normalisation has one owner, rejecting leaves the attempt pending, and a
 * rerun reopens a proposal that had already been settled.
 */
class SuggestionReviewTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    private EvaluationAttempt $attempt;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 8801, 'career_id' => $career->id, 'name' => 'Gestión']);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'lastname' => 'Docente', 'firstname' => 'Ana']);
        $this->teacher->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'content' => $this->content(),
        ]);

        $this->attempt = EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student', 'lastname' => 'Pérez', 'firstname' => 'Juan'])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'block_scores' => ['q1' => 0, 'q2' => 0],
            'score' => 0.0,
            'answers' => ['q1' => ['text' => 'Una respuesta.'], 'q2' => ['text' => 'Otra.']],
            'content_snapshot' => $this->evaluation->content,
            'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
        ]);
    }

    #[Test]
    public function pasting_saves_proposals_that_stay_pending(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $this->marks([
                ['q1', 'dato', 0.5, 'Menciona el concepto.'],
                ['q1', 'aplica', 1.0, 'Lo aplica bien.'],
            ]))
            ->call('applyPastedSuggestions')
            ->assertSet('assistErrors', []);

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(GradingSuggestionState::Proposed, $suggestion->state);
        $this->assertNull($suggestion->accepted_at);

        // Still no mark: the import half of the loop stops at a proposal.
        $this->assertSame(AttemptStatus::Submitted, $this->attempt->fresh()->status);
        $this->assertEquals(['q1' => 0, 'q2' => 0], $this->attempt->fresh()->block_scores);
    }

    #[Test]
    public function a_second_identical_paste_reports_no_change(): void
    {
        $this->actingAs($this->teacher);
        $markdown = $this->marks([['q1', 'dato', 0.5, 'Bien.']]);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $markdown)
            ->call('applyPastedSuggestions');

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $markdown)
            ->call('applyPastedSuggestions')
            ->assertSet('assistApplied.unchanged', 1)
            ->assertSet('assistApplied.created', 0)
            ->assertSet('assistApplied.updated', 0);
    }

    #[Test]
    public function applying_the_same_paste_twice_keeps_one_revision(): void
    {
        $markdown = $this->marks([['q1', 'dato', 0.5, 'Bien.']]);

        app(GradingSuggestionImporter::class)->import($this->evaluation, GradingFileParser::parse($markdown));
        app(GradingSuggestionImporter::class)->import($this->evaluation, GradingFileParser::parse($markdown));

        $this->assertSame(1, GradingSuggestion::count());
        $this->assertSame(1, GradingSuggestion::sole()->revision);
    }

    #[Test]
    public function pasting_does_not_overwrite_proposals_that_were_already_settled(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $this->marks([['q1', 'dato', 0.5, 'Bien.']]))
            ->call('applyPastedSuggestions');

        GradingSuggestion::sole()->update([
            'state' => GradingSuggestionState::Rejected->value,
            'disagreement_reason' => 'criterio_mal_aplicado',
        ]);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openAssist')
            ->set('assistPasted', $this->marks([['q1', 'dato', 1.0, 'Ahora completo.']]))
            ->call('applyPastedSuggestions')
            ->assertSet('assistApplied.updated', 1);

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(GradingSuggestionState::Proposed, $suggestion->state);
        $this->assertSame(2, $suggestion->revision);
        $this->assertSame('criterio_mal_aplicado', $suggestion->history[0]['disagreement_reason'] ?? null);
    }

    #[Test]
    public function accepting_proposals_writes_the_mark_through_the_grader(): void
    {
        $this->seedProposal(total: 1.5, max: 1.5);
        GradingSuggestion::factory()->create([
            'evaluation_attempt_id' => $this->attempt->id,
            'block_id' => 'q2',
            'total_points' => 0.5,
            'max_points' => 1.0,
            'state' => GradingSuggestionState::Proposed->value,
        ]);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('acceptSuggestions');

        $this->attempt->refresh();

        // 1.5 + 0.5 of 2.5 total, on the /10 scale.
        $this->assertEquals(['q1' => 1.5, 'q2' => 0.5], $this->attempt->block_scores);
        $this->assertSame('8.00', (string) $this->attempt->score);
        $this->assertSame(AttemptStatus::Graded, $this->attempt->status);

        foreach (GradingSuggestion::all() as $suggestion) {
            $this->assertSame(GradingSuggestionState::Accepted, $suggestion->state);
            $this->assertNotNull($suggestion->accepted_at);
            $this->assertSame($this->teacher->id, $suggestion->accepted_by);
        }
    }

    #[Test]
    public function accepting_keeps_a_mark_the_teacher_entered_by_hand(): void
    {
        // q2 was credited by hand and never became a proposal.
        $this->attempt->update(['block_scores' => ['q1' => 0, 'q2' => 1.0]]);
        $this->seedProposal(total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('acceptSuggestions');

        $this->assertEquals(['q1' => 1.0, 'q2' => 1.0], $this->attempt->fresh()->block_scores);
        $this->assertSame('8.00', (string) $this->attempt->fresh()->score);
    }

    #[Test]
    public function accepting_nothing_leaves_the_attempt_pending(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('acceptSuggestions');

        $this->assertSame(AttemptStatus::Submitted, $this->attempt->fresh()->status);
        $this->assertSame(0, GradingSuggestion::count());
    }

    #[Test]
    public function loading_a_proposal_only_fills_the_inputs_without_marking(): void
    {
        $this->seedProposal(total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('prefillSuggestions')
            ->assertSet('manualScores.q1', 1.0);

        $this->assertSame(AttemptStatus::Submitted, $this->attempt->fresh()->status);
        $this->assertEquals(['q1' => 0, 'q2' => 0], $this->attempt->fresh()->block_scores);
    }

    #[Test]
    public function adjusting_the_loaded_numbers_then_saving_records_the_difference(): void
    {
        $this->seedProposal(total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('prefillSuggestions')
            ->set('manualScores.q1', 1.25)
            ->call('saveGrading');

        $this->attempt->refresh();

        $this->assertEquals(['q1' => 1.25, 'q2' => 0], $this->attempt->block_scores);
        $this->assertSame('5.00', (string) $this->attempt->score);
    }

    #[Test]
    public function rejecting_leaves_the_attempt_pending_and_records_the_reason(): void
    {
        $this->seedProposal(total: 1.0, max: 1.5);
        GradingSuggestion::factory()->create([
            'evaluation_attempt_id' => $this->attempt->id,
            'block_id' => 'q2',
            'total_points' => 1.0,
            'max_points' => 1.0,
            'state' => GradingSuggestionState::Proposed->value,
        ]);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->set('suggestionRejectionReason', 'rubrica_inadecuada')
            ->call('rejectSuggestions');

        $this->attempt->refresh();

        $this->assertSame(AttemptStatus::Submitted, $this->attempt->status);
        $this->assertEquals(['q1' => 0, 'q2' => 0], $this->attempt->block_scores);

        foreach (GradingSuggestion::all() as $suggestion) {
            $this->assertSame(GradingSuggestionState::Rejected, $suggestion->state);
            $this->assertSame('rubrica_inadecuada', $suggestion->disagreement_reason);
            $this->assertNull($suggestion->accepted_at);
        }
    }

    #[Test]
    public function rejecting_without_a_reason_is_allowed(): void
    {
        $this->seedProposal(total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('rejectSuggestions');

        $this->assertSame(GradingSuggestionState::Rejected, GradingSuggestion::sole()->state);
        $this->assertNull(GradingSuggestion::sole()->disagreement_reason);
    }

    #[Test]
    public function the_grading_modal_shows_the_proposal_with_its_criteria_and_flags(): void
    {
        $suggestion = $this->seedProposal(total: 1.0, max: 1.5);
        $suggestion->update([
            'criteria' => [[
                'key' => 'dato',
                'label' => 'Distingue dato de información',
                'points_awarded' => 0.5,
                'points_max' => 0.5,
                'justification' => 'Menciona el concepto pero no lo desarrolla.',
            ]],
            'flags' => ['insufficient_length'],
        ]);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->assertSee('Propuesta del asistente')
            ->assertSee('Distingue dato de información')
            ->assertSee('Menciona el concepto pero no lo desarrolla.')
            ->assertSee('Respuesta insuficiente')
            ->assertSee('Aceptar y finalizar');
    }

    #[Test]
    public function an_attempt_with_no_proposals_offers_nothing_to_accept(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->assertDontSee('Aceptar y finalizar');
    }

    #[Test]
    public function the_attempt_list_shows_how_many_proposals_are_waiting(): void
    {
        GradingSuggestion::factory()->create([
            'evaluation_attempt_id' => $this->attempt->id,
            'block_id' => 'q1',
            'state' => GradingSuggestionState::Proposed->value,
        ]);

        $this->actingAs($this->teacher);

        // Without opening the modal: the table is what carries the badge.
        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->assertSee('Propuestas del asistente esperando revisión');
    }

    #[Test]
    public function the_attempt_list_shows_no_badge_without_proposals(): void
    {
        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->assertDontSee('Propuestas del asistente esperando revisión');
    }

    #[Test]
    public function a_settled_proposal_stops_showing_in_the_badge(): void
    {
        $this->seedProposal(total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('acceptSuggestions')
            ->call('closeGrading')
            ->assertDontSee('Propuestas del asistente esperando revisión');
    }

    #[Test]
    public function a_flagged_proposal_is_counted_as_needing_attention(): void
    {
        $suggestion = $this->seedProposal(total: 0.0, max: 1.5);
        $suggestion->update(['flags' => [GradingFlag::InstructionInjection->value]]);

        $this->assertTrue($suggestion->fresh()->requiresAttention());
    }

    private function seedProposal(float $total, float $max): GradingSuggestion
    {
        return GradingSuggestion::factory()->create([
            'evaluation_attempt_id' => $this->attempt->id,
            'block_id' => 'q1',
            'total_points' => $total,
            'max_points' => $max,
            'criteria' => [[
                'key' => 'dato',
                'label' => 'Distingue dato de información',
                'points_awarded' => $total,
                'points_max' => 0.5,
                'justification' => 'Menciona el concepto.',
            ]],
            'flags' => [],
            'state' => GradingSuggestionState::Proposed->value,
            'accepted_at' => null,
            'accepted_by' => null,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: float, 3: string}>  $rows
     */
    private function marks(array $rows): string
    {
        $table = [];

        foreach ($rows as [$block, $key, $points, $justification]) {
            $table[] = sprintf(
                '| %05d | %s | %s | %s | | %s |',
                $this->attempt->id,
                $block,
                $key,
                $points,
                $justification,
            );
        }

        return implode("\n", [
            sprintf('<!-- SAE id=%d modo=grading -->', $this->evaluation->id),
            '',
            '<!-- SAE:RUBRICA:INICIO -->',
            '| bloque | variante | clave | criterio | puntaje_max | niveles |',
            '| --- | --- | --- | --- | --- | --- |',
            '| q1 | — | dato | Distingue dato de información | 0.5 | 0: no |',
            '| q1 | — | aplica | Aplica el proceso al caso | 1.0 | 0: no |',
            '<!-- SAE:RUBRICA:FIN -->',
            '',
            '<!-- SAE:NOTAS:INICIO -->',
            '| ref | bloque | clave | puntaje | flags | justificacion |',
            '| --- | --- | --- | --- | --- | --- |',
            ...$table,
            '<!-- SAE:NOTAS:FIN -->',
            '',
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
                        'criteria' => [
                            ['key' => 'dato', 'label' => 'Distingue dato de información', 'points' => 0.5],
                            ['key' => 'aplica', 'label' => 'Aplica el proceso al caso', 'points' => 1.0],
                        ],
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
