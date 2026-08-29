<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presupuesto de un evento (control financiero del organizador) — llama a
 * /event/{evento}/presupuesto(/{presupuesto}) de ApiRestEvent, no
 * reimplementa el cálculo del balance acá (ver BalanceEventoData del lado
 * API, ya incluido en la respuesta del dashboard de inscripciones). A
 * diferencia de SocioController/LiquidacionController (solo super_admin),
 * el admin scoped a su propio evento también puede operar — mismo
 * criterio que ParticipantesController/NumeracionController. Ver
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class PresupuestoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $movimientosResponse = $client->forward('GET', "/event/{$evento}/presupuesto");
        $movimientos = $movimientosResponse?->json('data') ?? [];

        $categoriasResponse = $client->forward('GET', '/presupuesto-categorias');
        // array_values(): array_filter no reindexa las claves, y la vista
        // asume $categorias[0] para el <option> preseleccionado.
        $categorias = array_values(array_filter($categoriasResponse?->json('data') ?? [], fn ($c) => $c['activo']));

        $balanceResponse = $client->forward('GET', "/event/{$evento}/dashboard-inscripciones");
        $balance = $balanceResponse?->json('balance');

        return view('eventos.presupuesto', [
            'evento' => $eventoData,
            'movimientos' => $movimientos,
            'categorias' => $categorias,
            'balance' => $balance,
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/presupuesto", body: $request->only(
            'presupuesto_categoria_id', 'tipo', 'monto', 'moneda', 'fecha', 'comprobante_url'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('presupuesto.index', $evento)->with('status', 'Movimiento registrado correctamente.');
    }

    public function update(Request $request, int $evento, int $presupuesto, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/event/{$evento}/presupuesto/{$presupuesto}", body: $request->only(
            'presupuesto_categoria_id', 'tipo', 'monto', 'moneda', 'fecha', 'comprobante_url'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('presupuesto.index', $evento)->with('status', 'Movimiento actualizado correctamente.');
    }

    public function destroy(int $evento, int $presupuesto, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/event/{$evento}/presupuesto/{$presupuesto}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el movimiento.']);
        }

        return redirect()->route('presupuesto.index', $evento)->with('status', 'Movimiento eliminado correctamente.');
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
