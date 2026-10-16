import DangerButton from '@/Components/DangerButton';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Tabla } from '@/Components/Tabla';
import FlashMessages from '@/Components/FlashMessages';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import type { Rol } from '@/types';
import { useState } from 'react';

interface UsuarioFila {
    id: number;
    name: string;
    username: string;
    email: string;
    roles: Rol[];
    /** dd/mm/aaaa */
    created_at: string | null;
}

const rolBadge: Record<Rol, string> = {
    Supervisor: 'border-atalaya-orange bg-atalaya-orange/10 text-atalaya-orange',
    Administrativo: 'border-atalaya-sky bg-atalaya-sky/10 text-atalaya-sky',
    'Administrador de sistema':
        'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

export default function Index({ usuarios, authUserId, roles }: { usuarios: UsuarioFila[]; authUserId: number; roles: Rol[] }) {
    const [porEliminar, setPorEliminar] = useState<UsuarioFila | null>(null);

    const eliminar = () => {
        if (!porEliminar) return;
        router.delete(route('usuarios.destroy', porEliminar.id), {
            preserveScroll: true,
            onFinish: () => setPorEliminar(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Usuarios
                </h2>
            }
        >
            <Head title="Usuarios" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    <div className="flex items-center justify-between">
                        <div className="flex gap-4">
                            <Link
                                href={route('roles.index')}
                                className="text-sm font-medium text-atalaya-cyan hover:text-white"
                            >
                                Ver roles y permisos →
                            </Link>
                            <Link
                                href={route('usuarios.auditoria')}
                                className="text-sm font-medium text-atalaya-cyan hover:text-white"
                            >
                                Ver logs de auditoría →
                            </Link>
                        </div>
                        <PrimaryButton
                            onClick={() =>
                                router.visit(route('usuarios.create'))
                            }
                        >
                            Nuevo usuario
                        </PrimaryButton>
                    </div>

                    <Tabla>
                        <Cabecera>
                            <Encabezado>Nombre</Encabezado>
                            <Encabezado>Usuario</Encabezado>
                            <Encabezado>Email</Encabezado>
                            <Encabezado>Rol</Encabezado>
                            <Encabezado>Alta</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {usuarios.length === 0 && (
                                <FilaVacia columnas={6}>No hay usuarios.</FilaVacia>
                            )}
                            {usuarios.map((u) => (
                                <Fila key={u.id}>
                                    <Celda tono="principal" nowrap>
                                        {u.name}
                                        {u.id === authUserId && (
                                            <span className="ml-2 text-xs text-atalaya-text-dim">(vos)</span>
                                        )}
                                    </Celda>
                                    <Celda mono nowrap>{u.username}</Celda>
                                    <Celda tono="tenue" nowrap>{u.email}</Celda>
                                    <Celda>
                                        {/* Solo los roles asignados; se editan desde el formulario. */}
                                        <div className="flex flex-wrap gap-1">
                                            {roles
                                                .filter((r) => u.roles.includes(r))
                                                .map((r) => (
                                                    <Insignia key={r} color={rolBadge[r]}>
                                                        {r}
                                                    </Insignia>
                                                ))}
                                        </div>
                                    </Celda>
                                    <Celda tono="tenue" mono nowrap>{u.created_at}</Celda>
                                    <CeldaAcciones>
                                        <Accion icono="editar" etiqueta="Editar usuario" href={route('usuarios.edit', u.id)} />
                                        {u.id !== authUserId && (
                                            <Accion
                                                icono="eliminar"
                                                etiqueta="Eliminar usuario"
                                                variante="peligro"
                                                onClick={() => setPorEliminar(u)}
                                            />
                                        )}
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>
                </div>
            </div>

            <Modal show={!!porEliminar} onClose={() => setPorEliminar(null)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-white">
                        ¿Eliminar a {porEliminar?.name}?
                    </h2>
                    <p className="mt-1 text-sm text-atalaya-text-muted">
                        Esta acción no se puede deshacer.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPorEliminar(null)}>
                            Cancelar
                        </SecondaryButton>
                        <DangerButton onClick={eliminar}>Eliminar</DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
