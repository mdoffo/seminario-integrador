<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-024: zona o cuadrante del PM (agrupa PMs para filtros y estadísticas).
 * Nullable hasta que el relevamiento oficial la informe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->string('zona', 60)->nullable()->index()->after('jurisdiccion');
        });
    }

    public function down(): void
    {
        Schema::table('puntos_monitoreo', function (Blueprint $table) {
            $table->dropIndex(['zona']);
            $table->dropColumn('zona');
        });
    }
};
