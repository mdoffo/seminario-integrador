<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regla de negocio 1: fe de errata. Única forma de corregir un Evento; el
 * original nunca se modifica. Una fila por campo corregido (las que se hacen
 * juntas comparten motivo, autor y momento). Inmutable: solo created_at.
 *
 * valor_anterior / valor_nuevo guardan el valor crudo de la columna (ids,
 * 'Y-m-d H:i:s', 'H:i:s' o texto) para poder reconstruir el valor vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erratas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos');
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('campo_corregido', 40);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->text('motivo');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erratas');
    }
};
