<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
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
    use AuthorizesEventoScope;

    public function create(ApiRestEventClient $client): View
    {
        return view('eventos.create', [
            'tiposEvento' => $this->tiposEvento($client),
            'organizadores' => $this->organizadores($client),
        ]);
    }

    /**
     * Catálogo de disciplinas (Carrera de Ruta, Trail Running, ...,
     * "Congreso / No aplica") para el select de tipo/subtipo — ver
     * brain/PLAN-ENDPOINT-CONSUMO-05082026.md. Público del lado de
     * ApiRestEvent, sin datos sensibles.
     */
    private function tiposEvento(ApiRestEventClient $client): array
    {
        $response = $client->forward('GET', '/tipos-evento');

        return $response?->json('tiposEvento') ?? [];
    }

    /**
     * Catálogo de organizadores para el select de "crear/editar evento" —
     * ver OrganizadorController (ApiRestEvent, CRUD real) y
     * PRD-organizadores-crud.md. Este endpoint es solo super_admin del lado
     * de la API, pero create()/edit() lo llaman siempre igual que
     * tiposEvento(): un admin scoped a su evento nunca alcanza la pantalla
     * de "crear evento" (route eventos.create está bajo admin.superadmin),
     * y en edit() simplemente no se usa la lista si no es super_admin (la
     * vista muestra el organizador ya asignado como texto).
     */
    private function organizadores(ApiRestEventClient $client): array
    {
        $response = $client->forward('GET', '/organizadores');

        return $response?->json('data') ?? [];
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
                // hasDonation/hasPromoCode pasaron de evento a form_type
                // (QA visual, 10/08) — mismo criterio que hasTeam/hasDelivery.
                $ft['hasDonation'] = isset($ft['hasDonation']);
                $ft['hasPromoCode'] = isset($ft['hasPromoCode']);
                // Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md
                $ft['esStaff'] = isset($ft['esStaff']);
                // Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md
                $ft['esPonente'] = isset($ft['esPonente']);
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
                'status', 'video', 'image', 'colorHex', 'deslinde', 'deslinde_pdf_url',
                'tipo_evento_id', 'subtipo_evento_id', 'organizador_id', 'url_slug'
            ),
            [
                'categories'    => $categories,
                'formTypes'     => $formTypes,
                'coordinates'   => $coordinates,
                'route'         => $route,
                'promoCodes'    => $promoCodes,
                'auspiciadores' => $auspiciadores,
                'agenda'        => $agenda,
            ]
        );

        // El <select> de organizador tiene una opción "Sin organizador
        // asignado" (value=""), pero StoreEventosRequest valida
        // organizador_id como nullable|integer — un string vacío no pasa
        // "integer" (nullable solo exime el valor `null` real, no "").
        if (($payload['organizador_id'] ?? null) === '') {
            $payload['organizador_id'] = null;
        }

        // Inscripción en BOB y USD (18/08/2026) — ver
        // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Default false
        // si no viene en el form (eventos nuevos nacen BOB-only).
        $payload['aceptaUsd'] = $request->boolean('aceptaUsd');

        // Congresos con talleres (19/08/2026) — sin checkbox en create.blade.php
        // a propósito (los talleres se cargan después de crear el evento, vía
        // la pestaña "Talleres"); default false, igual que aceptaUsd.
        $payload['talleresConCosto'] = $request->boolean('talleresConCosto');

        // Precio USD fijo (19/08/2026) — sin checkbox en create.blade.php a
        // propósito, mismo motivo que talleresConCosto (el precio USD por
        // categoría se carga después, en la pestaña Categorías del evento
        // ya creado). Default false.
        $payload['usdPrecioFijo'] = $request->boolean('usdPrecioFijo');

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

        return view('eventos.edit', [
            'evento' => $eventoData,
            'tiposEvento' => $this->tiposEvento($client),
            'organizadores' => $this->organizadores($client),
        ]);
    }

    /**
     * Gafetes/credenciales en bulk (uno por participante, con QR) — proxy
     * de GET /event/{event}/gafetes-pdf. Ese endpoint es público del lado
     * de ApiRestEvent (no requiere el token de admin), pero el panel igual
     * lo sirve a través de su propio dominio en vez de linkear directo a
     * ApiRestEvent — mismo criterio de no exponer el host externo que usa
     * elascenso/event (ver api/agenda_pdf.php ahí, mismo patrón de stream).
     * Timeout más largo que el resto de las llamadas: dompdf puede tardar
     * con eventos grandes.
     */
    public function gafetesPdf(int $evento, ApiRestEventClient $client)
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}/gafetes-pdf", timeoutSeconds: 30, retries: 0);
        abort_if(!$response || !$response->successful(), 502, 'No se pudo generar el PDF de gafetes.');

        return response($response->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gafetes-evento-'.$evento.'.pdf"',
        ]);
    }

    /**
     * Certificados de asistencia/participación en bulk — proxy de
     * GET /event/{event}/certificados-pdf, mismo criterio que gafetesPdf().
     */
    public function certificadosPdf(int $evento, ApiRestEventClient $client)
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/event/{$evento}/certificados-pdf", timeoutSeconds: 30, retries: 0);
        abort_if(!$response || !$response->successful(), 502, 'No se pudo generar el PDF de certificados.');

        return response($response->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificados-evento-'.$evento.'.pdf"',
        ]);
    }

    /**
     * Solo campos escalares del evento — categorías/form_types/souvenirs
     * se administran aparte (CategoriaController/FormTypeController/
     * SouvenirController), mismo criterio que UpdateEventosRequest del
     * lado de la API (no resincroniza nested arrays).
     */
    public function update(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        // hasDonation ya no es un campo escalar del evento (pasó a
        // form_type, QA visual 10/08) — este endpoint no toca form_types,
        // así que no hay nada que sumar acá; se administra vía
        // FormTypeController.
        // organizador_id se manda solo si vino en el body — la vista solo
        // renderiza ese <select> para super_admin y con el evento todavía
        // en borrador (ver eventos/edit.blade.php), ApiRestEvent igual
        // rechaza cualquier otro caso (403 si no es super_admin, 422 si el
        // evento ya está publicado — ver EventoController::update() ahí).
        $payload = $request->only(
            'name', 'description', 'longDescription', 'date', 'localTime', 'location',
            'status', 'video', 'image', 'colorHex', 'chronotrackEventId', 'deslinde', 'deslinde_pdf_url',
            'tipo_evento_id', 'subtipo_evento_id', 'organizador_id'
        );

        // Mismo motivo que en store(): el value="" de "Sin organizador
        // asignado" no pasa la validación "integer" de UpdateEventosRequest
        // si se manda tal cual — se normaliza a null real.
        if (($payload['organizador_id'] ?? null) === '') {
            $payload['organizador_id'] = null;
        }

        // Cargo de servicio (11/08/2026) — el campo del formulario es un
        // porcentaje humano ("5"), la API espera una fracción ("0.05").
        // No se manda nada si el campo no vino (la vista solo lo muestra
        // a super_admin) — ApiRestEvent igual lo rechazaría con 403 si
        // llegara de alguien más, esto solo evita el viaje de red.
        if ($request->filled('feePctPorcentaje')) {
            $payload['feePct'] = round(((float) $request->input('feePctPorcentaje')) / 100, 4);

            // Cargo de servicio sobre talleres (19/08/2026) — mismo campo
            // super_admin-only, vive en el mismo bloque @if de la vista
            // que feePctPorcentaje, así que se usa esa presencia como
            // señal de "esta request sí incluye esta sección". El
            // checkbox solo llega si está tildado; si no vino, forzamos
            // false para que destildear también persista (mismo criterio
            // que aceptaUsd/talleresConCosto).
            $payload['feeIncluyeTalleres'] = $request->boolean('feeIncluyeTalleres');
        }

        // Inscripción en BOB y USD (18/08/2026) — ver
        // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. El checkbox
        // del formulario solo llega si el usuario lo tildó; si no viene,
        // forzamos false para que uncheckear también persista (en update
        // no se distingue "no mandó el campo" de "lo destildó"). Ambos
        // tipos de admin pueden mandarlo (a diferencia de feePct, que es
        // super_admin-only).
        $payload['aceptaUsd'] = $request->boolean('aceptaUsd');

        // Precio USD fijo (19/08/2026) — ver brain/PLAN-PRECIO-USD-FIJO-19082026.md.
        // Mismo motivo que aceptaUsd/talleresConCosto (destildear también persiste).
        $payload['usdPrecioFijo'] = $request->boolean('usdPrecioFijo');

        // Congresos con talleres (19/08/2026) — mismo motivo que aceptaUsd:
        // sin el checkbox marcado no se distingue "no vino el campo" de "lo
        // destildaron", así que se manda siempre (forzando false si no
        // vino tildado) para que destildear también persista.
        $payload['talleresConCosto'] = $request->boolean('talleresConCosto');

        // "Pagar en el evento (efectivo)" al agregar un taller a una
        // inscripción pagada — configurable por evento (02/09/2026), mismo
        // motivo que aceptaUsd: se manda siempre para que destildear
        // también persista.
        $payload['forzarQrPagoAdicional'] = $request->boolean('forzarQrPagoAdicional');

        // Purgar datos de Persona/Participante en inscripciones canceladas
        // (01/09/2026) — mismo motivo que aceptaUsd, se manda siempre para
        // que destildear también persista. A diferencia de los de arriba,
        // este nace TILDADO en edit.blade.php (default true, "mantener" es
        // lo seguro) — no tiene checkbox en create.blade.php a propósito,
        // así un evento nuevo usa el default `true` de la columna sin que
        // este controller lo pise.
        $payload['mantenerDatosPersona'] = $request->boolean('mantenerDatosPersona');

        // Orden de secciones en la página del evento (25/08/2026) — la
        // vista manda 9 inputs numéricos, uno por bloque
        // (orden[description], orden[calendar], ...); acá se ordenan por
        // su valor y se convierte a la lista de claves ordenada que
        // espera ApiRestEvent (EventoService::update() -> secciones_orden).
        // El backend revalida que sean exactamente esas 9 claves.
        $ordenSecciones = $request->input('orden', []);
        if (is_array($ordenSecciones) && count($ordenSecciones)) {
            asort($ordenSecciones);
            $payload['seccionesOrden'] = array_keys($ordenSecciones);
        }

        $response = $client->forward('PUT', "/event/{$evento}", body: $payload);

        // Mejora de visualización (12/08/2026) — '#datos' es la primera
        // pestaña de todos modos (fallback si no hay hash), pero se deja
        // explícito por si el orden de pestañas cambia más adelante.
        if (!$response || !$response->json('success')) {
            return redirect(route('eventos.edit', $evento) . '#datos')->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect(route('eventos.edit', $evento) . '#datos')->with('status', 'Evento actualizado correctamente.');
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
