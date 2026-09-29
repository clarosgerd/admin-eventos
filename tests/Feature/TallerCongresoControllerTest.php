<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Identificar talleres precongreso/formato (28/09/2026) — ver
 * brain/PLAN-REGISTRO-EFICIENTE-TALLER-PRECONGRESO-28092026.md (ApiRestEvent).
 * `TallerCongresoController` es un thin-forward hacia ApiRestEvent — estos
 * tests confirman que los 2 campos nuevos viajan en el POST/PUT y que el
 * listado los muestra.
 */
class TallerCongresoControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_el_listado_muestra_badges_de_precongreso_y_formato(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso'], 'success' => true], 200),
            '*/event/7/talleres' => Http::response(['data' => [[
                'id' => 11, 'nombre' => 'ALTO – Analgesia Multimodal', 'descripcion' => null,
                'modalidad' => 'OPTIONAL', 'precio' => 1500, 'price_usd' => null, 'orden' => 0,
                'activo' => true, 'permite_inscripcion' => true,
                'es_precongreso' => true, 'formato' => 'VIRTUAL',
                'sesiones' => [],
            ]]], 200),
        ]);

        $html = $this->comoSuperAdmin()->get('/eventos/7/talleres')->assertOk()->getContent();

        $this->assertStringContainsString('Precongreso', $html);
        $this->assertStringContainsString('Virtual', $html);
    }

    public function test_store_reenvia_es_precongreso_y_formato_a_apirestevent(): void
    {
        Http::fake([
            '*/event/7/talleres' => Http::response(['success' => true], 201),
        ]);

        $this->comoSuperAdmin()->post('/eventos/7/talleres', [
            'nombre' => 'ALTO – Analgesia Multimodal',
            'modalidad' => 'OPTIONAL',
            'precio' => 1500,
            'es_precongreso' => '1',
            'formato' => 'VIRTUAL',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/7/talleres')
                && $request->method() === 'POST'
                && $request['es_precongreso'] === '1'
                && $request['formato'] === 'VIRTUAL';
        });
    }

    public function test_update_reenvia_es_precongreso_y_formato_a_apirestevent(): void
    {
        Http::fake([
            '*/event/7/talleres/11' => Http::response(['success' => true], 200),
        ]);

        $this->comoSuperAdmin()->put('/eventos/7/talleres/11', [
            'nombre' => 'ALTO – Analgesia Multimodal',
            'es_precongreso' => '0',
            'formato' => 'HIBRIDO',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/7/talleres/11')
                && $request->method() === 'PUT'
                && $request['es_precongreso'] === '0'
                && $request['formato'] === 'HIBRIDO';
        });
    }
}
