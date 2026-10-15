<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaDatosDeEvento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fe de errata: los datos corregidos cumplen las mismas reglas que un evento
 * nuevo (US-003 / US-005), más un motivo obligatorio.
 */
class StoreErrataRequest extends FormRequest
{
    use ValidaDatosDeEvento;

    public function authorize(): bool
    {
        // Ruta gateada por role:Supervisor; el alcance (evento visible) lo
        // controla ErrataController.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasDeEvento(),
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
