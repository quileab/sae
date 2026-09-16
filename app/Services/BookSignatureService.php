<?php

namespace App\Services;

use Illuminate\Support\Str;

class BookSignatureService
{
    /**
     * Mapeo extendido de tipos de obra para bibliotecas escolares.
     */
    public const WORK_TYPES = [
        'general' => ['code' => 'GEN', 'label' => 'Fondo General / Ensayo / Ciencia'],
        'dictionary' => ['code' => 'REF', 'label' => 'Diccionario / Enciclopedia / Referencia'],
        'atlas' => ['code' => 'ATL', 'label' => 'Atlas / Mapas / Cartografía'],
        'children' => ['code' => 'INF', 'label' => 'Literatura Infantil / Cuentos / Primeros Lectores'],
        'juvenile' => ['code' => 'JUV', 'label' => 'Literatura Juvenil / Narrativa 12-18 años'],
        'teacher' => ['code' => 'DOC', 'label' => 'Documentación Docente / Manuales de Profesorado'],
        'periodical' => ['code' => 'REV', 'label' => 'Revistas / Periódicos / Historietas / Cómics'],
        'media' => ['code' => 'MED', 'label' => 'Material Didáctico / Audiovisual / Kits / Láminas'],
    ];

    /**
     * Sanitiza una cadena removiendo acentos, tildes y caracteres especiales.
     */
    public function sanitizeString(?string $input): string
    {
        if (! $input) {
            return '';
        }

        $sanitized = Str::ascii($input);
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $sanitized);

        return strtoupper($sanitized ?? '');
    }

    /**
     * Obtiene el código Cutter de 3 letras (basado en el Apellido del Autor o Título sin artículos).
     */
    public function calculateCutter(?string $author, ?string $title): string
    {
        $target = '';

        if (! empty(trim($author ?? ''))) {
            $target = $this->extractSurname($author);
        } else {
            $target = $this->ignoreInitialArticles($title);
        }

        $sanitized = $this->sanitizeString($target);

        if (empty($sanitized)) {
            return 'XXX';
        }

        return Str::padRight(substr($sanitized, 0, 3), 3, 'X');
    }

    /**
     * Extrae el Apellido del autor entendiendo formatos como 'Apellido, Nombre' o 'Nombre Apellido'.
     */
    public function extractSurname(string $author): string
    {
        $clean = trim($author);

        // Si contiene coma, la parte izquierda es el apellido (ej: "García Márquez, Gabriel")
        if (str_contains($clean, ',')) {
            $parts = explode(',', $clean);

            return trim($parts[0]);
        }

        // Si son varias palabras separar y obtener el apellido (ej: "Gabriel García Márquez" o "Julio Cortázar")
        $words = collect(explode(' ', $clean))->filter()->values();

        if ($words->count() === 1) {
            return $words->first();
        }

        // Si tiene 3 o más palabras (ej: "Gabriel García Márquez"), tomar el primer apellido
        if ($words->count() >= 3) {
            return $words->get(1); // "García"
        }

        // Si tiene 2 palabras (ej: "Julio Cortázar"), tomar la última
        return $words->last();
    }

    /**
     * Elimina artículos iniciales comunes en títulos (El, La, Los, Las, Un, Una, The, A).
     */
    public function ignoreInitialArticles(?string $title): string
    {
        if (! $title) {
            return '';
        }

        $clean = trim($title);
        $articles = ['/^(el|la|los|las|un|una|unos|unas|the|a|an)\s+/i'];

        return preg_replace($articles, '', $clean);
    }

    /**
     * Sanitiza y normaliza la CDU (solo números, puntos, guiones y paréntesis).
     */
    public function formatCdu(?string $cdu): string
    {
        if (! $cdu) {
            return '000';
        }

        $cleaned = preg_replace('/[^0-9.\-()]/', '', $cdu);

        return ! empty($cleaned) ? $cleaned : '000';
    }

    /**
     * Obtiene el código de sección (3 letras) a partir del tipo de obra.
     */
    public function mapSection(?string $workType): string
    {
        if (! $workType || ! isset(self::WORK_TYPES[$workType])) {
            return 'GEN';
        }

        return self::WORK_TYPES[$workType]['code'];
    }

    /**
     * Sanitiza el código de colección (3 letras o 000 por defecto).
     */
    public function formatCollectionCode(?string $collectionCode): string
    {
        if (! $collectionCode || trim($collectionCode) === '') {
            return '000';
        }

        $sanitized = $this->sanitizeString($collectionCode);

        if (empty($sanitized)) {
            return '000';
        }

        return Str::padRight(substr($sanitized, 0, 3), 3, '0');
    }

    /**
     * Formatea el número de ejemplar (E01, E02, etc.).
     */
    public function formatExemplar(int $exemplarNumber): string
    {
        $number = max(1, $exemplarNumber);

        return 'E'.Str::padLeft((string) $number, 2, '0');
    }

    /**
     * Genera la signatura topográfica completa (Smart Key).
     * Formato: [SECCIÓN]-[CDU]-[CUTTER]-[COLECCIÓN]-[EJEMPLAR]
     */
    public function generateSignature(
        ?string $workType,
        ?string $cdu,
        ?string $author,
        ?string $title,
        ?string $collectionCode = '000',
        int $exemplarNumber = 1
    ): string {
        $section = $this->mapSection($workType);
        $formattedCdu = $this->formatCdu($cdu);
        $cutter = $this->calculateCutter($author, $title);
        $collection = $this->formatCollectionCode($collectionCode);
        $exemplar = $this->formatExemplar($exemplarNumber);

        return "{$section}-{$formattedCdu}-{$cutter}-{$collection}-{$exemplar}";
    }

    /**
     * Devuelve las líneas desglosadas para el tejuelo físico.
     */
    public function getSpineLabelData(
        ?string $workType,
        ?string $cdu,
        ?string $author,
        ?string $title,
        ?string $collectionCode = '000',
        int $exemplarNumber = 1
    ): array {
        return [
            'section' => $this->mapSection($workType),
            'cdu' => $this->formatCdu($cdu),
            'cutter' => $this->calculateCutter($author, $title),
            'collection' => $this->formatCollectionCode($collectionCode),
            'exemplar' => $this->formatExemplar($exemplarNumber),
        ];
    }
}
