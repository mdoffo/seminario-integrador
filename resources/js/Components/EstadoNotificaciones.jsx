import { router } from '@inertiajs/react';

/**
 * US-006/US-007: estado del envío de la notificación a cada grupo
 * (enviada / pendiente / fallida) y botón de reenvío manual.
 */
const notificacionBadge = {
    enviada: 'border-emerald-500/50 bg-emerald-500/10 text-emerald-400',
    pendiente: 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    fallida: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
};

export default function EstadoNotificaciones({ notificaciones, puedeReenviar }) {
    if (notificaciones.length === 0) {
        return <span className="text-atalaya-text-dim">—</span>;
    }

    const reenviar = (id) =>
        router.post(
            route('notificaciones.reenviar', id),
            {},
            { preserveScroll: true },
        );

    return (
        <ul className="space-y-1">
            {notificaciones.map((n) => (
                <li key={n.id} title={n.error ?? undefined}>
                    <span className="text-xs text-atalaya-text-muted">
                        {n.grupo}
                    </span>{' '}
                    <span
                        className={
                            'inline-flex border px-1.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                            (notificacionBadge[n.estado] ?? '')
                        }
                    >
                        {n.estado}
                    </span>
                    {n.estado === 'fallida' && puedeReenviar && (
                        <button
                            type="button"
                            onClick={(ev) => {
                                // La fila de la tabla abre el detalle: no propagar.
                                ev.stopPropagation();
                                reenviar(n.id);
                            }}
                            className="ml-2 font-mono text-[10px] font-bold uppercase text-atalaya-cyan hover:underline"
                        >
                            Reenviar
                        </button>
                    )}
                </li>
            ))}
        </ul>
    );
}
