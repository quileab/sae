<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\EvaluationContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The table_design rubric, checked against the submissions the source exercises
 * actually produced.
 *
 * Shapes and grades come from:
 *   eval3gestion/js/eval.js  ce={clientes:3,empleados:3,ventas:4,detalle_ventas:5,productos:4}
 *   entregas-eval3gestion/BUYATTI_MARIASOL-*.json   nota 10  (perfect)
 *   entregas-eval3gestion/OTTINGER_SOL-*.json        nota 9   (one key mislabelled)
 *   entregas-eval3gestion/DELGADO_GRACIELA-*.json    nota 7   (renamed primary keys)
 *   entregas-eval3gestion/SARTOR_GEORGINA-*.json     nota 1   (blank)
 */
class TableDesignGradingTest extends TestCase
{
    /**
     * The sales exercise answer key.
     *
     * @return array<string, mixed>
     */
    private function salesBlock(): array
    {
        return [
            'id' => 'g2_1',
            'type' => 'table_design',
            'points' => 10,
            'relation_pairs' => [
                ['clave' => 'cliente_ventas', 'from' => 'clientes', 'to' => 'ventas'],
                ['clave' => 'empleado_ventas', 'from' => 'empleados', 'to' => 'ventas'],
                ['clave' => 'ventas_detalle', 'from' => 'ventas', 'to' => 'detalle_ventas'],
                ['clave' => 'producto_detalle', 'from' => 'productos', 'to' => 'detalle_ventas'],
            ],
            'answer_key' => [
                'tables' => [
                    ['name' => 'clientes', 'columns' => ['ID', 'Nombre', 'Teléfono'], 'pk' => 'ID', 'fks' => []],
                    ['name' => 'empleados', 'columns' => ['ID', 'Nombre', 'Cargo'], 'pk' => 'ID', 'fks' => []],
                    ['name' => 'ventas', 'columns' => ['ID', 'Fecha_Venta', 'ID_Cliente', 'ID_Empleado'], 'pk' => 'ID', 'fks' => ['ID_Cliente', 'ID_Empleado']],
                    ['name' => 'detalle_ventas', 'columns' => ['ID', 'ID_Venta', 'ID_Producto', 'Cantidad_Vendida', 'Precio_Unitario'], 'pk' => 'ID', 'fks' => ['ID_Venta', 'ID_Producto']],
                    ['name' => 'productos', 'columns' => ['ID', 'Descripcion', 'Precio_Unitario', 'Stock'], 'pk' => 'ID', 'fks' => []],
                ],
                'relations' => [
                    ['clave' => 'cliente_ventas', 'type' => '1:N'],
                    ['clave' => 'empleado_ventas', 'type' => '1:N'],
                    ['clave' => 'ventas_detalle', 'type' => '1:N'],
                    ['clave' => 'producto_detalle', 'type' => '1:N'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: string}>>  $rows
     * @return array<int, array{name: string, columns: array<int, array{type: string, name: string}>}>
     */
    private function tables(array $rows): array
    {
        $tables = [];

        foreach ($rows as $name => $columns) {
            $tables[] = [
                'name' => $name,
                'columns' => array_map(
                    fn (array $cell) => ['type' => $cell[0], 'name' => $cell[1]],
                    $columns
                ),
            ];
        }

        return $tables;
    }

    /**
     * @return array<int, array{clave: string, type: string}>
     */
    private function relations(string ...$types): array
    {
        $claves = ['cliente_ventas', 'empleado_ventas', 'ventas_detalle', 'producto_detalle'];
        $result = [];

        foreach ($claves as $index => $clave) {
            $result[] = ['clave' => $clave, 'type' => $types[$index] ?? ''];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function perfectAnswer(): array
    {
        return [
            'tables' => $this->tables([
                'clientes' => [['PK', 'ID'], ['', 'Nombre'], ['', 'Teléfono']],
                'empleados' => [['PK', 'ID'], ['', 'Nombre'], ['', 'Cargo']],
                'ventas' => [['PK', 'ID'], ['', 'Fecha_Venta'], ['FK', 'ID_Cliente'], ['FK', 'ID_Empleado']],
                'detalle_ventas' => [['PK', 'ID'], ['FK', 'ID_Venta'], ['FK', 'ID_Producto'], ['', 'Cantidad_Vendida'], ['', 'Precio_Unitario']],
                'productos' => [['PK', 'ID'], ['', 'Descripcion'], ['', 'Precio_Unitario'], ['', 'Stock']],
            ]),
            'relations' => $this->relations('1:N', '1:N', '1:N', '1:N'),
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function grade(array $answer): array
    {
        $block = $this->salesBlock();

        return EvaluationContent::gradeTableDesignDetailed($block, $answer, (float) $block['points']);
    }

    #[Test]
    public function a_perfect_submission_earns_every_point(): void
    {
        $result = $this->grade($this->perfectAnswer());

        $this->assertSame(10.0, $result['score']);
        $this->assertFalse($result['requires_manual']);

        foreach ($result['breakdown'] as $dimension) {
            $this->assertSame(
                $dimension['total'],
                $dimension['earned'],
                "{$dimension['label']} should be fully earned."
            );
        }
    }

    #[Test]
    public function a_single_mislabelled_foreign_key_costs_half_a_unit(): void
    {
        // OTTINGER: ID_Producto marked PK instead of FK in detalle_ventas.
        $answer = $this->perfectAnswer();
        $answer['tables'][3]['columns'][2]['type'] = 'PK';

        $result = $this->grade($answer);

        $this->assertLessThan(10.0, $result['score']);
        $this->assertSame(5.0, $result['breakdown']['distribution']['earned'], 'Placement was perfect.');
        $this->assertSame(4.5, $result['breakdown']['keys']['earned'], 'Only the fk half-unit was lost.');
        $this->assertSame(4.0, $result['breakdown']['relations']['earned']);

        $this->assertTrue(
            $result['per_table']['detalle_ventas']['pk'],
            'ID is still the first primary key, so the pk half-unit survives.'
        );
        $this->assertFalse(
            $result['per_table']['detalle_ventas']['fks'],
            'ID_Producto is no longer marked as a foreign key.'
        );
    }

    #[Test]
    public function renaming_the_primary_keys_costs_more_than_one_mislabelled_cell(): void
    {
        // DELGADO: descriptive names used as primary keys, generic ID used as fk.
        $answer = [
            'tables' => $this->tables([
                'clientes' => [['PK', 'ID_Cliente'], ['', 'Nombre'], ['', 'Teléfono']],
                'empleados' => [['PK', 'ID_Empleado'], ['', 'Nombre'], ['', 'Cargo']],
                'ventas' => [['PK', 'ID_Venta'], ['', 'Fecha_Venta'], ['FK', 'ID'], ['FK', 'ID']],
                'detalle_ventas' => [['PK', 'ID'], ['FK', 'ID'], ['FK', 'ID_Producto'], ['', 'Cantidad_Vendida'], ['', 'Precio_Unitario']],
                'productos' => [['FK', 'ID'], ['', 'Descripcion'], ['', 'Precio_Unitario'], ['', 'Stock']],
            ]),
            'relations' => $this->relations('1:N', '1:N', '1:N', '1:N'),
        ];

        $delgado = $this->grade($answer);
        $ottinger = $this->grade($this->perfectAnswer());
        $ottinger['breakdown']['keys']['earned'] = 4.5;

        $this->assertLessThan(
            4.5,
            $delgado['breakdown']['keys']['earned'],
            'Renaming every primary key should score below a single mislabelled cell.'
        );
        $this->assertLessThan(
            $this->grade($this->perfectAnswer())['score'] * 0.8,
            $delgado['score'],
            'Systematic key errors should lose a large share of the marks.'
        );
    }

    #[Test]
    public function a_wrong_relation_cardinality_costs_one_unit(): void
    {
        // VENTURINI: marked Ventas -> Detalle as 1:1 when it is 1:N.
        $answer = $this->perfectAnswer();
        $answer['relations'][2]['type'] = '1:1';

        $result = $this->grade($answer);

        $this->assertSame(5.0, $result['breakdown']['distribution']['earned']);
        $this->assertSame(5.0, $result['breakdown']['keys']['earned']);
        $this->assertSame(3.0, $result['breakdown']['relations']['earned']);
        $this->assertFalse($result['per_relation']['ventas_detalle']);
        $this->assertTrue($result['per_relation']['cliente_ventas']);
    }

    #[Test]
    public function a_blank_submission_scores_zero(): void
    {
        $result = $this->grade(['tables' => [], 'relations' => []]);

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['requires_manual']);
    }

    #[Test]
    public function repeating_a_token_inside_one_table_does_not_double_count(): void
    {
        // The pool genuinely repeats "ID", so a table may legitimately hold it
        // more than once; the comparison must stay a set comparison.
        $answer = $this->perfectAnswer();
        $answer['tables'][0]['columns'][] = ['type' => '', 'name' => 'ID'];

        $result = $this->grade($answer);

        $this->assertSame(10.0, $result['score']);
    }

    #[Test]
    public function table_and_attribute_matching_ignores_case_and_padding(): void
    {
        $answer = $this->perfectAnswer();

        foreach ($answer['tables'] as $index => $table) {
            $answer['tables'][$index]['name'] = '  '.mb_strtoupper($table['name']).' ';
            foreach ($table['columns'] as $columnIndex => $column) {
                $answer['tables'][$index]['columns'][$columnIndex]['name'] = mb_strtolower($column['name']);
            }
        }

        $this->assertSame(10.0, $this->grade($answer)['score']);
    }

    #[Test]
    public function relations_are_matched_by_connector_so_the_origin_cannot_be_spoofed(): void
    {
        $answer = $this->perfectAnswer();
        // A submission claiming clientes -> clientes must not satisfy any connector.
        $answer['relations'][0] = ['clave' => 'cliente_ventas', 'type' => '1:N', 'from' => 'clientes', 'to' => 'clientes'];

        $result = $this->grade($answer);

        $this->assertTrue(
            $result['per_relation']['cliente_ventas'],
            'The connector is declared by the exercise; the answer only supplies its cardinality.'
        );
    }

    #[Test]
    public function an_extra_table_is_ignored_rather_than_credited(): void
    {
        $answer = $this->perfectAnswer();
        $answer['tables'][] = ['name' => 'inventada', 'columns' => [['type' => 'PK', 'name' => 'ID']]];

        $this->assertSame(10.0, $this->grade($answer)['score']);
    }

    #[Test]
    public function a_table_missing_from_the_submission_scores_nothing(): void
    {
        $answer = $this->perfectAnswer();
        array_splice($answer['tables'], 1, 1);

        $result = $this->grade($answer);

        $this->assertTrue($result['per_table']['empleados']['missing']);
        $this->assertSame(4.0, $result['breakdown']['distribution']['earned']);
        $this->assertSame(4.0, $result['breakdown']['keys']['earned']);
    }

    #[Test]
    public function a_junction_table_without_a_primary_key_can_still_earn_full_marks(): void
    {
        // Charging for a pk unit the answer key never offers capped a perfect
        // design below full marks.
        $block = [
            'id' => 'td', 'type' => 'table_design', 'points' => 10,
            'relation_pairs' => [['clave' => 'p_d', 'from' => 'pedidos', 'to' => 'detalle']],
            'answer_key' => [
                'tables' => [
                    ['name' => 'pedidos', 'columns' => ['ID'], 'pk' => 'ID', 'fks' => []],
                    ['name' => 'detalle', 'columns' => ['ID_Pedido', 'ID_Articulo'], 'pk' => null, 'fks' => ['ID_Pedido', 'ID_Articulo']],
                ],
                'relations' => [['clave' => 'p_d', 'type' => '1:N']],
            ],
        ];

        $answer = [
            'tables' => $this->tables([
                'pedidos' => [['PK', 'ID']],
                'detalle' => [['FK', 'ID_Pedido'], ['FK', 'ID_Articulo']],
            ]),
            'relations' => [['clave' => 'p_d', 'type' => '1:N']],
        ];

        $result = EvaluationContent::gradeTableDesignDetailed($block, $answer, 10.0);

        $this->assertSame(10.0, $result['score']);
        $this->assertNull($result['per_table']['detalle']['pk'], 'No pk is expected for the junction table.');
    }

    #[Test]
    public function an_answer_key_without_column_detail_defers_to_manual_grading(): void
    {
        $legacy = [
            'id' => 'td', 'type' => 'table_design', 'points' => 10,
            'answer_key' => [
                'tables' => [['name' => 'clientes'], ['name' => 'ventas']],
                'relations' => [['clave' => 'c_v', 'type' => '1:N']],
            ],
        ];

        $result = EvaluationContent::gradeTableDesignDetailed($legacy, [
            'tables' => [['name' => 'clientes', 'columns' => []]],
            'relations' => [],
        ], 10.0);

        $this->assertTrue(
            $result['requires_manual'],
            'A legacy answer key must not be auto-marked off table names alone.'
        );
    }

    #[Test]
    public function free_origin_destination_relations_still_grade(): void
    {
        // Content saved before connectors were declared.
        $legacy = [
            'id' => 'td', 'type' => 'table_design', 'points' => 4,
            'answer_key' => [
                'tables' => [['name' => 'ventas', 'columns' => ['ID'], 'pk' => 'ID', 'fks' => []]],
                'relations' => [['from' => 'ventas', 'to' => 'detalle', 'type' => '1:N']],
            ],
        ];

        $result = EvaluationContent::gradeTableDesignDetailed($legacy, [
            'tables' => [['name' => 'ventas', 'columns' => [['type' => 'PK', 'name' => 'ID']]]],
            'relations' => [['from' => 'ventas', 'to' => 'detalle', 'type' => '1:N']],
        ], 4.0);

        $this->assertSame(4.0, $result['score']);
        $this->assertFalse($result['requires_manual']);
    }

    #[Test]
    public function grade_block_keeps_the_flat_contract_for_the_grader(): void
    {
        $block = $this->salesBlock();

        $result = EvaluationContent::gradeBlock($block, $this->perfectAnswer());

        $this->assertSame(['score', 'requires_manual'], array_keys($result));
        $this->assertSame(10.0, $result['score']);
    }
}
