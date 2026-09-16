<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $table = 'configs';

    protected $guarded = [];

    public $incrementing = false; // evita que el ID al "no ser" autoincrementable mantenga su valor

    protected static function booted(): void
    {
        static::saved(fn ($config) => cache()->forget("config.{$config->id}"));
        static::deleted(fn ($config) => cache()->forget("config.{$config->id}"));
    }

    public static function get(string $id, mixed $default = null): mixed
    {
        return cache()->rememberForever("config.{$id}", function () use ($id, $default) {
            $config = self::find($id);

            return $config ? $config->value : $default;
        });
    }

    public static function getValue($id)
    {
        return Config::where('id', $id)->get();
    }
}
