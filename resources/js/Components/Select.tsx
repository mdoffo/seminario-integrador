import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react';
import {
    Children,
    forwardRef,
    isValidElement,
    useLayoutEffect,
    useRef,
    useState,
    type ButtonHTMLAttributes,
    type CSSProperties,
    type ReactElement,
    type ReactNode,
    type RefObject,
} from 'react';

/**
 * Select del sistema: reemplaza al <select> nativo para que todos se vean
 * igual y la lista se despliegue SIEMPRE hacia abajo (el nativo y el anchor
 * de Headless UI la dan vuelta hacia arriba cuando no entra).
 *
 * Se usa igual que un <select>: mismos <option>/<optgroup> como hijos, y
 * onChange recibe { target: { id, name, value } } con value como string.
 * La lista va en un portal con posición fija, así no la recorta un contenedor
 * con overflow (p. ej. el panel lateral del mapa).
 */
const ALTO_MAXIMO = 240;
const ALTO_MINIMO = 120;
const SEPARACION = 4;

interface Opcion {
    grupo?: undefined;
    valor: string;
    etiqueta: ReactNode;
    key: string;
}

interface Grupo {
    grupo: string;
    key: string;
}

type Item = Opcion | Grupo;

type PropsOption = { value?: string | number; children?: ReactNode };
type PropsOptgroup = { label: string; children?: ReactNode };

/** Lo que recibe onChange: imita el evento de un <select> nativo. */
export interface SelectChangeEvent {
    target: { id?: string; name?: string; value: string };
}

interface Props extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'value'> {
    id?: string;
    name?: string;
    value?: string | number | null;
    onChange?: (e: SelectChangeEvent) => void;
    disabled?: boolean;
    className?: string;
    children?: ReactNode;
}

function leerOpciones(children: ReactNode): Item[] {
    const items: Item[] = [];

    Children.toArray(children).forEach((hijo) => {
        if (!isValidElement(hijo)) return;

        if (hijo.type === 'optgroup') {
            const grupo = hijo as ReactElement<PropsOptgroup>;
            items.push({
                grupo: grupo.props.label,
                key: `grupo-${grupo.props.label}`,
            });
            Children.toArray(grupo.props.children).forEach((opcion) => {
                if (isValidElement(opcion)) {
                    items.push(aOpcion(opcion as ReactElement<PropsOption>));
                }
            });
        } else if (hijo.type === 'option') {
            items.push(aOpcion(hijo as ReactElement<PropsOption>));
        }
    });

    return items;
}

function aOpcion(opcion: ReactElement<PropsOption>): Opcion {
    const valor = String(opcion.props.value ?? '');

    return { valor, etiqueta: opcion.props.children, key: `opcion-${valor}` };
}

export default function Select({
    id,
    name,
    value,
    onChange,
    disabled = false,
    className = '',
    children,
    ...props
}: Props) {
    const items = leerOpciones(children);
    const opciones = items.filter((i): i is Opcion => i.grupo === undefined);
    const valor = String(value ?? '');
    // Como el nativo: si el valor no coincide con ninguna opción, muestra la primera.
    const seleccionada = opciones.find((o) => o.valor === valor) ?? opciones[0];

    const botonRef = useRef<HTMLButtonElement>(null);
    const cambiar = (nuevo: string) => onChange?.({ target: { id, name, value: nuevo } });

    return (
        <Listbox value={seleccionada?.valor ?? ''} onChange={cambiar} disabled={disabled}>
            {({ open }) => (
                <div className={'relative ' + className}>
                    <Boton ref={botonRef} id={id} {...props}>
                        {seleccionada?.etiqueta}
                    </Boton>
                    {open && <Lista items={items} botonRef={botonRef} />}
                </div>
            )}
        </Listbox>
    );
}

const Boton = forwardRef<HTMLButtonElement, ButtonHTMLAttributes<HTMLButtonElement>>(function Boton(
    { children, ...props },
    ref,
) {
    return (
        <ListboxButton
            ref={ref}
            {...props}
            className="relative block w-full rounded-none border border-atalaya-border bg-atalaya-canvas py-2 pe-9 ps-3 text-start font-mono text-xs text-white focus:outline-none data-[disabled]:cursor-not-allowed data-[focus]:border-atalaya-cyan data-[open]:border-atalaya-cyan data-[disabled]:opacity-50 data-[focus]:ring-1 data-[focus]:ring-atalaya-cyan"
        >
            <span className="block truncate">{children}</span>
            <svg
                className="pointer-events-none absolute end-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-atalaya-text-muted"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fillRule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                    clipRule="evenodd"
                />
            </svg>
        </ListboxButton>
    );
});

function Lista({ items, botonRef }: { items: Item[]; botonRef: RefObject<HTMLButtonElement> }) {
    // Fija desde el primer render: nunca entra al flujo de la página.
    const [estilo, setEstilo] = useState<CSSProperties>({ position: 'fixed', visibility: 'hidden' });

    // Posición fija debajo del botón; se recalcula al hacer scroll o redimensionar.
    useLayoutEffect(() => {
        const boton = botonRef.current;
        if (!boton) return;

        const ubicar = () => {
            const r = boton.getBoundingClientRect();
            const espacio = window.innerHeight - r.bottom - SEPARACION * 2;

            setEstilo({
                position: 'fixed',
                top: r.bottom + SEPARACION,
                left: r.left,
                width: r.width,
                maxHeight: Math.max(ALTO_MINIMO, Math.min(ALTO_MAXIMO, espacio)),
            });
        };

        // Si abajo no entra ni el mínimo, se desplaza la página (no se da vuelta la lista).
        const r = boton.getBoundingClientRect();
        const falta = r.bottom + SEPARACION * 2 + ALTO_MINIMO - window.innerHeight;
        if (falta > 0) {
            window.scrollBy(0, falta);
            boton.closest('.overflow-y-auto')?.scrollBy(0, falta);
        }

        ubicar();
        window.addEventListener('scroll', ubicar, true);
        window.addEventListener('resize', ubicar);

        return () => {
            window.removeEventListener('scroll', ubicar, true);
            window.removeEventListener('resize', ubicar);
        };
    }, [botonRef]);

    return (
        // modal={false}: sin bloqueo de scroll. El bloqueo oculta la barra de
        // scroll de la página y la corre hacia el costado al abrir la lista.
        <ListboxOptions
            static
            portal
            modal={false}
            style={estilo}
            className="z-[1100] overflow-auto border border-atalaya-border bg-atalaya-surface py-1 font-mono text-xs text-atalaya-text-muted shadow-2xl focus:outline-none"
        >
            {items.map((item) =>
                item.grupo !== undefined ? (
                    <div
                        key={item.key}
                        className="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim"
                    >
                        {item.grupo}
                    </div>
                ) : (
                    <ListboxOption
                        key={item.key}
                        value={item.valor}
                        className="group flex cursor-pointer items-center justify-between gap-3 px-3 py-2 data-[focus]:bg-atalaya-elevated data-[focus]:text-atalaya-cyan data-[selected]:text-white"
                    >
                        <span className="truncate">{item.etiqueta}</span>
                        <svg
                            className="invisible h-3.5 w-3.5 shrink-0 text-atalaya-cyan group-data-[selected]:visible"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fillRule="evenodd"
                                d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </ListboxOption>
                ),
            )}
        </ListboxOptions>
    );
}
