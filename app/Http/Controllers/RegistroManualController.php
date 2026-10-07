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
        // Talleres (21/08/2026) — opcional, uno o más nombres de taller
        // separados por ';' (ver RegistrationController::importarBulk()
        // en ApiRestEvent, resolverTalleresFila()). Vacío = sin talleres,
        // válido siempre que el evento no tenga ninguno obligatorio.
        'talleres',
    ];

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/event/{$evento}");
        $eventoData = $response?->json('eventos');
        abort_if(!$eventoData, 404);

        return view('eventos.registro-manual', ['evento' => $eventoData]);
    }

    /**
     * La plantilla incluye un ejemplo con un taller real del evento (si
     * tiene alguno cargado) para que el nombre a escribir en el CSV quede
     * claro de una — mismo criterio que el <select> de categoría en la
     * pantalla, que ya usa nombres reales.
     */
    public function plantilla(int $evento, ApiRestEventClient $client): Response
    {
        $response = $client->forward('GET', "/event/{$evento}");
        $talleres = $response?->json('eventos.talleres') ?? [];
        $ejemploTaller = $talleres[0]['nombre'] ?? '';

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNAS);
        fputcsv($handle, [
            '1234567', 'DNI', 'Ana', 'Prueba', 'AnaP', 'Femenino',
            '1995-06-15', 'ana@example.com', 'Av. Siempre Viva 123', 'La Paz', '77712345',
            'Juan Prueba', '77798765', 'Padre', $ejemploTaller,
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
            // 'categoria' (07/10/2026) — deja de ser obligatoria: un tipo de
            // formulario sin categoría (Staff, Ponente, o cualquier otro
            // armado así desde "Tipo de formulario" → requiere categoría,
            // ej. "GAFETES STANDS") no tiene nada que elegir acá; la vista
            // oculta el <select> en ese caso (ver registro-manual.blade.php).
            // ApiRestEvent valida que si el tipo SÍ requiere categoría, esta
            // llegue igual (ver RegistrationController::importarBulk()).
            'categoria' => ['nullable', 'string'],
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
        // Errores de lectura (06/10/2026) — antes, una sola celda con
        // caracteres que no son UTF-8 (típico al exportar un CSV desde
        // Excel en español como "CSV" en vez de "CSV UTF-8") hacía que
        // json_encode() fallara al armar el envío a ApiRestEvent
        // (GuzzleHttp\Exception\InvalidArgumentException: "Malformed UTF-8
        // characters"), tumbando el archivo COMPLETO con un error genérico
        // antes de que se creara una sola inscripción. Ahora se intenta
        // recuperar el valor (Windows-1252 es la codificación casi segura
        // en ese caso); si no se puede, esa fila queda en $erroresLectura
        // y el resto del archivo se sigue procesando — mismo criterio que
        // ApiRestEvent ya aplica fila por fila (ver reglasFilaCarga()).
        $erroresLectura = [];
        $filasReales = [];
        $fila = 1; // la fila 1 es el encabezado, ya consumido arriba
        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila completamente vacía, se ignora
            }
            $item = [];
            $columnaInvalida = null;
            foreach (self::COLUMNAS as $columna) {
                $valor = trim((string) ($row[$indices[$columna]] ?? ''));
                if ($valor !== '' && !mb_check_encoding($valor, 'UTF-8')) {
                    $convertido = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
                    if (mb_check_encoding($convertido, 'UTF-8')) {
                        $valor = $convertido;
                    } else {
                        $columnaInvalida = $columna;
                        break;
                    }
                }
                $item[$columna] = $valor;
            }
            if ($columnaInvalida !== null) {
                $erroresLectura[] = [
                    'fila' => $fila,
                    'numero_documento' => $item['numero_documento'] ?? null,
                    'error' => "La columna \"{$columnaInvalida}\" tiene caracteres con una codificación que no se pudo leer. Volvé a guardar el archivo como CSV UTF-8.",
                ];
                continue;
            }
            // La fila real del CSV, guardada por posición en $participantes
            // (no el índice del array: cuando alguna fila se omite más
            // arriba por error de lectura, la posición y la fila real del
            // archivo dejan de coincidir) — se usa abajo para corregir los
            // números de fila que devuelve ApiRestEvent, que los calcula
            // sobre el array que le llega, ya sin las filas omitidas acá.
            $filasReales[] = $fila;
            $participantes[] = $item;
        }
        fclose($handle);

        if (empty($participantes)) {
            if (!empty($erroresLectura)) {
                return redirect()->route('registro-manual.index', $evento)
                    ->with('status', '0 inscripción(es) creada(s). ' . count($erroresLectura) . ' fila(s) con error — ver detalle abajo.')
                    ->with('registroManualReporte', ['creados' => [], 'errores' => $erroresLectura]);
            }

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

        // ApiRestEvent numera 'fila' por la posición dentro del array que le
        // llegó (índice + 2) — se traduce de vuelta a la fila real del CSV
        // con $filasReales, guardado arriba en el mismo orden en que se
        // armó $participantes.
        $remapFila = function (array $entry) use ($filasReales): array {
            $posicion = ($entry['fila'] ?? 2) - 2;
            $entry['fila'] = $filasReales[$posicion] ?? $entry['fila'];

            return $entry;
        };

        $creados = array_map($remapFila, $response->json('creados') ?? []);
        $errores = array_merge($erroresLectura, array_map($remapFila, $response->json('errores') ?? []));
        usort($errores, fn ($a, $b) => $a['fila'] <=> $b['fila']);

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
