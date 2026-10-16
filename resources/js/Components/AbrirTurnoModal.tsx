import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PersonalPresenteInput from '@/Components/PersonalPresenteInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    show: boolean;
    onPosponer: () => void;
}

/**
 * US-025: al iniciar sesión, si el Supervisor no tiene turno abierto, se le
 * pregunta si desea abrir uno. `onPosponer` cierra el modal por la sesión.
 */
export default function AbrirTurnoModal({ show, onPosponer }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        personal_presente: string[];
    }>({
        personal_presente: [],
    });

    // Errores con claves que no son campos del form (por ítem y de turno).
    const errores: Record<string, string | undefined> = errors;

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('turnos.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Modal show={show} onClose={onPosponer} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Abrir turno de guardia
                </h2>
                <p className="mt-2 font-mono text-xs text-atalaya-text-muted">
                    No tenés un turno de guardia abierto. Abrí uno para poder
                    registrar eventos. Se registrará tu usuario y la fecha/hora
                    de inicio automáticamente.
                </p>

                <div className="mt-4">
                    <InputLabel htmlFor="personal_presente">
                        Personal presente
                        <span className="text-atalaya-crimson"> *</span>
                    </InputLabel>
                    <PersonalPresenteInput
                        value={data.personal_presente}
                        onChange={(nombres) =>
                            setData('personal_presente', nombres)
                        }
                    />
                    <p className="mt-1 font-mono text-[10px] text-atalaya-text-dim">
                        Escribí un nombre y presioná Enter para agregarlo.
                    </p>
                    <InputError
                        className="mt-2"
                        message={
                            errores.personal_presente ||
                            errores['personal_presente.0'] ||
                            errores.turno
                        }
                    />
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onPosponer}>
                        Ahora no
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        Abrir turno
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
