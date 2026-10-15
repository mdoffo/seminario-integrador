import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import L from '@/lib/leafletGlobal';
import 'leaflet.heat';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    CircleMarker,
    MapContainer,
    Popup,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

const CENTRO_VILLA_MARIA = [-32.4075, -63.2403];

// Rampa secuencial de un solo tono (cian de marca): sobre el mapa oscuro,
// más eventos = más claro.
const GRADIENTE = { 0.25: '#0b3a5c', 0.5: '#0e7fa8', 0.8: '#00d2ff', 1: '#e6fbff' };

// Radio (m) para el drill-down al hacer clic en una zona del mapa de calor.
const RADIO_DRILL_DOWN = 250;

const categoriaActiva = {
    policia: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    seguridad_ciudadana: 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    oficios_recepcionados: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

/** Estilo del círculo según el tipo de PM (municipal | provincial | alarma). */
const ESTILO_TIPO_PUNTO = {
    municipal: { color: '#00d2ff', weight: 2, fillColor: '#00d2ff', fillOpacity: 0.45 },
    provincial: { color: '#ffffff', weight: 2, dashArray: '3 3', fillColor: '#ffffff', fillOpacity: 0.12 },
    alarma: { color: '#a855f7', weight: 2, fillColor: '#a855f7', fillOpacity: 0.35 },
};

/** Capa de calor (leaflet.heat); se rehace cuando cambian los datos. */
function CapaCalor({ puntos }) {
    const map = useMap();

    useEffect(() => {
        // leaflet.heat falla (getImageData) si el mapa todavía mide 0 px.
        const { x, y } = map.getSize();
        if (puntos.length === 0 || x === 0 || y === 0) {
            return undefined;
        }

        const max = Math.max(...puntos.map((p) => p.cantidad));
        const capa = L.heatLayer(
            puntos.map((p) => [p.latitud, p.longitud, p.cantidad / max]),
            // maxZoom: desde ese zoom la intensidad es completa; con uno mayor
            // al inicial (14) el calor se ve apagado al abrir el mapa.
            { radius: 30, blur: 20, maxZoom: 13, max: 1, minOpacity: 0.4, gradient: GRADIENTE },
        ).addTo(map);

        return () => map.removeLayer(capa);
    }, [map, puntos]);

    return null;
}

/** Recalcula el tamaño del mapa cuando el contenedor cambia. */
function AjustarTamano() {
    const map = useMap();

    useEffect(() => {
        const observer = new ResizeObserver(() => map.invalidateSize());
        observer.observe(map.getContainer());

        return () => observer.disconnect();
    }, [map]);

    return null;
}

/**
 * US-017 drill-down: clic en una zona -> PM con eventos más cercano (dentro
 * del radio) y se abre su detalle.
 */
function DrillDownPorZona({ puntos, onElegir }) {
    const map = useMapEvents({
        click(e) {
            let elegido = null;
            let mejor = RADIO_DRILL_DOWN;

            puntos.forEach((p) => {
                const d = map.distance(e.latlng, [p.latitud, p.longitud]);
                if (d <= mejor) {
                    mejor = d;
                    elegido = p;
                }
            });

            if (elegido) {
                onElegir(elegido);
            }
        },
    });

    return null;
}

function Interruptor({ activo, onChange, children }) {
    return (
        <label className="flex cursor-pointer items-center gap-2 text-xs text-atalaya-text-muted">
            <input
                type="checkbox"
                checked={activo}
                onChange={(e) => onChange(e.target.checked)}
                className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
            />
            {children}
        </label>
    );
}

export default function Index({ filtros, densidad, puntos, tipos, categorias }) {
    const [capas, setCapas] = useState({ calor: true, eventos: true, todos: false });
    const [elegido, setElegido] = useState(null);
    const marcadores = useRef({});

    const filtrar = (cambios) =>
        router.get(
            route('mapa.index'),
            { ...filtros, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const seleccionadas = filtros.categorias ?? [];
    const alternarCategoria = (clave) =>
        filtrar({
            categorias: seleccionadas.includes(clave)
                ? seleccionadas.filter((c) => c !== clave)
                : [...seleccionadas, clave],
        });

    const max = useMemo(
        () => Math.max(1, ...densidad.puntos.map((p) => p.cantidad)),
        [densidad.puntos],
    );

    // Abre el popup del PM elegido por clic en una zona del mapa de calor.
    useEffect(() => {
        if (elegido) {
            marcadores.current[elegido.id]?.openPopup();
        }
    }, [elegido]);

    // Drill-down a la consulta de eventos manteniendo los filtros del mapa.
    const verEventos = (punto) =>
        route('eventos.index', {
            punto_monitoreo_id: punto.id,
            desde: filtros.desde,
            hasta: filtros.hasta,
            categorias: seleccionadas,
            tipo_evento_id: filtros.tipo_evento_id,
        });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Mapa de eventos
                </h2>
            }
        >
            <Head title="Mapa de eventos" />

            <div className="flex flex-col gap-4 p-4 lg:h-[calc(100vh-7.5rem)] lg:flex-row">
                <aside className="space-y-4 border border-atalaya-border bg-atalaya-surface p-4 lg:w-80 lg:shrink-0 lg:overflow-y-auto">
                    <div>
                        <InputLabel value="Categoría" />
                        <div className="mt-1 flex flex-wrap gap-2">
                            {Object.entries(categorias)
                                .map(([clave, etiqueta]) => {
                                    const activa = seleccionadas.includes(clave);

                                    return (
                                        <button
                                            key={clave}
                                            type="button"
                                            aria-pressed={activa}
                                            onClick={() => alternarCategoria(clave)}
                                            className={
                                                'border px-2 py-1 font-mono text-[10px] font-bold uppercase ' +
                                                (activa
                                                    ? categoriaActiva[clave]
                                                    : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                            }
                                        >
                                            {etiqueta}
                                        </button>
                                    );
                                })}
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                        <select
                            id="tipo_evento_id"
                            className="mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan"
                            value={filtros.tipo_evento_id ?? ''}
                            onChange={(e) => filtrar({ tipo_evento_id: e.target.value || undefined })}
                        >
                            <option value="">Todos</option>
                            {tipos.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.nombre}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel htmlFor="desde" value="Desde" />
                            <TextInput
                                id="desde"
                                type="date"
                                className="mt-1 block w-full"
                                value={filtros.desde}
                                onChange={(e) => e.target.value && filtrar({ desde: e.target.value })}
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="hasta" value="Hasta" />
                            <TextInput
                                id="hasta"
                                type="date"
                                className="mt-1 block w-full"
                                value={filtros.hasta}
                                onChange={(e) => e.target.value && filtrar({ hasta: e.target.value })}
                            />
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-atalaya-border pt-4">
                        <InputLabel value="Capas" />
                        <Interruptor
                            activo={capas.calor}
                            onChange={(v) => setCapas((c) => ({ ...c, calor: v }))}
                        >
                            Mapa de calor
                        </Interruptor>
                        <Interruptor
                            activo={capas.eventos}
                            onChange={(v) => setCapas((c) => ({ ...c, eventos: v }))}
                        >
                            Puntos con eventos
                        </Interruptor>
                        <Interruptor
                            activo={capas.todos}
                            onChange={(v) => setCapas((c) => ({ ...c, todos: v }))}
                        >
                            Todos los PMs (referencia)
                        </Interruptor>
                    </div>

                    <div className="space-y-2 border-t border-atalaya-border pt-4 text-xs text-atalaya-text-muted">
                        <p>
                            <span className="font-mono text-lg font-bold text-white">
                                {densidad.total}
                            </span>{' '}
                            eventos en {densidad.puntos.length} PM.
                        </p>
                        <p className="text-atalaya-text-dim">
                            No se incluyen {densidad.sin_ubicacion} evento(s)
                            sin ubicación ni los de tipos no cuantificables.
                        </p>

                        <div className="space-y-1.5 pt-2">
                            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Referencias
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-atalaya-cyan bg-atalaya-cyan/70" />
                                PM municipal
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-dashed border-white bg-transparent" />
                                PM provincial
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-[#a855f7] bg-[#a855f7]/50" />
                                Alarma comunitaria
                            </p>
                            <p className="text-atalaya-text-dim">
                                El tamaño del círculo indica la cantidad de
                                eventos. Clic en una zona o en un punto para
                                ver sus eventos.
                            </p>
                            <div className="pt-1">
                                <div
                                    className="h-2 w-full"
                                    style={{
                                        background:
                                            'linear-gradient(to right, #0b3a5c, #0e7fa8, #00d2ff, #e6fbff)',
                                    }}
                                />
                                <div className="mt-0.5 flex justify-between text-[10px] text-atalaya-text-dim">
                                    <span>Menos</span>
                                    <span>Más eventos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <div className="mapa-tactico relative h-[60vh] border border-atalaya-border lg:h-auto lg:flex-1">
                    <MapContainer
                        center={CENTRO_VILLA_MARIA}
                        zoom={14}
                        className="h-full w-full"
                        preferCanvas
                    >
                        <TileLayer
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        />
                        <AjustarTamano />

                        {capas.todos &&
                            puntos.map((p) => (
                                <CircleMarker
                                    key={`ref-${p.id}`}
                                    center={[p.latitud, p.longitud]}
                                    radius={2.5}
                                    pathOptions={{
                                        color: '#94a3b8',
                                        weight: 0,
                                        fillOpacity: 0.6,
                                    }}
                                />
                            ))}

                        {capas.calor && <CapaCalor puntos={densidad.puntos} />}

                        {capas.eventos &&
                            densidad.puntos.map((p) => {
                                return (
                                    <CircleMarker
                                        key={p.id}
                                        ref={(m) => {
                                            marcadores.current[p.id] = m;
                                        }}
                                        center={[p.latitud, p.longitud]}
                                        radius={5 + Math.sqrt(p.cantidad / max) * 12}
                                        pathOptions={ESTILO_TIPO_PUNTO[p.tipo] ?? ESTILO_TIPO_PUNTO.municipal}
                                    >
                                        <Popup>
                                            <div className="font-mono text-xs">
                                                <div className="font-bold text-atalaya-cyan">
                                                    {p.codigo}
                                                </div>
                                                <div className="text-atalaya-text-muted">
                                                    {p.nombre}
                                                </div>
                                                <div className="text-[10px] uppercase text-atalaya-text-dim">
                                                    PM {p.tipo_nombre}
                                                </div>
                                                <div className="mt-1 text-white">
                                                    {p.cantidad} evento(s) en el período
                                                </div>
                                                <Link
                                                    href={verEventos(p)}
                                                    className="mt-2 block border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-center text-[10px] font-bold uppercase !text-atalaya-cyan hover:bg-atalaya-cyan/25"
                                                >
                                                    Ver eventos
                                                </Link>
                                            </div>
                                        </Popup>
                                    </CircleMarker>
                                );
                            })}

                        <DrillDownPorZona
                            puntos={densidad.puntos}
                            onElegir={(p) => {
                                setCapas((c) => ({ ...c, eventos: true }));
                                setElegido({ ...p, n: Date.now() });
                            }}
                        />
                    </MapContainer>

                    {densidad.puntos.length === 0 && (
                        <div className="pointer-events-none absolute inset-x-0 top-4 z-[500] mx-auto w-fit border border-atalaya-border bg-atalaya-surface px-4 py-2 font-mono text-xs text-atalaya-text-muted">
                            Sin eventos con ubicación para estos filtros.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
