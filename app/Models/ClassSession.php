<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = [
        'subject_id',
        'date',
        'teacher_id',
        'class_number',
        'unit',
        'type',
        'content',
        'activities',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

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

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'class_session_id', 'user_id');
    }

    public static function count($subject_id, ?int $year = null): int
    {
        $year ??= session('cycle_id') ?? date('Y');

        return self::where('subject_id', $subject_id)
            ->whereYear('date', $year)
            ->count();
    }
}
