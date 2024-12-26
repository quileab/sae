<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    // guarded none
    protected $guarded = [];
    //cast integer to boolean
    protected $casts = [
        'allow_enrollments' => 'boolean',
        'allow_evaluations' => 'boolean'
    ];

}
