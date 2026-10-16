import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import type { PuntoMapa } from '@/types';
import { useMemo, useState } from 'react';

const MAX_RESULTADOS = 50;

const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * Buscador de Puntos de Monitoreo por código o nombre, con filtro por tipo de punto
 * (municipal / provincial / alarma). Busca también por barrio.
 * value/onChange trabajan con el id del PM (o null).
 */
interface Props {
    id?: string;
    puntos: PuntoMapa[];
    /** codigo => nombre (TipoPuntoMonitoreo::opciones). */
    tiposPunto: Record<string, string>;
    value: number | null;
    onChange: (id: number | null) => void;
}

export default function SelectorPuntoMonitoreo({
    id,
    puntos,
    tiposPunto,
    value,
    onChange,
}: Props) {
    const [query, setQuery] = useState('');
    const [tipo, setTipo] = useState('');

    const seleccionado = puntos.find((p) => p.id === value) ?? null;

    const conteo = useMemo(() => {
        const acc: Record<string, number> = {};
        for (const p of puntos) {
            acc[p.tipo] = (acc[p.tipo] ?? 0) + 1;
        }
        return acc;
    }, [puntos]);

    const filtrados = useMemo(() => {
        const q = normalizar(query.trim());

        return puntos.filter(
            (p) =>
                (!tipo || p.tipo === tipo) &&
                (!q ||
                    normalizar(
                        `${p.codigo} ${p.nombre} ${p.barrio ?? ''}`,
                    ).includes(q)),
        );
    }, [puntos, query, tipo]);

    const filtros: [string, string][] = [
        ['', `Todos (${puntos.length})`],
        ...Object.entries(tiposPunto).map(([clave, etiqueta]): [string, string] => [
            clave,
            `${etiqueta} (${conteo[clave] ?? 0})`,
        ]),
    ];

    return (
        <div className="mt-1 space-y-2">
            <div className="flex flex-wrap gap-2">
                {filtros.map(([clave, etiqueta]) => (
                    <button
                        key={clave || 'todos'}
                        type="button"
                        onClick={() => setTipo(clave)}
                        className={
                            'border px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-wider transition ' +
                            (tipo === clave
                                ? 'border-atalaya-cyan bg-atalaya-cyan text-black'
                                : 'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted hover:border-atalaya-cyan hover:text-white')
                        }
                    >
                        {etiqueta}
                    </button>
                ))}
            </div>

            <div className="flex gap-2">
                <Combobox
                    value={seleccionado}
                    onChange={(p) => onChange(p?.id ?? null)}
                    onClose={() => setQuery('')}
                >
                    <ComboboxInput
                        id={id}
                        autoComplete="off"
                        placeholder="Buscar por código o nombre…"
                        className="block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs text-white placeholder-atalaya-text-dim focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan"
                        displayValue={(p: PuntoMapa | null) =>
                            p ? `${p.codigo} — ${p.nombre}` : ''
                        }
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <ComboboxOptions
                        anchor="bottom start"
                        className="z-[1100] mt-1 max-h-72 w-[var(--input-width)] overflow-auto border border-atalaya-border bg-atalaya-surface py-1 font-mono text-xs text-atalaya-text-muted shadow-2xl empty:invisible"
                    >
                        {filtrados.length === 0 && (
                            <div className="px-3 py-2 text-atalaya-text-dim">
                                Sin resultados.
                            </div>
                        )}
                        {filtrados.slice(0, MAX_RESULTADOS).map((p) => (
                            <ComboboxOption
                                key={p.id}
                                value={p}
                                className="flex cursor-pointer items-center justify-between px-3 py-2 data-[focus]:bg-atalaya-elevated data-[focus]:text-atalaya-cyan"
                            >
                                <span>
                                    <span className="font-medium">
                                        {p.codigo}
                                    </span>{' '}
                                    — {p.nombre}
                                </span>
                                <span className="ms-3 text-xs opacity-70">
                                    {tiposPunto[p.tipo]}
                                </span>
                            </ComboboxOption>
                        ))}
                        {filtrados.length > MAX_RESULTADOS && (
                            <div className="px-3 py-2 text-xs text-atalaya-text-dim">
                                Mostrando {MAX_RESULTADOS} de{' '}
                                {filtrados.length}. Refiná la búsqueda.
                            </div>
                        )}
                    </ComboboxOptions>
                </Combobox>

                {seleccionado && (
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        className="shrink-0 border border-atalaya-border px-3 font-mono text-[11px] uppercase tracking-wider text-atalaya-text-muted transition hover:border-atalaya-crimson hover:text-atalaya-crimson"
                    >
                        Quitar
                    </button>
                )}
            </div>
        </div>
    );
}
