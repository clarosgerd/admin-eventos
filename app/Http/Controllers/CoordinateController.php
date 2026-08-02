<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /coordinate (ApiRestEvent, Fase 0), no
 * reimplementa validación.
 */
class CoordinateController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('lat', 'lng'),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/coordinate', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Coordenada creada correctamente.');
    }

    public function update(Request $request, int $coordinate, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/coordinate/{$coordinate}", body: $request->only('lat', 'lng'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Coordenada actualizada correctamente.');
    }

    public function destroy(Request $request, int $coordinate, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/coordinate/{$coordinate}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Coordenada eliminada correctamente.');
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
