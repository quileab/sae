<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'start',
        'end',
        'color',
        'user_id',
        'subject_id',
        'description',
        'presidente_id',
        'vocal1_id',
        'vocal2_id',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function presidente()
    {
        return $this->belongsTo(User::class, 'presidente_id');
    }

    public function vocal1()
    {
        return $this->belongsTo(User::class, 'vocal1_id');
    }

    public function vocal2()
    {
        return $this->belongsTo(User::class, 'vocal2_id');
    }
}
