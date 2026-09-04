<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administración de Personas (cuentas públicas del sitio de inscripción)
 * — solo super_admin (ver routes/web.php, bloque `admin.superadmin`). No
 * es un catálogo (listas fijas de referencia): son datos reales de
 * participantes, potencialmente miles de filas, por eso tiene su propia
 * entrada de menú y no vive bajo Catálogos (pedido explícito del usuario,
 * 03/09/2026).
 *
 * Llama a /persona de ApiRestEvent (PersonaController ahí, CRUD completo
 * desde el 21/08/2026 — acá solo se agrega la pantalla, no se reimplementa
 * nada de la lógica de negocio/validación/auditoría, que ya vive del lado
 * de la API). Búsqueda + paginación mismo patrón que
 * DashboardController::index() (listado de eventos); store/update/destroy
 * mismo patrón que SocioController/OrganizadorController.
 */
class PersonaController extends Controller
{
    public function index(Request $request, ApiRestEventClient $client): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = ['per_page' => 20, 'page' => (int) $request->query('page', 1)];
        if ($search !== '') {
            $query['search'] = $search;
        }

        $response = $client->forward('GET', '/persona', query: $query);
        $personas = $response?->json('persona') ?? [];
        $pagination = $response?->json('pagination');

        return view('personas.index', compact('personas', 'pagination', 'search'));
    }

    public function create(): View
    {
        return view('personas.create');
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/persona', body: $this->payload($request));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('personas.index')->with('status', 'Persona creada correctamente.');
    }

    public function edit(int $persona, ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', "/persona/{$persona}");
        $personaData = $response?->json('persona');

        abort_if(!$personaData, 404);

        return view('personas.edit', ['persona' => $personaData]);
    }

    public function update(Request $request, int $persona, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PUT', "/persona/{$persona}", body: $this->payload($request, sometimes: true));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('personas.index')->with('status', 'Persona actualizada correctamente.');
    }

    public function destroy(int $persona, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/persona/{$persona}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar la persona.']);
        }

        return redirect()->route('personas.index')->with('status', 'Persona eliminada correctamente.');
    }

    /**
     * `password` vacío significa "no cambiarla" en update (ver
     * UpdatePersonaRequest del lado de la API) — acá basta con no mandar
     * la clave si vino vacía, mismo criterio que otros forms de este panel
     * para checkboxes/campos opcionales. En create, un password vacío se
     * manda igual (la API genera una aleatoria si falta).
     */
    private function payload(Request $request, bool $sometimes = false): array
    {
        $payload = $request->only(
            'nombre', 'apellido', 'alias', 'email', 'sexo', 'tipo_documento',
            'numero_documento', 'fecha_nacimiento', 'direccion', 'ciudad',
            'telefono', 'celular'
        );

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        } elseif (!$sometimes) {
            $payload['password'] = null;
        }

        $payload['acepta_marketing'] = $request->boolean('acepta_marketing');

        return $payload;
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

        return ['general' => $response->json('error') ?? $response->json('message') ?? 'Ocurrió un error.'];
    }
}
