<?php

namespace App\Http\Requests;

use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
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
            'categorias' => ['nullable', 'array'],
            'categorias.*' => [Rule::in(array_keys(TipoEvento::CATEGORIAS))],
            'punto_monitoreo_id' => ['nullable', 'integer', 'exists:puntos_monitoreo,id'],
            'jurisdiccion' => ['nullable', Rule::in(array_keys(PuntoMonitoreo::JURISDICCIONES))],
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
