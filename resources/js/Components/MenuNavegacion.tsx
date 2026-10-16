import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';

/**
 * Navegación principal armada desde una lista de grupos de módulos.
 * Con pocos módulos se muestran sueltos; con muchos (p. ej. un Administrador
 * de sistema con varios roles) se agrupan en menús para que la barra no se
 * amontone. "Panel" siempre queda como acceso directo.
 *
 * grupos: [{ titulo, items: [{ etiqueta, ruta, activo: bool }] }]
 */

/** Más de este número de módulos (sin contar Panel) => se agrupan en menús. */
const MAXIMO_SUELTOS = 4;

export interface ItemNavegacion {
    etiqueta: string;
    /** Nombre de ruta de Ziggy. */
    ruta: string;
    activo: boolean;
}

export interface GrupoNavegacion {
    titulo: string;
    items: ItemNavegacion[];
}

interface Props {
    panel: ItemNavegacion;
    grupos: GrupoNavegacion[];
}

function MenuGrupo({ grupo }: { grupo: GrupoNavegacion }) {
    const activo = grupo.items.some((i) => i.activo);

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    aria-haspopup="menu"
                    className={
                        'inline-flex h-16 items-center gap-1 border-b-2 px-3 pt-1 text-xs font-semibold uppercase tracking-wider transition duration-150 ease-in-out focus:outline-none ' +
                        (activo
                            ? 'border-atalaya-cyan bg-atalaya-elevated/40 text-white'
                            : 'border-transparent text-atalaya-text-muted hover:border-atalaya-border hover:bg-atalaya-surface/40 hover:text-white')
                    }
                >
                    {grupo.titulo}
                    <svg className="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path
                            fillRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clipRule="evenodd"
                        />
                    </svg>
                </button>
            </Dropdown.Trigger>
            <Dropdown.Content align="left" width="56">
                <div className="border-b border-atalaya-border px-4 py-1.5 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                    {grupo.titulo}
                </div>
                {grupo.items.map((item) => (
                    <Dropdown.Link
                        key={item.ruta}
                        href={route(item.ruta)}
                        className={item.activo ? 'border-l-2 border-atalaya-cyan bg-atalaya-elevated !text-white' : 'border-l-2 border-transparent'}
                    >
                        {item.etiqueta}
                    </Dropdown.Link>
                ))}
            </Dropdown.Content>
        </Dropdown>
    );
}

export function NavegacionEscritorio({ panel, grupos }: Props) {
    const cantidad = grupos.reduce((n, g) => n + g.items.length, 0);
    const agrupar = cantidad > MAXIMO_SUELTOS;

    return (
        <div className="hidden h-16 items-center gap-1 md:flex">
            <NavLink href={route(panel.ruta)} active={panel.activo} className="h-16">
                {panel.etiqueta}
            </NavLink>
            {grupos.map((grupo) =>
                agrupar && grupo.items.length > 1 ? (
                    <MenuGrupo key={grupo.titulo} grupo={grupo} />
                ) : (
                    grupo.items.map((item) => (
                        <NavLink key={item.ruta} href={route(item.ruta)} active={item.activo} className="h-16">
                            {item.etiqueta}
                        </NavLink>
                    ))
                ),
            )}
        </div>
    );
}

export function NavegacionMovil({ panel, grupos }: Props) {
    return (
        <div className="py-2">
            <ResponsiveNavLink href={route(panel.ruta)} active={panel.activo}>
                {panel.etiqueta}
            </ResponsiveNavLink>
            {grupos.map((grupo) => (
                <div key={grupo.titulo} className="mt-2">
                    <p className="px-3 pb-1 pt-2 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                        {grupo.titulo}
                    </p>
                    {grupo.items.map((item) => (
                        <ResponsiveNavLink key={item.ruta} href={route(item.ruta)} active={item.activo}>
                            {item.etiqueta}
                        </ResponsiveNavLink>
                    ))}
                </div>
            ))}
        </div>
    );
}
