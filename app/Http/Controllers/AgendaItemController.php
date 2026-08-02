<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /agenda-item (ApiRestEvent, Fase 5), no
 * reimplementa validación.
 */
class AgendaItemController extends Controller
{
    private const FIELDS = [
        'form_type_id', 'fecha', 'hora_inicio', 'hora_fin', 'titulo',
        'descripcion', 'ponente', 'ponente_cargo', 'sala', 'icono', 'orden',
    ];

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only(self::FIELDS),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/agenda-item', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Ítem de agenda creado correctamente.');
    }

    public function update(Request $request, int $agenda_item, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/agenda-item/{$agenda_item}", body: $request->only(self::FIELDS));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Ítem de agenda actualizado correctamente.');
    }

    public function destroy(Request $request, int $agenda_item, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/agenda-item/{$agenda_item}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Ítem de agenda eliminado correctamente.');
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
