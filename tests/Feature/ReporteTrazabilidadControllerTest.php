<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reporte de trazabilidad de inscripciones (admin general, cross-evento,
 * 10/09/2026) — ver ApiRestEvent/brain/api_rest_event/
 * PLAN-REPORTE-TRAZABILIDAD-10092026.md. Smoke test del panel: la lógica
 * real vive en ApiRestEvent (ya cubierta por
 * tests/Feature/ReporteTrazabilidadTest.php ahí) — acá solo se confirma
 * que la vista compila y renderiza con una respuesta real de la API (evita
 * el típico "el controller pasa bien los datos pero la vista explota" que
 * un test unitario del controller no detecta).
 */
class ReporteTrazabilidadControllerTest extends TestCase
{
    private function withAdminSession(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null],
        ]);

        return $this;
    }

    private function respuestaApiFake(): array
    {
        return [
            'success' => true,
            'data' => [[
                'referencia' => 'REF123', 'eventoId' => 1, 'eventoNombre' => 'Evento Test',
                'formTypeNombre' => 'Individual', 'formTypeTipo' => 'deportivo',
                'pagoStatus' => 'paid', 'tipoPago' => 'sip', 'fecha' => now()->toIso8601String(),
                'montoInscripcion' => 105.0, 'cantidadParticipantes' => 1,
                'tienePoleras' => true, 'cantidadPoleras' => 1, 'tieneTalleres' => true, 'cantidadTalleres' => 1,
                'participantes' => [[
                    'nombre' => 'Ana', 'apellido' => 'Pérez', 'numeroDocumento' => '123',
                    'categoria' => '5K', 'tienePolera' => true, 'tallaPolera' => 'M',
                    'talleres' => [['tallerNombre' => 'Taller X', 'sesionTitulo' => 'Sesión 1', 'monto' => 80.0, 'pagoPendiente' => false]],
                ]],
                'adiciones' => [['referencia' => 'AD-1', 'monto' => 40.0, 'pagoStatus' => 'paid', 'creadoEn' => now()->toIso8601String(), 'pagadoEn' => now()->toIso8601String()]],
                'montoAdicionesPagadas' => 40.0, 'cantidadAdicionesPendientes' => 0,
            ]],
            'meta' => ['currentPage' => 1, 'lastPage' => 1, 'total' => 1, 'perPage' => 25],
            'filtrosDisponibles' => [
                'eventos' => [['id' => 1, 'nombre' => 'Evento Test']],
                'tiposEvento' => ['deportivo', 'congreso'],
                'tiposPago' => ['sip', 'multipago'],
            ],
        ];
    }

    public function test_index_renderiza_con_datos_reales_de_la_api(): void
    {
        Http::fake(['*/reporte-trazabilidad*' => Http::response($this->respuestaApiFake(), 200)]);

        $response = $this->withAdminSession()->get('/reporte-trazabilidad');

        $response->assertOk();
        $response->assertSee('REF123');
        $response->assertSee('Evento Test');
        $response->assertSee('Trazabilidad de inscripciones');
    }

    public function test_index_renderiza_sin_filas_sin_romper(): void
    {
        Http::fake(['*/reporte-trazabilidad*' => Http::response([
            'success' => true, 'data' => [],
            'meta' => ['currentPage' => 1, 'lastPage' => 1, 'total' => 0, 'perPage' => 25],
            'filtrosDisponibles' => ['eventos' => [], 'tiposEvento' => [], 'tiposPago' => []],
        ], 200)]);

        $response = $this->withAdminSession()->get('/reporte-trazabilidad');

        $response->assertOk();
        $response->assertSee('No hay inscripciones con estos filtros.');
    }

    public function test_index_502_si_la_api_falla(): void
    {
        Http::fake(['*/reporte-trazabilidad*' => Http::response(['success' => false], 500)]);

        // abort_if() lanza HttpException — se captura acá en vez de dejar
        // que el manejador de excepciones intente renderizar la página de
        // error completa (entorno de test sin ese recurso de Symfony
        // disponible, nada que ver con este controller).
        $this->withoutExceptionHandling();
        $this->withAdminSession();

        try {
            $this->get('/reporte-trazabilidad');
            $this->fail('Se esperaba un HttpException con status 502.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }
    }

    public function test_csv_download_junta_todas_las_paginas(): void
    {
        // Http::fake() por patrón de URL no distingue confiablemente por
        // querystring acá (el orden de los parámetros varía) — se decide la
        // respuesta leyendo el parámetro 'page' real de cada request.
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $referencia = $page === 1 ? 'REF-P1' : 'REF-P2';
            $monto = $page === 1 ? 100.0 : 200.0;

            return Http::response([
                'success' => true,
                'data' => [['referencia' => $referencia, 'eventoId' => 1, 'eventoNombre' => 'Evento Test', 'formTypeTipo' => 'deportivo',
                    'pagoStatus' => 'paid', 'tipoPago' => 'sip', 'fecha' => now()->toIso8601String(), 'montoInscripcion' => $monto,
                    'cantidadParticipantes' => 1, 'cantidadPoleras' => 0, 'cantidadTalleres' => 0,
                    'montoAdicionesPagadas' => 0.0, 'cantidadAdicionesPendientes' => 0]],
                'meta' => ['currentPage' => $page, 'lastPage' => 2, 'total' => 2, 'perPage' => 1],
            ], 200);
        });

        $response = $this->withAdminSession()->get('/reporte-trazabilidad/csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->getContent();
        $this->assertStringContainsString('REF-P1', $csv);
        $this->assertStringContainsString('REF-P2', $csv);
    }
}
