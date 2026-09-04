{{-- Form compartido por create/edit — 13 campos, demasiados para edición
     inline por fila (patrón de los catálogos chicos), mismo criterio que
     eventos/edit.blade.php para formularios largos. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-semibold mb-1">Nombre *</label>
        <input type="text" name="nombre" value="{{ old('nombre', $persona['nombre'] ?? '') }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Apellido *</label>
        <input type="text" name="apellido" value="{{ old('apellido', $persona['apellido'] ?? '') }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Alias</label>
        <input type="text" name="alias" value="{{ old('alias', $persona['alias'] ?? '') }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Email *</label>
        <input type="email" name="email" value="{{ old('email', $persona['email'] ?? '') }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Contraseña</label>
        <input type="password" name="password"
               placeholder="{{ isset($persona) ? 'Dejar vacío para no cambiarla' : 'Dejar vacío para generar una automática' }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Género *</label>
        <select name="sexo" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            @foreach (['Masculino', 'Femenino', 'Otro'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('sexo', $persona['sexo'] ?? '') === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Tipo de documento *</label>
        <select name="tipo_documento" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            @foreach (['DNI', 'CI', 'Pasaporte'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('tipo_documento', $persona['tipoDocumento'] ?? '') === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Número de documento *</label>
        <input type="text" name="numero_documento" value="{{ old('numero_documento', $persona['numeroDocumento'] ?? '') }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Fecha de nacimiento *</label>
        @php
            $nacimiento = $persona['nacimiento'] ?? null;
            $fechaNacimiento = $nacimiento ? sprintf('%04d-%02d-%02d', $nacimiento['anio'], $nacimiento['mes'], $nacimiento['dia']) : '';
        @endphp
        <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $fechaNacimiento) }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Dirección</label>
        <input type="text" name="direccion" value="{{ old('direccion', $persona['direccion'] ?? '') }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Ciudad</label>
        <input type="text" name="ciudad" value="{{ old('ciudad', $persona['ciudad'] ?? '') }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Teléfono</label>
        <input type="text" name="telefono" value="{{ old('telefono', $persona['telefono'] ?? '') }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-semibold mb-1">Celular</label>
        <input type="text" name="celular" value="{{ old('celular', $persona['celular'] ?? '') }}"
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
</div>
<div class="mt-4">
    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="acepta_marketing" value="1"
               @checked(old('acepta_marketing', $persona['acepta_marketing'] ?? true))>
        Acepta recibir comunicaciones de marketing
    </label>
</div>
