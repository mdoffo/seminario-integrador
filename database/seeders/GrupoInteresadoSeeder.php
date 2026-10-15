<?php

namespace Database\Seeders;

use App\Models\GrupoInteresado;
use Illuminate\Database\Seeder;

/**
 * US-006 / US-028: grupos de WhatsApp de ejemplo para desarrollo, SIN tipos
 * de evento asignados: el Administrador de sistema arma las correspondencias
 * desde Administración → Notificaciones (hoy ningún tipo notifica).
 *
 * No está en deploy.sh: en producción los grupos se cargan desde la UI.
 * // TODO: confirmar con el informante los grupos reales y sus números.
 * // Los contactos son de EJEMPLO: con el canal 'log' no se envía nada.
 */
class GrupoInteresadoSeeder extends Seeder
{
    public const GRUPOS = ['Guardia Local', 'Policía'];

    public function run(): void
    {
        foreach (self::GRUPOS as $nombre) {
            $grupo = GrupoInteresado::firstOrCreate(['nombre' => $nombre]);

            if ($grupo->contactos()->doesntExist()) {
                $grupo->contactos()->create([
                    'nombre' => "Contacto de prueba ({$nombre})",
                    'numero_whatsapp' => '5490000000000',
                ]);
            }
        }
    }
}
