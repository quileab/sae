<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscriptions extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false;

    public function subject()
    {
        return $this->belongsTo('App\Models\Subject');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
    public function career()
    {
        return $this->belongsTo('App\Models\Career');
    }
}
