<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use App\Support\CalendarioGrilla;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Calendario del evento (05/10/2026): agenda y sesiones de congreso por mes,
 * semana y día. Lee GET /event/{id}/calendario de ApiRestEvent y arma la grilla
 * con App\Support\CalendarioGrilla.
 */
class CalendarioEventoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $calendarioResponse = $client->forward('GET', "/event/{$evento}/calendario");
        abort_if(!$calendarioResponse || !$calendarioResponse->json('success'), 502, 'No se pudo cargar el calendario.');

        $calendario = $calendarioResponse->json('calendario') ?? [];
        $inicio = $calendario['fecha_inicio'] ?? null;
        $fin = $calendario['fecha_fin'] ?? null;
        $bloques = $calendario['bloques'] ?? [];

        $vista = in_array($request->query('vista'), CalendarioGrilla::VISTAS, true) ? $request->query('vista') : 'mes';
        $ancla = $this->fechaAncla($request, $inicio);

        $grilla = match ($vista) {
            'semana' => CalendarioGrilla::semana($ancla, $bloques, $inicio, $fin),
            'dia' => CalendarioGrilla::dia($ancla, $bloques, $inicio, $fin),
            default => CalendarioGrilla::mes($ancla, $bloques, $inicio, $fin),
        };

        return view('eventos.calendario', [
            'evento' => $eventoData,
            'vista' => $vista,
            'ancla' => $ancla,
            'grilla' => $grilla,
            'navegacion' => CalendarioGrilla::navegacion($vista, $ancla),
            'totalBloques' => count($bloques),
        ]);
    }

    /**
     * Fecha en torno a la que se arma la vista: la pedida, o la de inicio del
     * evento, o hoy si el evento no tiene fecha.
     */
    private function fechaAncla(Request $request, ?string $fechaInicio): Carbon
    {
        $pedida = $request->query('fecha');
        if (is_string($pedida) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pedida)) {
            return Carbon::parse($pedida)->startOfDay();
        }

        return $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay() : Carbon::today();
    }
}
