<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];
    public function career()
    {
        return $this->belongsTo(Career::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function subjectUsers($subjectId = null){
        return $this->enrollments()->with('user')->where('subject_id', $subjectId);
    }

    // add attribute full_name
    public function getFullNameAttribute()
    {
        return $this->career->name . '» ' . $this->name;
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'enrollments');
    }

    public function getTeacherAttribute()
    {
        // Find the first class session for this subject and return its teacher
        $classSession = $this->hasMany(ClassSession::class)->first();
        return $classSession ? $classSession->teacher : null;
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
