<?php

namespace App\Services\Evaluations;

class EvaluationContent
{
    /**
     * Whether a stored answer is substantial enough to count as answered.
     *
     * Block types store different answer shapes, so emptiness cannot be decided
     * with a single empty() check: open_text and table_design nest their values,
     * matching stores a sparse index map, and take.blade.php stashes the shuffled
     * content under a reserved `_metadata` key that is never an answer.
     */
    public static function isAnswered(array $block, mixed $answer): bool
    {
        return match ($block['type'] ?? null) {
            'open_text' => filled($answer['text'] ?? null) || filled($answer['variant'] ?? null),
            'table_design' => filled($answer['tables'] ?? null) || filled($answer['relations'] ?? null),
            'checkboxes' => ! empty($answer),
            'matching' => collect(is_array($answer) ? $answer : [])->filter()->isNotEmpty(),
            default => filled($answer),
        };
    }

    /**
     * Calcula el puntaje máximo total de la evaluación sumando los puntos de cada bloque.
     *
     * Normalizes first so legacy content stored as a flat block array (pre-sections)
     * reports the same total as the sectioned shape.
     */
    public static function getMaxScore(array $content): float
    {
        $maxScore = 0;

        foreach (self::sections($content) as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                $maxScore += (float) ($block['points'] ?? 0);
            }
        }

        return $maxScore;
    }

    /**
     * Content as a list of sections, whatever shape it was stored in.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function sections(array $content): array
    {
        return self::normalize($content)['sections'] ?? [];
    }

    /**
     * Normaliza el contenido JSON para asegurar que tiene secciones y bloques.
     */
    public static function normalize(array $content): array
    {
        // Si el contenido viejo era un array directo de bloques (opción múltiple)
        if (! isset($content['sections'])) {
            $blocks = [];
            foreach ($content as $block) {
                $blocks[] = self::normalizeBlock($block);
            }

            return [
                'sections' => [
                    [
                        'title' => 'Evaluación',
                        'intro' => '',
                        'blocks' => $blocks,
                    ],
                ],
            ];
        }

        $normalizedSections = [];
        foreach ($content['sections'] as $section) {
            $normalizedBlocks = [];
            foreach ($section['blocks'] ?? [] as $block) {
                $normalizedBlocks[] = self::normalizeBlock($block);
            }
            $section['blocks'] = $normalizedBlocks;
            $normalizedSections[] = $section;
        }

        $content['sections'] = $normalizedSections;

        return $content;
    }

    protected static function normalizeBlock(array $block): array
    {
        if (! isset($block['id'])) {
            $block['id'] = uniqid('blk_');
        }
        if (! isset($block['points'])) {
            $block['points'] = 1;
        }

        if (! isset($block['type'])) {
            $block['type'] = 'open_text';
        }

        switch ($block['type']) {
            case 'multiple_choice':
                if (! isset($block['options']) || ! is_array($block['options'])) {
                    $block['options'] = [];
                }
                if (! isset($block['correct_option'])) {
                    $block['correct_option'] = null;
                }
                break;
            case 'true_false':
                if (! isset($block['correct_option'])) {
                    $block['correct_option'] = 'true';
                }
                break;
            case 'checkboxes':
                if (! isset($block['options']) || ! is_array($block['options'])) {
                    $block['options'] = [];
                }
                if (! isset($block['correct_options']) || ! is_array($block['correct_options'])) {
                    $block['correct_options'] = [];
                }
                break;
            case 'matching':
                if (! isset($block['pairs']) || ! is_array($block['pairs'])) {
                    $block['pairs'] = [];
                }
                break;
            case 'table_design':
                if (! isset($block['attributes']) || ! is_array($block['attributes'])) {
                    $block['attributes'] = [];
                }
                if (! isset($block['tables']) || ! is_array($block['tables'])) {
                    $block['tables'] = [];
                }
                if (! isset($block['relations']) || ! is_array($block['relations'])) {
                    $block['relations'] = [];
                }
                if (! isset($block['relation_types']) || ! is_array($block['relation_types'])) {
                    $block['relation_types'] = ['1:1', '1:N', 'N:N'];
                }
                break;
        }

        return $block;
    }

    /**
     * Evalúa una respuesta contra un bloque de contenido.
     * Devuelve el puntaje obtenido y si requiere corrección manual.
     *
     * @return array{score: float, requires_manual: bool}
     */
    public static function gradeBlock(array $block, mixed $answer): array
    {
        $points = (float) ($block['points'] ?? 1);

        if (empty($answer) && $answer !== '0' && $answer !== 0 && $answer !== false && ! is_array($answer)) {
            return ['score' => 0.0, 'requires_manual' => false];
        }

        switch ($block['type']) {
            case 'multiple_choice':
            case 'true_false':
                $correct = ($block['correct_option'] ?? null) === $answer;

                return [
                    'score' => $correct ? $points : 0.0,
                    'requires_manual' => false,
                ];

            case 'checkboxes':
                if (! is_array($answer)) {
                    return ['score' => 0.0, 'requires_manual' => false];
                }
                $expected = $block['correct_options'] ?? [];

                // Moodle-like fractional grading could be complex, let's do all-or-nothing or simple fractional
                // Simple partial grading: point per correct choice, minus point per wrong choice (clamped to 0)
                $correctChoices = 0;
                $wrongChoices = 0;
                foreach ($answer as $selected) {
                    if (in_array($selected, $expected)) {
                        $correctChoices++;
                    } else {
                        $wrongChoices++;
                    }
                }

                $totalExpected = count($expected);
                if ($totalExpected === 0) {
                    return ['score' => $points, 'requires_manual' => false];
                }

                $fraction = ($correctChoices - $wrongChoices) / $totalExpected;
                $earned = max(0.0, $fraction * $points);

                return [
                    'score' => (float) $earned,
                    'requires_manual' => false,
                ];

            case 'matching':
                if (! is_array($answer)) {
                    return ['score' => 0.0, 'requires_manual' => false];
                }
                $expectedPairs = $block['pairs'] ?? [];
                $totalPairs = count($expectedPairs);
                if ($totalPairs === 0) {
                    return ['score' => $points, 'requires_manual' => false];
                }

                $correct = 0;
                foreach ($expectedPairs as $index => $pair) {
                    $expectedMatch = $pair['match'] ?? $pair['answer'] ?? '';
                    if (isset($answer[$index]) && $answer[$index] === $expectedMatch) {
                        $correct++;
                    }
                }

                return [
                    'score' => ($correct / $totalPairs) * $points,
                    'requires_manual' => false,
                ];

            case 'table_design':
                return self::gradeTableDesign($block, $answer, $points);

            case 'short_text':
                if (! empty($block['correct_answer'])) {
                    $isCorrect = mb_strtolower(trim($answer)) === mb_strtolower(trim($block['correct_answer']));

                    return [
                        'score' => $isCorrect ? $points : 0.0,
                        'requires_manual' => false,
                    ];
                }

                return ['score' => 0.0, 'requires_manual' => true];

            case 'open_text':
            default:
                return ['score' => 0.0, 'requires_manual' => true];
        }
    }

    /**
     * Grading a relational schema design exercise.
     *
     * The rubric is expressed in units so partial designs earn partial credit:
     *
     *   per expected table
     *     1.0  the table exists (name matched)
     *     1.0  its column set matches the expected one
     *     0.5  the primary key sits on the expected column
     *     0.5  its foreign keys match the expected ones
     *   per expected relation
     *     1.0  origin, target and cardinality all match
     *
     * A table name alone is worth one unit out of three, so listing the right
     * names with an empty schema no longer scores full marks.
     *
     * Answer keys stored before column detail existed cannot be graded reliably,
     * so they are flagged for manual review instead of being auto-marked.
     *
     * @return array{score: float, requires_manual: bool}
     */
    protected static function gradeTableDesign(array $block, mixed $answer, float $maxPoints): array
    {
        if (! is_array($answer)) {
            return ['score' => 0.0, 'requires_manual' => false];
        }

        $expectedTables = $block['answer_key']['tables'] ?? [];
        $expectedRelations = $block['answer_key']['relations'] ?? [];

        $totalUnits = self::tableDesignUnits($expectedTables) + count($expectedRelations);

        if ($totalUnits === 0) {
            // No answer key at all: there is nothing to compare against.
            return ['score' => 0.0, 'requires_manual' => true];
        }

        $studentTables = $answer['tables'] ?? [];
        $studentRelations = $answer['relations'] ?? [];

        $earned = 0.0;

        foreach ($expectedTables as $expectedTable) {
            $name = self::normalizeLabel($expectedTable['name'] ?? '');
            $match = self::findStudentTable($studentTables, $name);

            if ($match === null) {
                continue;
            }

            $earned += 1.0;

            // A key with no column expectation can only score the name unit.
            if (! isset($expectedTable['columns']) || ! is_array($expectedTable['columns'])) {
                continue;
            }

            $studentColumns = self::columnNames($match);
            $expectedColumns = self::normalizeAll($expectedTable['columns']);

            if (self::normalizeAll($studentColumns) === $expectedColumns) {
                $earned += 1.0;
            }

            if (isset($expectedTable['pk']) && is_string($expectedTable['pk']) && trim($expectedTable['pk']) !== '') {
                if (self::primaryKeyOf($match) === self::normalizeLabel($expectedTable['pk'])) {
                    $earned += 0.5;
                }
            }

            if (isset($expectedTable['fks']) && is_array($expectedTable['fks'])) {
                if (self::normalizeAll(self::foreignKeysOf($match)) === self::normalizeAll($expectedTable['fks'])) {
                    $earned += 0.5;
                }
            }
        }

        foreach ($expectedRelations as $expectedRelation) {
            $from = self::normalizeLabel($expectedRelation['from'] ?? '');
            $to = self::normalizeLabel($expectedRelation['to'] ?? '');

            foreach ($studentRelations as $studentRelation) {
                if (
                    self::normalizeLabel($studentRelation['from'] ?? '') === $from
                    && self::normalizeLabel($studentRelation['to'] ?? '') === $to
                    && self::normalizeLabel($studentRelation['type'] ?? '') === self::normalizeLabel($expectedRelation['type'] ?? '')
                ) {
                    $earned += 1.0;
                    break;
                }
            }
        }

        return [
            'score' => min($maxPoints, round($earned / $totalUnits * $maxPoints, 2)),
            // A key with no column detail can only score the name unit, so the
            // teacher still has to confirm the mark.
            'requires_manual' => ! self::hasColumnDetail($expectedTables),
        ];
    }

    /**
     * How many units the expected tables are worth.
     *
     * Mirrors exactly what the grading loop can award, so a table with no primary
     * key (a junction table, typically) is not charged for a pk unit the student
     * could never earn.
     *
     * @param  array<int, array<string, mixed>>  $expectedTables
     */
    private static function tableDesignUnits(array $expectedTables): float
    {
        $units = 0.0;

        foreach ($expectedTables as $table) {
            $units += 1.0; // name

            if (isset($table['columns']) && is_array($table['columns'])) {
                $units += 1.0;
            }

            if (isset($table['pk']) && is_string($table['pk']) && trim($table['pk']) !== '') {
                $units += 0.5;
            }

            if (isset($table['fks']) && is_array($table['fks'])) {
                $units += 0.5;
            }
        }

        return $units;
    }

    /**
     * @param  array<int, array<string, mixed>>  $expectedTables
     */
    private static function hasColumnDetail(array $expectedTables): bool
    {
        foreach ($expectedTables as $table) {
            if (isset($table['columns']) && is_array($table['columns'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $studentTables
     * @return array<string, mixed>|null
     */
    private static function findStudentTable(array $studentTables, string $normalizedName): ?array
    {
        foreach ($studentTables as $table) {
            if (self::normalizeLabel($table['name'] ?? '') === $normalizedName) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<int, string>
     */
    private static function columnNames(array $table): array
    {
        $names = [];

        foreach ($table['columns'] ?? [] as $column) {
            $name = self::normalizeLabel($column['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private static function primaryKeyOf(array $table): string
    {
        foreach ($table['columns'] ?? [] as $column) {
            if (strtoupper((string) ($column['type'] ?? '')) === 'PK') {
                return self::normalizeLabel($column['name'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<int, string>
     */
    private static function foreignKeysOf(array $table): array
    {
        $names = [];

        foreach ($table['columns'] ?? [] as $column) {
            if (strtoupper((string) ($column['type'] ?? '')) === 'FK') {
                $name = self::normalizeLabel($column['name'] ?? '');
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        sort($names);

        return $names;
    }

    private static function normalizeLabel(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private static function normalizeAll(array $values): array
    {
        $normalized = array_map(self::normalizeLabel(...), $values);
        sort($normalized);

        return $normalized;
    }
}
