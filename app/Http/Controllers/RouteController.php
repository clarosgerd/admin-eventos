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

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Punto de ruta creado correctamente.');
    }

    public function update(Request $request, int $route, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/route/{$route}", body: $request->only('lat', 'lng', 'label'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Punto de ruta actualizado correctamente.');
    }

    public function destroy(Request $request, int $route, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/route/{$route}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Punto de ruta eliminado correctamente.');
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
