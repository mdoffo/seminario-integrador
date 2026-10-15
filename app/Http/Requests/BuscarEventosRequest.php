<?php

namespace App\Http\Requests;

use App\Services\EventoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU04 / US-008 / US-009: filtros y orden de la consulta de eventos. Todos
 * opcionales. La autorización la resuelve el middleware `permission:eventos.consultar`.
 */
class BuscarEventosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_evento_id' => ['nullable', 'integer', 'exists:tipos_evento,id'],
            // categorias = códigos de GrupoEvento (selección múltiple).
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['string', 'exists:grupos_evento,codigo'],
            'punto_monitoreo_id' => ['nullable', 'integer', 'exists:puntos_monitoreo,id'],
            // tipo_punto = código de TipoPuntoMonitoreo (municipal | provincial | alarma).
            'tipo_punto' => ['nullable', 'string', 'exists:tipos_punto_monitoreo,codigo'],
            'barrio_id' => ['nullable', 'integer', 'exists:barrios,id'],
            'cuadrante_id' => ['nullable', 'integer', 'exists:cuadrantes,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            // Franja horaria: si hora_desde > hora_hasta, cruza la medianoche.
            'hora_desde' => ['nullable', 'date_format:H:i'],
            'hora_hasta' => ['nullable', 'date_format:H:i'],
            'texto' => ['nullable', 'string', 'max:100'],
            'orden' => ['nullable', Rule::in(EventoService::ORDENES)],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'hora_desde' => 'hora desde',
            'hora_hasta' => 'hora hasta',
            'texto' => 'texto',
        ];
    }
}
