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

    /**
     * Formas de pago activas para este organizador (19/08/2026) — ver
     * elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. Llama a
     * /organizadores/{id}/formas-pago de ApiRestEvent, que ya devuelve el
     * catálogo completo (sistema + propias) con el estado de selección de
     * cada una, para no tener que cruzar nada acá.
     */
    public function formasPago(ApiRestEventClient $client, int $organizador): View
    {
        $orgResponse = $client->forward('GET', "/organizadores/{$organizador}");
        $organizadorData = $orgResponse?->json('data') ?? ['id' => $organizador, 'razon_social' => "Organizador #{$organizador}"];

        $fpResponse = $client->forward('GET', "/organizadores/{$organizador}/formas-pago");
        $formasPago = $fpResponse?->json('data') ?? [];
        $usandoDefault = $fpResponse?->json('usandoDefaultDelSistema') ?? false;

        return view('organizadores.formas-pago', [
            'organizadorData' => $organizadorData,
            'organizadorId' => $organizador,
            'formasPago' => $formasPago,
            'usandoDefault' => $usandoDefault,
        ]);
    }

    public function updateFormasPago(Request $request, ApiRestEventClient $client, int $organizador): RedirectResponse
    {
        $response = $client->forward('PUT', "/organizadores/{$organizador}/formas-pago", body: [
            'forma_pago_ids' => $request->input('forma_pago_ids', []),
        ]);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('organizadores.formas-pago', $organizador)
            ->with('status', 'Formas de pago actualizadas correctamente.');
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
