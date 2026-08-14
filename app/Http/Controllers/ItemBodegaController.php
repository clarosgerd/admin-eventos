<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bodega de stock por evento — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * Mismo criterio de permisos que Lista de espera/Numeración/Presupuesto:
 * super_admin o el admin scoped a su propio evento.
 */
class ItemBodegaController extends Controller
{
    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/item-bodega");
        $itemBodega = $response?->json('item_bodega') ?? [];

        return view('eventos.bodega', [
            'evento'     => $eventoData,
            'itemBodega' => $itemBodega,
            'formTypes'  => $eventoData['formTypes'] ?? [],
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/item-bodega", body: $request->only(
            'nombre', 'icon', 'foto_url', 'requiere_talla', 'requiere_sexo'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect(route('bodega.index', $evento))->with('status', 'Ítem de bodega creado correctamente.');
    }

    public function update(Request $request, int $evento, int $item_bodega, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/item-bodega/{$item_bodega}", body: $request->only(
            'nombre', 'icon', 'foto_url', 'requiere_talla', 'requiere_sexo'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect(route('bodega.index', $evento))->with('status', 'Ítem de bodega actualizado correctamente.');
    }

    public function destroy(int $evento, int $item_bodega, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/item-bodega/{$item_bodega}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect(route('bodega.index', $evento))->with('status', 'Ítem de bodega eliminado correctamente.');
    }

    /**
     * Crea la asignación (Souvenir) a un form_type y redirige a la
     * pestaña "Tipos" del evento — mismo destino que crear un ítem del
     * kit suelto (ver SouvenirController), porque ahí es donde vive el
     * precio/incluido de la asignación; el "Gestionar stock →" de esa
     * fila lleva al cupo propio, independiente de cualquier otra
     * asignación del mismo ítem.
     */
    public function asignar(Request $request, int $evento, int $item_bodega, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/item-bodega/{$item_bodega}/asignar", body: $request->only('form_types_id'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#tipos')
            ->with('status', 'Ítem asignado — cargá su precio, si viene incluido, y su stock propio desde ahí.');
    }

    /**
     * Mismo criterio que ListaEsperaController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
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

        return ['general' => $response->json('error') ?? 'Ocurrió un error.'];
    }
}
