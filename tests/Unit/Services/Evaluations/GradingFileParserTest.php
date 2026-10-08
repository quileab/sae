<?php

namespace Tests\Unit\Services\Evaluations;

use App\Enums\GradingFlag;
use App\Services\Evaluations\GradingFileParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The parser reads whatever a model sent back, so these cover the shapes that
 * actually come out of chat assistants: reordered columns, trailing pipes,
 * escaped pipes inside a cell, comma decimals, chatty prose around the tables,
 * and the fenced block the protocol asks for.
 */
class GradingFileParserTest extends TestCase
{
    #[Test]
    public function it_reads_the_header_attributes(): void
    {
        $parsed = GradingFileParser::parse($this->file(header: '<!-- SAE id=20 modo=grading bloques=g1_1 intentos=24 -->'));

        $this->assertSame(20, $parsed->evaluationId());
        $this->assertSame('grading', $parsed->mode());
        $this->assertSame('g1_1', $parsed->attributes['bloques']);
    }

    #[Test]
    public function it_reads_rubric_and_mark_rows(): void
    {
        $parsed = GradingFileParser::parse($this->file());

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(2, $parsed->rubricRows);
        $this->assertCount(2, $parsed->markRows);

        $this->assertSame([
            'block' => 'g1_1',
            'variant' => null,
            'key' => 'dato',
            'label' => 'Distingue dato de información',
            'points' => 1.0,
            'levels' => '0: no / 1: sí',
        ], $parsed->rubricRows[0]);

        $this->assertSame('00042', $parsed->markRows[0]['ref']);
        $this->assertSame(0.5, $parsed->markRows[0]['points']);
        $this->assertSame([GradingFlag::Excellent], $parsed->markRows[0]['flags']);
    }

    #[Test]
    public function it_accepts_columns_in_any_order(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:NOTAS:INICIO -->
        | puntaje | ref | bloque |
        | --- | --- | --- |
        | 1.0 | 00042 | g1_1 |
        | 0.0 | 00043 | g1_1 |
        <!-- SAE:NOTAS:FIN -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | clave | criterio | puntaje_max |
        | --- | --- | --- | --- |
        | g1_1 | a | Uno | 0.5 |
        | g1_1 | b | Dos | 0.5 |
        <!-- SAE:RUBRICA:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(2, $parsed->markRows);
        $this->assertSame('00042', $parsed->markRows[0]['ref']);
    }

    #[Test]
    public function it_normalises_header_accents_and_spacing(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | Bloque | Clave  | Criterio | Puntaje Max | Niveles |
        | --- | --- | --- | --- | --- |
        | g1_1 | dato | Distingue | 1.5 | 0: no |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | Ref | Bloque | Puntaje | Justificación | Flags |
        | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | 1.5 | Correcto. | |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertSame('Correcto.', $parsed->markRows[0]['justification']);
    }

    #[Test]
    public function it_keeps_an_escaped_pipe_inside_a_cell(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | clave | criterio | puntaje_max | niveles |
        | --- | --- | --- | --- | --- |
        | g1_1 | dato | Distingue | 1.0 | 0: a \| b |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 | | Cita "a \| b" correctamente escapada |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertSame('0: a | b', $parsed->rubricRows[0]['levels']);
        $this->assertSame('Cita "a | b" correctamente escapada', $parsed->markRows[0]['justification']);
    }

    #[Test]
    public function it_rejects_a_row_whose_unquoted_pipe_splits_it_in_two(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 | | Cita "a | b" mal cerrada |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertSame([], $parsed->markRows);
        $this->assertStringContainsString('Tabla 1, fila 1: se esperaban 6 columnas y hay 7', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_accepts_comma_decimals_and_trailing_pipes(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | clave | criterio | puntaje_max |
        | --- | --- | --- | --- |
        | g1_1 | dato | Distingue | 1,5 |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1,5 | | Todo bien. |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertSame(1.5, $parsed->rubricRows[0]['points']);
        $this->assertSame(1.5, $parsed->markRows[0]['points']);
    }

    #[Test]
    public function it_prefers_the_trailing_fenced_block_over_chatty_prose(): void
    {
        $prose = <<<'MD'
        Acá está mi corrección. Mirá lo que respondí primero:

        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | puntaje | justificacion |
        | --- | --- | --- | --- |
        | 00001 | g1_1 | 0.0 |填 mal |
        <!-- SAE:NOTAS:FIN -->

        Y abajo el bloque limpio para que lo copies:
        MD;

        $fenced = "```markdown\n".$this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 |  | Correcto. |
        ROWS)."\n```";

        $parsed = GradingFileParser::parse($prose."\n".$fenced);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(1, $parsed->markRows);
        $this->assertSame('Correcto.', $parsed->markRows[0]['justification']);
    }

    #[Test]
    public function it_falls_back_to_the_whole_text_when_there_is_no_fence(): void
    {
        $parsed = GradingFileParser::parse($this->file());

        $this->assertCount(2, $parsed->markRows);
    }

    #[Test]
    public function it_reports_a_missing_section_instead_of_guessing(): void
    {
        $parsed = GradingFileParser::parse("<!-- SAE id=20 modo=grading -->\nSin tablas acá.\n");

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('Falta el bloque <!-- SAE:RUBRICA:INICIO -->', implode(' ', $parsed->errorMessages()));
        $this->assertSame([], $parsed->rubricRows);
        $this->assertSame([], $parsed->markRows);
    }

    #[Test]
    public function it_reports_an_unclosed_section(): void
    {
        $markdown = "<!-- SAE id=20 -->\n<!-- SAE:RUBRICA:INICIO -->\n| bloque | clave | criterio | puntaje_max |\n";

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('Falta el cierre', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_reports_a_missing_required_column(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | clave | puntaje_max |
        | --- | --- | --- |
        | g1_1 | dato | 1.0 |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | puntaje |
        | --- | --- | --- |
        | 00042 | g1_1 | 1.0 |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('Faltan columnas obligatorias en la tabla 1: criterio', implode(' / ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_rejects_a_non_numeric_score_and_names_the_row(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 | | Correcto. |
        | 00043 | g1_1 | dato | muchisimo | | Exagerado. |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertCount(1, $parsed->markRows, 'the sound row survives');

        $this->assertStringContainsString('Tabla 1, fila 2: puntaje inválido: "muchisimo"', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_rejects_a_row_with_the_wrong_column_count(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('Tabla 1, fila 1: se esperaban 6 columnas y hay 4', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_rejects_a_repeated_criterion_because_it_would_be_double_counted(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 | | Primera. |
        | 00042 | g1_1 | dato | 1.0 | | Repetida. |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertCount(1, $parsed->markRows);
        $this->assertStringContainsString('fila repetida para 00042/g1_1/dato', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_rejects_a_mark_naming_a_criterion_the_rubric_never_declared(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | inventada | 1.0 | | Sin rubricar. |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('La clave "inventada" no figura en la rúbrica', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function a_block_without_a_rubric_may_be_marked_with_a_dash_key(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | variante | clave | criterio | puntaje_max | niveles |
        | --- | --- | --- | --- | --- | --- |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_2 | — | 1.0 | | Sin rúbrica. |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertNull($parsed->markRows[0]['key']);
    }

    #[Test]
    public function it_keeps_a_row_with_an_unknown_flag_but_says_so(): void
    {
        $markdown = $this->file(marks: <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 1.0 | off_topic; raro | Correcto. |
        ROWS);

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid());
        $this->assertSame([GradingFlag::OffTopic], $parsed->markRows[0]['flags']);
        $this->assertStringContainsString('bandera desconocida "raro"', implode(' ', array_column($parsed->warnings, 'message')));
    }

    #[Test]
    public function it_warns_about_a_missing_separator_but_still_reads_the_rows(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=grading -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | clave | criterio | puntaje_max |
        | g1_1 | dato | Distingue | 1.0 |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | clave | puntaje | flags | justificacion |
        | 00042 | g1_1 | dato | 1.0 | | Correcto. |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid());
        $this->assertCount(1, $parsed->rubricRows);
        $this->assertStringContainsString('Falta la línea de separación', implode(' ', array_column($parsed->warnings, 'message')));
    }

    #[Test]
    public function an_untouched_export_is_valid_and_carries_no_rows(): void
    {
        $markdown = <<<'MD'
        <!-- SAE id=20 modo=rubric bloques=g1_1 -->
        <!-- SAE:RUBRICA:INICIO -->
        | bloque | variante | clave | criterio | puntaje_max | niveles |
        | --- | --- | --- | --- | --- | --- |
        <!-- SAE:RUBRICA:FIN -->
        <!-- SAE:NOTAS:INICIO -->
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        <!-- SAE:NOTAS:FIN -->
        MD;

        $parsed = GradingFileParser::parse($markdown);

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertFalse($parsed->hasContent());
        $this->assertSame('rubric', $parsed->mode());
    }

    #[Test]
    public function empty_text_is_reported_as_an_error(): void
    {
        $parsed = GradingFileParser::parse("   \n  ");

        $this->assertFalse($parsed->isValid());
        $this->assertStringContainsString('El texto está vacío', implode(' ', $parsed->errorMessages()));
    }

    #[Test]
    public function it_tolerates_windows_line_endings(): void
    {
        $parsed = GradingFileParser::parse(str_replace("\n", "\r\n", $this->file()));

        $this->assertTrue($parsed->isValid(), implode(' / ', $parsed->errorMessages()));
        $this->assertCount(2, $parsed->markRows);
    }

    private function file(?string $header = '<!-- SAE id=20 modo=grading -->', ?string $marks = null): string
    {
        $marks ??= <<<'ROWS'
        | ref | bloque | clave | puntaje | flags | justificacion |
        | --- | --- | --- | --- | --- | --- |
        | 00042 | g1_1 | dato | 0.5 | excellent | Menciona el concepto. |
        | 00043 | g1_1 | dato | 1.0 | | Define y aplica. |
        ROWS;

        return <<<MD
        {$header}

        <!-- SAE:RUBRICA:INICIO -->
        | bloque | variante | clave | criterio | puntaje_max | niveles |
        | --- | --- | --- | --- | --- | --- |
        | g1_1 | — | dato | Distingue dato de información | 1.0 | 0: no / 1: sí |
        | g1_1 | — | aplica | Aplica al caso | 0.5 | 0: no / 0.5: sí |
        <!-- SAE:RUBRICA:FIN -->

        <!-- SAE:NOTAS:INICIO -->
        {$marks}
        <!-- SAE:NOTAS:FIN -->
        MD;
    }
}
