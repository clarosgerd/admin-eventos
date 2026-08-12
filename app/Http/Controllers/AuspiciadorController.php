<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /auspiciador (ApiRestEvent, Fase 5), no
 * reimplementa validación.
 */
class AuspiciadorController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('nombre', 'logo_url', 'contacto', 'orden'),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/auspiciador', body: $payload);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#auspiciadores' hace que al volver se reabra esa
        // pestaña en vez de caer siempre en "Datos" (la primera).
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#auspiciadores')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#auspiciadores')->with('status', 'Auspiciador creado correctamente.');
    }

    public function update(Request $request, int $auspiciador, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/auspiciador/{$auspiciador}", body: $request->only('nombre', 'logo_url', 'contacto', 'orden'));

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#auspiciadores')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#auspiciadores')->with('status', 'Auspiciador actualizado correctamente.');
    }

    public function destroy(Request $request, int $auspiciador, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/auspiciador/{$auspiciador}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#auspiciadores')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#auspiciadores')->with('status', 'Auspiciador eliminado correctamente.');
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
