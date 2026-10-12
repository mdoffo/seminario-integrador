<?php

namespace Database\Factories;

use App\Models\PuntoMonitoreo;
use App\Models\TipoPuntoMonitoreo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuntoMonitoreo>
 */
class PuntoMonitoreoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PM-####'),
            'nombre' => fake()->streetName().' y '.fake()->streetName(),
            'tipo_punto_monitoreo_id' => fn () => TipoPuntoMonitoreo::inRandomOrder()->value('id'),
            // Área aproximada de Villa María.
            'latitud' => fake()->latitude(-32.44, -32.38),
            'longitud' => fake()->longitude(-63.28, -63.20),
        ];
    }

    /** Asigna el tipo por su código (los tipos los crea la migración). */
    public function delTipo(string $codigo): static
    {
        return $this->state(fn () => ['tipo_punto_monitoreo_id' => TipoPuntoMonitoreo::where('codigo', $codigo)->value('id')]);
    }
}
