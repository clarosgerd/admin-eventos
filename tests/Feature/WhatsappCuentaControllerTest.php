<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) —
 * WhatsappCuentaController es un proxy puro, mismo patrón que
 * SipBancoController: estos tests confirman que el formulario arma el
 * payload correcto y que `access_token` nunca se manda si quedó vacío
 * (editar sin tocarlo no lo borra).
 */
class WhatsappCuentaControllerTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_index_lista_las_cuentas(): void
    {
        Http::fake(['*/whatsapp-cuentas' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'nombre' => 'Cuenta Test', 'organizadorNombre' => 'Org Test', 'phoneNumberId' => '123', 'templateName' => 'notificacion_sistema', 'templateLang' => 'es', 'activo' => true],
        ]], 200)]);

        $html = $this->comoSuperAdmin()->get('/whatsapp-cuentas')->assertOk()->getContent();

        $this->assertStringContainsString('Cuenta Test', $html);
        $this->assertStringContainsString('Org Test', $html);
    }

    public function test_store_manda_el_payload_correcto(): void
    {
        Http::fake(['*/whatsapp-cuentas' => Http::response(['success' => true, 'data' => ['id' => 1]], 201)]);

        $this->comoSuperAdmin()->post('/whatsapp-cuentas', [
            'organizador_id' => '5',
            'nombre' => 'Cuenta Nueva',
            'phone_number_id' => '999888777',
            'business_account_id' => '111222333',
            'access_token' => 'token-secreto',
            'template_name' => 'notificacion_sistema',
            'template_lang' => 'es',
            'activo' => '1',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/whatsapp-cuentas')
                && $request->method() === 'POST'
                && $request['nombre'] === 'Cuenta Nueva'
                && $request['phone_number_id'] === '999888777'
                && $request['access_token'] === 'token-secreto';
        });
    }

    public function test_update_sin_access_token_no_lo_manda(): void
    {
        Http::fake([
            '*/whatsapp-cuentas/1' => Http::response(['success' => true, 'data' => ['id' => 1]], 200),
        ]);

        $this->comoSuperAdmin()->put('/whatsapp-cuentas/1', [
            'nombre' => 'Cuenta Renombrada',
            'phone_number_id' => '999888777',
            'access_token' => '',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/whatsapp-cuentas/1')
                && $request->method() === 'PUT'
                && !array_key_exists('access_token', $request->data());
        });
    }

    public function test_update_con_access_token_lo_manda(): void
    {
        Http::fake([
            '*/whatsapp-cuentas/1' => Http::response(['success' => true, 'data' => ['id' => 1]], 200),
        ]);

        $this->comoSuperAdmin()->put('/whatsapp-cuentas/1', [
            'nombre' => 'Cuenta Renombrada',
            'phone_number_id' => '999888777',
            'access_token' => 'token-nuevo',
        ]);

        Http::assertSent(fn ($request) => ($request->data()['access_token'] ?? null) === 'token-nuevo');
    }

    public function test_destroy_llama_al_delete(): void
    {
        Http::fake(['*/whatsapp-cuentas/1' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->delete('/whatsapp-cuentas/1');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/whatsapp-cuentas/1'));
    }
}
