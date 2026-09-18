<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — llama a /event/{evento}/sync-externo(/sincronizar-ahora)
 * de ApiRestEvent, no reimplementa nada acá (ver SyncExternoConfigController
 * del lado de la API). Solo accesible bajo `admin.superadmin` (ver
 * routes/web.php) — mismo criterio que SipBancoController (`token` es una
 * credencial de integración sensible).
 *
 * `token` nunca vuelve completo en la respuesta de la API (solo
 * `tokenPreview`) — el form de edición nunca lo prellena, y `store()` solo
 * lo manda si el usuario efectivamente escribió algo (mismo patrón que
 * SipBancoController con sus campos secretos).
 */
class SyncExternoConfigController extends Controller
{
    public function edit(int $evento, ApiRestEventClient $client): View
    {
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $configResponse = $client->forward('GET', "/event/{$evento}/sync-externo");
        $config = $configResponse?->json('success') ? $configResponse->json('data') : null;

        return view('sync-externo.edit', [
            'evento' => $eventoData,
            'config' => $config,
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $data = $request->only(['form_types_id', 'nombre_fuente', 'url', 'token', 'activo']);

        // Dejar en blanco = no cambiar el token actual (mismo patrón que
        // SipBancoController) — el form nunca lo prellena, así que "vacío"
        // siempre significa "no lo toques", nunca "vaciarlo".
        if (!$request->filled('token')) {
            unset($data['token']);
        }
        $data['activo'] = $request->boolean('activo');

        $response = $client->forward('POST', "/event/{$evento}/sync-externo", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('sync-externo.edit', $evento)->with('status', 'Configuración guardada correctamente.');
    }

    public function sincronizarAhora(int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/event/{$evento}/sync-externo/sincronizar-ahora", timeoutSeconds: 60);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        $resumen = $response->json('data');
        $status = "Sincronizado — creados: {$resumen['creados']}, actualizados: {$resumen['actualizados']}, omitidos: " . count($resumen['omitidos'] ?? []) . '.';

        return back()->with('status', $status);
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
