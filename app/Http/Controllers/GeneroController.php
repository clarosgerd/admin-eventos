<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de género de participante (config global, 31/08/2026)
 * — llama a /catalogos/generos de ApiRestEvent, no reimplementa nada acá
 * (ver GeneroController del lado de la API). Solo accesible bajo
 * `admin.superadmin` (ver routes/web.php), mismo criterio que
 * SexoController. Ver PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md.
 *
 * NO es SexoController — ese catálogo respalda categories.sexo_id, sin
 * relación con esto. Este respalda participantes.genero, que sigue siendo
 * un ENUM de base de datos: agregar acá un nombre distinto de
 * Masculino/Femenino/Otro rompe el registro de un participante que lo
 * elija.
 */
class GeneroController extends Controller
{
    public function index(ApiRestEventClient $client): View
    {
        $response = $client->forward('GET', '/catalogos/generos');
        $generos = $response?->json('data') ?? [];

        return view('catalogos.generos', compact('generos'));
    }

    public function store(Request $request, ApiRestEventClient $client): RedirectResponse
    {
        $response = $client->forward('POST', '/catalogos/generos', body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.generos.index')->with('status', 'Género creado correctamente.');
    }

    public function update(Request $request, ApiRestEventClient $client, int $genero): RedirectResponse
    {
        $response = $client->forward('PUT', "/catalogos/generos/{$genero}", body: $request->only('nombre', 'activo'));

        if (!$response || !$response->json('success')) {
            return back()->withInput()->withErrors($this->extractErrors($response));
        }

        return redirect()->route('catalogos.generos.index')->with('status', 'Género actualizado correctamente.');
    }

    public function destroy(ApiRestEventClient $client, int $genero): RedirectResponse
    {
        $response = $client->forward('DELETE', "/catalogos/generos/{$genero}");

        if (!$response || !$response->json('success')) {
            return back()->withErrors(['general' => $response?->json('error') ?? 'No se pudo eliminar el género.']);
        }

        return redirect()->route('catalogos.generos.index')->with('status', 'Género eliminado correctamente.');
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
