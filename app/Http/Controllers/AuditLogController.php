<?php

namespace App\Http\Controllers;

use App\Services\ApiRestEventClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, ApiRestEventClient $client): View
    {
        $query = $request->filled('evento_id') ? ['evento_id' => $request->query('evento_id')] : [];

        $response = $client->forward('GET', '/admin/audit-logs', query: $query);

        return view('auditoria.index', [
            'logs'       => $response?->json('data.data') ?? [],
            'eventoId'   => $request->query('evento_id'),
        ]);
    }
}
