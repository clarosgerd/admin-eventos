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

        // Staff disponible (13/08/2026) — participantes de form_types con
        // es_staff=true, para el selector "+ Asignar ayudante" de cada
        // sesión. Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md.
        $staffResponse = $client->forward('GET', "/event/{$evento}/staff-disponible");
        $staffDisponible = $staffResponse?->json('data') ?? [];

        // Ponentes disponibles (13/08/2026, mismo día) — mismo mecanismo,
        // participantes con es_ponente=true. Ver
        // brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.
        $ponentesResponse = $client->forward('GET', "/event/{$evento}/staff-disponible", query: ['rol' => 'ponente']);
        $ponentesDisponibles = $ponentesResponse?->json('data') ?? [];

        return view('eventos.sesiones.index', [
            'evento' => $eventoData,
            'sesiones' => $sesiones,
            'staffDisponible' => $staffDisponible,
            'ponentesDisponibles' => $ponentesDisponibles,
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

    /**
     * Vinculación de staff/ayudantes o ponentes a sesiones (13/08/2026) —
     * decisión del organizador, hecha después del registro. `rol` viaja en
     * el body (`staff` por default, mantiene compatibilidad con el primer
     * alcance del feature) — ver
     * brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md y
     * brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.
     */
    public function assignStaff(Request $request, int $evento, int $sesion, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $data = $request->validate([
            'participante_id' => 'required|integer',
            'rol' => 'sometimes|nullable|string|in:staff,ponente',
        ]);
        $rol = $data['rol'] ?? 'staff';

        $response = $client->forward('POST', "/event/{$evento}/sesiones/{$sesion}/staff", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        $mensaje = $rol === 'ponente' ? 'Ponente vinculado correctamente.' : 'Ayudante asignado correctamente.';

        return redirect()->route('sesiones.index', $evento)->with('status', $mensaje);
    }

    public function unassignStaff(Request $request, int $evento, int $sesion, int $participante, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        // input() en vez de query(): el formulario manda `rol` como campo
        // del POST (con `_method=DELETE` spoofed), no como querystring.
        $rol = $request->input('rol', 'staff');
        $response = $client->forward('DELETE', "/event/{$evento}/sesiones/{$sesion}/staff/{$participante}", body: ['rol' => $rol]);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        $mensaje = $rol === 'ponente' ? 'Ponente desvinculado.' : 'Ayudante desasignado.';

        return redirect()->route('sesiones.index', $evento)->with('status', $mensaje);
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
