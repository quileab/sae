<?php

namespace App\Services\Evaluations;

use App\Enums\GradingFlag;

/**
 * What a filled grading file contains, and what was wrong with it.
 *
 * Parsing never throws: a model that invents structure has to produce something
 * a teacher can read, not an exception.
 */
final class ParsedGradingFile
{
    /**
     * @param  array<string, string>  $attributes  key/value pairs from the SAE header
     * @param  array<int, array{block: string, variant: ?string, key: string, label: string, points: float, levels: string}>  $rubricRows
     * @param  array<int, array{ref: string, block: string, key: ?string, points: float, flags: array<int, GradingFlag>, justification: string}>  $markRows
     * @param  array<int, array{section: string, row: ?int, message: string}>  $errors  the row was rejected
     * @param  array<int, array{section: string, row: ?int, message: string}>  $warnings  the row was kept
     */
    public function __construct(
        public readonly array $attributes = [],
        public readonly array $rubricRows = [],
        public readonly array $markRows = [],
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function evaluationId(): ?int
    {
        return isset($this->attributes['id']) ? (int) $this->attributes['id'] : null;
    }

    public function mode(): ?string
    {
        return $this->attributes['modo'] ?? null;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasContent(): bool
    {
        return $this->rubricRows !== [] || $this->markRows !== [];
    }

    /**
     * Row-level messages already name their table and row, so nothing is appended
     * here. The `row` field stays on the error for callers that need to sort or
     * filter rather than print.
     *
     * @return array<int, string>
     */
    public function errorMessages(): array
    {
        return array_column($this->errors, 'message');
    }
}
