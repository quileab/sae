<?php

namespace App\Enums;

enum EvaluationGradingMethod: string
{
    case Highest = 'highest';
    case Average = 'average';
    case First = 'first';
    case Last = 'last';

    public function label(): string
    {
        return match ($this) {
            self::Highest => 'Calificación más alta',
            self::Average => 'Promedio de intentos',
            self::First => 'Primer intento',
            self::Last => 'Último intento',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Highest => 'Nota más alta',
            self::Average => 'Promedio',
            self::First => 'Primer intento',
            self::Last => 'Último intento',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($method) => [$method->value => $method->label()])->toArray();
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($method) => [
            'id' => $method->value,
            'name' => $method->value,
            'alias' => $method->label(),
        ])->toArray();
    }
}
