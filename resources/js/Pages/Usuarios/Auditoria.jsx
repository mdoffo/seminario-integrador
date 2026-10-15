import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const accionBadge = {
    'Inicio de sesión': 'border-green-500 bg-green-500/10 text-green-400',
    'Cierre de sesión': 'bg-atalaya-canvas text-white',
    'Intento fallido': 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    'Cuenta bloqueada':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    'Evento registrado': 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
    'Usuario creado': 'border-green-500 bg-green-500/10 text-green-400',
    'Usuario actualizado': 'border-atalaya-sky bg-atalaya-sky/10 text-atalaya-sky',
    'Usuario eliminado':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
};

const vacios = { usuario_id: '', accion: '', desde: '', hasta: '', objeto: '', orden: '', direccion: '' };

const selectClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

const conValor = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v != null));

// Columnas ordenables (US-027): clave del backend.
const ORDENABLES = { 'Fecha y hora': 'fecha', Usuario: 'usuario', Acción: 'accion' };

export default function Auditoria({ registros, filtros, usuarios, acciones }) {
    const [form, setForm] = useState({ ...vacios, ...filtros });
    const primeraVez = useRef(true);

    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;
            return undefined;
        }

        const espera = setTimeout(
            () =>
                router.get(route('usuarios.auditoria'), conValor(form), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            350,
        );

        return () => clearTimeout(espera);
    }, [form]);

    const cambiar = (campo) => (e) => setForm((f) => ({ ...f, [campo]: e.target.value }));
    const ordenActual = form.orden || 'fecha';
    const direccionActual = form.direccion || 'desc';
    const ordenarPor = (orden) =>
        setForm((f) => ({
            ...f,
            orden,
            direccion: ordenActual === orden && direccionActual === 'desc' ? 'asc' : 'desc',
        }));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Logs de auditoría
                </h2>
            }
        >
            <Head title="Logs de auditoría" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-atalaya-cyan hover:text-white"
                    >
                        ← Volver a usuarios
                    </Link>

                    <p className="text-sm text-atalaya-text-muted">
                        Movimientos de los usuarios en el sistema: quién, con qué
                        rol, desde qué IP y qué acción realizó. El log no se
                        puede modificar ni borrar, y cada consulta queda
                        registrada.
                    </p>

                    <div className="grid grid-cols-1 gap-4 border border-atalaya-border bg-atalaya-surface p-4 md:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <InputLabel htmlFor="usuario_id" value="Usuario" />
                            <select id="usuario_id" className={selectClass} value={form.usuario_id} onChange={cambiar('usuario_id')}>
                                <option value="">Todos</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="accion" value="Acción" />
                            <select id="accion" className={selectClass} value={form.accion} onChange={cambiar('accion')}>
                                <option value="">Todas</option>
                                {Object.entries(acciones).map(([clave, etiqueta]) => (
                                    <option key={clave} value={clave}>
                                        {etiqueta}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="desde" value="Desde" />
                            <TextInput id="desde" type="date" className="mt-1 block w-full" value={form.desde} onChange={cambiar('desde')} />
                        </div>
                        <div>
                            <InputLabel htmlFor="hasta" value="Hasta" />
                            <TextInput id="hasta" type="date" className="mt-1 block w-full" value={form.hasta} onChange={cambiar('hasta')} />
                        </div>
                        <div className="md:col-span-2 lg:col-span-2">
                            <InputLabel htmlFor="objeto" value="Objeto afectado (ID de evento, código de PM…)" />
                            <TextInput
                                id="objeto"
                                type="search"
                                placeholder="Ej.: 123 o PM-M-001"
                                className="mt-1 block w-full"
                                value={form.objeto}
                                onChange={cambiar('objeto')}
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 md:col-span-3 lg:col-span-6">
                            <p className="text-xs text-atalaya-text-dim">
                                {registros.total} registro(s) con estos filtros.
                            </p>
                            <div className="flex gap-3">
                                <SecondaryButton type="button" onClick={() => setForm(vacios)}>
                                    Limpiar filtros
                                </SecondaryButton>
                                <a
                                    href={route('usuarios.auditoria.exportar', conValor(form))}
                                    className="inline-flex items-center border border-atalaya-cyan px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-atalaya-cyan hover:bg-atalaya-cyan/10"
                                >
                                    Exportar a Excel
                                </a>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto bg-atalaya-surface border border-atalaya-border">
                        <table className="min-w-full divide-y divide-atalaya-border">
                            <thead className="bg-atalaya-canvas">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        <button
                                            type="button"
                                            onClick={() => ordenarPor(ORDENABLES['Fecha y hora'])}
                                            className={'inline-flex items-center gap-1 uppercase hover:text-white ' + (ordenActual === ORDENABLES['Fecha y hora'] ? 'text-atalaya-cyan' : '')}
                                        >
                                            Fecha y hora
                                            <span aria-hidden="true">
                                                {ordenActual === ORDENABLES['Fecha y hora'] ? (direccionActual === 'asc' ? '▲' : '▼') : '↕'}
                                            </span>
                                        </button>
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        <button
                                            type="button"
                                            onClick={() => ordenarPor(ORDENABLES['Usuario'])}
                                            className={'inline-flex items-center gap-1 uppercase hover:text-white ' + (ordenActual === ORDENABLES['Usuario'] ? 'text-atalaya-cyan' : '')}
                                        >
                                            Usuario
                                            <span aria-hidden="true">
                                                {ordenActual === ORDENABLES['Usuario'] ? (direccionActual === 'asc' ? '▲' : '▼') : '↕'}
                                            </span>
                                        </button>
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Rol
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        <button
                                            type="button"
                                            onClick={() => ordenarPor(ORDENABLES['Acción'])}
                                            className={'inline-flex items-center gap-1 uppercase hover:text-white ' + (ordenActual === ORDENABLES['Acción'] ? 'text-atalaya-cyan' : '')}
                                        >
                                            Acción
                                            <span aria-hidden="true">
                                                {ordenActual === ORDENABLES['Acción'] ? (direccionActual === 'asc' ? '▲' : '▼') : '↕'}
                                            </span>
                                        </button>
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        IP
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Detalle
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border bg-atalaya-surface">
                                {registros.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-atalaya-text-dim"
                                        >
                                            No hay registros de auditoría con esos filtros.
                                        </td>
                                    </tr>
                                )}
                                {registros.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.fecha_hora}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <div className="font-medium text-white">
                                                {r.usuario}
                                            </div>
                                            {r.email && r.email !== r.usuario && (
                                                <div className="text-xs text-atalaya-text-dim">
                                                    {r.email}
                                                </div>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.rol || '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                className={
                                                    'inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                                                    (accionBadge[r.accion] ||
                                                        'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted')
                                                }
                                            >
                                                {r.accion}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.ip || '—'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-atalaya-text-muted">
                                            {r.descripcion || '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {registros.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {registros.links.map((link, i) =>
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
