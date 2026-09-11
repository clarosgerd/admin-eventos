<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reporte de trazabilidad de inscripciones (admin general, cross-evento,
 * 10/09/2026) — ver ApiRestEvent/brain/api_rest_event/
 * PLAN-REPORTE-TRAZABILIDAD-10092026.md. Toda la lógica real vive en
 * ApiRestEvent (ReporteTrazabilidadData) — este controller solo arma la
 * querystring, pagina/exporta y renderiza. Solo super_admin (ruta dentro
 * del bloque `admin.superadmin` en routes/web.php — el rol real lo valida
 * ApiRestEvent vía AuthorizesEventoScope::assertIsSuperAdmin()).
 */
class ReporteTrazabilidadController extends Controller
{
    private const PER_PAGE_DEFAULT = 25;

    private const PER_PAGE_MAX = 100;

    private const ESTADO_LABELS = [
        'paid' => 'Pagado', 'pending' => 'Pendiente', 'cancelled' => 'Cancelado', 'failed' => 'Fallido',
    ];

    // Enum DISTINTO al de arriba (registrations.pago_status) — ver gotcha
    // en ReporteTrazabilidadData::mapRow(). No mezclar los 2 mapas.
    private const ESTADO_ADICION_LABELS = [
        'paid' => 'Pagado', 'pending' => 'Pendiente', 'expired' => 'Expirado', 'error' => 'Error',
    ];

    public function index(Request $request, ApiRestEventClient $client): View
    {
        $filtros = $this->filtrosDesde($request);

        $response = $client->forward('GET', '/reporte-trazabilidad', query: $this->queryDesde($filtros, [
            'per_page' => $filtros['perPage'],
            'page' => $filtros['page'],
        ]));

        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el reporte de trazabilidad.');

        return view('reporte-trazabilidad.index', [
            'filtros' => $filtros,
            'inscripciones' => $response->json('data') ?? [],
            'meta' => $response->json('meta'),
            'filtrosDisponibles' => $response->json('filtrosDisponibles') ?? ['eventos' => [], 'tiposEvento' => [], 'tiposPago' => []],
            'estadoLabels' => self::ESTADO_LABELS,
            'estadoAdicionLabels' => self::ESTADO_ADICION_LABELS,
        ]);
    }

    public function csvDownload(Request $request, ApiRestEventClient $client): Response
    {
        $filtros = $this->filtrosDesde($request);

        // Sin `per_page`/`page` a propósito en la QUERYSTRING del usuario —
        // mismo criterio que ParticipantesDetalleController::csvDownload():
        // la descarga trae TODO, sin paginar. El endpoint de ApiRestEvent sí
        // pagina siempre (tope de 100 por página) — se recorren las páginas
        // acá adentro hasta juntar todo (escala actual: ~300 inscripciones
        // en total, unos pocos requests como mucho).
        $inscripciones = [];
        $page = 1;
        do {
            $response = $client->forward('GET', '/reporte-trazabilidad', query: $this->queryDesde($filtros, [
                'per_page' => self::PER_PAGE_MAX,
                'page' => $page,
            ]));
            abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

            $inscripciones = array_merge($inscripciones, $response->json('data') ?? []);
            $lastPage = (int) ($response->json('meta.lastPage') ?? 1);
            $page++;
        } while ($page <= $lastPage);

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'referencia', 'evento', 'tipo_evento', 'fecha', 'estado', 'tipo_pago',
            'monto_inscripcion', 'cantidad_participantes', 'cantidad_poleras', 'cantidad_talleres',
            'monto_adiciones_pagadas', 'cantidad_adiciones_pendientes',
        ]);
        foreach ($inscripciones as $i) {
            fputcsv($handle, [
                $i['referencia'], $i['eventoNombre'], $i['formTypeTipo'],
                $i['fecha'] ? \Illuminate\Support\Carbon::parse($i['fecha'])->format('Y-m-d H:i') : '',
                self::ESTADO_LABELS[$i['pagoStatus']] ?? $i['pagoStatus'], $i['tipoPago'],
                $i['montoInscripcion'], $i['cantidadParticipantes'], $i['cantidadPoleras'], $i['cantidadTalleres'],
                $i['montoAdicionesPagadas'], $i['cantidadAdicionesPendientes'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte-trazabilidad.csv"',
        ]);
    }

    private function filtrosDesde(Request $request): array
    {
        return [
            'eventoId' => $request->query('evento_id', ''),
            'tipoEvento' => $request->query('tipo_evento', ''),
            'pagoStatus' => $request->query('pago_status', ''),
            'tipoPago' => $request->query('tipo_pago', ''),
            'tieneAdiciones' => $request->boolean('tiene_adiciones'),
            'fechaDesde' => $request->query('fecha_desde', ''),
            'fechaHasta' => $request->query('fecha_hasta', ''),
            'search' => trim((string) $request->query('search', '')),
            'perPage' => min((int) $request->query('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX),
            'page' => max((int) $request->query('page', 1), 1),
        ];
    }

    private function queryDesde(array $filtros, array $extra = []): array
    {
        return array_filter([
            'evento_id' => $filtros['eventoId'] ?: null,
            'tipo_evento' => $filtros['tipoEvento'] ?: null,
            'pago_status' => $filtros['pagoStatus'] ?: null,
            'tipo_pago' => $filtros['tipoPago'] ?: null,
            'tiene_adiciones' => $filtros['tieneAdiciones'] ? 1 : null,
            'fecha_desde' => $filtros['fechaDesde'] ?: null,
            'fecha_hasta' => $filtros['fechaHasta'] ?: null,
            'search' => $filtros['search'] !== '' ? $filtros['search'] : null,
        ] + $extra);
    }
}
