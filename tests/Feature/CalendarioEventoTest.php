<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Calendario del evento (05/10/2026): la pantalla lee el calendario de
 * ApiRestEvent y renderiza mes, semana y día con los bloques en su día.
 */
class CalendarioEventoTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function fakeApi(): void
    {
        Http::fake([
            '*/event/7/calendario' => Http::response(['success' => true, 'calendario' => [
                'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-12',
                'bloques' => [[
                    'tipo' => 'sesion', 'titulo' => 'Ponencia de apertura', 'fecha' => '2026-11-11',
                    'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00', 'sala' => 'Auditorio', 'ponente' => 'Dra. Y',
                ]],
            ]], 200),
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso Test', 'tipoEvento' => 'Congreso / No aplica']], 200),
        ]);
    }

    public function test_muestra_el_bloque_en_la_vista_mes(): void
    {
        $this->fakeApi();

        $html = $this->comoAdmin()->get('/eventos/7/calendario?vista=mes&fecha=2026-11-10')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ponencia de apertura', $html);
        $this->assertStringContainsString('Auditorio', $html);
    }

    public function test_muestra_el_bloque_en_la_vista_semana_y_dia(): void
    {
        $this->fakeApi();

        $semana = $this->comoAdmin()->get('/eventos/7/calendario?vista=semana&fecha=2026-11-11')->assertOk()->getContent();
        $dia = $this->comoAdmin()->get('/eventos/7/calendario?vista=dia&fecha=2026-11-11')->assertOk()->getContent();

        $this->assertStringContainsString('Ponencia de apertura', $semana);
        $this->assertStringContainsString('Dra. Y', $dia);
    }

    public function test_vista_invalida_cae_a_mes(): void
    {
        $this->fakeApi();

        $this->comoAdmin()->get('/eventos/7/calendario?vista=zzz&fecha=2026-11-10')
            ->assertOk()
            ->assertSee('Noviembre 2026');
    }
}
