import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** Entrada de Parametro::DEFINICIONES. */
interface Definicion {
    grupo: string;
    etiqueta: string;
    unidad: string;
    defecto: number;
    min: number;
    max: number;
    ayuda: string;
}

/**
 * US-029: parámetros generales del sistema, agrupados por tema. Cada uno
 * muestra su valor por defecto y el rango permitido.
 */
export default function Index({
    definiciones,
    valores,
}: {
    /** clave => definición. */
    definiciones: Record<string, Definicion>;
    /** clave => valor vigente (Parametro::vigentes). */
    valores: Record<string, number>;
}) {
    const { data, setData, put, processing, errors, isDirty, reset, setDefaults } =
        // Mientras se edita, el input deja el valor como texto.
        useForm<Record<string, number | string>>(valores);

    const grupos = Object.entries(definiciones).reduce((acc, [clave, d]) => {
        (acc[d.grupo] ??= []).push([clave, d]);
        return acc;
    }, {} as Record<string, [string, Definicion][]>);

    const restablecer = () =>
        setData(Object.fromEntries(Object.entries(definiciones).map(([clave, d]) => [clave, d.defecto])));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('parametros.update'), { preserveScroll: true, onSuccess: () => setDefaults() });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Parámetros del sistema
                </h2>
            }
        >
            <Head title="Parámetros del sistema" />

            <div className="py-12">
                <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {Object.entries(grupos).map(([grupo, items]) => (
                        <section key={grupo} className="border border-atalaya-border bg-atalaya-surface">
                            <h3 className="border-b border-atalaya-border px-6 py-3 font-mono text-xs font-bold uppercase tracking-widest text-white">
                                {grupo}
                            </h3>
                            <div className="divide-y divide-atalaya-border">
                                {items.map(([clave, d]) => {
                                    const modificado = Number(data[clave]) !== d.defecto;

                                    return (
                                        <div key={clave} className="grid gap-3 px-6 py-4 sm:grid-cols-[1fr_auto] sm:items-center">
                                            <div>
                                                <label htmlFor={clave} className="text-sm text-white">
                                                    {d.etiqueta}
                                                </label>
                                                <p className="mt-1 text-xs text-atalaya-text-dim">
                                                    {d.ayuda} Por defecto: {d.defecto} · rango {d.min}–{d.max}.
                                                </p>
                                                <InputError className="mt-1" message={errors[clave]} />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <TextInput
                                                    id={clave}
                                                    type="number"
                                                    min={d.min}
                                                    max={d.max}
                                                    className={`w-24 text-right font-mono ${modificado ? '!border-atalaya-amber' : ''}`}
                                                    value={data[clave]}
                                                    onChange={(e) => setData(clave, e.target.value)}
                                                />
                                                <span className="w-20 font-mono text-xs text-atalaya-text-muted">{d.unidad}</span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>
                    ))}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <SecondaryButton onClick={restablecer}>Restablecer valores por defecto</SecondaryButton>
                        <div className="flex items-center gap-3">
                            {isDirty && <span className="font-mono text-xs text-atalaya-amber">Cambios sin guardar</span>}
                            <SecondaryButton disabled={!isDirty || processing} onClick={() => reset()}>
                                Descartar
                            </SecondaryButton>
                            <PrimaryButton disabled={!isDirty || processing}>
                                {processing ? 'Guardando…' : 'Guardar'}
                            </PrimaryButton>
                        </div>
                    </div>
                    <p className="text-xs text-atalaya-text-dim">
                        Los valores con borde ámbar difieren del valor por defecto.
                        Cada cambio queda en el log de auditoría con el valor
                        anterior y el nuevo.
                    </p>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
