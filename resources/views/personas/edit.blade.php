@extends('layouts.app')

@section('title', 'Editar persona — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Editar persona — {{ $persona['nombre'] }} {{ $persona['apellido'] }}</h1>
    <a href="{{ route('personas.index') }}" class="text-sm text-brand-600 hover:underline">← Volver</a>
</div>

<form method="POST" action="{{ route('personas.update', $persona['id']) }}" class="bg-white rounded-lg shadow p-5">
    @csrf
    @method('PUT')
    @include('personas._form')

    <div class="mt-5">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Guardar cambios
        </button>
    </div>
</form>
@endsection
