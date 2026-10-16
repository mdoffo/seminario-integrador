/**
 * Tipos compartidos del frontend. Reflejan lo que arma Laravel: si cambia un
 * controller o HandleInertiaRequests::share(), actualizar acá.
 */

export type Rol = 'Supervisor' | 'Administrativo' | 'Administrador de sistema';

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    email_verified_at: string | null;
}

/** Turno abierto del Supervisor (HandleInertiaRequests::turnoDelSupervisor). */
export interface TurnoSupervisor {
    id: number;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm */
    hora_inicio: string;
}

/** Props que Inertia comparte con todas las páginas (HandleInertiaRequests::share). */
export interface SharedProps {
    auth: {
        user: User | null;
        roles: Rol[];
        permissions: string[];
    };
    turnoSupervisor: TurnoSupervisor | null;
    notificacionesFallidas: number;
    flash: {
        success: string | null;
        error: string | null;
        erroresImportacion: unknown;
    };
    errors: Record<string, string>;
}

/** Props de una página: las propias más las compartidas. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & SharedProps;

/** Punto de monitoreo para mapas y buscadores (PuntoMonitoreo::paraMapa). */
export interface PuntoMapa {
    id: number;
    codigo: string;
    nombre: string;
    /** Código del TipoPuntoMonitoreo (clave de `tiposPunto`). */
    tipo: string;
    barrio: string | null;
    latitud: number | null;
    longitud: number | null;
}

export type EstadoNotificacion = 'pendiente' | 'enviada' | 'fallida';

/** Envío a un grupo (EventoController::notificaciones). */
export interface Notificacion {
    id: number;
    grupo: string | null;
    estado: EstadoNotificacion;
    intentos: number;
    error: string | null;
}

/** Paginador de Laravel tal como lo serializa a JSON (LengthAwarePaginator). */
export interface Paginador<T = unknown> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
