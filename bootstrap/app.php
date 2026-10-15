<?php

use App\Http\Middleware\AplicarDuracionSesion;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Los listeners se registran explícitamente en EventServiceProvider. Con el
    // auto-discovery activo quedaban registrados dos veces (auditoría duplicada).
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás del nginx del host (reverse proxy): confiar en los headers
        // X-Forwarded-* para detectar HTTPS y generar URLs/cookies correctas.
        // La app solo es accesible por el proxy (127.0.0.1), por eso '*'.
        $middleware->trustProxies(at: '*');

        // US-029: antes de StartSession, para que tome la duración configurada.
        $middleware->web(prepend: [
            AplicarDuracionSesion::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias de middleware de spatie/laravel-permission (roles y permisos).
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // Habilidades de los tokens de Sanctum (API pública /api/v1).
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
