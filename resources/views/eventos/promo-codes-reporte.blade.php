@extends('layouts.app')

@section('title', 'Códigos promocionales usados — '.$evento['name'])

@section('content')
@php($tipoLabels = ['fixed_price' => 'Precio fijo', 'percentage' => 'Porcentaje'])

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-4 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-4 text-sm">{{ $errors->first() }}</div>
@endif

<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Códigos promocionales usados</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('eventos.edit', $evento['id']) }}#promos" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver al editor
    </a>
</div>

{{-- Resumen — 4ta tarjeta nueva (13/09/2026): "Usados" sigue significando
     "códigos tocados al menos una vez" (no cambia la lectura de esa
     fracción); "Usos totales" es la dimensión nueva de multi-uso — cuántos
     usos reales pasaron en total, sin importar cuántos códigos distintos. --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Códigos totales</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $totalCodigos }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Usados</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $totalUsados }} <span class="text-sm font-normal text-slate-400">/ {{ $totalCodigos }}</span></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Usos totales</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $totalUsos }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Monto total descontado</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">${{ number_format($totalDescontado, 2) }}</p>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Código</th>
                <th class="px-3 py-2 font-semibold">Tipo</th>
                <th class="px-3 py-2 font-semibold text-right">Valor</th>
                <th class="px-3 py-2 font-semibold">Estado</th>
                <th class="px-3 py-2 font-semibold">Participante</th>
                <th class="px-3 py-2 font-semibold text-right">Monto descontado</th>
                <th class="px-3 py-2 font-semibold">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                {{-- Multi-uso (13/09/2026) — un código puede tener 0, 1 o
                     varios usos; se renderiza una fila de tabla por USO
                     (rowspan en las columnas propias del código), en vez
                     de asumir un único participante como antes. Un código
                     sin usos ($usos vacío) sigue mostrando una sola fila
                     con guiones, igual que siempre. --}}
                @php($usos = $fila['usos'] ?? [])
                @php($rowspan = max(count($usos), 1))
                @if (count($usos) > 0)
                    @foreach ($usos as $i => $uso)
                        <tr class="border-t border-slate-100">
                            @if ($i === 0)
                                <td class="px-3 py-2 font-mono align-top" rowspan="{{ $rowspan }}">{{ $fila['codigo'] }}</td>
                                <td class="px-3 py-2 align-top" rowspan="{{ $rowspan }}">{{ $tipoLabels[$fila['tipo']] ?? $fila['tipo'] }}</td>
                                <td class="px-3 py-2 text-right align-top" rowspan="{{ $rowspan }}">
                                    {{ $fila['tipo'] === 'percentage' ? number_format($fila['valor'] * 100, 0).'%' : '$'.number_format($fila['valor'], 2) }}
                                </td>
                                <td class="px-3 py-2 align-top" rowspan="{{ $rowspan }}">
                                    <span class="inline-block text-xs font-medium px-2.5 py-1 rounded-full {{ $fila['usado'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        @if (($fila['maxUsos'] ?? 1) > 1)
                                            {{ $fila['vecesUsado'] ?? count($usos) }}/{{ $fila['maxUsos'] }} usos
                                        @else
                                            {{ $fila['usado'] ? 'Usado' : 'Sin usar' }}
                                        @endif
                                    </span>
                                </td>
                            @endif
                            <td class="px-3 py-2">
                                @if ($uso['participante'])
                                    {{ $uso['participante']['nombre'] }} {{ $uso['participante']['apellido'] }}
                                    <span class="text-slate-400">({{ $uso['participante']['numeroDocumento'] }})</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                {{ $uso['montoDescontado'] !== null ? '$'.number_format($uso['montoDescontado'], 2) : '—' }}
                            </td>
                            <td class="px-3 py-2">
                                {{ $uso['fecha'] ? \Illuminate\Support\Carbon::parse($uso['fecha'])->format('Y-m-d H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-mono">{{ $fila['codigo'] }}</td>
                        <td class="px-3 py-2">{{ $tipoLabels[$fila['tipo']] ?? $fila['tipo'] }}</td>
                        <td class="px-3 py-2 text-right">
                            {{ $fila['tipo'] === 'percentage' ? number_format($fila['valor'] * 100, 0).'%' : '$'.number_format($fila['valor'], 2) }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-block text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">
                                Sin usar
                            </span>
                        </td>
                        <td class="px-3 py-2"><span class="text-slate-400">—</span></td>
                        <td class="px-3 py-2 text-right">—</td>
                        <td class="px-3 py-2">—</td>
                    </tr>
                @endif
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="7">Este evento todavía no tiene códigos promocionales cargados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
