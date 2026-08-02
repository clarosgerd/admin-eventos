<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del panel — llama a los endpoints /admin/users de
 * ApiRestEvent, no reimplementa la lógica (esa vive en AdminUserController
 * del lado de la API, Fase 0). Solo accesible bajo el middleware
 * `admin.superadmin` (ver routes/web.php).
 */
class AdminUserController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/admin/users');
        $usuarios = $response?->json('data.data') ?? [];

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(ApiRestEventClient $client): View
    {
        $eventos = $this->listaEventos($client);

        return view('usuarios.form', [
            'usuario' => null,
            'eventos' => $eventos,
            'action'  => route('usuarios.store'),
        ]);
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/admin/users', body: $request->only(
            'nombre', 'email', 'password', 'rol', 'evento_id', 'activo'
        ));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(ApiRestEventClient $client, int $user): View
    {
        $response = $client->forward('GET', "/admin/users/{$user}");
        $usuario = $response?->json('data');
        $eventos = $this->listaEventos($client);

        return view('usuarios.form', [
            'usuario' => $usuario,
            'eventos' => $eventos,
            'action'  => route('usuarios.update', $user),
        ]);
    }

    public function update(Request $request, ApiRestEventClient $client, int $user): RedirectResponse
    {
        $data = $request->only('nombre', 'email', 'rol', 'evento_id', 'activo');
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $response = $client->forward('PUT', "/admin/users/{$user}", body: $data);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $user): RedirectResponse
    {
        $response = $client->forward('DELETE', "/admin/users/{$user}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el usuario.']);
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuario eliminado correctamente.');
    }

    /**
     * Lista de eventos para poblar el select de evento_id — reusa el
     * mismo endpoint que el dashboard, sin selector de evento propio para
     * no duplicar lógica de paginación (alcanza con la primera página
     * para este selector, ver Fase 2 si hace falta buscar más adelante).
     */
    private function listaEventos(ApiRestEventClient $client): array
    {
        $response = $client->forward('GET', '/event', query: ['per_page' => 48]);

        return $response?->json('eventos') ?? [];
    }

    private function extractErrors($response): array
    {
        if (!$response) {
            return ['general' => 'No se pudo conectar con el servidor.'];
        }

        $errors = $response->json('errors');
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $response->json('error') ?? 'Ocurrió un error.'];
    }
}
