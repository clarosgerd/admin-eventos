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
     *
     * $timeoutSeconds / $retries: override para llamadas que de por sí
     * tardan más de los 15s por default — ej. registro-manual/bulk, que
     * dispara un correo real y síncrono por cada inscripción creada
     * (mismo criterio que event/api/registro.php, que usa 20s para una
     * sola). Reintentar una request lenta-pero-exitosa es peor que
     * esperar: el reintento reenvía el mismo CSV completo y todo lo que
     * sí se creó en el primer intento vuelve como "ya existe" — visto
     * en vivo el 05/08/2026 al verificar la carga masiva. Por eso acá
     * el default sigue siendo conservador (15s/reintentos de
     * services.apirestevent.*) pero el caller puede pedir más tiempo y
     * cero reintentos cuando sabe que la operación es lenta por diseño.
     */
    public function forward(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $extraHeaders = [],
        ?int $timeoutSeconds = null,
        ?int $retries = null,
    ): ?Response {
        $headers = array_merge($this->buildHeaders(), $extraHeaders);
        $retries ??= (int) config('services.apirestevent.retries', 1);
        $delayMs = (int) config('services.apirestevent.retry_delay', 400);
        $timeoutSeconds ??= 15;

        $attempt = function () use ($method, $path, $query, $body, $headers, $timeoutSeconds): ?Response {
            try {
                $pending = Http::baseUrl(config('services.apirestevent.base_url'))
                    ->withHeaders($headers)
                    ->timeout($timeoutSeconds);

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

        // Mandamos el mismo token también en X-Auth-Token: el hosting cPanel
        // de UAT (api.inscrito.net) corre PHP vía mod_lsapi, que no reenvía
        // el header Authorization a PHP (ni con CGIPassAuth On — eso solo
        // cubre mod_cgi/mod_fcgid/mod_proxy_fcgi). ApiRestEvent tiene
        // NormalizeAuthTokenHeader como respaldo: arma Authorization desde
        // X-Auth-Token cuando Authorization no llegó. Mismo patrón que ya
        // usa elascenso/event (ver project_uat_mod_lsapi_auth_bug en brain/).
        if ($token = session('admin_token')) {
            $headers['Authorization'] = 'Bearer '.$token;
            $headers['X-Auth-Token'] = $token;
        }

        return $headers;
    }
}
