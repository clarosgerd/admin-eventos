<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exportación a ChronoTrack (18/09/2026) — CSV de carga manual, formato
 * calcado de un archivo real ya usado con éxito (ver memoria del proyecto,
 * project_chronotrack_export_entries). Http::fake contra ApiRestEventClient,
 * mismo patrón que PromoCodeReporteControllerTest.
 */
class ChronoTrackExportControllerTest extends TestCase
{
    private function withAdminSession(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null],
        ]);

        return $this;
    }

    private function fakeEventoYParticipantes(): void
    {
        Http::fake([
            '*/event/1' => Http::response([
                'success' => true,
                'eventos' => [
                    'id' => 1, 'name' => 'Carrera Test',
                    'categories' => [['id' => 5, 'name' => '5K']],
                    'pais' => ['nombre' => 'Bolivia', 'iso2' => 'BO'],
                ],
            ], 200),
            '*/event/1/participantes' => Http::response([
                'success' => true,
                'participantes' => [[
                    'id' => 42, 'nombre' => 'Ana', 'apellido' => 'Perez',
                    'categoria' => '5', 'numeroCorredor' => '101', 'chip' => 'CHIP101',
                    'genero' => 'Femenino', 'fechaNacimiento' => '1995-03-20',
                    'ciudad' => 'La Paz', 'telefono' => '70011122',
                ]],
            ], 200),
        ]);
    }

    public function test_header_exacto_20_columnas_en_orden(): void
    {
        $this->fakeEventoYParticipantes();

        $csv = $this->withAdminSession()->get('/eventos/1/chronotrack/csv')->assertOk()->getContent();
        $header = str_getcsv(explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $csv)))[0]);

        $this->assertSame([
            'EXTERNAL_ID', 'TYPE', 'REG_CHOICE', 'RACE_NAME', 'BIB', 'TAG', 'BRACKET',
            'FIRST_NAME', 'LAST_NAME', 'GENDER', 'RACE_AGE', 'REG_AGE', 'DOB', 'CITY',
            'COUNTRY_NAME', 'COUNTRY_CODE', 'SMScountycode1', 'SMSlanguage1', 'SMSnumber1', 'CATEGORIA',
        ], $header);
    }

    public function test_fila_con_valores_reales(): void
    {
        $this->fakeEventoYParticipantes();

        $csv = str_replace("\xEF\xBB\xBF", '', $this->withAdminSession()->get('/eventos/1/chronotrack/csv')->assertOk()->getContent());
        $linea = str_getcsv(explode("\n", trim($csv))[1]);

        $this->assertSame([
            '42', 'IND', '5K', '5K', '101', 'CHIP101', 'Femenino',
            'Ana', 'Perez', 'Femenino', '', '', '20-03-1995', 'La Paz',
            'BO', 'BO', 'Bolivia', 'Spanish', '70011122', '5K',
        ], $linea);
    }

    public function test_403_si_el_admin_no_tiene_acceso_al_evento(): void
    {
        $this->fakeEventoYParticipantes();

        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 2, 'rol' => 'admin', 'evento_id' => 99, 'eventoIds' => [99]],
        ]);

        $this->get('/eventos/1/chronotrack/csv')->assertForbidden();
    }
}
