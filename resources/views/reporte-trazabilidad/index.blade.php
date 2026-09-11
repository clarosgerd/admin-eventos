@extends('layouts.app')

@section('title', 'Trazabilidad de inscripciones')

@section('content')
@php($tipoEventoLabel = fn ($t) => $t ? ucfirst($t) : '—')

<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Trazabilidad de inscripciones</h1>
        <p class="text-sm text-slate-600">Todos los eventos — inscripciones, adiciones de talleres/souvenirs y sus montos.</p>
    </div>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('reporte-trazabilidad.index') }}" class="mb-4 flex gap-2 items-end flex-wrap">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Evento</label>
        <select name="evento_id" onchange="this.form.submit()" class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[180px]">
            <option value="" @selected($filtros['eventoId'] === '')>Todos los eventos</option>
            @foreach ($filtrosDisponibles['eventos'] as $ev)
                <option value="{{ $ev['id'] }}" @selected((string) $filtros['eventoId'] === (string) $ev['id'])>{{ $ev['nombre'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo de evento</label>
        <select name="tipo_evento" onchange="this.form.submit()" class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[160px]">
            <option value="" @selected($filtros['tipoEvento'] === '')>Todos los tipos</option>
            @foreach ($filtrosDisponibles['tiposEvento'] as $tipo)
                <option value="{{ $tipo }}" @selected($filtros['tipoEvento'] === $tipo)>{{ ucfirst($tipo) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Estado</label>
        <select name="pago_status" onchange="this.form.submit()" class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[150px]">
            <option value="" @selected($filtros['pagoStatus'] === '')>Todos los estados</option>
            @foreach ($estadoLabels as $valor => $label)
                <option value="{{ $valor }}" @selected($filtros['pagoStatus'] === $valor)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo de pago</label>
        <select name="tipo_pago" onchange="this.form.submit()" class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[150px]">
            <option value="" @selected($filtros['tipoPago'] === '')>Todos</option>
            @foreach ($filtrosDisponibles['tiposPago'] as $tp)
                <option value="{{ $tp }}" @selected($filtros['tipoPago'] === $tp)>{{ $tp }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Desde</label>
        <input type="date" name="fecha_desde" value="{{ $filtros['fechaDesde'] }}" class="border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Hasta</label>
        <input type="date" name="fecha_hasta" value="{{ $filtros['fechaHasta'] }}" class="border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar</label>
        <input type="text" name="search" value="{{ $filtros['search'] }}" placeholder="Referencia, nombre, documento o correo"
               class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[220px]">
    </div>
    <label class="flex items-center gap-1.5 text-sm text-slate-700 pb-2">
        <input type="checkbox" name="tiene_adiciones" value="1" onchange="this.form.submit()" @checked($filtros['tieneAdiciones'])>
        Solo con adiciones
    </label>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">Buscar</button>
    @if ($filtros['eventoId'] !== '' || $filtros['tipoEvento'] !== '' || $filtros['pagoStatus'] !== '' || $filtros['tipoPago'] !== '' || $filtros['tieneAdiciones'] || $filtros['fechaDesde'] !== '' || $filtros['fechaHasta'] !== '' || $filtros['search'] !== '')
        <a href="{{ route('reporte-trazabilidad.index') }}" class="text-sm text-slate-500 hover:underline self-center">Limpiar filtros</a>
    @endif
    <a href="{{ route('reporte-trazabilidad.csv', array_filter([
            'evento_id' => $filtros['eventoId'] ?: null, 'tipo_evento' => $filtros['tipoEvento'] ?: null,
            'pago_status' => $filtros['pagoStatus'] ?: null, 'tipo_pago' => $filtros['tipoPago'] ?: null,
            'tiene_adiciones' => $filtros['tieneAdiciones'] ? 1 : null, 'fecha_desde' => $filtros['fechaDesde'] ?: null,
            'fecha_hasta' => $filtros['fechaHasta'] ?: null, 'search' => $filtros['search'] ?: null,
        ])) }}"
       class="inline-block bg-white border border-slate-300 hover:bg-slate-50 text-sm font-semibold px-3 py-2 rounded-md">
        Descargar CSV (todo, sin paginar)
    </a>
</form>

<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Referencia</th>
                <th class="px-3 py-2 font-semibold">Evento</th>
                <th class="px-3 py-2 font-semibold">Tipo</th>
                <th class="px-3 py-2 font-semibold">Fecha</th>
                <th class="px-3 py-2 font-semibold">Estado</th>
                <th class="px-3 py-2 font-semibold">Pago</th>
                <th class="px-3 py-2 font-semibold text-right">Monto inscripción</th>
                <th class="px-3 py-2 font-semibold text-center">Poleras</th>
                <th class="px-3 py-2 font-semibold text-center">Talleres</th>
                <th class="px-3 py-2 font-semibold text-right">Adiciones pagadas</th>
                <th class="px-3 py-2 font-semibold"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inscripciones as $idx => $i)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2 font-mono">{{ $i['referencia'] }}</td>
                    <td class="px-3 py-2">{{ $i['eventoNombre'] }}</td>
                    <td class="px-3 py-2">{{ $tipoEventoLabel($i['formTypeTipo']) }}</td>
                    <td class="px-3 py-2">{{ $i['fecha'] ? \Illuminate\Support\Carbon::parse($i['fecha'])->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-3 py-2">{{ $estadoLabels[$i['pagoStatus']] ?? $i['pagoStatus'] }}</td>
                    <td class="px-3 py-2">{{ $i['tipoPago'] ?: '—' }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($i['montoInscripcion'], 2) }}</td>
                    <td class="px-3 py-2 text-center">{{ $i['cantidadPoleras'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $i['cantidadTalleres'] }}</td>
                    <td class="px-3 py-2 text-right">
                        ${{ number_format($i['montoAdicionesPagadas'], 2) }}
                        @if ($i['cantidadAdicionesPendientes'] > 0)
                            <span class="text-amber-600 text-xs block">+{{ $i['cantidadAdicionesPendientes'] }} pendiente(s)</span>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" onclick="document.getElementById('detalle-{{ $idx }}').classList.toggle('hidden')"
                                class="bg-white border border-slate-300 hover:bg-slate-50 text-xs font-semibold px-2.5 py-1.5 rounded-md whitespace-nowrap">
                            Ver detalle ▾
                        </button>
                    </td>
                </tr>
                <tr id="detalle-{{ $idx }}" class="hidden border-t border-slate-100 bg-slate-50">
                    <td colspan="11" class="px-3 py-3">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Participantes ({{ $i['cantidadParticipantes'] }})</h3>
                                @forelse ($i['participantes'] as $p)
                                    <div class="text-xs border-b border-slate-200 py-1.5">
                                        <strong>{{ $p['nombre'] }} {{ $p['apellido'] }}</strong> — {{ $p['numeroDocumento'] }}
                                        <br>Categoría: {{ $p['categoria'] }}
                                        @if ($p['tienePolera'])
                                            · Polera: talla {{ $p['tallaPolera'] }}
                                        @endif
                                        @if (count($p['talleres']) > 0)
                                            <ul class="list-disc list-inside mt-1">
                                                @foreach ($p['talleres'] as $t)
                                                    <li>{{ $t['tallerNombre'] }}@if($t['sesionTitulo']) — {{ $t['sesionTitulo'] }}@endif
                                                        (${{ number_format($t['monto'], 2) }}{{ $t['pagoPendiente'] ? ', pendiente' : '' }})</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">Sin participantes cargados (inscripción sin completar).</p>
                                @endforelse
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Adiciones ({{ count($i['adiciones']) }})</h3>
                                @forelse ($i['adiciones'] as $a)
                                    <div class="text-xs border-b border-slate-200 py-1.5">
                                        <span class="font-mono">{{ $a['referencia'] }}</span> — ${{ number_format($a['monto'], 2) }}
                                        — {{ $estadoAdicionLabels[$a['pagoStatus']] ?? $a['pagoStatus'] }}
                                        <br>Creado: {{ $a['creadoEn'] ? \Illuminate\Support\Carbon::parse($a['creadoEn'])->format('Y-m-d H:i') : '—' }}
                                        @if ($a['pagadoEn'])
                                            · Pagado: {{ \Illuminate\Support\Carbon::parse($a['pagadoEn'])->format('Y-m-d H:i') }}
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">Sin adiciones sobre esta inscripción.</p>
                                @endforelse
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="11">No hay inscripciones con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($meta)
    <div class="flex justify-between items-center mt-4 text-sm text-slate-600">
        <span>Página {{ $meta['currentPage'] }} de {{ $meta['lastPage'] }} — {{ $meta['total'] }} inscripción(es) en total</span>
        <div class="flex gap-2">
            @if ($meta['currentPage'] > 1)
                <a href="{{ route('reporte-trazabilidad.index', array_filter([
                        'evento_id' => $filtros['eventoId'] ?: null, 'tipo_evento' => $filtros['tipoEvento'] ?: null,
                        'pago_status' => $filtros['pagoStatus'] ?: null, 'tipo_pago' => $filtros['tipoPago'] ?: null,
                        'tiene_adiciones' => $filtros['tieneAdiciones'] ? 1 : null, 'fecha_desde' => $filtros['fechaDesde'] ?: null,
                        'fecha_hasta' => $filtros['fechaHasta'] ?: null, 'search' => $filtros['search'] ?: null,
                        'page' => $meta['currentPage'] - 1,
                    ])) }}" class="bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">← Anterior</a>
            @endif
            @if ($meta['currentPage'] < $meta['lastPage'])
                <a href="{{ route('reporte-trazabilidad.index', array_filter([
                        'evento_id' => $filtros['eventoId'] ?: null, 'tipo_evento' => $filtros['tipoEvento'] ?: null,
                        'pago_status' => $filtros['pagoStatus'] ?: null, 'tipo_pago' => $filtros['tipoPago'] ?: null,
                        'tiene_adiciones' => $filtros['tieneAdiciones'] ? 1 : null, 'fecha_desde' => $filtros['fechaDesde'] ?: null,
                        'fecha_hasta' => $filtros['fechaHasta'] ?: null, 'search' => $filtros['search'] ?: null,
                        'page' => $meta['currentPage'] + 1,
                    ])) }}" class="bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">Siguiente →</a>
            @endif
        </div>
    </div>
@endif
@endsection
