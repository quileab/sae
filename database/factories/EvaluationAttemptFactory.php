<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\EvaluationAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationAttempt>
 */
class EvaluationAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_number' => 1,
            'started_at' => now(),
            'submitted_at' => null,
            'status' => AttemptStatus::InProgress->value,
            'score' => null,
            'answers' => [],
            'block_scores' => [],
            'content_snapshot' => null,
            'feedback' => null,
        ];
    }

    public function graded(float $score = 8.0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Graded->value,
            'submitted_at' => now(),
            'score' => $score,
        ]);
    }

    public function submitted(float $score = 6.0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Submitted->value,
            'submitted_at' => now(),
            'score' => $score,
        ]);
    }
}
