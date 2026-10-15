<?php

use App\Http\Controllers\Api\V1\EstadisticaApiController;
use App\Http\Controllers\Api\V1\EventoApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API pública — Datos Abiertos, SOLO LECTURA, versionada
|--------------------------------------------------------------------------
| Consumo externo (Gobierno Abierto). Distinta del frontend Inertia.
| Auth: token personal de Sanctum con la habilidad 'datos-abiertos:leer'
| (se emite con `php artisan api:token`). Límite: 60 pedidos por minuto.
*/

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'abilities:datos-abiertos:leer', 'throttle:60,1'])
    ->name('api.v1.')
    ->group(function () {
        Route::get('eventos', [EventoApiController::class, 'index'])->name('eventos.index');
        Route::get('eventos/{evento}', [EventoApiController::class, 'show'])->whereNumber('evento')->name('eventos.show');
        Route::get('estadisticas/mensual/{anio}/{mes}', [EstadisticaApiController::class, 'mensual'])
            ->whereNumber(['anio', 'mes'])->name('estadisticas.mensual');
        Route::get('estadisticas/anual/{anio}', [EstadisticaApiController::class, 'anual'])
            ->whereNumber('anio')->name('estadisticas.anual');
    });
