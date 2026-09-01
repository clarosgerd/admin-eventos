<?php /* Congresos con talleres (18/08/2026) — ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. */ ?>
@extends('layouts.app')

@section('title', 'Talleres — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Talleres: {{ $evento['name'  ] }}</h1>
        <p class="text-sm text-slate-500">Agrupación de sesiones con modalidad y precio propio. Las sesiones del taller se configuran en <a href="{{ route('sesiones.index', $evento['id']) }}" class="text-brand-600 hover:underline">Sesiones</a> (campo "Taller").</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('sesiones.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
            ← Volver a sesiones
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
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Modalidad</th>
                <th class="px-4 py-2">Precio</th>
                <th class="px-4 py-2">Precio USD</th>
                <th class="px-4 py-2">Sesiones</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2">Permite inscripción</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($talleres as $taller)
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2 font-semibold">
                        {{ $taller['nombre'] }}
                        @if (!empty($taller['descripcion']))
                            <div class="text-xs text-slate-500 font-normal mt-1">{{ $taller['descripcion'] }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full {{ $taller['modalidad'] === 'REQUIRED' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700' }}">
                            {{ $taller['modalidad'] === 'REQUIRED' ? 'Obligatorio' : 'Opcional' }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        {{ $taller['precio'] !== null ? 'Bs. '.number_format((float) $taller['precio'], 2) : '—' }}
                    </td>
                    <td class="px-4 py-2">
                        {{-- Este endpoint (index) devuelve el modelo Eloquent crudo,
                             no TallerResource — por eso acá es snake_case (price_usd),
                             a diferencia de lo que ve el frontend público. --}}
                        {{ ($taller['price_usd'] ?? null) !== null ? 'US$ '.number_format((float) $taller['price_usd'], 2) : '—' }}
                    </td>
                    <td class="px-4 py-2">
                        <span class="text-xs text-slate-600">
                            {{ count($taller['sesiones'] ?? []) }} sesión(es)
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        {{ $taller['activo'] ? 'Sí' : 'No' }}
                    </td>
                    <td class="px-4 py-2">
                        {{-- Deshabilitar un taller sin ocultarlo (28/08/2026) —
                             distinto de "Activo": esto no afecta si el taller
                             se ve, solo si se puede elegir. --}}
                        @if ($taller['permite_inscripcion'] ?? true)
                            Sí
                        @else
                            <span class="text-amber-600 font-semibold">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                        <a href="#edit-taller-{{ $taller['id'] }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('talleres.destroy', [$evento['id'], $taller['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este taller? Sus sesiones quedarán como ponencias sueltas.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No hay talleres registrados todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Formularios de edición inline --}}
@foreach ($talleres as $taller)
<div id="edit-taller-{{ $taller['id'] }}" class="bg-white rounded-lg shadow p-4 mb-4 max-w-lg">
    <h2 class="font-semibold mb-3">Editar: {{ $taller['nombre'] }}</h2>
    <form method="POST" action="{{ route('talleres.update', [$evento['id'], $taller['id']]) }}" class="space-y-3">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $taller['nombre']) }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Descripción</label>
            <textarea name="descripcion" rows="2" class="border border-slate-300 rounded px-2 py-1.5 w-full">{{ old('descripcion', $taller['descripcion']) }}</textarea>
        </div>
        <div class="grid grid-cols-4 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Modalidad</label>
                <select name="modalidad" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
                    <option value="OPTIONAL" @selected(old('modalidad', $taller['modalidad']) === 'OPTIONAL')>Opcional</option>
                    <option value="REQUIRED" @selected(old('modalidad', $taller['modalidad']) === 'REQUIRED')>Obligatorio</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio</label>
                <input type="number" step="0.01" min="0" name="precio" value="{{ old('precio', $taller['precio']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio USD</label>
                <input type="number" step="0.01" min="0" name="price_usd" value="{{ old('price_usd', $taller['price_usd'] ?? '') }}"
                       placeholder="Solo modo Precio USD fijo"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Orden</label>
                <input type="number" min="0" name="orden" value="{{ old('orden', $taller['orden']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $taller['activo']))>
            Activo
        </label>
        {{-- Deshabilitar un taller sin ocultarlo (28/08/2026) — a diferencia
             de "Activo" (que también lo oculta), esto solo bloquea que se
             elija: el taller sigue apareciendo en la lista del participante,
             marcado como no disponible.

             Input hidden con value=0 antes del checkbox (28/08/2026) — un
             <input type="checkbox"> destildado no manda NADA en el POST,
             así que el controller nunca puede distinguir "destildado" de
             "el campo ni se tocó" y el default (`?? true`) gana siempre.
             Con el hidden, destildar el checkbox igual manda
             permite_inscripcion=0 (el checkbox, si está tildado, pisa el
             hidden porque el navegador manda ambos y PHP se queda con el
             último valor del mismo nombre). --}}
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="permite_inscripcion" value="0">
            <input type="checkbox" name="permite_inscripcion" value="1" @checked(old('permite_inscripcion', $taller['permite_inscripcion'] ?? true))>
            Permite inscripción <span class="text-xs text-slate-500">(destildar deja el taller visible pero no seleccionable)</span>
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Guardar cambios
        </button>
    </form>
</div>
@endforeach

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nuevo taller</h2>
    <form method="POST" action="{{ route('talleres.store', $evento['id']) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Descripción</label>
            <textarea name="descripcion" rows="2" class="border border-slate-300 rounded px-2 py-1.5 w-full">{{ old('descripcion') }}</textarea>
        </div>
        <div class="grid grid-cols-4 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Modalidad</label>
                <select name="modalidad" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
                    <option value="OPTIONAL" @selected(old('modalidad', 'OPTIONAL') === 'OPTIONAL')>Opcional</option>
                    <option value="REQUIRED" @selected(old('modalidad') === 'REQUIRED')>Obligatorio</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio</label>
                <input type="number" step="0.01" min="0" name="precio" value="{{ old('precio') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Precio USD</label>
                <input type="number" step="0.01" min="0" name="price_usd" value="{{ old('price_usd') }}"
                       placeholder="Solo modo Precio USD fijo"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Orden</label>
                <input type="number" min="0" name="orden" value="{{ old('orden', 0) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', true))>
            Activo
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="permite_inscripcion" value="0">
            <input type="checkbox" name="permite_inscripcion" value="1" @checked(old('permite_inscripcion', true))>
            Permite inscripción <span class="text-xs text-slate-500">(destildar deja el taller visible pero no seleccionable)</span>
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear taller
        </button>
    </form>
</div>
@endsection