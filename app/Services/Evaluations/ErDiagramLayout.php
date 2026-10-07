<?php

namespace App\Services\Evaluations;

/**
 * Resolves the visual bands of the relational design diagram.
 *
 * The source exercises were hand-drawn ASCII diagrams whose vertical order is a
 * pedagogical choice, not a derivable property: in the sales exercise
 * `productos` is a source table (nothing points at it) yet it is drawn last,
 * below the table it feeds. Guessing the order from the connector graph puts it
 * next to the other two sources and changes the diagram.
 *
 * So the arrangement is declared alongside the block:
 *
 *   'layout' => [['clientes', 'empleados'], ['ventas'], ['detalle_ventas'], ['productos']]
 *
 * When a block declares no layout, tables fall back to their declaration order,
 * one per band, which is a faithful and predictable default.
 *
 * Connectors are grouped with the band their target introduces, so each band
 * renders directly above the arrows pointing at the band below it.
 */
final class ErDiagramLayout
{
    /**
     * @param  array<int, array<string, mixed>>  $tables
     * @param  array<int, array{clave: string, from: string, to: string}>  $relationPairs
     * @param  array<int, array<int, string>>|null  $layout  declared bands of table names
     * @return array<int, array{tables: array<int, string>, connectors: array<int, array{clave: string, from: string, to: string}>}>
     */
    public static function bands(array $tables, array $relationPairs, ?array $layout = null): array
    {
        $names = array_values(array_filter(array_map(
            fn ($table) => (string) ($table['name'] ?? ''),
            $tables
        )));

        if ($names === []) {
            return [];
        }

        $bands = self::declaredBands($layout, $names) ?? self::declarationOrder($names);

        return self::attachConnectors($bands, $relationPairs, $names);
    }

    /**
     * Declared bands, ignoring names that no longer exist and appending any table
     * the layout forgot so nothing silently disappears from the diagram.
     *
     * @param  array<int, array<int, string>>|null  $layout
     * @param  array<int, string>  $names
     * @return array<int, array<int, string>>|null
     */
    private static function declaredBands(?array $layout, array $names): ?array
    {
        if ($layout === null || $layout === []) {
            return null;
        }

        $seen = [];
        $bands = [];

        foreach ($layout as $band) {
            $band = array_values(array_intersect(is_array($band) ? $band : [], $names));

            if ($band === []) {
                continue;
            }

            foreach ($band as $name) {
                $seen[$name] = true;
            }

            $bands[] = $band;
        }

        if ($bands === []) {
            return null;
        }

        $missing = array_values(array_diff($names, array_keys($seen)));

        foreach ($missing as $name) {
            $bands[] = [$name];
        }

        return $bands;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array<int, string>>
     */
    private static function declarationOrder(array $names): array
    {
        return array_map(fn (string $name) => [$name], $names);
    }

    /**
     * Group each connector with the band holding its target.
     *
     * @param  array<int, array<int, string>>  $bands
     * @param  array<int, array{clave: string, from: string, to: string}>  $relationPairs
     * @param  array<int, string>  $names
     * @return array<int, array{tables: array<int, string>, connectors: array<int, array{clave: string, from: string, to: string}>}>
     */
    private static function attachConnectors(array $bands, array $relationPairs, array $names): array
    {
        $result = array_map(fn (array $band) => ['tables' => $band, 'connectors' => []], $bands);

        foreach ($relationPairs as $pair) {
            $to = (string) ($pair['to'] ?? '');

            if ($to === '' || ! in_array($to, $names, true)) {
                continue;
            }

            foreach ($result as $index => $band) {
                if (in_array($to, $band['tables'], true)) {
                    $result[$index]['connectors'][] = $pair;

                    continue 2;
                }
            }
        }

        return $result;
    }
}
