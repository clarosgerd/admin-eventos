<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /route (ApiRestEvent, Fase 0), no
 * reimplementa validación.
 */
class RouteController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('lat', 'lng', 'label'),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/route', body: $payload);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#mapa' agrupa Coordenadas + Ruta en una sola pestaña.
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#mapa')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#mapa')->with('status', 'Punto de ruta creado correctamente.');
    }

    public function update(Request $request, int $route, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/route/{$route}", body: $request->only('lat', 'lng', 'label'));

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#mapa')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#mapa')->with('status', 'Punto de ruta actualizado correctamente.');
    }

    public function destroy(Request $request, int $route, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/route/{$route}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#mapa')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#mapa')->with('status', 'Punto de ruta eliminado correctamente.');
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
