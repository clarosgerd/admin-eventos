<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ponente/expositor (26/09/2026): al "+ Vincular ponente" a una sesión, el organizador ve lo que cada ponente
 * dijo que va a dictar en el formulario (preguntas `taller_dictara` y `tema_charla`). La vinculación sigue
 * siendo manual.
 */
class SesionesPonentesDictaraTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_el_selector_de_ponentes_muestra_taller_y_tema_que_indicaron(): void
    {
        Http::fake([
            '*/event/7/sesiones*' => Http::response(['data' => [[
                'id' => 31, 'titulo' => 'Mesa de urgencias', 'fecha' => '2026-10-26', 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
                'ponente' => null, 'ponente_cargo' => null, 'activa' => true, 'requiere_inscripcion' => false, 'modalidad' => null, 'taller_id' => null, 'sala' => null, 'cupo' => null, 'precio' => null, 'price_usd' => null,
                'staff_asignado' => [], 'ponentes_vinculados' => [],
            ]]], 200),
            '*/event/7/talleres*' => Http::response(['data' => []], 200),
            '*/event/7/staff-disponible*' => Http::sequence()
                ->push(['data' => []], 200) // staff
                ->push(['data' => [
                    ['id' => 501, 'nombre' => 'Lucía', 'apellido' => 'Paz', 'correo' => 'l@x.test', 'taller_dictara' => 'Taller de Ecografía — 2026-10-26 09:00', 'tema_charla' => null],
                    ['id' => 502, 'nombre' => 'Mario', 'apellido' => 'Rey', 'correo' => 'm@x.test', 'taller_dictara' => null, 'tema_charla' => 'Ultrasonido en urgencias'],
                    ['id' => 503, 'nombre' => 'Sara', 'apellido' => 'Gil', 'correo' => 's@x.test', 'taller_dictara' => null, 'tema_charla' => null],
                ]], 200), // ponentes
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'nombre' => 'Congreso', 'tipo' => 'Congreso'], 'success' => true], 200),
        ]);

        $this->withoutExceptionHandling();
        $html = $this->comoSuperAdmin()->get('/eventos/7/sesiones')->assertOk()->getContent();

        $this->assertStringContainsString('Lucía Paz', $html);
        $this->assertStringContainsString('Taller de Ecografía — 2026-10-26 09:00', $html);
        $this->assertStringContainsString('Mario Rey — Ultrasonido en urgencias', $html);
        // Sin respuesta: solo el nombre, sin guion colgando.
        $this->assertMatchesRegularExpression('/>Sara Gil<\/option>/', $html);
    }
}
