<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /category (ApiRestEvent, Fase 0), no
 * reimplementa validación. Categorías de un evento existente (a
 * diferencia de la Fase 2, que las crea anidadas dentro de POST /event
 * solo al momento de la alta).
 */
class CategoriaController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        // Categorías por form_type (27/08/2026) — formulario_id vacío ("")
        // debe mandarse como ausente/null, no como string vacío, para que
        // ApiRestEvent lo trate como "compartida por todos los form_types"
        // en vez de rechazar la validación `nullable|integer`.
        $payload = array_merge(
            $request->only('name', 'price', 'price_usd', 'description', 'color', 'formulario_id', 'permite_inscripcion'),
            ['event_id' => $evento]
        );
        if (($payload['formulario_id'] ?? '') === '') {
            unset($payload['formulario_id']);
        }

        $response = $client->forward('POST', '/category', body: $payload);

        // Mejora de visualización (12/08/2026) — eventos/edit.blade.php pasó
        // a tabs; '#categorias' hace que al volver se reabra la pestaña de
        // Categorías en vez de caer siempre en "Datos" (la primera).
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#categorias')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#categorias')->with('status', 'Categoría creada correctamente.');
    }

    public function update(Request $request, int $categoria, ApiRestEventClient $client): RedirectResponse
    {
        // Categorías por form_type (27/08/2026) — a diferencia de store(),
        // acá SÍ se manda `formulario_id: null` explícito cuando llega
        // vacío, para poder volver una categoría ya asignada a "compartida
        // por todos los form_types" (UpdateCategoryRequest usa `sometimes`,
        // así que omitir el campo entero dejaría el valor anterior intacto).
        $payload = $request->only('name', 'price', 'price_usd', 'description', 'color', 'formulario_id', 'permite_inscripcion');
        if (($payload['formulario_id'] ?? '') === '') {
            $payload['formulario_id'] = null;
        }

        $response = $client->forward('PUT', "/category/{$categoria}", body: $payload);

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#categorias')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#categorias')->with('status', 'Categoría actualizada correctamente.');
    }

    public function destroy(Request $request, int $categoria, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/category/{$categoria}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#categorias')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#categorias')->with('status', 'Categoría eliminada correctamente.');
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
