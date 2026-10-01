<?php

namespace App\Enums;

enum PreEnrollmentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Observed = 'observed';
    case Enrolled = 'enrolled';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Submitted => 'Enviado',
            self::Validated => 'Validado',
            self::Observed => 'Observado',
            self::Enrolled => 'Matriculado',
            self::Rejected => 'Rechazado',
            self::Waitlisted => 'En espera',
        };
    }
}
