<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo global de formas de pago (19/08/2026) — llama a
 * /catalogos/formas-pago de ApiRestEvent. Solo accesible bajo
 * `admin.superadmin`. Ver
 * elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md — nace para
 * poder agregar "meru" (u otra pasarela futura) sin escribir directo en la
 * BD; SIP/Multipago, que ya existían, tampoco tenían pantalla hasta ahora.
 */
class FormasPagoController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/formas-pago');
        $formasPago = $response?->json('data') ?? [];

        return view('catalogos.formas-pago', compact('formasPago'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/formas-pago', body: $request->only(
            'slug', 'nombre', 'descripcion', 'pasarela', 'tipo', 'activo'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.formas-pago.index')->with('status', 'Forma de pago creada correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $formas_pago): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/formas-pago/{$formas_pago}", body: $request->only(
            'slug', 'nombre', 'descripcion', 'pasarela', 'tipo', 'activo'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.formas-pago.index')->with('status', 'Forma de pago actualizada correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $formas_pago): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/formas-pago/{$formas_pago}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la forma de pago.']);
        }

        return redirect()->route('catalogos.formas-pago.index')->with('status', 'Forma de pago eliminada correctamente.');
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
