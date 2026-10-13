<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — el mismo combo puede costar distinto según la categoría del
 * participante. Thin forward a /taller-combo/{id}/precios y
 * /taller-combo-precio/{id} de ApiRestEvent, mismo patrón que
 * TallerPricePeriodController.
 */
class TallerComboPrecioController extends Controller
{
    use AuthorizesEventoScope;

    public function store(Request $request, int $evento, int $tallerCombo, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/taller-combo/{$tallerCombo}/precios", body: $request->only('category_id', 'precio', 'price_usd'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Precio del combo creado correctamente.');
    }

    public function update(Request $request, int $evento, int $tallerCombo, int $tallerComboPrecio, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/taller-combo-precio/{$tallerComboPrecio}", body: $request->only('category_id', 'precio', 'price_usd'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Precio del combo actualizado correctamente.');
    }

    public function destroy(int $evento, int $tallerCombo, int $tallerComboPrecio, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/taller-combo-precio/{$tallerComboPrecio}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el precio.']);
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Precio del combo eliminado correctamente.');
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
