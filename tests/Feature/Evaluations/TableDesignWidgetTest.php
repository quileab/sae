<?php

namespace Tests\Feature\Evaluations;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The relational design widget's attribute bookkeeping lives in JavaScript, so
 * it is exercised by driving the real module through Node.
 *
 * The pool repeats tokens on purpose: five tables share a generic "ID" primary
 * key. Treating it as a set (rather than removing one occurrence per placement)
 * deletes all five "ID" tokens the moment one is placed, leaving the other
 * four tables unable to receive a primary key.
 */
class TableDesignWidgetTest extends TestCase
{
    private const SALES_POOL = [
        'ID', 'ID', 'ID', 'ID', 'ID', 'Nombre', 'Nombre', 'Cargo', 'Descripcion',
        'Fecha_Venta', 'ID_Cliente', 'ID_Empleado', 'ID_Venta', 'ID_Producto',
        'Cantidad_Vendida', 'Precio_Unitario', 'Precio_Unitario', 'Stock', 'Teléfono',
    ];

    #[Test]
    public function placing_a_token_removes_only_one_copy_from_the_pool(): void
    {
        $report = $this->drive(function (string $body): string {
            return $body;
        }, <<<'JS'
            ctx.studentTables[0].columns[0].name = 'ID';
            ctx.studentTables[1].columns[0].name = 'ID';
            ctx.studentTables[2].columns[0].name = 'ID';
            return String(ctx.remainingAttributes().filter((a) => a === 'ID').length);
        JS);

        $this->assertSame('2', trim($report), 'Three IDs placed must leave two of five.');
    }

    #[Test]
    public function the_pool_is_empty_only_once_every_copy_is_placed(): void
    {
        $remaining = $this->drive(fn (string $body) => $body, <<<'JS'
            ctx.studentTables.forEach((table) => { table.columns[0].name = 'ID'; });
            return String(ctx.remainingAttributes().filter((a) => a === 'ID').length);
        JS);

        $this->assertSame('0', trim($remaining));
    }

    #[Test]
    public function the_attribute_dropdown_still_offers_the_remaining_copies(): void
    {
        $available = $this->drive(fn (string $body) => $body, <<<'JS'
            ctx.studentTables[0].columns[0].name = 'ID';
            return String(ctx.optionsFor('').includes('ID'));
        JS);

        $this->assertSame('true', trim($available), 'One ID must not remove ID from every other table.');
    }

    #[Test]
    public function a_used_token_disappears_from_the_dropdown_once_it_is_exhausted(): void
    {
        $available = $this->drive(fn (string $body) => $body, <<<'JS'
            ctx.studentTables.forEach((table) => { table.columns[0].name = 'Stock'; });
            return String(ctx.optionsFor('').includes('Stock'));
        JS);

        $this->assertSame('false', trim($available));
    }

    #[Test]
    public function the_current_value_stays_selectable_even_once_it_is_used_up(): void
    {
        $available = $this->drive(fn (string $body) => $body, <<<'JS'
            ctx.studentTables.forEach((table) => { table.columns[0].name = 'ID'; });
            return String(ctx.optionsFor('ID').includes('ID'));
        JS);

        $this->assertSame('true', trim($available), 'A cell must keep showing the value it already holds.');
    }

    #[Test]
    public function the_empty_cell_counter_matches_the_unplaced_slots(): void
    {
        $counts = $this->drive(fn (string $body) => $body, <<<'JS'
            const total = ctx.studentTables.reduce((sum, t) => sum + t.columns.length, 0);
            ctx.studentTables[1].columns[0].name = 'Nombre';
            return total + ':' + ctx.emptyCells();
        JS);

        $this->assertSame('19:18', trim($counts), 'The sales exercise has 19 slots.');
    }

    /**
     * Runs a snippet against the real Alpine component factory.
     */
    private function drive(callable $_unused, string $snippet): string
    {
        $module = base_path('resources/js/evaluation-table-design.js');

        if (! is_readable($module)) {
            $this->markTestSkipped('El módulo del widget no está disponible.');
        }

        // The module source is inlined rather than passed as an argument:
        // node executes its first argument, so a path there would be run
        // instead of the harness.
        $script = <<<'JS'
            let factory;
            global.Alpine = { data: (name, fn) => { factory = fn; } };
            global.document = { addEventListener: (event, callback) => callback() };

            MODULE_SOURCE

            const ctx = Object.assign({}, factory({}), {
                $wire: { get: () => null, set: () => {}, call: () => {} },
            });

            ctx.tables = [
                { name: 'clientes', rows: 3 },
                { name: 'empleados', rows: 3 },
                { name: 'ventas', rows: 4 },
                { name: 'detalle_ventas', rows: 5 },
                { name: 'productos', rows: 4 },
            ];
            ctx.attributes = ATTRIBUTES;
            ctx.init();

            process.stdout.write(String((function () { SNIPPET })()));
        JS;

        $script = str_replace(
            ['MODULE_SOURCE', 'ATTRIBUTES', 'SNIPPET'],
            [(string) file_get_contents($module), json_encode(self::SALES_POOL, JSON_UNESCAPED_UNICODE), $snippet],
            $script
        );

        $file = tempnam(sys_get_temp_dir(), 'sae_widget_').'.js';
        file_put_contents($file, $script);

        try {
            $command = sprintf('node %s 2>&1', escapeshellarg($file));

            $output = [];
            $status = 0;
            exec($command, $output, $status);
        } finally {
            @unlink($file);
        }

        if ($status !== 0) {
            $this->markTestSkipped('Node no está disponible para ejercitar el widget.');
        }

        return trim(implode(PHP_EOL, $output));
    }
}
