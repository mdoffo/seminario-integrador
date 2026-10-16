<?php

namespace App\Http\Controllers;

use App\Models\GrupoEvento;
use App\Models\TipoPuntoMonitoreo;
use App\Services\EventoService;
use App\Services\MapaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-016 / US-017: mapa de eventos y mapa de calor (permiso dashboards.ver).
 */
class MapaController extends Controller
{
    public function __construct(
        private readonly MapaService $mapa,
        private readonly EventoService $eventos,
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['string', 'exists:grupos_evento,codigo'],
            'tipo_evento_id' => ['nullable', 'integer', 'exists:tipos_evento,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        return Inertia::render('Mapa/Index', [
            'filtros' => [...$filtros, ...$this->mapa->periodo($filtros)],
            'densidad' => $this->mapa->densidad($filtros),
            // US-016: un marcador por evento.
            'eventos' => $this->mapa->eventos($filtros),
            // Capa de referencia: todos los PMs, con o sin eventos.
            'puntos' => $this->eventos->puntosMonitoreo(),
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => GrupoEvento::opciones(),
            'tiposPunto' => TipoPuntoMonitoreo::opciones(),
        ]);
    }
}
