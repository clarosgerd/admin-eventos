<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de rubros del presupuesto (config global, no por evento) —
 * llama a /presupuesto-categorias de ApiRestEvent, mismo patrón que
 * SocioController. Solo accesible bajo `admin.superadmin` (ver
 * routes/web.php). Ver elascenso/event/brain/ (sesión 11/08/2026).
 */
class PresupuestoCategoriaController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/presupuesto-categorias');
        $categorias = $response?->json('data') ?? [];

        return view('presupuesto-categorias.index', compact('categorias'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/presupuesto-categorias', body: $request->only('nombre', 'tipo', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('presupuesto-categorias.index')->with('status', 'Categoría creada correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $presupuesto_categoria): RedirectResponse
    {
        $response = $client->forward('PUT', "/presupuesto-categorias/{$presupuesto_categoria}", body: $request->only('nombre', 'tipo', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('presupuesto-categorias.index')->with('status', 'Categoría actualizada correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $presupuesto_categoria): RedirectResponse
    {
        $response = $client->forward('DELETE', "/presupuesto-categorias/{$presupuesto_categoria}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la categoría.']);
        }

        return redirect()->route('presupuesto-categorias.index')->with('status', 'Categoría eliminada correctamente.');
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
