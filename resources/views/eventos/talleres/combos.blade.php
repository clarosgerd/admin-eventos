<?php /* Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller B
     por el precio de 1" o "por $300 fijo". */ ?>
@extends('layouts.app')

@section('title', 'Combos de talleres — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Combos de talleres: {{ $evento['name'] }}</h1>
        <p class="text-sm text-slate-500">
            Ej. "Taller A + Taller B por el precio de 1", o por un precio fijo. El combo se aplica cuando el
            participante elige TODOS los talleres marcados acá, juntos, en la misma inscripción o edición — nunca
            retroactivo sobre un taller que ya pagó de antes.
        </p>
    </div>
    <a href="{{ route('talleres.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a talleres
    </a>
</div>

@php
    $nombrePorTaller = collect($talleres)->keyBy('id')->map(fn ($t) => $t['nombre']);
@endphp

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Talleres</th>
                <th class="px-4 py-2">Precio combo</th>
                <th class="px-4 py-2">Precio USD</th>
                <th class="px-4 py-2">Vigencia</th>
                <th class="px-4 py-2">Estado</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($combos as $combo)
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2 font-semibold">{{ $combo['nombre'] }}</td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($combo['tallerIds'] ?? [] as $tid)
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ $nombrePorTaller[$tid] ?? "#$tid" }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-2">Bs {{ number_format($combo['precioCombo'], 2) }}</td>
                    <td class="px-4 py-2">
                        {{ !is_null($combo['priceUsdCombo'] ?? null) ? 'US$ '.number_format($combo['priceUsdCombo'], 2) : '—' }}
                    </td>
                    <td class="px-4 py-2 text-xs">
                        @if ($combo['fechaDesde'] || $combo['fechaHasta'])
                            {{ $combo['fechaDesde'] ?? '…' }} → {{ $combo['fechaHasta'] ?? '…' }}
                        @else
                            <span class="text-slate-400">Sin vencimiento</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @if (!$combo['activo'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Inactivo</span>
                        @elseif ($combo['vigente'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Vigente</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Fuera de fecha</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                        <a href="#edit-combo-{{ $combo['id'] }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('taller-combos.destroy', [$evento['id'], $combo['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este combo? Las inscripciones ya cobradas con este combo no cambian.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No hay combos de talleres todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@php($tallerIdsUsados = collect($combos)->pluck('tallerIds')->flatten()->countBy())

{{-- Formularios de edición inline --}}
@foreach ($combos as $combo)
<div id="edit-combo-{{ $combo['id'] }}" class="bg-white rounded-lg shadow p-4 mb-4 max-w-lg">
    <h2 class="font-semibold mb-3">Editar: {{ $combo['nombre'] }}</h2>
    <form method="POST" action="{{ route('taller-combos.update', [$evento['id'], $combo['id']]) }}" class="space-y-3">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $combo['nombre']) }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Talleres incluidos (mínimo 2)</label>
            <div class="border border-slate-300 rounded px-3 py-2 space-y-1 max-h-40 overflow-y-auto">
                @foreach ($talleres as $taller)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="taller_ids[]" value="{{ $taller['id'] }}"
                               @checked(in_array($taller['id'], $combo['tallerIds'] ?? []))>
                        {{ $taller['nombre'] }}
                        @if (($tallerIdsUsados[$taller['id']] ?? 0) > 1)
                            <span class="text-xs text-amber-600">(en más de un combo)</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Precio combo</label>
                <input type="number" step="0.01" min="0" name="precio_combo" value="{{ old('precio_combo', $combo['precioCombo']) }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio USD <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="number" step="0.01" min="0" name="price_usd_combo" value="{{ old('price_usd_combo', $combo['priceUsdCombo']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Desde <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_desde" value="{{ old('fecha_desde', $combo['fechaDesde']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hasta <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_hasta" value="{{ old('fecha_hasta', $combo['fechaHasta']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $combo['activo']))>
            Activo
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Guardar cambios
        </button>
    </form>
</div>
@endforeach

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nuevo combo</h2>
    <form method="POST" action="{{ route('taller-combos.store', $evento['id']) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   placeholder="Ej. 2x1 Taller A + Taller B"
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Talleres incluidos (mínimo 2)</label>
            <div class="border border-slate-300 rounded px-3 py-2 space-y-1 max-h-40 overflow-y-auto">
                @forelse ($talleres as $taller)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="taller_ids[]" value="{{ $taller['id'] }}">
                        {{ $taller['nombre'] }}
                        @if (($tallerIdsUsados[$taller['id']] ?? 0) > 0)
                            <span class="text-xs text-amber-600">(ya está en otro combo)</span>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-slate-400">Este evento todavía no tiene talleres cargados.</p>
                @endforelse
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Precio combo</label>
                <input type="number" step="0.01" min="0" name="precio_combo" value="{{ old('precio_combo') }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio USD <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="number" step="0.01" min="0" name="price_usd_combo" value="{{ old('price_usd_combo') }}"
                       placeholder="Solo modo Precio USD fijo"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Desde <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_desde" value="{{ old('fecha_desde') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hasta <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_hasta" value="{{ old('fecha_hasta') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', true))>
            Activo
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear combo
        </button>
    </form>
</div>
@endsection
