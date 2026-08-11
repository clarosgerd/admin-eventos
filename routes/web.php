<?php

use App\Http\Controllers\AcreditacionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuspiciadorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ChronoTrackController;
use App\Http\Controllers\CoordinateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardInscripcionesController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FormTypeController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\NumeracionController;
use App\Http\Controllers\ParticipantesController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\RegistroManualController;
use App\Http\Controllers\RouteController as PanelRouteController;
use App\Http\Controllers\SocioController;
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

    // Numeración de corredor/chip por evento — manual y por CSV. Mismo
    // criterio de permisos que el resto del bloque: super_admin o el
    // admin scoped a su propio evento (ApiRestEvent valida el scoping
    // real vía AuthorizesEventoScope, esto es solo la UX del panel).
    Route::get('/eventos/{evento}/numeracion', [NumeracionController::class, 'index'])->name('numeracion.index');
    Route::get('/eventos/{evento}/numeracion/csv', [NumeracionController::class, 'csvDownload'])->name('numeracion.csv.download');
    Route::post('/eventos/{evento}/numeracion/csv', [NumeracionController::class, 'csvUpload'])->name('numeracion.csv.upload');
    Route::patch('/numeracion/{referencia}/{participante}', [NumeracionController::class, 'update'])->name('numeracion.update');

    // Acreditación (check-in) escaneando el QR de referencia — mismo
    // criterio de permisos que Numeración (super_admin o admin scoped a
    // su propio evento). lookup/checkin son endpoints JSON (llamados por
    // fetch desde la pantalla, no navegación de página completa).
    Route::get('/eventos/{evento}/acreditacion', [AcreditacionController::class, 'index'])->name('acreditacion.index');
    Route::post('/eventos/{evento}/acreditacion/lookup', [AcreditacionController::class, 'lookup'])->name('acreditacion.lookup');
    Route::patch('/eventos/{evento}/acreditacion/{participante}', [AcreditacionController::class, 'checkin'])->name('acreditacion.checkin');

    // Dashboard de inscripciones (mismo conteo que ya se manda por correo
    // al organizador) y edición restringida de datos de contacto del
    // participante — mismo criterio de permisos que el resto del bloque:
    // super_admin o el admin scoped a su propio evento.
    Route::get('/eventos/{evento}/dashboard', [DashboardInscripcionesController::class, 'show'])->name('eventos.dashboard');
    Route::get('/eventos/{evento}/participantes', [ParticipantesController::class, 'index'])->name('participantes.index');
    Route::patch('/eventos/{evento}/participantes/{participante}', [ParticipantesController::class, 'update'])->name('participantes.update');

    // Sync de resultados desde ChronoTrack — ver
    // brain/groovy-chasing-ladybug.md Parte B. Mismo criterio de permisos
    // que el resto del bloque.
    Route::get('/eventos/{evento}/resultados', [ChronoTrackController::class, 'index'])->name('chronotrack.index');
    Route::post('/eventos/{evento}/resultados/sincronizar', [ChronoTrackController::class, 'sincronizar'])->name('chronotrack.sincronizar');

    // Gafetes/credenciales y certificados en PDF — proxy de solo lectura,
    // mismo criterio de permisos que el resto (super_admin o admin scoped).
    Route::get('/eventos/{evento}/gafetes-pdf', [EventoController::class, 'gafetesPdf'])->name('eventos.gafetes-pdf');
    Route::get('/eventos/{evento}/certificados-pdf', [EventoController::class, 'certificadosPdf'])->name('eventos.certificados-pdf');

    Route::middleware('admin.superadmin')->group(function () {
        Route::resource('usuarios', AdminUserController::class)->except(['show']);
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
        Route::get('/eventos/create', [EventoController::class, 'create'])->name('eventos.create');
        Route::post('/eventos', [EventoController::class, 'store'])->name('eventos.store');

        // Carga masiva de inscripciones por CSV — solo super_admin (ver
        // brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md).
        Route::get('/eventos/{evento}/registro-manual', [RegistroManualController::class, 'index'])->name('registro-manual.index');
        Route::get('/eventos/{evento}/registro-manual/plantilla', [RegistroManualController::class, 'plantilla'])->name('registro-manual.plantilla');
        Route::post('/eventos/{evento}/registro-manual', [RegistroManualController::class, 'store'])->name('registro-manual.store');

        // Consolidación financiera (liquidación de utilidades) — solo
        // super_admin, ver elascenso/event/brain/ (sesión 11/08/2026).
        // Socios es config global; la liquidación es por evento.
        Route::resource('socios', SocioController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/eventos/{evento}/liquidacion', [LiquidacionController::class, 'show'])->name('liquidacion.show');
        Route::post('/eventos/{evento}/liquidacion', [LiquidacionController::class, 'store'])->name('liquidacion.store');
    });
});
