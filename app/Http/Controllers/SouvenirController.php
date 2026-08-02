<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /souvenir (ApiRestEvent, arreglado en
 * esta misma Fase 4 junto con este controlador). `evento_id` viaja como
 * campo oculto en cada form (ver eventos/edit.blade.php) porque
 * destroy() no tiene forma de recuperarlo después de borrar el souvenir.
 */
class SouvenirController extends Controller
{
    public function store(Request $request, int $form_type, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('name', 'icon', 'price'),
            ['form_types_id' => $form_type]
        );

        $response = $client->forward('POST', '/souvenir', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Souvenir creado correctamente.');
    }

    public function update(Request $request, int $souvenir, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/souvenir/{$souvenir}", body: $request->only('name', 'icon', 'price'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Souvenir actualizado correctamente.');
    }

    public function destroy(Request $request, int $souvenir, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/souvenir/{$souvenir}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Souvenir eliminado correctamente.');
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
