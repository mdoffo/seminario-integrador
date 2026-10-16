# Migración del frontend a TypeScript

Decisión (2026-10-16): pasar el frontend de JSX a TypeScript **de a poco**,
sin frenar las funcionalidades pendientes. **Terminada el 2026-10-16**: todo
el frontend es `.ts`/`.tsx` con `strict` y `allowJs: false`; lo nuevo se
escribe en TypeScript y `npm run typecheck` tiene que pasar.

## Por qué gradual y no de golpe

- El backend tiene tests (PHPUnit); el frontend no. Un error de una migración
  masiva solo se detecta probando cada pantalla a mano.
- Las props de cada página las arma Laravel (PHP), así que los tipos de esas
  props se escriben a mano en `resources/js/types/` y hay que mantenerlos a la
  par de los controllers.

## Reglas

- `npm run typecheck` (= `tsc --noEmit`) tiene que pasar antes de cada commit.
- Durante la migración: **un commit por módulo**, renombrando `.jsx` → `.tsx`
  con `git mv` (conserva el historial) y sin cambiar comportamiento en el
  mismo commit; los bugs que destapó el tipado van en commits `fix` aparte o
  explicados en el mensaje.
- Los imports van sin extensión (`@/Components/Select`).
- Tailwind escanea `resources/js/**/*.{js,jsx,ts,tsx}` (`tailwind.config.js`):
  si se agrega otra extensión, sumarla ahí o sus clases no se generan.
- Tipos compartidos en `resources/js/types/`:
  - `index.d.ts`: `User`, `PageProps` (lo que comparte `HandleInertiaRequests`)
    y las entidades que se repiten entre páginas (`PuntoMapa`, `Notificacion`,
    `Paginador<T>`, `TipoEventoOpcion`, `Opciones`, `ItemCatalogo`). Las
    props propias de una sola página se tipan en el mismo archivo.
  - `global.d.ts`: `route()` de Ziggy y `import.meta.env`.
- Las páginas se resuelven solo como `.tsx` (`app.tsx` y
  `resources/views/app.blade.php`).

## Fases

| # | Qué | Estado |
|---|-----|--------|
| 1 | Infraestructura: `typescript`, `@types/react(-dom)`, `tsconfig.json` (`allowJs`, `strict`), script `typecheck`, tipos compartidos, páginas `.tsx`/`.jsx` | ✅ |
| 1 | Piloto: componentes base `InputError`, `InputLabel`, `PrimaryButton`, `SecondaryButton`, `TextInput` | ✅ |
| 2 | Resto de componentes compartidos: `Select`, `Tabla`, `Modal`, `Dropdown`, `FlashMessages`, `NavLink`… (los que usan Leaflet pasan a la fase 5) | ✅ |
| 3 | Layouts (`AuthenticatedLayout`, `GuestLayout`) con `PageProps` | ✅ |
| 4 | Páginas de más uso, con sus props tipadas: Eventos (Index, Show, Create), Errata | ✅ |
| 5 | Estadísticas, Análisis, Mapa, Panel y los componentes de Leaflet (`TacticalMap`, `MapaPuntosMonitoreo`, `SelectorCoordenadas`), `lib/leafletGlobal` y `catalogos`; `@types/leaflet` y `@types/leaflet.heat` (Recharts trae los suyos) | ✅ |
| 6 | Administración (Usuarios, Roles, Puntos, Notificaciones, Parámetros, Auditoría), Turnos, Reportes, Auth/Profile de Breeze | ✅ |
| 7 | Cierre: `app.jsx` → `app.tsx`, `bootstrap.js` → `.ts`, `allowJs: false`; páginas solo `.tsx` en `app.tsx`, `app.blade.php` y `vite.config.js` | ✅ |
