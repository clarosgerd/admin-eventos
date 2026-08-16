<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de subtipo de evento (config global, 15/08/2026) —
 * llama a /catalogos/subtipos-evento de ApiRestEvent. Solo accesible bajo
 * `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class SubtipoEventoController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $subtiposResponse = $client->forward('GET', '/catalogos/subtipos-evento');
        $tiposResponse = $client->forward('GET', '/catalogos/tipos-evento');

        $subtipos = $subtiposResponse?->json('data') ?? [];
        $tipos = $tiposResponse?->json('data') ?? [];

        return view('catalogos.subtipos-evento', compact('subtipos', 'tipos'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/subtipos-evento', body: $request->only('tipo_evento_id', 'nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.subtipos-evento.index')->with('status', 'Subtipo de evento creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $subtipoEvento): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/subtipos-evento/{$subtipoEvento}", body: $request->only('tipo_evento_id', 'nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.subtipos-evento.index')->with('status', 'Subtipo de evento actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $subtipoEvento): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/subtipos-evento/{$subtipoEvento}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el subtipo de evento.']);
        }

        return redirect()->route('catalogos.subtipos-evento.index')->with('status', 'Subtipo de evento eliminado correctamente.');
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
