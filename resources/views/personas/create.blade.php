@extends('layouts.app')

@section('title', 'Nueva persona — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Nueva persona</h1>
    <a href="{{ route('personas.index') }}" class="text-sm text-brand-600 hover:underline">← Volver</a>
</div>

<form method="POST" action="{{ route('personas.store') }}" class="bg-white rounded-lg shadow p-5">
    @csrf
    @include('personas._form')

    <div class="mt-5">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Crear persona
        </button>
    </div>
</form>
@endsection
