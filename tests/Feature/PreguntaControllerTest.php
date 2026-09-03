<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `PreguntaController` (preguntas del formulario de inscripción) — cubre
 * puntualmente un bug real reportado por el usuario en producción
 * (03/09/2026, PUT /preguntas/{id} devolvía 500 con captura de pantalla de
 * "Server Error"): `parseOpciones()` estaba tipado `string $texto` sin
 * `?`, pero el middleware `ConvertEmptyStringsToNull` convierte el
 * textarea `opciones_texto` a `null` cuando llega vacío (caso normal para
 * cualquier `tipo_input` que no use opciones: text/email/tel/date/number/
 * textarea) — `$request->input('opciones_texto', '')` no aplica su
 * default porque la clave sí existe, solo que en `null`. Resultado: un
 * `TypeError` sin capturar en CUALQUIER alta o edición de pregunta sin
 * opciones, que es el caso más común.
 */
class PreguntaControllerTest extends TestCase
{
    private function withAdminSession(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null],
        ]);

        return $this;
    }

    public function test_update_sin_opciones_no_revienta(): void
    {
        Http::fake([
            '*/pregunta/3' => Http::response([
                'success' => true,
                'message' => 'Pregunta actualizada correctamente.',
                'formType' => ['id' => 1, 'name' => 'Individual', 'preguntas' => []],
            ], 200),
        ]);

        $response = $this->withAdminSession()->from('/eventos/1/edit')->put('/preguntas/3', [
            'evento_id' => '1',
            'seccion' => 'personal',
            'nombre_campo' => 'institucion de trabajo',
            'etiqueta' => 'institucion de trabajo',
            'tipo_input' => 'text',
            'placeholder' => 'Institución',
            'orden' => '2',
            'obligatorio' => '1',
            'visible_en_reporte' => '1',
            'opciones_texto' => '',
        ]);

        $response->assertRedirect(route('eventos.edit', 1) . '#tipos');
        $response->assertSessionHasNoErrors();
    }

    public function test_store_sin_opciones_no_revienta(): void
    {
        Http::fake([
            '*/form-type/5/preguntas' => Http::response([
                'success' => true,
                'message' => 'Pregunta creada correctamente.',
                'formType' => ['id' => 5, 'name' => 'Individual', 'preguntas' => []],
            ], 201),
        ]);

        $response = $this->withAdminSession()->from('/eventos/1/edit')->post('/formtypes/5/preguntas', [
            'evento_id' => '1',
            'seccion' => 'personal',
            'nombre_campo' => 'institucion_trabajo',
            'etiqueta' => 'Institución de trabajo',
            'tipo_input' => 'text',
            'placeholder' => '',
            'orden' => '3',
            // Vacío, no ausente — el form siempre manda el textarea, y
            // ConvertEmptyStringsToNull lo convierte a null antes de que
            // el controller lo vea. Un payload sin esta clave no
            // reproduce el bug (input() sí aplicaría su default ahí).
            'opciones_texto' => '',
        ]);

        $response->assertRedirect(route('eventos.edit', 1) . '#tipos');
        $response->assertSessionHasNoErrors();
    }

    public function test_update_con_opciones_sigue_funcionando(): void
    {
        Http::fake([
            '*/pregunta/4' => Http::response([
                'success' => true,
                'message' => 'Pregunta actualizada correctamente.',
                'formType' => ['id' => 1, 'name' => 'Individual', 'preguntas' => []],
            ], 200),
        ]);

        $response = $this->withAdminSession()->from('/eventos/1/edit')->put('/preguntas/4', [
            'evento_id' => '1',
            'seccion' => 'kit',
            'nombre_campo' => 'talla_extra',
            'etiqueta' => 'Talla extra',
            'tipo_input' => 'select',
            'placeholder' => '',
            'orden' => '1',
            'opciones_texto' => "XS\nS\nM\nL",
        ]);

        $response->assertRedirect(route('eventos.edit', 1) . '#tipos');
        $response->assertSessionHasNoErrors();

        Http::assertSent(function ($request) {
            return $request->url() === config('services.apirestevent.base_url') . '/pregunta/4'
                && $request['options'] === [
                    ['option_text' => 'XS', 'order' => 0],
                    ['option_text' => 'S', 'order' => 1],
                    ['option_text' => 'M', 'order' => 2],
                    ['option_text' => 'L', 'order' => 3],
                ];
        });
    }
}
