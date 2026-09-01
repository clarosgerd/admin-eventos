<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de bancos SIP (31/08/2026) — llama a /sip-bancos de ApiRestEvent, no
 * reimplementa nada acá (ver SipBancoController del lado de la API). Solo
 * accesible bajo `admin.superadmin` (ver routes/web.php). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md y
 * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md.
 *
 * Los 4 campos secretos (sip_password, sip_apikey, sip_apikey_servicio,
 * callback_basic_password) nunca vuelven en la respuesta de la API — el
 * form de edición nunca los prellena, y `update()` solo los manda si el
 * usuario efectivamente escribió algo (mismo patrón que
 * AdminUserController::update() con `password`).
 */
class SipBancoController extends Controller
{
    private const SECRET_FIELDS = ['sip_password', 'sip_apikey', 'sip_apikey_servicio', 'callback_basic_password'];

    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/sip-bancos');
        $bancos = $response?->json('data') ?? [];

        return view('sip-bancos.index', compact('bancos'));
    }

    public function create(ApiRestEventClient $client): View
    {
        return view('sip-bancos.form', [
            'banco' => null,
            'organizadores' => $this->listaOrganizadores($client),
            'action' => route('sip-bancos.store'),
        ]);
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/sip-bancos', body: $request->only(array_merge(
            ['organizador_id', 'nombre', 'sip_username', 'sip_base_auth_url', 'sip_base_api_url', 'callback_basic_user', 'activo'],
            self::SECRET_FIELDS
        )));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('sip-bancos.index')->with('status', 'Banco SIP creado correctamente.');
    }

    public function edit(ApiRestEventClient $client, int $sip_banco): View
    {
        $response = $client->forward('GET', "/sip-bancos/{$sip_banco}");

        return view('sip-bancos.form', [
            'banco' => $response?->json('data'),
            'organizadores' => $this->listaOrganizadores($client),
            'action' => route('sip-bancos.update', $sip_banco),
        ]);
    }

    public function update(Request $request, ApiRestEventClient $client, int $sip_banco): RedirectResponse
    {
        $data = $request->only(array_merge(
            ['organizador_id', 'nombre', 'sip_username', 'sip_base_auth_url', 'sip_base_api_url', 'callback_basic_user', 'activo'],
            self::SECRET_FIELDS
        ));

        // Dejar en blanco = no cambiar (mismo patrón que AdminUserController
        // con `password`) — el form nunca prellena estos 4 campos, así que
        // "vacío" siempre significa "no lo toques", nunca "vaciarlo".
        foreach (self::SECRET_FIELDS as $field) {
            if (!$request->filled($field)) {
                unset($data[$field]);
            }
        }

        $response = $client->forward('PUT', "/sip-bancos/{$sip_banco}", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('sip-bancos.index')->with('status', 'Banco SIP actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $sip_banco): RedirectResponse
    {
        $response = $client->forward('DELETE', "/sip-bancos/{$sip_banco}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el banco SIP.']);
        }

        return redirect()->route('sip-bancos.index')->with('status', 'Banco SIP eliminado correctamente.');
    }

    /**
     * Lista de organizadores para el select — a diferencia de
     * AdminUserController::listaEventos(), acá no hace falta recorrer
     * paginación: /organizadores devuelve el catálogo completo en una sola
     * llamada (lista chica, config global).
     */
    private function listaOrganizadores(ApiRestEventClient $client): array
    {
        $response = $client->forward('GET', '/organizadores');

        return $response?->json('data') ?? [];
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
