<?php

namespace App\Services\Evaluations;

use App\Enums\GradingFlag;
use Illuminate\Support\Str;

/**
 * Reads back the markdown an assistant grader filled in.
 *
 * Deliberately free of models and database access: everything it needs arrives
 * in the string, which keeps the riskiest part of the round trip testable on its
 * own. Semantic checks that need the database (does this ref exist, was this
 * attempt already marked by hand) belong to the caller, not here.
 *
 * Two rules keep a chatty model from being misread:
 *
 *  - columns are matched by name, never by position, so reordering is harmless
 *  - anything the model can get wrong is reported as an error with its row
 *    number instead of being silently dropped
 */
final class GradingFileParser
{
    public const RULE_PREFIX = 'SAE';

    private const REQUIRED_COLUMNS = [
        'RUBRICA' => ['bloque', 'clave', 'criterio', 'puntaje_max'],
        'NOTAS' => ['ref', 'bloque', 'puntaje'],
    ];

    public static function parse(string $markdown): ParsedGradingFile
    {
        if (trim($markdown) === '') {
            return new ParsedGradingFile(
                errors: [['section' => 'archivo', 'row' => null, 'message' => 'El texto está vacío.']],
            );
        }

        $errors = [];
        $warnings = [];
        $source = self::payload($markdown);

        $rubricRows = self::parseSection($source, 'RUBRICA', $errors, $warnings);
        $markRows = self::parseSection($source, 'NOTAS', $errors, $warnings);

        return new ParsedGradingFile(
            attributes: self::parseAttributes($markdown),
            rubricRows: $rubricRows,
            markRows: $markRows,
            errors: array_merge($errors, self::crossCheck($rubricRows, $markRows)),
            warnings: $warnings,
        );
    }

    /**
     * The protocol asks for a trailing fenced block holding only the two filled
     * sections, because copying from a code block keeps the markers verbatim.
     *
     * @return array<string, string>
     */
    private static function parseAttributes(string $markdown): array
    {
        if (preg_match('/<!--\s*'.self::RULE_PREFIX.'\s+(.*?)\s*-->/s', $markdown, $matches) !== 1) {
            return [];
        }

        $attributes = [];

        foreach (preg_split('/\s+/', trim($matches[1])) ?: [] as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * The protocol asks for a trailing fenced block holding only the two filled
     * sections, because copying from a code block keeps the markers verbatim.
     *
     * Every fenced block is concatenated, so a teacher who exported one question at a
     * time can paste all the files into the same box. Without a fence the whole text
     * is used, which still works as long as the markers survived.
     */
    private static function payload(string $markdown): string
    {
        if (preg_match_all('/```markdown\s*\R(.*?)```/s', $markdown, $matches) < 1 || $matches[1] === []) {
            return $markdown;
        }

        return implode("\n", array_map('trim', $matches[1]));
    }

    /**
     * Reads every INICIO/FIN pair, not just the first: marking one question at a time
     * means a teacher may paste several exports into the same box.
     *
     * @param  array<int, array{section: string, row: ?int, message: string}>  $errors
     * @param  array<int, array{section: string, row: ?int, message: string}>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private static function parseSection(string $source, string $section, array &$errors, array &$warnings): array
    {
        $opening = sprintf('<!-- %s:%s:INICIO -->', self::RULE_PREFIX, $section);
        $closing = sprintf('<!-- %s:%s:FIN -->', self::RULE_PREFIX, $section);

        $rows = [];
        $seen = [];
        $offset = 0;
        $tables = 0;

        while (($start = strpos($source, $opening, $offset)) !== false) {
            $end = strpos($source, $closing, $start + strlen($opening));

            if ($end === false) {
                $errors[] = ['section' => $section, 'row' => null, 'message' => sprintf('Falta el cierre %s.', $closing)];

                return $rows;
            }

            $tables++;
            $body = substr($source, $start + strlen($opening), $end - $start - strlen($opening));

            $rows = [
                ...$rows,
                ...self::parseTable($body, $section, $tables, $errors, $warnings, $seen),
            ];

            $offset = $end + strlen($closing);
        }

        if ($tables === 0) {
            $errors[] = ['section' => $section, 'row' => null, 'message' => sprintf('Falta el bloque %s.', $opening)];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{section: string, row: ?int, message: string}>  $errors
     * @param  array<int, array{section: string, row: ?int, message: string}>  $warnings
     * @param  array<string, bool>  $seen  spans tables, so repeats across two pasted files are still caught
     * @return array<int, array<string, mixed>>
     */
    private static function parseTable(
        string $body,
        string $section,
        int $table,
        array &$errors,
        array &$warnings,
        array &$seen,
    ): array {
        $where = sprintf('tabla %d', $table);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $body) ?: []),
            fn (string $line) => $line !== '',
        ));

        if ($lines === []) {
            $errors[] = ['section' => $section, 'row' => null, 'message' => sprintf('La %s está vacía.', $where)];

            return [];
        }

        $headers = array_map(self::normalizeHeader(...), self::splitRow(array_shift($lines)));

        if ($lines !== [] && self::isSeparator($lines[0])) {
            array_shift($lines);
        } elseif ($lines !== []) {
            $warnings[] = [
                'section' => $section,
                'row' => null,
                'message' => sprintf('Falta la línea de separación (| --- |) debajo del encabezado de la %s.', $where),
            ];
        }

        $missing = array_diff(self::REQUIRED_COLUMNS[$section], $headers);

        if ($missing !== []) {
            $errors[] = [
                'section' => $section,
                'row' => null,
                'message' => sprintf('Faltan columnas obligatorias en la %s: %s.', $where, implode(', ', $missing)),
            ];

            return [];
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 1;
            $cells = self::splitRow($line);

            if (count($cells) !== count($headers)) {
                $errors[] = [
                    'section' => $section,
                    'row' => $rowNumber,
                    'message' => sprintf(
                        '%s, fila %d: se esperaban %d columnas y hay %d.',
                        ucfirst($where),
                        $rowNumber,
                        count($headers),
                        count($cells),
                    ),
                ];

                continue;
            }

            $row = array_combine($headers, $cells);

            if ($section === 'RUBRICA') {
                $parsed = self::rubricRow($row, $where, $rowNumber, $errors);

                if ($parsed !== null) {
                    $rows[] = $parsed;
                }

                continue;
            }

            $parsed = self::markRow($row, $where, $rowNumber, $errors, $warnings);

            if ($parsed === null) {
                continue;
            }

            // Two rows for the same criterion would be added together, which is
            // exactly the kind of silent miscount this contract must not allow.
            $identity = $parsed['ref'].'/'.$parsed['block'].'/'.$parsed['key'];

            if (isset($seen[$identity])) {
                $errors[] = [
                    'section' => $section,
                    'row' => $rowNumber,
                    'message' => sprintf('%s, fila %d: fila repetida para %s.', ucfirst($where), $rowNumber, $identity),
                ];

                continue;
            }

            $seen[$identity] = true;
            $rows[] = $parsed;
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, array{section: string, row: ?int, message: string}>  $errors
     * @return array{block: string, variant: ?string, key: string, label: string, points: float, levels: string}|null
     */
    private static function rubricRow(array $row, string $where, int $rowNumber, array &$errors): ?array
    {
        $points = self::toFloat($row['puntaje_max'] ?? '');

        if ($points === null) {
            $errors[] = ['section' => 'RUBRICA', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: puntaje_max inválido: "%s".', ucfirst($where), $rowNumber, $row['puntaje_max'] ?? '')];

            return null;
        }

        $block = self::token($row['bloque'] ?? '');
        $key = self::token($row['clave'] ?? '');

        if ($block === '' || $key === '') {
            $errors[] = ['section' => 'RUBRICA', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: bloque y clave son obligatorios.', ucfirst($where), $rowNumber)];

            return null;
        }

        return [
            'block' => $block,
            'variant' => self::nullable($row['variante'] ?? null),
            'key' => $key,
            'label' => trim($row['criterio'] ?? ''),
            'points' => $points,
            'levels' => trim($row['niveles'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, array{section: string, row: ?int, message: string}>  $errors
     * @param  array<int, array{section: string, row: ?int, message: string}>  $warnings
     * @return array{ref: string, block: string, key: ?string, points: float, flags: array<int, GradingFlag>, justification: string}|null
     */
    private static function markRow(array $row, string $where, int $rowNumber, array &$errors, array &$warnings): ?array
    {
        $points = self::toFloat($row['puntaje'] ?? '');

        if ($points === null) {
            $errors[] = ['section' => 'NOTAS', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: puntaje inválido: "%s".', ucfirst($where), $rowNumber, $row['puntaje'] ?? '')];

            return null;
        }

        $ref = self::token($row['ref'] ?? '');
        $block = self::token($row['bloque'] ?? '');

        if ($ref === '' || $block === '') {
            $errors[] = ['section' => 'NOTAS', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: ref y bloque son obligatorios.', ucfirst($where), $rowNumber)];

            return null;
        }

        $justification = trim($row['justificacion'] ?? '');

        if ($justification === '') {
            $warnings[] = ['section' => 'NOTAS', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: la fila no trae justificación.', ucfirst($where), $rowNumber)];
        }

        return [
            'ref' => $ref,
            'block' => $block,
            'key' => self::nullable(self::token($row['clave'] ?? null)),
            'points' => $points,
            'flags' => self::flags($row['flags'] ?? '', $where, $rowNumber, $warnings),
            'justification' => $justification,
        ];
    }

    /**
     * @param  array<int, array{section: string, row: ?int, message: string}>  $warnings
     * @return array<int, GradingFlag>
     */
    private static function flags(string $raw, string $where, int $rowNumber, array &$warnings): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $flags = [];

        foreach (preg_split('/[;,]/', $raw) ?: [] as $candidate) {
            $candidate = strtolower(trim($candidate));

            if ($candidate === '') {
                continue;
            }

            $flag = GradingFlag::tryFrom($candidate);

            if ($flag === null) {
                $warnings[] = ['section' => 'NOTAS', 'row' => $rowNumber, 'message' => sprintf('%s, fila %d: bandera desconocida "%s".', ucfirst($where), $rowNumber, $candidate)];

                continue;
            }

            $flags[$flag->value] = $flag;
        }

        return array_values($flags);
    }

    /**
     * A mark naming a criterion the rubric never declared would be unauditable,
     * so it is surfaced rather than accepted quietly.
     *
     * @param  array<int, array<string, mixed>>  $rubricRows
     * @param  array<int, array<string, mixed>>  $markRows
     * @return array<int, array{section: string, row: ?int, message: string}>
     */
    private static function crossCheck(array $rubricRows, array $markRows): array
    {
        $declared = [];

        foreach ($rubricRows as $row) {
            $declared[$row['block']][$row['key']] = true;
        }

        $errors = [];

        foreach ($markRows as $index => $row) {
            if ($row['key'] === null || isset($declared[$row['block']][$row['key']])) {
                continue;
            }

            $errors[] = [
                'section' => 'NOTAS',
                'row' => $index + 1,
                'message' => sprintf('La clave "%s" no figura en la rúbrica de "%s".', $row['key'], $row['block']),
            ];
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private static function splitRow(string $line): array
    {
        $line = trim($line);

        if (str_starts_with($line, '|')) {
            $line = substr($line, 1);
        }

        if (str_ends_with($line, '|')) {
            $line = substr($line, 0, -1);
        }

        $cells = [];
        $current = '';
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            // Only an immediately preceding backslash escapes the separator, so
            // every other backslash survives as itself.
            if ($line[$i] === '\\' && ($line[$i + 1] ?? '') === '|') {
                $current .= '|';
                $i++;

                continue;
            }

            if ($line[$i] === '|') {
                $cells[] = $current;
                $current = '';

                continue;
            }

            $current .= $line[$i];
        }

        $cells[] = $current;

        return array_map(trim(...), $cells);
    }

    private static function isSeparator(string $line): bool
    {
        foreach (self::splitRow($line) as $cell) {
            if (! preg_match('/^:?-{2,}:?$/', $cell)) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeHeader(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '_', Str::ascii(Str::lower(trim($header))));
    }

    private static function toFloat(string $value): ?float
    {
        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private static function nullable(?string $value): ?string
    {
        $trimmed = self::token((string) $value);

        return ($trimmed === '' || $trimmed === '—' || $trimmed === '-') ? null : $trimmed;
    }

    /**
     * Identifiers are data. A model that wraps one in backticks is being
     * helpful, not wrong, so the decoration is removed rather than rejected.
     */
    private static function token(?string $value): string
    {
        $trimmed = trim((string) $value);

        if (strlen($trimmed) > 1 && str_starts_with($trimmed, '`') && str_ends_with($trimmed, '`')) {
            $trimmed = trim(substr($trimmed, 1, -1));
        }

        return $trimmed;
    }
}
