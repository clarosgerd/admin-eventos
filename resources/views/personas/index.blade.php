@extends('layouts.app')

@section('title', 'Personas — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Personas</h1>
    <a href="{{ route('personas.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        + Nueva persona
    </a>
</div>

<form method="GET" action="{{ route('personas.index') }}" class="mb-4 flex gap-2">
    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre, apellido, documento o correo…"
           class="w-full max-w-sm border border-slate-300 rounded-md px-3 py-2 text-sm">
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        Buscar
    </button>
    @if ($search !== '')
        <a href="{{ route('personas.index') }}" class="text-sm text-slate-500 hover:underline self-center">
            Limpiar
        </a>
    @endif
</form>

@if (empty($personas))
    <p class="text-sm text-slate-600">No hay personas para mostrar.</p>
@else
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Apellido</th>
                    <th class="px-4 py-2">Documento</th>
                    <th class="px-4 py-2">Correo</th>
                    <th class="px-4 py-2">Ciudad</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($personas as $persona)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-semibold">{{ $persona['nombre'] }}</td>
                        <td class="px-4 py-2">{{ $persona['apellido'] }}</td>
                        <td class="px-4 py-2">{{ $persona['tipoDocumento'] }} {{ $persona['numeroDocumento'] }}</td>
                        <td class="px-4 py-2">{{ $persona['correo'] ?? $persona['email'] }}</td>
                        <td class="px-4 py-2">{{ $persona['ciudad'] }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('personas.edit', $persona['id']) }}" class="text-xs text-brand-600 hover:underline">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('personas.destroy', $persona['id']) }}" class="inline"
                                  onsubmit="return confirm('¿Eliminar a {{ $persona['nombre'] }} {{ $persona['apellido'] }}?\n\nEsto borra la cuenta de forma permanente.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($pagination)
        <div class="flex justify-between items-center mt-3">
            <p class="text-xs text-slate-500">
                Página {{ $pagination['current_page'] }} de {{ $pagination['last_page'] }}
                ({{ $pagination['total'] }} personas en total)
            </p>
            @if ($pagination['last_page'] > 1)
                <div class="flex gap-2">
                    @if ($pagination['current_page'] > 1)
                        <a href="{{ route('personas.index', ['page' => $pagination['current_page'] - 1, 'search' => $search ?: null]) }}"
                           class="text-xs bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">
                            « Anterior
                        </a>
                    @endif
                    @if ($pagination['current_page'] < $pagination['last_page'])
                        <a href="{{ route('personas.index', ['page' => $pagination['current_page'] + 1, 'search' => $search ?: null]) }}"
                           class="text-xs bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">
                            Siguiente »
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif
@endif
@endsection
