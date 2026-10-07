<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caja: observaciones (07/10/2026) — nota libre y opcional por cobro,
 * disponible para cualquier método de pago (Efectivo/QR/Depósito/
 * Organizador/Cortesía), a diferencia de `motivo` (29/09/2026), que es
 * específico y obligatorio solo al quitar un taller ya pagado. Ver
 * ApiRestEvent/app/Http/Controllers/CajaController.php.
 * `CajaController` (acá) es un thin-forward — estos tests confirman que el
 * campo aparece en la vista y que `observaciones` viaja tal cual al
 * backend, sin tocar el JS de cálculo de totales.
 */
class CajaObservacionesTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_la_pantalla_de_alta_nueva_muestra_el_campo_observaciones(): void
    {
        Http::fake(['*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => []]], 200)]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/nueva')->assertOk()->getContent();

        $this->assertStringContainsString('name="observaciones"', $html);
        $this->assertStringContainsString('id="f_observaciones"', $html);
    }

    public function test_store_nueva_reenvia_observaciones(): void
    {
        Http::fake(['*/event/7/caja/inscripcion' => Http::response(['success' => true, 'data' => ['id' => 1, 'referencia' => 'CAJA-TEST']], 201)]);

        $this->comoAdmin()->post('/eventos/7/caja/nueva', [
            'form_types_id' => 1,
            'metodo_pago' => 'EFECTIVO',
            'observaciones' => 'Pagó con billete de Bs 200.',
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => 50]),
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/inscripcion') && $r['observaciones'] === 'Pagó con billete de Bs 200.');
    }

    public function test_store_nueva_sin_observaciones_manda_null(): void
    {
        Http::fake(['*/event/7/caja/inscripcion' => Http::response(['success' => true, 'data' => ['id' => 1, 'referencia' => 'CAJA-TEST']], 201)]);

        $this->comoAdmin()->post('/eventos/7/caja/nueva', [
            'form_types_id' => 1,
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => 50]),
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/inscripcion') && $r['observaciones'] === null);
    }

    public function test_cobrar_pendiente_reenvia_observaciones(): void
    {
        Http::fake(['*/caja/cobrar-pendiente' => Http::response(['success' => true], 200)]);

        $this->comoAdmin()->postJson('/eventos/7/caja/registrations/LA-TEST/cobrar-pendiente', [
            'metodo_pago' => 'DEPOSITO',
            'observaciones' => 'Autorizado por el organizador.',
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/cobrar-pendiente') && $r['observaciones'] === 'Autorizado por el organizador.');
    }

    public function test_store_editar_pagada_reenvia_observaciones(): void
    {
        Http::fake(['*/registrations/LA-TEST/caja/editar-pagada' => Http::response(['success' => true, 'costo_adicion' => 0, 'data' => []], 200)]);

        $this->comoAdmin()->post('/eventos/7/caja/registrations/LA-TEST/editar', [
            'pago_status' => 'paid',
            'metodo_pago' => 'EFECTIVO',
            'observaciones' => 'Cambio de categoría a pedido del participante.',
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => 50]),
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/editar-pagada') && $r['observaciones'] === 'Cambio de categoría a pedido del participante.');
    }

    public function test_store_editar_pendiente_no_manda_observaciones(): void
    {
        Http::fake(['*/registrations/LA-TEST/caja/editar-pendiente' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->comoAdmin()->post('/eventos/7/caja/registrations/LA-TEST/editar', [
            'pago_status' => 'pending',
            'observaciones' => 'No debería viajar acá.',
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => 50]),
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/editar-pendiente') && !array_key_exists('observaciones', $r->data()));
    }

    public function test_cierre_detalle_muestra_observaciones_cuando_hay(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200),
            '*/event/7/caja/turnos/9' => Http::response(['success' => true, 'turno' => [
                'id' => 9, 'cajeroId' => 1, 'cajeroNombre' => 'Ana', 'fondoInicial' => 100,
                'abiertoAt' => now()->toIso8601String(), 'cerradoAt' => null, 'estado' => 'abierto',
                'montoEsperado' => null, 'montoContado' => null, 'diferencia' => null, 'totalCobrado' => 50,
                'totalEfectivo' => 50, 'totalQr' => 0, 'totalDeposito' => 0, 'totalOrganizador' => 0,
                'totalCortesia' => 0,
                'movimientos' => [[
                    'id' => 1, 'tipo' => 'inscripcion_nueva', 'monto' => 50, 'metodoPago' => 'EFECTIVO',
                    'motivo' => null, 'observaciones' => 'Pagó con billete de Bs 200.',
                    'registrationReferencia' => 'LA-TEST', 'createdAt' => now()->toIso8601String(), 'anulable' => true,
                ]],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/cierres/9')->assertOk()->getContent();

        $this->assertStringContainsString('Pagó con billete de Bs 200.', $html);
    }
}
