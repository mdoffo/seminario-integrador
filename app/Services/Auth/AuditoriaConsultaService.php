<?php

namespace App\Services\Auth;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * US-027: consulta del log de auditoría (solo lectura). La escritura sigue
 * siendo exclusiva de AuditoriaAccesoService (SRP).
 */
class AuditoriaConsultaService
{
    public const ORDENES = ['fecha', 'usuario', 'accion'];

    /**
     * Minutos entre dos registros de "consultó el log" del mismo usuario:
     * paginar o filtrar no llena la auditoría de accesos repetidos.
     */
    private const MINUTOS_ENTRE_ACCESOS = 10;

    public function __construct(private readonly AuditoriaAccesoService $auditoria) {}

    /**
     * Filtros combinables (AND): usuario, rango de fechas, tipo de acción y
     * objeto afectado (p. ej. "123" encuentra "Evento #123", "PM-M-001" su PM).
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<AuditoriaAcceso>
     */
    public function consulta(array $filtros): Builder
    {
        $direccion = ($filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return AuditoriaAcceso::query()
            ->with('user:id,name')
            ->when($filtros['usuario_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filtros['accion'] ?? null, fn (Builder $q, $a) => $q->where('evento', $a))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('created_at', '<', Carbon::parse($h)->addDay()->startOfDay()))
            ->when($filtros['objeto'] ?? null, function (Builder $q, string $objeto) {
                $texto = addcslashes(mb_strtolower($objeto), '%_\\');
                // Un número busca la referencia "#N" exacta (no "#12" dentro de "#123").
                ctype_digit($objeto)
                    ? $q->where(fn (Builder $w) => $w
                        ->whereRaw("lower(descripcion) like ? escape '\\'", ["%#{$texto} %"])
                        ->orWhereRaw("lower(descripcion) like ? escape '\\'", ["%#{$texto}.%"])
                        ->orWhereRaw("lower(descripcion) like ? escape '\\'", ["%#{$texto}"])
                        ->orWhereRaw("lower(descripcion) like ? escape '\\'", ["%#{$texto})%"]))
                    : $q->whereRaw("lower(descripcion) like ? escape '\\'", ["%{$texto}%"]);
            })
            ->when(
                ($filtros['orden'] ?? 'fecha') === 'usuario',
                fn (Builder $q) => $q->orderBy('email', $direccion),
            )
            ->when(
                ($filtros['orden'] ?? 'fecha') === 'accion',
                fn (Builder $q) => $q->orderBy('evento', $direccion),
            )
            ->orderBy('created_at', $direccion)
            ->orderBy('id', $direccion);
    }

    /**
     * Fila aplanada para la UI y la exportación.
     *
     * @return array<string, mixed>
     */
    public function fila(AuditoriaAcceso $a): array
    {
        return [
            'id' => $a->id,
            'fecha_hora' => $a->created_at?->format('d/m/Y H:i:s'),
            'usuario' => $a->user?->name ?? $a->email,
            'email' => $a->email,
            'rol' => $a->rol,
            'accion' => AuditoriaAcceso::ACCIONES[$a->evento] ?? $a->evento,
            'clave_accion' => $a->evento,
            'ip' => $a->ip_address,
            'descripcion' => $a->descripcion,
        ];
    }

    /**
     * US-027: el acceso al log queda registrado en el propio log.
     */
    public function registrarAcceso(User $usuario): void
    {
        Cache::remember(
            "auditoria:acceso:{$usuario->id}",
            now()->addMinutes(self::MINUTOS_ENTRE_ACCESOS),
            function () use ($usuario) {
                $this->auditoria->registrarAccion(
                    AuditoriaAcceso::EVENTO_AUDITORIA_CONSULTA,
                    $usuario,
                    'Consultó el log de auditoría.',
                );

                return true;
            },
        );
    }
}
