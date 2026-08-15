<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caja de cobro presencial (14/08/2026) — ver
 * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. El rol `cajero` tiene permisos
 * mínimos: solo el módulo de Caja de su propio evento. La autorización
 * real ya la hace ApiRestEvent (AuthorizesEventoScope::assertCanOperarCaja()
 * / assertCanWriteEvento()) — esto es solo la guarda de UX del panel, para
 * que un cajero ni siquiera vea el resto de las pantallas al navegar por
 * URL directa.
 */
class RestrictCajeroToCaja
{
    public function handle(Request $request, Closure $next): Response
    {
        $rol = session('admin_user')['rol'] ?? null;

        if ($rol === 'cajero' && !$request->routeIs('caja.*') && !$request->routeIs('logout')) {
            $eventoId = session('admin_user')['evento_id'] ?? null;

            return $eventoId
                ? redirect()->route('caja.index', $eventoId)
                : abort(403, 'Tu usuario no tiene un evento asignado.');
        }

        return $next($request);
    }
}
