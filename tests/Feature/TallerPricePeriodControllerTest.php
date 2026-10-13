<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Precios por período de un taller (12/10/2026, pedido del usuario) —
 * `TallerPricePeriodController` es un thin-forward hacia ApiRestEvent,
 * mismo patrón que CategoryPricePeriodController.
 */
class TallerPricePeriodControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_el_listado_muestra_el_precio_vigente_y_los_periodos(): void
    {
        Http::fake([
            '*/event/7/talleres/11' => Http::response(['success' => true, 'data' => [
                'id' => 11, 'nombre' => 'Ética', 'precio' => 80, 'precioVigente' => 50,
                'periodoVigenteNombre' => 'Preventa', 'precioUsdVigente' => null,
                'periodos' => [
                    ['id' => 1, 'nombre' => 'Preventa', 'price' => 50, 'price_usd' => null, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20'],
                ],
            ]], 200),
        ]);

        $html = $this->comoSuperAdmin()->get('/eventos/7/talleres/11/periodos')->assertOk()->getContent();

        $this->assertStringContainsString('Preventa', $html);
        $this->assertStringContainsString('50', $html);
    }

    public function test_store_reenvia_el_payload_correcto(): void
    {
        Http::fake(['*/taller/11/periodos' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/talleres/11/periodos', [
            'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/taller/11/periodos')
                && $request->method() === 'POST'
                && $request['nombre'] === 'Preventa'
                && $request['price'] === 50;
        });
    }

    /** Período por categoría (13/10/2026, pedido del usuario). */
    public function test_store_reenvia_category_id(): void
    {
        Http::fake(['*/taller/11/periodos' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/talleres/11/periodos', [
            'nombre' => 'Especialista preventa', 'price' => 100, 'category_id' => '90',
            'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller/11/periodos') && $request['category_id'] === '90');
    }

    public function test_el_listado_pasa_las_categorias_del_evento_a_la_vista(): void
    {
        Http::fake([
            '*/event/7/talleres/11' => Http::response(['success' => true, 'data' => [
                'id' => 11, 'nombre' => 'Ética', 'precio' => 80, 'precioVigente' => 50,
                'periodoVigenteNombre' => 'Preventa', 'precioUsdVigente' => null,
                'periodos' => [
                    ['id' => 1, 'categoriaId' => 90, 'categoriaNombre' => 'Especialista', 'nombre' => 'Preventa especialista', 'price' => 100, 'price_usd' => null, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20'],
                ],
            ]], 200),
            '*/event/7' => Http::response(['eventos' => [
                'id' => 7, 'name' => 'Evento',
                'categories' => [['id' => 90, 'name' => 'Especialista'], ['id' => 91, 'name' => 'Licenciado']],
            ], 'success' => true], 200),
        ]);

        $html = $this->comoSuperAdmin()->get('/eventos/7/talleres/11/periodos')->assertOk()->getContent();

        $this->assertStringContainsString('Especialista', $html);
        $this->assertStringContainsString('Licenciado', $html);
    }

    public function test_update_reenvia_al_endpoint_correcto(): void
    {
        Http::fake(['*/taller-price-period/5' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7/talleres/11/periodos/5', [
            'nombre' => 'Preventa', 'price' => 55,
            'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-price-period/5') && $request->method() === 'PUT');
    }

    public function test_update_reenvia_category_id(): void
    {
        Http::fake(['*/taller-price-period/5' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7/talleres/11/periodos/5', [
            'nombre' => 'Preventa', 'price' => 55, 'category_id' => '',
            'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-price-period/5') && array_key_exists('category_id', $request->data()));
    }

    public function test_destroy_llama_al_delete(): void
    {
        Http::fake(['*/taller-price-period/5' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->delete('/eventos/7/talleres/11/periodos/5')->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/taller-price-period/5'));
    }
}
