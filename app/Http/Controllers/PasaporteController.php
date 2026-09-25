<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SmartStand fase 4 (26/09/2026) — "Pasaporte médico": sorteo general del
 * organizador entre los asistentes que fueron capturados por al menos N stands
 * distintos (N = expositores_config.pasaporte_min_stands, 5 por defecto).
 * Proxy delgado hacia ApiRestEvent (molde: LiquidacionController): la elección
 * (random_int), el scope por evento y la auditoría viven allá.
 */
class PasaporteController extends Controller
{
    use AuthorizesEventoScope;

    public function show(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $datosEvento = $eventoResponse?->json('eventos') ?? [];

        $pasaporte = $client->forward('GET', "/event/{$evento}/pasaporte");

        return view('eventos.pasaporte', [
            'evento'    => ['id' => $evento, 'name' => $datosEvento['name'] ?? "Evento #{$evento}"],
            'pasaporte' => $pasaporte?->json('success') ? $pasaporte->json('data') : null,
            'apiCaida'  => $pasaporte === null,
        ]);
    }

    public function sortear(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $data = $request->validate([
            'premio'     => ['required', 'string', 'max:150'],
            'min_stands' => ['nullable', 'integer', 'between:1,50'],
        ]);

        // Sin reintentos: un reintento tras un timeout podría sortear dos veces.
        $response = $client->forward('POST', "/event/{$evento}/pasaporte/sorteo", body: array_filter([
            'premio'     => $data['premio'],
            'min_stands' => $data['min_stands'] ?? null,
        ], fn ($valor) => $valor !== null), retries: 0);

        if (!$response || !$response->json('success')) {
            return redirect()->route('pasaporte.show', $evento)
                ->withInput()
                ->withErrors($this->extractErrors($response));
        }

        $ganador = $response->json('sorteo.ganador') ?? [];

        return redirect()->route('pasaporte.show', $evento)->with(
            'status',
            'Ganador del sorteo: ' . trim(($ganador['nombre'] ?? '') . ' ' . ($ganador['apellido'] ?? '')) . '.'
        );
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
