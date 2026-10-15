import EstadoNotificaciones from '@/Components/EstadoNotificaciones';
import { Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Paginacion, Tabla } from '@/Components/Tabla';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const categoriaBadge = {
    Policía: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    'Seguridad Ciudadana':
        'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    'Oficios Recepcionados':
        'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

const filtrosVacios = {
    categorias: [],
    tipo_evento_id: '',
    punto_monitoreo_id: '',
    tipo_punto: '',
    barrio_id: '',
    cuadrante_id: '',
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
    { titulo: 'Notificación', alinear: 'derecha' },
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
    tiposPunto,
    barrios,
    cuadrantes,
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
                    {desdeAnalisis && (
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                            >
                                ← Volver al análisis
                            </button>
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

                            <div className="lg:col-span-2">
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
                                    htmlFor="tipo_punto"
                                    value="Tipo de PM"
                                />
                                <select
                                    id="tipo_punto"
                                    className={selectClass}
                                    value={form.tipo_punto}
                                    onChange={cambiar('tipo_punto')}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(tiposPunto).map(
                                        ([clave, etiqueta]) => (
                                            <option key={clave} value={clave}>
                                                {etiqueta}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.tipo_punto} />
                            </div>

                            <div>
                                <InputLabel htmlFor="barrio_id" value="Barrio" />
                                <select
                                    id="barrio_id"
                                    className={selectClass}
                                    value={form.barrio_id}
                                    onChange={cambiar('barrio_id')}
                                >
                                    <option value="">Todos</option>
                                    {barrios.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.barrio_id} />
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

                            {/* Cuadrante: solo si hay cuadrantes cargados. */}
                            {cuadrantes.length > 0 && (
                                <div>
                                    <InputLabel htmlFor="cuadrante_id" value="Cuadrante" />
                                    <select
                                        id="cuadrante_id"
                                        className={selectClass}
                                        value={form.cuadrante_id}
                                        onChange={cambiar('cuadrante_id')}
                                    >
                                        <option value="">Todos</option>
                                        {cuadrantes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.cuadrante_id} />
                                </div>
                            )}

                            <div className={cuadrantes.length > 0 ? 'md:col-span-2 lg:col-span-1' : 'md:col-span-2'}>
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

                    <Tabla>
                        <Cabecera>
                            {columnas.map(({ titulo, orden, alinear }) => (
                                <Encabezado
                                    key={titulo}
                                    alinear={alinear}
                                    orden={orden}
                                    ordenActual={ordenActual}
                                    direccion={direccionActual}
                                    onOrdenar={ordenarPor}
                                >
                                    {titulo}
                                </Encabezado>
                            ))}
                        </Cabecera>
                        <Cuerpo>
                            {eventos.data.length === 0 && (
                                <FilaVacia columnas={columnas.length}>No hay eventos registrados con esos filtros.</FilaVacia>
                            )}
                            {eventos.data.map((e) => (
                                <Fila key={e.id} onClick={() => router.visit(route('eventos.show', e.id))}>
                                    <Celda tono="tenue" mono nowrap>
                                        {e.id}
                                        {e.corregido && (
                                            <div className="mt-1">
                                                <Insignia
                                                    color="border-atalaya-amber/60 text-atalaya-amber"
                                                    title="Tiene fe de errata: el detalle muestra los datos vigentes"
                                                >
                                                    Corregido
                                                </Insignia>
                                            </div>
                                        )}
                                    </Celda>
                                    <Celda tono="principal" mono nowrap>
                                        {e.fecha_hora}
                                        <div className="font-sans text-xs text-atalaya-text-dim">Turno #{e.turno_id}</div>
                                    </Celda>
                                    <Celda>
                                        <div className="font-medium text-white">{e.tipo}</div>
                                        <div className="mt-1">
                                            <Insignia color={categoriaBadge[e.categoria]}>{e.categoria}</Insignia>
                                        </div>
                                    </Celda>
                                    <Celda tono="tenue">
                                        {e.punto ?? (
                                            <Insignia color="border-atalaya-cyan/60 text-atalaya-cyan">Sin ubicación</Insignia>
                                        )}
                                        {e.timestamp_video && (
                                            <div className="font-mono text-xs text-atalaya-text-dim">Video {e.timestamp_video}</div>
                                        )}
                                    </Celda>
                                    <Celda className="max-w-md">{e.descripcion ?? '—'}</Celda>
                                    <Celda tono="tenue" nowrap>{e.autor}</Celda>
                                    <CeldaAcciones>
                                        <EstadoNotificaciones notificaciones={e.notificaciones} puedeReenviar={puedeReenviar} />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={eventos} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
