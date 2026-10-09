<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de cuentas de WhatsApp Business oficial (08/10/2026) — llama a
 * /whatsapp-cuentas de ApiRestEvent, no reimplementa nada acá (ver
 * WhatsappCuentaController del lado de la API). Solo accesible bajo
 * `admin.superadmin` (ver routes/web.php), mismo criterio que
 * SipBancoController.
 *
 * `access_token` nunca vuelve en la respuesta de la API — el form de
 * edición nunca lo prellena, y `update()` solo lo manda si el usuario
 * efectivamente escribió algo (mismo patrón que SipBancoController con sus
 * 4 campos secretos).
 */
class WhatsappCuentaController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/whatsapp-cuentas');
        $cuentas = $response?->json('data') ?? [];

        return view('whatsapp-cuentas.index', compact('cuentas'));
    }

    public function create(ApiRestEventClient $client): View
    {
        return view('whatsapp-cuentas.form', [
            'cuenta' => null,
            'organizadores' => $this->listaOrganizadores($client),
            'action' => route('whatsapp-cuentas.store'),
        ]);
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/whatsapp-cuentas', body: $this->payload($request));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('whatsapp-cuentas.index')->with('status', 'Cuenta de WhatsApp creada correctamente.');
    }

    public function edit(ApiRestEventClient $client, int $whatsapp_cuenta): View
    {
        $response = $client->forward('GET', "/whatsapp-cuentas/{$whatsapp_cuenta}");

        return view('whatsapp-cuentas.form', [
            'cuenta' => $response?->json('data'),
            'organizadores' => $this->listaOrganizadores($client),
            'action' => route('whatsapp-cuentas.update', $whatsapp_cuenta),
        ]);
    }

    public function update(Request $request, ApiRestEventClient $client, int $whatsapp_cuenta): RedirectResponse
    {
        $data = $this->payload($request);

        // Dejar en blanco = no cambiar (mismo patrón que SipBancoController)
        // — el form nunca prellena access_token, así que "vacío" siempre
        // significa "no lo toques", nunca "vaciarlo".
        if (!$request->filled('access_token')) {
            unset($data['access_token']);
        }

        $response = $client->forward('PUT', "/whatsapp-cuentas/{$whatsapp_cuenta}", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('whatsapp-cuentas.index')->with('status', 'Cuenta de WhatsApp actualizada correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $whatsapp_cuenta): RedirectResponse
    {
        $response = $client->forward('DELETE', "/whatsapp-cuentas/{$whatsapp_cuenta}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la cuenta de WhatsApp.']);
        }

        return redirect()->route('whatsapp-cuentas.index')->with('status', 'Cuenta de WhatsApp eliminada correctamente.');
    }

    private function payload(Request $request): array
    {
        return $request->only([
            'organizador_id', 'nombre', 'phone_number_id', 'business_account_id',
            'access_token', 'template_name', 'template_lang', 'activo',
        ]);
    }

    /**
     * Lista de organizadores para el select — mismo patrón que
     * SipBancoController::listaOrganizadores().
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
