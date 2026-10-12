<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GrupoEvento (diagrama de clases): reemplaza la columna de texto
 * tipos_evento.categoria por una entidad. Los grupos reales salen del registro
 * de intervenciones del Centro de Monitoreo (Policía, Seguridad Ciudadana,
 * Oficios Recepcionados).
 *
 * Además suma a tipos_evento: descripcion y notifica (si al registrar se avisa
 * por WhatsApp). El nombre del tipo deja de ser único global y pasa a ser único
 * por grupo ("Otros" existe en Policía y en Seguridad Ciudadana).
 *
 * Los tipos existentes se reasignan desde la categoría vieja (ver MAPEO), así
 * la migración no pierde datos en una base con eventos cargados.
 */
return new class extends Migration
{
    private const GRUPOS = [
        ['codigo' => 'policia', 'nombre' => 'Policía'],
        ['codigo' => 'seguridad_ciudadana', 'nombre' => 'Seguridad Ciudadana'],
        ['codigo' => 'oficios_recepcionados', 'nombre' => 'Oficios Recepcionados'],
    ];

    /** Categoría vieja => código de grupo nuevo. */
    private const MAPEO = [
        'prevencion' => 'policia',
        'seguridad_publica' => 'policia',
        'convivencia_urbana' => 'seguridad_ciudadana',
        'informativo' => 'seguridad_ciudadana',
    ];

    public function up(): void
    {
        Schema::create('grupos_evento', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('grupos_evento')->insert(array_map(
            fn (array $g) => [...$g, 'created_at' => $ahora, 'updated_at' => $ahora],
            self::GRUPOS,
        ));

        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->foreignId('grupo_evento_id')->nullable()->after('id')
                ->constrained('grupos_evento')->restrictOnDelete();
            $table->text('descripcion')->nullable()->after('nombre');
            $table->boolean('notifica')->default(false)->after('es_cuantificable');
        });

        $grupos = DB::table('grupos_evento')->pluck('id', 'codigo');
        foreach (self::MAPEO as $categoria => $codigo) {
            DB::table('tipos_evento')->where('categoria', $categoria)
                ->update(['grupo_evento_id' => $grupos[$codigo]]);
        }
        DB::table('tipos_evento')->whereNull('grupo_evento_id')
            ->update(['grupo_evento_id' => $grupos['seguridad_ciudadana']]);

        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->foreignId('grupo_evento_id')->nullable(false)->change();
            $table->dropUnique(['nombre']);
            $table->unique(['grupo_evento_id', 'nombre']);
            $table->dropIndex(['categoria']);
            $table->dropColumn('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->string('categoria', 30)->nullable()->index();
        });

        $inverso = ['policia' => 'seguridad_publica', 'seguridad_ciudadana' => 'convivencia_urbana', 'oficios_recepcionados' => 'informativo'];
        foreach (DB::table('grupos_evento')->pluck('codigo', 'id') as $id => $codigo) {
            DB::table('tipos_evento')->where('grupo_evento_id', $id)
                ->update(['categoria' => $inverso[$codigo] ?? 'informativo']);
        }

        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->dropUnique(['grupo_evento_id', 'nombre']);
            $table->dropConstrainedForeignId('grupo_evento_id');
            $table->dropColumn(['descripcion', 'notifica']);
            // TODO: si quedaron nombres repetidos entre grupos ("Otros"), este unique falla al revertir.
            $table->unique('nombre');
        });

        Schema::dropIfExists('grupos_evento');
    }
};
