<?php

namespace App\Enums;

class RoleGroups
{
    /**
     * Roles considered internal staff (non-student users with backoffice access).
     *
     * @var array<int, UserRole>
     */
    public const STAFF = [
        UserRole::Admin,
        UserRole::Principal,
        UserRole::Director,
        UserRole::Administrative,
        UserRole::Teacher,
        UserRole::Preceptor,
        UserRole::Treasurer,
    ];

    /**
     * Roles allowed to manage attendance through the PWA.
     *
     * @var array<int, UserRole>
     */
    public const ATTENDANCE_MANAGERS = [
        UserRole::Preceptor,
        UserRole::Admin,
        UserRole::Principal,
        UserRole::Director,
        UserRole::Administrative,
        UserRole::Teacher,
    ];

    /**
     * Roles allowed to broadcast global messages.
     *
     * @var array<int, UserRole>
     */
    public const GLOBAL_MESSAGERS = [
        UserRole::Admin,
        UserRole::Principal,
        UserRole::Director,
        UserRole::Administrative,
    ];

    /**
     * @return array<int, string>
     */
    public static function values(array $roles): array
    {
        return array_map(static fn (UserRole $role) => $role->value, $roles);
    }
}
