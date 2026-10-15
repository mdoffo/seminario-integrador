<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-006 / US-028: destinatarios de las notificaciones automáticas.
 *
 * - grupos_interesados:            p. ej. "Guardia Local", "Policía".
 *   identificador_whatsapp:        id del grupo en WhatsApp, si el canal lo
 *                                  soporta. // TODO: confirmar si la Cloud API
 *                                  de Meta permite enviar a grupos; si no, se
 *                                  envía a cada Contacto del grupo.
 * - contactos:                     números concretos de cada grupo (1:N).
 * - grupo_interesado_tipo_evento:  qué tipos de evento le llegan a cada grupo (N:M).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_interesados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('identificador_whatsapp', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_interesado_id')->constrained('grupos_interesados')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('numero_whatsapp', 20);
            $table->timestamps();
        });

        Schema::create('grupo_interesado_tipo_evento', function (Blueprint $table) {
            $table->foreignId('grupo_interesado_id')->constrained('grupos_interesados')->cascadeOnDelete();
            $table->foreignId('tipo_evento_id')->constrained('tipos_evento')->cascadeOnDelete();
            $table->primary(['grupo_interesado_id', 'tipo_evento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_interesado_tipo_evento');
        Schema::dropIfExists('contactos');
        Schema::dropIfExists('grupos_interesados');
    }
};
