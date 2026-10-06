<?php

namespace Database\Factories;

use App\Enums\EvaluationGradingMethod;
use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineEvaluation>
 */
class OnlineEvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'duration_minutes' => 60,
            'status' => EvaluationStatus::Draft->value,
            'passing_score' => 6.0,
            'max_attempts' => 1,
            'grading_method' => EvaluationGradingMethod::Highest->value,
            'shuffle_questions' => false,
            'starts_at' => null,
            'ends_at' => null,
            'content' => self::singleChoiceContent(),
        ];
    }

    /**
     * A single-choice evaluation with one graded question.
     */
    public static function singleChoiceContent(string $correct = 'A'): array
    {
        return [
            'sections' => [
                [
                    'id' => 'sec_'.uniqid(),
                    'title' => 'Sección 1',
                    'intro' => '',
                    'blocks' => [
                        [
                            'id' => 'blk_'.uniqid(),
                            'type' => 'multiple_choice',
                            'prompt' => '¿Cuál es la respuesta correcta?',
                            'points' => 1,
                            'options' => [
                                ['key' => 'A', 'text' => 'Correcta'],
                                ['key' => 'B', 'text' => 'Incorrecta'],
                            ],
                            'correct_option' => $correct,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationStatus::Published->value,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationStatus::Draft->value,
        ]);
    }

    public function openFor(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationStatus::Published->value,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationStatus::Published->value,
            'starts_at' => now()->subWeeks(2),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function withAttemptsAllowed(int $attempts): static
    {
        return $this->state(fn (array $attributes) => [
            'max_attempts' => $attempts,
        ]);
    }
}
