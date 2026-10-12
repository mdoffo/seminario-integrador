<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos del PuntoMonitoreo según el diagrama de clases:
 * - TipoPuntoMonitoreo: reemplaza la columna de texto puntos_monitoreo.jurisdiccion
 *   (municipal | provincial) y suma "alarma" (alarmas comunitarias).
 * - Barrio y Cuadrante: opcionales; el listado de cámaras solo trae coordenadas,
 *   así que el Administrador de sistema los asigna a mano.
 *
 * Los PMs existentes se reasignan desde su jurisdicción, sin perder datos.
 */
return new class extends Migration
{
    private const TIPOS = [
        ['codigo' => 'municipal', 'nombre' => 'Municipal'],
        ['codigo' => 'provincial', 'nombre' => 'Provincial'],
        ['codigo' => 'alarma', 'nombre' => 'Alarma comunitaria'],
    ];

    public function up(): void
    {
        Schema::create('tipos_punto_monitoreo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('barrios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('cuadrantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('tipos_punto_monitoreo')->insert(array_map(
            fn (array $t) => [...$t, 'created_at' => $ahora, 'updated_at' => $ahora],
            self::TIPOS,
        ));

        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->foreignId('tipo_punto_monitoreo_id')->nullable()->after('nombre')
                ->constrained('tipos_punto_monitoreo')->restrictOnDelete();
            $table->foreignId('barrio_id')->nullable()->after('tipo_punto_monitoreo_id')
                ->constrained('barrios')->nullOnDelete();
            $table->foreignId('cuadrante_id')->nullable()->after('barrio_id')
                ->constrained('cuadrantes')->nullOnDelete();
            $table->string('descripcion', 500)->nullable()->after('cuadrante_id');
        });

        $tipos = DB::table('tipos_punto_monitoreo')->pluck('id', 'codigo');
        foreach (['municipal', 'provincial'] as $codigo) {
            DB::table('puntos_monitoreo')->where('jurisdiccion', $codigo)
                ->update(['tipo_punto_monitoreo_id' => $tipos[$codigo]]);
        }
        DB::table('puntos_monitoreo')->whereNull('tipo_punto_monitoreo_id')
            ->update(['tipo_punto_monitoreo_id' => $tipos['municipal']]);

        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->foreignId('tipo_punto_monitoreo_id')->nullable(false)->change();
            $table->dropIndex(['jurisdiccion']);
            $table->dropColumn('jurisdiccion');
        });
    }

    public function down(): void
    {
        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->string('jurisdiccion', 20)->nullable()->index();
        });

        foreach (DB::table('tipos_punto_monitoreo')->pluck('codigo', 'id') as $id => $codigo) {
            DB::table('puntos_monitoreo')->where('tipo_punto_monitoreo_id', $id)
                ->update(['jurisdiccion' => $codigo === 'provincial' ? 'provincial' : 'municipal']);
        }

        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_punto_monitoreo_id');
            $table->dropConstrainedForeignId('barrio_id');
            $table->dropConstrainedForeignId('cuadrante_id');
            $table->dropColumn('descripcion');
        });

        Schema::dropIfExists('cuadrantes');
        Schema::dropIfExists('barrios');
        Schema::dropIfExists('tipos_punto_monitoreo');
    }
};
