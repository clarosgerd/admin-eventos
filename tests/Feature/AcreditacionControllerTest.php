<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Acreditación — búsqueda por nombre/apellido (07/10/2026), ver
 * AcreditacionController::buscarPorNombre() en este repo y
 * RegistrationController::checkinBuscarPorNombre() en ApiRestEvent. Pedido
 * real del organizador: con una carga masiva de "ponentes" sin ticket
 * físico en mano, el staff en la puerta no siempre tiene el QR/referencia
 * a mano para usar la búsqueda por referencia existente.
 */
class AcreditacionControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_buscar_por_nombre_reenvia_q_y_devuelve_los_resultados(): void
    {
        Http::fake(['*/event/7/checkin-buscar*' => Http::response([
            'success' => true,
            'resultados' => [
                ['id' => 1, 'nombre' => 'Alvaro', 'apellido' => 'Justiniano Grosz', 'referencia' => 'LA-AAA', 'pagoStatus' => 'paid', 'checkedInAt' => null],
            ],
        ], 200)]);

        $response = $this->comoSuperAdmin()->getJson('/eventos/7/acreditacion/buscar?q=justiniano');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('resultados'));

        Http::assertSent(fn ($request) => ($request->data()['q'] ?? null) === 'justiniano');
    }

    public function test_sin_acceso_al_evento_devuelve_403(): void
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'admin', 'evento_id' => 99, 'eventoIds' => [99]],
        ]);

        $this->getJson('/eventos/7/acreditacion/buscar?q=justiniano')->assertStatus(403);
    }

    /**
     * Imprimir gafete directo (08/10/2026) — pedido real del usuario:
     * además del link "Imprimir gafete" (pestaña nueva, sin cambios), un
     * iframe oculto reusable + función que lo manda directo al diálogo de
     * impresión del navegador. Smoke test de render — el comportamiento
     * real del iframe/print() no es verificable con PHPUnit.
     */
    public function test_la_pantalla_trae_el_iframe_y_la_funcion_de_imprimir_directo(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso']], 200),
            '*/event/7/participantes' => Http::response(['participantes' => []], 200),
        ]);

        $html = $this->comoSuperAdmin()->get('/eventos/7/acreditacion')->assertOk()->getContent();

        $this->assertStringContainsString('id="gafeteImprimirFrame"', $html);
        $this->assertStringContainsString('function imprimirGafeteDirecto', $html);
        $this->assertStringContainsString('🖨️ Imprimir directo', $html);
        // El link existente ("Imprimir gafete", pestaña nueva) sigue intacto.
        $this->assertStringContainsString('🖨 Imprimir gafete', $html);
    }
}
