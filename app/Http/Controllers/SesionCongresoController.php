<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Agenda y sesiones de congreso (config estructural) — llama a
 * /event/{evento}/sesiones(/{sesion}) de ApiRestEvent, mismo patrón que
 * PresupuestoController (admin scoped a su evento, o super_admin — no
 * solo super_admin). Ver PRD-Agenda-sessiones-onlycongresos.md y
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class SesionCongresoController extends Controller
{
    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $sesionesResponse = $client->forward('GET', "/event/{$evento}/sesiones");
        $sesiones = $sesionesResponse?->json('data') ?? [];

        return view('eventos.sesiones.index', [
            'evento' => $eventoData,
            'sesiones' => $sesiones,
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/sesiones", body: $request->only(
            'titulo', 'ponente', 'ponente_cargo', 'sala', 'fecha', 'hora_inicio', 'hora_fin', 'cupo', 'requiere_inscripcion'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('sesiones.index', $evento)->with('status', 'Sesión creada correctamente.');
    }

    public function update(Request $request, int $evento, int $sesion, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/event/{$evento}/sesiones/{$sesion}", body: $request->only(
            'titulo', 'ponente', 'ponente_cargo', 'sala', 'fecha', 'hora_inicio', 'hora_fin', 'cupo', 'requiere_inscripcion', 'activa'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('sesiones.index', $evento)->with('status', 'Sesión actualizada correctamente.');
    }

    public function destroy(int $evento, int $sesion, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/event/{$evento}/sesiones/{$sesion}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la sesión.']);
        }

        return redirect()->route('sesiones.index', $evento)->with('status', 'Sesión eliminada correctamente.');
    }

    /**
     * Mismo criterio que ParticipantesController::assertCanViewEvento.
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
