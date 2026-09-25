<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SmartStand (25/09/2026) — pantalla "Empresas expositoras" de un evento:
 * lista ordenada por leads capturados (= el ranking de atracción del
 * brochure), alta manual, edición, desactivar, reenviar credenciales y el
 * detalle/dashboard de cada empresa. Proxy delgado hacia ApiRestEvent
 * (molde: NumeracionRangoController): la autorización real —que la empresa
 * pertenezca a un evento al que el admin tiene acceso— la aplica ApiRestEvent
 * (AuthorizesEventoScope::assertCanWriteEvento()); acá `{evento}` solo sirve
 * de contexto de navegación, más el chequeo de sesión de las pantallas.
 */
class EmpresaExpositoraController extends Controller
{
    use AuthorizesEventoScope;

    public function index(int $evento, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $listado = $client->forward('GET', "/event/{$evento}/empresas-expositoras");
        $eventoResponse = $client->forward('GET', "/event/{$evento}");
        $datosEvento = $eventoResponse?->json('eventos') ?? [];

        // Solo las categorías de tipos de formulario "es expositor" son
        // tamaños de stand; si ninguna lo es (evento recién configurado),
        // se ofrecen todas para no dejar el selector vacío.
        $formTypeExpositorIds = collect($datosEvento['formTypes'] ?? [])
            ->filter(fn ($ft) => $ft['esExpositor'] ?? false)
            ->pluck('id');
        $categorias = collect($datosEvento['categories'] ?? []);
        $categoriasDeStand = $categorias->filter(fn ($c) => $formTypeExpositorIds->contains($c['formulario_id'] ?? null));

        return view('eventos.expositores', [
            'evento'      => ['id' => $evento, 'name' => $datosEvento['name'] ?? "Evento #{$evento}"],
            'expositores' => $listado?->json('expositores') ?? [],
            'categorias'  => ($categoriasDeStand->isNotEmpty() ? $categoriasDeStand : $categorias)->values()->all(),
            'apiCaida'    => $listado === null,
        ]);
    }

    public function store(Request $request, int $evento, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('POST', "/event/{$evento}/empresas-expositoras", body: $this->payload($request, incluirActivo: false));

        if (!$response || !$response->json('success')) {
            return redirect()->route('expositores.index', $evento)->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('expositores.index', $evento)->with('status', $response->json('message') ?? 'Expositor creado correctamente.');
    }

    public function update(Request $request, int $evento, int $empresa, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('PUT', "/empresas-expositoras/{$empresa}", body: $this->payload($request, incluirActivo: true));

        if (!$response || !$response->json('success')) {
            return redirect()->route('expositores.index', $evento)->withErrors($this->extractErrors($response));
        }

        return redirect()->route('expositores.index', $evento)->with('status', 'Expositor actualizado correctamente.');
    }

    public function destroy(int $evento, int $empresa, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('DELETE', "/empresas-expositoras/{$empresa}");

        if (!$response || !$response->json('success')) {
            return redirect()->route('expositores.index', $evento)->withErrors($this->extractErrors($response));
        }

        return redirect()->route('expositores.index', $evento)->with('status', 'Expositor eliminado correctamente.');
    }

    public function reenviar(int $evento, int $empresa, ApiRestEventClient $client): RedirectResponse
    {
        $this->assertCanViewEvento($evento);

        // Puede tardar (SMTP síncrono del lado de ApiRestEvent): más tiempo y
        // sin reintentos — un reintento mandaría un segundo correo con otra
        // contraseña, dejando inválida la primera.
        $response = $client->forward('POST', "/empresas-expositoras/{$empresa}/reenviar-credenciales", timeoutSeconds: 30, retries: 0);

        if (!$response || !$response->json('success')) {
            return redirect()->route('expositores.index', $evento)->withErrors($this->extractErrors($response));
        }

        return redirect()->route('expositores.index', $evento)->with('status', $response->json('message') ?? 'Credenciales reenviadas.');
    }

    public function show(int $evento, int $empresa, ApiRestEventClient $client): View
    {
        $this->assertCanViewEvento($evento);

        $response = $client->forward('GET', "/empresas-expositoras/{$empresa}/dashboard");
        abort_if(!$response || !$response->successful(), $response?->status() === 403 ? 403 : 404, 'No se encontró la empresa expositora.');

        return view('eventos.expositor-detalle', [
            'evento'    => ['id' => $evento],
            'expositor' => $response->json('expositor') ?? [],
            'datos'     => $response->json('data') ?? [],
        ]);
    }

    private function payload(Request $request, bool $incluirActivo): array
    {
        $payload = [
            'nombre'       => trim((string) $request->input('nombre')),
            'email'        => trim((string) $request->input('email')),
            'stand'        => $request->filled('stand') ? trim((string) $request->input('stand')) : null,
            // Un <select> sin elegir manda '' — la API lo rechaza contra `integer`.
            'categoria_id' => $request->filled('categoria_id') ? (int) $request->input('categoria_id') : null,
        ];

        if ($incluirActivo) {
            // Checkbox: destildado no viaja, por eso se manda siempre.
            $payload['activo'] = $request->boolean('activo');
        }

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
