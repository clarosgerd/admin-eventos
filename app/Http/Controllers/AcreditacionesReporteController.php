<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\View\View;

/**
 * Reporte/dashboard de acreditaciones (check-in) por evento (28/10/2026)
 * — pedido real del organizador: la pantalla de Acreditación
 * (AcreditacionController) permite hacer los check-in, pero no decía
 * cuántas acreditaciones se hicieron en total ni contra cuántos
 * participantes pagados quedaban por acreditar.
 *
 * Espejo del patrón de DashboardInscripcionesController: trae todo
 * calculado desde `GET /event/{evento}/acreditaciones-reporte`
 * (App\Support\AcreditacionesReporteData del lado ApiRestEvent, que
 * aplica el mismo scoping/autorización que el resto de reportes vía
 * assertCanWriteEvento) y solo lo vuelca en la vista.
 *
 * A propósito SOLO cubre la acreditación general del evento
 * (`participantes.checked_in_at`) — la asistencia por sesión de congreso
 * queda fuera de este reporte (decisión del usuario, 28/10/2026); esa ya
 * tiene su propio reporte de concurrencia (sesiones.reporte).
 */
class AcreditacionesReporteController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/acreditaciones-reporte");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el reporte de acreditaciones.');

        return view('eventos.acreditaciones-reporte', [
            'evento' => $eventoData,
            'totales' => $response->json('totales'),
            'porCategoria' => $response->json('porCategoria'),
            'nombresCategorias' => $response->json('nombresCategorias'),
            'porHora' => $response->json('porHora'),
            'recientes' => $response->json('recientes'),
        ]);
    }
}
