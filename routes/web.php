<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuspiciadorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CoordinateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FormTypeController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\RouteController as PanelRouteController;
use App\Http\Controllers\SouvenirController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('admin.auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Publicar: alcanzable por super_admin y por un admin scoped a su
    // propio evento — el scoping real lo valida ApiRestEvent
    // (AuthorizesEventoScope::assertCanWriteEvento()), no requiere
    // admin.superadmin acá.
    Route::post('/eventos/{evento}/publicar', [EventoController::class, 'publicar'])->name('eventos.publicar');

    // Edición/borrado de un evento existente (Fase 4) — mismo criterio
    // que publicar: alcanzable por super_admin y por un admin scoped a
    // su propio evento, sin admin.superadmin acá.
    Route::get('/eventos/{evento}/edit', [EventoController::class, 'edit'])->name('eventos.edit');
    Route::put('/eventos/{evento}', [EventoController::class, 'update'])->name('eventos.update');
    Route::delete('/eventos/{evento}', [EventoController::class, 'destroy'])->name('eventos.destroy');

    Route::post('/eventos/{evento}/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

    Route::post('/eventos/{evento}/formtypes', [FormTypeController::class, 'store'])->name('formtypes.store');
    Route::put('/formtypes/{form_type}', [FormTypeController::class, 'update'])->name('formtypes.update');
    Route::delete('/formtypes/{form_type}', [FormTypeController::class, 'destroy'])->name('formtypes.destroy');

    Route::post('/formtypes/{form_type}/souvenirs', [SouvenirController::class, 'store'])->name('souvenirs.store');
    Route::put('/souvenirs/{souvenir}', [SouvenirController::class, 'update'])->name('souvenirs.update');
    Route::delete('/souvenirs/{souvenir}', [SouvenirController::class, 'destroy'])->name('souvenirs.destroy');

    // Fase 5 — promo codes, coordenadas, ruta, auspiciadores, agenda de un
    // evento existente, más despublicar. Mismo criterio de permisos que el
    // resto del bloque admin.auth (no requiere admin.superadmin).
    Route::post('/eventos/{evento}/promocodes', [PromoCodeController::class, 'store'])->name('promocodes.store');
    Route::put('/promocodes/{promo_code}', [PromoCodeController::class, 'update'])->name('promocodes.update');
    Route::delete('/promocodes/{promo_code}', [PromoCodeController::class, 'destroy'])->name('promocodes.destroy');

    Route::post('/eventos/{evento}/coordenadas', [CoordinateController::class, 'store'])->name('coordenadas.store');
    Route::put('/coordenadas/{coordinate}', [CoordinateController::class, 'update'])->name('coordenadas.update');
    Route::delete('/coordenadas/{coordinate}', [CoordinateController::class, 'destroy'])->name('coordenadas.destroy');

    Route::post('/eventos/{evento}/ruta', [PanelRouteController::class, 'store'])->name('ruta.store');
    Route::put('/ruta/{route}', [PanelRouteController::class, 'update'])->name('ruta.update');
    Route::delete('/ruta/{route}', [PanelRouteController::class, 'destroy'])->name('ruta.destroy');

    Route::post('/eventos/{evento}/auspiciadores', [AuspiciadorController::class, 'store'])->name('auspiciadores.store');
    Route::put('/auspiciadores/{auspiciador}', [AuspiciadorController::class, 'update'])->name('auspiciadores.update');
    Route::delete('/auspiciadores/{auspiciador}', [AuspiciadorController::class, 'destroy'])->name('auspiciadores.destroy');

    Route::post('/eventos/{evento}/agenda', [AgendaItemController::class, 'store'])->name('agenda.store');
    Route::put('/agenda/{agenda_item}', [AgendaItemController::class, 'update'])->name('agenda.update');
    Route::delete('/agenda/{agenda_item}', [AgendaItemController::class, 'destroy'])->name('agenda.destroy');

    Route::patch('/eventos/{evento}/despublicar', [EventoController::class, 'despublicar'])->name('eventos.despublicar');

    Route::middleware('admin.superadmin')->group(function () {
        Route::resource('usuarios', AdminUserController::class)->except(['show']);
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
        Route::get('/eventos/create', [EventoController::class, 'create'])->name('eventos.create');
        Route::post('/eventos', [EventoController::class, 'store'])->name('eventos.store');
    });
});
