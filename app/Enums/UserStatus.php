<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case OnLeave = 'on_leave';
    case Passive = 'passive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Graduated => 'Egresado',
            self::Withdrawn => 'Baja',
            self::OnLeave => 'De Licencia',
            self::Passive => 'Pasivo',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-success',
            self::Graduated => 'badge-info',
            self::Withdrawn => 'badge-error',
            self::OnLeave => 'badge-warning',
            self::Passive => 'badge-neutral',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->toArray();
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($status) => [
            'id' => $status->value,
            'name' => $status->value,
            'alias' => $status->label(),
        ])->toArray();
    }
}
