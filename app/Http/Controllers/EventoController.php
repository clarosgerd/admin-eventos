<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del panel — arma el payload anidado que espera
 * StoreEventosRequest (ApiRestEvent) y llama POST /event. No reimplementa
 * ninguna validación de negocio, eso vive del lado de la API. Alcance
 * "núcleo" de la Fase 2 (ver brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md
 * §3): evento + categorías + form_types con souvenirs — sin promo_codes,
 * coordinates, route, auspiciadores ni agenda todavía.
 */
class EventoController extends Controller
{
    public function create(): View
    {
        return view('eventos.create');
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $categories = collect($request->input('categories', []))
            ->filter(fn ($c) => filled($c['name'] ?? null))
            ->values()
            ->all();

        $formTypes = collect($request->input('formTypes', []))
            ->filter(fn ($ft) => filled($ft['name'] ?? null))
            ->map(function ($ft) {
                $ft['requiere_categoria'] = isset($ft['requiere_categoria']);
                $ft['hasTeam'] = isset($ft['hasTeam']);
                $ft['hasDelivery'] = isset($ft['hasDelivery']);
                $ft['souvenirs'] = collect($ft['souvenirs'] ?? [])
                    ->filter(fn ($s) => filled($s['name'] ?? null))
                    ->values()
                    ->all();

                return $ft;
            })
            ->values()
            ->all();

        $coordinates = collect($request->input('coordinates', []))
            ->filter(fn ($c) => filled($c['lat'] ?? null) && filled($c['lng'] ?? null))
            ->values()
            ->all();

        $route = collect($request->input('route', []))
            ->filter(fn ($r) => filled($r['lat'] ?? null) && filled($r['lng'] ?? null))
            ->values()
            ->all();

        $promoCodes = collect($request->input('promoCodes', []))
            ->filter(fn ($p) => filled($p['promo_code'] ?? null))
            ->values()
            ->all();

        $auspiciadores = collect($request->input('auspiciadores', []))
            ->filter(fn ($a) => filled($a['nombre'] ?? null))
            ->values()
            ->all();

        $agenda = collect($request->input('agenda', []))
            ->filter(fn ($a) => filled($a['title'] ?? null))
            ->values()
            ->all();

        $payload = array_merge(
            $request->only(
                'name', 'description', 'longDescription', 'date', 'localTime', 'location',
                'status', 'video', 'image', 'colorHex', 'deslinde', 'deslinde_pdf_url'
            ),
            [
                'hasDonation'   => $request->boolean('hasDonation'),
                'categories'    => $categories,
                'formTypes'     => $formTypes,
                'coordinates'   => $coordinates,
                'route'         => $route,
                'promoCodes'    => $promoCodes,
                'auspiciadores' => $auspiciadores,
                'agenda'        => $agenda,
            ]
        );

        $response = $client->forward('POST', '/event', body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('dashboard')->with(
            'status',
            'Evento creado como borrador — no es visible para participantes hasta que lo publiques.'
        );
    }

    /**
     * Publicar es una acción irreversible que dispara un correo real al
     * organizador (EnviarDashboardOrganizadorAction, ApiRestEvent) — no
     * es un toggle. Alcanzable tanto por super_admin como por un admin
     * scoped a su propio evento (el scoping real lo valida la API vía
     * AuthorizesEventoScope::assertCanWriteEvento(), acá no hace falta
     * admin.superadmin).
     */
    public function publicar(int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PATCH', "/event/{$evento}/publicar");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $this->extractError($response, 'No se pudo publicar el evento.')]);
        }

        return back()->with('status', 'Evento publicado — se envió el correo al organizador.');
    }

    /**
     * Revierte un evento publicado a borrador — sin correo, la API lo
     * bloquea (409) si el evento ya tiene participantes inscritos.
     */
    public function despublicar(int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('PATCH', "/event/{$evento}/despublicar");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $this->extractError($response, 'No se pudo despublicar el evento.')]);
        }

        return back()->with('status', 'Evento despublicado correctamente.');
    }

    /**
     * GET /eventos/{evento}/edit — trae el evento completo (categorías y
     * form_types con souvenirs ya vienen anidados en la respuesta de
     * ApiRestEvent, no hace falta pedirlos aparte).
     */
    public function edit(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}");
        $eventoData = $response?->json('eventos');

        abort_if(!$eventoData, 404);

        return view('eventos.edit', ['evento' => $eventoData]);
    }

    /**
     * Guarda de UX del panel — la API ya rechaza cualquier escritura fuera
     * de scope, pero sin esto un admin scoped podría navegar directo a la
     * URL de otro evento y ver sus datos (categorías, form_types) aunque
     * no pueda guardarlos. Mismo criterio que EnsureSuperAdminSession.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if ($admin['rol'] !== 'super_admin' && (int) $admin['evento_id'] !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }

    /**
     * Solo campos escalares del evento — categorías/form_types/souvenirs
     * se administran aparte (CategoriaController/FormTypeController/
     * SouvenirController), mismo criterio que UpdateEventosRequest del
     * lado de la API (no resincroniza nested arrays).
     */
    public function update(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $payload = array_merge(
            $request->only(
                'name', 'description', 'longDescription', 'date', 'localTime', 'location',
                'status', 'video', 'image', 'colorHex', 'deslinde', 'deslinde_pdf_url'
            ),
            ['hasDonation' => $request->boolean('hasDonation')]
        );

        $response = $client->forward('PUT', "/event/{$evento}", body: $payload);

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('eventos.edit', $evento)->with('status', 'Evento actualizado correctamente.');
    }

    public function destroy(int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('DELETE', "/event/{$evento}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $this->extractError($response, 'No se pudo eliminar el evento.')]);
        }

        return redirect()->route('dashboard')->with('status', 'Evento eliminado correctamente.');
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

        return ['general' => $this->extractError($response, 'Ocurrió un error.')];
    }

    /**
     * Los 403 de AuthorizesEventoScope (ApiRestEvent) llegan como
     * excepción estándar de Laravel (clave "message"), no con el sobre
     * {success,error} que usan las respuestas de negocio — se revisan
     * ambas claves antes de caer al mensaje genérico.
     */
    private function extractError($response, string $fallback): string
    {
        return $response?->json('error') ?? $response?->json('message') ?? $fallback;
    }
}
