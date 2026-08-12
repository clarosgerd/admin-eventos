@extends('layouts.app')

@section('title', 'Dashboard de inscripciones — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Dashboard de inscripciones</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

<p class="text-xs text-slate-500 mb-4">
    Mismo conteo que se le manda por correo al organizador cuando se publica el evento.
</p>

{{-- Tarjetas de totales --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-brand-600">{{ $totalGeneral['total'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Total inscritos</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-green-600">{{ $totalGeneral['paid'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pagados</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-amber-600">{{ $totalGeneral['pending'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pendientes</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-red-600">{{ $totalGeneral['cancelled'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Cancelados</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-red-600">{{ $totalGeneral['failed'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Fallidos</div>
    </div>
</div>

{{-- Balance del presupuesto --}}
<div class="flex justify-between items-center mb-2">
    <h2 class="font-bold text-sm text-brand-600">Balance del evento</h2>
    <a href="{{ route('presupuesto.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">
        Ver/editar presupuesto →
    </a>
</div>
@if ($balance)
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosInscripciones'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingreso por inscripciones</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingresos manuales</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-red-600">${{ number_format($balance['gastosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Gastos</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-green-600">${{ number_format($balance['utilidadNeta'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Utilidad neta</div>
    </div>
</div>
@endif

{{-- Por categoría --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Por categoría</h2>
<div class="overflow-x-auto mb-6">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Categoría</th>
                @foreach ($estados as $estado)
                    <th class="px-3 py-2 font-semibold text-right">{{ ucfirst($estado) }}</th>
                @endforeach
                <th class="px-3 py-2 font-semibold text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porCategoria as $categoria => $counts)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $nombresCategorias[$categoria] ?? $categoria }}</td>
                    @foreach ($estados as $estado)
                        <td class="px-3 py-2 text-right">{{ $counts[$estado] }}</td>
                    @endforeach
                    <td class="px-3 py-2 text-right font-semibold">{{ $counts['total'] }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Por tipo de formulario --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Por tipo de formulario</h2>
<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Tipo de formulario</th>
                @foreach ($estados as $estado)
                    <th class="px-3 py-2 font-semibold text-right">{{ ucfirst($estado) }}</th>
                @endforeach
                <th class="px-3 py-2 font-semibold text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porFormulario as $formTypeId => $counts)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $nombresFormTypes[$formTypeId] ?? 'Sin especificar' }}</td>
                    @foreach ($estados as $estado)
                        <td class="px-3 py-2 text-right">{{ $counts[$estado] }}</td>
                    @endforeach
                    <td class="px-3 py-2 text-right font-semibold">{{ $counts['total'] }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
