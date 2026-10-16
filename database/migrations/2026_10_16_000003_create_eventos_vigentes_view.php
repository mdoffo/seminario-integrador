<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regla de negocio 1 + decisión del equipo (2026-10-15): estadísticas,
 * análisis, mapa, Excel y API usan los valores CORREGIDOS por fe de errata.
 *
 * eventos_vigentes es una vista con las mismas columnas que `eventos`, donde
 * cada campo corregible toma el valor_nuevo de su última errata (si tiene).
 * El original en `eventos` no se toca. Se lee con Evento::vigentes().
 *
 * Vista + índice nuevos: se agregan sin baja de servicio.
 */
return new class extends Migration
{
    /** Campo corregible => tipo SQL al que se castea valor_nuevo (texto). */
    private function tipos(): array
    {
        return DB::getDriverName() === 'pgsql'
            ? [
                'tipo_evento_id' => 'bigint',
                'punto_monitoreo_id' => 'bigint',
                'fecha_hora' => 'timestamp(0) without time zone',
                'timestamp_video' => 'time(0) without time zone',
                'descripcion' => 'text',
            ]
            // SQLite (tests): fechas y horas se guardan como texto.
            : [
                'tipo_evento_id' => 'integer',
                'punto_monitoreo_id' => 'integer',
                'fecha_hora' => 'text',
                'timestamp_video' => 'text',
                'descripcion' => 'text',
            ];
    }

    public function up(): void
    {
        Schema::table('erratas', function (Blueprint $table) {
            $table->index(['evento_id', 'campo_corregido', 'id']);
        });

        $campos = collect($this->tipos())->map(function (string $tipo, string $campo) {
            $ultima = "from erratas r where r.evento_id = e.id and r.campo_corregido = '{$campo}'";

            return "case when exists (select 1 {$ultima}) "
                ."then cast((select r.valor_nuevo {$ultima} order by r.id desc limit 1) as {$tipo}) "
                ."else e.{$campo} end as {$campo}";
        })->implode(",\n    ");

        DB::statement(<<<SQL
            create view eventos_vigentes as
            select
                e.id,
                e.turno_id,
                e.usuario_id,
                e.created_at,
                {$campos}
            from eventos e
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('erratas', function (Blueprint $table) {
            $table->dropIndex(['evento_id', 'campo_corregido', 'id']);
        });
    }
};
