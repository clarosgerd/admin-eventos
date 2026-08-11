@extends('layouts.app')

@section('title', 'Editar evento — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h1 class="text-lg font-bold">Editar: {{ $evento['name'] }}</h1>
    <div class="flex gap-2 flex-wrap">
        @if ((session('admin_user')['rol'] ?? null) === 'super_admin')
            <a href="{{ route('registro-manual.index', $evento['id']) }}"
               class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
                Carga masiva de inscripciones
            </a>
            <a href="{{ route('liquidacion.show', $evento['id']) }}"
               class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
                Liquidación
            </a>
        @endif
        <a href="{{ route('numeracion.index', $evento['id']) }}"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Numeración de corredor y chip
        </a>
        <a href="{{ route('acreditacion.index', $evento['id']) }}"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Acreditación
        </a>
        <a href="{{ route('eventos.dashboard', $evento['id']) }}"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Dashboard de inscripciones
        </a>
        <a href="{{ route('chronotrack.index', $evento['id']) }}"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Resultados / ChronoTrack
        </a>
        <a href="{{ route('participantes.index', $evento['id']) }}"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Participantes
        </a>
        <a href="{{ route('eventos.gafetes-pdf', $evento['id']) }}" target="_blank"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Gafetes (PDF)
        </a>
        <a href="{{ route('eventos.certificados-pdf', $evento['id']) }}" target="_blank"
           class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
            Certificados (PDF)
        </a>
    </div>
</div>

{{-- 1. Datos del evento --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Datos del evento</h2>
    <form method="POST" action="{{ route('eventos.update', $evento['id']) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $evento['name']) }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Descripción corta</label>
                <input type="text" name="description" value="{{ old('description', $evento['description']) }}" required maxlength="500"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Descripción larga</label>
                <textarea name="longDescription" rows="3"
                          class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('longDescription', $evento['longDescription']) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Fecha</label>
                <input type="date" name="date" value="{{ old('date', \Illuminate\Support\Str::before($evento['date'], ' ')) }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Hora local</label>
                <input type="time" name="localTime" value="{{ old('localTime', $evento['localTime']) }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Ubicación</label>
                <input type="text" name="location" value="{{ old('location', $evento['location']) }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Estado</label>
                <select name="status" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    @foreach (['open','closed','coming_soon'] as $opt)
                        <option value="{{ $opt }}" @selected(old('status', $evento['status']) === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">
                    Tipo de evento <span class="font-normal text-slate-500">(disciplina, o "Congreso / No aplica")</span>
                </label>
                <select name="tipo_evento_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($tiposEvento as $tipo)
                        <option value="{{ $tipo['id'] }}" @selected((string) old('tipo_evento_id', $evento['tipoEventoId'] ?? '') === (string) $tipo['id'])>
                            {{ $tipo['icono'] }} {{ $tipo['nombre'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Subtipo</label>
                <select name="subtipo_evento_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($tiposEvento as $tipo)
                        @foreach ($tipo['subtipos'] as $sub)
                            <option value="{{ $sub['id'] }}" @selected((string) old('subtipo_evento_id', $evento['subtipoEventoId'] ?? '') === (string) $sub['id'])>
                                {{ $tipo['nombre'] }} — {{ $sub['nombre'] }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Video (URL)</label>
                <input type="text" name="video" value="{{ old('video', $evento['video']) }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Imagen de portada (URL)</label>
                <input type="text" name="image" value="{{ old('image', $evento['image']) }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">
                    Color de marca <span class="font-normal text-slate-500">(certificados, no gafetes)</span>
                </label>
                <input type="color" name="colorHex" value="{{ old('colorHex', $evento['colorHex'] ?: '#022858') }}"
                       class="w-full h-9 border border-slate-300 rounded-md">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Deslinde de responsabilidad</label>
                <textarea name="deslinde" rows="2"
                          class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('deslinde', $evento['deslinde']) }}</textarea>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Deslinde (PDF, URL)</label>
                <input type="text" name="deslinde_pdf_url" value="{{ old('deslinde_pdf_url', $evento['deslinde_pdf_url']) }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
            Guardar datos del evento
        </button>
    </form>
</section>

{{-- 2. Categorías --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Categorías</h2>

    @foreach ($evento['categories'] as $categoria)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('categorias.update', $categoria['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ $categoria['name'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Precio</label>
                    <input type="number" step="0.01" min="0" name="price" value="{{ $categoria['price'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold mb-1">Descripción</label>
                    <input type="text" name="description" value="{{ $categoria['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <input type="color" name="color" value="{{ $categoria['color'] ?: '#022858' }}" class="flex-1 h-8 border border-slate-300 rounded">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('categorias.destroy', $categoria['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar esta categoría?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar categoría</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar categoría</p>
        <form method="POST" action="{{ route('categorias.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
            @csrf
            <div>
                <input type="text" name="name" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <input type="number" step="0.01" min="0" name="price" placeholder="Precio" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div class="col-span-2">
                <input type="text" name="description" placeholder="Descripción" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div class="flex items-end gap-2">
                <input type="color" name="color" value="#022858" class="flex-1 h-8 border border-slate-300 rounded">
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </div>
        </form>
    </div>
</section>

{{-- 3. Tipos de formulario --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Tipos de formulario</h2>

    @foreach ($evento['formTypes'] as $formType)
        <div class="border border-slate-200 rounded-md p-3 mb-3">
            <form method="POST" action="{{ route('formtypes.update', $formType['id']) }}" class="space-y-2">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div class="grid grid-cols-4 gap-2">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Nombre</label>
                        <input type="text" name="name" value="{{ $formType['name'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Ícono</label>
                        <input type="text" name="icon" value="{{ $formType['icon'] }}" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Descripción</label>
                        <input type="text" name="description" value="{{ $formType['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Tipo</label>
                        <select name="tipo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            @foreach (['deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'] as $tipo)
                                <option value="{{ $tipo }}" @selected(($formType['tipo'] ?? 'deportivo') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Cupo total</label>
                        <input type="number" min="0" name="cupo_total" value="{{ $formType['cupo_total'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Precio base</label>
                        <input type="number" step="0.01" min="0" name="precio_base" value="{{ $formType['precio_base'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Costo edición</label>
                        <input type="number" step="0.01" min="0" name="costo_edicion" value="{{ $formType['costo_edicion'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Expira (min)</label>
                        <input type="number" min="0" name="tiempo_expiracion_min" value="30" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Color <span class="font-normal text-slate-500">(gafetes)</span></label>
                        <input type="color" name="color" value="{{ $formType['color'] ?: '#00bad2' }}" class="w-full h-8 border border-slate-300 rounded">
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="requiere_categoria" value="1" {{ ($formType['requiereCategoria'] ?? true) ? 'checked' : '' }}>
                        Requiere categoría
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_team" value="1" {{ ($formType['hasTeam'] ?? false) ? 'checked' : '' }}>
                        Con equipo
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_delivery" value="1" {{ ($formType['hasDelivery'] ?? false) ? 'checked' : '' }}>
                        Con delivery de kit
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_donation" value="1" {{ ($formType['hasDonation'] ?? false) ? 'checked' : '' }}>
                        Permite donación
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_promo_code" value="1" {{ ($formType['hasPromoCode'] ?? false) ? 'checked' : '' }}>
                        Admite código promocional
                    </label>
                </div>
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded">Guardar</button>
            </form>
            <form method="POST" action="{{ route('formtypes.destroy', $formType['id']) }}" class="mt-2"
                  onsubmit="return confirm('¿Eliminar este tipo de formulario?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar tipo de formulario</button>
            </form>

            {{-- Souvenirs de este form_type --}}
            <div class="border-t border-slate-100 mt-3 pt-2">
                <span class="text-xs font-semibold text-slate-500">Souvenirs</span>
                @foreach ($formType['souvenirs'] as $souvenir)
                    <div class="grid grid-cols-5 gap-2 items-end mt-1">
                        <form method="POST" action="{{ route('souvenirs.update', $souvenir['id']) }}" class="col-span-4 grid grid-cols-4 gap-2 items-end">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                            <input type="text" name="name" value="{{ $souvenir['name'] }}" placeholder="Nombre" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <input type="text" name="icon" value="{{ $souvenir['icon'] }}" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <input type="number" step="0.01" min="0" name="price" value="{{ $souvenir['price'] }}" placeholder="Precio" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                        </form>
                        <form method="POST" action="{{ route('souvenirs.destroy', $souvenir['id']) }}"
                              onsubmit="return confirm('¿Eliminar este souvenir?')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                            <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </div>
                @endforeach

                <form method="POST" action="{{ route('souvenirs.store', $formType['id']) }}" class="grid grid-cols-5 gap-2 items-end mt-2">
                    @csrf
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <input type="text" name="name" placeholder="Nombre" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="text" name="icon" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="number" step="0.01" min="0" name="price" placeholder="Precio" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <button type="submit" class="text-xs text-brand-600 hover:underline">+ Agregar souvenir</button>
                </form>
            </div>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar tipo de formulario</p>
        <form method="POST" action="{{ route('formtypes.store', $evento['id']) }}" class="space-y-2">
            @csrf
            <div class="grid grid-cols-4 gap-2">
                <input type="text" name="name" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="icon" placeholder="Ícono" value="🏃" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="description" placeholder="Descripción" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <select name="tipo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    @foreach (['deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'] as $tipo)
                        <option value="{{ $tipo }}" @selected($tipo === 'deportivo')>{{ $tipo }}</option>
                    @endforeach
                </select>
                <input type="number" min="0" name="cupo_total" placeholder="Cupo total" value="100" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" step="0.01" min="0" name="precio_base" placeholder="Precio base" value="0" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" step="0.01" min="0" name="costo_edicion" placeholder="Costo edición" value="0" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" min="0" name="tiempo_expiracion_min" placeholder="Expira (min)" value="30" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="color" name="color" value="#00bad2" class="w-full h-8 border border-slate-300 rounded">
            </div>
            <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="requiere_categoria" value="1" checked>
                    Requiere categoría
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="has_team" value="1">
                    Con equipo
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="has_delivery" value="1">
                    Con delivery de kit
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="has_donation" value="1">
                    Permite donación
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="has_promo_code" value="1">
                    Admite código promocional
                </label>
            </div>
            <button type="submit" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">Agregar tipo de formulario</button>
        </form>
    </div>
</section>

{{-- 4. Coordenadas --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Coordenadas <span class="font-normal text-slate-500 text-sm">(punto en el mapa)</span></h2>

    @foreach ($evento['coordinates'] as $coordinate)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('coordenadas.update', $coordinate['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Lat</label>
                    <input type="number" step="any" name="lat" value="{{ $coordinate['lat'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Lng</label>
                    <input type="number" step="any" name="lng" value="{{ $coordinate['lng'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('coordenadas.destroy', $coordinate['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar esta coordenada?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar coordenada</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar coordenada</p>
        <form method="POST" action="{{ route('coordenadas.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
            @csrf
            <input type="number" step="any" name="lat" placeholder="Lat" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="number" step="any" name="lng" placeholder="Lng" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
        </form>
    </div>
</section>

{{-- 5. Ruta --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Ruta <span class="font-normal text-slate-500 text-sm">(puntos del recorrido, en orden)</span></h2>

    @foreach ($evento['route'] as $point)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('ruta.update', $point['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Lat</label>
                    <input type="number" step="any" name="lat" value="{{ $point['lat'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Lng</label>
                    <input type="number" step="any" name="lng" value="{{ $point['lng'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold mb-1">Etiqueta</label>
                    <input type="text" name="label" value="{{ $point['label'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('ruta.destroy', $point['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar este punto de ruta?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar punto</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar punto de ruta</p>
        <form method="POST" action="{{ route('ruta.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
            @csrf
            <input type="number" step="any" name="lat" placeholder="Lat" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="number" step="any" name="lng" placeholder="Lng" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="label" placeholder="Etiqueta" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
        </form>
    </div>
</section>

{{-- 6. Códigos promocionales --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Códigos promocionales</h2>

    @foreach ($evento['promoCodes'] as $promoCode)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('promocodes.update', $promoCode['id']) }}" class="grid grid-cols-6 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Código</label>
                    <input type="text" name="promo_code" value="{{ $promoCode['promo_code'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Precio fijo</label>
                    <input type="number" step="0.01" min="0" name="price" value="{{ $promoCode['price'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tipo</label>
                    <select name="discount_type" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <option value="fixed_price" @selected($promoCode['discount_type'] === 'fixed_price')>fixed_price</option>
                        <option value="percentage" @selected($promoCode['discount_type'] === 'percentage')>percentage</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">% descuento</label>
                    <input type="number" step="0.01" min="0" max="1" name="discount_percent" value="{{ $promoCode['discount_percent'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="text-xs text-slate-500">
                    {{ $promoCode['usado'] ? 'Usado' : 'Sin usar' }}
                </div>
                <div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('promocodes.destroy', $promoCode['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar este código de promoción?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar código</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar código promocional</p>
        <form method="POST" action="{{ route('promocodes.store', $evento['id']) }}" class="grid grid-cols-6 gap-2 items-end">
            @csrf
            <input type="text" name="promo_code" placeholder="Código" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="number" step="0.01" min="0" name="price" placeholder="Precio fijo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <select name="discount_type" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <option value="fixed_price">fixed_price</option>
                <option value="percentage">percentage</option>
            </select>
            <input type="number" step="0.01" min="0" max="1" name="discount_percent" placeholder="% descuento" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <div></div>
            <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
        </form>
    </div>
</section>

{{-- 7. Auspiciadores --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Auspiciadores <span class="font-normal text-slate-500 text-sm">(carrusel de logos)</span></h2>

    @foreach ($evento['auspiciadores'] as $auspiciador)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('auspiciadores.update', $auspiciador['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Nombre</label>
                    <input type="text" name="nombre" value="{{ $auspiciador['nombre'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold mb-1">Logo (URL)</label>
                    <input type="text" name="logo_url" value="{{ $auspiciador['logo_url'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Contacto</label>
                    <input type="text" name="contacto" value="{{ $auspiciador['contacto'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <input type="number" name="orden" value="{{ $auspiciador['orden'] }}" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('auspiciadores.destroy', $auspiciador['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar este auspiciador?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar auspiciador</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar auspiciador</p>
        <form method="POST" action="{{ route('auspiciadores.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
            @csrf
            <input type="text" name="nombre" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="logo_url" placeholder="Logo (URL)" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="contacto" placeholder="Contacto" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <div class="flex items-end gap-2">
                <input type="number" name="orden" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </div>
        </form>
    </div>
</section>

{{-- 8. Agenda --}}
<section class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-bold mb-4">Agenda <span class="font-normal text-slate-500 text-sm">(sesiones, ponentes, cronograma)</span></h2>

    @foreach ($evento['agenda'] as $item)
        <div class="border border-slate-200 rounded-md p-3 mb-2">
            <form method="POST" action="{{ route('agenda.update', $item['id']) }}" class="grid grid-cols-4 gap-2 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <div>
                    <label class="block text-xs font-semibold mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ $item['date'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Hora inicio</label>
                    <input type="time" name="hora_inicio" value="{{ $item['startTime'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Hora fin</label>
                    <input type="time" name="hora_fin" value="{{ $item['endTime'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tipo de formulario</label>
                    <select name="form_type_id" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <option value="">General (todo el evento)</option>
                        @foreach ($evento['formTypes'] as $formType)
                            <option value="{{ $formType['id'] }}" @selected($item['formTypeId'] == $formType['id'])>{{ $formType['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold mb-1">Título</label>
                    <input type="text" name="titulo" value="{{ $item['title'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold mb-1">Descripción</label>
                    <input type="text" name="descripcion" value="{{ $item['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Ponente</label>
                    <input type="text" name="ponente" value="{{ $item['speaker'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Cargo</label>
                    <input type="text" name="ponente_cargo" value="{{ $item['speakerRole'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Sala</label>
                    <input type="text" name="sala" value="{{ $item['room'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Ícono</label>
                    <input type="text" name="icono" value="{{ $item['icon'] }}" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('agenda.destroy', $item['id']) }}" class="mt-1"
                  onsubmit="return confirm('¿Eliminar este ítem de agenda?')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar ítem</button>
            </form>
        </div>
    @endforeach

    <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
        <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar ítem de agenda</p>
        <form method="POST" action="{{ route('agenda.store', $evento['id']) }}" class="grid grid-cols-4 gap-2 items-end">
            @csrf
            <input type="date" name="fecha" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="time" name="hora_inicio" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="time" name="hora_fin" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <select name="form_type_id" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <option value="">General (todo el evento)</option>
                @foreach ($evento['formTypes'] as $formType)
                    <option value="{{ $formType['id'] }}">{{ $formType['name'] }}</option>
                @endforeach
            </select>
            <input type="text" name="titulo" placeholder="Título" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="descripcion" placeholder="Descripción" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="ponente" placeholder="Ponente" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="ponente_cargo" placeholder="Cargo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="sala" placeholder="Sala" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <input type="text" name="icono" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
        </form>
    </div>
</section>

{{-- 9. Zona de peligro --}}
<section class="bg-white rounded-lg shadow p-6 border border-red-200">
    <h2 class="font-bold mb-2 text-red-700">Zona de peligro</h2>
    <p class="text-sm text-slate-600 mb-3">
        Solo se puede eliminar un evento publicado si todavía no tiene participantes.
    </p>
    <form method="POST" action="{{ route('eventos.destroy', $evento['id']) }}"
          onsubmit="return confirm('¿Eliminar este evento? Esta acción no se puede deshacer.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Eliminar evento
        </button>
    </form>
</section>
@endsection
