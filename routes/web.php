<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
| Administración de usuarios y roles — permiso usuarios.gestionar (solo Administrador de sistema).
*/
Route::middleware(['auth', 'permission:usuarios.gestionar'])->group(function () {
    // Antes del resource: evita que 'auditoria' se resuelva como {usuario}.
    Route::get('usuarios/auditoria', [AuditoriaController::class, 'index'])->name('usuarios.auditoria');

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['show']);

    // Designación rápida de roles desde el listado.
    Route::patch('usuarios/{usuario}/roles', [UsuarioController::class, 'cambiarRoles'])
        ->name('usuarios.roles');

    Route::get('roles', [RolController::class, 'index'])->name('roles.index');
    Route::post('roles/permisos', [RolController::class, 'storePermiso'])->name('roles.permisos.store');
    Route::put('roles/{role}/permisos', [RolController::class, 'actualizarPermisos'])->name('roles.permisos.update');
});

/*
| Registro de eventos (US-003) — solo rol Supervisor.
| NO agregar edición directa de eventos (inmutabilidad -> usar Errata).
*/
Route::middleware(['auth', 'role:Supervisor'])->group(function () {
    Route::resource('eventos', EventoController::class)->only(['create', 'store']);

    // US-025: apertura, cierre y consulta de turnos de guardia.
    // TODO: confirmar con el equipo si Administrativo/Admin de sistema también
    // consultan el historial (hoy 'turnos.index' es sólo del Supervisor).
    Route::get('turnos', [TurnoController::class, 'index'])->name('turnos.index');
    Route::post('turnos', [TurnoController::class, 'store'])->name('turnos.store');
    Route::patch('turnos/{turno}/cerrar', [TurnoController::class, 'cerrar'])->name('turnos.cerrar');

    // US-007: reenvío manual de una notificación fallida.
    Route::post('notificaciones/{notificacion}/reenviar', [NotificacionController::class, 'reenviar'])
        ->name('notificaciones.reenviar');
});

/*
| Consulta de eventos (CU04) — permiso eventos.consultar
| (Supervisor, Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:eventos.consultar'])->group(function () {
    Route::get('eventos', [EventoController::class, 'index'])->name('eventos.index');
    // US-010: detalle de un evento. whereNumber: no pisa 'eventos/create'.
    Route::get('eventos/{evento}', [EventoController::class, 'show'])
        ->whereNumber('evento')
        ->name('eventos.show');
});

/*
| Estadísticas mensuales y anuales (US-014) — permiso estadisticas.ver
| (Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:estadisticas.ver'])->group(function () {
    Route::get('estadisticas', [EstadisticaController::class, 'index'])->name('estadisticas.index');
});

/*
| Reportes PDF por turno (US-012) y exportación de eventos (US-013) —
| permiso reportes.exportar (Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:reportes.exportar'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('reportes/turnos/{turno}/pdf', [ReporteController::class, 'turnoPdf'])->name('reportes.turno.pdf');
    Route::get('reportes/eventos.xlsx', [ReporteController::class, 'eventosXlsx'])->name('reportes.eventos.xlsx');
});

/*
|--------------------------------------------------------------------------
| Rutas de dominio (Inertia) — pendientes de implementar
|--------------------------------------------------------------------------
| Protegidas con auth + roles de spatie/laravel-permission.
*/
// Route::middleware(['auth'])->group(function () {
//     Route::get('eventos/{evento}/errata', [ErrataController::class, 'create']);
//     Route::post('eventos/{evento}/errata', [ErrataController::class, 'store']);
// });

require __DIR__.'/auth.php';
