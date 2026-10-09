<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — el
 * <select> de whatsapp_canal en organizadores/index.blade.php, opt-in
 * estricto: solo un super_admin lo cambia, nunca se activa solo.
 */
class OrganizadorWhatsappCanalTest extends TestCase
{
    private function comoSuperAdmin(): self
    {
        $this->withSession([
            'admin_token' => 'fake-token',
            'admin_user' => ['id' => 1, 'rol' => 'super_admin', 'evento_id' => null, 'eventoIds' => []],
        ]);

        return $this;
    }

    public function test_index_muestra_el_select_de_whatsapp_canal(): void
    {
        Http::fake(['*/organizadores' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'razon_social' => 'Org Test', 'nombre_comercial' => '', 'rut_nit' => '', 'email' => 'a@a.com',
             'telefono' => '', 'activo' => true, 'whatsapp_canal' => 'oficial', 'eventos_count' => 0],
        ]], 200)]);

        $html = $this->comoSuperAdmin()->get('/organizadores')->assertOk()->getContent();

        $this->assertStringContainsString('name="whatsapp_canal"', $html);
        $this->assertMatchesRegularExpression('/<option value="oficial"[^>]*selected/', $html);
    }

    public function test_update_reenvia_whatsapp_canal(): void
    {
        Http::fake(['*/organizadores/1' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/organizadores/1', [
            'razon_social' => 'Org Test',
            'email' => 'a@a.com',
            'whatsapp_canal' => 'oficial',
        ]);

        Http::assertSent(fn ($request) => ($request->data()['whatsapp_canal'] ?? null) === 'oficial');
    }

    public function test_update_sin_whatsapp_canal_no_lo_manda(): void
    {
        Http::fake(['*/organizadores/1' => Http::response(['success' => true], 200)]);

        $this->comoSuperAdmin()->put('/organizadores/1', [
            'razon_social' => 'Org Test',
            'email' => 'a@a.com',
        ]);

        Http::assertSent(fn ($request) => !array_key_exists('whatsapp_canal', $request->data()));
    }
}
