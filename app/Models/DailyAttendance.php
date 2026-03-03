<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'absence_value' => 'float',
        ];
    }

    /** @return array<string, float> */
    public static function absenceValues(): array
    {
        return [
            'present' => 0.0,
            'late' => 0.5,       // Jornada simple; 0.25 para completa
            'early_leave' => 0.5, // Jornada simple; 0.25 para completa
            'absent' => 1.0,     // Jornada simple; 0.5 para completa
            'half_absent' => 0.5, // Siempre 0.5
        ];
    }

    public static function calculateAbsenceValue(string $status, string $shiftType = 'simple'): float
    {
        if ($status === 'present') {
            return 0.0;
        }

        if ($status === 'half_absent') {
            return 0.5;
        }

        if ($shiftType === 'complete') {
            return match ($status) {
                'late', 'early_leave' => 0.25,
                'absent' => 0.5,
                default => 0.0,
            };
        }

        return match ($status) {
            'late', 'early_leave' => 0.5,
            'absent' => 1.0,
            default => 0.0,
        };
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
