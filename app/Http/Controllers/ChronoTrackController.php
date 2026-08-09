<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Botón "Sincronizar con ChronoTrack" del panel — ver
 * brain/groovy-chasing-ladybug.md (Parte B). El campo `chronotrackEventId`
 * se edita acá mismo (PUT /event/{evento} vía EventoController::update(),
 * reusado), no hay un endpoint propio para eso — este controlador solo
 * agrega la pantalla y el botón de sincronizar.
 */
class ChronoTrackController extends Controller
{
    public function index(int $evento, ApiRestEventClient $client): View
    {
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        return view('eventos.resultados', ['evento' => $eventoData]);
    }

    public function sincronizar(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        // Timeout largo a propósito: la sincronización real encadena varias
        // llamadas secuenciales a la API de ChronoTrack (intervals + entries
        // + resultados paginados por carrera) — el default de 15s del
        // cliente no alcanza.
        $response = $client->forward('POST', "/event/{$evento}/chronotrack/sincronizar", timeoutSeconds: 120);

        if (!$response || !$response->json('success')) {
            $error = $response?->json('error') ?? 'No se pudo conectar con el servidor.';

            return redirect()->route('chronotrack.index', $evento)->withErrors(['general' => $error]);
        }

        $data = $response->json();

        return redirect()->route('chronotrack.index', $evento)->with('syncResult', [
            'procesados'    => $data['procesados'] ?? 0,
            'dns'           => $data['dns'] ?? 0,
            'dnf'           => $data['dnf'] ?? 0,
            'no_vinculados' => $data['no_vinculados'] ?? [],
        ]);
    }
}
