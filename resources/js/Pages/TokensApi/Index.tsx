import DangerButton from '@/Components/DangerButton';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Select from '@/Components/Select';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Tabla } from '@/Components/Tabla';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

/** Token de la API pública (TokenApiController::index). */
interface TokenFila {
    id: number;
    nombre: string;
    /** null si el usuario dueño ya no existe. */
    duenio: string | null;
    /** dd/mm/aaaa hh:mm */
    creado: string | null;
    ultimo_uso: string | null;
    /** dd/mm/aaaa; null = no vence. */
    vence: string | null;
    vencido: boolean;
}

interface Props {
    tokens: TokenFila[];
    usuarios: { id: number; name: string; username: string }[];
    /** Recién emitido: el texto plano llega una sola vez. */
    tokenNuevo: { nombre: string; token: string } | null;
}

/** Recuadro con el token recién emitido y el botón para copiarlo. */
function TokenEmitido({ nombre, token }: { nombre: string; token: string }) {
    const [copiado, setCopiado] = useState(false);

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(token);
            setCopiado(true);
        } catch {
            // Sin permiso de portapapeles (p. ej. http sin TLS): queda seleccionarlo a mano.
            setCopiado(false);
        }
    };

    return (
        <div className="space-y-2 border border-atalaya-amber/60 bg-atalaya-amber/10 p-4">
            <p className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-amber">
                Token "{nombre}": copialo ahora, no se vuelve a mostrar
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <code className="min-w-0 flex-1 select-all break-all border border-atalaya-border bg-atalaya-canvas px-3 py-2 font-mono text-xs text-white">
                    {token}
                </code>
                <SecondaryButton type="button" onClick={copiar}>
                    {copiado ? 'Copiado' : 'Copiar'}
                </SecondaryButton>
            </div>
            <p className="text-xs text-atalaya-text-muted">
                Se envía en cada pedido como <span className="font-mono text-white">Authorization: Bearer &lt;token&gt;</span>{' '}
                (ver docs/api.md).
            </p>
        </div>
    );
}

/**
 * Tokens de la API pública de Datos Abiertos (/api/v1, solo lectura):
 * emitir, ver uso y revocar. Administrador de sistema.
 */
export default function Index({ tokens, usuarios, tokenNuevo }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [porRevocar, setPorRevocar] = useState<TokenFila | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        nombre: '',
        user_id: String(auth.user?.id ?? ''),
        dias: '365',
    });

    const emitir = (e: FormEvent) => {
        e.preventDefault();
        post(route('tokens.store'), {
            preserveScroll: true,
            onSuccess: () => reset('nombre'),
        });
    };

    const revocar = () => {
        if (!porRevocar) return;
        router.delete(route('tokens.destroy', porRevocar.id), {
            preserveScroll: true,
            onFinish: () => setPorRevocar(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Tokens de la API
                </h2>
            }
        >
            <Head title="Tokens de la API" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {tokenNuevo && <TokenEmitido nombre={tokenNuevo.nombre} token={tokenNuevo.token} />}

                    <form
                        onSubmit={emitir}
                        className="grid grid-cols-1 gap-4 border border-atalaya-border bg-atalaya-surface p-4 md:grid-cols-4 md:items-end"
                    >
                        <div className="md:col-span-2">
                            <InputLabel htmlFor="nombre" value="Para qué / quién es" />
                            <TextInput
                                id="nombre"
                                className="mt-1 block w-full"
                                placeholder="Portal de Datos Abiertos"
                                maxLength={100}
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                            />
                            <InputError className="mt-1" message={errors.nombre} />
                        </div>
                        <div>
                            <InputLabel htmlFor="user_id" value="Usuario dueño" />
                            <Select
                                id="user_id"
                                className="mt-1"
                                value={data.user_id}
                                onChange={(e) => setData('user_id', e.target.value)}
                            >
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} ({u.username})
                                    </option>
                                ))}
                            </Select>
                            <InputError className="mt-1" message={errors.user_id} />
                        </div>
                        <div>
                            <InputLabel htmlFor="dias" value="Vence en (días)" />
                            <TextInput
                                id="dias"
                                type="number"
                                min={1}
                                max={3650}
                                placeholder="Sin vencimiento"
                                className="mt-1 block w-full"
                                value={data.dias}
                                onChange={(e) => setData('dias', e.target.value)}
                            />
                            <InputError className="mt-1" message={errors.dias} />
                        </div>
                        <div className="flex items-center justify-between gap-3 md:col-span-4">
                            <p className="text-xs text-atalaya-text-dim">
                                Solo lectura de /api/v1 (Datos Abiertos). Vacío en "vence" = no vence.
                            </p>
                            <PrimaryButton disabled={processing || data.nombre.trim() === ''}>Emitir token</PrimaryButton>
                        </div>
                    </form>

                    <Tabla titulo="Tokens emitidos">
                        <Cabecera>
                            <Encabezado>Nombre</Encabezado>
                            <Encabezado>Dueño</Encabezado>
                            <Encabezado>Creado</Encabezado>
                            <Encabezado>Último uso</Encabezado>
                            <Encabezado>Vence</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {tokens.length === 0 && <FilaVacia columnas={6}>No hay tokens emitidos.</FilaVacia>}
                            {tokens.map((t) => (
                                <Fila key={t.id}>
                                    <Celda tono="principal">{t.nombre}</Celda>
                                    <Celda nowrap>{t.duenio ?? '—'}</Celda>
                                    <Celda tono="tenue" mono nowrap>{t.creado ?? '—'}</Celda>
                                    <Celda tono="tenue" mono nowrap>{t.ultimo_uso ?? 'Nunca'}</Celda>
                                    <Celda mono nowrap>
                                        {t.vence ?? 'No vence'}
                                        {t.vencido && (
                                            <span className="ml-2">
                                                <Insignia color="border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson">
                                                    Vencido
                                                </Insignia>
                                            </span>
                                        )}
                                    </Celda>
                                    <CeldaAcciones>
                                        <Accion
                                            icono="eliminar"
                                            etiqueta={`Revocar ${t.nombre}`}
                                            variante="peligro"
                                            onClick={() => setPorRevocar(t)}
                                        />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>
                </div>
            </div>

            <Modal show={porRevocar !== null} onClose={() => setPorRevocar(null)} maxWidth="md">
                <div className="space-y-4 p-6">
                    <h3 className="font-mono text-sm font-bold uppercase text-white">
                        ¿Revocar el token "{porRevocar?.nombre}"?
                    </h3>
                    <p className="text-sm text-atalaya-text-muted">
                        Quien lo use deja de tener acceso a la API de inmediato. La
                        acción queda registrada en la auditoría.
                    </p>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPorRevocar(null)}>Cancelar</SecondaryButton>
                        <DangerButton onClick={revocar}>Revocar</DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
