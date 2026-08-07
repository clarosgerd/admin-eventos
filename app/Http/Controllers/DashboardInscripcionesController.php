<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\View\View;

/**
 * Dashboard de inscripciones dentro del panel — mismo conteo que ya se le
 * manda por correo al organizador vía link firmado (ver
 * ApiRestEvent\OrganizadorDashboardController /
 * App\Support\DashboardInscripcionesData), ahora también disponible
 * autenticado con el login normal del panel.
 */
class DashboardInscripcionesController extends Controller
{
    public function show(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $response = $client->forward('GET', "/event/{$evento}/dashboard-inscripciones");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el dashboard de inscripciones.');

        return view('eventos.dashboard-inscripciones', [
            'evento' => $eventoData,
            'totalGeneral' => $response->json('totalGeneral'),
            'porCategoria' => $response->json('porCategoria'),
            'porFormulario' => $response->json('porFormulario'),
            'nombresFormTypes' => $response->json('nombresFormTypes'),
            'estados' => $response->json('estados'),
        ]);
    }

    /**
     * Mismo criterio que EventoController::assertCanViewEvento /
     * NumeracionController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
