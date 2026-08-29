<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Acreditación (check-in) escaneando el QR de referencia — el QR ya
 * existía hace tiempo (e-ticket/PDF/email, ver
 * `ApiRestEvent\app\Services\ReferenceQrService`, solo codifica la
 * referencia) pero no tenía ningún consumidor hasta este panel.
 *
 * Del lado de ApiRestEvent: `GET /event/{evento}/checkin/{referencia}`
 * (lookup, scoped al evento — a propósito no reusa el `GET
 * /registrations/{reference}` público) y `PATCH
 * /participantes/{participante}/checkin` (marca presente, gate real de
 * `pago_status=paid` server-side), ambos detrás del guard `admins` con el
 * mismo scoping que Numeración/Participantes.
 *
 * `lookup()`/`checkin()` son endpoints JSON (no vistas) — la pantalla
 * (`acreditacion.blade.php`) los llama por `fetch()` tras un escaneo de
 * cámara o un submit manual, sin recargar la página.
 */
class AcreditacionController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $participantesResponse = $client->forward('GET', "/event/{$evento}/participantes");
        $participantes = $participantesResponse?->json('participantes') ?? [];

        // "Y" = pagados (solo esos son acreditables); "X" = cuántos de esos
        // ya tienen checkedInAt. Conteo inicial al cargar la página — el JS
        // lo va incrementando en vivo después de cada acreditación exitosa,
        // sin volver a pedir esta lista completa.
        $pagados = array_filter($participantes, fn ($p) => ($p['pagoStatus'] ?? null) === 'paid');
        $totalPagados = count($pagados);
        $totalAcreditados = count(array_filter($pagados, fn ($p) => !empty($p['checkedInAt'])));

        return view('eventos.acreditacion', [
            'evento' => $eventoData,
            'totalPagados' => $totalPagados,
            'totalAcreditados' => $totalAcreditados,
        ]);
    }

    public function lookup(Request $request, int $evento, ApiRestEventClient $client): JsonResponse
    {
        $this->assertCanViewEvento($evento);

        $referencia = trim((string) $request->input('referencia'));
        if ($referencia === '') {
            return response()->json(['success' => false, 'error' => 'Falta la referencia.'], 400);
        }

        $response = $client->forward('GET', '/event/'.$evento.'/checkin/'.rawurlencode($referencia));

        if (!$response) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con el servidor.'], 502);
        }

        return response()->json($response->json(), $response->status());
    }

    public function checkin(Request $request, int $evento, int $participante, ApiRestEventClient $client): JsonResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PATCH', "/participantes/{$participante}/checkin");

        if (!$response) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con el servidor.'], 502);
        }

        return response()->json($response->json(), $response->status());
    }

}
