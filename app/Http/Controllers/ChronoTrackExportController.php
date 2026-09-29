<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Exportación de inscritos al formato de carga de ChronoTrack (18/09/2026)
 * — reporte que el organizador sube manualmente a la plataforma de
 * ChronoTrack para habilitar a los participantes a cronometraje. No existía
 * (solo había integración de LECTURA de resultados ya cronometrados, ver
 * ChronoTrackClient::entriesDeCarrera(), dirección opuesta). Contrato de
 * columnas y orden calcado de un CSV real ya usado con éxito por el
 * organizador (ver memoria del proyecto, análisis del 18/09/2026) — no
 * inventado, replica también sus particularidades (columnas siempre vacías,
 * COUNTRY_NAME/COUNTRY_CODE con el mismo valor).
 *
 * Mismo patrón que NumeracionController::csvDownload() /
 * ParticipantesDetalleController::csvDownload(): el CSV se arma acá, no en
 * ApiRestEvent, a partir de los 2 JSON que ya existen.
 */
class ChronoTrackExportController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Filtros del reporte (26/09/2026). Antes bajaba a TODOS los participantes, con o
     * sin número de corredor/chip y de cualquier estado de pago. Ahora solo
     * inscripciones PAGADAS y, según el filtro: con numeración (predeterminado), con
     * chip, o todas las pagadas.
     */
    private const FILTROS = [
        'con_numeracion' => 'con numeración',
        'con_chip'       => 'con chip',
        'todos'          => 'todos los pagados',
    ];

    public function csvDownload(Request $request, int $evento, ApiRestEventClient $client): Response|RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $filtro = (string) $request->query('filtro', 'con_numeracion');
        abort_unless(array_key_exists($filtro, self::FILTROS), 422, 'Filtro no válido.');

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $categoriasPorId = collect($eventoData['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']);

        // País del evento (18/09/2026) — no existe nacionalidad de
        // participante en el modelo, se hardcodea el país del evento para
        // todos los inscritos (ver EventoResource::pais).
        $paisIso2 = $eventoData['pais']['iso2'] ?? '';
        $paisNombre = $eventoData['pais']['nombre'] ?? '';

        // Solo inscripciones pagadas: una pendiente, cancelada o fallida no debe cronometrarse
        // ni llevar número (26/09/2026). El filtro por numeración/chip se aplica abajo.
        $response = $client->forward('GET', "/event/{$evento}/participantes", query: ['pago_status' => 'paid']);
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $participantes = collect($response->json('participantes') ?? [])
            ->filter(fn ($p) => match ($filtro) {
                'con_numeracion' => trim((string) ($p['numeroCorredor'] ?? '')) !== '',
                'con_chip'       => trim((string) ($p['chip'] ?? '')) !== '',
                default          => true,
            })
            ->values()
            ->all();

        // Sin coincidencias: no se baja un CSV con solo el encabezado (parece un archivo válido
        // y vacío); se vuelve al evento con un mensaje claro.
        if ($participantes === []) {
            return redirect()->route('eventos.edit', $evento)->withErrors([
                'general' => 'No hay participantes pagados ' . self::FILTROS[$filtro] . ' para exportar a ChronoTrack.'
                    . ($filtro === 'todos' ? '' : ' Asigna la numeración/chip primero o usa la opción "todos".'),
            ]);
        }

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'EXTERNAL_ID', 'TYPE', 'REG_CHOICE', 'RACE_NAME', 'BIB', 'TAG', 'BRACKET',
            'FIRST_NAME', 'LAST_NAME', 'GENDER', 'RACE_AGE', 'REG_AGE', 'DOB', 'CITY',
            'COUNTRY_NAME', 'COUNTRY_CODE', 'SMScountycode1', 'SMSlanguage1', 'SMSnumber1', 'CATEGORIA',
        ]);
        foreach ($participantes as $p) {
            $categoriaNombre = $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'];
            $dob = $p['fechaNacimiento'] ? Carbon::parse($p['fechaNacimiento'])->format('d-m-Y') : '';

            fputcsv($handle, [
                $p['id'], 'IND', $categoriaNombre, $categoriaNombre,
                $p['numeroCorredor'], $p['chip'], $p['genero'],
                $p['nombre'], $p['apellido'], $p['genero'], '', '', $dob, $p['ciudad'],
                // COUNTRY_NAME/COUNTRY_CODE: mismo valor (iso2) — así viene
                // en la muestra real pese al nombre de columna.
                $paisIso2, $paisIso2, $paisNombre, 'Spanish', $p['telefono'],
                // CATEGORIA — recategorización visual por edad/género
                // (23/09/2026): si el evento tiene NumeracionRango cargado
                // y matchea para este participante, usa esa categoría real
                // en vez de la que eligió al inscribirse (REG_CHOICE/
                // RACE_NAME sí siguen siendo la elegida — es "lo que se
                // inscribió", distinto de "el bracket real de scoring").
                // Calculado en ApiRestEvent (ParticipanteController::porEvento),
                // no acá — necesita edad/calculo_edad_id que no vienen en
                // este payload.
                $p['categoriaRecalculada'] ?? $categoriaNombre,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'chronotrack-evento-'.$evento.'-'.str_replace('_', '-', $filtro).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
