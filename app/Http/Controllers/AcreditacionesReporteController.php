<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\Response;
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

    /**
     * CSV de los acreditados (13/11/2026, pedido del usuario): TODOS los
     * acreditados del evento (no solo los 20 de "recientes"), con hora de
     * acreditación, quién acreditó (admin_audit_logs, resuelto por
     * AcreditacionesReporteData) y taller/sesión inscrito. Mismo patrón de
     * exportación que HistorialCobrosReporteController::csvDownload().
     */
    public function csvDownload(int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}/acreditaciones-reporte");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $filas = $response->json('acreditadosDetalle') ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'referencia', 'nombre', 'apellido', 'alias_titulo', 'categoria',
            'hora_acreditacion', 'acreditado_por', 'talleres_sesiones',
        ]);
        foreach ($filas as $f) {
            fputcsv($handle, [
                $f['referencia'], $f['nombre'], $f['apellido'], $f['alias'] ?? '', $f['categoria'],
                $f['checkedInAt'] ? \Illuminate\Support\Carbon::parse($f['checkedInAt'])->format('Y-m-d H:i:s') : '',
                $f['acreditadoPor'],
                $f['inscripciones'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"acreditados-evento-{$evento}.csv\"",
        ]);
    }
}
