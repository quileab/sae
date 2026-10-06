<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\EvaluationContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TableDesignGradingTest extends TestCase
{
    /**
     * A fully specified table is worth 3 units (name 1, columns 1, pk 0.5,
     * fks 0.5), so this fixture's two tables plus one relation totals 7 units.
     * Marks are rounded to two decimals.
     */
    private const UNITS_PER_TABLE = 3;

    private const TOTAL_UNITS = 7;

    /**
     * @return array<string, mixed>
     */
    private function block(): array
    {
        return [
            'id' => 'td',
            'type' => 'table_design',
            'points' => 9,
            'answer_key' => [
                'tables' => [
                    [
                        'name' => 'CLIENTES',
                        'columns' => ['ID', 'Nombre', 'Telefono'],
                        'pk' => 'ID',
                        'fks' => [],
                    ],
                    [
                        'name' => 'VENTAS',
                        'columns' => ['ID', 'Fecha', 'ID_Cliente', 'Total'],
                        'pk' => 'ID',
                        'fks' => ['ID_Cliente'],
                    ],
                ],
                'relations' => [
                    ['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N'],
                ],
            ],
        ];
    }

    #[Test]
    public function a_perfect_design_earns_full_points(): void
    {
        $result = EvaluationContent::gradeBlock($this->block(), $this->perfectAnswer());

        $this->assertSame(9.0, $result['score']);
        $this->assertFalse($result['requires_manual']);
    }

    #[Test]
    public function listing_the_table_names_alone_does_not_earn_full_points(): void
    {
        // Regression: the grader used to compare names only, so an empty
        // schema scored 100% and the question was trivially gameable.
        $answer = [
            'tables' => [
                ['name' => 'CLIENTES', 'columns' => []],
                ['name' => 'VENTAS', 'columns' => []],
            ],
            'relations' => [['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N']],
        ];

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        $this->assertLessThan(
            9.0,
            $result['score'],
            'Correct table names with no columns must not score full marks.'
        );
    }

    #[Test]
    public function it_awards_the_table_name_unit_only(): void
    {
        $answer = [
            'tables' => [
                ['name' => 'CLIENTES', 'columns' => []],
                ['name' => 'VENTAS', 'columns' => []],
            ],
            'relations' => [['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N']],
        ];

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // CLIENTES has no expected foreign keys, so the empty sets match (0.5).
        // CLIENTES 1.5 + VENTAS 1.0 + relation 1.0 = 3.5 of 7 units.
        $this->assertSame(self::mark(3.5), $result['score']);
    }

    #[Test]
    public function correct_columns_but_a_wrong_primary_key_lose_the_pk_unit(): void
    {
        $answer = $this->perfectAnswer();

        // Move only VENTAS's primary key from ID to Fecha, leaving the
        // foreign key marker in place so only the pk unit is affected.
        $answer['tables'][1]['columns'][0]['type'] = '';
        $answer['tables'][1]['columns'][1]['type'] = 'PK';

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // CLIENTES 3.0 + VENTAS 2.5 (pk unit lost) + relation 1.0 = 6.5 of 7.
        $this->assertSame(self::mark(6.5), $result['score']);
    }

    #[Test]
    public function a_relation_with_the_wrong_cardinality_loses_credit(): void
    {
        $answer = $this->perfectAnswer();
        $answer['relations'][0]['type'] = 'N:N';

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // Both tables score in full, the relation unit is lost: 6 of 7.
        $this->assertSame(self::mark(6.0), $result['score']);
    }

    #[Test]
    public function a_missing_table_loses_all_of_its_units(): void
    {
        $answer = $this->perfectAnswer();
        array_pop($answer['tables']);

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // VENTAS contributes 3 units, all lost: 4 of 7 remain.
        $this->assertSame(self::mark(4.0), $result['score']);
    }

    #[Test]
    public function wrong_foreign_keys_lose_half_a_unit(): void
    {
        $answer = $this->perfectAnswer();

        // Strip the foreign key marker from ID_Cliente.
        foreach ($answer['tables'][1]['columns'] as $j => $column) {
            if ($column['type'] === 'FK') {
                $answer['tables'][1]['columns'][$j]['type'] = '';
            }
        }

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // CLIENTES 3.0 + VENTAS 2.5 (fk unit lost) + relation 1.0 = 6.5 of 7.
        $this->assertSame(self::mark(6.5), $result['score']);
    }

    #[Test]
    public function a_junction_table_without_a_primary_key_can_still_score_full_marks(): void
    {
        // The unit accounting must not charge for a pk unit the answer key
        // never offers, or a perfect design can never reach full marks.
        $block = [
            'id' => 'td',
            'type' => 'table_design',
            'points' => 10,
            'answer_key' => [
                'tables' => [
                    ['name' => 'VENTAS', 'columns' => ['ID'], 'pk' => 'ID', 'fks' => []],
                    ['name' => 'DETALLE', 'columns' => ['ID_Venta', 'ID_Producto'], 'pk' => null, 'fks' => ['ID_Venta', 'ID_Producto']],
                ],
                'relations' => [['from' => 'VENTAS', 'to' => 'DETALLE', 'type' => '1:N']],
            ],
        ];

        $answer = [
            'tables' => [
                ['name' => 'VENTAS', 'columns' => [['type' => 'PK', 'name' => 'ID']]],
                ['name' => 'DETALLE', 'columns' => [
                    ['type' => 'FK', 'name' => 'ID_Venta'],
                    ['type' => 'FK', 'name' => 'ID_Producto'],
                ]],
            ],
            'relations' => [['from' => 'VENTAS', 'to' => 'DETALLE', 'type' => '1:N']],
        ];

        $result = EvaluationContent::gradeBlock($block, $answer);

        $this->assertSame(10.0, $result['score']);
        $this->assertFalse($result['requires_manual']);
    }

    #[Test]
    public function a_wrong_column_set_loses_the_columns_unit(): void
    {
        $answer = $this->perfectAnswer();
        $answer['tables'][0]['columns'][1]['name'] = 'Apellido';

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        // CLIENTES loses the columns unit: 2.0 + 3.0 + relation 1.0 = 6 of 7.
        $this->assertSame(self::mark(6.0), $result['score']);
    }

    #[Test]
    public function an_answer_key_without_column_detail_defers_to_manual_grading(): void
    {
        $legacy = [
            'id' => 'td',
            'type' => 'table_design',
            'points' => 10,
            'answer_key' => [
                'tables' => [['name' => 'CLIENTES'], ['name' => 'VENTAS']],
                'relations' => [['from' => 'CLIENTES', 'to' => 'VENTAS']],
            ],
        ];

        $result = EvaluationContent::gradeBlock($legacy, [
            'tables' => [['name' => 'CLIENTES', 'columns' => []]],
            'relations' => [],
        ]);

        $this->assertTrue(
            $result['requires_manual'],
            'A legacy answer key must not auto-grade as if names were enough.'
        );
    }

    #[Test]
    public function a_legacy_answer_key_does_not_throw(): void
    {
        $legacy = [
            'id' => 'td',
            'type' => 'table_design',
            'points' => 10,
            'answer_key' => [
                'tables' => [['name' => 'CLIENTES']],
                'relations' => [],
            ],
        ];

        $result = EvaluationContent::gradeBlock($legacy, null);

        $this->assertIsFloat($result['score']);
    }

    #[Test]
    public function table_and_column_matching_is_case_insensitive(): void
    {
        $answer = $this->perfectAnswer();

        foreach ($answer['tables'] as $i => $table) {
            $answer['tables'][$i]['name'] = mb_strtolower($table['name']);
            foreach ($table['columns'] as $j => $column) {
                $answer['tables'][$i]['columns'][$j]['name'] = mb_strtolower($column['name']);
            }
        }

        $this->assertSame(9.0, EvaluationContent::gradeBlock($this->block(), $answer)['score']);
    }

    #[Test]
    public function extra_unexpected_tables_do_not_add_points(): void
    {
        $answer = $this->perfectAnswer();
        $answer['tables'][] = ['name' => 'INVENTADA', 'columns' => [['type' => '', 'name' => 'X']]];

        $this->assertSame(9.0, EvaluationContent::gradeBlock($this->block(), $answer)['score']);
    }

    #[Test]
    public function the_score_never_exceeds_the_block_maximum(): void
    {
        $answer = $this->perfectAnswer();

        // Duplicate everything; the grader must not pay twice for one table.
        $answer['tables'][] = $answer['tables'][0];
        $answer['relations'][] = $answer['relations'][0];

        $result = EvaluationContent::gradeBlock($this->block(), $answer);

        $this->assertLessThanOrEqual(9.0, $result['score']);
    }

    /**
     * Convert earned units into the resulting mark.
     */
    private static function mark(float $earnedUnits): float
    {
        return round($earnedUnits / self::TOTAL_UNITS * 9, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function perfectAnswer(): array
    {
        return [
            'tables' => [
                [
                    'name' => 'CLIENTES',
                    'columns' => [
                        ['type' => 'PK', 'name' => 'ID'],
                        ['type' => '', 'name' => 'Nombre'],
                        ['type' => '', 'name' => 'Telefono'],
                    ],
                ],
                [
                    'name' => 'VENTAS',
                    'columns' => [
                        ['type' => 'PK', 'name' => 'ID'],
                        ['type' => '', 'name' => 'Fecha'],
                        ['type' => 'FK', 'name' => 'ID_Cliente'],
                        ['type' => '', 'name' => 'Total'],
                    ],
                ],
            ],
            'relations' => [
                ['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N'],
            ],
        ];
    }
}
