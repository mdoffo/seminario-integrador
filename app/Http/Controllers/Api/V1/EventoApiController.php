<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventoResource;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * API PÚBLICA versionada (/api/v1), SOLO LECTURA, para Datos Abiertos.
 * Convive con Inertia (que resuelve el frontend interno); esto es ADEMÁS.
 * Auth: token personal de Sanctum con la habilidad 'datos-abiertos:leer'.
 */
class EventoApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'categoria' => ['nullable', Rule::in(array_keys(TipoEvento::CATEGORIAS))],
            'jurisdiccion' => ['nullable', Rule::in(array_keys(PuntoMonitoreo::JURISDICCIONES))],
            'punto' => ['nullable', 'string', 'max:30'], // código del PM
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $eventos = Evento::query()
            ->with(['tipoEvento:id,nombre,categoria,es_cuantificable', 'puntoMonitoreo:id,codigo,nombre,jurisdiccion,latitud,longitud'])
            ->withCount('erratas')
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('fecha_hora', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('fecha_hora', '<', Carbon::parse($h)->addDay()->startOfDay()))
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $c) => $q->whereHas('tipoEvento', fn (Builder $t) => $t->where('categoria', $c)))
            ->when($filtros['jurisdiccion'] ?? null, fn (Builder $q, $j) => $q->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('jurisdiccion', $j)))
            ->when($filtros['punto'] ?? null, fn (Builder $q, $codigo) => $q->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('codigo', $codigo)))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 50)
            ->withQueryString();

        return EventoResource::collection($eventos);
    }

    public function show(Evento $evento): EventoResource
    {
        return new EventoResource(
            $evento->load(['tipoEvento', 'puntoMonitoreo'])->loadCount('erratas'),
        );
    }
}
