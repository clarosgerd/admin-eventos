@extends('layouts.app')

@section('title', 'Historial de cobros — '.$evento['name'])

@section('content')

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-4 text-sm">{{ session('status') }}</div>
@endif

<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Historial de cobros</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('historial-cobros.reporte.csv', $evento['id']) }}" class="text-sm bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-md hover:bg-slate-50">
            ⬇ Descargar CSV
        </a>
        <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
            ← Volver al editor
        </a>
    </div>
</div>

<p class="text-xs text-slate-500 mb-4 max-w-3xl">
    Para cada inscripción pagada: el alta inicial y cada adición en autoservicio (taller/categoría agregados a una
    inscripción ya pagada), con el monto de cada movimiento desglosado en contenido nuevo, cargo de servicio (fee)
    y costo de edición. Las filas marcadas <span class="font-semibold text-amber-600">estimado</span> son el primer
    movimiento de edición de una inscripción: el monto total es exacto, pero el reparto entre fee y contenido de esa
    fila puntual es una aproximación (proporcional al % de cargo de servicio del evento) — no queda registrado en
    ningún lado cómo se repartió exactamente esa primera edición.
</p>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <div class="bg-white border border-slate-200 rounded-lg p-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Inscripciones</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ $resumen['totalInscripciones'] ?? 0 }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Con adición</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ $resumen['totalConAdiciones'] ?? 0 }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Total cobrado</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ number_format($resumen['totalCobrado'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Total fee</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ number_format($resumen['totalFee'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Total costo edición</p>
        <p class="text-xl font-semibold text-slate-900 mt-1">{{ number_format($resumen['totalCostoEdicion'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-3 {{ ($resumen['totalAnomalias'] ?? 0) > 0 ? 'ring-1 ring-red-300' : '' }}">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Anomalías</p>
        <p class="text-xl font-semibold {{ ($resumen['totalAnomalias'] ?? 0) > 0 ? 'text-red-600' : 'text-slate-900' }} mt-1">{{ $resumen['totalAnomalias'] ?? 0 }}</p>
    </div>
</div>

<div class="flex items-center gap-3 mb-3 flex-wrap">
    <input type="text" id="buscadorHistorial" placeholder="Buscar por referencia o participante…"
           class="border border-slate-300 rounded-md px-3 py-1.5 text-sm w-72">
    <label class="text-xs text-slate-600 flex items-center gap-1.5">
        <input type="checkbox" id="soloAnomalasHistorial"> Solo filas con anomalía
    </label>
</div>

<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm" id="tablaHistorial">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Inscripción</th>
                <th class="px-3 py-2 font-semibold">Movimiento</th>
                <th class="px-3 py-2 font-semibold">Ref. movimiento</th>
                <th class="px-3 py-2 font-semibold">Fecha</th>
                <th class="px-3 py-2 font-semibold text-right">Monto</th>
                <th class="px-3 py-2 font-semibold text-right">Fee</th>
                <th class="px-3 py-2 font-semibold text-right">Costo edición</th>
                <th class="px-3 py-2 font-semibold text-right">Contenido</th>
                <th class="px-3 py-2 font-semibold text-right">Total acumulado</th>
                <th class="px-3 py-2 font-semibold">Participante</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                @php($esInicial = $fila['tipoMovimiento'] === 'inicial')
                @php($tieneAnomalia = !empty($fila['anomalias']))
                <tr class="border-t border-slate-100 fila-historial {{ $tieneAnomalia ? 'bg-red-50' : '' }}"
                    data-search="{{ mb_strtolower($fila['referenciaInscripcion'].' '.$fila['referenciaMovimiento'].' '.($fila['participantePrincipal'] ?? '')) }}"
                    data-anomalia="{{ $tieneAnomalia ? '1' : '0' }}">
                    <td class="px-3 py-2 {{ $esInicial ? 'font-semibold' : 'pl-6 text-slate-500' }}">{{ $fila['referenciaInscripcion'] }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full {{ $esInicial ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $esInicial ? 'Inicial' : 'Adición' }}
                        </span>
                        @if ($tieneAnomalia)
                            <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700 ml-1" title="{{ implode(', ', $fila['anomalias']) }}">⚠ anomalía</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $fila['referenciaMovimiento'] }}</td>
                    <td class="px-3 py-2">{{ $fila['fechaMovimiento'] ? \Illuminate\Support\Carbon::parse($fila['fechaMovimiento'])->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($fila['monto'], 2) }}</td>
                    <td class="px-3 py-2 text-right {{ $fila['esEstimado'] ? 'text-amber-600 font-semibold' : '' }}">{{ number_format($fila['fee'], 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($fila['costoEdicion'], 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($fila['contenidoNuevo'], 2) }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($fila['grandTotalAcumulado'], 2) }}</td>
                    <td class="px-3 py-2">{{ $fila['participantePrincipal'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="10">Este evento todavía no tiene inscripciones pagadas.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p id="sinResultadosHistorial" class="text-sm text-slate-500 px-3 py-4" hidden>Sin resultados para este filtro.</p>
</div>

<script>
(function () {
    var buscador = document.getElementById('buscadorHistorial');
    var soloAnomalas = document.getElementById('soloAnomalasHistorial');
    var filas = document.querySelectorAll('#tablaHistorial tbody tr.fila-historial');
    var sinResultados = document.getElementById('sinResultadosHistorial');

    function aplicarFiltro() {
        var q = (buscador.value || '').toLowerCase().trim();
        var soloAnom = soloAnomalas.checked;
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincideTexto = !q || (fila.dataset.search || '').indexOf(q) !== -1;
            var coincideAnomalia = !soloAnom || fila.dataset.anomalia === '1';
            var visible = coincideTexto && coincideAnomalia;
            fila.hidden = !visible;
            if (visible) visibles++;
        });

        sinResultados.hidden = visibles > 0;
    }

    buscador.addEventListener('input', aplicarFiltro);
    soloAnomalas.addEventListener('change', aplicarFiltro);
})();
</script>
@endsection
