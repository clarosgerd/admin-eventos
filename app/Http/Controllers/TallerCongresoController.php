<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Congresos con talleres (18/08/2026) — ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. CRUD de
 * talleres, llama a /event/{evento}/talleres(/{taller}) de
 * ApiRestEvent. Mismo patrón que `SesionCongresoController` (thin
 * forward con `ApiRestEventClient`).
 */
class TallerCongresoController extends Controller
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

        return view('eventos.talleres.index', [
            'evento'  => $eventoData,
            'talleres' => $talleres,
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/talleres", body: $request->only(
            'nombre', 'descripcion', 'modalidad', 'precio', 'price_usd', 'orden', 'activo', 'permite_inscripcion'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('talleres.index', $evento)->with('status', 'Taller creado correctamente.');
    }

    public function update(Request $request, int $evento, int $taller, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/event/{$evento}/talleres/{$taller}", body: $request->only(
            'nombre', 'descripcion', 'modalidad', 'precio', 'price_usd', 'orden', 'activo', 'permite_inscripcion'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('talleres.index', $evento)->with('status', 'Taller actualizado correctamente.');
    }

    public function destroy(int $evento, int $taller, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/event/{$evento}/talleres/{$taller}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el taller.']);
        }

        return redirect()->route('talleres.index', $evento)->with('status', 'Taller eliminado correctamente.');
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

        // 'message' (20/08/2026) — antes solo miraba 'error' (la clave que
        // usan las respuestas de ESTE panel), pero una excepción no
        // controlada en ApiRestEvent (p.ej. una columna faltante en BD)
        // responde con 'message' (formato default de Laravel), nunca
        // 'error' — quedaba en el genérico "Ocurrió un error" sin ningún
        // detalle. Mismo fallback que ya usan la mayoría de los otros
        // controllers del panel.
        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}