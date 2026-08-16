<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de administración del catálogo de tipo de evento (config global,
 * 15/08/2026) — llama a /catalogos/tipos-evento de ApiRestEvent
 * (`TipoEventoController::adminIndex/store/update/destroy`, distinto del
 * `EventoController::tiposEvento()` de este mismo repo, que sigue leyendo
 * el endpoint público sin auth para poblar los selects del alta de
 * evento — no se toca). Solo accesible bajo `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class TipoEventoController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/tipos-evento');
        $tipos = $response?->json('data') ?? [];

        return view('catalogos.tipos-evento', compact('tipos'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/tipos-evento', body: $request->only('nombre', 'icono', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.tipos-evento.index')->with('status', 'Tipo de evento creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $tipoEvento): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/tipos-evento/{$tipoEvento}", body: $request->only('nombre', 'icono', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.tipos-evento.index')->with('status', 'Tipo de evento actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $tipoEvento): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/tipos-evento/{$tipoEvento}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el tipo de evento.']);
        }

        return redirect()->route('catalogos.tipos-evento.index')->with('status', 'Tipo de evento eliminado correctamente.');
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

        return ['general' => $response->json('error') ?? 'Ocurrió un error.'];
    }
}
