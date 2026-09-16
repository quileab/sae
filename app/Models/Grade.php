<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Grade extends Model
{
    protected $fillable = [
        'user_id',
        'class_session_id',
        'grade',
        'approved',
        'attendance',
        'type',
        'recovered_grade_id',
        'comments',
    ];

    const TYPE_REGULAR = 'regular';

    const TYPE_EVALUATION = 'evaluation';

    const TYPE_PRACTICAL_WORK = 'practical_work';

    const TYPE_RECOVERY = 'recuperatory';

    public function isEvaluation(): bool
    {
        return $this->type === self::TYPE_EVALUATION
            || str_starts_with(strtolower((string) $this->comments), 'ev');
    }

    public function isPracticalWork(): bool
    {
        return $this->type === self::TYPE_PRACTICAL_WORK
            || str_starts_with(strtolower((string) $this->comments), 'tp');
    }

    public function isRecovery(): bool
    {
        return $this->type === self::TYPE_RECOVERY;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function recoveredGrade(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recovered_grade_id');
    }

    public function recovery(): HasOne
    {
        return $this->hasOne(self::class, 'recovered_grade_id');
    }

    // casts
    protected $casts = [
        'approved' => 'boolean',
    ];
}
