<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Precios por período de un taller (12/10/2026, pedido del usuario) —
 * mismo patrón que CategoryPricePeriodController. Llama a
 * /event/{evento}/talleres/{taller} (show, ahora vía TallerResource),
 * /taller/{id}/periodos y /taller-price-period/{id} de ApiRestEvent.
 */
class TallerPricePeriodController extends Controller
{
    public function index(Request $request, int $evento, int $taller, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/event/{$evento}/talleres/{$taller}");
        $tallerData = $response?->json('data') ?? [];

        return view('eventos.talleres.periodos', [
            'eventoId'  => $evento,
            'tallerId'  => $taller,
            'taller'    => $tallerData,
            'periodos'  => $tallerData['periodos'] ?? [],
        ]);
    }

    public function store(Request $request, int $evento, int $taller, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/taller/{$taller}/periodos", body: $request->only('nombre', 'price', 'price_usd', 'fecha_desde', 'fecha_hasta'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('talleres.periodos.index', [$evento, $taller])->with('status', 'Período de precio creado correctamente.');
    }

    public function update(Request $request, int $evento, int $taller, int $tallerPricePeriod, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/taller-price-period/{$tallerPricePeriod}", body: $request->only('nombre', 'price', 'price_usd', 'fecha_desde', 'fecha_hasta'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('talleres.periodos.index', [$evento, $taller])->with('status', 'Período de precio actualizado correctamente.');
    }

    public function destroy(int $evento, int $taller, int $tallerPricePeriod, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/taller-price-period/{$tallerPricePeriod}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('talleres.periodos.index', [$evento, $taller])->with('status', 'Período de precio eliminado correctamente.');
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

        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}
