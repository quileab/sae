<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Student = 'student';
    case Teacher = 'teacher';
    case Director = 'director';
    case Administrative = 'administrative';
    case Treasurer = 'treasurer';
    case User = 'user';
    case Preceptor = 'preceptor';
    case Principal = 'principal';
    case BasicUser = 'basic_user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'ADMIN',
            self::Student => 'Estudiante',
            self::Teacher => 'Profesor',
            self::Director => 'Director',
            self::Administrative => 'Administrativo',
            self::Treasurer => 'Tesorero',
            self::User => 'Usuario',
            self::Preceptor => 'Preceptor',
            self::Principal => 'Principal',
            self::BasicUser => 'Usuario básico',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()])->toArray();
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($role) => [
            'id' => $role->value, // Keeping 'id' key for backward compatibility if needed in frontend
            'name' => $role->value,
            'alias' => $role->label(),
        ])->toArray();
    }
}
