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

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Auspiciador creado correctamente.');
    }

    public function update(Request $request, int $auspiciador, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/auspiciador/{$auspiciador}", body: $request->only('nombre', 'logo_url', 'contacto', 'orden'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Auspiciador actualizado correctamente.');
    }

    public function destroy(Request $request, int $auspiciador, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/auspiciador/{$auspiciador}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Auspiciador eliminado correctamente.');
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
