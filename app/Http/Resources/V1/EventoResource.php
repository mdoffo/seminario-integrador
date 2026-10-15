<?php

namespace App\Http\Resources\V1;

use App\Models\Evento;
use App\Models\TipoEvento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública (Datos Abiertos) de un evento.
 *
 * NO expone la descripción libre ni quién lo registró: pueden contener datos
 * personales. // TODO: confirmar con el equipo/Municipalidad qué campos se
 * publican y si se usan los valores vigentes (con fe de errata) o los
 * originales (hoy: originales, igual que las estadísticas).
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
            'categoria' => $tipo->categoria,
            'categoria_nombre' => TipoEvento::CATEGORIAS[$tipo->categoria] ?? $tipo->categoria,
            'tipo' => $tipo->nombre,
            'cuantificable' => (bool) $tipo->es_cuantificable,
            'punto_monitoreo' => $punto ? [
                'codigo' => $punto->codigo,
                'nombre' => $punto->nombre,
                'jurisdiccion' => $punto->jurisdiccion,
                'latitud' => (float) $punto->latitud,
                'longitud' => (float) $punto->longitud,
            ] : null,
            'corregido' => $this->whenCounted('erratas', fn ($n) => $n > 0),
        ];
    }
}
