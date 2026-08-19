<?php

namespace App\Http\Controllers;

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
            'nombre', 'descripcion', 'modalidad', 'precio', 'orden', 'activo'
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
            'nombre', 'descripcion', 'modalidad', 'precio', 'orden', 'activo'
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

    /**
     * Mismo criterio que SesionCongresoController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
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