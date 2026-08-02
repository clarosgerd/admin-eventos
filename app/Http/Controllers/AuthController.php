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

        $data = $response->json('data');
        session([
            'admin_token' => $data['token'],
            'admin_user'  => $data['admin'],
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(ApiRestEventClient $client): RedirectResponse
    {
        $client->forward('POST', '/admin/logout');
        session()->forget(['admin_token', 'admin_user']);

        return redirect()->route('login');
    }
}
