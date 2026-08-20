<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Preguntas adicionales del formulario de inscripción (20/08/2026) —
 * llama a /form-type/{formType}/preguntas y /pregunta/{id} (ApiRestEvent,
 * FormularioCamposController, implementado el mismo día). Mismo patrón
 * que SouvenirController: `evento_id` viaja como campo oculto en cada
 * form porque destroy() no tiene forma de recuperarlo después de borrar.
 *
 * Las opciones (select/radio/checkbox) se cargan como un textarea de una
 * línea por opción — más simple que filas dinámicas de JS, consistente
 * con el resto del panel (formularios planos, sin build step).
 */
class PreguntaController extends Controller
{
    public function store(Request $request, int $form_type, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('seccion', 'nombre_campo', 'etiqueta', 'tipo_input', 'placeholder', 'orden'),
            [
                'obligatorio'        => $request->boolean('obligatorio'),
                'visible_en_reporte' => $request->boolean('visible_en_reporte'),
                'options'            => $this->parseOpciones($request->input('opciones_texto', '')),
            ]
        );

        $response = $client->forward('POST', "/form-type/{$form_type}/preguntas", body: $payload);

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#tipos')->with('status', 'Pregunta creada correctamente.');
    }

    public function update(Request $request, int $pregunta, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only('seccion', 'nombre_campo', 'etiqueta', 'tipo_input', 'placeholder', 'orden'),
            [
                'obligatorio'        => $request->boolean('obligatorio'),
                'visible_en_reporte' => $request->boolean('visible_en_reporte'),
                'options'            => $this->parseOpciones($request->input('opciones_texto', '')),
            ]
        );

        $response = $client->forward('PUT', "/pregunta/{$pregunta}", body: $payload);

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#tipos')->with('status', 'Pregunta actualizada correctamente.');
    }

    public function destroy(Request $request, int $pregunta, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/pregunta/{$pregunta}");

        $eventoId = $request->input('evento_id');
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $eventoId) . '#tipos')->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $eventoId) . '#tipos')->with('status', 'Pregunta eliminada correctamente.');
    }

    /**
     * Una opción por línea en el textarea → array de {option_text, order}.
     * Líneas vacías se descartan (espaciado accidental del organizador no
     * debe crear una opción en blanco).
     */
    private function parseOpciones(string $texto): array
    {
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $texto)), fn ($l) => $l !== ''));

        return array_map(fn ($l, $i) => ['option_text' => $l, 'order' => $i], $lineas, array_keys($lineas));
    }

    private function extractErrors($response): array
    {
        if (!$response) {
            return ['general' => 'No se pudo conectar con el servidor.'];
        }

        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        $detalle = $response->json('error') ?: $response->json('message');
        if ($detalle) {
            return ['general' => $detalle];
        }

        return ['general' => sprintf(
            'Ocurrió un error. HTTP %d: %s',
            $response->status(),
            substr($response->body(), 0, 500) ?: '(respuesta vacía)'
        )];
    }
}
