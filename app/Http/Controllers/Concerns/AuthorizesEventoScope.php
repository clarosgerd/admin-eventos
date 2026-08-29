<?php

namespace App\Http\Controllers\Concerns;

/**
 * Admin de evento asignado a varios eventos (28/08/2026) — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md.
 *
 * Consolida acá los 13 `assertCanViewEvento()` que hasta esta fecha
 * estaban duplicados idénticos (uno privado por controller — nunca se
 * factorizaron a un trait, a diferencia del lado de ApiRestEvent, que sí
 * tiene su propio `AuthorizesEventoScope`). `session('admin_user')`
 * ahora trae `eventoIds` (evento principal + eventos adicionales,
 * calculado del lado de ApiRestEvent — ver `AdminUser::eventoIds()`),
 * poblado en `AuthController::login()`.
 */
trait AuthorizesEventoScope
{
    protected function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && !in_array($evento, $admin['eventoIds'] ?? [], true)) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
