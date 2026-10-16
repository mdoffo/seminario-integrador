<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\TokenApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de la API pública de Datos Abiertos (Administrador de sistema,
 * permiso usuarios.gestionar). Controller fino: la emisión vive en
 * TokenApiService, igual que el comando `api:token`.
 */
class TokenApiController extends Controller
{
    public function __construct(
        private readonly TokenApiService $tokens,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(): Response
    {
        return Inertia::render('TokensApi/Index', [
            'tokens' => $this->tokens->listar()->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'nombre' => $t->name,
                'duenio' => $t->tokenable instanceof User ? $t->tokenable->name : null,
                'creado' => $t->created_at?->format('d/m/Y H:i'),
                'ultimo_uso' => $t->last_used_at?->format('d/m/Y H:i'),
                'vence' => $t->expires_at?->format('d/m/Y'),
                'vencido' => $t->expires_at?->isPast() ?? false,
            ]),
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'username']),
            // Texto plano del token recién emitido: llega una sola vez (flash).
            'tokenNuevo' => session('tokenNuevo'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'dias' => ['nullable', 'integer', 'between:1,3650'],
        ], [], [
            'nombre' => 'nombre',
            'user_id' => 'usuario dueño',
            'dias' => 'vencimiento',
        ]);

        $duenio = User::findOrFail($datos['user_id']);
        $token = $this->tokens->emitir($duenio, $datos['nombre'], $datos['dias'] ?? null);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_TOKEN_ALTA,
            $request->user(),
            mb_strimwidth(
                "Token de API \"{$datos['nombre']}\" para {$duenio->username}"
                .(isset($datos['dias']) ? ", vence en {$datos['dias']} días." : ', sin vencimiento.'),
                0, 500, '…',
            ),
        );

        return redirect()->route('tokens.index')
            ->with('tokenNuevo', ['nombre' => $datos['nombre'], 'token' => $token->plainTextToken])
            ->with('success', 'Token emitido. Copialo ahora: no se vuelve a mostrar.');
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        // Solo los tokens de la API pública se gestionan desde acá.
        abort_unless($token->can(TokenApiService::HABILIDAD), 404);

        $nombre = $token->name;
        $this->tokens->revocar($token);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_TOKEN_BAJA,
            $request->user(),
            mb_strimwidth("Token de API \"{$nombre}\" revocado.", 0, 500, '…'),
        );

        return redirect()->route('tokens.index')->with('success', "Token \"{$nombre}\" revocado.");
    }
}
