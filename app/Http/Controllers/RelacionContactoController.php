<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de relación de contacto de emergencia (config global,
 * 15/08/2026) — llama a /catalogos/relaciones-contacto de ApiRestEvent.
 * Solo accesible bajo `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class RelacionContactoController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/relaciones-contacto');
        $relaciones = $response?->json('data') ?? [];

        return view('catalogos.relaciones-contacto', compact('relaciones'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/relaciones-contacto', body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.relaciones-contacto.index')->with('status', 'Relación creada correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $relacionContacto): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/relaciones-contacto/{$relacionContacto}", body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.relaciones-contacto.index')->with('status', 'Relación actualizada correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $relacionContacto): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/relaciones-contacto/{$relacionContacto}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la relación.']);
        }

        return redirect()->route('catalogos.relaciones-contacto.index')->with('status', 'Relación eliminada correctamente.');
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
