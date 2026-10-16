@extends('layouts.app')

@section('title', 'Editar inscripción — Caja')

@section('content')
@php
    $participantes = $registro['participantes'] ?? [];
    $participanteIndex = $participanteIndex ?? 0;
    $p = $participantes[$participanteIndex] ?? [];
    $pagoStatus = $registro['pago_status'] ?? 'pending';
    $ftActual = collect($evento['formTypes'] ?? [])->firstWhere('id', $registro['form_types_id'] ?? null);
    $costoEdicion = $ftActual['costo_edicion'] ?? 0;
@endphp

<div class="mb-4">
    <a href="{{ route('caja.buscar', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a la búsqueda</a>
</div>
<h1 class="text-lg font-bold mb-1">Editar inscripción {{ $referencia }}</h1>
<p class="text-sm text-slate-500 mb-5">
    Estado actual: <strong>{{ $pagoStatus === 'paid' ? 'Pagada' : 'Pendiente' }}</strong>
    @if ($pagoStatus !== 'paid')
        — editar acá no cobra nada por sí solo; si además querés cobrarla, hacelo desde "Buscar" con el botón Cobrar.
    @endif
</p>

<p class="text-xs text-slate-500 mb-4">
    Nota: el kit/souvenirs no vienen prellenados por una limitación del detalle que devuelve
    ApiRestEvent hoy — si la inscripción ya tenía ítems del kit y siguen aplicando, volvé a
    marcarlos.
</p>

{{-- Múltiples participantes (16/10/2026) — esta pantalla edita UN
     participante a la vez; con 2+ en la misma inscripción, elegí a cuál
     antes de tocar nada (cambiar de participante sin guardar descarta lo
     tipeado, como cualquier navegación). --}}
@if (count($participantes) > 1)
    <div class="bg-white rounded-lg shadow p-4 mb-5">
        <label class="block text-xs font-semibold mb-1">Editando al participante</label>
        <select onchange="location.href = this.value" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            @foreach ($participantes as $i => $part)
                <option value="{{ route('caja.editar', [$evento['id'], $referencia]) }}?participante={{ $i }}" {{ $i === $participanteIndex ? 'selected' : '' }}>
                    {{ ($i + 1) }}. {{ trim(($part['nombre'] ?? '').' '.($part['apellido'] ?? '')) ?: 'Participante '.($i + 1) }}
                </option>
            @endforeach
        </select>
    </div>
@endif

@include('caja._formulario', [
    'modo' => 'editar',
    'formTypeFijo' => $registro['form_types_id'] ?? null,
    'prefill' => [
        'nombre' => $p['nombre'] ?? '',
        'apellido' => $p['apellido'] ?? '',
        'alias' => $p['alias'] ?? '',
        'genero' => $p['genero'] ?? '',
        'tipoDocumento' => $p['tipoDocumento'] ?? 'DNI',
        'numeroDocumento' => $p['numeroDocumento'] ?? '',
        'nacimientoDia' => $p['nacimiento']['dia'] ?? '',
        'nacimientoMes' => $p['nacimiento']['mes'] ?? '',
        'nacimientoAnio' => $p['nacimiento']['anio'] ?? '',
        'correo' => $p['correo'] ?? '',
        'direccion' => $p['direccion'] ?? '',
        'ciudad' => $p['ciudad'] ?? '',
        'pais' => $p['pais'] ?? '',
        'telefono' => $p['telefono'] ?? '',
        'contactoNombre' => $p['contacto_emergencia']['nombre'] ?? '',
        'contactoCelular' => $p['contacto_emergencia']['celular'] ?? '',
        'contactoRelacion' => $p['contacto_emergencia']['relacion'] ?? '',
        'categoria' => $p['categoria'] ?? null,
        // Cobro correcto al editar una inscripción pagada (17/09/2026) —
        // ver caja/_formulario.blade.php calcular(): sin esto, el resumen
        // de cobro usaba el precio VIGENTE de la categoría (que puede
        // haber subido desde que se pagó) en vez de compararlo contra lo
        // que ya se pagó de verdad.
        'precioCategoria' => $p['precioCategoria'] ?? null,
        'equipoId' => $p['equipoId'] ?? null,
        'quiereDelivery' => $p['quiereDelivery'] ?? false,
        'donacion' => $p['donacion'] ?? 0,
        'promoCodigo' => $p['promoCodigo'] ?? '',
        'souvenirs' => [],
        // Congresos con talleres desde Caja (20/08/2026).
        'talleres' => $p['talleres'] ?? [],
    ],
    'costoEdicion' => $costoEdicion,
    'actionUrl' => route('caja.editar.store', [$evento['id'], $referencia]),
    'pagoStatus' => $pagoStatus,
    'participanteIndex' => $participanteIndex,
])
@endsection
