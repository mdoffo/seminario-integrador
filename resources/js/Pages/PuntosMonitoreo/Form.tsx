import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorCoordenadas from '@/Components/SelectorCoordenadas';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { ItemCatalogo, TipoPuntoItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** PM a editar (null = alta). */
interface PuntoEditable {
    id: number;
    codigo: string;
    nombre: string;
    tipo_punto_monitoreo_id: number;
    barrio_id: number | null;
    cuadrante_id: number | null;
    descripcion: string | null;
    latitud: number | null;
    longitud: number | null;
}

interface Props {
    punto: PuntoEditable | null;
    tiposPunto: TipoPuntoItem[];
    barrios: ItemCatalogo[];
    cuadrantes: ItemCatalogo[];
    /** Área válida (PuntoMonitoreo::LIMITES): [mín, máx]. */
    limites: { latitud: [number, number]; longitud: [number, number] };
}

/** Selects y coordenadas vacíos = ''. */
type FormPunto = {
    codigo: string;
    nombre: string;
    tipo_punto_monitoreo_id: number | string;
    barrio_id: number | string;
    cuadrante_id: number | string;
    descripcion: string;
    latitud: number | string;
    longitud: number | string;
};

/**
 * US-024: alta / edición de un Punto de Monitoreo.
 */
export default function Form({ punto, tiposPunto, barrios, cuadrantes, limites }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormPunto>({
        codigo: punto?.codigo ?? '',
        nombre: punto?.nombre ?? '',
        tipo_punto_monitoreo_id: punto?.tipo_punto_monitoreo_id ?? tiposPunto[0]?.id ?? '',
        barrio_id: punto?.barrio_id ?? '',
        cuadrante_id: punto?.cuadrante_id ?? '',
        descripcion: punto?.descripcion ?? '',
        latitud: punto?.latitud ?? '',
        longitud: punto?.longitud ?? '',
    });

    const submit = (e: FormEvent) => {
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

            <div className="px-4 py-12 sm:px-6 lg:px-8">
                <form
                    onSubmit={submit}
                    className="mx-auto grid max-w-7xl gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-5"
                >
                    <div className="space-y-5 lg:col-span-2">
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
                                <InputLabel htmlFor="tipo_punto_monitoreo_id" value="Tipo" />
                                <Select
                                    id="tipo_punto_monitoreo_id"
                                    className="mt-1"
                                    value={data.tipo_punto_monitoreo_id}
                                    onChange={(e) => setData('tipo_punto_monitoreo_id', e.target.value)}
                                >
                                    {tiposPunto.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError className="mt-2" message={errors.tipo_punto_monitoreo_id} />
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

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="barrio_id" value="Barrio (opcional)" />
                                <Select
                                    id="barrio_id"
                                    className="mt-1"
                                    value={data.barrio_id ?? ''}
                                    onChange={(e) => setData('barrio_id', e.target.value)}
                                >
                                    <option value="">Sin barrio</option>
                                    {barrios.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError className="mt-2" message={errors.barrio_id} />
                            </div>
                            <div>
                                <InputLabel htmlFor="cuadrante_id" value="Cuadrante (opcional)" />
                                <Select
                                    id="cuadrante_id"
                                    className="mt-1"
                                    value={data.cuadrante_id ?? ''}
                                    onChange={(e) => setData('cuadrante_id', e.target.value)}
                                    disabled={cuadrantes.length === 0}
                                >
                                    <option value="">{cuadrantes.length === 0 ? 'No hay cuadrantes cargados' : 'Sin cuadrante'}</option>
                                    {cuadrantes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError className="mt-2" message={errors.cuadrante_id} />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="descripcion" value="Descripción (opcional)" />
                            <TextInput
                                id="descripcion"
                                className="mt-1 block w-full"
                                placeholder="Ej.: domo, identificador de la alarma"
                                value={data.descripcion ?? ''}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.descripcion} />
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
                        className="h-[420px] lg:col-span-3 lg:h-full lg:min-h-[560px]"
                    />
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
