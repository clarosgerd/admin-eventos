<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de socios de PassToGo (config global, no por evento) — llama a
 * /socios de ApiRestEvent, no reimplementa nada acá (ver SocioController
 * del lado de la API). Solo accesible bajo `admin.superadmin` (ver
 * routes/web.php), igual que AdminUserController. Ver
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class SocioController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/socios');
        $socios = $response?->json('data') ?? [];
        $porcentajeTotal = $response?->json('porcentaje_total') ?? 0;

        return view('socios.index', compact('socios', 'porcentajeTotal'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/socios', body: $request->only('nombre', 'porcentaje', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('socios.index')->with('status', 'Socio creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $socio): RedirectResponse
    {
        $response = $client->forward('PUT', "/socios/{$socio}", body: $request->only('nombre', 'porcentaje', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('socios.index')->with('status', 'Socio actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $socio): RedirectResponse
    {
        $response = $client->forward('DELETE', "/socios/{$socio}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el socio.']);
        }

        return redirect()->route('socios.index')->with('status', 'Socio eliminado correctamente.');
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
