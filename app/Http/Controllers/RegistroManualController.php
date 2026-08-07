<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Carga masiva de inscripciones por CSV — solo super_admin (ver
 * brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md). Evento + tipo de
 * formulario + categoría se eligen una sola vez para todo el archivo;
 * el CSV solo trae los datos del participante. Cada fila crea su
 * propia inscripción independiente en ApiRestEvent
 * (RegistrationController::importarBulk), con pago pendiente — no una
 * sola inscripción grupal para todo el lote.
 */
class RegistroManualController extends Controller
{
    private const COLUMNAS = [
        'numero_documento', 'tipo_documento', 'nombre', 'apellido', 'alias', 'genero',
        'fecha_nacimiento', 'email', 'direccion', 'ciudad', 'telefono',
        'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_relacion',
    ];

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/event/{$evento}");
        $eventoData = $response?->json('eventos');
        abort_if(!$eventoData, 404);

        return view('eventos.registro-manual', ['evento' => $eventoData]);
    }

    public function plantilla(): Response
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNAS);
        fputcsv($handle, [
            '1234567', 'DNI', 'Ana', 'Prueba', 'AnaP', 'Femenino',
            '1995-06-15', 'ana@example.com', 'Av. Siempre Viva 123', 'La Paz', '77712345',
            'Juan Prueba', '77798765', 'Padre',
        ]);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-registro-manual.csv"',
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $request->validate([
            'form_types_id' => ['required', 'integer'],
            'categoria' => ['required', 'string'],
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (!$handle) {
            return back()->withErrors(['general' => 'No se pudo leer el archivo.']);
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['general' => 'El archivo está vacío.']);
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $faltantes = array_diff(self::COLUMNAS, $header);
        if (!empty($faltantes)) {
            fclose($handle);
            return back()->withErrors(['general' => 'Al archivo le faltan columnas: ' . implode(', ', $faltantes) . '. Usá la plantilla descargable.']);
        }

        $indices = array_flip($header);
        $participantes = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila completamente vacía, se ignora
            }
            $item = [];
            foreach (self::COLUMNAS as $columna) {
                $item[$columna] = trim((string) ($row[$indices[$columna]] ?? ''));
            }
            $participantes[] = $item;
        }
        fclose($handle);

        if (empty($participantes)) {
            return back()->withErrors(['general' => 'El archivo no tiene filas con datos.']);
        }

        // Timeout generoso y sin reintentos: cada fila creada dispara un
        // correo real y síncrono del lado de ApiRestEvent (igual que el
        // registro online) — con el timeout/reintento por default de
        // ApiRestEventClient, un lote de varias filas puede "tardar
        // demasiado", el cliente lo da por caído y reintenta, y el
        // reintento reenvía el mismo CSV completo: todo lo que sí se
        // había creado en el primer intento vuelve como "ya existe".
        // Mejor esperar de más una sola vez que reintentar a ciegas.
        $response = $client->forward('POST', "/event/{$evento}/registro-manual/bulk", body: [
            'form_types_id' => (int) $request->input('form_types_id'),
            'categoria' => $request->input('categoria'),
            'participantes' => $participantes,
        ], timeoutSeconds: max(30, count($participantes) * 10), retries: 0);

        if (!$response || !$response->json('success')) {
            return back()->withErrors($this->extractErrors($response));
        }

        $creados = $response->json('creados') ?? [];
        $errores = $response->json('errores') ?? [];

        $status = count($creados) . ' inscripción(es) creada(s), pendiente(s) de pago.';
        if (!empty($errores)) {
            $status .= ' ' . count($errores) . ' fila(s) con error — ver detalle abajo.';
        }

        return redirect()->route('registro-manual.index', $evento)
            ->with('status', $status)
            ->with('registroManualReporte', ['creados' => $creados, 'errores' => $errores]);
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
