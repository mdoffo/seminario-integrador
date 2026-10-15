import InputLabel from '@/Components/InputLabel';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Paginacion, Tabla } from '@/Components/Tabla';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

/**
 * US-012: elegir un turno cerrado y descargar su reporte PDF.
 */
export default function Index({ turnos, filtros }) {
    const filtrarPorFecha = (fecha) =>
        router.get(
            route('reportes.index'),
            fecha ? { fecha } : {},
            { preserveState: true, replace: true },
        );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Reportes de turno
                </h2>
            }
        >
            <Head title="Reportes" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div>
                            <InputLabel htmlFor="fecha" value="Fecha del turno" />
                            <TextInput
                                id="fecha"
                                type="date"
                                className="mt-1 block"
                                value={filtros.fecha ?? ''}
                                onChange={(e) => filtrarPorFecha(e.target.value)}
                            />
                        </div>
                        {filtros.fecha && (
                            <SecondaryButton onClick={() => filtrarPorFecha('')}>
                                Ver todos
                            </SecondaryButton>
                        )}
                        <p className="ml-auto max-w-sm text-xs text-atalaya-text-dim">
                            Solo se listan turnos cerrados. El PDF incluye
                            exclusivamente los eventos de ese turno.
                        </p>
                    </div>

                    <Tabla>
                        <Cabecera>
                            <Encabezado>Turno</Encabezado>
                            <Encabezado>Fecha</Encabezado>
                            <Encabezado>Horario</Encabezado>
                            <Encabezado>Supervisor</Encabezado>
                            <Encabezado alinear="derecha">Eventos</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {turnos.data.length === 0 && (
                                <FilaVacia columnas={6}>
                                    No hay turnos cerrados{filtros.fecha ? ' en esa fecha' : ''}.
                                </FilaVacia>
                            )}
                            {turnos.data.map((t) => (
                                <Fila key={t.id}>
                                    <Celda tono="tenue" mono>#{t.id}</Celda>
                                    <Celda tono="principal" mono nowrap>{t.fecha}</Celda>
                                    <Celda mono nowrap>
                                        {t.inicio} – {t.fin}
                                        {t.fecha_fin && t.fecha_fin !== t.fecha && (
                                            <span className="ml-1 text-xs text-atalaya-text-dim">({t.fecha_fin})</span>
                                        )}
                                    </Celda>
                                    <Celda>{t.supervisor}</Celda>
                                    <Celda alinear="derecha" mono>{t.eventos}</Celda>
                                    <CeldaAcciones>
                                        <Accion
                                            icono="descargar"
                                            etiqueta={`Descargar el PDF del turno #${t.id}`}
                                            href={route('reportes.turno.pdf', t.id)}
                                            descarga
                                        />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={turnos} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
