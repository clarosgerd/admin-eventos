<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — `TallerComboPrecioController` es un thin-forward hacia
 * ApiRestEvent, mismo patrón que TallerPricePeriodController.
 */
class TallerComboPrecioControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user'  => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_store_reenvia_category_id_y_precio(): void
    {
        Http::fake(['*/taller-combo/1/precios' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/taller-combos/1/precios', [
            'category_id' => '90', 'precio' => '1000',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/taller-combo/1/precios')
                && $request->method() === 'POST'
                && $request['category_id'] === '90'
                && $request['precio'] === '1000';
        });
    }

    public function test_store_default_sin_category_id(): void
    {
        Http::fake(['*/taller-combo/1/precios' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/taller-combos/1/precios', [
            'precio' => '900',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-combo/1/precios') && $request['precio'] === '900');
    }

    public function test_store_reenvia_price_usd(): void
    {
        Http::fake(['*/taller-combo/1/precios' => Http::response(['success' => true, 'data' => []], 201)]);

        $this->comoSuperAdmin()->post('/eventos/7/taller-combos/1/precios', [
            'category_id' => '90', 'precio' => '1000', 'price_usd' => '45',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-combo/1/precios') && $request['price_usd'] === '45');
    }

    public function test_update_reenvia_al_endpoint_correcto(): void
    {
        Http::fake(['*/taller-combo-precio/5' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->comoSuperAdmin()->put('/eventos/7/taller-combos/1/precios/5', [
            'category_id' => '90', 'precio' => '1050',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/taller-combo-precio/5') && $request->method() === 'PUT');
    }

    public function test_destroy_llama_al_delete(): void
    {
        Http::fake(['*/taller-combo-precio/5' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->delete('/eventos/7/taller-combos/1/precios/5')->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/taller-combo-precio/5'));
    }
}
