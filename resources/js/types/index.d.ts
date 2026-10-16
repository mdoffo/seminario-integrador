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
