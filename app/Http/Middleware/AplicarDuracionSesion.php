<?php

namespace App\Http\Middleware;

use App\Models\Parametro;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-029: la expiración de sesión por inactividad sale de los parámetros del
 * sistema. Corre ANTES de StartSession (prepend en el grupo web), que es quien
 * lee session.lifetime para la cookie y el vencimiento del registro.
 */
class AplicarDuracionSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => Parametro::valor(Parametro::SESION_MINUTOS)]);

        return $next($request);
    }
}
