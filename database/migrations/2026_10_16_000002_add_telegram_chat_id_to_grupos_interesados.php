<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-006 / US-028: canal Telegram. Cada grupo interesado puede tener el
 * chat_id del grupo de Telegram donde está el bot (p. ej. -1001234567890).
 * Columna nueva y nullable: se agrega sin baja de servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos_interesados', function (Blueprint $table) {
            $table->string('telegram_chat_id', 50)->nullable()->after('identificador_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_interesados', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
