<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * Rangos de numeración (bib) por color, ligados a una categoría — mismo
 * patrón exacto que CategoryPricePeriodController (llama a
 * /category/{id}/numeracion-rangos y /numeracion-rango/{id} de
 * ApiRestEvent; evento_id/nombre viajan por querystring solo para el
 * breadcrumb).
 */
class NumeracionRangoController extends Controller
{
    public function index(Request $request, int $category, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/category/{$category}");
        $categoria = $response?->json('category') ?? [];

        $generosResponse = $client->forward('GET', '/generos');
        $calculoEdadesResponse = $client->forward('GET', '/calculo-edades');

        return view('categorias.numeracion-rangos', [
            'categoryId' => $category,
            'eventoId'   => $request->query('evento_id'),
            'categoria'  => $categoria,
            'rangos'     => $categoria['numeracion_rangos'] ?? [],
            'generos'    => $generosResponse?->json('data') ?? [],
            'calculoEdades' => $calculoEdadesResponse?->json('data') ?? [],
        ]);
    }

    public function store(Request $request, int $category, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/category/{$category}/numeracion-rangos", body: $request->only('genero_id', 'edad_min', 'edad_max', 'color', 'numero_min', 'numero_max'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl($category, $request))->with('status', 'Rango de numeración creado correctamente.');
    }

    public function update(Request $request, int $numeracionRango, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/numeracion-rango/{$numeracionRango}", body: $request->only('genero_id', 'edad_min', 'edad_max', 'color', 'numero_min', 'numero_max'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('category_id'), $request))->with('status', 'Rango de numeración actualizado correctamente.');
    }

    public function destroy(Request $request, int $numeracionRango, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/numeracion-rango/{$numeracionRango}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect($this->volverUrl((int) $request->input('category_id'), $request))->with('status', 'Rango de numeración eliminado correctamente.');
    }

    private function volverUrl(int $category, Request $request): string
    {
        return route('categorias.rangos.index', $category) . '?' . http_build_query([
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
