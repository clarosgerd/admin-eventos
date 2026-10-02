<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real (02/10/2026, evento 90013 RUN & CHILL): la base del fee en
 * caja/_formulario.blade.php::calcular() nunca sumaba el souvenir con
 * `aplica_cargo_servicio=true` (ej. la polera), a diferencia del servidor
 * (CrearInscripcionAction::validateFeePct()) — con ese souvenir
 * seleccionado, el fee estimado quedaba más bajo que el real y el alta
 * nueva se rechazaba con "El cargo de servicio no coincide con el vigente
 * para este evento". No hay runner de JS en este stack — se verifica que
 * el plumbing (atributo `data-aplica-cargo` + su uso en calcular()) está
 * presente en el JS servido, mismo criterio que el resto de los tests de
 * este archivo (aserciones de string sobre el HTML/JS, no ejecución real).
 */
class CajaFeeSouvenirConCargoTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_calcular_suma_souvenirs_con_cargo_de_servicio_a_la_base_del_fee(): void
    {
        Http::fake(['*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => []]], 200)]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/nueva')->assertOk()->getContent();

        $this->assertStringContainsString('data-aplica-cargo="${sv.aplica_cargo_servicio ? 1 : 0}"', $html);
        $this->assertStringContainsString('aplicaCargoServicio: chk.dataset.aplicaCargo', $html);
        $this->assertStringContainsString('const souvenirsConCargo', $html);
        $this->assertStringContainsString('+ souvenirsConCargo', $html);
    }
}
