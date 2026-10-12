<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real confirmado 11/10/2026 (referencia LA-2B6689CA, reportado por el
 * usuario): al editar una inscripción YA PAGADA en Caja sin tocar la
 * categoría (ej. solo cambiar un taller), el form mandaba
 * `precioCategoria: totales.inscripcion` — pero ese campo es el DELTA a
 * cobrar (0 cuando la categoría no cambia), no el precio absoluto.
 * `SnapshotInscripcionPagadaData::recalcular()` (ApiRestEvent), al
 * recibir un precio_categoria=0 que no coincide con ningún precio real de
 * la categoría, caía al precio VIGENTE DE HOY como mejor estimación —
 * subiendo/bajando en silencio el precio de categoría de alguien cuya
 * categoría nunca se tocó, solo porque Caja editó otra cosa (un taller).
 * El dinero cobrado de verdad (caja_movimientos) nunca estuvo mal — era
 * puramente un bug de registro/reporte (`registration_totals.inscripcion`,
 * participante.precio_categoria).
 *
 * Fix: separar el DELTA (para calcular cuánto cobrar/mostrar) del precio
 * ABSOLUTO (`inscripcionAbsoluta`, para persistir) — ver calcular() en
 * caja/_formulario.blade.php.
 */
class CajaPrecioCategoriaAbsolutoTest extends TestCase
{
    private function comoAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_editar_pagada_manda_el_precio_absoluto_no_el_delta(): void
    {
        Http::fake([
            '*/event/7' => Http::response(['eventos' => ['id' => 7, 'name' => 'Congreso', 'formTypes' => [
                ['id' => 1, 'requiereCategoria' => true, 'hasDonation' => false, 'hasPromoCode' => false],
            ]]], 200),
            '*/registrations/LA-2B6689CA' => Http::response(['success' => true, 'data' => [
                'form_types_id' => 1, 'pago_status' => 'paid',
                'participantes' => [[
                    'nombre' => 'Viviana', 'categoria' => '1', 'precioCategoria' => 1000,
                ]],
            ]], 200),
        ]);

        $html = $this->comoAdmin()->get('/eventos/7/caja/registrations/LA-2B6689CA/editar')->assertOk()->getContent();

        // El fix: existe un campo separado para el precio absoluto.
        $this->assertStringContainsString('inscripcionAbsoluta', $html);
        // El bug original mandaba exactamente esta línea — confirmamos que
        // ya no está (sin la variable nueva de por medio).
        $this->assertStringNotContainsString('precioCategoria: totales.inscripcion,', $html);
        $this->assertStringContainsString('precioCategoria: totales.inscripcionAbsoluta,', $html);
        $this->assertStringContainsString('subtotal: totales.inscripcionAbsoluta,', $html);
    }
}
