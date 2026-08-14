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
                <th class="px-4 py-2">Staff asignado</th>
                <th class="px-4 py-2">Ponentes vinculados</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sesiones as $sesion)
                @php
                    $staffAsignadoIds = collect($sesion['staff_asignado'] ?? [])->pluck('id')->all();
                    $staffDisponibleParaEsta = collect($staffDisponible)->reject(fn ($p) => in_array($p['id'], $staffAsignadoIds));
                    // Ponentes vinculados (13/08/2026, mismo día) — ver
                    // brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md
                    $ponentesVinculadosIds = collect($sesion['ponentes_vinculados'] ?? [])->pluck('id')->all();
                    $ponentesDisponiblesParaEsta = collect($ponentesDisponibles)->reject(fn ($p) => in_array($p['id'], $ponentesVinculadosIds));
                @endphp
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2 font-semibold">{{ $sesion['titulo'] }}</td>
                    <td class="px-4 py-2">{{ $sesion['ponente'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $sesion['sala'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($sesion['fecha'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">{{ substr($sesion['hora_inicio'], 0, 5) }}–{{ substr($sesion['hora_fin'], 0, 5) }}</td>
                    <td class="px-4 py-2">{{ $sesion['cupo'] ?? 'Sin límite' }}</td>
                    <td class="px-4 py-2">
                        {{-- Asignación de staff/ayudantes (13/08/2026) — ver
                             brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md --}}
                        <div class="flex flex-wrap gap-1 mb-2">
                            @forelse ($sesion['staff_asignado'] ?? [] as $staff)
                                <span class="inline-flex items-center gap-1 bg-slate-100 rounded-full px-2 py-0.5 text-xs">
                                    {{ $staff['nombre'] }} {{ $staff['apellido'] }}
                                    <form method="POST" action="{{ route('sesiones.staff.destroy', [$evento['id'], $sesion['id'], $staff['id']]) }}"
                                          class="inline" onsubmit="return confirm('¿Desasignar a {{ $staff['nombre'] }} de esta sesión?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="rol" value="staff">
                                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Desasignar">&times;</button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">Sin ayudantes asignados</span>
                            @endforelse
                        </div>
                        @if ($staffDisponibleParaEsta->isNotEmpty())
                            <form method="POST" action="{{ route('sesiones.staff.store', [$evento['id'], $sesion['id']]) }}" class="flex gap-1">
                                @csrf
                                <input type="hidden" name="rol" value="staff">
                                <select name="participante_id" required class="border border-slate-300 rounded px-1.5 py-1 text-xs">
                                    <option value="">+ Asignar ayudante…</option>
                                    @foreach ($staffDisponibleParaEsta as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['nombre'] }} {{ $p['apellido'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-brand-600 hover:underline text-xs whitespace-nowrap">Asignar</button>
                            </form>
                        @elseif (empty($staffDisponible))
                            <span class="text-xs text-slate-400">No hay participantes de un form_type "Staff" inscritos.</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        {{-- Vinculación de ponentes (13/08/2026, mismo día) — ver
                             brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.
                             Complementa el campo de texto libre "Ponente" (columna 2),
                             no lo reemplaza. --}}
                        <div class="flex flex-wrap gap-1 mb-2">
                            @forelse ($sesion['ponentes_vinculados'] ?? [] as $ponente)
                                <span class="inline-flex items-center gap-1 bg-slate-100 rounded-full px-2 py-0.5 text-xs">
                                    {{ $ponente['nombre'] }} {{ $ponente['apellido'] }}
                                    <form method="POST" action="{{ route('sesiones.staff.destroy', [$evento['id'], $sesion['id'], $ponente['id']]) }}"
                                          class="inline" onsubmit="return confirm('¿Desvincular a {{ $ponente['nombre'] }} de esta sesión?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="rol" value="ponente">
                                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Desvincular">&times;</button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">Sin ponentes vinculados</span>
                            @endforelse
                        </div>
                        @if ($ponentesDisponiblesParaEsta->isNotEmpty())
                            <form method="POST" action="{{ route('sesiones.staff.store', [$evento['id'], $sesion['id']]) }}" class="flex gap-1">
                                @csrf
                                <input type="hidden" name="rol" value="ponente">
                                <select name="participante_id" required class="border border-slate-300 rounded px-1.5 py-1 text-xs">
                                    <option value="">+ Vincular ponente…</option>
                                    @foreach ($ponentesDisponiblesParaEsta as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['nombre'] }} {{ $p['apellido'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-brand-600 hover:underline text-xs whitespace-nowrap">Vincular</button>
                            </form>
                        @elseif (empty($ponentesDisponibles))
                            <span class="text-xs text-slate-400">No hay participantes de un form_type "Ponente" inscritos.</span>
                        @endif
                    </td>
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
                <tr><td colspan="9" class="px-4 py-6 text-center text-slate-400">No hay sesiones registradas todavía.</td></tr>
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
