<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — pantalla "Empresas expositoras" de admin-eventos
 * (proxy hacia ApiRestEvent), flag "Es empresa expositora" de los tipos de
 * formulario y configuración de expositores del evento.
 */
class EmpresaExpositoraControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function expositorFake(array $extra = []): array
    {
        return array_merge([
            'id' => 11, 'eventoId' => 7, 'nombre' => 'Farma Andina', 'email' => 'contacto@farma.test',
            'stand' => 'B-12', 'categoriaId' => 3, 'tamanoStand' => 'Stand 6x3', 'activo' => true,
            'credencialesEnviadas' => true, 'leadsCount' => 9,
        ], $extra);
    }

    public function test_index_muestra_las_empresas_y_solo_las_categorias_de_stand(): void
    {
        Http::fake([
            '*/event/7/empresas-expositoras' => Http::response(['success' => true, 'expositores' => [
                $this->expositorFake(),
                $this->expositorFake(['id' => 12, 'nombre' => 'Lab Sur', 'credencialesEnviadas' => false, 'leadsCount' => 0]),
            ]], 200),
            '*/event/7' => Http::response(['success' => true, 'eventos' => [
                'name' => 'Congreso Médico',
                'formTypes' => [['id' => 50, 'esExpositor' => true], ['id' => 51, 'esExpositor' => false]],
                'categories' => [
                    ['id' => 3, 'name' => 'Stand 6x3', 'formulario_id' => 50],
                    ['id' => 4, 'name' => 'Especialista', 'formulario_id' => 51],
                ],
            ]], 200),
        ]);

        $response = $this->comoSuperAdmin()->get('/eventos/7/expositores');

        $response->assertOk()
            ->assertSee('Farma Andina')
            ->assertSee('Lab Sur')
            ->assertSee('Enviado')
            ->assertSee('Sin enviar')
            ->assertSee('Congreso Médico')
            ->assertSee('Stand 6x3')
            ->assertDontSee('Especialista');
    }

    public function test_un_admin_de_otro_evento_recibe_403(): void
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 2, 'rol' => 'admin', 'evento_id' => 5, 'eventoIds' => [5]],
        ]);
        Http::fake();

        $this->get('/eventos/7/expositores')->assertStatus(403);
        $this->post('/eventos/7/expositores', ['nombre' => 'X', 'email' => 'x@x.test'])->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_store_reenvia_los_datos_a_la_api(): void
    {
        Http::fake(['*/event/7/empresas-expositoras' => Http::response(['success' => true, 'message' => 'Expositor creado y credenciales enviadas por correo.'], 201)]);

        $response = $this->comoSuperAdmin()->post('/eventos/7/expositores', [
            'nombre' => ' Farma Nueva ', 'email' => 'nueva@farma.test', 'stand' => '', 'categoria_id' => '3',
        ]);

        $response->assertRedirect('/eventos/7/expositores');
        $response->assertSessionHas('status', 'Expositor creado y credenciales enviadas por correo.');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/event/7/empresas-expositoras')
            && $request['nombre'] === 'Farma Nueva'
            && $request['categoria_id'] === 3
            && $request['stand'] === null
            && !isset($request['activo']));
    }

    public function test_store_muestra_el_error_de_validacion_de_la_api(): void
    {
        Http::fake(['*/event/7/empresas-expositoras' => Http::response(['message' => 'x', 'errors' => ['email' => ['El correo ya está en uso.']]], 422)]);

        $this->comoSuperAdmin()->post('/eventos/7/expositores', ['nombre' => 'X', 'email' => 'dup@farma.test'])
            ->assertRedirect('/eventos/7/expositores')
            ->assertSessionHasErrors(['email' => 'El correo ya está en uso.']);
    }

    /** El checkbox destildado no viaja en el POST: hay que mandar activo=false igual, o nunca se podría desactivar. */
    public function test_update_manda_activo_false_si_el_checkbox_no_viaja(): void
    {
        Http::fake(['*/empresas-expositoras/11' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7/expositores/11', [
            'nombre' => 'Farma Andina', 'email' => 'contacto@farma.test', 'stand' => 'C-01', 'categoria_id' => '',
        ])->assertRedirect('/eventos/7/expositores');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request['activo'] === false
            && $request['stand'] === 'C-01'
            && $request['categoria_id'] === null);
    }

    public function test_destroy_muestra_el_motivo_si_la_api_lo_rechaza(): void
    {
        Http::fake(['*/empresas-expositoras/11' => Http::response(['success' => false, 'error' => 'Esta empresa ya capturó contactos y no se puede eliminar. Desactívala en su lugar.'], 409)]);

        $this->comoSuperAdmin()->delete('/eventos/7/expositores/11')
            ->assertSessionHasErrors(['general' => 'Esta empresa ya capturó contactos y no se puede eliminar. Desactívala en su lugar.']);
    }

    public function test_reenviar_llama_al_endpoint_de_credenciales_sin_reintentos(): void
    {
        Http::fake(['*/empresas-expositoras/11/reenviar-credenciales' => Http::response(['success' => true, 'message' => 'Credenciales reenviadas por correo.'], 200)]);

        $this->comoSuperAdmin()->post('/eventos/7/expositores/11/reenviar')
            ->assertSessionHas('status', 'Credenciales reenviadas por correo.');

        Http::assertSentCount(1);
    }

    public function test_show_muestra_las_estadisticas_de_la_empresa(): void
    {
        Http::fake(['*/empresas-expositoras/11/dashboard' => Http::response(['success' => true,
            'expositor' => $this->expositorFake(),
            'data' => [
                'totalLeads' => 9, 'calificacionPromedio' => 4.25,
                'porDia' => [['fecha' => '2026-10-15', 'total' => 6], ['fecha' => '2026-10-16', 'total' => 3]],
                'porCiudad' => [['ciudad' => 'Sucre', 'total' => 5], ['ciudad' => 'Sin dato', 'total' => 4]],
            ],
        ], 200)]);

        $this->comoSuperAdmin()->get('/eventos/7/expositores/11')
            ->assertOk()
            ->assertSee('Farma Andina')
            ->assertSee('15/10/2026')
            ->assertSee('Sucre')
            ->assertSee('4.3'); // número redondeado a 1 decimal
    }

    public function test_show_da_404_si_la_api_no_encuentra_la_empresa(): void
    {
        Http::fake(['*/empresas-expositoras/99/dashboard' => Http::response(['message' => 'No query results'], 404)]);

        $this->comoSuperAdmin()->get('/eventos/7/expositores/99')->assertNotFound();
    }

    // ── Flag del tipo de formulario y configuración del evento ──────────

    public function test_formtype_store_y_update_mandan_es_expositor(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->post('/eventos/7/formtypes', ['name' => 'Expositor', 'es_expositor' => '1'])
            ->assertRedirect();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/form-type') && $r['es_expositor'] === true);

        // update() arma el redirect con el evento_id que manda el formulario.
        $this->comoSuperAdmin()->put('/formtypes/50', ['name' => 'Expositor', 'evento_id' => 7])->assertRedirect();
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/form-type/50') && $r['es_expositor'] === false);
    }

    public function test_update_del_evento_manda_solo_las_claves_de_expositores_con_valor(): void
    {
        Http::fake(['*/event/7' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7', [
            'name' => 'Evento',
            'expositoresAppUrlAndroid' => ' https://play.google.com/store/apps/details?id=x ',
            'expositoresAppUrlIos' => '',
            'expositoresInstrucciones' => 'Retira tu credencial.',
        ]);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['expositoresConfig'] === [
            'app_url_android' => 'https://play.google.com/store/apps/details?id=x',
            'instrucciones'   => 'Retira tu credencial.',
        ]);
    }

    public function test_update_del_evento_manda_null_si_se_vacian_todos_los_campos_de_expositores(): void
    {
        Http::fake(['*/event/7' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7', ['name' => 'Evento']);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && array_key_exists('expositoresConfig', $r->data()) && $r['expositoresConfig'] === null);
    }
}
