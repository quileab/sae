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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The importer writes proposals and nothing else.
 *
 * Every test here checks block_scores and score afterwards, because that promise
 * is the reason this table is separate from the gradebook.
 */
class GradingSuggestionImporterTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    private EvaluationAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 7701, 'career_id' => $career->id, 'name' => 'Gestión']);

        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'content' => $this->content(),
        ]);

        $this->attempt = EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student', 'lastname' => 'Pérez', 'firstname' => 'Juan'])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'block_scores' => ['q1' => 0.0, 'q2' => 0.0],
            'score' => 0.0,
            'answers' => ['q1' => ['text' => 'Una respuesta.'], 'q2' => ['text' => 'Otra.']],
            'content_snapshot' => $this->evaluation->content,
            'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
        ]);
    }

    #[Test]
    public function it_stores_one_proposal_per_attempt_and_question(): void
    {
        $report = $this->import($this->marks([
            ['q1', 'dato', 0.5, 'Menciona el concepto.'],
            ['q1', 'aplica', 0.5, 'Lo aplica.'],
        ]));

        $this->assertSame(1, $report['created']);
        $this->assertSame(1, GradingSuggestion::count());

        $suggestion = GradingSuggestion::sole();

        $this->assertSame($this->attempt->id, $suggestion->evaluation_attempt_id);
        $this->assertSame('q1', $suggestion->block_id);
        $this->assertSame(1.0, (float) $suggestion->total_points);
        $this->assertSame(1.5, (float) $suggestion->max_points);
        $this->assertSame(GradingSuggestionState::Proposed, $suggestion->state);
        $this->assertSame(1, $suggestion->revision);
    }

    #[Test]
    public function it_collapses_the_criterion_rows_into_named_criteria(): void
    {
        $this->import($this->marks([
            ['q1', 'dato', 0.5, 'Menciona el concepto.'],
            ['q1', 'aplica', 0.0, 'No lo aplica.'],
        ]));

        $criteria = GradingSuggestion::sole()->criteria;

        $this->assertCount(2, $criteria);
        $this->assertSame('dato', $criteria[0]['key']);
        $this->assertSame('Distingue dato de información', $criteria[0]['label']);
        $this->assertSame(0.5, $criteria[0]['points_awarded']);
        $this->assertSame(0.5, $criteria[0]['points_max']);
        $this->assertSame('No lo aplica.', $criteria[1]['justification']);
    }

    #[Test]
    public function a_question_without_a_rubric_gets_one_general_criterion(): void
    {
        $this->import($this->marks([['q2', null, 1.0, 'Respuesta razonable.']]));

        $suggestion = GradingSuggestion::sole();

        $this->assertCount(1, $suggestion->criteria);
        $this->assertNull($suggestion->criteria[0]['key']);
        $this->assertSame('Valoración general', $suggestion->criteria[0]['label']);
        $this->assertSame(1.0, (float) $suggestion->criteria[0]['points_max']);
    }

    #[Test]
    public function it_never_writes_a_mark(): void
    {
        $this->import($this->marks([['q1', 'dato', 0.5, 'Bien.'], ['q1', 'aplica', 1.0, 'Muy bien.']]));

        $this->attempt->refresh();

        // The JSON column normalises 0.0 to 0 on the way in.
        $this->assertEquals(['q1' => 0, 'q2' => 0], $this->attempt->block_scores);
        $this->assertSame('0.00', (string) $this->attempt->score);
        $this->assertSame(AttemptStatus::Submitted, $this->attempt->status);
    }

    #[Test]
    public function re_importing_the_same_file_changes_nothing(): void
    {
        $markdown = $this->marks([['q1', 'dato', 0.5, 'Bien.']]);

        $this->import($markdown);
        $report = $this->import($markdown);

        $this->assertSame(1, $report['unchanged']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(1, GradingSuggestion::sole()->revision);
        $this->assertSame([], GradingSuggestion::sole()->history);
    }

    #[Test]
    public function a_different_file_overwrites_and_keeps_the_previous_payload(): void
    {
        $this->import($this->marks([['q1', 'dato', 0.5, 'Primera.']]));
        $report = $this->import($this->marks([['q1', 'dato', 1.0, 'Segunda.']]));

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(1, $report['updated']);
        $this->assertSame(2, $suggestion->revision);
        $this->assertSame(1.0, (float) $suggestion->total_points);
        $this->assertSame('Segunda.', $suggestion->criteria[0]['justification']);
        $this->assertCount(1, $suggestion->history);
        $this->assertSame(0.5, (float) $suggestion->history[0]['total_points']);
        $this->assertSame('Primera.', $suggestion->history[0]['criteria'][0]['justification']);
    }

    #[Test]
    public function a_settled_proposal_goes_back_for_review_when_the_model_is_rerun(): void
    {
        $this->import($this->marks([['q1', 'dato', 0.5, 'Primera.']]));

        GradingSuggestion::sole()->update([
            'state' => GradingSuggestionState::Rejected->value,
            'disagreement_reason' => 'criterio mal aplicado',
            'final_points' => 0.0,
        ]);

        $this->import($this->marks([['q1', 'dato', 1.0, 'Segunda.']]));

        $suggestion = GradingSuggestion::sole();

        $this->assertSame(GradingSuggestionState::Proposed, $suggestion->state);
        $this->assertNull($suggestion->final_points);
        $this->assertNull($suggestion->disagreement_reason);
    }

    #[Test]
    public function it_collects_the_flags_of_every_criterion_row(): void
    {
        $this->import($this->marks([
            ['q1', 'dato', 0.0, 'Fuera de tema.', [GradingFlag::OffTopic]],
            ['q1', 'aplica', 0.0, 'Intento de instrucción.', [GradingFlag::InstructionInjection]],
        ]));

        $suggestion = GradingSuggestion::sole();

        $this->assertEqualsCanonicalizing(
            ['off_topic', 'instruction_injection'],
            $suggestion->flags,
        );
        $this->assertTrue($suggestion->requiresAttention());
    }

    #[Test]
    public function it_rejects_a_ref_from_another_evaluation(): void
    {
        $other = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->evaluation->subject_id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'content' => $this->content(),
        ]);

        $foreign = EvaluationAttempt::factory()->submitted()->create([
            'online_evaluation_id' => $other->id,
            'user_id' => User::factory()->create(['role' => 'student'])->id,
        ]);

        $report = $this->import($this->marks([['q1', 'dato', 0.5, 'Bien.']], ref: $foreign->id));

        $this->assertSame(0, $report['created']);
        $this->assertCount(1, $report['rejected']);
        $this->assertSame(0, GradingSuggestion::count());
    }

    #[Test]
    public function it_does_nothing_when_the_file_has_errors(): void
    {
        $report = $this->import("<!-- SAE id={$this->evaluation->id} -->\nsin tablas\n");

        $this->assertSame(0, $report['created']);
        $this->assertSame(0, GradingSuggestion::count());
    }

    #[Test]
    public function a_drafted_rubric_is_stored_only_when_it_adds_up(): void
    {
        $report = app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, GradingFileParser::parse($this->rubricDraft([
            ['q2', 'concepto', 'Explica el concepto.', 0.6],
            ['q2', 'ejemplo', 'Lo ilustra.', 0.4],
        ])));

        $rubric = Rubric::snapshotFor($this->evaluation->refresh()->content)['q2'] ?? null;

        $this->assertSame(1, $report['created']);
        $this->assertSame(Rubric::SOURCE_AGENT, $rubric['source']);
        $this->assertSame(1.0, Rubric::make($rubric)->maxPoints());
    }

    #[Test]
    public function a_rubric_that_does_not_add_up_is_refused(): void
    {
        $touchedAt = $this->evaluation->updated_at?->toIso8601String();

        $report = app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, GradingFileParser::parse($this->rubricDraft([
            ['q2', 'concepto', 'Explica el concepto.', 0.6],
        ])));

        $this->assertSame(0, $report['created']);
        $this->assertCount(1, $report['rejected']);
        $this->assertStringContainsString('q2', $report['rejected'][0]);
        $this->assertArrayNotHasKey('q2', Rubric::snapshotFor($this->evaluation->refresh()->content));
        // Not written at all, not merely left equivalent.
        $this->assertSame($touchedAt, $this->evaluation->updated_at?->toIso8601String());
    }

    #[Test]
    public function re_applying_the_same_rubric_reports_no_change(): void
    {
        $draft = $this->rubricDraft([['q2', 'concepto', 'Explica.', 1.0]]);

        app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, GradingFileParser::parse($draft));
        $report = app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, GradingFileParser::parse($draft));

        $this->assertSame(0, $report['created']);
        $this->assertSame(1, $report['unchanged']);
    }

    #[Test]
    public function a_rubric_for_a_question_that_does_not_exist_is_refused(): void
    {
        $report = app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, GradingFileParser::parse($this->rubricDraft([
            ['inexistente', 'c', 'Criterio.', 1.0],
        ])));

        $this->assertSame(0, $report['created']);
        $this->assertCount(1, $report['rejected']);
    }

    /**
     * @param  array<int, array{0: string, 1: ?string, 2: float, 3: string, 4?: array<int, GradingFlag>}>  $rows
     * @return array{created: int, updated: int, unchanged: int, rejected: array<int, string>}
     */
    private function import(string $markdown): array
    {
        return app(GradingSuggestionImporter::class)->import($this->evaluation, GradingFileParser::parse($markdown));
    }

    /**
     * @param  array<int, array{0: string, 1: ?string, 2: float, 3: string, 4?: array<int, GradingFlag>}>  $rows
     */
    private function marks(array $rows, ?int $ref = null): string
    {
        $ref ??= $this->attempt->id;
        $table = [];

        foreach ($rows as $row) {
            [$block, $key, $points, $justification] = $row;
            $flags = $row[4] ?? null;

            $table[] = sprintf(
                '| %05d | %s | %s | %s | %s | %s |',
                $ref,
                $block,
                $key ?? '—',
                $points,
                $flags === null ? '' : implode(';', array_map(fn (GradingFlag $f) => $f->value, $flags)),
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
     * @param  array<int, array{0: string, 1: string, 2: string, 3: float}>  $rows
     */
    private function rubricDraft(array $rows): string
    {
        $table = [];

        foreach ($rows as [$block, $key, $label, $points]) {
            $table[] = sprintf('| %s | — | %s | %s | %s | 0: no |', $block, $key, $label, $points);
        }

        return implode("\n", [
            sprintf('<!-- SAE id=%d modo=rubric -->', $this->evaluation->id),
            '',
            '<!-- SAE:RUBRICA:INICIO -->',
            '| bloque | variante | clave | criterio | puntaje_max | niveles |',
            '| --- | --- | --- | --- | --- | --- |',
            ...$table,
            '<!-- SAE:RUBRICA:FIN -->',
            '',
            '<!-- SAE:NOTAS:INICIO -->',
            '| ref | bloque | clave | puntaje | flags | justificacion |',
            '| --- | --- | --- | --- | --- | --- |',
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
