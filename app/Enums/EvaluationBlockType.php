<?php

namespace App\Enums;

enum EvaluationBlockType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case Checkboxes = 'checkboxes';
    case Matching = 'matching';
    case ShortText = 'short_text';
    case OpenText = 'open_text';
    case TableDesign = 'table_design';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Opción Múltiple',
            self::TrueFalse => 'Verdadero / Falso',
            self::Checkboxes => 'Casillas',
            self::Matching => 'Emparejar',
            self::ShortText => 'Texto Breve',
            self::OpenText => 'Desarrollo',
            self::TableDesign => 'Diseño de Base de Datos',
        };
    }

    /**
     * Short label used on the editor block header badge.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Opción Múltiple',
            self::TrueFalse => 'V / F',
            self::Checkboxes => 'Casillas',
            self::Matching => 'Emparejar',
            self::ShortText => 'Texto Breve',
            self::OpenText => 'Desarrollo',
            self::TableDesign => 'Diseño BD',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MultipleChoice => 'o-list-bullet',
            self::TrueFalse => 'o-check-circle',
            self::Checkboxes => 'o-squares-2x2',
            self::Matching => 'o-arrows-right-left',
            self::ShortText => 'o-bars-2',
            self::OpenText => 'o-bars-3-bottom-left',
            self::TableDesign => 'o-table-cells',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->toArray();
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($type) => [
            'id' => $type->value,
            'name' => $type->value,
            'alias' => $type->label(),
        ])->toArray();
    }
}
