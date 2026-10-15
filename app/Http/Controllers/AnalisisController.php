<?php

namespace App\Http\Controllers;

use App\Models\TipoEvento;
use App\Services\AnalisisService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboards de análisis (US-015, US-018 a US-022) — permiso dashboards.ver.
 * La UI se refresca sola pidiendo solo la prop `analisis` (US-022).
 */
class AnalisisController extends Controller
{
    /**
     * US-022: intervalo de actualización automática, en segundos.
     * // TODO: hacerlo configurable desde parámetros del sistema (US-029).
     */
    public const INTERVALO_REFRESCO = 60;

    public function __construct(private readonly AnalisisService $analisis) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'periodo' => ['nullable', Rule::in([...array_keys(AnalisisService::PERIODOS), 'personalizado'])],
            'desde' => ['nullable', 'required_if:periodo,personalizado', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'required_if:periodo,personalizado', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'categorias' => ['nullable', 'array'],
            'categorias.*' => [Rule::in(array_keys(TipoEvento::CATEGORIAS))],
            'granularidad' => ['nullable', Rule::in(AnalisisService::GRANULARIDADES)],
        ]);

        return Inertia::render('Analisis/Index', [
            'filtros' => (object) $filtros,
            'analisis' => fn () => $this->analisis->calcular($filtros),
            'categorias' => collect(TipoEvento::CATEGORIAS)->except('informativo'),
            'intervaloRefresco' => self::INTERVALO_REFRESCO,
        ]);
    }
}
