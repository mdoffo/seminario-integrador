<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaDatosDeEvento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * US-003 / US-005: validación del registro de un evento.
 * El autor (usuario_id) y el turno NO vienen del request: los fija EventoService.
 */
class StoreEventoRequest extends FormRequest
{
    use ValidaDatosDeEvento;

    public function authorize(): bool
    {
        // La ruta ya está gateada por middleware role:Supervisor.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reglasDeEvento();
    }
}
