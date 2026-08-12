<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Check-in de staff por sesión de congreso (individual y masivo) +
 * reporte de asistencia — llama a los endpoints scoped por sesión de
 * ApiRestEvent (AsistenciaSesionController). Mismo criterio de permisos
 * que SesionCongresoController. Ver PRD-Agenda-sessiones-onlycongresos.md
 * y elascenso/event/brain/ (sesión 11/08/2026).
 *
 * `lookup()`/`checkin()`/`checkinBulk()` son endpoints JSON (no vistas) —
 * la pantalla los llama por `fetch()`, mismo patrón que AcreditacionController.
 */
class AsistenciaSesionController extends Controller
{
    public function index(int $evento, int $sesion, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $sesionesResponse = $client->forward('GET', "/event/{$evento}/sesiones");
        $sesionData = collect($sesionesResponse?->json('data') ?? [])->firstWhere('id', $sesion);
        abort_if(!$sesionData, 404);

        // Lista para el check-in masivo — todos los pagados del evento,
        // no solo los que ya asistieron a esta sesión (no hay
        // pre-inscripción por sesión en esta ronda, ver el plan).
        $participantesResponse = $client->forward('GET', "/event/{$evento}/participantes");
        $participantesPagados = array_values(array_filter(
            $participantesResponse?->json('participantes') ?? [],
            fn ($p) => ($p['pagoStatus'] ?? null) === 'paid'
        ));

        return view('eventos.sesiones.acreditacion', [
            'evento' => $eventoData,
            'sesion' => $sesionData,
            'participantesPagados' => $participantesPagados,
        ]);
    }

    public function lookup(Request $request, int $evento, int $sesion, ApiRestEventClient $client): JsonResponse
    {
        $this->assertCanViewEvento($evento);

        $referencia = trim((string) $request->input('referencia'));
        if ($referencia === '') {
            return response()->json(['success' => false, 'error' => 'Falta la referencia.'], 400);
        }

        $response = $client->forward('GET', "/event/{$evento}/sesiones/{$sesion}/lookup/".rawurlencode($referencia));

        if (!$response) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con el servidor.'], 502);
        }

        return response()->json($response->json(), $response->status());
    }

    public function checkin(int $evento, int $sesion, int $participante, ApiRestEventClient $client): JsonResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PATCH', "/event/{$evento}/sesiones/{$sesion}/participantes/{$participante}/checkin");

        if (!$response) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con el servidor.'], 502);
        }

        return response()->json($response->json(), $response->status());
    }

    public function checkinBulk(Request $request, int $evento, int $sesion, ApiRestEventClient $client): JsonResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/sesiones/{$sesion}/checkin-bulk", body: [
            'participante_ids' => $request->input('participante_ids', []),
        ]);

        if (!$response) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con el servidor.'], 502);
        }

        return response()->json($response->json(), $response->status());
    }

    public function reporte(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $reporteResponse = $client->forward('GET', "/event/{$evento}/sesiones-reporte");
        abort_if(!$reporteResponse || !$reporteResponse->json('success'), 502, 'No se pudo cargar el reporte de asistencia.');

        return view('eventos.sesiones.reporte', [
            'evento' => $eventoData,
            'totalParticipantesPagados' => $reporteResponse->json('totalParticipantesPagados'),
            'sesiones' => $reporteResponse->json('data') ?? [],
        ]);
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
}
