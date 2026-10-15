import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

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

                    <div className="overflow-x-auto border border-atalaya-border bg-atalaya-surface">
                        <table className="min-w-full divide-y divide-atalaya-border text-sm">
                            <thead className="bg-atalaya-canvas">
                                <tr className="text-left text-xs uppercase tracking-wider text-atalaya-text-dim">
                                    <th className="px-6 py-3 font-medium">Turno</th>
                                    <th className="px-6 py-3 font-medium">Fecha</th>
                                    <th className="px-6 py-3 font-medium">Horario</th>
                                    <th className="px-6 py-3 font-medium">Supervisor</th>
                                    <th className="px-6 py-3 text-right font-medium">Eventos</th>
                                    <th className="px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border">
                                {turnos.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-atalaya-text-dim"
                                        >
                                            No hay turnos cerrados
                                            {filtros.fecha ? ' en esa fecha' : ''}.
                                        </td>
                                    </tr>
                                )}
                                {turnos.data.map((t) => (
                                    <tr key={t.id}>
                                        <td className="px-6 py-3 font-mono text-atalaya-text-dim">
                                            #{t.id}
                                        </td>
                                        <td className="px-6 py-3 text-white">{t.fecha}</td>
                                        <td className="px-6 py-3 font-mono text-atalaya-text-muted">
                                            {t.inicio} – {t.fin}
                                            {t.fecha_fin && t.fecha_fin !== t.fecha && (
                                                <span className="ml-1 text-xs text-atalaya-text-dim">
                                                    ({t.fecha_fin})
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-atalaya-text-muted">
                                            {t.supervisor}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-atalaya-text-muted">
                                            {t.eventos}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            <a
                                                href={route('reportes.turno.pdf', t.id)}
                                                className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                                            >
                                                Descargar PDF
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {turnos.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {turnos.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
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
        </AuthenticatedLayout>
    );
}
