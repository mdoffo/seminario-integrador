<?php

namespace App\Providers;

use App\Contracts\ConversorPdfAInterface;
use App\Contracts\NotificadorInterface;
use App\Factories\NotificadorFactory;
use App\Services\PdfA\GhostscriptConversorPdfA;
use App\Services\PdfA\SinConversionPdfA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * DEPENDENCY INVERSION: interfaz -> implementación concreta.
     * NotificadorInterface se resuelve con NotificadorFactory según
     * config('notificaciones.canal'): 'log' en desarrollo, 'whatsapp' en producción.
     * ExportadorInterface NO se bindea acá: tiene varias implementaciones y se
     * resuelve vía ExportadorFactory según el formato pedido.
     */
    public function register(): void
    {
        $this->app->bind(
            NotificadorInterface::class,
            fn ($app) => $app->make(NotificadorFactory::class)->crear(),
        );

        // RNF PDF/A: Ghostscript, salvo que esté deshabilitado (tests, entornos sin gs).
        $this->app->bind(
            ConversorPdfAInterface::class,
            fn () => config('reportes.pdfa.habilitado')
                ? new GhostscriptConversorPdfA(
                    config('reportes.pdfa.ghostscript'),
                    config('reportes.pdfa.perfil_icc'),
                    config('reportes.pdfa.timeout'),
                )
                : new SinConversionPdfA,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Fechas y diffForHumans en español (toda la UI está en es).
        Carbon::setLocale(config('app.locale'));
    }
}
