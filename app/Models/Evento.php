<?php

namespace App\Models;

use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Evento detectado por videovigilancia. INMUTABLE tras su creación:
 * sin edición ni borrado; toda corrección va por Errata.
 *
 * Campos: tipo_evento_id, punto_monitoreo_id (nullable, ver requiere_ubicacion),
 * turno_id, usuario_id (Supervisor autor), fecha_hora (del hecho),
 * timestamp_video (hh:mm:ss, obligatorio con PM), descripcion, created_at.
 */
#[Fillable([
    'tipo_evento_id',
    'punto_monitoreo_id',
    'turno_id',
    'usuario_id',
    'fecha_hora',
    'timestamp_video',
    'descripcion',
])]
class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos';

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    /**
     * Regla de negocio 1: el original nunca se sobreescribe ni se elimina.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos son inmutables: usar fe de errata.'));
        static::deleting(fn () => throw new LogicException('Los eventos no se eliminan: usar fe de errata.'));
    }

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(TipoEvento::class);
    }

    public function puntoMonitoreo(): BelongsTo
    {
        return $this->belongsTo(PuntoMonitoreo::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function erratas(): HasMany
    {
        return $this->hasMany(Errata::class)->orderBy('id');
    }

    /**
     * Valores vigentes de los campos corregibles: el original con las fe de
     * erratas aplicadas en orden. El registro original no se toca.
     *
     * @return array<string, mixed>
     */
    public function valoresVigentes(): array
    {
        $valores = [
            'tipo_evento_id' => $this->tipo_evento_id,
            'fecha_hora' => $this->fecha_hora->format('Y-m-d H:i:s'),
            'punto_monitoreo_id' => $this->punto_monitoreo_id,
            'timestamp_video' => $this->timestamp_video,
            'descripcion' => $this->descripcion,
        ];

        foreach ($this->erratas as $errata) {
            $valores[$errata->campo_corregido] = $errata->valor_nuevo;
        }

        return $valores;
    }
}
