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
            case 'open_text':
                // A rubric that cannot be marked is dropped rather than kept half
                // usable: the assistant grader would silently misaward against it.
                if (isset($block['rubric'])) {
                    $block['rubric'] = Rubric::fromBlock($block)?->toArray();
                }
                break;
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
                // Fixed connectors: the student picks a cardinality per declared
                // pair instead of inventing an origin and a target.
                if (! isset($block['relation_pairs']) || ! is_array($block['relation_pairs'])) {
                    $block['relation_pairs'] = [];
                }
                if (! isset($block['answer_key']) || ! is_array($block['answer_key'])) {
                    $block['answer_key'] = ['tables' => [], 'relations' => []];
                }
                if (! isset($block['answer_key']['tables']) || ! is_array($block['answer_key']['tables'])) {
                    $block['answer_key']['tables'] = [];
                }
                if (! isset($block['answer_key']['relations']) || ! is_array($block['answer_key']['relations'])) {
                    $block['answer_key']['relations'] = [];
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
     * Grade a relational schema design exercise.
     *
     * The rubric is split into three dimensions, mirroring how these exercises
     * were actually marked by hand:
     *
     *   distribution  1.0 per table whose set of attributes matches
     *   keys          0.5 per table with the right primary key
     *                  0.5 per table with the right set of foreign keys
     *   relations     1.0 per connector with the right cardinality
     *
     * Keeping distribution and keys apart matters: a submission can place every
     * attribute in the right table and still lose points for labelling them,
     * which is exactly the distinction the written feedback used to make ("el
     * diseño lógico es correcto, pero las claves están mal etiquetadas").
     *
     * Answer keys stored before column detail existed cannot be graded
     * reliably, so they are flagged for manual review instead of auto-marked.
     *
     * @param  array<string, mixed>  $block
     * @return array{score: float, requires_manual: bool}
     */
    protected static function gradeTableDesign(array $block, mixed $answer, float $maxPoints): array
    {
        $detailed = self::gradeTableDesignDetailed($block, $answer, $maxPoints);

        return [
            'score' => $detailed['score'],
            'requires_manual' => $detailed['requires_manual'],
        ];
    }

    /**
     * The table_design rubric with a per-dimension and per-item breakdown, so a
     * teacher can see exactly why a submission lost points.
     *
     * @param  array<string, mixed>  $block
     * @return array{
     *     score: float,
     *     requires_manual: bool,
     *     breakdown: array<string, array{label: string, earned: float, total: float}>,
     *     per_table: array<string, array{columns: bool, pk: bool, fks: bool, missing: bool}>,
     *     per_relation: array<string, bool>
     * }
     */
    public static function gradeTableDesignDetailed(array $block, mixed $answer, float $maxPoints): array
    {
        $expectedTables = $block['answer_key']['tables'] ?? [];
        $expectedRelations = $block['answer_key']['relations'] ?? [];

        $studentTables = is_array($answer) ? ($answer['tables'] ?? []) : [];
        $studentRelations = is_array($answer) ? ($answer['relations'] ?? []) : [];

        $distribution = 0.0;
        $keys = 0.0;
        $perTable = [];

        foreach ($expectedTables as $expectedTable) {
            $match = self::findStudentTable($studentTables, self::normalizeLabel($expectedTable['name'] ?? ''));

            // A key with no column expectation can only be matched by name.
            $expectsColumns = isset($expectedTable['columns']) && is_array($expectedTable['columns']);
            $expectsPk = self::declares($expectedTable['pk'] ?? null);
            $expectsFks = isset($expectedTable['fks']) && is_array($expectedTable['fks']);

            $columnsMatch = false;
            $pkMatch = false;
            $fksMatch = false;

            if ($match !== null) {
                if (! $expectsColumns) {
                    $distribution += 1.0;
                    $columnsMatch = true;
                } else {
                    $columnsMatch = self::normalizeAll(self::columnNames($match))
                        === self::normalizeAll($expectedTable['columns']);
                    $distribution += $columnsMatch ? 1.0 : 0.0;

                    if ($expectsPk) {
                        $pkMatch = self::primaryKeyOf($match) === self::normalizeLabel($expectedTable['pk']);
                        $keys += $pkMatch ? 0.5 : 0.0;
                    }

                    if ($expectsFks) {
                        $fksMatch = self::normalizeAll(self::foreignKeysOf($match))
                            === self::normalizeAll($expectedTable['fks']);
                        $keys += $fksMatch ? 0.5 : 0.0;
                    }
                }
            }

            $perTable[$expectedTable['name'] ?? 'tabla_'.count($perTable)] = [
                'columns' => $columnsMatch,
                'pk' => $expectsPk ? $pkMatch : null,
                'fks' => $expectsFks ? $fksMatch : null,
                'missing' => $match === null,
            ];
        }

        $relations = 0.0;
        $perRelation = [];

        foreach ($expectedRelations as $expectedRelation) {
            $clave = $expectedRelation['clave'] ?? null;

            $matched = $clave !== null
                ? self::relationTypeByClave($studentRelations, $clave) === self::normalizeLabel($expectedRelation['type'] ?? '')
                : self::matchesLegacyRelation($studentRelations, $expectedRelation);

            $relations += $matched ? 1.0 : 0.0;
            $perRelation[(string) ($clave ?? (($expectedRelation['from'] ?? '').' → '.($expectedRelation['to'] ?? '')))] = $matched;
        }

        $totalUnits = self::tableDesignUnits($expectedTables) + count($expectedRelations);
        $earned = $distribution + $keys + $relations;

        $distributionTotal = (float) count($expectedTables);
        $keysTotal = 0.0;

        foreach ($expectedTables as $expectedTable) {
            if (self::declares($expectedTable['pk'] ?? null)) {
                $keysTotal += 0.5;
            }

            if (isset($expectedTable['fks']) && is_array($expectedTable['fks'])) {
                $keysTotal += 0.5;
            }
        }

        return [
            'score' => $totalUnits > 0 ? min($maxPoints, round($earned / $totalUnits * $maxPoints, 2)) : 0.0,
            'requires_manual' => $totalUnits === 0 || ! self::hasColumnDetail($expectedTables),
            'breakdown' => [
                'distribution' => [
                    'label' => 'Distribución de atributos',
                    'earned' => $distribution,
                    'total' => $distributionTotal,
                ],
                'keys' => [
                    'label' => 'Claves PK y FK',
                    'earned' => $keys,
                    'total' => $keysTotal,
                ],
                'relations' => [
                    'label' => 'Relaciones',
                    'earned' => $relations,
                    'total' => (float) count($expectedRelations),
                ],
            ],
            'per_table' => $perTable,
            'per_relation' => $perRelation,
        ];
    }

    /**
     * Total units the expected tables are worth.
     *
     * Mirrors exactly what the grading loop can award, which for a fully specified
     * table is 1.0 distribution + 0.5 primary key + 0.5 foreign keys. A junction
     * table with no primary key is therefore worth 1.5, not 2.0: charging for a
     * pk unit the student could never earn is what previously capped a perfect
     * design below full marks.
     *
     * @param  array<int, array<string, mixed>>  $expectedTables
     */
    private static function tableDesignUnits(array $expectedTables): float
    {
        $units = 0.0;

        foreach ($expectedTables as $table) {
            // Distribution, or merely "the table exists" when the key declares no
            // columns. Either way it is worth one unit.
            $units += 1.0;

            if (self::declares($table['pk'] ?? null)) {
                $units += 0.5;
            }

            if (isset($table['fks']) && is_array($table['fks'])) {
                $units += 0.5;
            }
        }

        return $units;
    }

    private static function declares(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
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
     * Locate the student's table matching an expected one.
     *
     * The widget sends a list of {name, columns}, but the array key is accepted
     * as a fallback so a shape drift degrades to a visible mismatch instead of a
     * silent zero.
     *
     * @param  array<array-key, mixed>  $studentTables
     * @return array<string, mixed>|null
     */
    private static function findStudentTable(array $studentTables, string $normalizedName): ?array
    {
        foreach ($studentTables as $key => $table) {
            if (! is_array($table)) {
                continue;
            }

            $name = $table['name'] ?? (is_string($key) ? $key : '');

            if (self::normalizeLabel($name) === $normalizedName) {
                return $table;
            }
        }

        return null;
    }

    /**
     * The cardinality the student chose for a fixed connector.
     *
     * @param  array<int, array<string, mixed>>  $studentRelations
     */
    private static function relationTypeByClave(array $studentRelations, string $clave): string
    {
        foreach ($studentRelations as $relation) {
            if (($relation['clave'] ?? null) === $clave) {
                return self::normalizeLabel($relation['type'] ?? '');
            }
        }

        return '';
    }

    /**
     * Fallback for relations stored as free origin/destination pairs.
     *
     * @param  array<int, array<string, mixed>>  $studentRelations
     * @param  array<string, mixed>  $expectedRelation
     */
    private static function matchesLegacyRelation(array $studentRelations, array $expectedRelation): bool
    {
        $from = self::normalizeLabel($expectedRelation['from'] ?? '');
        $to = self::normalizeLabel($expectedRelation['to'] ?? '');

        foreach ($studentRelations as $relation) {
            if (! is_array($relation)) {
                continue;
            }

            if (
                self::normalizeLabel($relation['from'] ?? '') === $from
                && self::normalizeLabel($relation['to'] ?? '') === $to
            ) {
                return self::normalizeLabel($relation['type'] ?? '') === self::normalizeLabel($expectedRelation['type'] ?? '');
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<int, string>
     */
    private static function columnNames(array $table): array
    {
        $names = [];

        foreach ($table['columns'] ?? [] as $column) {
            $name = is_array($column) ? self::normalizeLabel($column['name'] ?? '') : self::normalizeLabel((string) $column);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private static function primaryKeyOf(array $table): string
    {
        foreach ($table['columns'] ?? [] as $column) {
            if (is_array($column) && strtoupper((string) ($column['type'] ?? '')) === 'PK') {
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
            if (is_array($column) && strtoupper((string) ($column['type'] ?? '')) === 'FK') {
                $name = self::normalizeLabel($column['name'] ?? '');

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private static function normalizeLabel(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * Case-insensitive, deduplicated and sorted, so a pool that repeats a token
     * (several tables sharing a generic "ID") still compares as a set.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private static function normalizeAll(array $values): array
    {
        $normalized = array_values(array_unique(array_map(self::normalizeLabel(...), $values)));
        sort($normalized);

        return $normalized;
    }
}
