<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caja: métodos de pago nuevos — Depósito, Organizador, Cortesía
 * (30/09/2026), además de Efectivo/QR ya existentes. Ver
 * brain/PLAN-CAJA-METODOS-PAGO-30092026.md (ApiRestEvent). `CajaController`
 * es un thin-forward — estos tests confirman que las 5 opciones aparecen en
 * las vistas y que `metodo_pago` viaja tal cual al backend.
 */
class CajaMetodosPagoTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_la_pantalla_de_alta_nueva_muestra_los_5_metodos_de_pago(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => []]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/nueva')->assertOk()->getContent();

        $this->assertStringContainsString('id="mp_efectivo"', $html);
        $this->assertStringContainsString('id="mp_qr"', $html);
        $this->assertStringContainsString('id="mp_deposito"', $html);
        $this->assertStringContainsString('id="mp_organizador"', $html);
        $this->assertStringContainsString('id="mp_cortesia"', $html);
        $this->assertStringContainsString('id="cortesiaAviso"', $html);
    }

    public function test_la_pantalla_de_buscar_ofrece_los_5_botones_de_cobro(): void
    {
        Http::fake(['*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200)]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/buscar')->assertOk()->getContent();

        $this->assertStringContainsString('data-metodo="DEPOSITO"', $html);
        $this->assertStringContainsString('data-metodo="ORGANIZADOR"', $html);
        $this->assertStringContainsString('data-metodo="CORTESIA"', $html);
    }

    public function test_store_nueva_reenvia_metodo_pago_cortesia(): void
    {
        Http::fake([
            '*/event/7/caja/inscripcion' => Http::response(['success' => true, 'data' => ['id' => 1, 'referencia' => 'CAJA-TEST']], 201),
        ]);

        $this->comoAdmin()->post('/eventos/7/caja/nueva', [
            'form_types_id' => 1,
            'metodo_pago' => 'CORTESIA',
            'participante_json' => json_encode(['nombre' => 'Ana']),
            'totales_json' => json_encode(['grand_total' => 0]),
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/caja/inscripcion') && $r['metodo_pago'] === 'CORTESIA');
    }

    public function test_cierre_detalle_muestra_los_totales_de_los_metodos_nuevos(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200),
            '*/event/7/caja/turnos/9' => Http::response(['success' => true, 'turno' => [
                'id' => 9, 'cajeroId' => 1, 'cajeroNombre' => 'Ana', 'fondoInicial' => 100,
                'abiertoAt' => now()->toIso8601String(), 'cerradoAt' => null, 'estado' => 'abierto',
                'montoEsperado' => null, 'montoContado' => null, 'diferencia' => null, 'totalCobrado' => 0,
                'totalEfectivo' => 0, 'totalQr' => 0, 'totalDeposito' => 75.5, 'totalOrganizador' => 30,
                'totalCortesia' => 0, 'movimientos' => [],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/cierres/9')->assertOk()->getContent();

        $this->assertStringContainsString('75.50', $html);
        $this->assertStringContainsString('30.00', $html);
    }

    /**
     * Impresión del detalle de un turno (07/10/2026) — pedido real del
     * usuario: hasta esta fecha no había botón ni estilos de impresión,
     * un Ctrl+P imprimía con todo el menú del panel alrededor. Mismo
     * patrón que caja/eticket.blade.php (botón + @media print que oculta
     * todo salvo .cierre-imprimible).
     */
    public function test_cierre_detalle_tiene_boton_de_imprimir_y_estilos_de_impresion(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200),
            '*/event/7/caja/turnos/9' => Http::response(['success' => true, 'turno' => [
                'id' => 9, 'cajeroId' => 1, 'cajeroNombre' => 'Ana', 'fondoInicial' => 100,
                'abiertoAt' => now()->toIso8601String(), 'cerradoAt' => null, 'estado' => 'abierto',
                'montoEsperado' => null, 'montoContado' => null, 'diferencia' => null, 'totalCobrado' => 0,
                'totalEfectivo' => 0, 'totalQr' => 0, 'totalDeposito' => 0, 'totalOrganizador' => 0,
                'totalCortesia' => 0, 'movimientos' => [],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/cierres/9')->assertOk()->getContent();

        $this->assertStringContainsString('onclick="window.print()"', $html);
        $this->assertStringContainsString('class="cierre-imprimible"', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('no-print', $html);
    }
}
