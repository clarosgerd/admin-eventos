<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Caja de cobro presencial — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
 * Proxy puro: toda la validación/cálculo real vive en ApiRestEvent
 * (CajaController/CajaTurnoController), esto solo arma las pantallas y
 * reenvía. Accesible por admin/cajero/super_admin — el scoping real lo
 * valida ApiRestEvent (assertCanOperarCaja()).
 */
class CajaController extends Controller
{
    public function index(int $evento, ApiRestEventClient $client): View
    {
        $eventoData = $this->fetchEvento($evento, $client);
        $turno = $client->forward('GET', "/event/{$evento}/caja/turno-actual")?->json('turno');

        return view('caja.index', ['evento' => $eventoData, 'turno' => $turno]);
    }

    public function abrirTurno(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/event/{$evento}/caja/turno/abrir", body: [
            'fondo_inicial' => $request->input('fondo_inicial', 0),
        ]);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('caja.index', $evento)->with('status', 'Turno de caja abierto.');
    }

    public function cerrarTurno(Request $request, int $evento, int $turno, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/caja/turno/{$turno}/cerrar", body: [
            'monto_contado' => $request->input('monto_contado', 0),
            'notas'         => $request->input('notas'),
        ]);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        $t = $response->json('turno');
        $status = sprintf(
            'Turno cerrado. Esperado: %s — Contado: %s — Diferencia: %s',
            number_format((float) $t['montoEsperado'], 2),
            number_format((float) $t['montoContado'], 2),
            number_format((float) $t['diferencia'], 2)
        );

        return redirect()->route('caja.index', $evento)->with('status', $status);
    }

    public function nueva(int $evento, ApiRestEventClient $client): View
    {
        $eventoData = $this->fetchEvento($evento, $client);

        return view('caja.nueva', ['evento' => $eventoData]);
    }

    public function storeNueva(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', "/event/{$evento}/caja/inscripcion", body: [
            'form_types_id' => $request->input('form_types_id'),
            'participante'  => json_decode((string) $request->input('participante_json'), true) ?? [],
            'totales'       => json_decode((string) $request->input('totales_json'), true) ?? [],
        ]);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        $referencia = $response->json('data.referencia');

        // Comprobante imprimible (20/08/2026) — el cajero necesita algo
        // físico para entregar; lo mandamos directo al comprobante en vez
        // de a Caja > índice, que era un punto muerto sin acción posible.
        return redirect()->route('caja.eticket', [$evento, $referencia])
            ->with('status', "Inscripción {$referencia} registrada y cobrada correctamente.");
    }

    public function buscarPage(int $evento, ApiRestEventClient $client): View
    {
        $eventoData = $this->fetchEvento($evento, $client);

        return view('caja.buscar', ['evento' => $eventoData]);
    }

    /**
     * Búsqueda en vivo — llamada por fetch() desde caja/buscar.blade.php.
     */
    public function buscar(Request $request, int $evento, ApiRestEventClient $client): JsonResponse
    {
        $response = $client->forward('GET', "/event/{$evento}/caja/buscar", query: [
            'q' => $request->input('q', ''),
        ]);

        return response()->json($response?->json() ?? ['success' => false, 'error' => 'No se pudo conectar con el servidor.']);
    }

    /**
     * Prellenado desde `personas` (20/08/2026) — búsqueda en vivo llamada
     * desde caja/_formulario.blade.php al tipear el N° documento en alta
     * nueva. Proxy puro, mismo patrón que buscar().
     */
    public function buscarPersona(Request $request, int $evento, ApiRestEventClient $client): JsonResponse
    {
        $response = $client->forward('GET', "/event/{$evento}/caja/persona", query: [
            'numero_documento' => $request->input('numero_documento', ''),
        ]);

        return response()->json($response?->json() ?? ['success' => false, 'error' => 'No se pudo conectar con el servidor.']);
    }

    public function cobrarPendiente(int $evento, string $referencia, ApiRestEventClient $client): JsonResponse
    {
        $response = $client->forward('POST', "/registrations/{$referencia}/caja/cobrar-pendiente");

        return response()->json($response?->json() ?? ['success' => false, 'error' => 'No se pudo conectar con el servidor.'], $response?->status() ?? 502);
    }

    public function editar(int $evento, string $referencia, ApiRestEventClient $client): View
    {
        $eventoData = $this->fetchEvento($evento, $client);
        $registro = $client->forward('GET', "/registrations/{$referencia}")?->json('data');

        return view('caja.editar', ['evento' => $eventoData, 'registro' => $registro, 'referencia' => $referencia]);
    }

    /**
     * Comprobante imprimible (20/08/2026) — el cajero lo necesita en mano
     * después de cobrar (o para reimprimir uno viejo desde "Buscar").
     * Mismo detalle que editar() (GET /registrations/{reference}), acá
     * solo se muestra, no se edita nada.
     */
    public function eticket(int $evento, string $referencia, ApiRestEventClient $client): View
    {
        $eventoData = $this->fetchEvento($evento, $client);
        $registro = $client->forward('GET', "/registrations/{$referencia}")?->json('data');

        abort_if(!$registro, 404, 'Inscripción no encontrada.');

        return view('caja.eticket', ['evento' => $eventoData, 'registro' => $registro, 'referencia' => $referencia]);
    }

    public function storeEditar(Request $request, int $evento, string $referencia, ApiRestEventClient $client): RedirectResponse
    {
        $esPagada = $request->input('pago_status') === 'paid';
        $path = $esPagada
            ? "/registrations/{$referencia}/caja/editar-pagada"
            : "/registrations/{$referencia}/caja/editar-pendiente";

        $body = [
            'participantes' => [json_decode((string) $request->input('participante_json'), true) ?? []],
            'totales'       => json_decode((string) $request->input('totales_json'), true) ?? [],
        ];
        if ($esPagada) {
            $body['confirmacion'] = true;
        }

        $response = $client->forward('PATCH', $path, body: $body);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        $status = 'Inscripción actualizada correctamente.';
        // != 0 en vez de > 0 (25/08/2026) — un cambio a categoría más
        // barata da un costo_adicion negativo (devolución al participante,
        // ver ApiRestEvent CajaController::editarPagada()), que antes no
        // se reflejaba en este mensaje.
        $costoAdicion = (float) $response->json('costo_adicion');
        if ($esPagada && abs($costoAdicion) > 0.001) {
            $status .= $costoAdicion > 0
                ? ' Adicional cobrado: ' . number_format($costoAdicion, 2) . '.'
                : ' Devolución al participante: ' . number_format(abs($costoAdicion), 2) . '.';
        }

        return redirect()->route('caja.index', $evento)->with('status', $status);
    }

    public function cierres(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/event/{$evento}/caja/turnos", query: array_filter([
            'admin_user_id' => $request->input('cajero'),
            'desde'         => $request->input('desde'),
            'hasta'         => $request->input('hasta'),
        ]));

        // Detalle de cierre de caja (27/08/2026) — el filtro de cajero
        // necesita la lista de cajeros que tuvieron turnos en este evento
        // (no existe un endpoint de "admins del evento" reusable acá). Se
        // deriva de una consulta SIN el filtro de cajero (respetando
        // fecha, si hay), no de la lista ya filtrada — si no, elegir un
        // cajero haría desaparecer del <select> a los demás.
        $todosLosTurnos = $request->filled('cajero')
            ? ($client->forward('GET', "/event/{$evento}/caja/turnos", query: array_filter([
                'desde' => $request->input('desde'),
                'hasta' => $request->input('hasta'),
              ]))?->json('turnos') ?? [])
            : ($response?->json('turnos') ?? []);
        $cajeros = collect($todosLosTurnos)
            ->unique('cajeroId')
            ->map(fn ($t) => ['id' => $t['cajeroId'], 'nombre' => $t['cajeroNombre'] ?? ('#'.$t['cajeroId'])])
            ->sortBy('nombre')
            ->values();

        return view('caja.cierres', [
            'evento' => $this->fetchEvento($evento, $client),
            'turnos' => $response?->json('turnos') ?? [],
            'cajeros' => $cajeros,
            'error'  => (!$response || !$response->json('success')) ? ($response?->json('error') ?? 'No se pudo conectar con el servidor.') : null,
        ]);
    }

    /**
     * Detalle de un turno (27/08/2026) — drill-down pedido por el usuario
     * desde el reporte de cierres, mismo patrón que editar()/eticket().
     */
    public function cierreDetalle(int $evento, int $turno, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/event/{$evento}/caja/turnos/{$turno}");

        abort_if(!$response || !$response->json('success'), $response?->status() ?? 502, $response?->json('error') ?? 'No se pudo cargar el detalle del turno.');

        return view('caja.cierre-detalle', [
            'evento' => $this->fetchEvento($evento, $client),
            'turno'  => $response->json('turno'),
        ]);
    }

    private function fetchEvento(int $evento, ApiRestEventClient $client): array
    {
        $response = $client->forward('GET', "/event/{$evento}");

        return $response?->json('eventos') ?? [];
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
