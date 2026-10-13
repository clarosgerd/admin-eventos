<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) —
 * `TallerComboController` es un thin-forward hacia ApiRestEvent, mismo
 * patrón que TallerCongresoController.
 */
class TallerComboControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function fakeEventoYTalleres(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => [
                'id' => 7, 'name' => 'Congreso',
                'categories' => [['id' => 90, 'name' => 'Especialistas'], ['id' => 91, 'name' => 'Residentes']],
            ], 'success' => true], 200),
            '*/event/7/talleres' => Http::response(['success' => true, 'data' => [
                ['id' => 11, 'nombre' => 'Taller A'],
                ['id' => 12, 'nombre' => 'Taller B'],
            ]], 200),
            '*/event/7/taller-combos' => Http::response(['success' => true, 'data' => [
                [
                    'id' => 1, 'eventoId' => 7, 'nombre' => 'Combo A+B',
                    'fechaDesde' => null, 'fechaHasta' => null,
                    'activo' => true, 'vigente' => true, 'tallerIds' => [11, 12],
                    'precios' => [
                        ['id' => 5, 'categoriaId' => 90, 'categoriaNombre' => 'Especialistas', 'precio' => 1000],
                    ],
                ],
            ]], 200),
        ]);
    }

    public function test_el_listado_muestra_el_combo_y_sus_talleres(): void
    {
        $this->fakeEventoYTalleres();

        $html = $this->comoSuperAdmin()->get('/eventos/7/taller-combos')->assertOk()->getContent();

        $this->assertStringContainsString('Combo A+B', $html);
        $this->assertStringContainsString('Taller A', $html);
        $this->assertStringContainsString('Taller B', $html);
        $this->assertStringContainsString('Especialistas', $html);
        $this->assertStringContainsString('1,000.00', $html);
    }

    public function test_store_reenvia_el_payload_con_taller_ids_sin_precio(): void
    {
        Http::fake(['*/event/7/taller-combos' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/taller-combos', [
            'nombre' => 'Combo A+B',
            'taller_ids' => ['11', '12'], 'activo' => '1',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/7/taller-combos')
                && $request->method() === 'POST'
                && $request['nombre'] === 'Combo A+B'
                && $request['taller_ids'] === ['11', '12']
                && ! array_key_exists('precio_combo', $request->data());
        });
    }

    public function test_update_reenvia_al_endpoint_correcto(): void
    {
        Http::fake(['*/taller-combo/1' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7/taller-combos/1', [
            'nombre' => 'Combo A+B',
            'taller_ids' => ['11', '12'],
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-combo/1') && $request->method() === 'PUT');
    }

    public function test_destroy_llama_al_delete(): void
    {
        Http::fake(['*/taller-combo/1' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->delete('/eventos/7/taller-combos/1')->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/taller-combo/1'));
    }
}
