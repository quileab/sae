<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, $permission=null)
    {
        if (Auth::user() && Auth::user()->hasPermission($permission)) {
            return $next($request);
        }
        abort(403, 'No tienes permiso para realizar esta acción.');
    }

    protected function hasPermission($user, $permission)
    {
        // Lógica para validar los permisos del usuario
        return $user->hasPermission($permission);
    }
}
