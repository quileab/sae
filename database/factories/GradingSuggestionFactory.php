<?php

namespace Database\Factories;

use App\Enums\GradingFlag;
use App\Enums\GradingSuggestionState;
use App\Models\EvaluationAttempt;
use App\Models\GradingSuggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingSuggestion>
 */
class GradingSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Deliberately no default: a suggestion without an attempt is
            // meaningless, and the attempt graph needs a subject the factory
            // cannot invent.
            'block_id' => 'g1_1',
            'rubric_version' => 1,
            'rubric_hash' => sha1('g1_1'),
            'agent' => 'assistant',
            'criteria' => [
                ['key' => 'c1', 'label' => 'Distingue dato de información', 'points_awarded' => 0.75, 'points_max' => 0.75, 'justification' => 'Define el concepto y lo aplica al caso.'],
                ['key' => 'c2', 'label' => 'Describe el proceso de transformación', 'points_awarded' => 0.5, 'points_max' => 0.75, 'justification' => 'Menciona filtros y agrupamientos, sin cálculo.'],
            ],
            'total_points' => 1.25,
            'max_points' => 1.5,
            'flags' => [],
            'confidence' => 80,
            'state' => GradingSuggestionState::Proposed->value,
            'revision' => 1,
            'history' => [],
            'disagreement_reason' => null,
            'final_points' => null,
            'raw_response' => null,
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }

    public function forAttempt(EvaluationAttempt $attempt): static
    {
        return $this->state(fn (array $attributes) => [
            'evaluation_attempt_id' => $attempt->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => GradingSuggestionState::Proposed->value,
            'accepted_at' => null,
            'accepted_by' => null,
        ]);
    }

    public function flagged(GradingFlag ...$flags): static
    {
        return $this->state(fn (array $attributes) => [
            'flags' => array_map(fn (GradingFlag $flag) => $flag->value, $flags),
        ]);
    }

    public function accepted(float $finalPoints = 1.25): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => GradingSuggestionState::Accepted->value,
            'final_points' => $finalPoints,
            'accepted_at' => now(),
            'accepted_by' => User::factory(),
        ]);
    }
}
