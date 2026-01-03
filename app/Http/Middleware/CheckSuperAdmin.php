<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para verificar que el usuario es Super Admin
 *
 * Los Super Admins tienen acceso completo al sistema y pueden gestionar todos los tenants
 */
class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'No autenticado',
                ]
            ], 401);
        }

        // Verificar si el usuario tiene el rol de super_admin
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'No tienes permisos de Super Administrador',
                ]
            ], 403);
        }

        return $next($request);
    }
}
