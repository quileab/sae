<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    protected $fillable = [
        'user_id',
        'blood_type',
        'healthcare_provider',
        'healthcare_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_hospital',
        'doctor_name',
        'doctor_phone',
        'allergies',
        'chronic_conditions',
        'current_medication',
        'dietary_restrictions',
        'has_physical_fitness_certificate',
        'physical_fitness_expires_at',
        'observations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_physical_fitness_certificate' => 'boolean',
            'physical_fitness_expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPhysicalFitnessValid(): bool
    {
        if (! $this->has_physical_fitness_certificate) {
            return false;
        }

        if (! $this->physical_fitness_expires_at) {
            return true;
        }

        return $this->physical_fitness_expires_at->isFuture() || $this->physical_fitness_expires_at->isToday();
    }
}
