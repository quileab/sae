<?php

namespace App\Traits;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait AuthorizesAccess
{
    /**
     * Get the target user based on provided ID or current authenticated user.
     * Prevents students from targeting others.
     */
    protected function getTargetUser(?int $userId = null): User
    {
        $auth = Auth::user();

        if (! $userId || $auth->hasRole('student')) {
            return $auth;
        }

        return User::find($userId) ?: $auth;
    }

    /**
     * Ensure the current user has access to a specific subject.
     */
    protected function authorizeSubject(int $subjectId): void
    {
        if (! Auth::user()->hasSubject($subjectId)) {
            abort(403, 'No tienes permiso para acceder a esta materia.');
        }
    }

    /**
     * Ensure the current user has administrative or teaching roles.
     */
    protected function authorizeStaff(): void
    {
        if (! Auth::user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor', 'treasurer', 'teacher'])) {
            abort(403, 'Acceso denegado: Se requieren permisos administrativos o docentes.');
        }
    }

    /**
     * Ensure the current user can manage a specific user's data.
     */
    protected function authorizeUserManagement(int $targetUserId): void
    {
        $auth = Auth::user();
        if ($auth->id !== $targetUserId && ! $auth->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403, 'No tienes permiso para gestionar los datos de este usuario.');
        }
    }

    /**
     * Get the current cycle ID from session or default to current year.
     */
    protected function getCycleId(): int
    {
        return session('cycle_id', (int) date('Y'));
    }
}
