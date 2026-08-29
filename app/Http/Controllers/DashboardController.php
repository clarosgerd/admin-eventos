<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ApiRestEventClient $client): View
    {
        $admin = session('admin_user');
        $search = trim((string) $request->query('search', ''));

        if ($admin['rol'] === 'super_admin') {
            $query = ['per_page' => 20, 'page' => (int) $request->query('page', 1)];
            if ($search !== '') {
                $query['search'] = $search;
            }

            $response = $client->forward('GET', '/event', query: $query);
            $eventos = $response?->json('eventos') ?? [];
            $pagination = $response?->json('pagination');
        } else {
            // Admin de evento asignado a varios eventos (28/08/2026) — ver
            // ApiRestEvent/brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md.
            // 'eventoIds' es evento principal + adicionales, deduplicado
            // (ver AdminUser::eventoIds() del lado de ApiRestEvent); un
            // admin sin eventos adicionales sigue viendo exactamente su
            // único evento, como antes.
            $eventos = [];
            foreach ($admin['eventoIds'] ?? [] as $eventoId) {
                $response = $client->forward('GET', '/event/'.$eventoId);
                $evento = $response?->json('eventos');
                if ($evento) {
                    $eventos[] = $evento;
                }
            }
            $pagination = null;
        }

        return view('dashboard', compact('eventos', 'pagination', 'admin', 'search'));
    }
}
