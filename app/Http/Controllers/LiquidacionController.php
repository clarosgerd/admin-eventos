<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación financiera — pantalla de liquidación de utilidades por
 * evento. Llama a /event/{evento}/liquidacion(/preview) de ApiRestEvent —
 * todo el cálculo vive ahí (LiquidarEventoAction), acá solo se muestra.
 * Solo accesible bajo `admin.superadmin` (ver routes/web.php) — a
 * diferencia de Numeración/Acreditación, esta pantalla no es para el
 * admin scoped a su propio evento. Ver elascenso/event/brain/ (sesión
 * 11/08/2026).
 */
class LiquidacionController extends Controller
{
    public function show(int $evento, ApiRestEventClient $client): View
    {
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $liquidacionResponse = $client->forward('GET', "/event/{$evento}/liquidacion");
        $liquidacion = $liquidacionResponse?->json('success') ? $liquidacionResponse->json('data') : null;

        $preview = null;
        if (!$liquidacion) {
            $previewResponse = $client->forward('GET', "/event/{$evento}/liquidacion/preview");
            $preview = $previewResponse?->json('success') ? $previewResponse->json() : null;
        }

        return view('eventos.liquidacion', [
            'evento' => $eventoData,
            'liquidacion' => $liquidacion,
            'preview' => $preview,
        ]);
    }

    public function store(int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/event/{$evento}/liquidacion");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo liquidar el evento.']);
        }

        return redirect()->route('liquidacion.show', $evento)->with('status', 'Evento liquidado correctamente.');
    }
}
