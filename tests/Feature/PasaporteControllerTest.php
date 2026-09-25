<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SmartStand fase 4 (26/09/2026) — pantalla "Pasaporte médico" (proxy hacia
 * ApiRestEvent) y las claves nuevas de configuración de expositores del evento.
 */
class PasaporteControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function pasaporteFake(array $extra = []): array
    {
        return array_merge([
            'minStands' => 5, 'empresasTotal' => 8, 'asistentesConLeads' => 40, 'elegibles' => 6,
            'distribucion' => [['stands' => 1, 'asistentes' => 30], ['stands' => 5, 'asistentes' => 6]],
            'sorteos' => [[
                'id' => 1, 'tipo' => 'pasaporte', 'premio' => 'Tablet', 'sorteadoAt' => '2026-09-26T15:30:00-04:00',
                'candidatosCount' => 6, 'filtros' => [],
                'ganador' => ['nombre' => 'Ana', 'apellido' => 'Pérez', 'correo' => 'ana@test.net', 'telefono' => '777'],
            ]],
        ], $extra);
    }

    public function test_show_muestra_resumen_distribucion_e_historial(): void
    {
        Http::fake([
            '*/event/7/pasaporte' => Http::response(['success' => true, 'data' => $this->pasaporteFake()], 200),
            '*/event/7' => Http::response(['success' => true, 'eventos' => ['name' => 'Congreso Médico']], 200),
        ]);

        $this->comoSuperAdmin()->get('/eventos/7/pasaporte')
            ->assertOk()
            ->assertSee('Congreso Médico')
            ->assertSee('Califican al sorteo')
            ->assertSee('Tablet')
            ->assertSee('Ana Pérez')
            ->assertSee('26/09/2026');
    }

    public function test_show_escapa_datos_hostiles_del_ganador_y_del_premio(): void
    {
        Http::fake([
            '*/event/7/pasaporte' => Http::response(['success' => true, 'data' => $this->pasaporteFake([
                'sorteos' => [[
                    'id' => 1, 'tipo' => 'pasaporte', 'premio' => '<script>alert(1)</script>', 'sorteadoAt' => '2026-09-26T15:30:00-04:00',
                    'candidatosCount' => 1, 'filtros' => [],
                    'ganador' => ['nombre' => '<img src=x onerror=alert(2)>', 'apellido' => '', 'correo' => '', 'telefono' => ''],
                ]],
            ])], 200),
            '*/event/7' => Http::response(['success' => true, 'eventos' => ['name' => 'Congreso']], 200),
        ]);

        $this->comoSuperAdmin()->get('/eventos/7/pasaporte')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false);
    }

    public function test_show_avisa_si_la_api_no_responde(): void
    {
        Http::fake([
            '*/event/7/pasaporte' => Http::response(['success' => false], 500),
            '*/event/7' => Http::response(['success' => true, 'eventos' => ['name' => 'Congreso']], 200),
        ]);

        $this->comoSuperAdmin()->get('/eventos/7/pasaporte')
            ->assertOk()
            ->assertSee('No se pudo obtener el estado del pasaporte');
    }

    public function test_sortear_reenvia_a_la_api_sin_reintentos_y_muestra_al_ganador(): void
    {
        Http::fake(['*/event/7/pasaporte/sorteo' => Http::response(['success' => true, 'sorteo' => [
            'ganador' => ['nombre' => 'Ana', 'apellido' => 'Pérez'],
        ]], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/pasaporte/sorteo', ['premio' => 'Tablet', 'min_stands' => '3'])
            ->assertRedirect('/eventos/7/pasaporte')
            ->assertSessionHas('status', 'Ganador del sorteo: Ana Pérez.');

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_contains($r->url(), '/event/7/pasaporte/sorteo')
            && $r['premio'] === 'Tablet'
            && (int) $r['min_stands'] === 3);
        Http::assertSentCount(1);
    }

    public function test_sortear_muestra_el_error_de_la_api_y_exige_premio(): void
    {
        Http::fake(['*/event/7/pasaporte/sorteo' => Http::response(['success' => false, 'error' => 'No hay asistentes disponibles.'], 422)]);

        $this->comoSuperAdmin()->post('/eventos/7/pasaporte/sorteo', ['premio' => 'Tablet'])
            ->assertSessionHasErrors(['general' => 'No hay asistentes disponibles.']);

        $this->comoSuperAdmin()->post('/eventos/7/pasaporte/sorteo', ['premio' => ''])
            ->assertSessionHasErrors('premio');
    }

    public function test_un_admin_de_otro_evento_recibe_403(): void
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 2, 'rol' => 'admin', 'evento_id' => 5, 'eventoIds' => [5]],
        ]);
        Http::fake();

        $this->get('/eventos/7/pasaporte')->assertStatus(403);
        $this->post('/eventos/7/pasaporte/sorteo', ['premio' => 'X'])->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_update_del_evento_manda_las_claves_nuevas_de_configuracion(): void
    {
        Http::fake(['*/event/7' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7', [
            'name' => 'Evento',
            'expositoresEspecialidadPregunta' => ' Especialidad ',
            'expositoresInstitucionPregunta'  => '',
            'expositoresPasaporteMinStands'   => '4',
            'expositoresSeguimientoHabilitado' => '1',
            'expositoresSeguimientoTyc'       => '1',
            'expositoresSeguimientoTycAt'     => '2026-09-20 09:00:00',
            'expositoresSeguimientoMax'       => '7',
        ]);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['expositoresConfig'] === [
            'especialidad_pregunta'           => 'Especialidad',
            'pasaporte_min_stands'            => 4,
            'seguimiento_max_por_asistente'   => 7,
            'seguimiento_tyc_confirmado_at'   => '2026-09-20 09:00:00',
            'seguimiento_habilitado'          => true,
        ]);
    }

    public function test_sin_tildar_habilitar_no_viaja_la_bandera_y_sin_tyc_no_viaja_el_sello(): void
    {
        Http::fake(['*/event/7' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7', [
            'name' => 'Evento',
            'expositoresSeguimientoTycAt' => '2026-09-20 09:00:00',   // hidden viejo, sin tildar
        ]);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['expositoresConfig'] === null);
    }

    public function test_el_bloque_de_configuracion_ofrece_las_preguntas_del_evento_y_marca_la_guardada(): void
    {
        $evento = [
            'id' => 7,
            'formTypes' => [
                ['id' => 50, 'preguntas' => [['id' => 1, 'etiqueta' => 'Especialidad'], ['id' => 2, 'etiqueta' => 'Hospital']]],
                ['id' => 51, 'preguntas' => [['id' => 3, 'etiqueta' => 'ESPECIALIDAD']]],
            ],
            'expositoresConfig' => ['especialidad_pregunta' => 'especialidad', 'seguimiento_habilitado' => false],
        ];

        $html = view('eventos.partials.expositores-config-fase4', ['evento' => $evento])->render();

        $this->assertStringContainsString('name="expositoresEspecialidadPregunta"', $html);
        // "Especialidad" y "ESPECIALIDAD" son la misma pregunta: una sola opción, y queda seleccionada.
        $this->assertSame(1, preg_match_all('/<option value="Especialidad"[^>]*selected/i', $html));
        $this->assertSame(0, preg_match_all('/<option value="ESPECIALIDAD"/', $html));
        $this->assertStringContainsString('<option value="Hospital"', $html);
        $this->assertStringNotContainsString('name="expositoresSeguimientoHabilitado" value="1" id="expositoresSeguimientoHabilitado" checked', $html);
    }

    public function test_el_bloque_conserva_una_pregunta_guardada_que_ya_no_existe_y_tilda_el_seguimiento(): void
    {
        $evento = [
            'id' => 7,
            'formTypes' => [],
            'expositoresConfig' => [
                'institucion_pregunta' => 'Clínica <b>x</b>',
                'seguimiento_habilitado' => true,
                'seguimiento_tyc_confirmado_at' => '2026-09-20 09:00:00',
            ],
        ];

        $html = view('eventos.partials.expositores-config-fase4', ['evento' => $evento])->render();

        $this->assertStringContainsString('Clínica &lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertMatchesRegularExpression('/id="expositoresSeguimientoHabilitado"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/id="expositoresSeguimientoTyc"[^>]*checked/', $html);
        $this->assertStringContainsString('value="2026-09-20 09:00:00"', $html);
    }
}
