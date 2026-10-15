import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Accion } from '@/Components/Tabla';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function Seccion({ titulo, children }) {
    return (
        <section className="space-y-4 border border-atalaya-border bg-atalaya-surface p-6">
            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">{titulo}</h3>
            {children}
        </section>
    );
}

/**
 * US-028: alta / edición de un grupo de WhatsApp, sus contactos y los tipos
 * de evento que recibe.
 */
export default function Form({ grupo, tipos, categorias }) {
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: grupo?.nombre ?? '',
        identificador_whatsapp: grupo?.identificador_whatsapp ?? '',
        contactos: grupo?.contactos ?? [],
        tipos_evento: grupo?.tipos_evento ?? [],
    });

    const submit = (e) => {
        e.preventDefault();
        grupo ? put(route('grupos.update', grupo.id)) : post(route('grupos.store'));
    };

    const cambiarContacto = (i, campo, valor) =>
        setData('contactos', data.contactos.map((c, j) => (j === i ? { ...c, [campo]: valor } : c)));

    const alternarTipo = (id) =>
        setData('tipos_evento', data.tipos_evento.includes(id) ? data.tipos_evento.filter((t) => t !== id) : [...data.tipos_evento, id]);

    const titulo = grupo ? `Editar grupo ${grupo.nombre}` : 'Nuevo grupo de WhatsApp';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">{titulo}</h2>
                    <Link href={route('grupos.index')} className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline">
                        ← Volver
                    </Link>
                </div>
            }
        >
            <Head title={titulo} />

            <div className="py-12">
                <form onSubmit={submit} className="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                    <div className="space-y-6">
                        <Seccion titulo="Grupo">
                            <div>
                                <InputLabel htmlFor="nombre" value="Nombre (fuerza o área destinataria)" />
                                <TextInput
                                    id="nombre"
                                    className="mt-1 block w-full"
                                    placeholder="Ej.: Bomberos"
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.nombre} />
                            </div>
                            <div>
                                <InputLabel htmlFor="identificador_whatsapp" value="Identificador del grupo de WhatsApp" />
                                <TextInput
                                    id="identificador_whatsapp"
                                    className="mt-1 block w-full font-mono"
                                    value={data.identificador_whatsapp}
                                    onChange={(e) => setData('identificador_whatsapp', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.identificador_whatsapp} />
                                <p className="mt-2 text-xs text-atalaya-text-dim">
                                    Si se completa, el mensaje va al grupo; si queda
                                    vacío, se envía a cada contacto por separado.
                                </p>
                            </div>
                        </Seccion>

                        <Seccion titulo="Contactos">
                            {data.contactos.length === 0 && (
                                <p className="text-xs text-atalaya-text-dim">Sin contactos cargados.</p>
                            )}
                            {data.contactos.map((c, i) => (
                                <div key={i} className="flex items-start gap-2">
                                    <div className="flex-1">
                                        <TextInput
                                            className="block w-full"
                                            placeholder="Nombre"
                                            aria-label={`Nombre del contacto ${i + 1}`}
                                            value={c.nombre}
                                            onChange={(e) => cambiarContacto(i, 'nombre', e.target.value)}
                                        />
                                        <InputError className="mt-1" message={errors[`contactos.${i}.nombre`]} />
                                    </div>
                                    <div className="flex-1">
                                        <TextInput
                                            className="block w-full font-mono"
                                            inputMode="numeric"
                                            placeholder="5493534000000"
                                            aria-label={`Número del contacto ${i + 1}`}
                                            value={c.numero_whatsapp}
                                            onChange={(e) => cambiarContacto(i, 'numero_whatsapp', e.target.value.replace(/\D/g, ''))}
                                        />
                                        <InputError className="mt-1" message={errors[`contactos.${i}.numero_whatsapp`]} />
                                    </div>
                                    <div className="pt-1.5">
                                        <Accion
                                            icono="eliminar"
                                            etiqueta={`Quitar contacto ${i + 1}`}
                                            variante="peligro"
                                            onClick={() => setData('contactos', data.contactos.filter((_, j) => j !== i))}
                                        />
                                    </div>
                                </div>
                            ))}
                            <SecondaryButton onClick={() => setData('contactos', [...data.contactos, { nombre: '', numero_whatsapp: '' }])}>
                                Agregar contacto
                            </SecondaryButton>
                            <p className="text-xs text-atalaya-text-dim">
                                Número con código de país, sin "+" ni espacios.
                            </p>
                        </Seccion>
                    </div>

                    <div className="space-y-6">
                        <Seccion titulo="Tipos de evento que recibe">
                            {Object.entries(categorias).map(([cat, etiqueta]) => {
                                const delGrupo = tipos.filter((t) => t.categoria === cat);

                                return delGrupo.length === 0 ? null : (
                                    <fieldset key={cat}>
                                        <legend className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-muted">
                                            {etiqueta}
                                        </legend>
                                        <div className="mt-2 grid gap-1 sm:grid-cols-2">
                                            {delGrupo.map((t) => (
                                                <label key={t.id} className="flex items-center gap-2 text-sm text-atalaya-text-muted">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.tipos_evento.includes(t.id)}
                                                        onChange={() => alternarTipo(t.id)}
                                                        className="h-4 w-4 rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
                                                    />
                                                    {t.nombre}
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                );
                            })}
                            <InputError message={errors.tipos_evento} />
                        </Seccion>

                        <PrimaryButton disabled={processing}>{grupo ? 'Guardar cambios' : 'Crear grupo'}</PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
