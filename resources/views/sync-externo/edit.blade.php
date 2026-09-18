@extends('layouts.app')

@section('title', 'Sync externo — '.($evento['name'] ?? 'Evento'))

@section('content')
<h1 class="text-lg font-bold mb-1">Sync de participantes — fuente externa</h1>
<p class="text-sm text-slate-500 mb-4">
    <a href="{{ route('eventos.edit', $evento['id']) }}" class="text-brand-600 hover:underline">← {{ $evento['name'] ?? 'Evento' }}</a>
</p>

<p class="text-sm text-slate-600 mb-4 max-w-2xl">
    Trae periódicamente (cada hora, vía cron) los participantes que un sistema de registro propio
    del organizador expone en su propia URL — nunca al revés, nosotros nunca le escribimos nada.
    Solo crea/actualiza <strong>Registration/Participante</strong> ($0, `tipo_pago=externo`), sin
    cobrar nada acá.
</p>

<div class="bg-white rounded-lg shadow p-6 max-w-lg mb-6">
    <form method="POST" action="{{ route('sync-externo.store', $evento['id']) }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-semibold mb-1" for="form_types_id">Tipo de inscripción (default)</label>
            <select name="form_types_id" id="form_types_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— seleccionar —</option>
                @foreach ($evento['formTypes'] ?? [] as $ft)
                    <option value="{{ $ft['id'] }}" @selected((string) old('form_types_id', $config['formTypesId'] ?? '') === (string) $ft['id'])>
                        {{ $ft['name'] }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1">
                Si la fuente manda <code>form_type</code> por participante (evento con más de un
                tipo, ej. carrera + congreso), ese valor manda; sin ese campo, cae acá.
            </p>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="nombre_fuente">Nombre de la fuente <span class="font-normal text-slate-500">(solo descriptivo)</span></label>
            <input type="text" name="nombre_fuente" id="nombre_fuente" value="{{ old('nombre_fuente', $config['nombreFuente'] ?? '') }}"
                   placeholder="Ej. Sistema propio del organizador"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="url">URL de la fuente</label>
            <input type="url" name="url" id="url" value="{{ old('url', $config['url'] ?? '') }}" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="token">
                Token
                @if ($config)
                    <span class="font-normal text-slate-500">(actual: {{ $config['tokenPreview'] ?? 'sin token' }} — dejar vacío para no cambiar)</span>
                @endif
            </label>
            <input type="password" name="token" id="token"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            <p class="text-xs text-slate-500 mt-1">Se manda como <code>Authorization: Bearer {token}</code> al llamar a la URL de arriba.</p>
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $config['activo'] ?? true) ? 'checked' : '' }}>
                Activo (el cron cada hora solo procesa fuentes activas — apagar acá cuando termine el evento)
            </label>
        </div>

        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
            Guardar
        </button>
    </form>
</div>

@if ($config)
    <div class="bg-white rounded-lg shadow p-6 max-w-lg mb-6">
        <h2 class="font-semibold mb-3">Última sincronización</h2>
        @if ($config['ultimaSincronizacionAt'])
            <p class="text-sm mb-2">{{ \Illuminate\Support\Carbon::parse($config['ultimaSincronizacionAt'])->format('d/m/Y H:i') }}</p>
            @if ($config['ultimoResultado'])
                <p class="text-sm text-slate-600">
                    Creados: {{ $config['ultimoResultado']['creados'] ?? 0 }} ·
                    Actualizados: {{ $config['ultimoResultado']['actualizados'] ?? 0 }} ·
                    Omitidos: {{ count($config['ultimoResultado']['omitidos'] ?? []) }}
                </p>
                @if (!empty($config['ultimoResultado']['error']))
                    <p class="text-sm text-red-600 mt-1">{{ $config['ultimoResultado']['error'] }}</p>
                @endif
            @endif
        @else
            <p class="text-sm text-slate-500">Todavía no corrió ninguna sincronización.</p>
        @endif

        <form method="POST" action="{{ route('sync-externo.sincronizar-ahora', $evento['id']) }}" class="mt-4">
            @csrf
            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white rounded-md px-3 py-2 text-sm font-semibold">
                Sincronizar ahora
            </button>
        </form>
    </div>
@endif

<div class="bg-amber-50 border border-amber-200 rounded-lg p-4 max-w-2xl text-sm text-amber-900">
    <p class="font-semibold mb-2">Contrato JSON esperado (entregar a quien gestiona la fuente):</p>
    <pre class="bg-white rounded p-3 overflow-x-auto text-xs">{
  "participantes": [
    {
      "form_type": "opcional — nombre del tipo, si el evento tiene más de uno",
      "numero_documento": "opcional si viene correo",
      "tipo_documento": "opcional, default CI",
      "correo": "opcional si viene numero_documento",
      "nombre": "requerido",
      "apellido": "requerido",
      "telefono": "opcional",
      "categoria": "opcional",
      "genero": "opcional — Masculino/Femenino",
      "fecha_nacimiento": "opcional — YYYY-MM-DD",
      "souvenirs": [{ "nombre": "...", "talla": "...", "sexo": "..." }],
      "talleres": [{ "taller": "...", "sesion": "opcional si el taller tiene una sola" }]
    }
  ]
}</pre>
</div>
@endsection
