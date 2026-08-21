<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Precios por período de una categoría — ver
 * ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md
 * (sesión 12/08/2026). Llama a /category/{id}/periodos y
 * /category-price-period/{id} de ApiRestEvent, mismo patrón que
 * ItemStockController (kit/tallas/stock): `evento_id`/`nombre` viajan
 * por querystring solo para el breadcrumb, no son parte del recurso.
 */
class CategoryPricePeriodController extends Controller
{
    public function index(Request $request, int $category, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/category/{$category}");
        $categoria = $response?->json() ?? [];

        return view('categorias.periodos', [
            'categoryId' => $category,
            'eventoId'   => $request->query('evento_id'),
            'categoria'  => $categoria,
            'periodos'   => $categoria['periodos'] ?? [],
        ]);
    }

    public function store(Request $request, int $category, ApiRestEventClient $client): RedirectResponse
    {
        // price_usd (20/08/2026) — opcional, ver PrecioVigenteData en
        // ApiRestEvent. Sin esto, el precio USD fijo de un evento con
        // usd_precio_fijo=true ignoraba los períodos por completo.
        $response = $client->forward('POST', "/category/{$category}/periodos", body: $request->only('nombre', 'price', 'price_usd', 'fecha_desde', 'fecha_hasta'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl($category, $request))->with('status', 'Período de precio creado correctamente.');
    }

    public function update(Request $request, int $categoryPricePeriod, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/category-price-period/{$categoryPricePeriod}", body: $request->only('nombre', 'price', 'price_usd', 'fecha_desde', 'fecha_hasta'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('category_id'), $request))->with('status', 'Período de precio actualizado correctamente.');
    }

    public function destroy(Request $request, int $categoryPricePeriod, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/category-price-period/{$categoryPricePeriod}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('category_id'), $request))->with('status', 'Período de precio eliminado correctamente.');
    }

    private function volverUrl(int $category, Request $request): string
    {
        return route('categorias.periodos.index', $category) . '?' . http_build_query([
            'evento_id' => $request->input('evento_id'),
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

        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}
