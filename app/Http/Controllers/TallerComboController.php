<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller
 * B por el precio de 1" o "por $300 fijo". Thin forward a
 * /event/{evento}/taller-combos y /taller-combo/{id} de ApiRestEvent,
 * mismo patrón que TallerCongresoController.
 */
class TallerComboController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $talleresResponse = $client->forward('GET', "/event/{$evento}/talleres");
        $talleres = $talleresResponse?->json('data') ?? [];

        $combosResponse = $client->forward('GET', "/event/{$evento}/taller-combos");
        $combos = $combosResponse?->json('data') ?? [];

        return view('eventos.talleres.combos', [
            'evento'     => $eventoData,
            'talleres'   => $talleres,
            'combos'     => $combos,
            // Precio por categoría (13/10/2026) — categorías del evento
            // para poblar el <select> del form de precios.
            'categorias' => $eventoData['categories'] ?? [],
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        // El precio no viaja acá (13/10/2026) — el mismo combo puede
        // costar distinto según la categoría, se carga aparte vía
        // TallerComboPrecioController una vez creado el combo.
        $response = $client->forward('POST', "/event/{$evento}/taller-combos", body: $request->only(
            'nombre', 'fecha_desde', 'fecha_hasta', 'activo', 'taller_ids'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Combo de talleres creado correctamente.');
    }

    public function update(Request $request, int $evento, int $tallerCombo, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/taller-combo/{$tallerCombo}", body: $request->only(
            'nombre', 'fecha_desde', 'fecha_hasta', 'activo', 'taller_ids'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Combo de talleres actualizado correctamente.');
    }

    public function destroy(int $evento, int $tallerCombo, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/taller-combo/{$tallerCombo}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el combo.']);
        }

        return redirect()->route('taller-combos.index', $evento)->with('status', 'Combo de talleres eliminado correctamente.');
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
