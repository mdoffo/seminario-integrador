# Product Backlog — ATALAYA

Copia del backlog del documento de dominio (sección 9). El texto de las
historias se respeta tal cual; las diferencias con lo decidido por el equipo
están en **Desvíos respecto del doc** y el avance real en **Estado**.

## Desvíos respecto del doc (decididos por el equipo)

- **Mapas:** OpenStreetMap + Leaflet, **no** Google Maps (US-016, US-017,
  US-023, US-024 y la Épica 7 deben leerse así). US-023 pierde sentido tal cual
  está escrita (no hay API key).
- **Roles:** el doc dice "Usuario Administrador" en Épicas 4–6; en el sistema
  ese actor es **Administrativo** (solo lectura) o **Administrador de sistema**.
- **Exportación:** el doc pide `.xls`; el stack usa `.xlsx` UTF-8.
  // TODO: confirmar con el informante.
- **Informativo:** el doc lo trata como marca aparte de las 3 categorías (RN2);
  hoy está modelado como 4ª categoría de `TipoEvento`. Decisión A/B pendiente.
- **Fuera de alcance (regla 6 de CLAUDE.md):** "Fuerza de respuesta" de US-028
  se interpreta solo como destinatario de la notificación, no como derivación.

## Estado (al 2026-10-15)

Leyenda: ✅ hecho · 🟡 parcial · ⬜ no empezado (solo stubs o nada)

| US | Historia | Estado | Notas |
|----|----------|--------|-------|
| 001 | Autenticación | ✅ | login por username, bloqueo 3 intentos/15 min, auditoría de acceso, sesión 30 min. Falta desbloqueo manual (ver `pendientes.md`) |
| 002 | Roles y permisos | ✅ | multi-rol, matriz de permisos, auditado |
| 003 | Evento con ubicación | 🟡 | registro OK; falta CSV oficial de 394 PMs (seeder actual es de prueba) |
| 004 | Evento informativo | 🟡 | funciona vía tipo sin ubicación; pendiente decisión A/B e indicador visual |
| 005 | Validaciones | ✅ | |
| 006 | Notificación WhatsApp | 🟡 | circuito completo con canal `log` (grupos, mensaje, cola, registro del resultado). Falta: cuenta Meta, plantilla aprobada y confirmar envío a grupos |
| 007 | Reintentos WhatsApp | ✅ | 1 + 3 reintentos c/30 s, fallida → auditoría + alerta al Supervisor, botón Reenviar en Eventos |
| 008 | Búsqueda con filtros | 🟡 | tipo, categoría, PM, rango de fechas. Faltan: multi-selección, rango horario, zona, texto libre, "Limpiar filtros" |
| 009 | Tabla de resultados | 🟡 | paginación 20, click en fila → detalle. Falta ordenar por columna |
| 010 | Detalle de evento | ✅ | vista dedicada `eventos/{id}` con PM (municipal/provincial), timestamp, autor, turno, notificaciones e indicador sin ubicación |
| 011 | Filtro informativos | ⬜ | depende de decisión A/B |
| 012 | PDF por turno | ⬜ | `PdfExportador` stub; turnos ya existen (prerrequisito OK). PDF/A pendiente |
| 013 | Export Excel | ⬜ | `XlsxExportador` stub |
| 014 | Estadísticas mensuales/anuales | ⬜ | `EstadisticaService` vacío |
| 015 | Dashboard KPIs | 🟡 | consola táctica con datos reales; revisar variación % y gráfico 6 meses |
| 016 | Mapa de eventos | 🟡 | Leaflet/OSM con PMs; revisar colores por tipo y filtros |
| 017 | Heatmap | ⬜ | |
| 018 | Tendencias temporales | ⬜ | |
| 019 | Ranking zonas | ⬜ | |
| 020 | Franja horaria | ⬜ | |
| 021 | Drill-down | ⬜ | |
| 022 | Refresco automático | ⬜ | |
| 023 | Config Google Maps | — | no aplica (OSM) |
| 024 | Carga de coordenadas PMs | ⬜ | falta CRUD + import CSV |
| 025 | Apertura/cierre de turno | ✅ | |
| 026 | Log de auditoría | 🟡 | login/logout, eventos, turnos, roles. Faltan: reportes, exportaciones, erratas |
| 027 | Consulta de log | 🟡 | pantalla "Logs" con filtros; falta export Excel y auditar el acceso al log |
| 028 | Config notificaciones | ⬜ | tablas y seeder listos (Guardia Local / Policía); falta la pantalla de administración |
| 029 | Parámetros del sistema | ⬜ | hoy por `.env`/config |

Fuera del backlog pero en CLAUDE.md: **fe de errata** ⬜ (stubs) y **API
pública `/api/v1`** ⬜ (rutas comentadas).

---

## 9.1 Épica 1: Gestión de usuarios y seguridad

### US-001: Autenticación de Usuarios
Como Usuario del sistema (Administrador del sistema, Supervisor o Administrativo),
quiero iniciar sesión con mis credenciales personales (usuario y contraseña),
para acceder al sistema de forma segura y garantizar la trazabilidad de mis acciones.

Criterios de aceptación:
- El sistema debe validar usuario y contraseña contra la base de datos
- Las contraseñas deben almacenarse encriptadas
- Tras 3 intentos fallidos, la cuenta debe bloquearse temporalmente
- El sistema debe registrar en log de auditoría: IP local, usuario, fecha y hora de inicio de sesión
- La sesión debe expirar después de 30 minutos de inactividad

Prioridad: Alta · Actor: Administrador del Sistema / Supervisor / Administrativo

### US-002: Gestión de Roles y Permisos
Como Administrador del Sistema,
quiero asignar roles (Administrador del Sistema, Supervisor o Administrativo) a los usuarios,
para controlar el acceso a las funcionalidades según las responsabilidades de cada usuario.

Criterios de aceptación:
- Un usuario puede tener uno o más roles (Administrador del Sistema, Supervisor y Administrador)
- Los permisos deben aplicarse según la matriz definida (ver sección 3.4 del documento)
- El cambio de rol debe registrarse en log de auditoría
- Solo usuarios con rol de Administrador del Sistema pueden asignar roles

Prioridad: Alta · Actor: Administrador del Sistema

## 9.2 Épica 2: Registro de eventos

### US-003: Registro de Evento con Ubicación
Como Usuario Supervisor,
quiero registrar un evento detectado por videovigilancia asociándolo a un Punto de Monitoreo (PM),
para documentar el incidente con su ubicación exacta y generar estadísticas precisas.

Criterios de aceptación:
- El sistema debe permitir seleccionar el tipo de evento (Prevención, Convivencia Urbana, Seguridad Pública)
- Debe consignar fecha y horario del hecho (obligatorios)
- Debe permitir buscar y seleccionar un PM de la lista de 394 PMs (235 municipales + 159 provinciales)
- Debe registrar timestamp exacto del video (hora, minuto, segundo) — obligatorio cuando hay PM
- Debe incluir campo de descripción de texto libre
- El sistema debe validar que todos los campos obligatorios estén completos
- El sistema debe validar que el PM seleccionado exista en la base de datos
- Al guardar, el sistema debe registrar automáticamente el usuario Supervisor que crea el evento

Prioridad: Alta · Actor: Supervisor

### US-004: Registro de Evento Informativo Sin Ubicación
Como Usuario Supervisor,
quiero registrar un evento de carácter informativo sin asociarlo a una ubicación geográfica,
para documentar novedades que no requieren geolocalización pero deben quedar registradas.

Criterios de aceptación:
- El sistema debe permitir marcar el evento como "informativo" o "sin ubicación"
- No debe ser obligatorio seleccionar un PM
- No debe ser obligatorio registrar timestamp de video
- El evento debe quedar marcado con un indicador visual especial
- El evento debe incluirse en totales generales pero excluirse de estadísticas de ubicación (RN4)

Prioridad: Media · Actor: Supervisor

### US-005: Validación de Campos en Registro de Eventos
Como Usuario Supervisor,
quiero que el sistema valide los datos ingresados antes de guardar un evento,
para asegurar la calidad e integridad de la información registrada.

Criterios de aceptación:
- El timestamp del video debe ser coherente con la fecha y horario del evento
- La fecha no puede ser futura
- El horario debe estar en formato HH:MM:SS válido
- La descripción no puede estar vacía
- El sistema debe mostrar mensajes de error claros indicando qué campo es inválido
- El sistema debe prevenir el guardado hasta corregir todos los errores

Prioridad: Alta · Actor: Supervisor

## 9.3 Épica 3: Notificaciones automáticas

### US-006: Notificación Automática a WhatsApp
Como Usuario Supervisor,
quiero que el sistema notifique automáticamente al grupo de WhatsApp correspondiente al registrar un evento,
para mantener informados en tiempo real a los interesados sin intervención manual.

Criterios de aceptación:
- El sistema debe identificar el grupo de WhatsApp según el tipo de evento:
  - Prevención → Grupo de WhatsApp de Guardia Local
  - Convivencia Urbana → Grupo de WhatsApp de Guardia Local
  - Seguridad Pública → Grupo de WhatsApp de Policía
- El mensaje debe incluir: tipo de evento, fecha, horario, descripción resumida, ubicación (PM si aplica)
- La notificación debe enviarse automáticamente al confirmar el registro del evento
- El sistema debe registrar en log: fecha, hora y resultado del envío (éxito/fallo)
- La notificación debe ejecutarse sin intervención manual del usuario

Prioridad: Alta · Actor: Sistema (disparado por Supervisor)

### US-007: Manejo de Fallos en Notificaciones WhatsApp
Como Usuario Supervisor,
quiero que el sistema reintente automáticamente el envío de notificaciones si falla, y me permita reenviar manualmente si es necesario,
para garantizar que la información llegue a los grupos de WhatsApp incluso ante fallos técnicos.

Criterios de aceptación:
- Si el envío falla, el sistema debe reintentar automáticamente hasta 3 veces con intervalos de 30 segundos
- Si persiste el fallo después de 3 reintentos, el sistema debe:
  - Registrar el incidente en el log de auditoría
  - Mostrar una alerta visual al Usuario Supervisor en la interfaz
- El sistema debe permitir al Usuario Supervisor reenviar manualmente la notificación desde la interfaz de consulta de eventos
- El reenvío manual debe quedar registrado en el log de auditoría

Prioridad: Media · Actor: Supervisor / Sistema

## 9.4 Épica 4: Consulta y gestión de eventos

### US-008: Búsqueda de Eventos con Filtros
Como Usuario Administrador,
quiero buscar eventos aplicando múltiples filtros (tipo, fecha, horario, ubicación, texto),
para encontrar rápidamente la información específica que necesito consultar o analizar.

Criterios de aceptación:
- El sistema debe permitir filtrar por:
  - Tipo de evento (Prevención, Convivencia Urbana, Seguridad Pública) — múltiple selección
  - Rango de fechas (fecha inicial y final)
  - Rango de horarios (hora inicial y final)
  - Ubicación: PM específico o zona/cuadrante
  - Texto libre en descripción (búsqueda por palabras clave)
- Los filtros deben poder combinarse (búsqueda AND)
- El sistema debe permitir limpiar todos los filtros con un botón "Limpiar filtros"
- Los resultados deben actualizarse automáticamente al aplicar filtros

Prioridad: Alta · Actor: Administrador

### US-009: Visualización de Resultados en Tabla
Como Usuario Administrador,
quiero ver los resultados de búsqueda en una tabla ordenable,
para analizar rápidamente la información y encontrar patrones o eventos específicos.

Criterios de aceptación:
- La tabla debe mostrar las columnas: ID del evento, tipo, fecha, horario, PM asociado (si aplica), descripción, usuario que registró
- La tabla debe permitir ordenamiento ascendente/descendente haciendo clic en los encabezados de columna
- Debe mostrarse el número total de resultados encontrados
- Si hay más de 50 resultados, debe implementarse paginación (20 resultados por página)
- Al hacer clic en una fila, debe mostrarse el detalle completo del evento

Prioridad: Alta · Actor: Administrador

### US-010: Visualización de Detalle de Evento
Como Usuario Administrador,
quiero ver el detalle completo de un evento registrado,
para conocer toda la información asociada al mismo incluyendo el timestamp exacto del video.

Criterios de aceptación:
- El detalle debe mostrar toda la información del evento:
  - ID del evento
  - Tipo de evento
  - Fecha y horario del hecho
  - Descripción completa
  - PM asociado (si aplica) con indicación de si es municipal o provincial
  - Timestamp exacto del video (hora, minuto, segundo)
  - Usuario Supervisor que registró el evento
  - Fecha y hora de registro en el sistema
- Los eventos informativos/sin ubicación deben mostrar un indicador visual claro
- El detalle debe abrirse en un modal o vista dedicada

Prioridad: Alta · Actor: Administrador

### US-011: Filtrado de Eventos Informativos
Como Usuario Administrador,
quiero filtrar y visualizar de forma independiente los eventos informativos o sin ubicación,
para poder excluirlos de análisis estadísticos de localización cuando sea necesario.

Criterios de aceptación:
- El sistema debe permitir filtrar específicamente eventos marcados como "informativos" o "sin ubicación"
- Debe haber un checkbox o toggle "Mostrar solo eventos con ubicación" / "Incluir eventos sin ubicación"
- Los eventos sin ubicación deben identificarse visualmente en la tabla (icono o color diferente)
- Al exportar datos, debe incluirse una columna que indique si el evento es "sin ubicación" o "informativo"

Prioridad: Media · Actor: Administrador

## 9.5 Épica 5: Reportes y exportación

### US-012: Generación de Reporte PDF por Turno
Como Usuario Administrador,
quiero generar un reporte en formato PDF de un turno de trabajo específico,
para tener un documento formal de los eventos ocurridos durante ese turno.

Criterios de aceptación:
- El sistema debe permitir seleccionar el turno específico basado en los horarios de cierre de turno registrados
- El PDF debe incluir en el encabezado:
  - Logo de la Municipalidad de Villa María
  - Título: "Reporte de Turno de Trabajo - Centro de Monitoreo Urbano"
  - Fecha del turno
  - Horario de inicio y fin del turno
  - Nombre del Supervisor que cerró el turno
- El cuerpo del reporte debe incluir:
  - Tabla con todos los eventos del turno ordenados cronológicamente
  - Para cada evento: hora, tipo, descripción, PM asociado (si aplica), timestamp del video
  - Resumen estadístico del turno: total de eventos por tipo
- El pie de página debe incluir: número de página, fecha/hora de generación, nombre del Usuario Administrador que generó el reporte
- El PDF debe ser descargable e imprimible
- El reporte debe incluir EXCLUSIVAMENTE eventos del turno seleccionado (RN8)

Prioridad: Alta · Actor: Administrador

### US-013: Exportación de Datos a Excel
Como Usuario Administrador,
quiero exportar los resultados de una búsqueda filtrada a formato Excel (.xls),
para facilitar la incorporación de datos a la plataforma de Datos Abiertos de la Municipalidad.

Criterios de aceptación:
- El archivo .xls debe contener todas las columnas visibles en la tabla de resultados
- Los eventos informativos o sin ubicación deben incluir una columna adicional que indique "Evento sin ubicación" o "Evento informativo"
- El nombre del archivo debe seguir el formato: `Eventos_CMU_[fecha_desde]_[fecha_hasta].xls`
- El sistema debe mostrar un mensaje de confirmación al completar la exportación exitosamente
- El archivo debe incluir todos los registros que coinciden con los filtros aplicados (sin límite)
- Las fechas deben estar en formato DD/MM/AAAA
- El archivo debe cumplir con los estándares de la plataforma de Datos Abiertos

Prioridad: Alta · Actor: Administrador

## 9.6 Épica 6: Estadísticas y dashboards

### US-014: Generación de Estadísticas Mensuales y Anuales
Como Usuario Administrador,
quiero generar estadísticas mensuales y anuales de eventos,
para analizar la distribución de eventos por tipo, ubicación y período de tiempo.

Criterios de aceptación:
- El sistema debe permitir seleccionar el período: mes específico o año completo
- Las estadísticas deben incluir:
  - Cantidad total de eventos en el período seleccionado
  - Distribución por tipo de evento (Prevención, Convivencia Urbana, Seguridad Pública)
  - Distribución por ubicación (PM o zona/cuadrante)
  - Comparativa temporal: mes a mes (para anuales) o semana a semana (para mensuales)
- Los eventos informativos/sin ubicación NO deben incluirse en la distribución por ubicación, pero SÍ en el total general y distribución por tipo (RN4, RN10)
- El sistema debe diferenciar entre PMs municipales y provinciales en las estadísticas (RN11)
- Los resultados deben poder visualizarse en formato de tablas y gráficos

Prioridad: Alta · Actor: Administrador

### US-015: Dashboard — Panel de Resumen General
Como Usuario Administrador,
quiero visualizar un panel de resumen general con indicadores clave (KPIs),
para tener una visión rápida del estado actual de los eventos.

Criterios de aceptación:
- El panel debe mostrar tarjetas con:
  - Total de eventos en el mes actual
  - Total de eventos en el año actual
  - Porcentaje de eventos por tipo (Prevención, Convivencia Urbana, Seguridad Pública)
- Debe incluir un gráfico de barras comparativo: eventos por tipo en los últimos 6 meses
- Los datos deben actualizarse en tiempo real
- Las tarjetas deben mostrar variación porcentual respecto al período anterior (ej: vs mes anterior)
- Debe permitir seleccionar el período de visualización

Prioridad: Alta · Actor: Administrador

### US-016: Dashboard — Mapa de Eventos (doc: Google Maps → OSM/Leaflet)
Como Usuario Administrador,
quiero visualizar los eventos registrados como marcadores en un mapa interactivo,
para identificar geográficamente la distribución de eventos en la ciudad de Villa María.

Criterios de aceptación:
- El mapa debe mostrar la ciudad de Villa María (doc: Google Maps API; equipo: OpenStreetMap + Leaflet)
- Cada evento debe mostrarse como un marcador geolocalizado en la posición del PM asociado
- Los marcadores deben diferenciarse visualmente por tipo de evento (color o ícono diferente):
  Prevención: color X · Convivencia Urbana: color Y · Seguridad Pública: color Z
- Los marcadores deben permitir diferenciar visualmente entre PMs municipales y provinciales
- Al hacer clic en un marcador, debe mostrarse un popup con resumen del evento: tipo, fecha, horario, descripción, PM asociado
- El mapa debe permitir zoom y desplazamiento (pan)
- El mapa debe respetar los filtros aplicados (tipo de evento, rango de fechas, etc.)
- La visualización debe actualizarse en tiempo real al aplicar filtros

Prioridad: Alta · Actor: Administrador

### US-017: Dashboard — Mapa de Calor (Heatmap)
Como Usuario Administrador,
quiero visualizar un mapa de calor que muestre la densidad de eventos por zona,
para identificar rápidamente las zonas de mayor incidencia en la ciudad.

Criterios de aceptación:
- El heatmap debe superponerse sobre el mapa de Villa María (doc: Google Maps; equipo: OSM)
- La intensidad del color debe reflejar la densidad de eventos (más eventos = color más intenso)
- El mapa de calor debe ser filtrable por:
  - Tipo de evento
  - Período de tiempo (rango de fechas personalizable)
- Al hacer clic en una zona del heatmap, el sistema debe permitir "drill-down" mostrando el listado de eventos en esa ubicación
- El heatmap debe actualizarse dinámicamente al cambiar los filtros
- Debe poder activarse/desactivarse la visualización del heatmap independientemente del mapa de marcadores
- Los eventos informativos/sin ubicación NO deben incluirse en el heatmap

Prioridad: Alta · Actor: Administrador

### US-018: Dashboard — Gráfico de Tendencias Temporales
Como Usuario Administrador,
quiero visualizar un gráfico de líneas que muestre la evolución de eventos a lo largo del tiempo,
para identificar tendencias y patrones temporales.

Criterios de aceptación:
- El gráfico debe mostrar la evolución de eventos en el tiempo (eje X: tiempo, eje Y: cantidad)
- Debe permitir seleccionar la granularidad temporal: día, semana, mes
- Debe permitir desglosar por tipo de evento (líneas separadas por tipo o apilado)
- Debe permitir seleccionar el período de análisis: último mes, últimos 3 meses, último año, rango personalizado
- Al hacer clic en un punto del gráfico, debe mostrar el detalle de eventos de ese período (drill-down)
- El gráfico debe actualizarse en tiempo real
- Debe incluir leyenda identificando cada tipo de evento

Prioridad: Media · Actor: Administrador

### US-019: Dashboard — Ranking de Zonas Críticas
Como Usuario Administrador,
quiero visualizar un ranking de las zonas o PMs con mayor cantidad de eventos,
para identificar las áreas críticas que requieren mayor atención.

Criterios de aceptación:
- El sistema debe mostrar el top 10 de PMs o zonas con mayor cantidad de eventos
- Debe presentarse como tabla o gráfico de barras horizontales
- Cada entrada debe mostrar:
  - Nombre/identificación del PM o zona
  - Cantidad total de eventos en el período seleccionado
  - Porcentaje que representa del total de eventos
  - Indicador visual del porcentaje (barra de progreso o similar)
- Debe permitir filtrar por período de tiempo
- Debe permitir hacer clic en una zona/PM para ver el detalle de eventos (drill-down)
- Debe permitir alternar entre vista por PM individual o por zona/cuadrante

Prioridad: Media · Actor: Administrador

### US-020: Dashboard — Análisis por Franja Horaria
Como Usuario Administrador,
quiero visualizar la distribución de eventos por hora del día,
para identificar las franjas horarias de mayor incidencia.

Criterios de aceptación:
- El gráfico debe mostrar la distribución de eventos por hora del día (0–23 hs)
- Debe presentarse como gráfico de barras (24 barras, una por hora)
- Debe permitir filtrar por tipo de evento
- Debe permitir filtrar por período de tiempo
- Debe mostrar valores absolutos (cantidad de eventos) y porcentajes
- Al hacer clic en una barra (hora), debe mostrar el detalle de eventos de esa franja horaria (drill-down)
- Debe destacar visualmente las 3 horas de mayor incidencia

Prioridad: Media · Actor: Administrador

### US-021: Dashboard — Interactividad y Drill-Down
Como Usuario Administrador,
quiero que todos los dashboards sean interactivos y permitan explorar detalles desde los elementos visuales,
para profundizar en el análisis sin tener que navegar a otras secciones del sistema.

Criterios de aceptación:
- Al hacer clic en una barra de gráfico, debe mostrar el detalle de eventos que componen ese dato
- Al hacer clic en un punto del gráfico de líneas, debe mostrar los eventos de ese período
- Al hacer clic en una zona del mapa de calor, debe mostrar el listado de eventos en esa ubicación
- Al hacer clic en un marcador del mapa, debe mostrar el popup con detalles del evento
- Al hacer clic en una tarjeta de KPI, debe mostrar el desglose detallado
- El drill-down debe mantener los filtros aplicados
- Debe haber un botón o breadcrumb para volver a la vista anterior

Prioridad: Media · Actor: Administrador

### US-022: Actualización en Tiempo Real de Dashboards
Como Usuario Administrador,
quiero que los dashboards se actualicen automáticamente cuando se registren nuevos eventos,
para tener siempre información actualizada sin necesidad de recargar la página manualmente.

Criterios de aceptación:
- Los dashboards deben actualizarse automáticamente cada 60 segundos
- Debe haber un indicador visual que muestre cuándo fue la última actualización
- Debe haber un botón "Actualizar ahora" para forzar la actualización manual
- Durante la actualización, debe mostrarse un indicador de carga
- Si hay error en la actualización, debe mostrarse un mensaje de error
- La actualización no debe interrumpir la interacción del usuario con los gráficos

Prioridad: Media · Actor: Administrador

## 9.7 Épica 7: Integración con mapas (doc: Google Maps)

### US-023: Configuración de API de Google Maps — no aplica con OSM
Como Administrador del Sistema,
quiero configurar la integración con Google Maps API,
para habilitar la visualización geográfica de eventos en el sistema.

Criterios de aceptación (doc original):
- El sistema debe permitir configurar la API Key de Google Maps
- Debe validar que la API Key sea válida y tenga los permisos necesarios
- La API Key debe estar restringida por dominio (HTTP referrer)
- El sistema debe utilizar Maps JavaScript API para la visualización interactiva
- El sistema debe manejar errores de API (cuota excedida, API inválida, etc.) mostrando mensajes claros
- Debe registrarse en log el consumo de la API (número de solicitudes)

Prioridad: Alta · Actor: Administrador del Sistema

### US-024: Carga de Coordenadas de PMs
Como Administrador del Sistema,
quiero cargar y mantener actualizadas las coordenadas geográficas de los 394 PMs (235 municipales + 159 provinciales),
para que el sistema pueda mostrar correctamente la ubicación de los eventos en el mapa.

Criterios de aceptación:
- El sistema debe permitir cargar masivamente las coordenadas de PMs desde un archivo (CSV, Excel)
- Cada PM debe tener: ID único, nombre/identificación, latitud, longitud, tipo (municipal/provincial), zona/cuadrante
- El sistema debe validar que las coordenadas sean válidas (dentro del rango de Villa María)
- Debe permitir actualizar coordenadas de PMs individuales
- Debe mostrar en el mapa todos los PMs cargados (incluso sin eventos) como referencia
- Los PMs deben diferenciarse visualmente según su origen (municipal vs provincial)

Prioridad: Alta · Actor: Administrador del Sistema

## 9.8 Épica 8: Gestión de turnos

### US-025: Apertura y Cierre de Turno
Como Usuario Supervisor,
quiero registrar formalmente la apertura y cierre de mi turno de trabajo,
para delimitar el período de eventos que me corresponde y permitir la generación de reportes por turno.

Criterios de aceptación:
- Al iniciar sesión, el sistema debe preguntar si desea abrir un nuevo turno
- Al abrir turno, debe registrarse: usuario Supervisor, fecha/hora de inicio, personal presente
- Solo puede haber un turno activo por Supervisor a la vez
- Al cerrar turno, debe registrarse: fecha/hora de fin, novedades pendientes
- El sistema debe validar que no se puedan registrar eventos de un turno cerrado
- El cierre de turno debe quedar registrado en log de auditoría
- El sistema debe permitir consultar el historial de turnos

Prioridad: Alta · Actor: Supervisor

## 9.9 Épica 9: Auditoría y trazabilidad

### US-026: Log de Auditoría del Sistema
Como Administrador del Sistema,
quiero que el sistema registre todas las acciones críticas en un log de auditoría,
para garantizar la trazabilidad, integridad de la información y responsabilidad sobre los registros.

Criterios de aceptación:
- El sistema debe registrar en log de auditoría:
  - Inicios y cierres de sesión
  - Altas de eventos (quién, cuándo, qué evento)
  - Modificaciones de eventos (qué cambió, valor anterior, valor nuevo)
  - Generación de reportes PDF
  - Exportaciones de datos
  - Fallos de notificación WhatsApp
  - Asignación de roles de usuario
  - Apertura y cierre de turnos
- Cada registro de auditoría debe incluir:
  - Usuario que ejecutó la acción
  - Fecha y hora exacta
  - Tipo de acción
  - Detalle de la acción (ID de evento afectado, etc.)
  - Dirección IP (si aplica)
- El log debe ser inmutable (no se debe permitir modificar o eliminar registros de auditoría)
- Debe permitir consultar el log de auditoría con filtros (usuario, fecha, tipo de acción)
- El log debe conservarse por al menos 2 años

Prioridad: Alta · Actor: Sistema (automático)

### US-027: Consulta de Log de Auditoría
Como Administrador del Sistema,
quiero consultar el log de auditoría aplicando filtros,
para investigar actividades del sistema, detectar anomalías o cumplir con requerimientos de compliance.

Criterios de aceptación:
- El sistema debe permitir filtrar el log por:
  - Usuario
  - Rango de fechas
  - Tipo de acción
  - ID de objeto afectado (ej: ID de evento)
- Los resultados deben mostrarse en tabla ordenable
- Debe permitir exportar el log a Excel
- Solo los Administradores del Sistema pueden acceder al log de auditoría
- El acceso al log debe quedar registrado en el propio log de auditoría

Prioridad: Media · Actor: Administrador del Sistema

## 9.10 Épica 10: Configuración del sistema

### US-028: Configuración de Correspondencias de Notificación
Como Administrador del Sistema,
quiero configurar las correspondencias entre tipos de evento y grupos de WhatsApp,
para permitir ajustes futuros sin modificar el código del sistema (RN3, RN5).

Criterios de aceptación:
- El sistema debe tener una tabla de configuración que mapee:
  - Tipo de evento → Grupo de WhatsApp destinatario
  - Tipo de evento → Fuerza de respuesta (Bomberos, Guardia Local, Policía)
- Debe permitir modificar estas correspondencias desde una interfaz de administración
- Los cambios deben aplicarse inmediatamente a nuevos eventos
- Los cambios deben registrarse en log de auditoría
- Debe permitir agregar nuevos grupos de WhatsApp (identificador del grupo)

Prioridad: Baja · Actor: Administrador del Sistema

### US-029: Configuración de Parámetros del Sistema
Como Administrador del Sistema,
quiero configurar parámetros generales del sistema,
para adaptar el comportamiento del sistema a las necesidades operativas.

Criterios de aceptación:
- El sistema debe permitir configurar:
  - Tiempo de expiración de sesión (default: 30 minutos)
  - Número máximo de intentos de login antes de bloqueo (default: 3)
  - Tiempo de bloqueo de cuenta (default: 15 minutos)
  - Intervalo de actualización de dashboards (default: 60 segundos)
  - Número de reintentos de notificación WhatsApp (default: 3)
  - Intervalo entre reintentos (default: 30 segundos)
- Los cambios deben registrarse en log de auditoría
- Debe haber valores por defecto preconfigurados

Prioridad: Baja · Actor: Administrador del Sistema

## Dependencias identificadas

- US-023 y US-024 (mapas) son prerrequisitos para US-016 y US-017
- US-001 y US-002 son prerrequisitos para la mayoría de las historias
- US-025 (Gestión de Turnos) es prerrequisito para US-012 (Reportes por turno)
