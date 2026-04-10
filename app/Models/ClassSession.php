<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $guarded = [];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsTo
    {
        return $this->belongsToMany(User::class, 'enrollments', 'class_session_id', 'user_id');
    }

    public static function count($subject_id, ?int $year = null): int
    {
        $year ??= session('cycle_id') ?? session('cycle') ?? date('Y');

        return self::where('subject_id', $subject_id)
            ->whereYear('date', $year)
            ->count();
    }
}
