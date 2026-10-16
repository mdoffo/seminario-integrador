<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens personales de Sanctum para la API pública de Datos Abiertos
 * (/api/v1, solo lectura). Los usan el comando `api:token` y la pantalla de
 * tokens del Administrador de sistema.
 */
class TokenApiService
{
    /**
     * Única habilidad que exige la API pública (routes/api.php).
     */
    public const HABILIDAD = 'datos-abiertos:leer';

    /**
     * El texto plano del token solo existe en el valor devuelto: en la base
     * queda el hash, así que se muestra una única vez.
     */
    public function emitir(User $duenio, string $nombre, ?int $dias = null): NewAccessToken
    {
        return $duenio->createToken(
            $nombre,
            [self::HABILIDAD],
            $dias ? now()->addDays($dias) : null,
        );
    }

    /**
     * Tokens de la API pública (con la habilidad de Datos Abiertos), del más nuevo al más viejo.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function listar(): Collection
    {
        return PersonalAccessToken::query()
            ->with('tokenable')
            ->where('abilities', 'like', '%'.self::HABILIDAD.'%')
            ->latest('id')
            ->get();
    }

    public function revocar(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
