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
        // Kit/tallas/stock (11/08/2026) — ver
        // PRD-kit-tallas-stock-lista-espera.md. Los checkboxes no mandan
        // nada si están destildados, por eso el default explícito acá
        // (no en ApiRestEvent, que ya trata su ausencia como false).
        $payload = array_merge(
            $request->only('name', 'icon', 'price', 'foto_url'),
            [
                'form_types_id'  => $form_type,
                'incluido'       => $request->boolean('incluido'),
                'requiere_talla' => $request->boolean('requiere_talla'),
                'requiere_sexo'  => $request->boolean('requiere_sexo'),
            ]
        );

        $response = $client->forward('POST', '/souvenir', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Ítem creado correctamente.');
    }

    public function update(Request $request, int $souvenir, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('name', 'icon', 'price', 'foto_url'),
            [
                'incluido'       => $request->boolean('incluido'),
                'requiere_talla' => $request->boolean('requiere_talla'),
                'requiere_sexo'  => $request->boolean('requiere_sexo'),
            ]
        );

        $response = $client->forward('PUT', "/souvenir/{$souvenir}", body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Ítem actualizado correctamente.');
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
