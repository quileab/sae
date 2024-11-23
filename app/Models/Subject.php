<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    public function career()
    {
        return $this->belongsTo(Career::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
