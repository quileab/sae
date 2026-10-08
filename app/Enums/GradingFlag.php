<?php

namespace App\Enums;

/**
 * Reasons an assistant grader flagged an answer.
 *
 * Flag values are part of the file contract with the external model, so the
 * string values must stay stable.
 */
enum GradingFlag: string
{
    case InstructionInjection = 'instruction_injection';
    case OffTopic = 'off_topic';
    case InsufficientLength = 'insufficient_length';
    case ContradictsRubric = 'contradicts_rubric';
    case Excellent = 'excellent';

    public function label(): string
    {
        return match ($this) {
            self::InstructionInjection => 'Intento de instrucción',
            self::OffTopic => 'Fuera de tema',
            self::InsufficientLength => 'Respuesta insuficiente',
            self::ContradictsRubric => 'Contradice la rúbrica',
            self::Excellent => 'Excelente',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::InstructionInjection => 'badge-error',
            self::OffTopic, self::InsufficientLength => 'badge-warning',
            self::ContradictsRubric => 'badge-neutral',
            self::Excellent => 'badge-success',
        };
    }

    /**
     * Flags that put a suggestion at the front of the review queue.
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [
            self::InstructionInjection,
            self::OffTopic,
            self::InsufficientLength,
            self::ContradictsRubric,
        ], true);
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $flag) => [$flag->value => $flag->label()])->toArray();
    }
}
