<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    /**
     * Usuario ADMIN que guarda el catálogo de opciones de inscripción.
     */
    public const CATALOG_USER_ID = 1;

    protected $table = 'inscriptions';

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
