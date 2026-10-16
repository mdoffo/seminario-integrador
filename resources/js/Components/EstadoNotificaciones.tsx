import { Accion, Insignia } from '@/Components/Tabla';
import type { EstadoNotificacion, Notificacion } from '@/types';
import { router } from '@inertiajs/react';

/**
 * US-006/US-007: estado del envío de la notificación a cada grupo
 * (enviada / pendiente / fallida) y botón de reenvío manual.
 */
const notificacionBadge: Record<EstadoNotificacion, string> = {
    enviada: 'border-emerald-500/50 bg-emerald-500/10 text-emerald-400',
    pendiente: 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    fallida: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
};

interface Props {
    notificaciones: Notificacion[];
    puedeReenviar: boolean;
}

export default function EstadoNotificaciones({ notificaciones, puedeReenviar }: Props) {
    if (notificaciones.length === 0) {
        return <span className="text-sm text-atalaya-text-dim">—</span>;
    }

    const reenviar = (id: number) => router.post(route('notificaciones.reenviar', id), {}, { preserveScroll: true });

    return (
        <ul className="space-y-1">
            {notificaciones.map((n) => (
                <li key={n.id} title={n.error ?? undefined} className="flex items-center justify-end gap-2">
                    <span className="text-xs text-atalaya-text-muted">{n.grupo}</span>
                    <Insignia color={notificacionBadge[n.estado]}>{n.estado}</Insignia>
                    {n.estado === 'fallida' && puedeReenviar && (
                        <Accion
                            icono="reenviar"
                            etiqueta={`Reenviar a ${n.grupo}`}
                            variante="advertencia"
                            onClick={() => reenviar(n.id)}
                        />
                    )}
                </li>
            ))}
        </ul>
    );
}
