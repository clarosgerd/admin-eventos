<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
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

    public function csvDownload(int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $categoriasPorId = collect($eventoData['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']);

        // País del evento (18/09/2026) — no existe nacionalidad de
        // participante en el modelo, se hardcodea el país del evento para
        // todos los inscritos (ver EventoResource::pais).
        $paisIso2 = $eventoData['pais']['iso2'] ?? '';
        $paisNombre = $eventoData['pais']['nombre'] ?? '';

        $response = $client->forward('GET', "/event/{$evento}/participantes");
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $participantes = $response->json('participantes') ?? [];

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
                // CATEGORIA — MVP: nombre de categoría propio. El ideal a
                // futuro (pedido por el usuario, no implementado ahora) es
                // recategorizar por NumeracionRango (categoría+edad+género).
                $categoriaNombre,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'chronotrack-evento-'.$evento.'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
