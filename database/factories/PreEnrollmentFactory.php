<?php

namespace Database\Factories;

use App\Enums\PreEnrollmentStatus;
use App\Models\Career;
use App\Models\PreEnrollment;
use App\Services\AcademicCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollment>
 */
class PreEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cycle_id' => AcademicCycle::enrollmentCycle(),
            'career_id' => Career::factory(),
            'doc_type' => 'DNI',
            'doc_number' => $this->faker->unique()->numerify('########'),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->numerify('##########'),
            'payload' => [
                'fname' => $this->faker->firstName(),
                'lname' => $this->faker->lastName(),
                'fnacimiento' => $this->faker->date('Y-m-d', '-12 years'),
            ],
            'status' => PreEnrollmentStatus::Submitted->value,
        ];
    }

    public function status(PreEnrollmentStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status->value,
        ]);
    }
}
