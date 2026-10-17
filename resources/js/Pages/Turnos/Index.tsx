import FlashMessages from '@/Components/FlashMessages';
import { Cabecera, Celda, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Paginacion, Tabla } from '@/Components/Tabla';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PersonalPresenteInput from '@/Components/PersonalPresenteInput';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginador } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface TurnoFila {
    id: number;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm */
    hora_inicio: string;
    fecha_fin: string | null;
    hora_fin: string | null;
    supervisor: string | null;
    personal_presente: string[];
    novedades_pendientes: string | null;
    eventos_count: number;
    abierto: boolean;
}

interface TurnoAbierto {
    id: number;
    fecha: string;
    hora_inicio: string;
    personal_presente: string[];
}

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

/** Tarjeta para abrir un turno cuando el supervisor no tiene ninguno activo. */
function AbrirTurno() {
    const { data, setData, post, processing, errors, reset } = useForm<{ personal_presente: string[] }>({
        personal_presente: [],
    });
    // personal_presente.N y turno (SinTurnoActivo…) no son campos del form.
    const errores = errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('turnos.store'), { onSuccess: () => reset() });
    };

    return (
        <form
            onSubmit={submit}
            className="border border-atalaya-border bg-atalaya-surface p-6"
        >
            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-orange">
                Sin turno abierto
            </h3>
            <p className="mt-1 font-mono text-[11px] text-atalaya-text-muted">
                Abrí un turno para poder registrar eventos (RN-05). Se registra
                tu usuario y la fecha/hora de inicio automáticamente.
            </p>

            <div className="mt-4">
                <InputLabel htmlFor="personal_presente">
                    Personal presente
                    <span className="text-atalaya-crimson"> *</span>
                </InputLabel>
                <PersonalPresenteInput
                    value={data.personal_presente}
                    onChange={(nombres) => setData('personal_presente', nombres)}
                />
                <InputError
                    className="mt-2"
                    message={
                        errores.personal_presente ||
                        errores['personal_presente.0'] ||
                        errores.turno
                    }
                />
            </div>

            <div className="mt-4 flex justify-end">
                <PrimaryButton disabled={processing}>Abrir turno</PrimaryButton>
            </div>
        </form>
    );
}

/** Tarjeta del turno activo, con el formulario de cierre. */
function TurnoActivo({ turno }: { turno: TurnoAbierto }) {
    const { data, setData, patch, processing, errors } = useForm({
        novedades_pendientes: '',
    });
    const errores = errors as Record<string, string | undefined>;

    const cerrar = (e: FormEvent) => {
        e.preventDefault();
        patch(route('turnos.cerrar', turno.id));
    };

    return (
        <form
            onSubmit={cerrar}
            className="border border-atalaya-cyan/40 bg-atalaya-cyan/10 p-6"
        >
            <div className="flex items-center justify-between">
                <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-cyan">
                    Turno #{turno.id} abierto
                </h3>
                <span className="font-mono text-[11px] text-atalaya-text-muted">
                    Desde {turno.hora_inicio} · {turno.fecha}
                </span>
            </div>

            <div className="mt-2 flex flex-wrap gap-1.5">
                {turno.personal_presente.map((nombre, i) => (
                    <span
                        key={`${nombre}-${i}`}
                        className="border border-atalaya-border bg-atalaya-canvas px-2 py-0.5 font-mono text-[11px] text-white"
                    >
                        {nombre}
                    </span>
                ))}
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="novedades_pendientes">
                    Novedades pendientes
                    <span className="font-normal text-atalaya-text-dim">
                        {' '}
                        (opcional)
                    </span>
                </InputLabel>
                <textarea
                    id="novedades_pendientes"
                    rows={3}
                    maxLength={2000}
                    className={campoClass}
                    value={data.novedades_pendientes}
                    onChange={(e) =>
                        setData('novedades_pendientes', e.target.value)
                    }
                    placeholder="Novedades a dejar asentadas para el próximo turno…"
                />
                <InputError
                    className="mt-2"
                    message={errores.novedades_pendientes || errores.turno}
                />
            </div>

            <div className="mt-4 flex justify-end">
                <PrimaryButton disabled={processing}>Cerrar turno</PrimaryButton>
            </div>
        </form>
    );
}

export default function Index({ turnos, turnoActivo }: { turnos: Paginador<TurnoFila>; turnoActivo: TurnoAbierto | null }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Turnos de guardia
                </h2>
            }
        >
            <Head title="Turnos de guardia" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {turnoActivo ? (
                        <TurnoActivo turno={turnoActivo} />
                    ) : (
                        <AbrirTurno />
                    )}

                    <Tabla titulo="Historial de turnos">
                        <Cabecera>
                            <Encabezado>#</Encabezado>
                            <Encabezado>Fecha</Encabezado>
                            <Encabezado>Supervisor</Encabezado>
                            <Encabezado>Inicio</Encabezado>
                            <Encabezado>Fin</Encabezado>
                            <Encabezado>Personal</Encabezado>
                            <Encabezado alinear="derecha">Eventos</Encabezado>
                            <Encabezado>Estado</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {turnos.data.length === 0 && (
                                <FilaVacia columnas={8}>Todavía no hay turnos registrados.</FilaVacia>
                            )}
                            {turnos.data.map((t) => (
                                <Fila key={t.id}>
                                    <Celda tono="tenue" mono>{t.id}</Celda>
                                    <Celda tono="principal" mono nowrap>{t.fecha}</Celda>
                                    <Celda tono="principal">{t.supervisor ?? '—'}</Celda>
                                    <Celda mono nowrap>{t.hora_inicio}</Celda>
                                    <Celda mono nowrap>
                                        {t.hora_fin
                                            ? `${t.hora_fin}${t.fecha_fin && t.fecha_fin !== t.fecha ? ` (${t.fecha_fin})` : ''}`
                                            : '—'}
                                    </Celda>
                                    <Celda>{t.personal_presente.length > 0 ? t.personal_presente.join(', ') : '—'}</Celda>
                                    <Celda alinear="derecha" mono>{t.eventos_count}</Celda>
                                    <Celda nowrap>
                                        {t.abierto ? (
                                            <Insignia color="border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan">Abierto</Insignia>
                                        ) : (
                                            <Insignia>Cerrado</Insignia>
                                        )}
                                    </Celda>
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
