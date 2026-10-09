<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\GradingSuggestionState;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradingCalibration;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Calibration only means something if the numbers it compares exist.
 *
 * The first half pins down that saving settles pending proposals (identical
 * numbers count as accepted, anything else as edited), because adjusted
 * suggestions used to stay proposed forever and the disagreement signal was
 * invisible. The second half pins down the report math itself.
 */
class GradingCalibrationTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    private EvaluationAttempt $attempt;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 8902, 'career_id' => $career->id, 'name' => 'Gestión']);
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
    public function saving_untouched_numbers_counts_as_accepted(): void
    {
        $this->seedProposal($this->attempt, total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('prefillSuggestions')
            ->call('saveGrading');

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(GradingSuggestionState::Accepted, $suggestion->state);
        $this->assertSame('1.00', (string) $suggestion->final_points);
        $this->assertNotNull($suggestion->accepted_at);
        $this->assertSame($this->teacher->id, $suggestion->accepted_by);
    }

    #[Test]
    public function saving_adjusted_numbers_counts_as_edited(): void
    {
        $this->seedProposal($this->attempt, total: 1.0, max: 1.5);

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->call('prefillSuggestions')
            ->set('manualScores.q1', 0.75)
            ->call('saveGrading');

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(GradingSuggestionState::Edited, $suggestion->state);
        $this->assertSame('0.75', (string) $suggestion->final_points);
    }

    #[Test]
    public function a_block_left_empty_stays_undecided(): void
    {
        $this->seedProposal($this->attempt, total: 1.0, max: 1.5, block: 'q1');
        $this->seedProposal($this->attempt, total: 0.5, max: 1.0, block: 'q2');

        $this->actingAs($this->teacher);

        Volt::test('evaluations.results', ['id' => $this->evaluation->id])
            ->call('openGrading', $this->attempt->id)
            ->set('manualScores', ['q1' => 1.0])
            ->call('saveGrading');

        $this->assertSame(GradingSuggestionState::Accepted, GradingSuggestion::where('block_id', 'q1')->sole()->state);
        $this->assertSame(GradingSuggestionState::Proposed, GradingSuggestion::where('block_id', 'q2')->sole()->state);
    }

    #[Test]
    public function the_report_measures_what_the_teacher_settled(): void
    {
        $this->seedSettledFixture();

        $report = app(GradingCalibration::class)->report($this->evaluation);

        $this->assertSame(4, $report['settled']);
        $this->assertSame(1, $report['pending']);
        $this->assertSame(['accepted' => 1, 'edited' => 1, 'rejected' => 2], [
            'accepted' => $report['accepted'],
            'edited' => $report['edited'],
            'rejected' => $report['rejected'],
        ]);
        $this->assertSame(0.25, $report['acceptance_rate']);
        $this->assertSame(3, $report['measured']);
        $this->assertSame(0.5, $report['mae']);
        $this->assertSame(0.28, $report['mae_normalized']);
        $this->assertSame(0.5, $report['bias']);
        $this->assertFalse($report['reliable']);
    }

    #[Test]
    public function the_report_breaks_down_blocks_agents_flags_and_reasons(): void
    {
        $this->seedSettledFixture();

        $report = app(GradingCalibration::class)->report($this->evaluation);

        $this->assertEquals(
            ['q1' => ['settled' => 2, 'accepted' => 1, 'measured' => 2, 'mae' => 0.25]],
            array_intersect_key($report['by_block'], ['q1' => true])
        );
        $this->assertEquals(
            ['q2' => ['settled' => 2, 'accepted' => 0, 'measured' => 1, 'mae' => 1.0]],
            array_intersect_key($report['by_block'], ['q2' => true])
        );
        $this->assertSame(0.25, $report['by_agent']['gpt-x']['mae']);
        $this->assertSame(1.0, $report['by_agent']['claude-y']['mae']);
        $this->assertEquals(['off_topic' => 1, 'instruction_injection' => 1], $report['flags']);
        $this->assertSame(0.0, $report['flag_rejection_rate']['off_topic']);
        $this->assertSame(1.0, $report['flag_rejection_rate']['instruction_injection']);
        $this->assertSame(['criterio_mal_aplicado' => 1], $report['disagreement_reasons']);
    }

    #[Test]
    public function filters_narrow_the_report(): void
    {
        $this->seedSettledFixture();

        $byAgent = app(GradingCalibration::class)->report($this->evaluation, agent: 'gpt-x');
        $byBlock = app(GradingCalibration::class)->report($this->evaluation, block: 'q2');

        $this->assertSame(2, $byAgent['settled']);
        $this->assertSame('gpt-x', $byAgent['agent']);
        $this->assertSame(2, $byBlock['settled']);
        $this->assertSame('q2', $byBlock['block']);
        $this->assertTrue(app(GradingCalibration::class)->report($this->evaluation, minSettled: 4)['reliable']);
    }

    #[Test]
    public function the_command_reports_and_warns_on_small_samples(): void
    {
        $this->seedSettledFixture();

        $this->artisan('evaluations:calibrate', ['evaluation' => $this->evaluation->id])
            // One substring per written line: a single doWrite call satisfies a
            // single expectsOutputToContain expectation.
            ->expectsOutputToContain('Liquidadas: 4')
            ->expectsOutputToContain('Aceptación: 25.0%')
            ->expectsOutputToContain('Muestra chica')
            ->assertSuccessful();
    }

    #[Test]
    public function the_command_says_so_when_there_is_nothing_to_measure(): void
    {
        $this->seedProposal($this->attempt, total: 1.0, max: 1.5);

        $this->artisan('evaluations:calibrate', ['evaluation' => $this->evaluation->id])
            ->expectsOutputToContain('No hay sugerencias liquidadas para medir (1 todavía pendientes)')
            ->assertSuccessful();
    }

    #[Test]
    public function the_command_rejects_an_unknown_evaluation(): void
    {
        $this->artisan('evaluations:calibrate', ['evaluation' => 999999])
            ->expectsOutputToContain('La evaluación no existe.')
            ->assertFailed();
    }

    #[Test]
    public function the_json_flag_prints_the_raw_report(): void
    {
        $this->seedSettledFixture();

        // The whole JSON goes out in a single write, so only one substring
        // expectation can match it; the array contents are covered by the
        // service tests above.
        $this->artisan('evaluations:calibrate', ['evaluation' => $this->evaluation->id, '--json' => true])
            ->expectsOutputToContain('"measured": 3')
            ->assertSuccessful();
    }

    /**
     * One accepted, one edited, one rejected with a later manual mark, one
     * rejected without one, one still pending. Enough to exercise every branch.
     *
     * Each suggestion lives on its own attempt: the table allows a single live
     * row per (attempt, block).
     */
    private function seedSettledFixture(): void
    {
        $accepted = $this->freshAttempt();
        $edited = $this->freshAttempt();
        $graded = $this->freshAttempt(AttemptStatus::Graded, ['q2' => 1.0]);
        $ungraded = $this->freshAttempt();
        $pending = $this->freshAttempt();

        $this->seedProposal($accepted, total: 1.5, max: 1.5, block: 'q1', agent: 'gpt-x', state: 'accepted', final: 1.5);
        $this->seedProposal($edited, total: 1.5, max: 1.5, block: 'q1', agent: 'gpt-x', state: 'edited', final: 1.0, flags: ['off_topic']);
        $this->seedProposal($graded, total: 2.0, max: 2.0, block: 'q2', agent: 'claude-y', state: 'rejected', flags: ['instruction_injection'], reason: 'criterio_mal_aplicado');
        $this->seedProposal($ungraded, total: 0.5, max: 1.0, block: 'q2', agent: 'claude-y', state: 'rejected');
        $this->seedProposal($pending, total: 1.0, max: 1.5, block: 'q1', agent: 'gpt-x', state: 'proposed');
    }

    private function freshAttempt(AttemptStatus $status = AttemptStatus::Submitted, array $scores = ['q1' => 0, 'q2' => 0]): EvaluationAttempt
    {
        return EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => $status->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'block_scores' => $scores,
            'score' => 0.0,
            'answers' => ['q1' => ['text' => 'Una respuesta.'], 'q2' => ['text' => 'Otra.']],
            'content_snapshot' => $this->evaluation->content,
            'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
        ]);
    }

    private function seedProposal(
        EvaluationAttempt $attempt,
        float $total,
        float $max,
        string $block = 'q1',
        string $agent = 'assistant',
        string $state = 'proposed',
        ?float $final = null,
        array $flags = [],
        ?string $reason = null,
    ): GradingSuggestion {
        return GradingSuggestion::factory()->forAttempt($attempt)->create([
            'block_id' => $block,
            'agent' => $agent,
            'total_points' => $total,
            'max_points' => $max,
            'flags' => $flags,
            'state' => GradingSuggestionState::from($state)->value,
            'final_points' => $final,
            'disagreement_reason' => $reason,
            'accepted_at' => $state === 'proposed' ? null : now(),
            'accepted_by' => $state === 'proposed' ? null : $this->teacher->id,
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
                ['id' => 'q1', 'type' => 'open_text', 'prompt' => 'Explique.', 'points' => 1.5],
                ['id' => 'q2', 'type' => 'open_text', 'prompt' => 'Diferencie.', 'points' => 1.0],
            ],
        ]]];
    }
}
