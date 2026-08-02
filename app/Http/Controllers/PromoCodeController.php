<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel — llama a /promo-code (ApiRestEvent, Fase 0), no
 * reimplementa validación. Códigos de un evento existente (a diferencia
 * de la Fase 2, que los crea anidados dentro de POST /event solo al
 * momento de la alta).
 */
class PromoCodeController extends Controller
{
    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('promo_code', 'price', 'discount_type', 'discount_percent'),
            ['event_id' => $evento]
        );

        $response = $client->forward('POST', '/promo-code', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Código de promoción creado correctamente.');
    }

    public function update(Request $request, int $promo_code, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/promo-code/{$promo_code}", body: $request->only('promo_code', 'price', 'discount_type', 'discount_percent'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Código de promoción actualizado correctamente.');
    }

    public function destroy(Request $request, int $promo_code, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/promo-code/{$promo_code}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $request->input('evento_id'))->with('status', 'Código de promoción eliminado correctamente.');
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
