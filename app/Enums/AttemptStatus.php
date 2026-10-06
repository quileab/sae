<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En curso',
            self::Submitted => 'Para corregir',
            self::Graded => 'Corregido',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'badge-ghost',
            self::Submitted => 'badge-warning',
            self::Graded => 'badge-success',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::InProgress;
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
