import EstadoNotificaciones from '@/Components/EstadoNotificaciones';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const categoriaBadge = {
    Prevención: 'border-atalaya-orange bg-atalaya-orange/10 text-atalaya-orange',
    'Convivencia Urbana':
        'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    'Seguridad Pública':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    Informativo: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

const filtrosVacios = {
    categorias: [],
    tipo_evento_id: '',
    punto_monitoreo_id: '',
    jurisdiccion: '',
    zona: '',
    desde: '',
    hasta: '',
    hora_desde: '',
    hora_hasta: '',
    texto: '',
    orden: '',
    direccion: '',
};

// Columnas de la tabla; `orden` = clave de ordenamiento del backend (US-009).
const columnas = [
    { titulo: '#', orden: 'id' },
    { titulo: 'Fecha y hora', orden: 'fecha_hora' },
    { titulo: 'Tipo', orden: 'tipo' },
    { titulo: 'Ubicación', orden: 'punto' },
    { titulo: 'Descripción' },
    { titulo: 'Registró', orden: 'autor' },
    { titulo: 'Notificación' },
];

const selectClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

/** Quita los filtros vacíos para no ensuciar la URL. */
const conValor = (valores) =>
    Object.fromEntries(
        Object.entries(valores).filter(([, v]) =>
            Array.isArray(v) ? v.length > 0 : v !== '' && v != null,
        ),
    );

export default function Index({
    eventos,
    filtros,
    tipos,
    categorias,
    puntos,
    jurisdicciones,
    alcanceTurno,
}) {
    const { errors, auth } = usePage().props;
    // US-021: si se llegó desde un gráfico de Análisis, ofrecer volver.
    const desdeAnalisis = usePage().url.includes('origen=analisis');
    const puedeReenviar = auth.roles.includes('Supervisor');
    const puedeExportar = auth.permissions.includes('reportes.exportar');
    const [exportacion, setExportacion] = useState(null);
    const [form, setForm] = useState({ ...filtrosVacios, ...filtros });
    const primeraVez = useRef(true);

    // US-008: los resultados se actualizan solos al cambiar un filtro
    // (con una pequeña espera para no buscar en cada tecla).
    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;
            return;
        }

        const espera = setTimeout(
            () =>
                router.get(route('eventos.index'), conValor(form), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            350,
        );

        return () => clearTimeout(espera);
    }, [form]);

    const cambiar = (campo) => (e) =>
        setForm((actual) => ({ ...actual, [campo]: e.target.value }));

    const alternarCategoria = (clave) =>
        setForm((actual) => ({
            ...actual,
            categorias: actual.categorias.includes(clave)
                ? actual.categorias.filter((c) => c !== clave)
                : [...actual.categorias, clave],
        }));

    const ordenarPor = (orden) =>
        setForm((actual) => ({
            ...actual,
            orden,
            direccion:
                (actual.orden || 'fecha_hora') === orden &&
                (actual.direccion || 'desc') === 'desc'
                    ? 'asc'
                    : 'desc',
        }));

    // US-013: descarga la planilla con los filtros aplicados y confirma al terminar.
    const exportar = async () => {
        setExportacion({ estado: 'generando' });

        try {
            const respuesta = await fetch(
                route('reportes.eventos.xlsx', conValor(form)),
                { headers: { Accept: 'application/octet-stream' } },
            );

            if (!respuesta.ok) {
                throw new Error();
            }

            const nombre =
                /filename="([^"]+)"/.exec(
                    respuesta.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? 'Eventos_CMU.xlsx';
            const url = URL.createObjectURL(await respuesta.blob());
            const enlace = Object.assign(document.createElement('a'), {
                href: url,
                download: nombre,
            });
            enlace.click();
            URL.revokeObjectURL(url);

            setExportacion({
                estado: 'ok',
                mensaje: `Exportación completada: ${nombre} (${respuesta.headers.get('X-Cantidad-Eventos')} eventos).`,
            });
        } catch {
            setExportacion({
                estado: 'error',
                mensaje: 'No se pudo generar la exportación. Probá de nuevo.',
            });
        }
    };

    const ordenActual = form.orden || 'fecha_hora';
    const direccionActual = form.direccion || 'desc';
    const hayFiltros = Object.keys(
        conValor({ ...form, orden: '', direccion: '' }),
    ).length > 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Eventos
                </h2>
            }
        >
            <Head title="Eventos" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {(desdeAnalisis || form.zona) && (
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            {desdeAnalisis && (
                                <button
                                    type="button"
                                    onClick={() => window.history.back()}
                                    className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                                >
                                    ← Volver al análisis
                                </button>
                            )}
                            {form.zona && (
                                <span className="border border-atalaya-cyan/50 px-2 py-0.5 font-mono text-xs text-atalaya-cyan">
                                    Zona: {form.zona}
                                    <button
                                        type="button"
                                        aria-label="Quitar filtro de zona"
                                        onClick={() => setForm((f) => ({ ...f, zona: '' }))}
                                        className="ml-2 hover:text-white"
                                    >
                                        ×
                                    </button>
                                </span>
                            )}
                        </div>
                    )}

                    <form
                        onSubmit={(e) => e.preventDefault()}
                        className="space-y-4 border border-atalaya-border bg-atalaya-surface p-6"
                    >
                        <div>
                            <InputLabel value="Categoría" />
                            <div className="mt-1 flex flex-wrap gap-2">
                                {Object.entries(categorias).map(
                                    ([clave, etiqueta]) => {
                                        const activa =
                                            form.categorias.includes(clave);

                                        return (
                                            <button
                                                key={clave}
                                                type="button"
                                                aria-pressed={activa}
                                                onClick={() =>
                                                    alternarCategoria(clave)
                                                }
                                                className={
                                                    'border px-3 py-1 font-mono text-[11px] font-bold uppercase ' +
                                                    (activa
                                                        ? categoriaBadge[
                                                              etiqueta
                                                          ]
                                                        : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                                }
                                            >
                                                {etiqueta}
                                            </button>
                                        );
                                    },
                                )}
                            </div>
                            <InputError message={errors.categorias} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                            <div className="lg:col-span-2">
                                <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                                <select
                                    id="tipo_evento_id"
                                    className={selectClass}
                                    value={form.tipo_evento_id}
                                    onChange={cambiar('tipo_evento_id')}
                                >
                                    <option value="">Todos</option>
                                    {tipos.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.tipo_evento_id} />
                            </div>

                            <div className="lg:col-span-3">
                                <InputLabel
                                    htmlFor="punto_monitoreo_id"
                                    value="Punto de monitoreo"
                                />
                                <select
                                    id="punto_monitoreo_id"
                                    className={selectClass}
                                    value={form.punto_monitoreo_id}
                                    onChange={cambiar('punto_monitoreo_id')}
                                >
                                    <option value="">Todos</option>
                                    {puntos.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.codigo} — {p.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.punto_monitoreo_id}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="jurisdiccion"
                                    value="Jurisdicción"
                                />
                                <select
                                    id="jurisdiccion"
                                    className={selectClass}
                                    value={form.jurisdiccion}
                                    onChange={cambiar('jurisdiccion')}
                                >
                                    <option value="">Todas</option>
                                    {Object.entries(jurisdicciones).map(
                                        ([clave, etiqueta]) => (
                                            <option key={clave} value={clave}>
                                                {etiqueta}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.jurisdiccion} />
                            </div>

                            <div>
                                <InputLabel htmlFor="desde" value="Fecha desde" />
                                <TextInput
                                    id="desde"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={form.desde}
                                    onChange={cambiar('desde')}
                                />
                                <InputError message={errors.desde} />
                            </div>

                            <div>
                                <InputLabel htmlFor="hasta" value="Fecha hasta" />
                                <TextInput
                                    id="hasta"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={form.hasta}
                                    onChange={cambiar('hasta')}
                                />
                                <InputError message={errors.hasta} />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="hora_desde"
                                    value="Hora desde"
                                />
                                <TextInput
                                    id="hora_desde"
                                    type="time"
                                    className="mt-1 block w-full"
                                    value={form.hora_desde}
                                    onChange={cambiar('hora_desde')}
                                />
                                <InputError message={errors.hora_desde} />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="hora_hasta"
                                    value="Hora hasta"
                                />
                                <TextInput
                                    id="hora_hasta"
                                    type="time"
                                    className="mt-1 block w-full"
                                    value={form.hora_hasta}
                                    onChange={cambiar('hora_hasta')}
                                />
                                <InputError message={errors.hora_hasta} />
                            </div>

                            <div className="md:col-span-2">
                                <InputLabel
                                    htmlFor="texto"
                                    value="Texto en la descripción"
                                />
                                <TextInput
                                    id="texto"
                                    type="search"
                                    placeholder="Palabras clave…"
                                    className="mt-1 block w-full"
                                    value={form.texto}
                                    onChange={cambiar('texto')}
                                />
                                <InputError message={errors.texto} />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-atalaya-text-dim">
                                Los filtros se combinan entre sí. Si la hora
                                desde es mayor que la hora hasta, la franja
                                cruza la medianoche.
                            </p>
                            <SecondaryButton
                                type="button"
                                disabled={!hayFiltros}
                                onClick={() =>
                                    setForm({
                                        ...filtrosVacios,
                                        orden: form.orden,
                                        direccion: form.direccion,
                                    })
                                }
                            >
                                Limpiar filtros
                            </SecondaryButton>
                        </div>
                    </form>

                    {alcanceTurno && (
                        <p className="border border-atalaya-cyan/40 bg-atalaya-cyan/10 px-4 py-2 text-sm text-atalaya-cyan">
                            {alcanceTurno.id
                                ? `Se muestran solo los eventos de tu turno abierto (#${alcanceTurno.id}).`
                                : 'No tenés un turno abierto: abrí uno para ver y registrar eventos.'}
                        </p>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-atalaya-text-muted">
                            {eventos.total === 0
                                ? 'Sin resultados.'
                                : `Mostrando ${eventos.from}–${eventos.to} de ${eventos.total} eventos.`}
                        </p>
                        {puedeExportar && (
                            <SecondaryButton
                                type="button"
                                onClick={exportar}
                                disabled={
                                    eventos.total === 0 ||
                                    exportacion?.estado === 'generando'
                                }
                            >
                                {exportacion?.estado === 'generando'
                                    ? 'Generando…'
                                    : 'Exportar a Excel'}
                            </SecondaryButton>
                        )}
                    </div>

                    {exportacion?.mensaje && (
                        <p
                            role="status"
                            className={
                                'border px-4 py-2 text-sm ' +
                                (exportacion.estado === 'ok'
                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200'
                                    : 'border-atalaya-crimson/50 bg-atalaya-crimson/10 text-red-100')
                            }
                        >
                            {exportacion.mensaje}
                        </p>
                    )}

                    <div className="overflow-x-auto border border-atalaya-border bg-atalaya-surface">
                        <table className="min-w-full divide-y divide-atalaya-border">
                            <thead className="bg-atalaya-canvas">
                                <tr>
                                    {columnas.map(({ titulo, orden }) => (
                                        <th
                                            key={titulo}
                                            scope="col"
                                            aria-sort={
                                                orden === ordenActual
                                                    ? direccionActual ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : undefined
                                            }
                                            className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim"
                                        >
                                            {orden ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        ordenarPor(orden)
                                                    }
                                                    className={
                                                        'inline-flex items-center gap-1 uppercase hover:text-white ' +
                                                        (orden === ordenActual
                                                            ? 'text-atalaya-cyan'
                                                            : '')
                                                    }
                                                >
                                                    {titulo}
                                                    <span aria-hidden="true">
                                                        {orden === ordenActual
                                                            ? direccionActual ===
                                                              'asc'
                                                                ? '▲'
                                                                : '▼'
                                                            : '↕'}
                                                    </span>
                                                </button>
                                            ) : (
                                                titulo
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border bg-atalaya-surface">
                                {eventos.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={columnas.length}
                                            className="px-6 py-4 text-center text-sm text-atalaya-text-dim"
                                        >
                                            No hay eventos registrados con esos
                                            filtros.
                                        </td>
                                    </tr>
                                )}
                                {eventos.data.map((e) => (
                                    <tr
                                        key={e.id}
                                        onClick={() =>
                                            router.visit(
                                                route('eventos.show', e.id),
                                            )
                                        }
                                        className="cursor-pointer align-top hover:bg-atalaya-canvas"
                                    >
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.id}
                                            {e.corregido && (
                                                <div
                                                    className="mt-1 font-mono text-[10px] font-bold uppercase text-atalaya-amber"
                                                    title="Tiene fe de errata: el detalle muestra los datos vigentes"
                                                >
                                                    Corregido
                                                </div>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-white">
                                            {e.fecha_hora}
                                            <div className="text-xs text-atalaya-text-dim">
                                                Turno #{e.turno_id}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            <div className="font-medium text-white">
                                                {e.tipo}
                                            </div>
                                            <span
                                                className={
                                                    'mt-1 inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                                                    (categoriaBadge[
                                                        e.categoria
                                                    ] ||
                                                        'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted')
                                                }
                                            >
                                                {e.categoria}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.punto ?? (
                                                <span className="inline-flex border border-atalaya-cyan/60 px-1.5 font-mono text-[10px] font-bold uppercase leading-4 text-atalaya-cyan">
                                                    Sin ubicación
                                                </span>
                                            )}
                                            {e.timestamp_video && (
                                                <div className="text-xs text-atalaya-text-dim">
                                                    Video {e.timestamp_video}
                                                </div>
                                            )}
                                        </td>
                                        <td className="max-w-md px-6 py-4 text-sm text-atalaya-text-muted">
                                            {e.descripcion ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.autor}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <EstadoNotificaciones
                                                notificaciones={
                                                    e.notificaciones
                                                }
                                                puedeReenviar={puedeReenviar}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {eventos.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {eventos.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={
                                            'rounded-none border px-3 py-1 text-sm ' +
                                            (link.active
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border bg-atalaya-surface text-atalaya-text-muted hover:bg-atalaya-canvas')
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded-none border border-atalaya-border px-3 py-1 text-sm text-atalaya-text-dim"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
