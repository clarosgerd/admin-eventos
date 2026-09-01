<?php

use App\Http\Controllers\AcreditacionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuspiciadorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\PaisController;
use App\Http\Controllers\FormasPagoController;
use App\Http\Controllers\GeneroController;
use App\Http\Controllers\RelacionContactoController;
use App\Http\Controllers\SexoController;
use App\Http\Controllers\SipBancoController;
use App\Http\Controllers\SubtipoEventoController;
use App\Http\Controllers\TipoEventoController;
use App\Http\Controllers\CategoryPricePeriodController;
use App\Http\Controllers\ChronoTrackController;
use App\Http\Controllers\CoordinateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardInscripcionesController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FormTypeController;
use App\Http\Controllers\ItemBodegaController;
use App\Http\Controllers\ItemStockController;
use App\Http\Controllers\ListaEsperaController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\NumeracionController;
use App\Http\Controllers\OrganizadorController;
use App\Http\Controllers\ParticipantesController;
use App\Http\Controllers\ParticipantesDetalleController;
use App\Http\Controllers\PresupuestoCategoriaController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\AsistenciaSesionController;
use App\Http\Controllers\SesionCongresoController;
use App\Http\Controllers\TallerCongresoController;
use App\Http\Controllers\PreguntaController;
use App\Http\Controllers\RegistroManualController;
use App\Http\Controllers\RouteController as PanelRouteController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\SouvenirController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['admin.auth', 'admin.restrict-cajero'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Caja de cobro presencial (14/08/2026) — ver
    // ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
    // Accesible por admin/cajero/super_admin — el scoping real (cajero
    // solo su propio evento) lo valida ApiRestEvent.
    Route::get('/eventos/{evento}/caja', [CajaController::class, 'index'])->name('caja.index');
    Route::post('/eventos/{evento}/caja/turno/abrir', [CajaController::class, 'abrirTurno'])->name('caja.turno.abrir');
    Route::post('/eventos/{evento}/caja/turno/{turno}/cerrar', [CajaController::class, 'cerrarTurno'])->name('caja.turno.cerrar');
    Route::get('/eventos/{evento}/caja/nueva', [CajaController::class, 'nueva'])->name('caja.nueva');
    Route::post('/eventos/{evento}/caja/nueva', [CajaController::class, 'storeNueva'])->name('caja.nueva.store');
    Route::get('/eventos/{evento}/caja/buscar', [CajaController::class, 'buscarPage'])->name('caja.buscar');
    Route::get('/eventos/{evento}/caja/buscar/resultados', [CajaController::class, 'buscar'])->name('caja.buscar.resultados');
    // Prellenado desde `personas` (20/08/2026).
    Route::get('/eventos/{evento}/caja/persona', [CajaController::class, 'buscarPersona'])->name('caja.persona');
    Route::post('/eventos/{evento}/caja/registrations/{referencia}/cobrar-pendiente', [CajaController::class, 'cobrarPendiente'])->name('caja.cobrar-pendiente');
    Route::get('/eventos/{evento}/caja/registrations/{referencia}/editar', [CajaController::class, 'editar'])->name('caja.editar');
    Route::post('/eventos/{evento}/caja/registrations/{referencia}/editar', [CajaController::class, 'storeEditar'])->name('caja.editar.store');
    // Comprobante imprimible (20/08/2026) — el cajero necesita algo físico
    // para entregar; reusa el mismo detalle que /registrations/{reference}
    // (ya se pide en editar()), no hace falta un endpoint nuevo en
    // ApiRestEvent.
    Route::get('/eventos/{evento}/caja/registrations/{referencia}/comprobante', [CajaController::class, 'eticket'])->name('caja.eticket');
    // Cierres de caja — el control pedido por el stakeholder; solo
    // admin/super_admin en la práctica (ApiRestEvent rechaza a un cajero
    // con 403, ver assertCanWriteEvento()).
    Route::get('/eventos/{evento}/caja/cierres', [CajaController::class, 'cierres'])->name('caja.cierres');
    // Detalle de un turno (27/08/2026) — drill-down de movimientos.
    Route::get('/eventos/{evento}/caja/cierres/{turno}', [CajaController::class, 'cierreDetalle'])->name('caja.cierres.detalle');

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

    // Precios por período (12/08/2026) — ver
    // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md.
    // Mismo criterio de permisos que el resto del bloque.
    Route::get('/categorias/{category}/periodos', [CategoryPricePeriodController::class, 'index'])->name('categorias.periodos.index');
    Route::post('/categorias/{category}/periodos', [CategoryPricePeriodController::class, 'store'])->name('categorias.periodos.store');
    Route::put('/categorias-periodos/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'update'])->name('categorias.periodos.update');
    Route::delete('/categorias-periodos/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'destroy'])->name('categorias.periodos.destroy');

    Route::post('/eventos/{evento}/formtypes', [FormTypeController::class, 'store'])->name('formtypes.store');
    Route::put('/formtypes/{form_type}', [FormTypeController::class, 'update'])->name('formtypes.update');
    Route::delete('/formtypes/{form_type}', [FormTypeController::class, 'destroy'])->name('formtypes.destroy');

    Route::post('/formtypes/{form_type}/souvenirs', [SouvenirController::class, 'store'])->name('souvenirs.store');
    Route::put('/souvenirs/{souvenir}', [SouvenirController::class, 'update'])->name('souvenirs.update');
    Route::delete('/souvenirs/{souvenir}', [SouvenirController::class, 'destroy'])->name('souvenirs.destroy');

    // Preguntas adicionales del formulario de inscripción (20/08/2026) —
    // ver PreguntaController y ApiRestEvent/FormularioCamposController.
    Route::post('/formtypes/{form_type}/preguntas', [PreguntaController::class, 'store'])->name('preguntas.store');
    Route::put('/preguntas/{pregunta}', [PreguntaController::class, 'update'])->name('preguntas.update');
    Route::delete('/preguntas/{pregunta}', [PreguntaController::class, 'destroy'])->name('preguntas.destroy');

    // Kit/tallas/stock (11/08/2026) — stock por talla/sexo de un ítem del
    // kit, y lista de espera del evento. Mismo criterio de permisos que
    // el resto del bloque (super_admin o admin scoped a su propio
    // evento). Ver PRD-kit-tallas-stock-lista-espera.md.
    Route::get('/souvenirs/{souvenir}/stock', [ItemStockController::class, 'index'])->name('souvenirs.stock.index');
    Route::post('/souvenirs/{souvenir}/stock', [ItemStockController::class, 'store'])->name('souvenirs.stock.store');
    Route::put('/item-stock/{item_stock}', [ItemStockController::class, 'update'])->name('souvenirs.stock.update');
    Route::delete('/item-stock/{item_stock}', [ItemStockController::class, 'destroy'])->name('souvenirs.stock.destroy');

    Route::get('/eventos/{evento}/lista-espera', [ListaEsperaController::class, 'index'])->name('lista-espera.index');

    // Bodega de stock por evento (14/08/2026) — ver
    // ApiRestEvent/brain/api_rest_event/PLAN-BODEGA-STOCK-EVENTO-14082026.md.
    Route::get('/eventos/{evento}/bodega', [ItemBodegaController::class, 'index'])->name('bodega.index');
    Route::post('/eventos/{evento}/bodega', [ItemBodegaController::class, 'store'])->name('bodega.store');
    Route::put('/eventos/{evento}/bodega/{item_bodega}', [ItemBodegaController::class, 'update'])->name('bodega.update');
    Route::delete('/eventos/{evento}/bodega/{item_bodega}', [ItemBodegaController::class, 'destroy'])->name('bodega.destroy');
    Route::post('/eventos/{evento}/bodega/{item_bodega}/asignar', [ItemBodegaController::class, 'asignar'])->name('bodega.asignar');

    // Mapa de ubicación de delivery (12/08/2026) — vista de solo lectura,
    // mismo criterio de permisos que lista-espera.
    Route::get('/eventos/{evento}/delivery', [DeliveryController::class, 'index'])->name('delivery.index');

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
    // CSV del Reporte de talleres (20/08/2026) — sin agrupar, ordenado por
    // fecha, ver DashboardInscripcionesController::csvTalleres().
    Route::get('/eventos/{evento}/dashboard/talleres/csv', [DashboardInscripcionesController::class, 'csvTalleres'])->name('eventos.dashboard.talleres.csv');
    Route::get('/eventos/{evento}/participantes', [ParticipantesController::class, 'index'])->name('participantes.index');
    Route::patch('/eventos/{evento}/participantes/{participante}', [ParticipantesController::class, 'update'])->name('participantes.update');
    // Reporte detallado de inscritos (15/08/2026) — drill-down desde las
    // tarjetas del Dashboard, ver ParticipantesDetalleController. Rutas
    // separadas a propósito de `participantes.index` (esa es la pantalla
    // de edición de contacto, otra UX/contrato).
    Route::get('/eventos/{evento}/participantes/detalle', [ParticipantesDetalleController::class, 'index'])->name('participantes.detalle');
    Route::get('/eventos/{evento}/participantes/detalle/csv', [ParticipantesDetalleController::class, 'csvDownload'])->name('participantes.detalle.csv');
    Route::post('/eventos/{evento}/participantes/detalle/{referencia}/confirmar-pago-manual', [ParticipantesDetalleController::class, 'confirmarPagoManual'])->name('participantes.detalle.confirmar-pago-manual');

    // Presupuesto de un evento (control financiero del organizador) —
    // mismo criterio de permisos que Numeración/Participantes: super_admin
    // o el admin scoped a su propio evento. Ver elascenso/event/brain/
    // (sesión 11/08/2026).
    Route::get('/eventos/{evento}/presupuesto', [PresupuestoController::class, 'index'])->name('presupuesto.index');
    Route::post('/eventos/{evento}/presupuesto', [PresupuestoController::class, 'store'])->name('presupuesto.store');
    Route::put('/eventos/{evento}/presupuesto/{presupuesto}', [PresupuestoController::class, 'update'])->name('presupuesto.update');
    Route::delete('/eventos/{evento}/presupuesto/{presupuesto}', [PresupuestoController::class, 'destroy'])->name('presupuesto.destroy');

    // Agenda y sesiones de congreso — mismo criterio de permisos que
    // Presupuesto/Numeración: super_admin o el admin scoped a su propio
    // evento. Ver elascenso/event/brain/ (sesión 11/08/2026).
    Route::get('/eventos/{evento}/sesiones', [SesionCongresoController::class, 'index'])->name('sesiones.index');
    Route::post('/eventos/{evento}/sesiones', [SesionCongresoController::class, 'store'])->name('sesiones.store');
    Route::put('/eventos/{evento}/sesiones/{sesion}', [SesionCongresoController::class, 'update'])->name('sesiones.update');
    Route::delete('/eventos/{evento}/sesiones/{sesion}', [SesionCongresoController::class, 'destroy'])->name('sesiones.destroy');
    Route::get('/eventos/{evento}/sesiones-reporte', [AsistenciaSesionController::class, 'reporte'])->name('sesiones.reporte');
    Route::get('/eventos/{evento}/sesiones/{sesion}/acreditacion', [AsistenciaSesionController::class, 'index'])->name('sesiones.acreditacion.index');
    Route::post('/eventos/{evento}/sesiones/{sesion}/acreditacion/lookup', [AsistenciaSesionController::class, 'lookup'])->name('sesiones.acreditacion.lookup');
    Route::patch('/eventos/{evento}/sesiones/{sesion}/acreditacion/{participante}', [AsistenciaSesionController::class, 'checkin'])->name('sesiones.acreditacion.checkin');
    Route::post('/eventos/{evento}/sesiones/{sesion}/acreditacion/checkin-bulk', [AsistenciaSesionController::class, 'checkinBulk'])->name('sesiones.acreditacion.checkin-bulk');
    // Asignación de staff/ayudantes (13/08/2026) — ver
    // elascenso/event/brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md.
    Route::post('/eventos/{evento}/sesiones/{sesion}/staff', [SesionCongresoController::class, 'assignStaff'])->name('sesiones.staff.store');
    Route::delete('/eventos/{evento}/sesiones/{sesion}/staff/{participante}', [SesionCongresoController::class, 'unassignStaff'])->name('sesiones.staff.destroy');

    // Congresos con talleres (18/08/2026) — ver
    // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. Mismo
    // criterio de permisos que sesiones (super_admin o admin scoped).
    Route::get('/eventos/{evento}/talleres', [TallerCongresoController::class, 'index'])->name('talleres.index');
    Route::post('/eventos/{evento}/talleres', [TallerCongresoController::class, 'store'])->name('talleres.store');
    Route::put('/eventos/{evento}/talleres/{taller}', [TallerCongresoController::class, 'update'])->name('talleres.update');
    Route::delete('/eventos/{evento}/talleres/{taller}', [TallerCongresoController::class, 'destroy'])->name('talleres.destroy');

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

        // Bancos SIP (31/08/2026) — credenciales de cobro por organizador,
        // más sensible que el resto del panel (config con secretos
        // reales). Ver ApiRestEvent/brain/api_rest_event/
        // PLAN-SIP-MULTIBANCO-28082026.md.
        Route::resource('sip-bancos', SipBancoController::class)->except(['show']);
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

        // CRUD de organizadores — config global (dueños de eventos), solo
        // super_admin. Ver PRD-organizadores-crud.md (sesión 11/08/2026).
        Route::resource('organizadores', OrganizadorController::class)->only(['index', 'store', 'update', 'destroy']);

        // Formas de pago activas por organizador (19/08/2026) — ver
        // elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md.
        Route::get('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'formasPago'])->name('organizadores.formas-pago');
        Route::put('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'updateFormasPago'])->name('organizadores.formas-pago.update');

        Route::get('/eventos/{evento}/liquidacion', [LiquidacionController::class, 'show'])->name('liquidacion.show');
        Route::post('/eventos/{evento}/liquidacion', [LiquidacionController::class, 'store'])->name('liquidacion.store');

        // Catálogo de rubros del presupuesto — solo super_admin (config
        // global, igual que Socios).
        Route::resource('presupuesto-categorias', PresupuestoCategoriaController::class)->only(['index', 'store', 'update', 'destroy']);

        // Catálogos globales (15/08/2026) — País/Ciudad/Sexo/Tipo de
        // evento/Subtipo de evento/Relación de contacto, todos config
        // global, solo super_admin. Ver
        // elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
        Route::view('/catalogos', 'catalogos.index')->name('catalogos.index');
        Route::prefix('catalogos')->name('catalogos.')->group(function () {
            Route::resource('paises', PaisController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('ciudades', CiudadController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('sexos', SexoController::class)->only(['index', 'store', 'update', 'destroy']);
            // Género de participante (31/08/2026) — respalda
            // participantes.genero, NO confundir con sexos (categories.sexo_id).
            Route::resource('generos', GeneroController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('tipos-evento', TipoEventoController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('subtipos-evento', SubtipoEventoController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('relaciones-contacto', RelacionContactoController::class)->only(['index', 'store', 'update', 'destroy']);

            // Formas de pago (19/08/2026) — ver
            // elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md.
            Route::resource('formas-pago', FormasPagoController::class)->only(['index', 'store', 'update', 'destroy']);
        });
    });
});
