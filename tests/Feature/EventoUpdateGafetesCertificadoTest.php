<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gafetes/certificados parametrizables por evento (13/09/2026) — pedido real
 * de COLABIOCLI 2026. EventoController::update() (admin-eventos) es un
 * proxy: confirma que certificadoSoloNombre/gafeteConfig se mandan siempre
 * en el payload hacia ApiRestEvent, incluso cuando el checkbox no está
 * tildado o los inputs de tamaño vienen vacíos (mismo patrón ya establecido
 * para aceptaUsd/usdPrecioFijo — destildear/vaciar también debe persistir).
 */
class EventoUpdateGafetesCertificadoTest extends TestCase
{
    private function withAdminSession(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null],
        ]);

        return $this;
    }

    public function test_update_manda_certificado_solo_nombre_y_gafete_config_completo(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true], 200),
        ]);

        $this->withAdminSession()->put('/eventos/1', [
            'name' => 'Evento Test',
            'certificadoSoloNombre' => '1',
            'gafeteWidthCm' => '8',
            'gafeteHeightCm' => '6',
            'gafetePerRow' => '2',
            'gafetePaper' => 'letter',
            'gafeteOrientation' => 'portrait',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/1')
                && $request->method() === 'PUT'
                && $request['certificadoSoloNombre'] === true
                && $request['gafeteConfig'] === [
                    'tipo' => 'completo',
                    'width_cm' => 8.0, 'height_cm' => 6.0, 'per_row' => 2,
                    'paper' => 'letter', 'orientation' => 'portrait',
                ];
        });
    }

    /** Gafete tipo "pegatina" (23/09/2026) — gafeteTipo=label persiste en el payload. */
    public function test_update_manda_gafete_config_tipo_label(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true], 200),
        ]);

        $this->withAdminSession()->put('/eventos/1', [
            'name' => 'Evento Test',
            'gafeteTipo' => 'label',
            'gafeteWidthCm' => '3',
            'gafeteHeightCm' => '3',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/1')
                && $request['gafeteConfig']['tipo'] === 'label';
        });
    }

    /**
     * Checkbox destildeado (no viaja en el POST) y ancho de gafete vacío —
     * ambos deben persistir como false/null, no quedar ausentes del
     * payload (si no, el checkbox nunca se podría "apagar" una vez
     * prendido).
     */
    public function test_update_sin_checkbox_ni_ancho_manda_false_y_null(): void
    {
        Http::fake([
            '*/event/1' => Http::response(['success' => true], 200),
        ]);

        $this->withAdminSession()->put('/eventos/1', [
            'name' => 'Evento Test',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/event/1')
                && $request['certificadoSoloNombre'] === false
                && $request['gafeteConfig'] === null;
        });
    }

    /**
     * Smoke test de renderizado real (no `php -l`, que trivialmente pasa en
     * cualquier .blade.php sin `<?php` literal), con datos de CIACRUZ
     * (evento real id=1) capturados en vivo de ApiRestEvent local —
     * confirma que la sección nueva no rompe el resto de la pantalla de
     * edición con un evento existente real.
     */
    public function test_edit_renderiza_con_datos_reales_de_ciacruz(): void
    {
        $eventoJson = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/evento1_real.json'),
            true
        );

        Http::fake([
            '*/tipos-evento' => Http::response(['tiposEvento' => []], 200),
            '*/organizadores*' => Http::response(['organizadores' => []], 200),
            '*/event/1' => Http::response($eventoJson, 200),
        ]);

        $response = $this->withAdminSession()->get('/eventos/1/edit');

        $response->assertOk();
        $response->assertSee('Certificado solo con nombre');
        $response->assertSee('Tamaño de gafete');
    }

    /**
     * Ocultar Fecha de nacimiento/Género por tipo de formulario (26/09/2026):
     * los checkboxes existen en el bloque del form_type y quedan marcados
     * según `camposOcultos`.
     */
    public function test_edit_muestra_checkboxes_de_nacimiento_y_genero_marcados_segun_campos_ocultos(): void
    {
        $eventoJson = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/evento1_real.json'),
            true
        );
        $eventoJson['eventos']['formTypes'][0]['camposOcultos'] = ['nacimiento'];
        $eventoJson['eventos']['formTypes'][0]['permite_inscripcion_grupal'] = true;
        $eventoJson['eventos']['formTypes'][0]['max_integrantes_grupo'] = 7;
        $eventoJson['eventos']['formTypes'][0]['descuento_registrante_pct'] = 0.2;

        Http::fake([
            '*/tipos-evento' => Http::response(['tiposEvento' => []], 200),
            '*/organizadores*' => Http::response(['organizadores' => []], 200),
            '*/event/1' => Http::response($eventoJson, 200),
        ]);

        $html = $this->withAdminSession()->get('/eventos/1/edit')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="campos_ocultos\[\]" value="nacimiento"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="campos_ocultos\[\]" value="genero"\s+checked/', $html);
        $this->assertStringContainsString('value="genero"', $html);
        // Apellido (26/09/2026): existe el checkbox y, sin estar en camposOcultos, no viene marcado.
        $this->assertStringContainsString('value="apellido"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="campos_ocultos\[\]" value="apellido"\s+checked/', $html);

        // Solo un participante (26/09/2026): el checkbox existe y viene marcado según `unSoloParticipante`.
        $this->assertStringContainsString('name="un_solo_participante"', $html);
        // Staff/ponente (26/09/2026): las casillas marcan "sin costo" y "Requiere categoría" se puede bloquear.
        $this->assertStringContainsString('name="es_staff" value="1" data-sin-costo', $html);
        $this->assertStringContainsString('name="es_ponente" value="1" data-sin-costo', $html);
        $this->assertStringContainsString('data-requiere-categoria', $html);
        $this->assertStringContainsString('Tema de la charla', $html);
        // Inscripción grupal (26/09/2026): casilla + N + % del tipo existente con sus valores actuales.
        $this->assertStringContainsString('name="permite_inscripcion_grupal"', $html);
        $this->assertMatchesRegularExpression('/name="max_integrantes_grupo" value="7"/', $html);
        $this->assertMatchesRegularExpression('/name="descuento_registrante_pct" value="20"/', $html);
        $this->assertMatchesRegularExpression('/name="permite_inscripcion_grupal" value="1"\s+checked/', $html);
    }
}
