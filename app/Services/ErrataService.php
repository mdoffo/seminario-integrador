<?php

namespace App\Services;

use App\Models\Errata;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único mecanismo de corrección permitido sobre un Evento (regla de negocio 1).
 *
 * SINGLE RESPONSIBILITY: crear registros de "fe de errata".
 * REGLA DE NEGOCIO: el Evento original NUNCA se sobreescribe. Una Errata es
 * un registro separado que referencia al evento y guarda campo_corregido,
 * valor_anterior, valor_nuevo, motivo y quién la hizo.
 */
class ErrataService
{
    /**
     * Compara los datos corregidos con los valores vigentes y registra una
     * errata por cada campo que cambió, todas con el mismo motivo.
     *
     * @param  array{tipo_evento_id: int|string, fecha: string, hora: string, punto_monitoreo_id?: int|string|null, timestamp_video?: string|null, descripcion: string}  $datos
     * @return Collection<int, Errata>
     *
     * @throws DomainException si no hay cambios respecto de los valores vigentes.
     */
    public function registrar(Evento $evento, array $datos, string $motivo, User $autor): Collection
    {
        $evento->loadMissing('erratas');
        $vigentes = $evento->valoresVigentes();
        $puntoId = $datos['punto_monitoreo_id'] ?? null;

        $nuevos = [
            'tipo_evento_id' => (string) $datos['tipo_evento_id'],
            'fecha_hora' => "{$datos['fecha']} {$datos['hora']}",
            'punto_monitoreo_id' => filled($puntoId) ? (string) $puntoId : null,
            // Sin PM no hay video asociado.
            'timestamp_video' => filled($puntoId) ? ($datos['timestamp_video'] ?? null) : null,
            'descripcion' => $datos['descripcion'],
        ];

        $cambios = collect($nuevos)->filter(
            fn ($nuevo, $campo) => $this->normalizar($vigentes[$campo]) !== $this->normalizar($nuevo),
        );

        if ($cambios->isEmpty()) {
            throw new DomainException('No hay cambios respecto de los datos vigentes del evento.');
        }

        return DB::transaction(fn () => $cambios->map(fn ($nuevo, $campo) => Errata::create([
            'evento_id' => $evento->id,
            'usuario_id' => $autor->id,
            'campo_corregido' => $campo,
            'valor_anterior' => $this->normalizar($vigentes[$campo]),
            'valor_nuevo' => $this->normalizar($nuevo),
            'motivo' => $motivo,
        ]))->values());
    }

    /**
     * Valor legible de un campo para mostrar en pantalla.
     */
    public function legible(string $campo, ?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($campo) {
            'tipo_evento_id' => TipoEvento::find($valor)?->nombre ?? "#{$valor}",
            'punto_monitoreo_id' => ($p = PuntoMonitoreo::find($valor)) ? "{$p->codigo} — {$p->nombre}" : "#{$valor}",
            'fecha_hora' => Carbon::parse($valor)->format('d/m/Y H:i:s'),
            default => $valor,
        };
    }

    private function normalizar(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
