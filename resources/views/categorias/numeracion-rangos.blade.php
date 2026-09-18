<?php /* Aviso de numeración vs. género/edad real en entrega de kit
     (16/09/2026) — mismo patrón que categorias/periodos.blade.php. Un
     rango de numeración (bib) por color, ligado a esta categoría y
     consultado por género+edad real del participante (no por la categoría
     que eligió) al momento de la entrega física del kit en
     elascenso/delivery. Sin ningún rango cargado, todo sigue funcionando
     exactamente igual que hoy — ver garantía de no-regresión del plan. */ ?>
@extends('layouts.app')

@section('title', 'Rangos de numeración — ' . ($categoria['name'] ?? 'Categoría') . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    @if ($eventoId)
        <a href="{{ route('eventos.edit', $eventoId) }}#categorias" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    @endif
    <h1 class="text-lg font-bold mt-1">Rangos de numeración — {{ $categoria['name'] ?? 'Categoría' }}</h1>
    <p class="text-xs text-slate-400 mt-1">
        Define qué color/rango de números le corresponde a alguien de un género y edad determinados dentro de esta
        categoría. Al momento de la entrega del kit, si el número asignado a un participante no cae en el rango que
        le corresponde según su género/edad real, se avisa (sin bloquear nada). Sin ningún rango cargado acá, no se
        muestra ningún aviso — comportamiento actual, sin cambios.
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Género</th>
                <th class="px-4 py-2">Edad mín.</th>
                <th class="px-4 py-2">Edad máx.</th>
                <th class="px-4 py-2">Color</th>
                <th class="px-4 py-2">N° mín.</th>
                <th class="px-4 py-2">N° máx.</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rangos as $rango)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <select name="genero_id" required form="rango-form-{{ $rango['id'] }}" class="border border-slate-300 rounded px-2 py-1">
                            @foreach ($generos as $genero)
                                <option value="{{ $genero['id'] }}" @selected($rango['genero_id'] == $genero['id'])>{{ $genero['nombre'] }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" min="0" name="edad_min" value="{{ $rango['edad_min'] }}" required
                               form="rango-form-{{ $rango['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-20">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" min="0" name="edad_max" value="{{ $rango['edad_max'] }}" required
                               form="rango-form-{{ $rango['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-20">
                    </td>
                    <td class="px-4 py-2">
                        <input type="color" name="color" value="{{ $rango['color'] }}" form="rango-form-{{ $rango['id'] }}" class="h-8 border border-slate-300 rounded">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" min="0" name="numero_min" value="{{ $rango['numero_min'] }}" required
                               form="rango-form-{{ $rango['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-20">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" min="0" name="numero_max" value="{{ $rango['numero_max'] }}" required
                               form="rango-form-{{ $rango['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-20">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('categorias.rangos.update', $rango['id']) }}" id="rango-form-{{ $rango['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="category_id" value="{{ $categoryId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                        </form>
                        <button type="submit" form="rango-form-{{ $rango['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('categorias.rangos.destroy', $rango['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este rango?')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="category_id" value="{{ $categoryId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Sin rangos cargados — no se muestra ningún aviso en la entrega de kit.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo rango</h2>
    <form method="POST" action="{{ route('categorias.rangos.store', $categoryId) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="evento_id" value="{{ $eventoId }}">
        <div>
            <label class="block text-sm font-medium mb-1">Género</label>
            <select name="genero_id" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
                @foreach ($generos as $genero)
                    <option value="{{ $genero['id'] }}">{{ $genero['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-sm font-medium mb-1">Edad mínima</label>
                <input type="number" name="edad_min" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Edad máxima</label>
                <input type="number" name="edad_max" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Color</label>
            <input type="color" name="color" value="#022858" class="h-8 border border-slate-300 rounded w-full">
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-sm font-medium mb-1">N° mínimo</label>
                <input type="number" name="numero_min" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">N° máximo</label>
                <input type="number" name="numero_max" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Agregar
        </button>
    </form>
</div>
@endsection
