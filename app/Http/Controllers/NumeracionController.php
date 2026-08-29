<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Numeración de corredor y chip por evento — edición manual fila por fila
 * y carga masiva por CSV (descargar la lista actual, completar las
 * columnas numero_corredor/chip, volver a subir el mismo archivo).
 *
 * Del lado de ApiRestEvent, GET /event/{evento}/participantes,
 * PATCH .../numeracion y POST .../numeracion/bulk viven ahora detrás del
 * guard `admins` con el mismo scoping que el resto del panel (antes no
 * tenían auth — ver brain/PLAN-NUMERACION-PANEL-05082026.md); acá solo
 * se agrega la UX (guarda de vista + armado/parseo del CSV), la
 * autorización real la hace la API.
 */
class NumeracionController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request, int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $eventoData = $eventoResponse?->json('eventos');
        abort_if(!$eventoData, 404);

        $categoria = $request->query('categoria', '');

        $participantesResponse = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
        ]));

        abort_if(!$participantesResponse || !$participantesResponse->json('success'), 502, 'No se pudo cargar la lista de participantes.');

        return view('eventos.numeracion', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'participantes' => $participantesResponse->json('participantes') ?? [],
        ]);
    }

    public function update(Request $request, string $referencia, int $participante, ApiRestEventClient $client): RedirectResponse
    {
        $evento = (int) $request->input('evento_id');
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PATCH', "/registrations/{$referencia}/participantes/{$participante}/numeracion", body: $request->only('numero_corredor', 'chip'));

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('numeracion.index', ['evento' => $evento, 'categoria' => $request->input('categoria') ?: null])
            ->with('status', 'Numeración actualizada correctamente.');
    }

    public function csvDownload(Request $request, int $evento, ApiRestEventClient $client): Response
    {
        $this->assertCanViewEvento($evento);

        $categoria = $request->query('categoria', '');

        // `categoria` viaja como ID (ver nota en NumeracionController::index)
        // — se resuelve el nombre acá solo para que la columna del CSV y el
        // nombre del archivo sean legibles, el filtro real ya se aplicó
        // arriba comparando por ID.
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $categoriasPorId = collect($eventoResponse?->json('eventos.categories') ?? [])->keyBy(fn ($c) => (string) $c['id']);

        $response = $client->forward('GET', "/event/{$evento}/participantes", query: array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
        ]));
        abort_if(!$response || !$response->json('success'), 502, 'No se pudo generar el archivo.');

        $participantes = $response->json('participantes') ?? [];

        $handle = fopen('php://temp', 'w+');
        // BOM UTF-8: sin esto Excel en Windows (el destino habitual de este
        // archivo) muestra mal tildes/ñ en nombre/categoría.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['numero_documento', 'nombre', 'apellido', 'categoria', 'numero_corredor', 'chip']);
        foreach ($participantes as $p) {
            fputcsv($handle, [
                $p['numeroDocumento'], $p['nombre'], $p['apellido'],
                $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'],
                $p['numeroCorredor'], $p['chip'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $categoriaLabel = $categoriasPorId[$categoria]['name'] ?? $categoria;
        $filename = 'numeracion-evento-'.$evento.($categoria !== '' ? '-'.\Illuminate\Support\Str::slug($categoriaLabel) : '').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function csvUpload(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $categoria = $request->input('categoria', '');
        $redirectBack = fn () => redirect()->route('numeracion.index', ['evento' => $evento, 'categoria' => $categoria ?: null]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (!$handle) {
            return $redirectBack()->withErrors(['general' => 'No se pudo leer el archivo.']);
        }

        // BOM opcional al inicio del archivo (Excel lo agrega al exportar/guardar).
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $redirectBack()->withErrors(['general' => 'El archivo está vacío.']);
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $idxDoc = array_search('numero_documento', $header, true);
        $idxCorredor = array_search('numero_corredor', $header, true);
        $idxChip = array_search('chip', $header, true);

        if ($idxDoc === false) {
            fclose($handle);
            return $redirectBack()->withErrors(['general' => 'El archivo no tiene la columna "numero_documento" — usá el CSV descargado desde acá, no lo reordenes.']);
        }

        $items = [];
        while (($row = fgetcsv($handle)) !== false) {
            $numeroDocumento = trim($row[$idxDoc] ?? '');
            if ($numeroDocumento === '') {
                continue;
            }
            $items[] = [
                'numero_documento' => $numeroDocumento,
                'numero_corredor' => $idxCorredor !== false ? trim((string) ($row[$idxCorredor] ?? '')) ?: null : null,
                'chip' => $idxChip !== false ? trim((string) ($row[$idxChip] ?? '')) ?: null : null,
            ];
        }
        fclose($handle);

        if (empty($items)) {
            return $redirectBack()->withErrors(['general' => 'El archivo no tiene filas con numero_documento.']);
        }

        $response = $client->forward('POST', "/event/{$evento}/participantes/numeracion/bulk", body: ['items' => $items]);

        if (!$response || !$response->json('success')) {
            return $redirectBack()->withErrors($this->extractErrors($response));
        }

        $actualizados = $response->json('actualizados');
        $noEncontrados = $response->json('no_encontrados') ?? [];

        $status = "Numeración actualizada: {$actualizados} participante(s).";
        if (!empty($noEncontrados)) {
            $status .= ' No se encontraron '.count($noEncontrados).' número(s) de documento: '.implode(', ', $noEncontrados).'.';
        }

        return $redirectBack()->with('status', $status);
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
