<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de país (config global, 15/08/2026) — llama a
 * /catalogos/paises de ApiRestEvent. Solo accesible bajo
 * `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class PaisController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/paises');
        $paises = $response?->json('data') ?? [];

        return view('catalogos.paises', compact('paises'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/paises', body: $request->only('nombre', 'iso2', 'iso3', 'prefijo_tel', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.paises.index')->with('status', 'País creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $pais): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/paises/{$pais}", body: $request->only('nombre', 'iso2', 'iso3', 'prefijo_tel', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.paises.index')->with('status', 'País actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $pais): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/paises/{$pais}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el país.']);
        }

        return redirect()->route('catalogos.paises.index')->with('status', 'País eliminado correctamente.');
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
