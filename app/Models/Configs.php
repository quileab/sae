<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configs extends Model
{
    protected $guarded = [];

    public $incrementing = false; // evita que el ID al "no ser" autoincrementable mantenga su valor

    public static function getValue($id)
    {
        //return User::where('pid','>','1000000')->count();
        return Configs::where('id', $id)->get();
    }
}