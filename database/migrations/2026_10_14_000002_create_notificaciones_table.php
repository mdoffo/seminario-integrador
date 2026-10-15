<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-006 / US-007: una fila por envío (evento x grupo). Registra el mensaje,
 * el resultado (pendiente / enviada / fallida), los intentos y el último error,
 * para poder mostrar alertas y reenviar manualmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos');
            $table->foreignId('grupo_interesado_id')->constrained('grupos_interesados');
            $table->string('canal', 20);
            $table->text('mensaje');
            $table->string('estado', 20)->index();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
