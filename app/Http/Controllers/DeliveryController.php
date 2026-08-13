<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\View\View;

/**
 * Mapa de ubicación de delivery de un evento — de solo lectura, mismo
 * criterio de permisos que Numeración/Presupuesto/Lista de espera:
 * super_admin o el admin scoped a su propio evento. Muestra el pin que
 * cada participante marcó en elascenso/event (opcional — no todos van a
 * tener uno) además de la dirección de texto. Ver
 * elascenso/event/brain/DEPLOY-CHECKLIST-MAPA-DELIVERY-12082026.md.
 */
class DeliveryController extends Controller
{
    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/delivery");
        $participantes = $response?->json('participantes') ?? [];
        $resumen = $response?->json('resumen') ?? [];

        return view('eventos.delivery', [
            'evento' => $eventoData,
            'participantes' => $participantes,
            'resumen' => $resumen,
        ]);
    }

    /**
     * Mismo criterio que ListaEsperaController::assertCanViewEvento /
     * EventoController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
