# Evaluación de viabilidad: Migración de MariaDB a PostgreSQL

> **Estado:** EVALUACIÓN / DIAGNÓSTICO. No se ha implementado ningún cambio.
> **Fecha:** 2026-06-29
> **Alcance analizado:** plantilla `public/model/create_new_company_api_emp_N.sql`, base viva `api_emp_194` y central `api_empresas` (servidor `vps-contabo-dev`, MariaDB 10.11.18), y código de consultas en `app/` + `src/`.

---

## 1. Resumen ejecutivo (lo importante primero)

1. **La migración de motor es viable, pero es un proyecto grande (semanas, no días)** porque la API usa **SQL crudo vía PDO sin ORM**: cada consulta está escrita a mano con sintaxis MySQL/MariaDB y debe revisarse una por una.

2. **Cambiar de motor NO resolverá por sí solo el problema de rendimiento que te preocupa.** Con el volumen actual (231 MB, ~167 mil filas) MariaDB no está ni cerca de su límite. Los cuellos de botella reales son de **diseño**, no de motor:
   - Falta de claves foráneas e **índices** en las columnas de relación.
   - Una columna `longtext` inflada que pesa **199 MB ella sola** (`ordenes_observaciones`).
   - La arquitectura de **una base de datos por empresa** (muchas conexiones).

3. **Tu intuición sobre las claves foráneas es correcta y es el hallazgo más grave:** la plantilla declara **105 `FOREIGN KEY`**, pero la base real `api_emp_194` solo tiene **24 FK activas**. Hay **192 columnas `id_*`** que guardan IDs de otras tablas y la inmensa mayoría **no tiene integridad referencial**.

4. **Recomendación realista:** antes (o en vez) de migrar el motor, primero **sanear el esquema en MariaDB** (FKs, índices, sacar el `longtext` gigante). Eso da el 80% de la mejora de rendimiento con el 20% del esfuerzo y sin reescribir la API. La migración a PostgreSQL se justifica como paso posterior, planificado, cuando el factor decisivo sea concurrencia alta, consultas analíticas complejas o muchas empresas grandes simultáneas.

---

## 2. Fotografía del estado actual

### 2.1 Volumen de datos (servidor de desarrollo)

| Base | Tamaño | Filas aprox. | Notas |
|------|--------|--------------|-------|
| `api_emp_194` | **231 MB** | ~166.876 | Empresa "de prueba" — en realidad la más grande |
| `api_emp_163` | 47 MB | ~117.394 | Legacy (la que creías más grande) |
| `api_emp_193` | 3,7 MB | ~503 | |
| `api_emp_195` | 3,8 MB | ~323 | |
| `api_empresas` (central) | 0,2 MB | ~139 | 9 tablas, 5 FK |

> **Dato clave:** 231 MB / 167 mil filas es un volumen **pequeño** para cualquier motor. MariaDB maneja decenas de millones de filas sin problema si el esquema está bien indexado. La preocupación por "MariaDB no escalará" es prematura respecto al motor; el problema hoy es el diseño.

### 2.2 De dónde sale el peso real

`ordenes_observaciones` ocupa **199 MB de los 231 MB** totales, con solo **5.443 filas** (~37 KB por fila). Su estructura es `_id`, `id_orden`, `observaciones longtext`. Es casi seguro que ese `longtext` guarda contenido enorme (HTML, base64 o JSON acumulado). **Esto distorsiona cualquier métrica de rendimiento y se arrastraría tal cual a PostgreSQL si no se sanea primero.**

### 2.3 Objetos de base de datos

| Objeto | Plantilla `..._emp_N.sql` | Real `api_emp_194` | Real `api_empresas` |
|--------|---------------------------|--------------------|--------------------|
| Tablas | 96 | **99** (drift +3) | 9 |
| Claves foráneas | 105 declaradas | **24 activas** | 5 |
| Triggers | 3 | **0** | 0 |
| Funciones / Procedimientos | 0 | **0** | 0 |
| Vistas | 0 | **0** | 0 |
| Índices | — | 205 (~2/tabla) | — |

**Observaciones:**
- Los **3 triggers de la plantilla NO existen en `api_emp_194`** (`ordenes_fila_orden_cambios_*`). Significa que la lógica de "mantener últimos 3 snapshots" o no se aplica en 194, o se hace por código. Hay que confirmarlo antes de migrar.
- **No hay funciones, procedimientos ni vistas** en producción/desarrollo. Esto es una **buena noticia** para migrar: no hay PL/SQL propietario que reescribir (que suele ser lo más caro de una migración de motor).
- Hay **drift de esquema**: la base real tiene 3 tablas más que la plantilla. La plantilla no refleja exactamente lo desplegado.

---

## 3. El problema de las claves foráneas (tu sospecha, confirmada)

- **192 columnas** se llaman `id_*` (apuntan lógicamente a otra tabla).
- Solo **24** tienen `FOREIGN KEY` real en `api_emp_194`.
- Las 24 que sí existen están casi todas en tablas **nuevas/recientes**: CRM (`crm_*`), geografía (`catalogo_paises/estados/ciudades`), tintas (`catalogo_tintas`, `catalogo_colores_tintas`) e `inventario`.
- El **núcleo operativo histórico NO tiene FK**: `ordenes`, `ordenes_productos`, `ordenes_fila_orden`, `lotes`, `lotes_detalles`, `pagos`, `abonos`, `inventario_movimientos`, etc. Todas usan IDs "sueltos".

**Consecuencias hoy (en MariaDB, ya mismo):**
- Riesgo de datos huérfanos e inconsistencias (un pago apuntando a una orden borrada, etc.).
- Sin FK, MariaDB tampoco crea índices automáticos sobre esas columnas → los `JOIN` y filtros por `id_orden`, `id_lote`, etc. pueden hacer *full scan*. **Esta es probablemente la causa real de lentitud, no el motor.**

**Consecuencia para la migración:** PostgreSQL no aceptará crear una FK si existen datos huérfanos. Por lo tanto, **antes de migrar hay que limpiar los huérfanos**. Conviene auditar cada relación `id_*` candidata, decidir cuáles deben ser FK reales y sanear los datos. Este trabajo es **independiente del motor** y aporta valor inmediato en MariaDB.

---

## 4. Compatibilidad de tipos de datos (MariaDB → PostgreSQL)

| Tipo MariaDB (uso) | Equivalente PostgreSQL | Dificultad | Nota |
|--------------------|------------------------|------------|------|
| `int` / `int(11)` (≈359) | `integer` | Baja | El `(n)` de display se ignora |
| `bigint` (2) | `bigint` | Baja | |
| `tinyint(1)` (34) | `boolean` o `smallint` | **Media** | Decidir caso a caso: ¿es bandera 0/1 o número? Afecta al código PHP que compara `== 1` |
| `varchar(n)` / `char` (~163) | `varchar(n)` / `char` | Baja | |
| `text` / `mediumtext` / `longtext` (~45) | `text` | Baja | PG no distingue tamaños; todo es `text` |
| `decimal(p,s)` (~79) | `numeric(p,s)` | Baja | |
| `double` / `float` | `double precision` / `real` | Baja | |
| `datetime` (~24) | `timestamp` | Baja | |
| `timestamp` (~81) | `timestamptz` o `timestamp` | **Media** | Cuidado con zonas horarias y `ON UPDATE` |
| `date` / `time` | `date` / `time` | Baja | |
| `enum(...)` (16) | `CREATE TYPE ... AS ENUM` o `CHECK` | **Media** | PG sí tiene ENUM pero la sintaxis y ALTER son distintos |
| `json` (5) | `jsonb` | Media | Mejora en PG (jsonb indexable), pero operadores distintos |
| `longblob` (1) | `bytea` | Baja | |
| `AUTO_INCREMENT` (96 tablas) | `SERIAL` / `GENERATED ... AS IDENTITY` + secuencia | **Media** | Cambia cómo se obtiene el último ID insertado |
| `ON UPDATE CURRENT_TIMESTAMP` (11) | **No existe en PG** | **Media** | Requiere un trigger por tabla para emularlo |
| `ENGINE=InnoDB`, `CHARSET=utf8mb4` | (no aplica) | Baja | PG no tiene motores; UTF-8 es nativo |
| Collations mezcladas (`spanish_ci`, `unicode_ci`, `general_ci`) | Collation ICU / locale | **Media** | 79 tablas en `spanish_ci`, 18 en `unicode_ci`, 2 en `general_ci`. Ordenamientos y comparaciones case-insensitive cambian de comportamiento |

**Punto sensible nº 1 — `tinyint(1)` como booleano:** 34 columnas. En MariaDB es un entero; el PHP probablemente lee `1`/`0`. Si se mapea a `boolean` real de PG, el código que hace `if ($row['activo'] == 1)` puede romperse (PG devuelve `t`/`f` o `true`/`false`). Hay que decidir mapeo y revisar el frontend también.

**Punto sensible nº 2 — comparaciones case-insensitive:** MySQL con `_ci` compara sin distinguir mayúsculas/acentos por defecto. PostgreSQL **distingue** salvo que se use `citext` o collation ICU explícita. Búsquedas tipo `WHERE nombre = 'juan'` que hoy funcionan podrían dejar de coincidir. Es un cambio de comportamiento sutil y de alto impacto funcional.

---

## 5. Esfuerzo de adaptar las consultas (lo más costoso)

**No hay ORM** (sin Doctrine/Eloquent): es **SQL crudo vía PDO** repartido en ~86 archivos PHP. Cada consulta hay que revisarla. Inventario de construcciones específicas de MariaDB encontradas en `app/` + `src/`:

| Construcción | Apariciones | Equivalente PostgreSQL | Dificultad |
|--------------|-------------|------------------------|------------|
| **Backticks** `` `col` `` | **369 líneas** | Comillas dobles `"col"` o nada | Baja pero masiva (search & replace cuidadoso) |
| `DATE_FORMAT(...)` | **123** | `to_char(...)` con códigos distintos | **Alta** (los códigos de formato son diferentes: `%Y-%m` → `YYYY-MM`) |
| `IFNULL(a,b)` | 73 | `COALESCE(a,b)` | Baja |
| `CURDATE()` | 49 | `CURRENT_DATE` | Baja |
| `GROUP_CONCAT(...)` | 39 | `string_agg(...)` (separador y `ORDER BY` distintos) | **Media-Alta** |
| `TIMESTAMPDIFF(...)` | 36 | `EXTRACT(EPOCH FROM ...)` / `age()` | **Media-Alta** |
| `JSON_OBJECT(...)` | 19 | `json_build_object(...)` | Media |
| `lastInsertId()` (PDO) | 14 | Funciona, pero conviene `RETURNING id`; cuidado con nombre de secuencia | Media |
| `FIND_IN_SET(x, lista)` | 10 | `x = ANY(string_to_array(...))` | Media |
| `JSON_EXTRACT(...)` | 6 | Operadores `->` / `->>` / `#>>` | Media |
| `INSERT IGNORE` | 5 | `INSERT ... ON CONFLICT DO NOTHING` | Media |
| `LIMIT x, y` (offset) | 3 | `LIMIT y OFFSET x` | Baja |
| `LAST_INSERT_ID()` (SQL) | 3 | `currval()` / `RETURNING` | Media |
| `ON DUPLICATE KEY UPDATE` | 2 | `INSERT ... ON CONFLICT DO UPDATE` | Media |
| `DAYNAME` / `UNIX_TIMESTAMP` | 1+1 | `to_char(..., 'Day')` / `extract(epoch...)` | Baja |

**Además:** los **3 triggers** de la plantilla usan `GROUP_CONCAT`, `JSON_OBJECT` y variables de sesión `@var` — todo MySQL. Si se decide conservarlos, hay que reescribirlos como funciones PL/pgSQL + triggers (aunque hoy **no están activos en 194**, así que quizá ni hagan falta).

**Capa de conexión:** el DSN está cableado como `mysql:host=...` en `IdEmpresaMiddleware.php` y `app/routes_refactorized.php`, y se usa `PDO::MYSQL_ATTR_INIT_COMMAND` (atributo exclusivo de MySQL). Eso son puntos concretos a cambiar a `pgsql:host=...`.

---

## 6. Factor arquitectónico: una base de datos por empresa

El sistema es **multi-tenant con una base `api_emp_N` por empresa** (DSN construido dinámicamente en `IdEmpresaMiddleware`). Implicaciones:

- Migrar no es "una base", son **N bases** + la central. Hay que automatizar la conversión y repetirla por empresa.
- En PostgreSQL el equivalente natural puede ser **un esquema (`schema`) por empresa** dentro de una sola base, o **una base por empresa** como ahora. Cada opción tiene trade-offs de conexiones, backups y aislamiento.
- Este modelo, con muchas empresas grandes, **multiplica conexiones simultáneas**. Aquí PostgreSQL (con `PgBouncer`) o MariaDB necesitan *pooling*; no es ventaja automática de un motor sobre otro.

---

## 7. ¿PostgreSQL realmente ayudará al rendimiento?

**Sí, pero condicionalmente.** PostgreSQL tiende a rendir mejor que MariaDB en:
- Consultas analíticas complejas y agregaciones grandes (mejor planificador, índices parciales, `jsonb` indexable, índices `GIN`/`BRIN`).
- Alta concurrencia de escritura (MVCC más maduro).
- Integridad y consistencia (FK, constraints, transacciones).

Pero **a tu volumen actual la diferencia sería imperceptible**, y se vería **anulada** por los problemas de diseño que viajarían con los datos (sin índices, sin FK, `longtext` de 199 MB). Migrar de motor sin sanear primero es "cambiar de carro sin arreglar el motor".

---

## 8. Recomendación y hoja de ruta sugerida (sin implementar aún)

### Fase A — Saneamiento en MariaDB (alto valor, bajo riesgo, sin tocar la API)
1. Auditar las 192 columnas `id_*`, identificar huérfanos y limpiar.
2. Crear **índices** en las columnas de relación más usadas en `JOIN`/`WHERE` (esto solo ya debería mejorar mucho la velocidad).
3. Resolver el inflado de `ordenes_observaciones` (¿qué guarda ese `longtext`? ¿se puede mover a archivos/CDN o normalizar?).
4. Reconciliar el **drift** plantilla (96) vs real (99 tablas) y alinear FKs declaradas (105) con las reales (24).
5. Medir con datos reales (`EXPLAIN`) antes/después.

> Si después de la Fase A el rendimiento es adecuado, **quizá no necesites migrar de motor todavía.**

### Fase B — Prueba de concepto PostgreSQL (si se decide migrar)
1. Convertir **una** base (p. ej. `api_emp_193`, pequeña) con una herramienta tipo `pgloader`.
2. Crear una rama del backend con capa de conexión PG y portar las consultas más críticas.
3. Comparar rendimiento real con la misma carga.

### Fase C — Migración completa (proyecto formal)
1. Portar las ~106 consultas / 369 líneas con backticks y las funciones MySQL listadas en §5.
2. Resolver `tinyint(1)`→boolean y comparaciones case-insensitive (§4) — **revisar también el frontend**.
3. Reescribir triggers si se conservan; definir estrategia de secuencias/`lastInsertId`.
4. Automatizar la conversión por empresa + central.
5. Plan de *cutover*, backups y rollback.

---

## 9. Estimación de esfuerzo (orientativa)

| Bloque | Esfuerzo relativo |
|--------|-------------------|
| Fase A — Saneamiento esquema (FK, índices, longtext) | Medio — **el de mejor relación valor/esfuerzo** |
| Conversión de esquema/tipos (DDL) por empresa | Medio (automatizable con pgloader) |
| Reescritura de consultas SQL en PHP (§5) | **Alto** — es el grueso del trabajo |
| `tinyint(1)`/booleanos + case-insensitive + frontend | Medio-Alto (riesgo de regresiones funcionales) |
| Capa de conexión + secuencias + triggers | Medio |
| Pruebas, QA por empresa, cutover | Alto |

**Veredicto:** técnicamente factible y sin PL/SQL propietario que lo complique (ventaja). Pero el SQL crudo sin ORM hace que la reescritura de consultas sea el coste dominante. **El mayor retorno inmediato está en la Fase A dentro de MariaDB, no en cambiar de motor.**
