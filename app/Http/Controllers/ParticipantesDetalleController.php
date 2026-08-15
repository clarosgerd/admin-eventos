<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reporte detallado de inscritos (15/08/2026) — pantalla de solo lectura a
 * la que se llega desde las tarjetas de totales del Dashboard de
 * inscripciones (eventos.dashboard), filtrable por estado de pago. Pedido
 * por el usuario a partir de un reporte de un sistema legado (fila por
 * fila: número, estado, importe, CI, nombre, apellido, sexo, celular,
 * fecha de inscripción, referencia, nacimiento, distancia).
 *
 * A propósito NO reutiliza ParticipantesController (esa pantalla es de
 * edición de contacto — otra UX/contrato) ni la ruta `participantes.index`.
 * Consume el mismo `GET /event/{evento}/participantes` que
 * NumeracionController/ParticipantesController ya usan
 * (ParticipanteController::porEvento en ApiRestEvent), extendido ese mismo
 * día con `pago_status`, `importe`, `fechaInscripcion` y paginación
 * opt-in (`per_page`) — ver ApiRestEvent/CHANGELOG.md.
 */
class ParticipantesDetalleController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        [$categoria, $pagoStatus, $perPage, $page] = $this->filtrosDesde($request);

        $response = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el detalle de inscritos.');

        return view('eventos.participantes-detalle', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'pagoStatusSeleccionado' => $pagoStatus,
            'participantes' => $response->json('participantes') ?? [],
            'meta' => $response->json('meta'),
        ]);
    }

    public function csvDownload(Request $request, int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        [$categoria, $pagoStatus] = $this->filtrosDesde($request);

        // Igual que NumeracionController::csvDownload — `categoria` viaja
        // como ID, se resuelve el nombre acá solo para que la columna del
        // CSV sea legible.
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $categoriasPorId = collect($eventoResponse?->json('eventos.categories') ?? [])->keyBy(fn ($c) => (string) $c['id']);

        // Sin `per_page` a propósito: la descarga CSV es una acción
        // explícita del usuario, no la carga de pantalla por defecto —
        // mismo criterio que ya usa la exportación de Numeración.
        $response = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
        ]));
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $participantes = $response->json('participantes') ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'numero_corredor', 'estado', 'importe', 'numero_documento', 'nombre', 'apellido',
            'sexo', 'celular', 'fecha_inscripcion', 'referencia', 'nacimiento', 'distancia',
        ]);
        foreach ($participantes as $p) {
            fputcsv($handle, [
                $p['numeroCorredor'], $this->estadoLabel($p['pagoStatus']), $p['importe'],
                $p['numeroDocumento'], $p['nombre'], $p['apellido'], $p['genero'], $p['telefono'],
                $p['fechaInscripcion'], $p['referencia'], $p['fechaNacimiento'],
                $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'detalle-inscritos-evento-'.$evento.($pagoStatus !== '' ? '-'.$pagoStatus : '').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtrosDesde(Request $request): array
    {
        $categoria = $request->query('categoria', '');
        $pagoStatus = $request->query('pago_status', '');
        $perPage = min((int) $request->query('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);
        $page = max((int) $request->query('page', 1), 1);

        return [$categoria, $pagoStatus, $perPage, $page];
    }

    private function estadoLabel(string $pagoStatus): string
    {
        return match ($pagoStatus) {
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelado',
            'failed' => 'Fallido',
            default => $pagoStatus,
        };
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
