<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reporte detallado de inscritos en CSV (04/10/2026) — formato igual al
 * legacy (xlsx de referencia): encabezados en MAYÚSCULAS, N°, FORMA DE PAGO,
 * OBSERVACIONES y una columna por pregunta "En reporte". Carrera y congreso
 * tienen columnas distintas (ver ParticipantesDetalleController::csvDownload).
 */
class ParticipantesDetalleCsvTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    private function fakeApi(string $tipoEvento, array $participante): void
    {
        Http::fake([
            '*/event/7/participantes*' => Http::response(['success' => true, 'participantes' => [$participante]], 200),
            '*/event/7' => Http::response(['eventos' => [
                'id' => 7, 'name' => 'Evento', 'date' => '2026-11-01', 'tipoEvento' => $tipoEvento,
                'categories' => [['id' => 90039, 'name' => '5K']],
            ]], 200),
        ]);
    }

    private function participante(array $overrides = []): array
    {
        return array_merge([
            'id' => 1, 'referencia' => 'LA-CSV1', 'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => 'ALI-7',
            'numeroDocumento' => '123', 'categoria' => '90039', 'numeroCorredor' => '12', 'genero' => 'Femenino',
            'telefono' => '77700000', 'fechaInscripcion' => '2026-10-01T10:00:00+00:00', 'fechaNacimiento' => '1990-01-01',
            'pagoStatus' => 'paid', 'tipoPago' => 'sip', 'importe' => 396, 'importePolera' => 50, 'importeTaller' => 0,
            'importeTotal' => 396, 'promoCodigo' => 'NARANJILLO10-01', 'promoDescuento' => 44,
            'categoriaRecalculada' => '18 - 29', 'respuestas' => [
                ['nombre_campo' => 'especialidad', 'etiqueta' => 'Especialidad', 'valor' => 'Retina'],
            ],
        ], $overrides);
    }

    private function csvDe(string $tipoEvento, array $participante): array
    {
        $this->fakeApi($tipoEvento, $participante);
        $csv = $this->comoAdmin()->get('/eventos/7/participantes/detalle/csv?pago_status=paid')
            ->assertOk()->getContent();

        $lineas = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $csv))));

        return [
            'encabezados' => str_getcsv($lineas[0]),
            'fila' => str_getcsv($lineas[1]),
        ];
    }

    public function test_carrera_separa_polera_y_muestra_distancia_categoria_forma_de_pago_y_pregunta(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante());

        $this->assertSame([
            'N°', 'NUMERO_CORREDOR', 'ESTADO', 'IMPORTE', 'IMPORTE_POLERA', 'IMPORTE_TOTAL',
            'PROMO_CODIGO', 'PROMO_DESCUENTO', 'NUMERO_DOCUMENTO', 'NOMBRE', 'APELLIDO', 'ALIAS',
            'SEXO', 'CELULAR', 'FECHA_INSCRIPCION', 'REFERENCIA', 'NACIMIENTO', 'DISTANCIA', 'CATEGORIA',
            'EDAD_FECHA', 'EDAD_FIN_DE_ANIO', 'EDAD_HOY', 'FORMA DE PAGO', 'OBSERVACIONES', 'ESPECIALIDAD',
        ], $csv['encabezados']);

        // IMPORTE sin polera (396 - 50), polera aparte, total sin cambios.
        $this->assertSame('1', $csv['fila'][0]);
        $this->assertSame('346', $csv['fila'][3]);
        $this->assertSame('50', $csv['fila'][4]);
        $this->assertSame('396', $csv['fila'][5]);
        $this->assertSame('NARANJILLO10-01', $csv['fila'][6]);
        $this->assertSame('5K', $csv['fila'][17]);
        $this->assertSame('18 - 29', $csv['fila'][18]);
        $this->assertSame('QR SIP', $csv['fila'][22]);
        $this->assertSame('Retina', end($csv['fila']));
    }

    public function test_congreso_muestra_taller_den_y_sin_columnas_de_carrera(): void
    {
        $csv = $this->csvDe('Congreso / No aplica', $this->participante([
            'importe' => 1800, 'importeTaller' => 1000, 'importeTotal' => 2800, 'importePolera' => 0,
            'tipoPago' => 'EFECTIVO', 'alias' => 'Dra.',
        ]));

        $this->assertSame([
            'N°', 'ESTADO', 'IMPORTE', 'IMPORTE_TALLER', 'IMPORTE_TOTAL',
            'PROMO_CODIGO', 'PROMO_DESCUENTO', 'NUMERO_DOCUMENTO', 'DEN.', 'NOMBRE', 'APELLIDO',
            'SEXO', 'CELULAR', 'FECHA_INSCRIPCION', 'REFERENCIA', 'NACIMIENTO', 'CATEGORIA',
            'EDAD_FECHA', 'EDAD_FIN_DE_ANIO', 'EDAD_HOY', 'FORMA DE PAGO', 'OBSERVACIONES', 'ESPECIALIDAD',
        ], $csv['encabezados']);

        $this->assertSame('Dra.', $csv['fila'][8]);
        $this->assertSame('1000', $csv['fila'][3]);
        $this->assertSame('EFECTIVO', $csv['fila'][20]);
    }

    public function test_forma_de_pago_desconocida_sale_en_mayusculas_sin_perder_el_dato(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante(['tipoPago' => 'otra_cosa']));

        $this->assertSame('OTRA_COSA', $csv['fila'][22]);
    }
    public function test_carrera_con_formulario_de_equipo_muestra_la_columna_equipo(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante([
            'eventoConEquipo' => true, 'equipo' => 'Los Veloces',
        ]));

        $this->assertContains('EQUIPO', $csv['encabezados']);
        $posicion = array_search('EQUIPO', $csv['encabezados'], true);
        $this->assertSame('Los Veloces', $csv['fila'][$posicion]);
    }

    public function test_sin_formulario_de_equipo_no_muestra_la_columna(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante(['eventoConEquipo' => false]));

        $this->assertNotContains('EQUIPO', $csv['encabezados']);
    }

    public function test_congreso_nunca_muestra_la_columna_equipo(): void
    {
        $csv = $this->csvDe('Congreso / No aplica', $this->participante([
            'eventoConEquipo' => true, 'equipo' => 'Los Veloces', 'importeTaller' => 0, 'tipoPago' => 'EFECTIVO',
        ]));

        $this->assertNotContains('EQUIPO', $csv['encabezados']);
    }
    public function test_carrera_con_polera_muestra_la_talla(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante([
            'eventoConPolera' => true, 'polera' => 'M',
        ]));

        $posicion = array_search('POLERA', $csv['encabezados'], true);
        $this->assertNotFalse($posicion);
        $this->assertSame('M', $csv['fila'][$posicion]);
    }

    public function test_polera_no_shirt_sale_vacia(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante([
            'eventoConPolera' => true, 'polera' => 'No shirt',
        ]));

        $posicion = array_search('POLERA', $csv['encabezados'], true);
        $this->assertSame('', $csv['fila'][$posicion]);
    }

    public function test_evento_sin_polera_no_muestra_la_columna(): void
    {
        $csv = $this->csvDe('Carrera de Ruta', $this->participante(['eventoConPolera' => false]));

        $this->assertNotContains('POLERA', $csv['encabezados']);
    }

    public function test_congreso_nunca_muestra_la_columna_polera(): void
    {
        $csv = $this->csvDe('Congreso / No aplica', $this->participante([
            'eventoConPolera' => true, 'polera' => 'M', 'importeTaller' => 0, 'tipoPago' => 'EFECTIVO',
        ]));

        $this->assertNotContains('POLERA', $csv['encabezados']);
    }
}
