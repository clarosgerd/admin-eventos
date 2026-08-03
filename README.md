# admin-eventos — Panel de administración de eventos

Panel de administración (el "tercer frontend" del ecosistema Pass2Go,
junto a [`elascenso/event`](../event) y [`elascenso/delivery`](../delivery))
para dar de alta, editar, publicar/despublicar y borrar eventos —
categorías, form_types, souvenirs, promo codes, coordenadas, ruta,
auspiciadores y agenda — más gestión de usuarios administradores y su
log de auditoría.

Laravel + Blade + Tailwind (CDN), sin JS de build step, mismo patrón que
`elascenso/delivery`. **No tiene base de datos propia**: todo el estado
de negocio (eventos, usuarios admin, audit logs) vive en
[`ApiRestEvent`](../ApiRestEvent), que es la fuente de verdad. Este panel
es un cliente HTTP puro de esa API (`app/Services/ApiRestEventClient.php`),
con sesión de Laravel (`SESSION_DRIVER=file`) guardando solo el token de
`ApiRestEvent` — el navegador nunca ve ese token, solo la cookie de sesión
del panel.

## Roles

- **`super_admin`**: ve y administra todos los eventos, gestiona usuarios
  (`/usuarios`) y auditoría (`/auditoria`), y es el único que puede dar de
  alta un evento nuevo (`/eventos/create`).
- **`admin`**: scoped a un único evento asignado — sin selector de evento
  en el dashboard, sin acceso a `/usuarios` ni `/auditoria`. Puede editar,
  publicar y despublicar su propio evento.

La autorización real la valida `ApiRestEvent`
(`AuthorizesEventoScope::assertCanWriteEvento()`); los middlewares locales
(`admin.auth`, `admin.superadmin`) son solo UX — redirect a `/login` o
403 antes de golpear la API.

## Correr localmente

```
composer install
cp .env.example .env   # completar EXTERNAL_API_BASE si ApiRestEvent no corre en :8000
php artisan key:generate
php artisan serve --port=8011
```

`ApiRestEvent` debe estar corriendo (por defecto se espera en
`http://127.0.0.1:8000/api/v1`, configurable con `EXTERNAL_API_BASE` en
`config/services.php`). El puerto 8010 es deliberado para no chocar con
`ApiRestEvent` (8000).

## Estructura

- `app/Services/ApiRestEventClient.php` — único punto de salida HTTP hacia
  `ApiRestEvent`; arma el header `Authorization` desde `session('admin_token')`.
- `app/Http/Middleware/EnsureAdminAuthenticated.php` /
  `EnsureSuperAdminSession.php` — guardas de sesión (`admin.auth`,
  `admin.superadmin`).
- `app/Http/Controllers/` — un controlador por entidad (`EventoController`,
  `CategoriaController`, `FormTypeController`, `SouvenirController`,
  `PromoCodeController`, `CoordinateController`, `RouteController`,
  `AuspiciadorController`, `AgendaItemController`) más
  `AuthController`/`DashboardController`/`AdminUserController`/`AuditLogController`.
- `resources/views/` — `auth/login`, `dashboard`, `eventos/create` (alta
  anidada completa), `eventos/edit` (edición fila a fila de cada entidad),
  `usuarios/`, `auditoria/`.
- `routes/web.php` — todas las rutas de escritura viven bajo `admin.auth`;
  usuarios/auditoría/alta de evento además bajo `admin.superadmin`.

## Estado

Fases 0–5 del plan implementadas y verificadas (ver
`elascenso/event/brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md`): login,
dashboard con scope por rol, usuarios, auditoría, alta/edición núcleo de
evento (categorías/form_types/souvenirs), y alta/edición de promo
codes/coordenadas/ruta/auspiciadores/agenda + publicar/despublicar.

Pendiente: verificación completa en navegador real (la de Fase 1 se hizo
por HTTP/curl, no cubre JS del lado cliente), y la decisión de
nombre/dominio de despliegue real — hosting será el mismo cPanel de
`ApiRestEvent`/`elascenso/delivery`, con acceso solo por File Manager (sin
SSH).
