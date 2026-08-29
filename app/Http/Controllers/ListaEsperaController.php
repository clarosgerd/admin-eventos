<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\View\View;

/**
 * Lista de espera de un evento — de solo lectura en esta ronda, la
 * promoción es automática (ver PromoverListaEsperaAction en
 * ApiRestEvent). Mismo criterio de permisos que Numeración/Presupuesto:
 * super_admin o el admin scoped a su propio evento. Ver
 * ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md.
 */
class ListaEsperaController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/lista-espera");
        $lista = $response?->json('lista_espera') ?? [];

        $nombresFormTypes = collect($eventoData['formTypes'] ?? [])->pluck('name', 'id');

        return view('eventos.lista-espera', [
            'evento' => $eventoData,
            'lista' => $lista,
            'nombresFormTypes' => $nombresFormTypes,
        ]);
    }

}
