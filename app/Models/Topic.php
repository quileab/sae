<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'name',
        'content',
        'order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }

    protected static function booted()
    {
        static::deleting(function ($topic) {
            $topic->resources()->delete();
        });
    }
}