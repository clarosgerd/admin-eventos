<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock por talla/sexo de un ítem del kit (fila de `souvenirs`, ver
 * decisión de terminología en
 * ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md)
 * — llama a /souvenir/{id}/stock de ApiRestEvent. `evento_id`/`nombre`
 * viajan por querystring desde el link en eventos/edit.blade.php: no hay
 * endpoint público liviano para volver a resolver el evento desde un
 * souvenir_id, y pedirlo aparte solo para el breadcrumb no vale la pena.
 */
class ItemStockController extends Controller
{
    public function index(Request $request, int $souvenir, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/souvenir/{$souvenir}/stock");
        $stock = $response?->json('stock') ?? [];

        return view('souvenirs.stock', [
            'souvenirId' => $souvenir,
            'eventoId'   => $request->query('evento_id'),
            'nombre'     => $request->query('nombre', 'Ítem'),
            'stock'      => $stock,
        ]);
    }

    public function store(Request $request, int $souvenir, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/souvenir/{$souvenir}/stock", body: $request->only('talla', 'sexo', 'cantidad_total'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl($souvenir, $request))->with('status', 'Stock agregado correctamente.');
    }

    public function update(Request $request, int $item_stock, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/item-stock/{$item_stock}", body: $request->only('cantidad_total'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('souvenir_id'), $request))->with('status', 'Stock actualizado correctamente.');
    }

    public function destroy(Request $request, int $item_stock, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/item-stock/{$item_stock}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('souvenir_id'), $request))->with('status', 'Stock eliminado correctamente.');
    }

    /**
     * `evento_id`/`nombre` no son parte del recurso — solo viajan para
     * que la pantalla pueda mostrar un breadcrumb sin pedirle a
     * ApiRestEvent que resuelva evento_id desde un souvenir_id (no hay
     * endpoint liviano para eso). Se reenvían tal cual en cada
     * redirect, como querystring, no como flash session.
     */
    private function volverUrl(int $souvenir, Request $request): string
    {
        return route('souvenirs.stock.index', $souvenir) . '?' . http_build_query([
            'evento_id' => $request->input('evento_id'),
            'nombre'    => $request->input('nombre'),
        ]);
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
