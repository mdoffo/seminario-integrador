<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Services\NotificacionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * US-007: reenvío manual de notificaciones fallidas (Supervisor).
 *
 * // TODO: confirmar con el equipo si cualquier Supervisor puede reenviar o
 * //       solo el que registró el evento (hoy: cualquiera).
 */
class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function reenviar(Request $request, Notificacion $notificacion): RedirectResponse
    {
        try {
            $this->notificaciones->reenviar($notificacion, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Notificación #{$notificacion->id} reenviada.");
    }
}
