<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-029: parámetros generales del sistema (clave => valor entero).
 * Una clave sin fila toma el valor por defecto de Parametro::DEFINICIONES,
 * así que la tabla puede arrancar vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->integer('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
