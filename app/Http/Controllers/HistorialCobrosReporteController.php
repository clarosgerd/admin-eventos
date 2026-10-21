<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reporte de historial de cobros por inscripción, por evento (22/10/2026,
 * pedido real del usuario) — para cada inscripción pagada, la secuencia
 * cronológica de movimientos (alta inicial + cada adición en
 * autoservicio), con el monto desglosado en contenido/fee/costo de
 * edición. Toda la lógica real vive en ApiRestEvent
 * (HistorialCobrosReporteData) — este controller solo arma la pantalla y
 * exporta el CSV. Mismo patrón que PromoCodeReporteController (pantalla
 * de solo lectura, por evento) y ReporteTrazabilidadController::
 * csvDownload() (formato del CSV).
 */
class HistorialCobrosReporteController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/historial-cobros-reporte");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el reporte de historial de cobros.');

        return view('eventos.historial-cobros-reporte', [
            'evento' => $eventoData,
            'filas' => $response->json('filas') ?? [],
            'resumen' => $response->json('resumen') ?? [],
        ]);
    }

    public function csvDownload(int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}/historial-cobros-reporte");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $filas = $response->json('filas') ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'referencia_inscripcion', 'tipo_movimiento', 'referencia_movimiento', 'fecha_movimiento',
            'monto', 'fee', 'costo_edicion', 'contenido_nuevo', 'es_estimado', 'total_acumulado',
            'participante_principal', 'anomalias',
        ]);
        foreach ($filas as $f) {
            fputcsv($handle, [
                $f['referenciaInscripcion'], $f['tipoMovimiento'], $f['referenciaMovimiento'],
                $f['fechaMovimiento'] ? \Illuminate\Support\Carbon::parse($f['fechaMovimiento'])->format('Y-m-d H:i:s') : '',
                $f['monto'], $f['fee'], $f['costoEdicion'], $f['contenidoNuevo'],
                $f['esEstimado'] ? 'SI' : 'NO', $f['grandTotalAcumulado'], $f['participantePrincipal'],
                implode(';', $f['anomalias'] ?? []),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="historial-cobros-evento-'.$evento.'.csv"',
        ]);
    }
}
