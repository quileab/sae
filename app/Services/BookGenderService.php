<?php

namespace App\Services;

use Illuminate\Support\Str;

class BookGenderService
{
    /**
     * Catálogo estandarizado de géneros literarios y materias escolares.
     */
    public const SUGGESTED_GENRES = [
        // Ficción y Literatura
        'Novela Realista',
        'Novela Histórica',
        'Novela Policial',
        'Novela de Aventuras',
        'Ciencia Ficción',
        'Fantasía',
        'Cuentos / Relatos',
        'Literatura Infantil',
        'Literatura Juvenil',
        'Poesía / Lírica',
        'Teatro / Dramaturgia',
        'Historieta / Cómic / Manga',
        'Mitos y Leyendas',
        'Fábulas',

        // Ciencias Exactas y Naturales
        'Ciencias Naturales',
        'Biología',
        'Botánica / Zoología',
        'Física',
        'Química',
        'Matemáticas',
        'Astronomía / Espacio',
        'Geología / Ecología',

        // Ciencias Sociales y Humanidades
        'Historia Universal',
        'Historia Argentina',
        'Geografía / Cartografía',
        'Filosofía / Ética',
        'Ciudadanía / Derecho',
        'Sociología / Cultura',
        'Educación / Pedagogía',

        // Arte, Deportes y Referencia
        'Arte / Música / Dibujo',
        'Deportes / Educación Física',
        'Diccionario / Enciclopedia',
    ];

    /**
     * Convierte una cadena delimitada por comas en un arreglo de géneros limpios.
     *
     * @return array<int, string>
     */
    public function toArray(?string $genderString): array
    {
        if (! $genderString || trim($genderString) === '') {
            return [];
        }

        $items = explode(',', $genderString);

        return collect($items)
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => ! empty($item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Convierte un arreglo de géneros en una cadena formateada limpia.
     *
     * @param  array<int, string>  $genres
     */
    public function toString(array $genres): string
    {
        return collect($genres)
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => ! empty($item))
            ->unique()
            ->implode(', ');
    }

    /**
     * Filtra sugerencias por término de búsqueda y las devuelve ordenadas alfabéticamente.
     *
     * @return array<int, string>
     */
    public function filterSuggestions(string $search = ''): array
    {
        $genres = self::SUGGESTED_GENRES;

        if (trim($search) !== '') {
            $term = Str::ascii(strtolower(trim($search)));
            $genres = collect($genres)
                ->filter(fn ($genre) => str_contains(Str::ascii(strtolower($genre)), $term))
                ->all();
        }

        return collect($genres)
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
