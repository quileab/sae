<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class InscriptionPdfService
{
    /**
     * Nombre de archivo usado históricamente para los PDFs de inscripción.
     */
    public static function fileName(int $studentId, int $careerId, string $periodId): string
    {
        return "insc-{$studentId}-{$careerId}-{$periodId}-.pdf";
    }

    /**
     * Ruta relativa al disco local (storage/app/private).
     */
    public static function storagePath(int $studentId, int $careerId, string $periodId): string
    {
        return 'private/inscriptions/'.self::fileName($studentId, $careerId, $periodId);
    }

    public static function exists(int $studentId, int $careerId, string $periodId): bool
    {
        return Storage::exists(self::storagePath($studentId, $careerId, $periodId));
    }

    public static function delete(int $studentId, int $careerId, string $periodId): void
    {
        Storage::delete(self::storagePath($studentId, $careerId, $periodId));
    }

    /**
     * Ruta absoluta para servir un archivo ya guardado.
     */
    public static function absolutePath(string $fileName): string
    {
        return Storage::disk('local')->path('private/inscriptions/'.$fileName);
    }
}
