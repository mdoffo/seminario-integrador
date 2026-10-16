<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TokenApiService;
use Illuminate\Console\Command;

/**
 * Emite un token personal de Sanctum para la API pública de Datos Abiertos
 * (/api/v1, solo lectura). El token se muestra una única vez. También se
 * pueden emitir y revocar desde la pantalla "Tokens de la API" (Administrador
 * de sistema).
 */
class EmitirTokenApi extends Command
{

    protected $signature = 'api:token
        {username : Usuario dueño del token (p. ej. una cuenta de Gobierno Abierto)}
        {nombre : Para qué/quién es el token (p. ej. "Portal Datos Abiertos")}
        {--dias= : Vencimiento en días (sin valor: no vence)}';

    protected $description = 'Emite un token de lectura para la API pública /api/v1 (Datos Abiertos)';

    public function handle(TokenApiService $tokens): int
    {
        $user = User::where('username', $this->argument('username'))->first();

        if (! $user) {
            $this->error("No existe el usuario '{$this->argument('username')}'.");

            return self::FAILURE;
        }

        $dias = $this->option('dias');

        if ($dias !== null && (! ctype_digit((string) $dias) || (int) $dias < 1)) {
            $this->error('--dias debe ser un número entero positivo.');

            return self::FAILURE;
        }

        $token = $tokens->emitir($user, $this->argument('nombre'), $dias ? (int) $dias : null);

        $this->info('Token emitido. Copialo ahora: no se vuelve a mostrar.');
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('Uso: Authorization: Bearer <token>  ·  GET /api/v1/eventos');

        return self::SUCCESS;
    }
}
