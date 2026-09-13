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
                    'maxUsos' => 1, 'vecesUsado' => 1, 'usado' => true,
                    // Multi-uso (13/09/2026) — el reporte ya no manda un
                    // participante fijo, manda una lista `usos[]`.
                    'usos' => [[
                        'participante' => ['nombre' => 'Ana', 'apellido' => 'Perez', 'numeroDocumento' => '123'],
                        'montoDescontado' => 15.0, 'fecha' => now()->toIso8601String(),
                    ]],
                ]],
                'totalCodigos' => 1, 'totalUsados' => 1, 'totalUsos' => 1, 'totalDescontado' => 15.0,
            ], 200),
        ]);

        $response = $this->withAdminSession()->get('/eventos/1/promo-codes-reporte');

        $response->assertOk();
        $response->assertSee('NARANJILLO10-01');
        $response->assertSee('Ana Perez');
        $response->assertSee('Códigos promocionales usados');
    }

    /**
     * Multi-uso (13/09/2026) — un código con varios usos muestra a todos
     * los participantes, no solo el último/único como antes.
     */
    public function test_index_muestra_usos_multiples_para_codigo_multi_uso(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true, 'eventos' => ['id' => 1, 'name' => 'Evento Test']], 200),
            '*/event/1/promo-codes-reporte' => Http::response([
                'success' => true,
                'filas' => [[
                    'id' => 1, 'codigo' => 'MULTI5', 'tipo' => 'fixed_price', 'valor' => 20.0,
                    'maxUsos' => 5, 'vecesUsado' => 2, 'usado' => false,
                    'usos' => [
                        ['participante' => ['nombre' => 'Ana', 'apellido' => 'Perez', 'numeroDocumento' => '111'], 'montoDescontado' => 20.0, 'fecha' => now()->toIso8601String()],
                        ['participante' => ['nombre' => 'Luis', 'apellido' => 'Gomez', 'numeroDocumento' => '222'], 'montoDescontado' => 20.0, 'fecha' => now()->toIso8601String()],
                    ],
                ]],
                'totalCodigos' => 1, 'totalUsados' => 1, 'totalUsos' => 2, 'totalDescontado' => 40.0,
            ], 200),
        ]);

        $response = $this->withAdminSession()->get('/eventos/1/promo-codes-reporte');

        $response->assertOk();
        $response->assertSee('MULTI5');
        $response->assertSee('2/5 usos');
        $response->assertSee('Ana Perez');
        $response->assertSee('Luis Gomez');
    }

    public function test_index_renderiza_sin_codigos_sin_romper(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true, 'eventos' => ['id' => 1, 'name' => 'Evento Test']], 200),
            '*/event/1/promo-codes-reporte' => Http::response([
                'success' => true, 'filas' => [], 'totalCodigos' => 0, 'totalUsados' => 0, 'totalUsos' => 0, 'totalDescontado' => 0,
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
