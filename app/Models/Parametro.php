<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * US-029: parámetro configurable del sistema. Campos: clave (PK), valor (int).
 *
 * DEFINICIONES es el catálogo: etiqueta, unidad, valor por defecto y rango
 * válido. Los valores se leen con Parametro::valor(), que cachea la tabla
 * entera (se invalida al guardar) y cae al valor por defecto si la tabla
 * todavía no existe (p. ej. durante una migración).
 */
#[Fillable(['clave', 'valor'])]
class Parametro extends Model
{
    public const SESION_MINUTOS = 'sesion_minutos';

    public const LOGIN_MAX_INTENTOS = 'login_max_intentos';

    public const BLOQUEO_MINUTOS = 'bloqueo_minutos';

    public const REFRESCO_DASHBOARDS = 'refresco_dashboards';

    public const NOTIF_REINTENTOS = 'notificacion_reintentos';

    public const NOTIF_INTERVALO = 'notificacion_intervalo';

    /**
     * @var array<string, array{grupo: string, etiqueta: string, unidad: string, defecto: int, min: int, max: int, ayuda: string}>
     */
    public const DEFINICIONES = [
        self::SESION_MINUTOS => [
            'grupo' => 'Seguridad', 'etiqueta' => 'Expiración de sesión por inactividad', 'unidad' => 'minutos',
            'defecto' => 30, 'min' => 5, 'max' => 480,
            'ayuda' => 'Rige para las sesiones que se inicien o renueven después del cambio.',
        ],
        self::LOGIN_MAX_INTENTOS => [
            'grupo' => 'Seguridad', 'etiqueta' => 'Intentos de login antes del bloqueo', 'unidad' => 'intentos',
            'defecto' => 3, 'min' => 1, 'max' => 10,
            'ayuda' => 'Intentos fallidos seguidos que bloquean la cuenta.',
        ],
        self::BLOQUEO_MINUTOS => [
            'grupo' => 'Seguridad', 'etiqueta' => 'Tiempo de bloqueo de cuenta', 'unidad' => 'minutos',
            'defecto' => 15, 'min' => 1, 'max' => 1440,
            'ayuda' => 'Las cuentas ya bloqueadas mantienen el vencimiento que tenían.',
        ],
        self::REFRESCO_DASHBOARDS => [
            'grupo' => 'Dashboards', 'etiqueta' => 'Intervalo de actualización de dashboards', 'unidad' => 'segundos',
            'defecto' => 60, 'min' => 15, 'max' => 3600,
            'ayuda' => 'Cada cuánto se refrescan los datos de la sección Análisis.',
        ],
        self::NOTIF_REINTENTOS => [
            'grupo' => 'Notificaciones WhatsApp', 'etiqueta' => 'Reintentos de notificación', 'unidad' => 'reintentos',
            'defecto' => 3, 'min' => 0, 'max' => 10,
            'ayuda' => 'Reintentos automáticos después del primer envío fallido.',
        ],
        self::NOTIF_INTERVALO => [
            'grupo' => 'Notificaciones WhatsApp', 'etiqueta' => 'Intervalo entre reintentos', 'unidad' => 'segundos',
            'defecto' => 30, 'min' => 5, 'max' => 3600,
            'ayuda' => 'Espera entre un intento fallido y el siguiente.',
        ],
    ];

    private const CACHE = 'parametros';

    protected $table = 'parametros';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['valor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE));
        static::deleted(fn () => Cache::forget(self::CACHE));
    }

    /**
     * Valor vigente de un parámetro (el configurado o, si no hay, el de fábrica).
     */
    public static function valor(string $clave): int
    {
        return self::vigentes()[$clave] ?? self::DEFINICIONES[$clave]['defecto'];
    }

    /**
     * Todos los parámetros con su valor vigente.
     *
     * @return array<string, int>
     */
    public static function vigentes(): array
    {
        try {
            $guardados = Cache::rememberForever(self::CACHE, fn () => self::pluck('valor', 'clave')->all());
        } catch (Throwable) {
            $guardados = [];
        }

        return array_map(
            fn (string $clave) => (int) ($guardados[$clave] ?? self::DEFINICIONES[$clave]['defecto']),
            array_combine(array_keys(self::DEFINICIONES), array_keys(self::DEFINICIONES)),
        );
    }
}
