<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de equipos por evento (01/09/2026) — llama a
 * /event/{event}/equipos y /equipo/{equipo} (ApiRestEvent), mismo patrón
 * que AuspiciadorController/SouvenirController. store() acepta un textarea
 * de un nombre por línea (mismo endpoint bulk que ya existía en la API,
 * antes solo alcanzable pegándole directo).
 */
class EquipoController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $nombres = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('nombres')))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->values()
            ->all();

        if (empty($nombres)) {
            return redirect(route('eventos.edit', $evento) . '#equipos')
                ->withErrors(['general' => 'Escribí al menos un nombre de equipo, uno por línea.']);
        }

        $response = $client->forward('POST', "/event/{$evento}/equipos", body: ['equipos' => $nombres]);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#equipos' hace que al volver se reabra esta pestaña.
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#equipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#equipos')->with('status', 'Equipos agregados correctamente.');
    }

    public function update(Request $request, int $equipo, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/equipo/{$equipo}", body: $request->only('nombre'));

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#equipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#equipos')->with('status', 'Equipo actualizado correctamente.');
    }

    public function destroy(Request $request, int $equipo, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/equipo/{$equipo}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#equipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#equipos')->with('status', 'Equipo eliminado correctamente.');
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
