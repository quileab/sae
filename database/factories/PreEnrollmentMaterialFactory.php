<?php

namespace Database\Factories;

use App\Models\PreEnrollmentMaterial;
use App\Services\AcademicCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentMaterial>
 */
class PreEnrollmentMaterialFactory extends Factory
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
            'career_id' => null,
            'title' => $this->faker->sentence(3),
            'file_path' => 'pre_enrollment_materials/'.$this->faker->uuid().'.pdf',
            'original_name' => $this->faker->word().'.pdf',
        ];
    }
}
