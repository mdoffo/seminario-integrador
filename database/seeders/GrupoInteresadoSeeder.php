<?php

namespace Database\Seeders;

use App\Models\GrupoInteresado;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * US-006: grupos destinatarios y su correspondencia con las categorías:
 *   Prevención y Convivencia Urbana -> Guardia Local
 *   Seguridad Pública               -> Policía
 * Los Informativos no se notifican.
 *
 * // TODO: confirmar con el informante los grupos reales y sus números.
 * // Los contactos son de EJEMPLO: con el canal 'log' no se envía nada.
 */
class GrupoInteresadoSeeder extends Seeder
{
    /**
     * nombre del grupo => categorías de TipoEvento que recibe.
     *
     * @var array<string, list<string>>
     */
    public const CORRESPONDENCIAS = [
        'Guardia Local' => ['prevencion', 'convivencia_urbana'],
        'Policía' => ['seguridad_publica'],
    ];

    public function run(): void
    {
        foreach (self::CORRESPONDENCIAS as $nombre => $categorias) {
            $grupo = GrupoInteresado::firstOrCreate(['nombre' => $nombre]);

            if ($grupo->contactos()->doesntExist()) {
                $grupo->contactos()->create([
                    'nombre' => "Contacto de prueba ({$nombre})",
                    'numero_whatsapp' => '5490000000000',
                ]);
            }

            $grupo->tiposEvento()->sync(
                TipoEvento::whereIn('categoria', $categorias)->pluck('id'),
            );
        }
    }
}
