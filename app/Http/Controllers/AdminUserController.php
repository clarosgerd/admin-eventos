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
            'nombre', 'email', 'password', 'rol', 'evento_id', 'evento_ids_adicionales', 'activo'
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
        $data = $request->only('nombre', 'email', 'rol', 'evento_id', 'evento_ids_adicionales', 'activo');
        // Admin de evento asignado a varios eventos (28/08/2026) — un
        // <select multiple> vacío (nadie tildado) no manda la clave en el
        // POST, y ApiRestEvent necesita distinguir "no la toques" de
        // "vaciala" (ver AdminUserController::update() del lado de la
        // API). Se manda explícita como array vacío cuando el rol es
        // admin, para que "deseleccionar todo" también funcione.
        if (($data['rol'] ?? null) === 'admin' && !array_key_exists('evento_ids_adicionales', $data)) {
            $data['evento_ids_adicionales'] = [];
        }
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
     * Lista de eventos para poblar el select de evento_id (principal) y
     * el multi-select de evento_ids_adicionales — reusa el mismo endpoint
     * que el dashboard.
     *
     * Bug real (28/08/2026, reportado por el usuario: "solo le muestra 48
     * registros de eventos") — `GET /event` tiene un tope DURO server-side
     * de 48 por página (`EventoController::index()`,
     * `min(48, $perPage)` — deliberado, para que el frontend público nunca
     * pida el catálogo completo de una), así que pedir `per_page` más alto
     * no alcanzaba: con más de 48 eventos cargados, este selector
     * simplemente no dejaba elegir los que quedaban afuera de la primera
     * página. Se recorre acá toda la paginación (respetando el tope de 48
     * por página) hasta juntar el catálogo completo — no toca el tope del
     * lado de la API, que sigue protegiendo al consumidor público.
     */
    private function listaEventos(ApiRestEventClient $client): array
    {
        $eventos = [];
        $page = 1;

        do {
            $response = $client->forward('GET', '/event', query: ['per_page' => 48, 'page' => $page]);
            $eventos = array_merge($eventos, $response?->json('eventos') ?? []);
            $lastPage = $response?->json('pagination.last_page') ?? $page;
            $page++;
        } while ($page <= $lastPage);

        return $eventos;
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
