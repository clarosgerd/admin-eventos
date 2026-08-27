@extends('layouts.app')

@section('title', 'Detalle de turno — Cierres de caja')

@section('content')
@php
    $tipoLabels = [
        'inscripcion_nueva' => 'Inscripción nueva',
        'cobro_pendiente'   => 'Cobro pendiente',
        'edicion_pagada'    => 'Edición pagada',
    ];
@endphp

<div class="mb-4">
    <a href="{{ route('caja.cierres', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a cierres de caja</a>
</div>
<h1 class="text-lg font-bold mb-1">Detalle de turno</h1>
<p class="text-sm text-slate-500 mb-5">{{ $evento['name'] ?? '' }} — {{ $turno['cajeroNombre'] ?? ('#'.$turno['cajeroId']) }}</p>

<div class="bg-white rounded-lg shadow p-4 mb-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
    <div>
        <p class="text-xs text-slate-500">Abierto</p>
        <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($turno['abiertoAt'])->format('d/m/Y H:i') }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Cerrado</p>
        <p class="font-semibold">{{ $turno['cerradoAt'] ? \Illuminate\Support\Carbon::parse($turno['cerradoAt'])->format('d/m/Y H:i') : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Fondo inicial</p>
        <p class="font-semibold">{{ number_format($turno['fondoInicial'], 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Estado</p>
        <span class="text-xs px-2 py-0.5 rounded-full {{ $turno['estado'] === 'abierto' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">
            {{ $turno['estado'] }}
        </span>
    </div>
    <div>
        <p class="text-xs text-slate-500">Esperado</p>
        <p class="font-semibold">{{ $turno['montoEsperado'] !== null ? number_format($turno['montoEsperado'], 2) : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Contado</p>
        <p class="font-semibold">{{ $turno['montoContado'] !== null ? number_format($turno['montoContado'], 2) : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Diferencia</p>
        <p class="font-semibold {{ ($turno['diferencia'] ?? 0) < 0 ? 'text-red-600' : (($turno['diferencia'] ?? 0) > 0 ? 'text-amber-600' : '') }}">
            {{ $turno['diferencia'] !== null ? number_format($turno['diferencia'], 2) : '—' }}
        </p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Total movimientos</p>
        <p class="font-semibold">{{ number_format($turno['totalCobrado'] ?? 0, 2) }}</p>
    </div>
</div>

<h2 class="font-bold text-sm text-brand-600 mb-2">Movimientos ({{ count($turno['movimientos'] ?? []) }})</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Hora</th>
                <th class="px-3 py-2">Tipo</th>
                <th class="px-3 py-2">Inscripción</th>
                <th class="px-3 py-2">Método</th>
                <th class="px-3 py-2 text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($turno['movimientos'] ?? [] as $m)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($m['createdAt'])->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $tipoLabels[$m['tipo']] ?? $m['tipo'] }}</td>
                    <td class="px-3 py-2">
                        @if ($m['registrationReferencia'])
                            <a href="{{ route('caja.eticket', [$evento['id'], $m['registrationReferencia']]) }}" class="text-brand-600 hover:underline">{{ $m['registrationReferencia'] }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $m['metodoPago'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-right {{ $m['monto'] < 0 ? 'text-red-600 font-semibold' : '' }}">
                        {{ number_format($m['monto'], 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Este turno todavía no tiene movimientos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
