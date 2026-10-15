# API pública — Datos Abiertos (`/api/v1`)

Solo lectura, versionada. Pensada para el portal de Gobierno Abierto de la
Municipalidad; el frontend interno NO la usa (va por Inertia).

## Acceso

Token personal de Sanctum con la habilidad `datos-abiertos:leer`. Lo emite
alguien con acceso al servidor:

```bash
php artisan api:token <usuario> "Portal Datos Abiertos" --dias=365
```

El token se muestra una sola vez. Se envía en cada pedido:

```
Authorization: Bearer <token>
Accept: application/json
```

Límite: 60 pedidos por minuto por token. Sin token → `401`; token sin la
habilidad → `403`.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/eventos` | Eventos paginados, del más reciente al más antiguo |
| GET | `/api/v1/eventos/{id}` | Un evento |
| GET | `/api/v1/estadisticas/mensual/{anio}/{mes}` | Estadística de un mes (igual que la pantalla) |
| GET | `/api/v1/estadisticas/anual/{anio}` | Estadística de un año |

Filtros de `/eventos` (todos opcionales, se combinan): `desde`, `hasta`
(`AAAA-MM-DD`), `categoria` (`prevencion`, `convivencia_urbana`,
`seguridad_publica`, `informativo`), `jurisdiccion` (`municipal`,
`provincial`), `punto` (código del PM), `por_pagina` (1–100, por defecto 50).

## Qué se publica

Por evento: id, fecha, hora, categoría, tipo, si es cuantificable, el punto
de monitoreo (código, nombre, jurisdicción, coordenadas) y si tiene fe de
errata. **No** se publican la descripción libre ni quién lo registró, porque
pueden contener datos personales.

// TODO: confirmar con la Municipalidad los campos a publicar y si se exponen
los valores corregidos por fe de errata (hoy: los originales).
