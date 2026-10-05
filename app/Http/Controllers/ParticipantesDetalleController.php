<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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
    use AuthorizesEventoScope;

    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        [$categoria, $pagoStatus, $perPage, $page, $search] = $this->filtrosDesde($request);

        $response = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'per_page' => $perPage,
            'page' => $page,
            'search' => $search !== '' ? $search : null,
        ]));

        abort_if(!$response || !$response->json('success'), 502, 'No se pudo cargar el detalle de inscritos.');

        $participantes = collect($response->json('participantes') ?? [])
            ->map(fn (array $p) => $p + ['poleraTalla' => $this->tallaPolera($p['polera'] ?? null)])
            ->all();

        return view('eventos.participantes-detalle', [
            'evento' => $eventoData,
            // Carrera vs congreso: numeración y distancia solo aplican a carreras.
            'usaNumeracion' => $this->esCarrera($eventoData),
            // Equipo: solo carreras, y solo si algún formulario del evento tiene has_team.
            'mostrarEquipo' => $this->esCarrera($eventoData)
                && collect($participantes)->contains(fn ($p) => ($p['eventoConEquipo'] ?? false) === true),
            // Talla de polera: solo carreras con souvenir de polera en el evento.
            'mostrarPolera' => $this->esCarrera($eventoData)
                && collect($participantes)->contains(fn ($p) => ($p['eventoConPolera'] ?? false) === true),
            'categoriaSeleccionada' => $categoria,
            'pagoStatusSeleccionado' => $pagoStatus,
            'searchSeleccionado' => $search,
            'participantes' => $participantes,
            'meta' => $response->json('meta'),
        ]);
    }

    /**
     * Conciliación manual de "Pago pendiente (USD)" (24/08/2026) — el admin
     * confirma desde esta misma pantalla que el participante pagó por el
     * link enviado por correo. Reenvía a
     * RegistrationController::confirmarPagoManual() en ApiRestEvent, que
     * revalida tipo_pago/pago_status server-side (no confía en que el botón
     * solo se muestre para las filas correctas).
     */
    public function confirmarPagoManual(Request $request, int $evento, string $referencia, ApiRestEventClient $client): \Illuminate\Http\RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PATCH', "/registrations/{$referencia}/confirmar-pago-manual");

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return back()->with('status', "Pago confirmado — referencia {$referencia}.");
    }

    public function csvDownload(Request $request, int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        [$categoria, $pagoStatus, , , $search] = $this->filtrosDesde($request);

        // Igual que NumeracionController::csvDownload — `categoria` viaja
        // como ID, se resuelve el nombre acá solo para que la columna del
        // CSV sea legible.
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $categoriasPorId = collect($eventoResponse?->json('eventos.categories') ?? [])->keyBy(fn ($c) => (string) $c['id']);
        // 3 edades para recategorización + carga a ChronoTrack (28/08/2026)
        // — pedido del usuario. ChronoTrack y las federaciones deportivas
        // categorizan por edad de 3 formas distintas, todas legítimas según
        // el evento: a la fecha del evento, a fin de año (la más usada para
        // "edad que cumple en el año"), y la edad actual/de hoy. En vez de
        // construir el selector `calculo_edad_id` (columna que existe en
        // `categories` desde julio pero nunca se implementó — decisión
        // explícita del usuario de no hacerlo ahora), se calculan las 3 acá
        // mismo para que el staff decida a mano cuál aplica.
        $fechaEvento = $eventoResponse?->json('eventos.date');
        $finDeAnioEvento = $fechaEvento ? Carbon::parse($fechaEvento)->endOfYear() : null;

        // Campos de carrera/congreso en reportes al cliente (18/09/2026) —
        // 'numero_corredor' solo tiene sentido si el evento usa numeración,
        // mismo criterio (por tipo de evento) que ya usa ApiRestEvent
        // (OrganizadorDashboardController::exportCsv()) para la misma
        // columna en el CSV firmado — ver análisis en la memoria del
        // proyecto (project_reportes_csv_campos_carrera_congreso).
        $usaNumeracion = $this->esCarrera($eventoResponse?->json('eventos') ?? []);

        // Sin `per_page` a propósito: la descarga CSV es una acción
        // explícita del usuario, no la carga de pantalla por defecto —
        // mismo criterio que ya usa la exportación de Numeración.
        $response = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'search' => $search !== '' ? $search : null,
        ]));
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $participantes = $response->json('participantes') ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        // importe_taller / importe_total (19/08/2026) — `importe` (subtotal)
        // nunca incluyó el importe de talleres, así que no servía para
        // conciliar contra el depósito real del banco. `importe_total` sí
        // es lo comparable (importe + importe_taller); no incluye el cargo
        // de servicio, que se cobra por registro completo, no por
        // participante — ver ApiRestEvent ParticipanteController::porEvento.
        // Mismo formato que los reportes legacy (xlsx de referencia, 04/10/2026):
        // encabezados en MAYÚSCULAS, N° correlativo, FORMA DE PAGO y
        // OBSERVACIONES, y al final una columna por pregunta "En reporte".
        // Carrera: IMPORTE sin polera + IMPORTE_POLERA aparte; DISTANCIA =
        // categoría del participante, CATEGORIA = grupo de edad recalculado.
        // Congreso: IMPORTE_TALLER y DEN. (título = alias) antes de NOMBRE.
        // IMPORTE_TOTAL no cambia en ningún caso (sigue = importe + polera + taller).
        $preguntas = collect($participantes[0]['respuestas'] ?? [])->values();

        // Equipo: solo en carreras, y solo si algún formulario del evento tiene el flag has_team.
        $mostrarEquipo = $usaNumeracion && collect($participantes)->contains(fn ($p) => ($p['eventoConEquipo'] ?? false) === true);
        // Talla de polera: solo en carreras con souvenir de polera en el evento.
        $mostrarPolera = $usaNumeracion && collect($participantes)->contains(fn ($p) => ($p['eventoConPolera'] ?? false) === true);

        fputcsv($handle, [
            'N°',
            ...($usaNumeracion ? ['NUMERO_CORREDOR'] : []),
            'ESTADO',
            'IMPORTE',
            ...($usaNumeracion ? ['IMPORTE_POLERA'] : ['IMPORTE_TALLER']),
            'IMPORTE_TOTAL',
            'PROMO_CODIGO', 'PROMO_DESCUENTO',
            'NUMERO_DOCUMENTO',
            ...($usaNumeracion ? [] : ['DEN.']),
            'NOMBRE', 'APELLIDO',
            ...($usaNumeracion ? ['ALIAS'] : []),
            ...($mostrarEquipo ? ['EQUIPO'] : []),
            ...($mostrarPolera ? ['POLERA'] : []),
            'SEXO', 'CELULAR', 'FECHA_INSCRIPCION', 'REFERENCIA', 'NACIMIENTO',
            ...($usaNumeracion ? ['DISTANCIA', 'CATEGORIA'] : ['CATEGORIA']),
            'EDAD_FECHA', 'EDAD_FIN_DE_ANIO', 'EDAD_HOY',
            'FORMA DE PAGO', 'OBSERVACIONES',
            ...$preguntas->map(fn ($r) => mb_strtoupper($r['etiqueta']))->all(),
        ]);
        foreach ($participantes as $i => $p) {
            [$edadEvento, $edadFinDeAnio, $edadHoy] = $this->edades($p['fechaNacimiento'] ?? null, $fechaEvento, $finDeAnioEvento);
            $importePolera = (float) ($p['importePolera'] ?? 0);
            $importeCarrera = $usaNumeracion ? round((float) $p['importe'] - $importePolera, 2) : $p['importe'];
            $distancia = $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'];

            fputcsv($handle, [
                $i + 1,
                ...($usaNumeracion ? [$p['numeroCorredor']] : []),
                $this->estadoLabel($p['pagoStatus']),
                $importeCarrera,
                ...($usaNumeracion ? [$importePolera] : [$p['importeTaller'] ?? 0]),
                $p['importeTotal'] ?? $p['importe'],
                $p['promoCodigo'] ?? '', $p['promoDescuento'] ?? 0,
                $p['numeroDocumento'],
                ...($usaNumeracion ? [] : [$p['alias'] ?? '']),
                $p['nombre'], $p['apellido'],
                ...($usaNumeracion ? [$p['alias'] ?? ''] : []),
                ...($mostrarEquipo ? [$p['equipo'] ?? ''] : []),
                ...($mostrarPolera ? [$this->tallaPolera($p['polera'] ?? null)] : []),
                $p['genero'], $p['telefono'],
                $p['fechaInscripcion'], $p['referencia'], $p['fechaNacimiento'],
                ...($usaNumeracion ? [$distancia, $p['categoriaRecalculada'] ?? ''] : [$distancia]),
                $edadEvento, $edadFinDeAnio, $edadHoy,
                $this->formaPagoLabel($p['tipoPago'] ?? null),
                '',
                ...collect($p['respuestas'] ?? [])->pluck('valor')->all(),
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
        // Buscador (07/09/2026, pedido del usuario: buscar desde "Pagados"
        // en el dashboard por documento/nombre/apellido/correo) — filtra
        // server-side en ApiRestEvent (ParticipanteController::porEvento),
        // no client-side, porque esta pantalla pagina (a diferencia del
        // buscador de eventos.participantes, que sí es client-side porque
        // esa pantalla trae todo sin paginar — ver project_buscador_participantes_evento_admin).
        $search = trim((string) $request->query('search', ''));

        return [$categoria, $pagoStatus, $perPage, $page, $search];
    }

    /**
     * Talla de polera para el reporte. El API devuelve el centinela legacy
     * 'No shirt' cuando el participante no eligió polera: se muestra vacío.
     */
    private function tallaPolera(?string $talla): string
    {
        $limpia = trim((string) $talla);

        return strcasecmp($limpia, 'No shirt') === 0 ? '' : $limpia;
    }

    /**
     * Carrera = cualquier tipo de evento distinto de "Congreso / No aplica"
     * (mismo criterio que ApiRestEvent OrganizadorDashboardController).
     */
    private function esCarrera(array $evento): bool
    {
        return mb_strtolower(trim((string) ($evento['tipoEvento'] ?? ''))) !== 'congreso / no aplica';
    }

    /**
     * FORMA DE PAGO con las etiquetas del reporte legacy. `tipo_pago` guarda
     * el origen del cobro (pasarela, Caja o sincronización externa); los
     * valores desconocidos salen tal cual en mayúsculas, sin perder el dato.
     */
    private function formaPagoLabel(?string $tipoPago): string
    {
        $clave = mb_strtolower(trim((string) $tipoPago));

        return match ($clave) {
            '' => '',
            'sip' => 'QR SIP',
            'qr' => 'QR',
            'multipago' => 'QR MULTIPAGO',
            'efectivo' => 'EFECTIVO',
            'organizador' => 'DIRECTO ORG.',
            'cortesía', 'cortesia' => 'CORTESIA',
            'depósito', 'deposito' => 'DEPOSITO',
            'pendiente' => 'PENDIENTE',
            'pendiente_usd' => 'PENDIENTE USD',
            'gratis' => 'GRATIS',
            'externo' => 'EXTERNO',
            'legado' => 'LEGADO',
            'excel' => 'EXCEL',
            default => mb_strtoupper($clave),
        };
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
     * Las 3 edades del reporte (28/08/2026) — ver comentario en
     * csvDownload(). Devuelve '' cuando falta el dato de origen (fecha de
     * nacimiento ausente, o evento sin fecha cargada) en vez de un 0
     * engañoso — mejor una celda vacía en el CSV que una edad falsa.
     *
     * @return array{0: int|string, 1: int|string, 2: int|string}
     */
    private function edades(?string $fechaNacimiento, ?string $fechaEvento, ?Carbon $finDeAnioEvento): array
    {
        if (!$fechaNacimiento) {
            return ['', '', ''];
        }

        $nacimiento = Carbon::parse($fechaNacimiento);

        // (int), no el float que devuelve diffInYears() en Carbon 3.x por
        // default (ej. 53.69 en vez de 53) — trunca hacia el año cumplido,
        // que es lo que significa "edad" acá.
        $edadEvento = $fechaEvento ? (int) $nacimiento->diffInYears(Carbon::parse($fechaEvento)) : '';
        $edadFinDeAnio = $finDeAnioEvento ? (int) $nacimiento->diffInYears($finDeAnioEvento) : '';
        $edadHoy = (int) $nacimiento->diffInYears(Carbon::today());

        return [$edadEvento, $edadFinDeAnio, $edadHoy];
    }
}
