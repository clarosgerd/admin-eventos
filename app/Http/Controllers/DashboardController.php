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
            $response = $client->forward('GET', '/event/'.$admin['evento_id']);
            $evento = $response?->json('eventos');
            $eventos = $evento ? [$evento] : [];
            $pagination = null;
        }

        return view('dashboard', compact('eventos', 'pagination', 'admin', 'search'));
    }
}
