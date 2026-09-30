<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caja: quitar/cambiar un taller ya pagado (29/09/2026) — ver
 * brain/PLAN-CAJA-QUITAR-TALLER-PAGADO-29092026.md (ApiRestEvent).
 * `CajaController` es un thin-forward hacia ApiRestEvent — estos tests
 * confirman que `motivo` viaja en el POST y que la vista de edición trae
 * los controles nuevos (campo de motivo, aviso ampliado).
 */
class CajaQuitarTallerPagadoTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_store_editar_reenvia_motivo_a_apirestevent(): void
    {
        Http::fake([
            '*/registrations/LA-TEST/caja/editar-pagada' => Http::response(['success' => true, 'costo_adicion' => -5], 200),
        ]);

        $this->comoAdmin()->post('/eventos/7/caja/registrations/LA-TEST/editar', [
            'pago_status' => 'paid',
            'metodo_pago' => 'EFECTIVO',
            'motivo' => 'No se concretó el ponente, se pasó a otro taller.',
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => -5]),
        ])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/registrations/LA-TEST/caja/editar-pagada')
            && $r->method() === 'PATCH'
            && $r['motivo'] === 'No se concretó el ponente, se pasó a otro taller.');
    }

    public function test_la_vista_de_editar_pagada_muestra_el_campo_de_motivo_y_el_aviso_ampliado(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => [
                ['id' => 1, 'name' => 'Individual', 'costo_edicion' => 10],
            ]]], 200),
            '*/registrations/LA-TEST' => Http::response(['data' => [
                'form_types_id' => 1,
                'pago_status' => 'paid',
                'participantes' => [[
                    'nombre' => 'Ana', 'apellido' => 'Prueba', 'categoria' => '1', 'precioCategoria' => 50,
                    'talleres' => [],
                ]],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/registrations/LA-TEST/editar')->assertOk()->getContent();

        $this->assertStringContainsString('id="f_motivo"', $html);
        $this->assertStringContainsString('id="tallerMotivoGroup"', $html);
        $this->assertStringContainsString('id="tallerCambioResumen"', $html);
        $this->assertStringContainsString('taller ya pagado que se quite o cambie', $html);
    }
}
