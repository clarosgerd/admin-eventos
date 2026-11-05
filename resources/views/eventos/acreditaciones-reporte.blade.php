@extends('layouts.app')

@section('title', 'Reporte de acreditaciones — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Reporte de acreditaciones</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <div class="flex items-center gap-3 self-center">
        <a href="{{ route('acreditacion.index', $evento['id']) }}" class="text-sm bg-brand-600 hover:bg-brand-700 text-white font-semibold px-3 py-1.5 rounded-md">
            Ir a Acreditación →
        </a>
        <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">
            ← Volver a editar evento
        </a>
    </div>
</div>

<p class="text-xs text-slate-500 mb-4 max-w-3xl">
    Acreditación general del evento (check-in en la puerta escaneando el QR de referencia). Solo los participantes
    <strong>pagados</strong> se pueden acreditar, así que el porcentaje de avance se calcula sobre ellos.
    No incluye la asistencia por sesión de congreso (ese reporte está en
    <a href="{{ route('sesiones.reporte', $evento['id']) }}" class="text-brand-600 hover:underline">Reporte de sesiones</a>).
</p>

{{-- Tarjetas de totales — el contador de acreditados es el destacado (el
     dato del día del evento). --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-green-600">{{ $totales['acreditados'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Acreditados</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-amber-600">{{ $totales['pendientes'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pendientes de acreditar</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-brand-600">{{ $totales['pagados'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pagados (acreditables)</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-slate-600">{{ $totales['total'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Total inscritos</div>
    </div>
</div>

{{-- Barra de progreso de acreditación (sobre los pagados) --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex justify-between text-sm mb-2">
        <span class="font-semibold text-slate-700">Avance de acreditación</span>
        <span class="font-bold text-green-600">{{ $totales['porcentaje'] }}%</span>
    </div>
    <div class="w-full bg-slate-200 rounded-full h-3">
        <div class="bg-green-500 h-3 rounded-full transition-all" style="width: {{ min(100, $totales['porcentaje']) }}%"></div>
    </div>
</div>

{{-- Por categoría --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Por categoría</h2>
<div class="overflow-x-auto mb-6">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Categoría</th>
                <th class="px-3 py-2 font-semibold text-right">Pagados</th>
                <th class="px-3 py-2 font-semibold text-right">Acreditados</th>
                <th class="px-3 py-2 font-semibold text-right">Pendientes</th>
                <th class="px-3 py-2 font-semibold w-48">% acreditado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porCategoria as $categoria => $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $nombresCategorias[$categoria] ?? $categoria }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['pagados'] }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-green-600">{{ $fila['acreditados'] }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['pendientes'] }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-28 bg-slate-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ min(100, $fila['porcentaje']) }}%"></div>
                            </div>
                            <span class="text-xs text-slate-600">{{ $fila['porcentaje'] }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="5">Todavía no hay participantes pagados en este evento.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Acreditaciones por hora (ritmo del día) --}}
    <div>
        <h2 class="font-bold text-sm text-brand-600 mb-2">Acreditaciones por hora</h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @php $maxHora = collect($porHora)->max('cantidad') ?: 1; @endphp
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Hora</th>
                        <th class="px-3 py-2 font-semibold text-right">Acreditaciones</th>
                        <th class="px-3 py-2 font-semibold w-32"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($porHora)->take(12) as $fila)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $fila['hora'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $fila['cantidad'] }}</td>
                            <td class="px-3 py-2">
                                <div class="w-full bg-slate-200 rounded-full h-2">
                                    <div class="bg-brand-600 h-2 rounded-full" style="width: {{ round($fila['cantidad'] * 100 / $maxHora) }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-2 text-slate-500" colspan="3">Todavía no hay acreditaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Últimos acreditados --}}
    <div>
        <h2 class="font-bold text-sm text-brand-600 mb-2">Últimos acreditados</h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Hora</th>
                        <th class="px-3 py-2 font-semibold">Participante</th>
                        <th class="px-3 py-2 font-semibold">Categoría</th>
                        <th class="px-3 py-2 font-semibold">Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recientes as $fila)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 whitespace-nowrap">{{ $fila['checkedInAt'] ? \Illuminate\Support\Carbon::parse($fila['checkedInAt'])->format('Y-m-d H:i') : '—' }}</td>
                            <td class="px-3 py-2">{{ $fila['nombre'] }}</td>
                            <td class="px-3 py-2">{{ $nombresCategorias[$fila['categoria']] ?? $fila['categoria'] }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $fila['referencia'] }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-2 text-slate-500" colspan="4">Todavía no hay acreditaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
