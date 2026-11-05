<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Inscription;
use Illuminate\Support\Collection;

class InscriptionCatalog
{
    public const TYPE_SINGLE = 'csv-1';

    public const TYPE_MULTIPLE = 'csv-n';

    /**
     * Obtiene el catálogo del ADMIN para un período y un conjunto de materias.
     *
     * @param  array<int, int|string>  $subjectIds
     * @return Collection<int|string, Inscription>
     */
    public function forSubjects(string $periodId, array $subjectIds): Collection
    {
        return Inscription::query()
            ->where('configs_id', $periodId)
            ->where('user_id', Inscription::CATALOG_USER_ID)
            ->whereIn('subject_id', $subjectIds)
            ->get()
            ->keyBy('subject_id');
    }

    /**
     * Convierte el valor libre del catálogo en una lista de opciones.
     *
     * Los valores usados como marcador de posición (solo guiones, puntos o
     * espacios, sin letras ni números) se consideran vacíos: la materia no
     * tiene opciones cargadas.
     *
     * @return list<string>
     */
    public function options(?string $value): array
    {
        if (! $this->hasMeaningfulContent($value)) {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn (string $option): string => trim($option))
            ->filter(fn (string $option): bool => $option !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Indica si el valor tiene contenido real (al menos una letra o número).
     */
    public function hasMeaningfulContent(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return preg_match('/[\p{L}\p{N}]/u', $value) === 1;
    }

    /**
     * Normaliza una selección simple o múltiple a una lista de strings.
     *
     * @return list<string>
     */
    public function normalizeSelection(mixed $selection): array
    {
        if (is_array($selection)) {
            $values = $selection;
        } elseif ($selection === null || trim((string) $selection) === '') {
            $values = [];
        } else {
            $values = [(string) $selection];
        }

        return collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function mode(?Inscription $catalogRow): string
    {
        return $catalogRow?->type === self::TYPE_MULTIPLE
            ? self::TYPE_MULTIPLE
            : self::TYPE_SINGLE;
    }

    public function isOpen(string $periodId): bool
    {
        return Config::query()
            ->whereKey($periodId)
            ->where('group', 'inscriptions')
            ->where('value', 'true')
            ->exists();
    }
}
