<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\Schema;

class BookContainerService
{
    /**
     * Catálogo maestro de todos los tipos de encuadernación/contenedor de selección única.
     */
    public const ALL_CONTAINERS = [
        'Rústica / Tapa Blanda',
        'Tapa Dura / Cartoné',
        'Espiral / Anillado',
        'Engrapado / Grapado',
        'Todo Cartón / Board Book',
        'Libro de Tela / Baño',
        'Bolsillo / Pocket',
        'Carpeta de Argollas / Bibliorato',
        'Hojas Sueltas en Carpeta',
        'Revista / Cuadernillo',
        'Tomo Encuadernado',
        'Caja de Cartón / Plástico',
        'Estuche Rígido / Funda',
        'Carpeta de Láminas / Mapas',
        'Bolsa Plástica / Ziploc',
        'Otro / Especial',
    ];

    /**
     * Obtiene la lista de opciones para un dropdown <x-select> de selección única.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getOptions(): array
    {
        $all = self::ALL_CONTAINERS;

        try {
            if (Schema::hasColumn('books', 'container')) {
                $dbContainers = Book::query()
                    ->select('container')
                    ->whereNotNull('container')
                    ->where('container', '!=', '')
                    ->distinct()
                    ->pluck('container')
                    ->filter()
                    ->toArray();

                $all = array_merge($all, $dbContainers);
            }
        } catch (\Exception $e) {
            // Silence exception
        }

        return collect($all)
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => ! empty($item))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn ($item) => ['id' => $item, 'name' => $item])
            ->all();
    }
}
