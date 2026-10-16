<?php

namespace App\Http\Resources\V1;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública (Datos Abiertos) de un evento.
 *
 * NO expone la descripción libre ni quién lo registró: pueden contener datos
 * personales. Usa los valores vigentes (con fe de errata), igual que las
 * estadísticas. Campos confirmados por el equipo (2026-10-15).
 *
 * @mixin Evento
 */
class EventoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tipo = $this->tipoEvento;
        $punto = $this->puntoMonitoreo;

        return [
            'id' => $this->id,
            'fecha' => $this->fecha_hora->format('Y-m-d'),
            'hora' => $this->fecha_hora->format('H:i:s'),
            'categoria' => $tipo->grupoEvento->codigo,
            'categoria_nombre' => $tipo->grupoEvento->nombre,
            'tipo' => $tipo->nombre,
            'cuantificable' => (bool) $tipo->es_cuantificable,
            'punto_monitoreo' => $punto ? [
                'codigo' => $punto->codigo,
                'nombre' => $punto->nombre,
                'tipo' => $punto->tipoPuntoMonitoreo->codigo,
                'barrio' => $punto->barrio?->nombre,
                'latitud' => (float) $punto->latitud,
                'longitud' => (float) $punto->longitud,
            ] : null,
            'corregido' => $this->whenCounted('erratas', fn ($n) => $n > 0),
        ];
    }
}
