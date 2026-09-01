<?php /* Índice de catálogos globales — ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. */ ?>
@extends('layouts.app')

@section('title', 'Catálogos — Admin Eventos')

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-bold">Catálogos</h1>
    <p class="text-sm text-slate-500">Config global compartida por todos los eventos.</p>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <a href="{{ route('catalogos.paises.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">País</div>
        <div class="text-xs text-slate-500 mt-1">Catálogo de países.</div>
    </a>
    <a href="{{ route('catalogos.ciudades.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Ciudad</div>
        <div class="text-xs text-slate-500 mt-1">Ciudades por país.</div>
    </a>
    <a href="{{ route('catalogos.sexos.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Sexo</div>
        <div class="text-xs text-slate-500 mt-1">Catálogo de sexo (respalda categories.sexo_id).</div>
    </a>
    <a href="{{ route('catalogos.generos.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Género</div>
        <div class="text-xs text-slate-500 mt-1">Opciones de género del formulario de inscripción (respalda participantes.genero).</div>
    </a>
    <a href="{{ route('catalogos.tipos-evento.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Tipo de evento</div>
        <div class="text-xs text-slate-500 mt-1">Disciplinas (Carrera de Ruta, Ciclismo, Congreso...).</div>
    </a>
    <a href="{{ route('catalogos.subtipos-evento.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Subtipo de evento</div>
        <div class="text-xs text-slate-500 mt-1">Variantes dentro de cada tipo de evento.</div>
    </a>
    <a href="{{ route('catalogos.relaciones-contacto.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Relación de contacto</div>
        <div class="text-xs text-slate-500 mt-1">Relación del contacto de emergencia del participante.</div>
    </a>
    <a href="{{ route('catalogos.formas-pago.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="font-semibold">Formas de pago</div>
        <div class="text-xs text-slate-500 mt-1">Catálogo del sistema (SIP, Multipago, Meru...). Activarlas por organizador se hace desde Organizadores.</div>
    </a>
</div>
@endsection
