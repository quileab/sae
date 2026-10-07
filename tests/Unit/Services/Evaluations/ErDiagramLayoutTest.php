<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\ErDiagramLayout;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ErDiagramLayoutTest extends TestCase
{
    /**
     * @param  array<int, string>  $names
     * @return array<int, array<string, mixed>>
     */
    private function tables(array $names): array
    {
        return array_map(fn (string $name) => ['name' => $name, 'rows' => 3], $names);
    }

    /**
     * @param  array<int, array<int, string>>  $bands
     * @return array<int, array<int, string>>
     */
    private function compact(array $bands): array
    {
        return array_map(fn (array $band) => $band['tables'], $bands);
    }

    #[Test]
    public function it_reproduces_the_sales_diagram(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['clientes', 'empleados', 'ventas', 'detalle_ventas', 'productos']),
            [
                ['clave' => 'cliente_ventas', 'from' => 'clientes', 'to' => 'ventas'],
                ['clave' => 'empleado_ventas', 'from' => 'empleados', 'to' => 'ventas'],
                ['clave' => 'ventas_detalle', 'from' => 'ventas', 'to' => 'detalle_ventas'],
                ['clave' => 'producto_detalle', 'from' => 'productos', 'to' => 'detalle_ventas'],
            ],
            [['clientes', 'empleados'], ['ventas'], ['detalle_ventas'], ['productos']]
        );

        $this->assertSame([
            ['clientes', 'empleados'],
            ['ventas'],
            ['detalle_ventas'],
            ['productos'],
        ], $this->compact($bands));
    }

    #[Test]
    public function it_reproduces_the_purchases_diagram(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['proveedores', 'articulos', 'compras', 'detalle_compras']),
            [
                ['clave' => 'proveedor_compras', 'from' => 'proveedores', 'to' => 'compras'],
                ['clave' => 'compras_detalle', 'from' => 'compras', 'to' => 'detalle_compras'],
                ['clave' => 'articulo_detalle', 'from' => 'articulos', 'to' => 'detalle_compras'],
            ],
            [['proveedores', 'articulos'], ['compras'], ['detalle_compras']]
        );

        $this->assertSame([
            ['proveedores', 'articulos'],
            ['compras'],
            ['detalle_compras'],
        ], $this->compact($bands));
    }

    #[Test]
    public function connectors_are_grouped_with_the_band_they_point_at(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['clientes', 'ventas', 'detalle_ventas', 'productos']),
            [
                ['clave' => 'cliente_ventas', 'from' => 'clientes', 'to' => 'ventas'],
                ['clave' => 'ventas_detalle', 'from' => 'ventas', 'to' => 'detalle_ventas'],
                ['clave' => 'producto_detalle', 'from' => 'productos', 'to' => 'detalle_ventas'],
            ],
            [['clientes'], ['ventas'], ['detalle_ventas'], ['productos']]
        );

        $this->assertSame([], $bands[0]['connectors'], 'Nothing points at the first band.');
        $this->assertSame(['cliente_ventas'], array_column($bands[1]['connectors'], 'clave'));
        $this->assertSame(
            ['ventas_detalle', 'producto_detalle'],
            array_column($bands[2]['connectors'], 'clave'),
            'Both connectors of the third band travel together.'
        );
    }

    #[Test]
    public function without_a_declared_layout_tables_fall_back_to_their_declaration_order(): void
    {
        $bands = ErDiagramLayout::bands($this->tables(['a', 'b', 'c']), []);

        $this->assertSame([['a'], ['b'], ['c']], $this->compact($bands));
    }

    #[Test]
    public function a_layout_that_forgets_a_table_still_shows_it(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['clientes', 'ventas', 'detalle']),
            [],
            [['clientes'], ['ventas']]
        );

        $this->assertSame([['clientes'], ['ventas'], ['detalle']], $this->compact($bands));
    }

    #[Test]
    public function a_layout_naming_unknown_tables_does_not_render_them(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['clientes', 'ventas']),
            [],
            [['clientes', 'fantasma'], ['ventas']]
        );

        $this->assertSame([['clientes'], ['ventas']], $this->compact($bands));
    }

    #[Test]
    public function a_table_nothing_connects_still_appears(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['ventas', 'suelta']),
            [],
            [['ventas'], ['suelta']]
        );

        $this->assertSame([['ventas'], ['suelta']], $this->compact($bands));
    }

    #[Test]
    public function cyclic_connectors_do_not_loop_forever(): void
    {
        $bands = ErDiagramLayout::bands(
            $this->tables(['a', 'b']),
            [
                ['clave' => 'ab', 'from' => 'a', 'to' => 'b'],
                ['clave' => 'ba', 'from' => 'b', 'to' => 'a'],
            ],
            [['a'], ['b']]
        );

        $this->assertSame([['a'], ['b']], $this->compact($bands));
        $this->assertSame(
            ['ba'],
            array_column($bands[0]['connectors'], 'clave'),
            'The connector pointing back at the first band still reaches the student.'
        );
        $this->assertSame(['ab'], array_column($bands[1]['connectors'], 'clave'));
    }

    #[Test]
    public function no_tables_yields_no_bands(): void
    {
        $this->assertSame([], ErDiagramLayout::bands([], [['clave' => 'x', 'from' => 'a', 'to' => 'b']]));
    }
}
