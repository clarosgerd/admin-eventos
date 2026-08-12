<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de organizadores (config global, no por evento) — llama a
 * /organizadores de ApiRestEvent, no reimplementa nada acá (ver
 * OrganizadorController del lado de la API). Solo accesible bajo
 * `admin.superadmin` (ver routes/web.php), mismo criterio que
 * SocioController. Ver PRD-organizadores-crud.md (sesión 11/08/2026).
 */
class OrganizadorController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/organizadores');
        $organizadores = $response?->json('data') ?? [];

        return view('organizadores.index', compact('organizadores'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/organizadores', body: $this->payload($request));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('organizadores.index')->with('status', 'Organizador creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $organizador): RedirectResponse
    {
        $response = $client->forward('PUT', "/organizadores/{$organizador}", body: $this->payload($request));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('organizadores.index')->with('status', 'Organizador actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $organizador): RedirectResponse
    {
        $response = $client->forward('DELETE', "/organizadores/{$organizador}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el organizador.']);
        }

        return redirect()->route('organizadores.index')->with('status', 'Organizador eliminado correctamente.');
    }

    private function payload(Request $request): array
    {
        $data = $request->only(
            'razon_social', 'nombre_comercial', 'rut_nit', 'email', 'telefono',
            'direccion', 'comision_especial', 'convenio_notas'
        );

        // hidden 0 antes del checkbox en la vista (mismo patrón que
        // socios/index.blade.php) — si queda sin marcar, igual manda
        // activo=0, nunca un checkbox vacío que no mande nada.
        $data['activo'] = $request->boolean('activo');

        return $data;
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
