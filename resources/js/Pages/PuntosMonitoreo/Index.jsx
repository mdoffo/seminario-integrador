import DangerButton from '@/Components/DangerButton';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Paginacion, Tabla } from '@/Components/Tabla';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import MapaPuntosMonitoreo from '@/Components/MapaPuntosMonitoreo';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
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

                    <Tabla>
                        <Cabecera>
                            <Encabezado>Código</Encabezado>
                            <Encabezado>Nombre</Encabezado>
                            <Encabezado>Jurisdicción</Encabezado>
                            <Encabezado>Zona</Encabezado>
                            <Encabezado>Coordenadas</Encabezado>
                            <Encabezado alinear="derecha">Eventos</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {puntos.data.length === 0 && (
                                <FilaVacia columnas={7}>No hay puntos con esos filtros.</FilaVacia>
                            )}
                            {puntos.data.map((p) => (
                                <Fila key={p.id}>
                                    <Celda tono="principal" mono nowrap>{p.codigo}</Celda>
                                    <Celda>{p.nombre}</Celda>
                                    <Celda nowrap>{jurisdicciones[p.jurisdiccion]}</Celda>
                                    <Celda>{p.zona ?? '—'}</Celda>
                                    <Celda tono="tenue" mono nowrap className="text-xs">
                                        {p.latitud !== null ? (
                                            `${p.latitud}, ${p.longitud}`
                                        ) : (
                                            <Insignia color="border-atalaya-amber/60 text-atalaya-amber">Sin relevar</Insignia>
                                        )}
                                    </Celda>
                                    <Celda alinear="derecha" mono>{p.eventos_count}</Celda>
                                    <CeldaAcciones>
                                        <Accion icono="editar" etiqueta={`Editar ${p.codigo}`} href={route('puntos.edit', p.id)} />
                                        <Accion
                                            icono="eliminar"
                                            etiqueta={p.eventos_count === 0 ? `Eliminar ${p.codigo}` : 'Tiene eventos: no se puede eliminar'}
                                            variante="peligro"
                                            disabled={p.eventos_count > 0}
                                            onClick={() => setPorEliminar(p)}
                                        />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={puntos} />
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
