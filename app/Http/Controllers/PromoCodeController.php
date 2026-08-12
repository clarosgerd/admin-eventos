<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /promo-code (ApiRestEvent, Fase 0), no
 * reimplementa validación. Códigos de un evento existente (a diferencia
 * de la Fase 2, que los crea anidados dentro de POST /event solo al
 * momento de la alta).
 */
class PromoCodeController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('promo_code', 'price', 'discount_type', 'discount_percent'),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/promo-code', body: $payload);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#promos' hace que al volver se reabra la pestaña de
        // Promos en vez de caer siempre en "Datos" (la primera).
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#promos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#promos')->with('status', 'Código de promoción creado correctamente.');
    }

    public function update(Request $request, int $promo_code, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/promo-code/{$promo_code}", body: $request->only('promo_code', 'price', 'discount_type', 'discount_percent'));

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#promos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#promos')->with('status', 'Código de promoción actualizado correctamente.');
    }

    public function destroy(Request $request, int $promo_code, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/promo-code/{$promo_code}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#promos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#promos')->with('status', 'Código de promoción eliminado correctamente.');
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
