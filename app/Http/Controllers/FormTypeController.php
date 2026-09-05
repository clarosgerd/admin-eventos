<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama al endpoint SUELTO /form-type
 * (ApiRestEvent, Fase 0), no al nested POST /event que usa
 * EventoController::store() (Fase 2). Ojo: los nombres de campo son
 * distintos entre los dos endpoints —
 * acá es snake_case (has_team/has_delivery/requiere_categoria) + event_id
 * explícito, el nested usa camelCase (hasTeam/hasDelivery) y toma el
 * evento_id del padre. No compartir lógica de armado de payload entre
 * los dos controladores.
 */
class FormTypeController extends Controller
{
    private const FIELDS = [
        'name', 'icon', 'imagen_url', 'description', 'tipo', 'cupo_total', 'precio_base',
        'costo_edicion', 'tiempo_expiracion_min', 'color',
    ];

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only(self::FIELDS),
            [
                'event_id'           => $evento,
                'requiere_categoria' => $request->boolean('requiere_categoria'),
                'has_team'           => $request->boolean('has_team'),
                'has_delivery'       => $request->boolean('has_delivery'),
                'has_donation'       => $request->boolean('has_donation'),
                'has_promo_code'     => $request->boolean('has_promo_code'),
                // Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md
                'es_staff'           => $request->boolean('es_staff'),
                // Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md
                'es_ponente'         => $request->boolean('es_ponente'),
                // Caja para eventos tipo congreso (20/08/2026) — checkbox
                // "checked" por default en el <form> de alta (mismo
                // criterio que requiere_categoria), así que a diferencia
                // de los demás booleans de este payload SÍ importa que el
                // request->boolean() lea el checkbox real, no un default
                // fijo acá.
                'requiere_contacto_emergencia' => $request->boolean('requiere_contacto_emergencia'),
                // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de
                // formulario (01/09/2026) — es un array, no un boolean;
                // "nada tildado" ya manda [] naturalmente, no necesita el
                // patrón request->boolean() de los flags de arriba.
                'campos_ocultos'     => $request->input('campos_ocultos', []),
                // Edición restringida a solo souvenirs/talleres (04/09/2026).
                'edicion_solo_extras' => $request->boolean('edicion_solo_extras'),
            ]
        );

        $response = $client->forward('POST', '/form-type', body: $payload);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#tipos' hace que al volver se reabra la pestaña de Tipos
        // de formulario en vez de caer siempre en "Datos" (la primera).
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#tipos')->with('status', 'Tipo de formulario creado correctamente.');
    }

    public function update(Request $request, int $form_type, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only(self::FIELDS),
            [
                'requiere_categoria' => $request->boolean('requiere_categoria'),
                'has_team'           => $request->boolean('has_team'),
                'has_delivery'       => $request->boolean('has_delivery'),
                'has_donation'       => $request->boolean('has_donation'),
                'has_promo_code'     => $request->boolean('has_promo_code'),
                // Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md
                'es_staff'           => $request->boolean('es_staff'),
                // Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md
                'es_ponente'         => $request->boolean('es_ponente'),
                // Caja para eventos tipo congreso (20/08/2026).
                'requiere_contacto_emergencia' => $request->boolean('requiere_contacto_emergencia'),
                // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de
                // formulario (01/09/2026) — es un array, no un boolean;
                // "nada tildado" ya manda [] naturalmente.
                'campos_ocultos'     => $request->input('campos_ocultos', []),
                // Edición restringida a solo souvenirs/talleres (04/09/2026).
                'edicion_solo_extras' => $request->boolean('edicion_solo_extras'),
            ]
        );

        $response = $client->forward('PUT', "/form-type/{$form_type}", body: $payload);

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#tipos')->with('status', 'Tipo de formulario actualizado correctamente.');
    }

    public function destroy(Request $request, int $form_type, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/form-type/{$form_type}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#tipos')->with('status', 'Tipo de formulario eliminado correctamente.');
    }

    private function extractErrors($response): array
    {
        if (!$response) {
            return ['general' => 'No se pudo conectar con el servidor.'];
        }

        $errors = $response->json('errors');
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}
