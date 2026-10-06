<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicada',
            self::Closed => 'Cerrada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-warning',
            self::Published => 'badge-success',
            self::Closed => 'badge-error',
        };
    }

    /**
     * Whether students may open and submit an attempt in this state.
     */
    public function acceptsAttempts(): bool
    {
        return $this === self::Published;
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
