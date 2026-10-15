<?php

namespace Database\Seeders;

use App\Models\GrupoEvento;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Catálogo real de grupos y tipos de evento, tomado del "Registro de
 * Intervenciones" del Centro de Control Videoperativo (planilla octubre 2025).
 * Idempotente: crea lo que falta por codigo de grupo y por (grupo, nombre) del tipo.
 *
 * // TODO: confirmar con el equipo qué tipos notifican por WhatsApp (hoy ninguno)
 * // y si alguno es no cuantificable (la planilla los cuenta a todos).
 */
class TipoEventoSeeder extends Seeder
{
    /**
     * codigo => [nombre, requiere_ubicacion, tipos]
     *
     * @var array<string, array{0: string, 1: bool, 2: list<string>}>
     */
    public const CATALOGO = [
        'policia' => ['Policía', true, [
            'Personas sospechosas',
            'Vehículos sospechosos',
            'Personas aprehendidas',
            'Robos',
            'Hurtos',
            'Disturbios en vía pública',
            'Vandalismos',
            'Violencia familiar',
            'Arrebato',
            'Daño a propiedad pública/privada',
            'Persona en estado de ebriedad/sustancias',
            'Persona en situación de calle',
            'Resistencia a la autoridad',
            'Otros',
        ]],
        'seguridad_ciudadana' => ['Seguridad Ciudadana', true, [
            'Camiones en zona prohibida',
            'Vehículos mal estacionados',
            'Animales sueltos',
            'Accidentes',
            'Consumo de alcohol en vía pública',
            'Incendios',
            'Semáforos cortados',
            'Alumbrado público',
            'Saliente de agua',
            'Pescadores',
            'Restos de poda',
            'Venta de alcohol',
            'Obstáculo en la vía pública',
            'Terrenos con maleza alta y falta de higiene',
            'Transgrede vallado',
            'Quema de pastizales',
            'Vehículo descompuesto',
            'Vehículo en estado de abandono',
            'Otros',
        ]],
        // Los oficios se reciben en la sala: no se ubican en un PM.
        'oficios_recepcionados' => ['Oficios Recepcionados', false, [
            'Oficios judiciales',
            'Particulares',
            'Solicitudes de seguros',
        ]],
    ];

    /** Tipos de ejemplo que cargaba la versión anterior de este seeder. */
    private const TIPOS_DE_EJEMPLO = [
        'Persona en actitud sospechosa',
        'Ruidos molestos',
        'Disturbios en vía pública',
        'Robo / hurto',
        'Accidente de tránsito',
        'Falla de cámara',
        'Novedad de guardia',
    ];

    public function run(): void
    {
        // deploy.sh corre este seeder en cada deploy: firstOrCreate para no pisar
        // lo que el Administrador de sistema edite después desde la UI.
        foreach (self::CATALOGO as $codigo => [$nombreGrupo, $requiereUbicacion, $tipos]) {
            $grupo = GrupoEvento::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombreGrupo]);

            foreach ($tipos as $nombre) {
                TipoEvento::firstOrCreate(
                    ['grupo_evento_id' => $grupo->id, 'nombre' => $nombre],
                    // "Otros" no siempre ocurre frente a una cámara.
                    ['requiere_ubicacion' => $requiereUbicacion && $nombre !== 'Otros'],
                );
            }
        }

        // Los de ejemplo se borran solo si no tienen eventos (los eventos son
        // inmutables y el FK es restrictivo). "Disturbios en vía pública" ya
        // existe en el catálogo real dentro de Policía: se conserva ese.
        $policia = GrupoEvento::where('codigo', 'policia')->value('id');
        TipoEvento::whereIn('nombre', self::TIPOS_DE_EJEMPLO)
            ->whereNot(fn ($q) => $q->where('nombre', 'Disturbios en vía pública')->where('grupo_evento_id', $policia))
            ->doesntHave('eventos')
            ->delete();
    }
}
