<?php /* SmartStand fase 4 (26/09/2026) — pasaporte médico: sorteo general entre los asistentes
     que visitaron (fueron capturados por) al menos N stands distintos. Ver PasaporteController. */ ?>
@extends('layouts.app')

@section('title', 'Pasaporte médico — ' . $evento['name'] . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    <h1 class="text-lg font-bold mt-1">Pasaporte médico</h1>
    <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    <p class="text-xs text-slate-400 mt-1 max-w-2xl">
        Sorteo general del congreso entre los asistentes que fueron visitados por un mínimo de stands distintos
        (cada expositor los registra al escanear su gafete). Cada persona participa una sola vez y quien ya ganó
        no vuelve a entrar. Cada sorteo queda registrado.
    </p>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ session('status') }}
    </div>
@endif

@if ($apiCaida || !$pasaporte)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded-md mb-4 text-sm">
        No se pudo obtener el estado del pasaporte. Reintenta en unos segundos.
    </div>
@else
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 max-w-3xl">
        <div class="bg-white rounded-lg shadow p-3">
            <p class="text-xs text-slate-500">Empresas expositoras</p>
            <p class="text-xl font-bold">{{ $pasaporte['empresasTotal'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-3">
            <p class="text-xs text-slate-500">Asistentes capturados</p>
            <p class="text-xl font-bold">{{ $pasaporte['asistentesConLeads'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-3">
            <p class="text-xs text-slate-500">Mínimo de stands</p>
            <p class="text-xl font-bold">{{ $pasaporte['minStands'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-3 border-2 border-brand-600">
            <p class="text-xs text-slate-500">Califican al sorteo</p>
            <p class="text-xl font-bold">{{ $pasaporte['elegibles'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 max-w-5xl mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-bold mb-2">Sortear un premio</h2>
            <form method="POST" action="{{ route('pasaporte.sortear', $evento['id']) }}" class="space-y-3"
                  onsubmit="return confirm('¿Sortear este premio ahora? El resultado queda registrado y no se puede deshacer.');">
                @csrf
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="premio">Premio</label>
                    <input type="text" id="premio" name="premio" maxlength="150" required value="{{ old('premio') }}"
                           placeholder="Ej.: Tablet, pasaje a congreso…"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="w-56">
                    <label class="block text-xs text-slate-500 mb-1" for="min_stands">Stands mínimos <span class="text-slate-400">(vacío = {{ $pasaporte['minStands'] }})</span></label>
                    <input type="number" id="min_stands" name="min_stands" min="1" max="50" value="{{ old('min_stands') }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <button type="submit" @disabled($pasaporte['elegibles'] < 1)
                        class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md disabled:opacity-40 disabled:cursor-not-allowed">
                    Sortear
                </button>
                @if ($pasaporte['elegibles'] < 1)
                    <p class="text-xs text-slate-500">Todavía nadie alcanza el mínimo con la configuración actual. Puedes bajar los "Stands mínimos" arriba para ver con quién se sortearía.</p>
                @endif
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-bold mb-2">¿A cuántos stands llegó cada asistente?</h2>
            @if (empty($pasaporte['distribucion']))
                <p class="text-sm text-slate-500">Aún no hay contactos capturados.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-500">
                        <tr><th class="py-1">Stands visitados</th><th class="py-1 text-right">Asistentes</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pasaporte['distribucion'] as $fila)
                            <tr class="border-t border-slate-100 {{ $fila['stands'] >= $pasaporte['minStands'] ? 'font-semibold' : 'text-slate-500' }}">
                                <td class="py-1">{{ $fila['stands'] }}</td>
                                <td class="py-1 text-right">{{ $fila['asistentes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-slate-400 mt-2">En negrita: los que alcanzan el mínimo.</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto max-w-5xl mb-6">
        <h2 class="text-sm font-bold px-4 pt-3">Historial de sorteos</h2>
        <table class="w-full text-sm mt-2">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Premio</th>
                    <th class="px-4 py-2">Ganador</th>
                    <th class="px-4 py-2">Contacto</th>
                    <th class="px-4 py-2 text-right">Participaban</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pasaporte['sorteos'] as $sorteo)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($sorteo['sorteadoAt'])->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $sorteo['premio'] }}</td>
                        <td class="px-4 py-2 font-semibold">{{ trim(($sorteo['ganador']['nombre'] ?? '') . ' ' . ($sorteo['ganador']['apellido'] ?? '')) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $sorteo['ganador']['correo'] ?? '' }} {{ $sorteo['ganador']['telefono'] ?? '' }}</td>
                        <td class="px-4 py-2 text-right">{{ $sorteo['candidatosCount'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-3 text-slate-500">Todavía no se hizo ningún sorteo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
