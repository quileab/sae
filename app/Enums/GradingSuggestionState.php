<?php

namespace App\Enums;

enum GradingSuggestionState: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Propuesta',
            self::Accepted => 'Aceptada',
            self::Edited => 'Aceptada con ajustes',
            self::Rejected => 'Rechazada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Proposed => 'badge-warning',
            self::Accepted => 'badge-success',
            self::Edited => 'badge-success',
            self::Rejected => 'badge-ghost',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Proposed;
    }

    public function isSettled(): bool
    {
        return $this !== self::Proposed;
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $state) => [$state->value => $state->label()])->toArray();
    }
}
