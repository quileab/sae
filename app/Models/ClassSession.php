<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public static function count($subject_id): int
    {
        // check if session cycle is set, if not set it to the current year
        if (!session()->has('cycle')) {
            session(['cycle' => date('Y')]);
        }
        return \App\Models\ClassSession::where('subject_id', $subject_id)
            // between dates
            ->whereBetween('date', [session('cycle') . '-01-01', session('cycle') . '-12-31'])
            ->count();
    }
}
