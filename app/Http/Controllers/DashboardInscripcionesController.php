<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\Response;
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
            'nombresCategorias' => $response->json('nombresCategorias'),
            'porFormulario' => $response->json('porFormulario'),
            'nombresFormTypes' => $response->json('nombresFormTypes'),
            'estados' => $response->json('estados'),
            // Balance del presupuesto — ver BalanceEventoData del lado
            // ApiRestEvent, sesión 11/08/2026.
            'balance' => $response->json('balance'),
            // Reporte de inscritos por modalidad/categoría + poleras — ver
            // App\Support\ReporteInscritosData del lado ApiRestEvent,
            // sesión 15/08/2026.
            'reporteInscritos' => $response->json('reporteInscritos'),
        ]);
    }

    /**
     * CSV del "Reporte de talleres" (20/08/2026) — pedido del usuario: un
     * reporte descargable para el organizador, sin agrupar (una fila por
     * cada selección de taller, no por sesión) y ordenado por fecha. Reusa
     * `reporteInscritos.porTaller.detalle` que ya viene armado y ordenado
     * desde ApiRestEvent (App\Support\ReporteInscritosData::detalleTalleres()) —
     * acá solo se vuelca a CSV, sin recalcular nada.
     */
    public function csvTalleres(int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}/dashboard-inscripciones");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $filas = $response->json('reporteInscritos.porTaller.detalle') ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'fecha', 'hora_inicio', 'hora_fin', 'sala', 'taller', 'sesion',
            'nombre', 'apellido', 'numero_documento', 'correo', 'telefono', 'referencia', 'precio',
        ]);
        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['fecha'], $fila['horaInicio'], $fila['horaFin'], $fila['sala'],
                $fila['tallerNombre'], $fila['sesionTitulo'],
                $fila['participanteNombre'], $fila['participanteApellido'], $fila['numeroDocumento'],
                $fila['correo'], $fila['telefono'], $fila['referencia'], $fila['precio'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte-talleres-evento-{$evento}.csv\"",
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
