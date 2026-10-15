<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Models\Parametro;
use App\Services\Auth\AuditoriaAccesoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-029: parámetros generales del sistema (permiso parametros.configurar,
 * Administrador de sistema). Cada cambio queda en la auditoría con el valor
 * anterior y el nuevo.
 */
class ParametroController extends Controller
{
    public function __construct(private readonly AuditoriaAccesoService $auditoria) {}

    public function index(): Response
    {
        return Inertia::render('Parametros/Index', [
            'definiciones' => Parametro::DEFINICIONES,
            'valores' => Parametro::vigentes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $reglas = collect(Parametro::DEFINICIONES)
            ->mapWithKeys(fn ($d, $clave) => [$clave => ['required', 'integer', "between:{$d['min']},{$d['max']}"]])
            ->all();
        $nombres = collect(Parametro::DEFINICIONES)->map(fn ($d) => mb_strtolower($d['etiqueta']))->all();

        $datos = $request->validate($reglas, [], $nombres);
        $anteriores = Parametro::vigentes();

        $cambios = collect($datos)
            ->map(fn ($valor) => (int) $valor)
            ->filter(fn ($valor, $clave) => $valor !== $anteriores[$clave]);

        if ($cambios->isEmpty()) {
            return back()->with('success', 'No había cambios para guardar.');
        }

        DB::transaction(fn () => $cambios->each(
            fn ($valor, $clave) => Parametro::updateOrCreate(['clave' => $clave], ['valor' => $valor]),
        ));

        $detalle = $cambios->map(function ($valor, $clave) use ($anteriores) {
            $d = Parametro::DEFINICIONES[$clave];

            return "{$d['etiqueta']}: {$anteriores[$clave]} → {$valor} {$d['unidad']}";
        })->implode('; ');

        $this->auditoria->registrarAccion(AuditoriaAcceso::EVENTO_PARAMETROS, $request->user(), $detalle);

        return back()->with('success', 'Parámetros guardados.');
    }
}
