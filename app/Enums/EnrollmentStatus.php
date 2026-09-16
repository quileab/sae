<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Completed => 'Completado',
            self::Withdrawn => 'Retirado',
        };
    }
}
