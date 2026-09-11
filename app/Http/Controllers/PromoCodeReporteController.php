<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\View\View;

/**
 * Reporte de códigos promocionales usados, por evento (11/09/2026) —
 * pedido por el usuario al generar códigos reales para Naranjillo Ultra
 * Trail: no existía ningún reporte, solo la pestaña "Promos" del editor de
 * evento (usado/sin-usar por código, sin decir quién ni cuánto). Mismo
 * patrón que ParticipantesDetalleController: pantalla de solo lectura,
 * alcanzada con un link desde donde ya se está (acá, la pestaña Promos),
 * no embebida inline.
 */
class PromoCodeReporteController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/promo-codes-reporte");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el reporte de códigos promocionales.');

        return view('eventos.promo-codes-reporte', [
            'evento' => $eventoData,
            'filas' => $response->json('filas') ?? [],
            'totalCodigos' => $response->json('totalCodigos') ?? 0,
            'totalUsados' => $response->json('totalUsados') ?? 0,
            'totalDescontado' => $response->json('totalDescontado') ?? 0,
        ]);
    }
}
