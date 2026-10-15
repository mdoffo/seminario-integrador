import DangerButton from '@/Components/DangerButton';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import MapaPuntosMonitoreo from '@/Components/MapaPuntosMonitoreo';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function Dato({ etiqueta, valor, alerta = false }) {
    return (
        <div className="border border-atalaya-border bg-atalaya-surface px-4 py-3">
            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                {etiqueta}
            </p>
            <p className={`mt-1 font-mono text-2xl font-bold ${alerta ? 'text-atalaya-amber' : 'text-white'}`}>
                {valor}
            </p>
        </div>
    );
}

/**
 * US-024: listado, mapa e importación masiva de Puntos de Monitoreo.
 */
export default function Index({ puntos, filtros, resumen, mapa, jurisdicciones }) {
    const { flash } = usePage().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [porEliminar, setPorEliminar] = useState(null);
    const primeraVez = useRef(true);
    const importacion = useForm({ archivo: null });

    const filtrar = (cambios) =>
        router.get(
            route('puntos.index'),
            Object.fromEntries(
                Object.entries({ ...filtros, ...cambios }).filter(([, v]) => v !== '' && v != null && v !== false),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;
            return undefined;
        }

        const espera = setTimeout(() => filtrar({ buscar }), 350);

        return () => clearTimeout(espera);
    }, [buscar]);

    const importar = (e) => {
        e.preventDefault();
        importacion.post(route('puntos.importar'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => importacion.reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Puntos de monitoreo
                </h2>
            }
        >
            <Head title="Puntos de monitoreo" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {flash?.erroresImportacion?.length > 0 && (
                        <div className="max-h-56 overflow-y-auto border border-atalaya-crimson/50 bg-atalaya-crimson/10 px-4 py-3 text-xs text-red-100">
                            <ul className="list-inside list-disc space-y-0.5 font-mono">
                                {flash.erroresImportacion.map((e) => (
                                    <li key={e}>{e}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Dato etiqueta="Total" valor={resumen.total} />
                        <Dato etiqueta="Municipales" valor={resumen.municipales} />
                        <Dato etiqueta="Provinciales" valor={resumen.provinciales} />
                        <Dato etiqueta="Sin coordenadas" valor={resumen.sin_coordenadas} alerta={resumen.sin_coordenadas > 0} />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-5">
                        <form
                            onSubmit={importar}
                            className="space-y-3 border border-atalaya-border bg-atalaya-surface p-4 lg:col-span-2"
                        >
                            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Importación masiva
                            </h3>
                            <p className="text-xs text-atalaya-text-muted">
                                Archivo .csv o .xlsx con las columnas{' '}
                                <span className="font-mono text-white">codigo, nombre, jurisdiccion</span>{' '}
                                y opcionalmente{' '}
                                <span className="font-mono text-white">zona, latitud, longitud</span>.
                                Los códigos que ya existen se actualizan. Si una
                                fila tiene errores no se importa nada.
                            </p>
                            <input
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                onChange={(e) => importacion.setData('archivo', e.target.files[0] ?? null)}
                                className="block w-full text-xs text-atalaya-text-muted file:mr-3 file:border file:border-atalaya-cyan file:bg-atalaya-cyan/10 file:px-3 file:py-1.5 file:font-mono file:text-xs file:font-bold file:uppercase file:text-atalaya-cyan"
                            />
                            <InputError message={importacion.errors.archivo} />
                            <div className="flex items-center justify-between gap-3">
                                <a
                                    href={route('puntos.plantilla')}
                                    className="font-mono text-xs text-atalaya-cyan hover:underline"
                                >
                                    Descargar plantilla
                                </a>
                                <PrimaryButton disabled={!importacion.data.archivo || importacion.processing}>
                                    {importacion.processing ? 'Importando…' : 'Importar'}
                                </PrimaryButton>
                            </div>
                        </form>

                        <div className="lg:col-span-3">
                            <MapaPuntosMonitoreo
                                puntos={mapa}
                                seleccionadoId={null}
                                onSelect={(id) => id && router.visit(route('puntos.edit', id))}
                                className="h-64 w-full lg:h-full"
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <TextInput
                            type="search"
                            placeholder="Buscar por código, nombre o zona…"
                            className="w-72"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                        />
                        <select
                            className="rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs text-white"
                            value={filtros.jurisdiccion ?? ''}
                            onChange={(e) => filtrar({ jurisdiccion: e.target.value })}
                        >
                            <option value="">Todas las jurisdicciones</option>
                            {Object.entries(jurisdicciones).map(([clave, etiqueta]) => (
                                <option key={clave} value={clave}>
                                    {etiqueta}
                                </option>
                            ))}
                        </select>
                        <label className="flex items-center gap-2 text-xs text-atalaya-text-muted">
                            <input
                                type="checkbox"
                                checked={Boolean(filtros.sin_coordenadas)}
                                onChange={(e) => filtrar({ sin_coordenadas: e.target.checked ? 1 : '' })}
                                className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan"
                            />
                            Solo sin coordenadas
                        </label>
                        <div className="ml-auto">
                            <PrimaryButton onClick={() => router.visit(route('puntos.create'))}>
                                Nuevo PM
                            </PrimaryButton>
                        </div>
                    </div>

                    <div className="overflow-x-auto border border-atalaya-border bg-atalaya-surface">
                        <table className="min-w-full divide-y divide-atalaya-border text-sm">
                            <thead className="bg-atalaya-canvas">
                                <tr className="text-left text-xs uppercase tracking-wider text-atalaya-text-dim">
                                    <th className="px-4 py-3 font-medium">Código</th>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium">Jurisdicción</th>
                                    <th className="px-4 py-3 font-medium">Zona</th>
                                    <th className="px-4 py-3 font-medium">Coordenadas</th>
                                    <th className="px-4 py-3 text-right font-medium">Eventos</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border">
                                {puntos.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-4 text-center text-atalaya-text-dim">
                                            No hay puntos con esos filtros.
                                        </td>
                                    </tr>
                                )}
                                {puntos.data.map((p) => (
                                    <tr key={p.id}>
                                        <td className="px-4 py-2 font-mono text-white">{p.codigo}</td>
                                        <td className="px-4 py-2 text-atalaya-text-muted">{p.nombre}</td>
                                        <td className="px-4 py-2 text-atalaya-text-muted">{jurisdicciones[p.jurisdiccion]}</td>
                                        <td className="px-4 py-2 text-atalaya-text-muted">{p.zona ?? '—'}</td>
                                        <td className="px-4 py-2 font-mono text-xs text-atalaya-text-dim">
                                            {p.latitud !== null ? (
                                                `${p.latitud}, ${p.longitud}`
                                            ) : (
                                                <span className="text-atalaya-amber">Sin relevar</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-atalaya-text-muted">{p.eventos_count}</td>
                                        <td className="whitespace-nowrap px-4 py-2 text-right">
                                            <Link
                                                href={route('puntos.edit', p.id)}
                                                className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                                            >
                                                Editar
                                            </Link>
                                            {p.eventos_count === 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => setPorEliminar(p)}
                                                    className="ml-3 font-mono text-xs font-bold uppercase text-atalaya-crimson hover:underline"
                                                >
                                                    Eliminar
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {puntos.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {puntos.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={
                                            'border px-3 py-1 text-sm ' +
                                            (link.active
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border text-atalaya-text-muted hover:bg-atalaya-canvas')
                                        }
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="border border-atalaya-border px-3 py-1 text-sm text-atalaya-text-dim"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>

            <Modal show={porEliminar !== null} onClose={() => setPorEliminar(null)} maxWidth="md">
                <div className="space-y-4 p-6">
                    <h3 className="font-mono text-sm font-bold uppercase text-white">
                        ¿Eliminar el PM {porEliminar?.codigo}?
                    </h3>
                    <p className="text-sm text-atalaya-text-muted">
                        Solo se pueden eliminar puntos sin eventos. La acción
                        queda registrada en la auditoría.
                    </p>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPorEliminar(null)}>Cancelar</SecondaryButton>
                        <DangerButton
                            onClick={() =>
                                router.delete(route('puntos.destroy', porEliminar.id), {
                                    preserveScroll: true,
                                    onFinish: () => setPorEliminar(null),
                                })
                            }
                        >
                            Eliminar
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
