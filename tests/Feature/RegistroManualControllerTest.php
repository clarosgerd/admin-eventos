<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Carga masiva de inscripciones por CSV — RegistroManualController::store()
 * (06/10/2026). Bug real reportado por un usuario: un CSV exportado desde
 * Excel como "CSV" (no "CSV UTF-8") trae acentos en Windows-1252; una sola
 * celda así hacía que json_encode() fallara al armar el envío completo a
 * ApiRestEvent ("Malformed UTF-8 characters"), tumbando las 46 filas del
 * archivo con un error genérico, sin crear ninguna inscripción.
 */
class RegistroManualControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private const COLUMNAS = 'numero_documento,tipo_documento,nombre,apellido,alias,genero,fecha_nacimiento,email,direccion,ciudad,telefono,contacto_emergencia_nombre,contacto_emergencia_telefono,contacto_emergencia_relacion,talleres';

    private function filaCsv(string $nombreBytes): string
    {
        return "ABC001,DNI,{$nombreBytes},-,{$nombreBytes},Femenino,1995-06-15,ana@example.com,x,La Paz,77712345,Juan Prueba,77798765,Padre,\n";
    }

    public function test_un_campo_con_acentos_en_windows_1252_se_corrige_solo_y_no_tumba_el_archivo(): void
    {
        Http::fake(['*/event/7/registro-manual/bulk' => Http::response([
            'success' => true,
            'creados' => [['fila' => 2, 'numero_documento' => 'ABC001', 'referencia' => 'LA-TEST0001']],
            'errores' => [],
        ], 200)]);

        // "LAS AMÉRICAS" con la É codificada en Windows-1252 (0xC9), no en UTF-8.
        $nombreRoto = "LAS AM" . chr(0xC9) . "RICAS";
        $this->assertFalse(mb_check_encoding($nombreRoto, 'UTF-8'), 'el byte de prueba debe ser inválido en UTF-8');

        $csv = self::COLUMNAS . "\n" . $this->filaCsv($nombreRoto);
        $archivo = UploadedFile::fake()->createWithContent('lote.csv', $csv);

        $response = $this->comoSuperAdmin()->post('/eventos/7/registro-manual', [
            'form_types_id' => 5,
            'categoria' => 'Expositor',
            'csv' => $archivo,
        ]);

        $response->assertRedirect(route('registro-manual.index', 7));
        $reporte = $response->getSession()->get('registroManualReporte');
        $this->assertCount(1, $reporte['creados']);
        $this->assertSame(2, $reporte['creados'][0]['fila']);
        $this->assertSame([], $reporte['errores']);

        Http::assertSent(function ($request) {
            $nombreEnviado = $request['participantes'][0]['nombre'];

            return mb_check_encoding($nombreEnviado, 'UTF-8')
                && $nombreEnviado === 'LAS AMÉRICAS';
        });
    }

    public function test_sin_problemas_de_codificacion_los_numeros_de_fila_del_reporte_son_los_reales_del_csv(): void
    {
        Http::fake(['*/event/7/registro-manual/bulk' => Http::response([
            'success' => true,
            'creados' => [
                ['fila' => 2, 'numero_documento' => 'ABC001', 'referencia' => 'LA-TEST0001'],
                ['fila' => 3, 'numero_documento' => 'ABC002', 'referencia' => 'LA-TEST0002'],
            ],
            'errores' => [
                ['fila' => 4, 'numero_documento' => 'ABC003', 'error' => 'La fecha de nacimiento no es válida.'],
            ],
        ], 200)]);

        $csv = self::COLUMNAS . "\n"
            . $this->filaCsv('Ana')
            . $this->filaCsv('Luis')
            . $this->filaCsv('Mario');
        $archivo = UploadedFile::fake()->createWithContent('lote.csv', $csv);

        $response = $this->comoSuperAdmin()->post('/eventos/7/registro-manual', [
            'form_types_id' => 5,
            'categoria' => 'Expositor',
            'csv' => $archivo,
        ]);

        $reporte = $response->getSession()->get('registroManualReporte');
        $this->assertSame([2, 3], array_column($reporte['creados'], 'fila'));
        $this->assertSame([4], array_column($reporte['errores'], 'fila'));
    }

    /**
     * 'categoria' (07/10/2026) deja de ser obligatoria — un tipo de
     * formulario sin categoría (Staff, Ponente, "GAFETES STANDS", etc.)
     * no manda nada en ese campo porque la vista oculta el <select>. Antes
     * esta validación en sí misma ('categoria' => 'required') ya bloqueaba
     * el envío del formulario completo para ese caso.
     */
    public function test_sin_categoria_el_envio_funciona_igual(): void
    {
        Http::fake(['*/event/7/registro-manual/bulk' => Http::response([
            'success' => true,
            'creados' => [['fila' => 2, 'numero_documento' => 'ABC001', 'referencia' => 'LA-TEST0001']],
            'errores' => [],
        ], 200)]);

        $csv = self::COLUMNAS . "\n" . $this->filaCsv('Ana');
        $archivo = UploadedFile::fake()->createWithContent('lote.csv', $csv);

        $response = $this->comoSuperAdmin()->post('/eventos/7/registro-manual', [
            'form_types_id' => 5,
            // sin 'categoria' — equivalente a un <select> deshabilitado/oculto.
            'csv' => $archivo,
        ]);

        $response->assertRedirect(route('registro-manual.index', 7));
        $reporte = $response->getSession()->get('registroManualReporte');
        $this->assertCount(1, $reporte['creados']);

        Http::assertSent(fn ($request) => ($request['categoria'] ?? null) === null);
    }
}
