<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Edición restringida de datos de contacto/identidad de un participante —
 * nombre, apellido, alias, correo, teléfono, dirección, ciudad, género,
 * fecha de nacimiento, y talla de camiseta (solo si el participante ya
 * tiene una asignada). Nunca toca categoría, souvenirs, donación, promo
 * code ni numeración de corredor/chip (eso vive en NumeracionController).
 *
 * Del lado de ApiRestEvent: GET /event/{evento}/participantes (mismo
 * endpoint que ya usa NumeracionController, extendido con los campos de
 * contacto) y PATCH /participantes/{participante}, ambos detrás del guard
 * `admins` — acá solo se agrega la UX, la autorización real (whitelist de
 * campos + scoping por evento) la hace la API.
 */
class ParticipantesController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $categoria = $request->query('categoria', '');

        $participantesResponse = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
        ]));

        abort_if(!$participantesResponse || !$participantesResponse->json('success'), 502, 'No se pudo cargar la lista de participantes.');

        return view('eventos.participantes', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'participantes' => $participantesResponse->json('participantes') ?? [],
        ]);
    }

    public function update(Request $request, int $evento, int $participante, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $data = $request->only([
            'nombre', 'apellido', 'alias', 'correo', 'telefono', 'direccion',
            'ciudad', 'genero', 'fecha_nacimiento', 'polera',
        ]);

        $response = $client->forward('PATCH', "/participantes/{$participante}", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('participantes.index', ['evento' => $evento, 'categoria' => $request->input('categoria') ?: null])
            ->with('status', 'Participante actualizado correctamente.');
    }

    private function extractErrors($response): array
    {
        if (!$response) {
            return ['general' => 'No se pudo conectar con el servidor.'];
        }

        $errors = $response->json('errors');
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}
