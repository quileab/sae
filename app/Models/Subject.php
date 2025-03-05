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

    // add attribute full_name
    public function getFullNameAttribute()
    {
        return $this->name . ' / ' . $this->career->name;
    }
}
