<?php /* SmartStand (25/09/2026) — empresas expositoras de un evento. Las cuentas de
     autoservicio las crea solas el sistema al confirmarse el pago de una inscripción
     con tipo de formulario "Es empresa expositora"; acá el organizador las ve
     ordenadas por contactos capturados (el ranking de atracción entre stands), les
     asigna el número de stand, las desactiva o les reenvía su acceso. */ ?>
@extends('layouts.app')

@section('title', 'Empresas expositoras — ' . $evento['name'] . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    <h1 class="text-lg font-bold mt-1">Empresas expositoras</h1>
    <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    <p class="text-xs text-slate-400 mt-1">
        Las empresas se inscriben desde el sitio público con un tipo de formulario marcado "Es empresa expositora"
        (las categorías de ese formulario son los tamaños de stand). Al confirmarse su pago se crea su cuenta y se
        le envía por correo su usuario, su contraseña y el link de la app de escaneo. La lista está ordenada por
        contactos capturados: es el ranking de atracción entre stands.
    </p>
</div>

@if ($apiCaida)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded-md mb-4 text-sm">
        No se pudo obtener la lista de empresas expositoras. Reintenta en unos segundos.
    </div>
@endif

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Empresa</th>
                <th class="px-3 py-2">Usuario (correo)</th>
                <th class="px-3 py-2">Tamaño de stand</th>
                <th class="px-3 py-2">N° / ubicación</th>
                <th class="px-3 py-2 text-center">Activa</th>
                <th class="px-3 py-2 text-right">Contactos</th>
                <th class="px-3 py-2">Acceso</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expositores as $expositor)
                @php $formId = 'exp-form-' . $expositor['id']; @endphp
                <tr class="border-t border-slate-100 align-middle">
                    <td class="px-3 py-2 text-slate-500 tabular-nums">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2">
                        <input type="text" name="nombre" value="{{ $expositor['nombre'] }}" required maxlength="255"
                               form="{{ $formId }}" class="border border-slate-300 rounded px-2 py-1 w-44">
                    </td>
                    <td class="px-3 py-2">
                        <input type="email" name="email" value="{{ $expositor['email'] }}" required maxlength="255"
                               form="{{ $formId }}" class="border border-slate-300 rounded px-2 py-1 w-52">
                    </td>
                    <td class="px-3 py-2">
                        <select name="categoria_id" form="{{ $formId }}" class="border border-slate-300 rounded px-2 py-1">
                            <option value="">—</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria['id'] }}" @selected(($expositor['categoriaId'] ?? null) == $categoria['id'])>{{ $categoria['name'] }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <input type="text" name="stand" value="{{ $expositor['stand'] }}" maxlength="255" placeholder="Ej. B-12"
                               form="{{ $formId }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" name="activo" value="1" @checked($expositor['activo']) form="{{ $formId }}">
                    </td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $expositor['leadsCount'] ?? 0 }}</td>
                    <td class="px-3 py-2">
                        @if ($expositor['credencialesEnviadas'])
                            <span class="text-xs text-green-700 bg-green-50 border border-green-200 rounded px-2 py-0.5">Enviado</span>
                        @else
                            <span class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-0.5">Sin enviar</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                        <form method="POST" action="{{ route('expositores.update', [$evento['id'], $expositor['id']]) }}" id="{{ $formId }}" class="inline">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="text-brand-600 hover:underline">Guardar</button>
                        </form>
                        <a href="{{ route('expositores.show', [$evento['id'], $expositor['id']]) }}" class="text-brand-600 hover:underline">Ver detalle</a>
                        <form method="POST" action="{{ route('expositores.reenviar', [$evento['id'], $expositor['id']]) }}" class="inline"
                              onsubmit="return confirm('Se generará una contraseña nueva y se enviará por correo. La anterior dejará de funcionar. ¿Continuar?');">
                            @csrf
                            <button type="submit" class="text-slate-600 hover:underline">Reenviar acceso</button>
                        </form>
                        <form method="POST" action="{{ route('expositores.destroy', [$evento['id'], $expositor['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta empresa expositora? Solo es posible si aún no capturó contactos.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-slate-500">
                        Todavía no hay empresas expositoras en este evento. Aparecerán solas cuando se confirme el pago de una
                        inscripción de tipo expositor, o puedes agregar una a mano abajo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<section class="bg-white rounded-lg shadow p-5">
    <h2 class="font-bold text-sm mb-1">Agregar una empresa a mano</h2>
    <p class="text-xs text-slate-400 mb-3">
        Se crea la cuenta y se le envía por correo su usuario y contraseña. Úsalo para expositores que no pasaron por la
        inscripción pública (por ejemplo, invitados o canje).
    </p>
    <form method="POST" action="{{ route('expositores.store', $evento['id']) }}" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
        @csrf
        <div>
            <label for="nuevoNombre" class="block text-xs text-slate-500 mb-1">Empresa</label>
            <input id="nuevoNombre" type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="255"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label for="nuevoEmail" class="block text-xs text-slate-500 mb-1">Correo (será el usuario)</label>
            <input id="nuevoEmail" type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label for="nuevaCategoria" class="block text-xs text-slate-500 mb-1">Tamaño de stand</label>
            <select id="nuevaCategoria" name="categoria_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">—</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria['id'] }}" @selected(old('categoria_id') == $categoria['id'])>{{ $categoria['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="nuevoStand" class="block text-xs text-slate-500 mb-1">N° / ubicación</label>
            <input id="nuevoStand" type="text" name="stand" value="{{ old('stand') }}" maxlength="255"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Crear y enviar acceso
        </button>
    </form>
</section>
@endsection
