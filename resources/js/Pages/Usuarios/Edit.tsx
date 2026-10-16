import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Rol } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type FormUsuario = {
    name: string;
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: Rol[];
};

interface Props {
    usuario: { id: number; name: string; username: string; email: string; roles: Rol[] };
    roles: Rol[];
    /** Entre Administradores de sistema no se blanquea la clave. */
    puedeResetPassword: boolean;
    /** El rol Administrador de sistema no se le quita a quien ya lo tiene. */
    rolAdminFijo: boolean;
}


export default function Edit({ usuario, roles, puedeResetPassword, rolAdminFijo }: Props) {
    const { data, setData, put, processing, errors } = useForm<FormUsuario>({
        name: usuario.name,
        username: usuario.username,
        email: usuario.email,
        password: '',
        password_confirmation: '',
        roles: usuario.roles ?? [],
    });

    const toggleRol = (rol: Rol) => {
        setData(
            'roles',
            data.roles.includes(rol)
                ? data.roles.filter((r) => r !== rol)
                : [...data.roles, rol],
        );
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('usuarios.update', usuario.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Editar usuario
                </h2>
            }
        >
            <Head title="Editar usuario" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-atalaya-surface p-6 border border-atalaya-border"
                    >
                        <div>
                            <InputLabel htmlFor="name" value="Nombre" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                isFocused
                            />
                            <InputError
                                className="mt-2"
                                message={errors.name}
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="username" value="Usuario" />
                            <TextInput
                                id="username"
                                className="mt-1 block w-full"
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.username}
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="email" value="Email" />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.email}
                            />
                        </div>

                        <div>
                            <InputLabel value="Roles" />
                            <div className="mt-2 space-y-2">
                                {roles.map((r) => {
                                    const fijo = rolAdminFijo && r === 'Administrador de sistema';

                                    return (
                                        <label
                                            key={r}
                                            className={`flex items-center gap-2 text-sm text-atalaya-text-muted ${fijo ? 'cursor-not-allowed' : 'cursor-pointer'}`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={data.roles.includes(r)}
                                                disabled={fijo}
                                                onChange={() => toggleRol(r)}
                                                className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan disabled:opacity-60"
                                            />
                                            <span className="font-mono text-xs">
                                                {r}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {rolAdminFijo && (
                                <p className="mt-2 text-xs text-atalaya-text-dim">
                                    El rol Administrador de sistema no se puede quitar.
                                </p>
                            )}
                            <InputError
                                className="mt-2"
                                message={errors.roles}
                            />
                        </div>

                        {puedeResetPassword ? (
                            <>
                                <div className="border-t border-atalaya-border pt-4">
                                    <p className="text-sm text-atalaya-text-dim">
                                        Dejá la contraseña en blanco para no
                                        cambiarla.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password"
                                        value="Nueva contraseña"
                                    />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.password}
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password_confirmation"
                                        value="Confirmar nueva contraseña"
                                    />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="border-t border-atalaya-border pt-4">
                                <p className="text-sm text-atalaya-text-dim">
                                    La contraseña de un Administrador de sistema
                                    no se blanquea desde acá: esa cuenta la
                                    recupera por email.
                                </p>
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-3">
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    router.visit(route('usuarios.index'))
                                }
                            >
                                Cancelar
                            </SecondaryButton>
                            <PrimaryButton disabled={processing}>
                                Guardar cambios
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
