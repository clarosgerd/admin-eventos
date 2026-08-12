<?php /* Agenda y sesiones de congreso — config estructural. Ver elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('layouts.app')

@section('title', 'Sesiones de congreso — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Sesiones de congreso: {{ $evento['name'] }}</h1>
        <p class="text-sm text-slate-500">Ponencias/talleres con sala, horario y cupo propios.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('sesiones.reporte', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
            Ver reporte de asistencia →
        </a>
        <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
            ← Volver al evento
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Título</th>
                <th class="px-4 py-2">Ponente</th>
                <th class="px-4 py-2">Sala</th>
                <th class="px-4 py-2">Fecha</th>
                <th class="px-4 py-2">Horario</th>
                <th class="px-4 py-2">Cupo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sesiones as $sesion)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 font-semibold">{{ $sesion['titulo'] }}</td>
                    <td class="px-4 py-2">{{ $sesion['ponente'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $sesion['sala'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($sesion['fecha'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">{{ substr($sesion['hora_inicio'], 0, 5) }}–{{ substr($sesion['hora_fin'], 0, 5) }}</td>
                    <td class="px-4 py-2">{{ $sesion['cupo'] ?? 'Sin límite' }}</td>
                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('sesiones.acreditacion.index', [$evento['id'], $sesion['id']]) }}" class="text-brand-600 hover:underline">
                            Acreditar
                        </a>
                        <form method="POST" action="{{ route('sesiones.destroy', [$evento['id'], $sesion['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta sesión? También se borra su asistencia registrada.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No hay sesiones registradas todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nueva sesión</h2>
    <form method="POST" action="{{ route('sesiones.store', $evento['id']) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Título</label>
            <input type="text" name="titulo" value="{{ old('titulo') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Ponente</label>
                <input type="text" name="ponente" value="{{ old('ponente') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Cargo del ponente</label>
                <input type="text" name="ponente_cargo" value="{{ old('ponente_cargo') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Sala / track</label>
                <input type="text" name="sala" value="{{ old('sala') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Cupo (vacío = sin límite)</label>
                <input type="number" name="cupo" min="1" value="{{ old('cupo') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Fecha</label>
                <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hora inicio</label>
                <input type="time" name="hora_inicio" value="{{ old('hora_inicio') }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hora fin</label>
                <input type="time" name="hora_fin" value="{{ old('hora_fin') }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="requiere_inscripcion" value="1">
            Requiere inscripción separada <span class="text-slate-400">(config para una fase futura, todavía sin efecto)</span>
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear sesión
        </button>
    </form>
</div>
@endsection
