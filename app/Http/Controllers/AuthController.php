<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = $client->forward('POST', '/admin/login', body: $request->only('email', 'password'));

        if (!$response || !$response->json('success')) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $response?->json('error') ?? 'No se pudo conectar con el servidor.']);
        }

        // Admin de evento asignado a varios eventos (28/08/2026) — ver
        // ApiRestEvent/brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md.
        // $data['admin'] ya trae 'eventoIds' (evento principal +
        // adicionales) desde AdminAuthController::login(); se guarda tal
        // cual, no hace falta desarmarlo acá.
        $data = $response->json('data');
        session([
            'admin_token' => $data['token'],
            'admin_user'  => $data['admin'],
        ]);

        // Caja de cobro presencial (14/08/2026) — un cajero no tiene
        // dashboard, va directo a su módulo. Ver
        // ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
        if (($data['admin']['rol'] ?? null) === 'cajero') {
            return $data['admin']['evento_id']
                ? redirect()->route('caja.index', $data['admin']['evento_id'])
                : redirect()->route('login')->withErrors(['email' => 'Tu usuario cajero no tiene un evento asignado — contactá a un administrador.']);
        }

        return redirect()->route('dashboard');
    }

    public function logout(ApiRestEventClient $client): RedirectResponse
    {
        $client->forward('POST', '/admin/logout');
        session()->forget(['admin_token', 'admin_user']);

        return redirect()->route('login');
    }
}
