@extends('layouts.app')

@section('title', 'Clonar evento — Admin Eventos')

@section('content')
<h1 class="text-lg font-bold mb-1">Clonar evento</h1>
<p class="text-sm text-slate-600 mb-4">
    Se copia toda la configuración de <strong>{{ $evento['name'] }}</strong> (categorías, tipos de
    formulario, souvenirs, talleres, agenda, promo codes) a un evento nuevo. No se copia ningún
    participante, inscripción ni pago. El evento nuevo nace como <strong>borrador</strong> — revisá las
    fechas antes de publicarlo.
</p>

<form method="POST" action="{{ route('eventos.clonar', $evento['id']) }}" class="space-y-6">
    @csrf

    <section class="bg-white rounded-lg shadow p-6 max-w-xl">
        <label class="block text-sm font-semibold mb-1">Nombre del evento nuevo</label>
        <input type="text" name="nombre" value="{{ old('nombre', $evento['name'] . ' (copia)') }}" required
               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        @error('nombre')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </section>

    <div class="flex gap-3">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-md text-sm">
            Clonar evento
        </button>
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:underline self-center">Cancelar</a>
    </div>
</form>
@endsection
