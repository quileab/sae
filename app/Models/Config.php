<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Config extends Model
{
    protected $table = 'configs';

    protected $guarded = [];

    public $incrementing = false; // evita que el ID al "no ser" autoincrementable mantenga su valor

    protected static function booted(): void
    {
        static::saved(fn ($config) => self::forgetCache($config));
        static::deleted(fn ($config) => self::forgetCache($config));
    }

    private static function forgetCache(Config $config): void
    {
        cache()->forget("config.{$config->id}");
        cache()->forget("configs.{$config->group}");
    }

    public static function get(string $id, mixed $default = null): mixed
    {
        return cache()->rememberForever("config.{$id}", function () use ($id, $default) {
            $config = self::find($id);

            return $config ? $config->value : $default;
        });
    }

    /**
     * Devuelve todas las configuraciones de un grupo, cacheadas hasta que alguna cambie.
     */
    public static function getGroup(string $group): Collection
    {
        return cache()->rememberForever("configs.{$group}", function () use ($group) {
            return self::where('group', $group)->get();
        });
    }

    public static function getValue($id)
    {
        return Config::where('id', $id)->get();
    }
}
