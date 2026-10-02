<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caja: anular un cobro ya registrado (02/10/2026) — ver
 * ApiRestEvent/app/Actions/AnularCobroAction.php y
 * brain/PLAN-CAJA-ANULAR-COBRO-02102026.md. `CajaController` es un
 * thin-forward (igual que CajaMetodosPagoTest) — estos tests confirman que
 * el botón/modal aparecen en la vista y que movimiento_id/motivo viajan tal
 * cual al backend, sin reimplementar ninguna validación acá.
 */
class CajaAnularCobroTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_la_pantalla_de_buscar_incluye_el_boton_y_el_modal_de_anular(): void
    {
        Http::fake(['*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200)]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/buscar')->assertOk()->getContent();

        $this->assertStringContainsString('btn-anular', $html);
        $this->assertStringContainsString('id="anularModal"', $html);
        $this->assertStringContainsString('id="anularMotivo"', $html);
    }

    public function test_movimientos_reenvia_la_consulta_a_apirestevent(): void
    {
        Http::fake([
            '*/registrations/LA-TEST/caja/movimientos' => Http::response([
                'success' => true,
                'data'    => [
                    ['id' => 1, 'tipo' => 'inscripcion_nueva', 'monto' => 52.5, 'metodoPago' => 'EFECTIVO', 'motivo' => null, 'createdAt' => now()->toIso8601String(), 'anulable' => true],
                ],
            ], 200),
        ]);

        $response = $this->comoAdmin()->getJson('/eventos/7/caja/registrations/LA-TEST/movimientos');

        $response->assertOk()->assertJson(['success' => true]);
        $response->assertJsonCount(1, 'data');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/registrations/LA-TEST/caja/movimientos') && $r->method() === 'GET');
    }

    public function test_anular_cobro_reenvia_movimiento_id_y_motivo(): void
    {
        Http::fake([
            '*/registrations/LA-TEST/caja/anular-cobro' => Http::response(['success' => true, 'message' => 'Cobro anulado.'], 200),
        ]);

        $response = $this->comoAdmin()->postJson('/eventos/7/caja/registrations/LA-TEST/anular-cobro', [
            'movimiento_id' => 42,
            'motivo'        => 'Chargeback bancario.',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/registrations/LA-TEST/caja/anular-cobro')
            && $r['movimiento_id'] === 42
            && $r['motivo'] === 'Chargeback bancario.');
    }

    public function test_anular_cobro_propaga_el_error_de_apirestevent(): void
    {
        Http::fake([
            '*/registrations/LA-TEST/caja/anular-cobro' => Http::response(['success' => false, 'error' => 'Ese movimiento ya fue anulado antes.'], 422),
        ]);

        $response = $this->comoAdmin()->postJson('/eventos/7/caja/registrations/LA-TEST/anular-cobro', [
            'movimiento_id' => 42,
            'motivo'        => 'Prueba.',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false, 'error' => 'Ese movimiento ya fue anulado antes.']);
    }

    public function test_cierre_detalle_muestra_el_label_de_anulacion(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200),
            '*/event/7/caja/turnos/9' => Http::response(['success' => true, 'turno' => [
                'id' => 9, 'cajeroId' => 1, 'cajeroNombre' => 'Ana', 'fondoInicial' => 100,
                'abiertoAt' => now()->toIso8601String(), 'cerradoAt' => null, 'estado' => 'abierto',
                'montoEsperado' => null, 'montoContado' => null, 'diferencia' => null, 'totalCobrado' => -52.5,
                'totalEfectivo' => -52.5, 'totalQr' => 0, 'totalDeposito' => 0, 'totalOrganizador' => 0,
                'totalCortesia' => 0,
                'movimientos' => [[
                    'id' => 2, 'tipo' => 'anulacion', 'monto' => -52.5, 'metodoPago' => 'EFECTIVO',
                    'motivo' => 'Chargeback bancario.', 'registrationReferencia' => 'LA-TEST',
                    'createdAt' => now()->toIso8601String(), 'anulable' => false,
                ]],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/cierres/9')->assertOk()->getContent();

        $this->assertStringContainsString('Anulación', $html);
        $this->assertStringContainsString('Chargeback bancario.', $html);
    }
}
