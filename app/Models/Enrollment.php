<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Services\EnrollmentAcademicStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $table = 'enrollments';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'subject_id',
        'status',
        'final_grade',
        'final_grade_date',
        'modalities_id',
        'observations',
        'attendance_percentage',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'final_grade' => 'decimal:2',
            'final_grade_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Obtiene el estado académico calculado.
     * Nota: Para procesos masivos, use EnrollmentAcademicStatusService directamente.
     */
    public function getAcademicStatusAttribute(): array
    {
        return app(EnrollmentAcademicStatusService::class)->calculate($this);
    }
}
