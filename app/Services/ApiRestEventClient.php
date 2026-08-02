<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Llama a ApiRestEvent server-to-server — adaptado de la versión de
 * elascenso-blade (app/Services/ApiRestEventClient.php), mismo contrato de
 * reintentos (ante fallas de red o 5xx, nunca ante 4xx — eso es un error de
 * negocio real). Única diferencia: acá el token de admin vive en la sesión
 * Laravel del panel (login server-side, ver AuthController), no se reenvía
 * desde un header del navegador — el navegador nunca ve el token de
 * ApiRestEvent, solo la cookie de sesión de este panel.
 */
class ApiRestEventClient
{
    /**
     * @return Response|null null si la request nunca llegó a responder
     *                        (falla de red) tras agotar los reintentos.
     */
    public function forward(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $extraHeaders = [],
    ): ?Response {
        $headers = array_merge($this->buildHeaders(), $extraHeaders);
        $retries = (int) config('services.apirestevent.retries', 1);
        $delayMs = (int) config('services.apirestevent.retry_delay', 400);

        $attempt = function () use ($method, $path, $query, $body, $headers): ?Response {
            try {
                $pending = Http::baseUrl(config('services.apirestevent.base_url'))
                    ->withHeaders($headers)
                    ->timeout(15);

                return match (strtoupper($method)) {
                    'GET' => $pending->get($path, $query),
                    'POST' => $pending->post($path, $body ?? []),
                    'PUT' => $pending->put($path, $body ?? []),
                    'PATCH' => $pending->patch($path, $body ?? []),
                    'DELETE' => $pending->delete($path, $body ?? []),
                    default => throw new \InvalidArgumentException("Método no soportado: {$method}"),
                };
            } catch (ConnectionException) {
                return null;
            }
        };

        $response = $attempt();
        while ($retries > 0 && ($response === null || $response->serverError())) {
            usleep($delayMs * 1000);
            $response = $attempt();
            $retries--;
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($token = session('admin_token')) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }
}
