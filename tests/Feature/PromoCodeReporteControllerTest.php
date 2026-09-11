<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reporte de códigos promocionales usados, por evento (11/09/2026) —
 * smoke test de la vista con Http::fake; la lógica real ya está cubierta
 * en ApiRestEvent (tests/Feature/PromoCodeReporteTest.php).
 */
class PromoCodeReporteControllerTest extends TestCase
{
    private function withAdminSession(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null],
        ]);

        return $this;
    }

    public function test_index_renderiza_con_datos_reales_de_la_api(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true, 'eventos' => ['id' => 1, 'name' => 'Evento Test']], 200),
            '*/event/1/promo-codes-reporte' => Http::response([
                'success' => true,
                'filas' => [[
                    'id' => 1, 'codigo' => 'NARANJILLO10-01', 'tipo' => 'percentage', 'valor' => 0.10,
                    'usado' => true,
                    'participante' => ['nombre' => 'Ana', 'apellido' => 'Perez', 'numeroDocumento' => '123'],
                    'montoDescontado' => 15.0, 'fecha' => now()->toIso8601String(),
                ]],
                'totalCodigos' => 1, 'totalUsados' => 1, 'totalDescontado' => 15.0,
            ], 200),
        ]);

        $response = $this->withAdminSession()->get('/eventos/1/promo-codes-reporte');

        $response->assertOk();
        $response->assertSee('NARANJILLO10-01');
        $response->assertSee('Ana Perez');
        $response->assertSee('Códigos promocionales usados');
    }

    public function test_index_renderiza_sin_codigos_sin_romper(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true, 'eventos' => ['id' => 1, 'name' => 'Evento Test']], 200),
            '*/event/1/promo-codes-reporte' => Http::response([
                'success' => true, 'filas' => [], 'totalCodigos' => 0, 'totalUsados' => 0, 'totalDescontado' => 0,
            ], 200),
        ]);

        $response = $this->withAdminSession()->get('/eventos/1/promo-codes-reporte');

        $response->assertOk();
        $response->assertSee('todavía no tiene códigos promocionales cargados');
    }

    public function test_index_502_si_la_api_falla(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true, 'eventos' => ['id' => 1, 'name' => 'Evento Test']], 200),
            '*/event/1/promo-codes-reporte' => Http::response(['success' => false], 500),
        ]);

        $this->withoutExceptionHandling();
        $this->withAdminSession();

        try {
            $this->get('/eventos/1/promo-codes-reporte');
            $this->fail('Se esperaba un HttpException con status 502.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }
    }
}
