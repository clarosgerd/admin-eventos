<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (16/10/2026): una inscripción a un
 * congreso con 2 participantes solo mostraba al primero al editar desde
 * Caja (`caja/editar.blade.php` leía `$registro['participantes'][0]` a
 * secas) — el segundo quedaba invisible e imposible de editar (ej. para
 * agregarle un taller el día del evento). Además, aunque se hubiera forzado
 * el guardado, `CajaController::storeEditar()` solo mandaba un array de 1
 * participante — `ActualizarInscripcionPagadaAction`/`ActualizarInscripcionAction`
 * (ApiRestEvent) exigen que el array completo coincida en cantidad con los
 * que ya existen (no permiten agregar/quitar), así que el guardado hubiera
 * fallado siempre con 422 para cualquier inscripción de 2+ participantes
 * (sin pérdida de datos — todo corre dentro de una transacción que hace
 * rollback — pero bloqueando por completo la edición).
 *
 * Fix: selector de participante (`?participante=N`) en la pantalla de
 * editar, y `storeEditar()` ahora reconstruye el array COMPLETO trayendo el
 * registro fresco y reemplazando solo la posición editada.
 */
class CajaEditarMultiplesParticipantesTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function registroDosParticipantes(): array
    {
        return [
            'form_types_id' => 1, 'pago_status' => 'paid',
            'participantes' => [
                [
                    'nombre' => 'Ana', 'apellido' => 'García', 'genero' => 'Femenino', 'correo' => 'ana@test.net',
                    'categoria' => '1', 'precioCategoria' => 100, 'donacion' => 0,
                    'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 100,
                    'contacto_emergencia' => ['nombre' => 'Luis', 'celular' => '700', 'relacion' => 'Padre'],
                    'talleres' => [
                        ['tallerId' => 10, 'sesionCongresoId' => 100, 'unitPrice' => 20.0, 'total' => 20.0],
                    ],
                ],
                [
                    'nombre' => 'Carlos', 'apellido' => 'Pérez', 'genero' => 'Masculino', 'correo' => 'carlos@test.net',
                    'categoria' => '1', 'precioCategoria' => 100, 'donacion' => 0,
                    'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 100,
                    'contacto_emergencia' => ['nombre' => 'Rosa', 'celular' => '701', 'relacion' => 'Madre'],
                    'talleres' => [],
                ],
            ],
        ];
    }

    public function test_pantalla_de_editar_muestra_selector_con_los_2_participantes(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => [
                ['id' => 1, 'requiereCategoria' => true, 'hasDonation' => false, 'hasPromoCode' => false],
            ]]], 200),
            '*/registrations/LA-TEST' => Http::response(['success' => true, 'data' => $this->registroDosParticipantes()], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/registrations/LA-TEST/editar')->assertOk()->getContent();

        $this->assertStringContainsString('Ana García', $html);
        $this->assertStringContainsString('Carlos Pérez', $html);
        // Default: participante 0 (Ana) prellenado en el formulario.
        $this->assertStringContainsString('value="Ana"', $html);
        $this->assertStringContainsString('name="participante_index" value="0"', $html);
    }

    public function test_participante_query_param_prellena_al_segundo_participante(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => [
                ['id' => 1, 'requiereCategoria' => true, 'hasDonation' => false, 'hasPromoCode' => false],
            ]]], 200),
            '*/registrations/LA-TEST' => Http::response(['success' => true, 'data' => $this->registroDosParticipantes()], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/registrations/LA-TEST/editar?participante=1')->assertOk()->getContent();

        $this->assertStringContainsString('value="Carlos"', $html);
        $this->assertStringContainsString('name="participante_index" value="1"', $html);
    }

    /** El fix central: guardar al 2do participante no debe perder/romper al 1ro. */
    public function test_guardar_el_2do_participante_manda_los_2_al_backend(): void
    {
        Http::fake([
            '*/registrations/LA-TEST' => Http::response(['success' => true, 'data' => $this->registroDosParticipantes()], 200),
            '*/registrations/LA-TEST/caja/editar-pagada' => Http::response(['success' => true, 'costo_adicion' => 15, 'data' => []], 200),
        ]);

        $this->comoAdmin()->post('/eventos/7/caja/registrations/LA-TEST/editar', [
            'pago_status' => 'paid',
            'participante_index' => 1,
            'participante_json' => json_encode([
                'nombre' => 'Carlos', 'apellido' => 'Pérez', 'genero' => 'Masculino', 'correo' => 'carlos@test.net',
                'categoria' => '1', 'precioCategoria' => 100, 'donacion' => 0,
                'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 100,
                'contacto_emergencia' => ['nombre' => 'Rosa', 'celular' => '701', 'relacion' => 'Madre'],
                // Agregó un taller nuevo — el escenario real reportado.
                'talleres' => [['taller_id' => 20, 'sesion_congreso_id' => 200]],
            ]),
            'totales_json' => json_encode(['grand_total' => 115]),
        ]);

        Http::assertSent(function ($r) {
            if (!str_contains($r->url(), '/caja/editar-pagada')) {
                return false;
            }
            $participantes = $r['participantes'];

            return count($participantes) === 2
                // Participante 0 (Ana) viaja intacto, con 'talleres' ya
                // traducido a snake_case (taller_id/sesion_congreso_id).
                && $participantes[0]['nombre'] === 'Ana'
                && $participantes[0]['talleres'][0]['taller_id'] === 10
                && $participantes[0]['talleres'][0]['sesion_congreso_id'] === 100
                // Participante 1 (Carlos) es el que se editó, con su taller nuevo.
                && $participantes[1]['nombre'] === 'Carlos'
                && $participantes[1]['talleres'][0]['taller_id'] === 20;
        });
    }
}
