<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\GradingFlag;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradingFileBuilder;
use App\Services\Evaluations\GradingFileParser;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The contract is only real if what the builder emits can be read back.
 *
 * These close the loop on the app's own output, including the shape the prefilled
 * rubric actually has, so a change to the export cannot quietly invalidate the
 * import format.
 */
class GradingFileRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 5501, 'career_id' => $career->id, 'name' => 'Gestión']);

        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'title' => 'Parcial 3 - Gestión',
            'content' => $this->content(),
        ]);
    }

    #[Test]
    public function an_untouched_export_parses_clean_with_the_prefilled_rubric(): void
    {
        $parsed = GradingFileParser::parse($this->export());

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertSame([], $parsed->warnings);
        $this->assertSame($this->evaluation->id, $parsed->evaluationId());
        $this->assertSame(2, count($parsed->rubricRows), 'The shipped rubric comes back as rubric rows.');
        $this->assertSame([], $parsed->markRows, 'Nothing was marked yet.');
    }

    #[Test]
    public function marks_written_against_the_shipped_rubric_parse_clean(): void
    {
        $attempt = $this->attempt();
        $ref = str_pad((string) $attempt->id, 5, '0', STR_PAD_LEFT);

        $filled = str_replace('<!-- SAE:NOTAS:FIN -->', implode("\n", [
            "| {$ref} | g1_1 | dato | 0.5 | | Define el concepto. |",
            "| {$ref} | g1_1 | aplica | 0.5 | excellent | Caso coherente. |",
            '<!-- SAE:NOTAS:FIN -->',
        ]), $this->export());

        $parsed = GradingFileParser::parse($filled);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(2, $parsed->markRows);
        $this->assertSame([GradingFlag::Excellent], $parsed->markRows[1]['flags']);
        $this->assertSame(1.0, (float) array_sum(array_column($parsed->markRows, 'points')));
    }

    #[Test]
    public function a_model_that_wraps_ids_in_backticks_is_still_read(): void
    {
        $attempt = $this->attempt();
        $ref = str_pad((string) $attempt->id, 5, '0', STR_PAD_LEFT);

        $filled = str_replace('<!-- SAE:NOTAS:FIN -->', implode("\n", [
            "| `{$ref}` | `g1_1` | `dato` | 0.5 | | Con backticks. |",
            "| `{$ref}` | `g1_1` | `aplica` | 0.5 | | También. |",
            '<!-- SAE:NOTAS:FIN -->',
        ]), $this->export());

        $parsed = GradingFileParser::parse($filled);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertSame($ref, $parsed->markRows[0]['ref']);
        $this->assertSame('g1_1', $parsed->markRows[0]['block']);
        $this->assertCount(2, $parsed->markRows);
    }

    #[Test]
    public function exporting_one_question_and_marking_it_leaves_the_others_alone(): void
    {
        $attempt = $this->attempt();
        $ref = str_pad((string) $attempt->id, 5, '0', STR_PAD_LEFT);
        $export = $this->export($attempt, blockId: 'g1_2');

        $this->assertStringNotContainsString('g1_1', $export);
        $this->assertStringContainsString('bloques=g1_2', $export);
        $this->assertTrue(GradingFileParser::parse($export)->isValid());

        $filled = str_replace('<!-- SAE:NOTAS:FIN -->', implode("\n", [
            "| {$ref} | g1_2 | — | 1.0 | | Pregunta sin rúbrica. |",
            '<!-- SAE:NOTAS:FIN -->',
        ]), $export);

        $parsed = GradingFileParser::parse($filled);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(1, $parsed->markRows);
        $this->assertNull($parsed->markRows[0]['key']);
    }

    #[Test]
    public function two_question_exports_can_be_pasted_together(): void
    {
        $attempt = $this->attempt();
        $ref = str_pad((string) $attempt->id, 5, '0', STR_PAD_LEFT);

        $first = str_replace('<!-- SAE:NOTAS:FIN -->', implode("\n", [
            "| {$ref} | g1_1 | dato | 0.5 | | Uno. |",
            "| {$ref} | g1_1 | aplica | 0.5 | | Dos. |",
            '<!-- SAE:NOTAS:FIN -->',
        ]), $this->export($attempt, blockId: 'g1_1'));

        $second = str_replace('<!-- SAE:NOTAS:FIN -->', implode("\n", [
            "| {$ref} | g1_2 | — | 1.5 | | Tres. |",
            '<!-- SAE:NOTAS:FIN -->',
        ]), $this->export($attempt, blockId: 'g1_2'));

        $parsed = GradingFileParser::parse($first."\n".$second);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertSame(
            ['g1_1', 'g1_1', 'g1_2'],
            array_column($parsed->markRows, 'block'),
            'Exports of different questions coexist without colliding.',
        );
        $this->assertSame(2.5, (float) array_sum(array_column($parsed->markRows, 'points')));
    }

    #[Test]
    public function a_rubric_draft_comes_back_as_rubric_rows_only(): void
    {
        $export = $this->export(mode: GradingFileBuilder::MODE_RUBRIC);

        $this->assertTrue(GradingFileParser::parse($export)->isValid());
        $this->assertSame([], GradingFileParser::parse($export)->rubricRows);

        $drafted = str_replace('<!-- SAE:RUBRICA:FIN -->', implode("\n", [
            '| g1_1 | — | concepto | Explica el concepto pedido. | 1.0 | 0: no lo explica. |',
            '| g1_1 | — | ejemplo | Lo ilustra con un caso. | 0.5 | 0: sin ejemplo. |',
            '<!-- SAE:RUBRICA:FIN -->',
        ]), $export);

        $parsed = GradingFileParser::parse($drafted);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(2, $parsed->rubricRows);
        $this->assertSame(1.5, (float) array_sum(array_column($parsed->rubricRows, 'points')), 'criteria add up to the question.');
    }

    private function export(
        ?EvaluationAttempt $attempt = null,
        string $mode = GradingFileBuilder::MODE_GRADING,
        ?string $blockId = null,
    ): string {
        $attempt ??= $this->attempt();

        return (new GradingFileBuilder(
            evaluation: $this->evaluation,
            attempts: collect([$attempt]),
            mode: $mode,
            blockId: $blockId,
        ))->build();
    }

    private function attempt(): EvaluationAttempt
    {
        return EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student', 'name' => 'Apellido, Nombre'])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'answers' => [
                'g1_1' => ['text' => 'El remito es un dato; el total del mes es información.'],
                'g1_2' => ['text' => 'Una consulta puntual vs. un reporte consolidado.'],
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
                    'id' => 'g1_1',
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
                    'id' => 'g1_2',
                    'type' => 'open_text',
                    'prompt' => 'Diferencie una consulta de un reporte.',
                    'points' => 1.5,
                ],
            ],
        ]]];
    }
}
