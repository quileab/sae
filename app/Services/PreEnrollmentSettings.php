<?php

namespace App\Services;

use App\Models\Config;

class PreEnrollmentSettings
{
    /**
     * ID de la configuración que habilita el formulario público de preinscripción.
     */
    public const OPEN_CONFIG_ID = 'pre_enrollment_open';

    /**
     * Indica si el formulario público de preinscripción está habilitado.
     *
     * Ante la ausencia de la configuración asume abierto, para no bloquear el
     * trámite por un problema de datos.
     */
    public static function isOpen(): bool
    {
        return filter_var(Config::get(self::OPEN_CONFIG_ID, 'true'), FILTER_VALIDATE_BOOLEAN);
    }
}
