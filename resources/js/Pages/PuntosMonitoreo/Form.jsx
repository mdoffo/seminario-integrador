import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorCoordenadas from '@/Components/SelectorCoordenadas';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

/**
 * US-024: alta / edición de un Punto de Monitoreo.
 */
export default function Form({ punto, jurisdicciones, limites }) {
    const { data, setData, post, put, processing, errors } = useForm({
        codigo: punto?.codigo ?? '',
        nombre: punto?.nombre ?? '',
        jurisdiccion: punto?.jurisdiccion ?? 'municipal',
        zona: punto?.zona ?? '',
        latitud: punto?.latitud ?? '',
        longitud: punto?.longitud ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        punto ? put(route('puntos.update', punto.id)) : post(route('puntos.store'));
    };

    const titulo = punto ? `Editar PM ${punto.codigo}` : 'Nuevo punto de monitoreo';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                        {titulo}
                    </h2>
                    <Link
                        href={route('puntos.index')}
                        className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                    >
                        ← Volver
                    </Link>
                </div>
            }
        >
            <Head title={titulo} />

            <div className="py-12">
                <form
                    onSubmit={submit}
                    className="mx-auto grid max-w-5xl gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-2"
                >
                    <div className="space-y-5">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="codigo" value="Código" />
                                <TextInput
                                    id="codigo"
                                    className="mt-1 block w-full"
                                    value={data.codigo}
                                    onChange={(e) => setData('codigo', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.codigo} />
                            </div>
                            <div>
                                <InputLabel htmlFor="jurisdiccion" value="Jurisdicción" />
                                <select
                                    id="jurisdiccion"
                                    className={campoClass}
                                    value={data.jurisdiccion}
                                    onChange={(e) => setData('jurisdiccion', e.target.value)}
                                >
                                    {Object.entries(jurisdicciones).map(([clave, etiqueta]) => (
                                        <option key={clave} value={clave}>
                                            {etiqueta}
                                        </option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={errors.jurisdiccion} />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="nombre" value="Nombre / ubicación" />
                            <TextInput
                                id="nombre"
                                className="mt-1 block w-full"
                                placeholder="Ej.: Bv. España y Mendoza"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.nombre} />
                        </div>

                        <div>
                            <InputLabel htmlFor="zona" value="Zona / cuadrante (opcional)" />
                            <TextInput
                                id="zona"
                                className="mt-1 block w-full"
                                value={data.zona}
                                onChange={(e) => setData('zona', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.zona} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="latitud" value="Latitud" />
                                <TextInput
                                    id="latitud"
                                    inputMode="decimal"
                                    className="mt-1 block w-full font-mono"
                                    value={data.latitud}
                                    onChange={(e) => setData('latitud', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.latitud} />
                            </div>
                            <div>
                                <InputLabel htmlFor="longitud" value="Longitud" />
                                <TextInput
                                    id="longitud"
                                    inputMode="decimal"
                                    className="mt-1 block w-full font-mono"
                                    value={data.longitud}
                                    onChange={(e) => setData('longitud', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.longitud} />
                            </div>
                        </div>
                        <p className="text-xs text-atalaya-text-dim">
                            Hacé clic en el mapa para tomar las coordenadas. Tienen
                            que caer dentro del área punteada; si todavía no se
                            relevaron, dejalas vacías.
                        </p>

                        <PrimaryButton disabled={processing}>
                            {punto ? 'Guardar cambios' : 'Crear punto'}
                        </PrimaryButton>
                    </div>

                    <SelectorCoordenadas
                        latitud={data.latitud}
                        longitud={data.longitud}
                        limites={limites}
                        onChange={(lat, lng) => setData((d) => ({ ...d, latitud: lat, longitud: lng }))}
                        className="h-80 lg:h-full lg:min-h-[420px]"
                    />
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
