<?php /* Catálogo de género de participante — respalda participantes.genero (ENUM). NO es Sexo (esa tabla es de categories.sexo_id). Ver PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md. */ ?>
@extends('layouts.app')

@section('title', 'Género — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Género</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
        </p>
    </div>
</div>

<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded-md mb-5 text-sm">
    El nombre tiene que coincidir exacto con <strong>Masculino</strong>, <strong>Femenino</strong> u <strong>Otro</strong> — la base de datos todavía valida el género de un participante contra esos 3 valores. Para ocultar una opción sin romper inscripciones, destildá "Activo" en vez de cambiar el nombre.
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($generos as $genero)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $genero['nombre'] }}" required
                               form="genero-form-{{ $genero['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="genero-form-{{ $genero['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $genero['activo'] ? 'checked' : '' }}
                               form="genero-form-{{ $genero['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('catalogos.generos.update', $genero['id']) }}" id="genero-form-{{ $genero['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="genero-form-{{ $genero['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('catalogos.generos.destroy', $genero['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este género? Solo se puede si no está en uso.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">No hay géneros registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo género</h2>
    <form method="POST" action="{{ route('catalogos.generos.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear género
        </button>
    </form>
</div>
@endsection
