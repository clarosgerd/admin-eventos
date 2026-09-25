<?php /* SmartStand (25/09/2026) — detalle de una empresa expositora: estadísticas de
     los contactos (leads) que capturó su staff. Solo usa datos estructurados
     (fecha de captura, ciudad del participante, calificación); el mapa de
     especialidades queda fuera hasta normalizar ese dato. */ ?>
@extends('layouts.app')

@section('title', ($expositor['nombre'] ?? 'Expositor') . ' — Empresas expositoras — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('expositores.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver a empresas expositoras</a>
    <h1 class="text-lg font-bold mt-1">{{ $expositor['nombre'] ?? 'Expositor' }}</h1>
    <p class="text-sm text-slate-600">
        {{ $expositor['email'] ?? '' }}
        @if (!empty($expositor['tamanoStand'])) · Stand {{ $expositor['tamanoStand'] }} @endif
        @if (!empty($expositor['stand'])) · {{ $expositor['stand'] }} @endif
        @if (!($expositor['activo'] ?? true)) · <span class="text-red-600 font-semibold">Desactivada</span> @endif
    </p>
</div>

<div class="grid grid-cols-2 gap-3 mb-6 max-w-md">
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-slate-500">Contactos capturados</p>
        <p class="text-2xl font-bold tabular-nums">{{ $datos['totalLeads'] ?? 0 }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-slate-500">Calificación promedio</p>
        <p class="text-2xl font-bold tabular-nums">
            {{ isset($datos['calificacionPromedio']) ? number_format($datos['calificacionPromedio'], 1) : '—' }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <section class="bg-white rounded-lg shadow p-5">
        <h2 class="font-bold text-sm mb-3">Contactos por día</h2>
        @forelse ($datos['porDia'] ?? [] as $fila)
            <div class="flex justify-between text-sm border-t border-slate-100 py-1.5 first:border-0">
                <span>{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</span>
                <span class="font-semibold tabular-nums">{{ $fila['total'] }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Todavía no capturó contactos.</p>
        @endforelse
    </section>

    <section class="bg-white rounded-lg shadow p-5">
        <h2 class="font-bold text-sm mb-3">Origen de los visitantes (ciudad)</h2>
        @forelse ($datos['porCiudad'] ?? [] as $fila)
            <div class="flex justify-between text-sm border-t border-slate-100 py-1.5 first:border-0">
                <span>{{ $fila['ciudad'] }}</span>
                <span class="font-semibold tabular-nums">{{ $fila['total'] }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Todavía no capturó contactos.</p>
        @endforelse
    </section>
</div>
@endsection
