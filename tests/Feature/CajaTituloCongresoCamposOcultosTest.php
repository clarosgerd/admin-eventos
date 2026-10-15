<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real confirmado 15/10/2026 (reportado por el usuario): en un evento
 * tipo congreso con un form_type "stand" (empresa expositora) que oculta
 * el campo `alias` (reusado como "Título" en congresos, ver
 * toggleAliasTituloMode() en caja/_formulario.blade.php), el botón de
 * Caja no dejaba avanzar sin ningún mensaje visible.
 *
 * Causa: toggleAliasTituloMode() marcaba `select.required = true` para
 * CUALQUIER form_type tipo='congreso', sin mirar camposOcultos — corría
 * DESPUÉS de applyCamposOcultos() en actualizarSecciones(), así que el
 * campo quedaba oculto (display:none) pero igual required=true. Un campo
 * required oculto bloquea el submit del navegador en silencio (mismo
 * motivo por el que renderContactoEmergencia() ya sacaba el required a
 * mano en vez de confiar en display:none).
 */
class CajaTituloCongresoCamposOcultosTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_titulo_nunca_queda_required_si_alias_esta_en_campos_ocultos(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => [
                [
                    'id' => 1, 'name' => 'Stand', 'tipo' => 'congreso', 'requiereCategoria' => true,
                    'hasDonation' => false, 'hasPromoCode' => false,
                    'camposOcultos' => ['alias'],
                ],
            ]]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/nueva')->assertOk()->getContent();

        // El fix: el required del Título respeta camposOcultos.
        $this->assertStringContainsString('select.required = isCongreso && !oculto;', $html);
        // El bug original: required solo dependía de isCongreso.
        $this->assertStringNotContainsString('select.required = isCongreso;', $html);
    }
}
