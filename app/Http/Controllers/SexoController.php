<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de sexo (config global, 15/08/2026) — llama a
 * /catalogos/sexos de ApiRestEvent, no reimplementa nada acá (ver
 * SexoController del lado de la API). Solo accesible bajo
 * `admin.superadmin` (ver routes/web.php), mismo criterio que
 * SocioController. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class SexoController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/sexos');
        $sexos = $response?->json('data') ?? [];

        return view('catalogos.sexos', compact('sexos'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/sexos', body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.sexos.index')->with('status', 'Sexo creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $sexo): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/sexos/{$sexo}", body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.sexos.index')->with('status', 'Sexo actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $sexo): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/sexos/{$sexo}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el sexo.']);
        }

        return redirect()->route('catalogos.sexos.index')->with('status', 'Sexo eliminado correctamente.');
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
