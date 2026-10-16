# Migración del frontend a TypeScript (gradual)

Decisión (2026-10-16): pasar el frontend de JSX a TypeScript **de a poco**,
sin frenar las funcionalidades pendientes. JS y TS conviven (`allowJs`): cada
archivo se migra cuando conviene y lo nuevo se escribe directamente en `.tsx`.

## Por qué gradual y no de golpe

- El backend tiene tests (PHPUnit); el frontend no. Un error de una migración
  masiva solo se detecta probando cada pantalla a mano.
- Las props de cada página las arma Laravel (PHP), así que los tipos de esas
  props se escriben a mano en `resources/js/types/` y hay que mantenerlos a la
  par de los controllers.

## Reglas

- **Un commit por módulo** migrado, verificando la pantalla en el navegador.
- `npm run typecheck` (= `tsc --noEmit`) tiene que pasar antes de cada commit.
- Al migrar un archivo: renombrar `.jsx` → `.tsx` con `git mv` (conserva el
  historial), tipar sus props y no cambiar comportamiento en el mismo commit.
- Los imports siguen sin extensión (`@/Components/Select`): Vite resuelve
  `.tsx` y `.jsx` por igual.
- Tipos compartidos en `resources/js/types/`:
  - `index.d.ts`: `User`, `PageProps` (lo que comparte `HandleInertiaRequests`)
    y las entidades que se repiten entre páginas (`PuntoMapa`, `Notificacion`,
    `Paginador<T>`, `TipoEventoOpcion`, `Opciones`, `ItemCatalogo`). Las
    props propias de una sola página se tipan en el mismo archivo.
  - `global.d.ts`: `route()` de Ziggy y `import.meta.env`.
- Las páginas se resuelven como `.tsx` o `.jsx` (`app.jsx` y
  `resources/views/app.blade.php` ya contemplan los dos).

## Fases

| # | Qué | Estado |
|---|-----|--------|
| 1 | Infraestructura: `typescript`, `@types/react(-dom)`, `tsconfig.json` (`allowJs`, `strict`), script `typecheck`, tipos compartidos, páginas `.tsx`/`.jsx` | ✅ |
| 1 | Piloto: componentes base `InputError`, `InputLabel`, `PrimaryButton`, `SecondaryButton`, `TextInput` | ✅ |
| 2 | Resto de componentes compartidos: `Select`, `Tabla`, `Modal`, `Dropdown`, `FlashMessages`, `NavLink`… (los que usan Leaflet pasan a la fase 5) | ✅ |
| 3 | Layouts (`AuthenticatedLayout`, `GuestLayout`) con `PageProps` | ✅ |
| 4 | Páginas de más uso, con sus props tipadas: Eventos (Index, Show, Create), Errata | ✅ |
| 5 | Estadísticas, Análisis, Mapa, Panel y los componentes de Leaflet (`TacticalMap`, `MapaPuntosMonitoreo`, `SelectorCoordenadas`); revisar los `@types` de Leaflet/Recharts | ⬜ |
| 6 | Administración (Usuarios, Roles, Puntos, Notificaciones, Parámetros, Auditoría), Turnos, Reportes, Auth/Profile de Breeze | ⬜ |
| 7 | Cierre: `app.jsx` → `app.tsx`, `bootstrap.js` → `.ts`, `allowJs: false` | ⬜ |
