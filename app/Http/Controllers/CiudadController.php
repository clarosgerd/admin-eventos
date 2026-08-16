<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de ciudad (config global, 15/08/2026) — llama a
 * /catalogos/ciudades de ApiRestEvent. Solo accesible bajo
 * `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class CiudadController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $ciudadesResponse = $client->forward('GET', '/catalogos/ciudades');
        $paisesResponse = $client->forward('GET', '/catalogos/paises');

        $ciudades = $ciudadesResponse?->json('data') ?? [];
        $paises = $paisesResponse?->json('data') ?? [];

        return view('catalogos.ciudades', compact('ciudades', 'paises'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/ciudades', body: $request->only('pais_id', 'nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.ciudades.index')->with('status', 'Ciudad creada correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $ciudad): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/ciudades/{$ciudad}", body: $request->only('pais_id', 'nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.ciudades.index')->with('status', 'Ciudad actualizada correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $ciudad): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/ciudades/{$ciudad}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la ciudad.']);
        }

        return redirect()->route('catalogos.ciudades.index')->with('status', 'Ciudad eliminada correctamente.');
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
