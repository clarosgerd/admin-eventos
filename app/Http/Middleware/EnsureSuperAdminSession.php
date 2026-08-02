<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda de UX del lado del panel — la autorización real ya la hace
 * ApiRestEvent (AuthorizesEventoScope::assertIsSuperAdmin(), Fase 0). Si
 * esto se salteara, el request igual sería rechazado con 403 del lado de
 * la API.
 */
class EnsureSuperAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((session('admin_user')['rol'] ?? null) !== 'super_admin') {
            abort(403, 'Esta sección requiere rol super_admin.');
        }

        return $next($request);
    }
}
