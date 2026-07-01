# Análisis de optimización de claves foráneas — `api_emp_N`

> Documento de trabajo persistente. Creado el 2026-06-29.
> Estado: **IMPLEMENTADO Y DESPLEGADO — Dev y Producción completamente sincronizados al 2026-06-30.**
> Esquema maestro: `ninesys-api/public/model/create_new_company_api_emp_N.sql`
> BD auditada: `api_emp_194` en `vps-contabo-prod` (producción real). `api_emp_163` = legacy/solo lectura.
>
> ### Resumen ejecutivo del estado actual (2026-06-30)
> - **53 FKs activas en producción** (21 preexistentes + 32 nuevas en dos jornadas)
> - **Dev = Producción** en esquema y código. Rama: `refactor/modular-routes` HEAD `4e159cc`
> - **Ola A** (17 FKs + 10 correcciones bigint): ✅ Dev + Prod — commits `99101bf` `346f891`
> - **Ola B** (5 FKs RESTRICT tintas/colores): ✅ Dev + Prod — commit `7627d7f`
> - **Ola C** (6 FKs + limpieza 173 filas): ✅ Dev + Prod — commit `2db7d08`
> - **reposiciones_departamentos_excluidos** (2 FKs): ✅ Dev + Prod — tabla creada en prod 2026-06-30
> - **Red de seguridad HTTP 409**: ✅ Dev + Prod — commit `48323ad`
> - **insert_id reemplaza SELECT MAX**: ✅ Dev + Prod — commit `b9d73ca`
> - **beginTransaction en /ordenes/nueva y /presupuesto/nuevo**: ✅ Dev + Prod — commit `4e159cc`
> - **displayErrorDetails: false**: ✅ Dev + Prod — commit `839fd09`
> - **id_catalogo_telas eliminada** (presupuestos_productos): ✅ Dev + Prod
> - **rendimiento.id_product**: ❌ DESCARTADO — columna no existe en Dev ni en Prod. No es tarea pendiente.

---

## 1. Estado actual del esquema

- **81 tablas**, todas InnoDB.
- **104 FKs reales** declaradas (el comentario del archivo dice "95 FKs" → **desactualizado**; corregir a 104).
- Acciones referenciales: `ON DELETE` → 63 CASCADE / 33 SET NULL / 8 RESTRICT. `ON UPDATE` → 103 CASCADE.

### Hallazgo 1 — inconsistencia menor
La única FK declarada *inline* (`fk_gastos_registros_plantilla` en `gastos_registros`) es **la única sin `ON UPDATE CASCADE`**. Las otras 103 sí lo tienen. Homogeneizar.

---

## 2. Clasificación de las 75 columnas `id_*` sin FK

Tras la corrección del usuario, **NO existen IDs externos residuales**: los campos con nombres heredados de la antigua sincronización WooCommerce (`id_woo`, `id_wp`, `id_wp_order`, `id_category`, `id_products_attributes`) hoy referencian registros **locales** de la misma `api_emp_N`. La única BD con la que se comunica es `api_empresas` (usuarios/empleados globales del tenant).

- **Cross-DB (FK InnoDB imposible):** 33 columnas de empleados/usuarios (`id_empleado`, `id_usuario`, `id_admin`, `id_vendedor`…) → viven en `api_empresas`, no hay tabla `empleados` local. No se pueden enlazar con FK.
- **FK locales faltantes (candidatas reales):** las auditadas en la sección 3.
- **Ambiguas / no-FK:** `identificador_fiscal`, `id_modulo` (no existe tabla `modulos`), `id_catalogo`, `id_carga`, `id_maquina` (no existe tabla `maquinas`).

### Hallazgo 2 — inconsistencia de nombres entre tablas paralelas
- `ordenes_productos` usa **`id_tela`** como FK → `catalogo_telas`.
- `presupuestos_productos` tiene `id_catalogo_telas` (con la FK declarada) **y** `id_tela` (sin FK). **⚠️ CORREGIDO tras investigar código+datos (ver 5.2): la FK está en la columna equivocada — la viva es `id_tela`, la muerta es `id_catalogo_telas`.**
- `ordenes` **no tiene NINGUNA FK a `customers`**; guarda el cliente en `id_wp` (sin FK) + `cliente_nombre`/`cliente_cedula`. En cambio `presupuestos.id_wp` SÍ tiene FK → `customers` (`presup_ibfk_1`).

---

## 3. Auditoría de integridad ejecutada en `api_emp_194` (solo lectura)

Script: `scratchpad/auditoria_fk.sql` (generador: `scratchpad/gen_audit.py`). Cada fila = `LEFT JOIN` hija→padre contando filas, nulos, no_nulos y huérfanos (valor sin padre).

### ✅ Viables YA — FK directo (0 huérfanos, con datos)
| Relación | Filas con valor |
|---|---|
| `lotes_detalles.id_woo → products` | 52.514 (0 huérfanos) — confirma id_woo = products local |
| `ordenes_productos.id_woo → products` | 12.515 |
| `inventario_movimientos.id_producto → products` | 3.398 |
| `ordenes_auditoria.id_orden → ordenes` | 1.708 |
| `rendimiento.id_departamento → departamentos` | 790 |
| `disenos.id_product → products` | 513 (398 NULL → usar SET NULL) |
| `revisiones.id_product → products` | 513 (397 NULL → SET NULL) |
| `products_prices.id_product → products` | 387 |
| `check_tareas.id_ordenes_productos → ordenes_productos` | 223 |
| `products_comisiones.id_product → products` | 121 |
| `inventario_movimientos_historial.id_movimiento → inventario_movimientos` | 20 |
| `catalogo_insumos_productos.id_product / id_departamento` | 9 c/u |
| `products_attributes_values.id_product → products` | 6 |
| `presupuestos_productos.*` (id_woo, id_category, id_size, id_tela) | 15 |

### ⚠️ Requieren LIMPIEZA antes de la FK (huérfanos > 0)
| Relación | Huérfanos | Total | Nota |
|---|---|---|---|
| `inventario_remanentes.id_insumo → inventario` | 46 | 125 | 🔴 37% rota |
| `ordenes_productos.id_category → categories` | 69 | 12.549 | 🟠 col `NOT NULL DEFAULT 0` → revisar sentinela 0 |
| `product_insumos_asignados.id_product → products` | 38 | 2.627 | 🟠 |
| `products_tiempos_de_produccion.id_product → products` | 7 | 883 | 🟡 |
| `ordenes.id_wp → customers` | 2 | 5.913 | 🟢 casi listo — confirma id_wp = cliente |

### ❌ Inferencia de padre INCORRECTA
- `presupuestos.id_wp_order → ordenes` — 5/5 huérfanos (100%). `id_wp_order` NO apunta a `ordenes._id`. Investigar significado real o marcar columna muerta.

### ⬜ Columnas/tablas sin datos (FK sin riesgo pero sin uso actual)
- Tablas vacías (0 filas): `inventario_corte` (×2 cols), `lotes_corte_ajustes` (×3 cols), `lotes_fisicos.id_woo`, `gastos_auditoria.id_registro`.
- Columnas casi muertas: `inventario_movimientos.id_reposicion` (100% NULL), `ordenes.id_wp_order` (5.911/5.913 NULL), `presupuestos_productos.id_products_attributes` (100% NULL).

---

## 4. Conclusiones clave

1. El nombre `id_woo` engaña pero los datos son locales e íntegros → las FKs grandes (`lotes_detalles`, `ordenes_productos` → products) son viables de inmediato.
2. `ordenes.id_wp` SÍ es el cliente (solo 2 huérfanos de 5.913) → vale la pena cerrar esa brecha; hoy la tabla más crítica no protege la relación con `customers`.
3. Cinco relaciones necesitan limpieza de datos antes de la FK; la peor es `inventario_remanentes.id_insumo` (37%).
4. `id_wp_order` quedó desmentida como FK → investigar o marcar muerta.

---

## 5. Próximos pasos pendientes

- [x] **Investigar filas huérfanas concretas (2026-06-30, `api_emp_194` Dev, solo lectura) — ver hallazgos en 5.1.**
- [x] **Resuelto el duplicado `id_tela` vs `id_catalogo_telas` (2026-06-30) — ver 5.2. Columna muerta `id_catalogo_telas` eliminada del maestro, Dev y Producción.**
- [x] **Preparar y aplicar ALTER TABLE para el grupo "viable YA" — Olas A, B y C completadas en Dev y Producción.**
- [x] **Corregir Hallazgo 1 y comentario "95 FKs"→"104 FKs" en el maestro (2026-06-30).** Contador actualizado a 123 FKs tras las olas.
- [x] **reposiciones_departamentos_excluidos creada en producción (2026-06-30).** FKs `fk_rde_reposicion` → `reposiciones` CASCADE y `fk_rde_departamento` → `departamentos` CASCADE activas.
- [x] **rendimiento.id_product — DESCARTADO.** La columna no existe en Dev ni en Prod. No es tarea pendiente real.

---

### ✅ TAREAS COMPLETADAS (resumen para retomar sesión)

Todo lo siguiente está DESPLEGADO en producción. Última actualización: **2026-07-01**

1. Red de seguridad FK: `DatabaseConstraintException` + HTTP 409 (`48323ad`)
2. Ola A: 17 FKs + 10 correcciones de tipo bigint/UNSIGNED (`99101bf` `346f891`)
3. Ola B: 5 FKs RESTRICT tintas/colores (`7627d7f`)
4. Ola C: 6 FKs + limpieza 173 filas sucias (`2db7d08`)
5. Tabla `reposiciones_departamentos_excluidos` + 2 FKs en producción (DDL directo)
6. `presupuestos_productos.id_catalogo_telas` eliminada en Dev y Prod
7. `displayErrorDetails: false` + logError activo (`839fd09`)
8. `insert_id` reemplaza `SELECT MAX(_id)` en 3 puntos de orders.php (`b9d73ca`)
9. `beginTransaction/commit/rollback` en `/ordenes/nueva` y `/presupuesto/nuevo` (`4e159cc`)
10. **P1** — `lotes_movimientos` eliminada (tabla vacía + código muerto en manufacturing.php) (`a2090e2`)
11. **P2** — `presupuestos.id_wp_order` eliminada de INSERT + esquema maestro; bug silencioso corregido (`b6bb5b6`)
12. **P3** — `beginTransaction/commit/rollback` en `/ordenes/nueva/simple` y `/presupuesto/{id}/convertir-a-orden` (`30a51dd`). Total: 8 pares en orders.php.
13. **P4** — Endpoint BAK `/produccion/reposicion/final/BAK` eliminado de production.php. Era código muerto con doble fuente de id_empleado y referencias a lotes_detalles. Frontend ya usaba solo `/produccion/reposicion/final`. (`b3da648`)

---

### ✅ PLAN COMPLETADO — Sin tareas pendientes de la jornada P1–P4

Todas las tareas del plan han sido implementadas y desplegadas en producción (HEAD `b3da648` en vps-contabo-prod).
Dev y Producción están completamente sincronizados.

### 5.1 Investigación de huérfanos — resultados (`api_emp_194` Dev, 2026-06-30)

| Relación | Huérfanos | Causa raíz confirmada | Acción para habilitar FK |
|---|---|---|---|
| `ordenes_productos.id_category → categories` | 69 | **Sentinela `0` = 53 filas** (col `NOT NULL DEFAULT 0`) + categoría borrada `1` = 16 filas | Crear categoría "Sin categoría" y reasignar, o volver la col nullable + `SET NULL`; reasignar las 16 de cat `1`. |
| `inventario_remanentes.id_insumo → inventario` | 43 distintos / 46 filas | **Insumos BORRADOS de `inventario`** (los ids huérfanos 719–836 caen dentro del rango real 355–931; 66 de los distintos sí existen). `inventario` ES el padre correcto; sin FK con CASCADE quedan remanentes colgando. | Limpiar/borrar las 43 filas huérfanas y luego FK `ON DELETE CASCADE`. |
| `ordenes.id_wp → customers` | 2 | Sano por diseño: orden 3269 (`id_wp=1705`, cliente borrado, nombre preservado en snapshot) + orden 3542 (`id_wp=0`, "Cliente Desconocido", sentinela) | Tratar los 2 casos y FK `SET NULL` (o sentinela). El nombre se conserva igual en `cliente_nombre`. |
| `presupuestos.id_wp_order → ordenes` | 5/5 | **COLUMNA MUERTA:** las 5 filas valen `0` (no existe `ordenes._id = 0`). Nunca fue FK real. | No es FK. Marcar columna como muerta/legacy; no enlazar. |

### 5.2 Duplicado `id_tela` vs `id_catalogo_telas` en `presupuestos_productos` (investigado 2026-06-30)

Investigación de código (backend `ninesys-api` + frontend `app_multi`) y datos (`api_emp_194` Dev, solo lectura). **La FK del esquema está sobre la columna equivocada.**

| Columna | Filas pobladas (de 15) | Válidas en `catalogo_telas` | FK en esquema | Referencias en código |
|---|---|---|---|---|
| **`id_tela`** | 15/15 | 15/15 ✓ | **ninguna** | **viva**: INSERT/UPDATE/SELECT en `orders.php` (2221, 2301 `LEFT JOIN catalogo_telas ct ON pp.id_tela = ct._id`, 2451); frontend lee `apiProd.id_tela` y reenvía como `tela` (`presupuesto.vue`, `nueva.vue`, `NewStockOrderModal.vue`) |
| **`id_catalogo_telas`** | 0/15 (todo NULL) | — | **`presup_prod_ibfk_2` → catalogo_telas** | **cero** referencias en backend ni frontend |

Además hay una columna de texto `tela` ("Tela principal seleccionada desde Comercialización"). **No existe columna `rollo`/`insumo` aquí** → la hipótesis "id_tela = rollo en inventario" queda descartada: `id_tela` guarda `catalogo_telas._id`, igual que en `ordenes_productos`.

**Conclusión:** el verdadero duplicado/muerto es **`id_catalogo_telas`** (0 datos, 0 código, pero tiene la FK). La columna correcta y usada es **`id_tela`** (consistente con `ordenes_productos.id_tela`). NO se tocó nada.

**✅ APLICADO (2026-06-30) — eliminación de la columna muerta:**
1. **Maestro `create_new_company_api_emp_N.sql`:** eliminada la columna `id_catalogo_telas`; índices → `KEY id_orden (id_orden)` + `KEY id_tela (id_tela)`; FK `presup_prod_ibfk_2` **redirigida a `id_tela`** (espejo de `ord_prod_ibfk_2`). Verificado: cero referencias a la columna `id_catalogo_telas` (la única coincidencia restante es un índice homónimo sobre la col `rollo` en `ordenes_productos`, no relacionado). Cambios SIN commit.
2. **Contabo Dev (`api_emp_194`):** hallazgo — la tabla **no tenía NINGUNA FK** (drift: las FKs del maestro nunca se aplicaron a esta empresa). Backup previo (`/tmp/backup_presupuestos_productos_194dev_*.sql`). `ALTER`: drop índices `id_catalogo_telas` e `id_orden`, drop columna `id_catalogo_telas`, re-crear `KEY id_orden (id_orden)` y `KEY id_tela (id_tela)`. **No se añadió FK** (la adopción de FKs en empresas existentes pertenece al grupo "viable YA"). Datos intactos: 15 filas, `SUM(id_tela)=883` antes y después.
3. **Producción: NO tocada.** Pendiente de orden explícita; antes verificar datos de `id_catalogo_telas`/`id_tela` en la prod real.
- [ ] **EN CURSO:** Análisis de viabilidad de eliminar la tabla `lotes_detalles` y reemplazarla por `ordenes` (ver sección 6).

---

## 5.3 Red de seguridad central para FKs (APLICADO 2026-06-30, commit `48323ad`)

Prerrequisito antes de activar FKs por olas. Problema: `LocalDB::goQuery` atrapaba toda `PDOException` y devolvía `['status'=>'error']`, que ~90% de los 1.272 llamadores NO verifica → fallos como HTTP 200 silencioso (peligroso al activar FKs).

Cambio mínimo y dirigido (no altera otros flujos):
- **`LocalDB::goQuery`**: si el errno del driver es de integridad referencial (**1451/1452/1216/1217**) lanza `App\Application\Exceptions\DatabaseConstraintException`; el resto de errores (p. ej. duplicado 1062) conserva el retorno `status=error`.
- **Nueva** `src/Application/Exceptions/DatabaseConstraintException.php`.
- **`HttpErrorHandler`**: mapea esa excepción a **HTTP 409** con mensaje claro en español, mostrado siempre. Reutiliza el middleware de errores de Slim ya existente.

**Verificado en Dev (`api_emp_194`, código desplegado):** violación de FK (errno 1452) → 409 con mensaje; error no-FK (1054) → retorno array sin excepción; el UPDATE que viola la FK no persiste.

### Plan de activación de FKs por olas (pendiente)
- **Ola A** — FK CASCADE/SET NULL con 0 huérfanos y sin sentinela ni quiebre del camino feliz (la mayoría del grupo "viable YA" de la sección 3, incl. `presup_prod id_tela → catalogo_telas`). Activar en Dev y observar.
- **Ola B** — los 8 RESTRICT (tintas/colores): solo tras preparar el mensaje amigable de borrado (hoy ya cubierto en parte por el 409 central).
- **Ola C** — las que requieren datos: limpiar huérfanos / resolver sentinelas (`id_category=0`, `ordenes.id_wp=0`, `inventario_remanentes`).
- **Endurecer** los ~6 creadores multi-tabla (`/ordenes/nueva` y hermanos): transacción + `insert_id` (no `MAX(_id)`) + chequeo + HTTP correcto, antes de FKs sobre `ordenes_productos`/`lotes_detalles → ordenes`. (Auditoría detallada: ver log del 2026-06-30 de auditoría de `/ordenes/nueva`.)

## 6. Análisis `lotes_detalles` → posible reemplazo por `ordenes`

> Contexto del usuario: `lotes_detalles` es una de las primeras tablas. Su tarea inicial era el control del proceso del lote, pero su estructura quedó obsoleta cuando surgió la necesidad de asignar varios empleados a una misma tarea/departamento (ej. 4 costureras en la misma orden), lo que se resolvió con una implementación de distribución por porcentaje de carga (para comisiones). Por ser tan antigua, aparece en muchos JOINS ya obsoletos de la API que entorpecen resultados. Genera confusión por su nombre (se asume que controla producción, cuando ya no es su rol real). Hipótesis del usuario: los PK de `lotes_detalles` deberían estar sincronizados con los PK de `ordenes`, por lo que podría prescindirse de la tabla y usar `ordenes` en su lugar.

### 6.1 ¿Coinciden los PK de `lotes_detalles` y `ordenes`? → **NO** (hipótesis descartada)

Auditoría en `api_emp_194` (`scratchpad/check_pk.sql`):

| Métrica | Valor |
|---|---|
| Filas en `lotes_detalles` | 52.535 |
| `id_orden` distintos en `lotes_detalles` | 3.906 → ~13 filas por orden |
| Filas en `ordenes` | 5.913 |
| Filas donde `_id == id_orden` | 1 (coincidencia) |
| MAX `_id` lotes_detalles / ordenes | 56.129 / 5.916 (rangos distintos) |

`lotes_detalles` es tabla HIJA N:1 de `ordenes`, con `_id` autoincrement propio. **No** se puede reemplazar por `ordenes` ni reapuntar sus FKs entrantes a `ordenes._id`.

### 6.2 Estructura y FKs

- **Salientes:** `id_orden→ordenes` (CASCADE), `id_departamento→departamentos` (SET NULL), `id_ordenes_productos→ordenes_productos` (CASCADE), `id_reposicion→reposiciones` (SET NULL).
- **Entrantes (3 tablas dependen de `lotes_detalles._id`):**
  - `lotes_detalles_empleados_asignados` (LDEA) — CASCADE
  - `lotes_movimientos` — CASCADE
  - `pagos.id_lotes_detalles` — SET NULL

### 6.3 Uso real en el backend (grep exacto, excluyendo LDEA)

- **Escrituras vivas:** INSERT en `orders.php` (creación de orden, 1 fila por depto/producto), `production.php` (21, reposiciones), `designs.php`, `products.php`, `manufacturing.php`. UPDATE de `progreso`/`fecha_terminado` en `manufacturing.php` (8) y `production.php`. DELETE en `orders.php`, `designs.php`.
- **Lecturas:** `FROM lotes_detalles` en `manufacturing.php` (27) y `production.php` (24) — fuerte, candidato a JOINs obsoletos.

### 6.4 Relación real con sus hijos (datos `api_emp_194`, `scratchpad/rel_ld.sql`)

| Hecho | Cifra | Lectura |
|---|---|---|
| LDEA con `id_lotes_detalles` poblado | 382 / 10.981 (3,5%) | FK LDEA→lotes_detalles **abandonada**; LDEA opera por id_orden+id_departamento+id_empleado |
| `lotes_detalles` con hijo LDEA | 382 / 52.535 (0,7%) | 99,3% no alimenta el sistema nuevo |
| `pagos` con `id_lotes_detalles` | **7.061 / 13.082 (54%)** | 🔴 función viva crítica: trazabilidad de comisión/pago |
| `lotes_movimientos` | **0 filas** | tabla/FK muerta |

### 6.5 Conclusiones

1. **PK NO sincronizados con `ordenes`** → reemplazo plano por `ordenes` inviable.
2. **No se puede eliminar de plano:** 7.061 filas de `pagos` dependen de `lotes_detalles._id`.
3. **El rol de "control de producción" es vestigial/duplicado:** el control real migró a LDEA (que ya ni usa la FK al padre). De ahí los JOINs obsoletos en queries de producción/manufacturing.
4. **`lotes_movimientos` está vacía** → candidata a eliminación directa (verificar también en otras empresas).
5. Rol real superviviente de `lotes_detalles` = **(a)** esqueleto histórico de progreso por depto/producto + **(b)** ancla de trazabilidad de pagos.

### 6.6 Camino de depuración sugerido (reencuadrado — sin cambios aún)

En lugar de "reemplazar por ordenes" (inviable):
- [ ] Auditar endpoint por endpoint los `FROM`/`JOIN lotes_detalles` en `manufacturing.php` y `production.php` para marcar cuáles JOINs son obsoletos (el dato real ya está en LDEA) y eliminarlos con pruebas.
- [ ] Evaluar eliminar la tabla **`lotes_movimientos`** (0 filas) y su FK.
- [ ] Para una eventual jubilación de `lotes_detalles`: primero migrar la trazabilidad de `pagos` a una clave estable (p. ej. id_orden+id_departamento o a LDEA), porque es la única dependencia dura.
- [ ] Considerar renombrar/comentar la tabla para reflejar su rol real y reducir la confusión al programar.

### 6.7 Barrido de JOINs obsoletos — `manufacturing.php` y `production.php` (2026-06-29)

Resultado: la hipótesis de "muchos JOINs obsoletos" NO se sostiene en estos dos archivos; aquí `lotes_detalles` es carga estructural viva.

**🟢 KEEP (uso vivo):**
- Núcleo comisiones/pagos en `manufacturing.php`: patrón `SELECT _id FROM lotes_detalles WHERE id_orden+id_departamento` → ese `_id` como `id_lotes_detalles` en `INSERT INTO pagos` (líneas 436, 1168-1256, 2410-2748, 3802-3859). Origen de los 7.061 pagos. Intocable sin migrar pagos primero.
- Tablero de producción en `production.php` (306, 354, 1711, 1776): `lotes_detalles` = fuente de "órdenes activas × departamento × producto".

**🟠 REVIEW (doble fuente de verdad — foco real):**
- `production.php` 1368-1443: `SELECT id_empleado FROM lotes_detalles WHERE departamento='X'` lee el empleado desde `lotes_detalles.id_empleado`, cuando el sistema nuevo lo guarda en LDEA. Dos fuentes paralelas del "quién trabajó".

**🔴 DEAD (confirmado por datos):**
- `manufacturing.php` 1339-1346: usa `lotes_movimientos`, tabla VACÍA (0 filas). Camino muerto.
- Queries comentadas: `manufacturing.php` 2739, `production.php` 1556, 1728.

**Conclusión:** los JOINs obsoletos que "entorpecían resultados" probablemente están en los archivos de REPORTE (`products_reports.php` con 15 JOINs, `reports.php`), donde unir `lotes_detalles` infla filas/cuentas. Pendiente: barrer esos archivos. El problema de fondo en producción/manufacturing es la doble fuente de verdad `lotes_detalles.id_empleado` vs `LDEA.id_empleado`, no JOINs sobrantes.

### 6.8 Barrido reportes — `products_reports.php` y `reports.php` (2026-06-29)

- **`reports.php`: 0 JOINs** a `lotes_detalles` (sus menciones son la columna `id_lotes_detalles` en queries de `pagos`). Limpio.
- **`products_reports.php`: 15 JOINs, NINGUNO obsoleto.** Todos usan `lotes_detalles` como puente para resolver el producto (`ld.id_ordenes_productos → ordenes_productos.id_woo`), porque LDEA no guarda el producto. El código ya maneja la FK NULL con un `UNION ALL` de dos ramas:
  - Rama 1: `JOIN ld ON ldea.id_lotes_detalles = ld._id` (solo 3,5% de LDEA).
  - Rama 2: `JOIN ld ON (ldea.id_orden = ld.id_orden AND ldea.id_departamento = ld.id_departamento) WHERE ldea.id_lotes_detalles IS NULL` (96,5% de LDEA).

**🔴 HALLAZGO CRÍTICO — fan-out que infla el costo de mano de obra por producto:**
La Rama 2 une por clave natural `(id_orden, id_departamento)`. Si una orden tiene varios productos en el mismo departamento, una sola sesión de trabajo se atribuye COMPLETA a cada producto → sobre-conteo de tiempo y salario.

Datos `api_emp_194` (`scratchpad/fanout.sql`):
- Grupos `(orden, departamento)` con >1 producto: **8.242 / 27.577 (30%)**.
- Filas LDEA con FK NULL que caen en Rama 2 y tocan un grupo multi-producto: **4.903** (de 10.599 → ~46%).

→ El reporte de costos por producto (tiempos reales + salarios proporcionales, `products_reports.php` ~líneas 218-275, 530-600, 860-930) **sobre-cuenta la mano de obra** en esos casos. NO es un JOIN para eliminar; es la atribución por clave natural ante la falta del enlace granular. Causa raíz: `lotes_detalles` N:1 vs LDEA + FK `ldea.id_lotes_detalles` despoblada (96,5%).

**Opciones de corrección evaluadas:**
1. ~~Poblar `ldea.id_lotes_detalles` (backfill)~~ → DESCARTADO como raíz: solo 5.039/10.599 son deterministas (1 padre); 4.903 son AMBIGUAS (>1 padre, el sistema nunca guardó el producto del empleado) y 659 sin padre. El backfill no resuelve justo las filas que causan el fan-out, y exige escribir datos inferidos en producción.
2. **ELEGIDA — Atribución proporcional en la Rama 2:** dividir tiempo/salario entre el nº de filas `lotes_detalles` del grupo `(orden, departamento)`.
3. Atribuir por piezas reales por producto (descartada: mayor complejidad, sin mejor señal en datos).

**✅ APLICADO (2026-06-29) — fix de atribución proporcional en `products_reports.php`:**
A cada subquery de Rama 2 se le agregó un join derivado `grp` que cuenta las filas de `lotes_detalles` por `(orden, departamento)` y se dividió la duración entre `grp.np`:
```sql
JOIN (SELECT id_orden, id_departamento, COUNT(*) np FROM lotes_detalles GROUP BY id_orden, id_departamento) grp
     ON grp.id_orden = ldea.id_orden AND grp.id_departamento = ldea.id_departamento
-- ... SUM(TIMESTAMPDIFF(SECOND, ldea.fecha_inicio, COALESCE(ldea.fecha_terminado, NOW())) / grp.np)
```
- 6 subqueries corregidas (tiempos + salarios, por producto ×2 endpoints y por talla). Rama 1 (FK exacta) intacta. `php -l` OK.
- **Validación en `api_emp_194` (`scratchpad/validar_fix.sql`):** total Rama 2 actual = 8.041.895.411 seg (inflado 3,24×); corregido = 2.483.308.893 seg = verdad terreno exacta. ✓
- **Despliegue a Contabo Dev (2026-06-30): CONFIRMADO.** Commit `8cc5b21` commiteado y pusheado a `refactor/modular-routes`; deploy con `ninesys-hub/bin/deploy_backend.sh` opción 2 → `HEAD Remoto vps-contabo-dev = 8cc5b21`, OPcache reseteado. Producción intacta.
- **Verificación en Dev (2026-06-30): CONFIRMADA por dos vías.**
  - *UI:* `/reporte-costos-productos` (Reporte de Desviaciones de Costos) carga y renderiza la columna Costo Mano de Obra + su modal de detalle por talla, sin errores. Es un reporte **agregado por producto/talla** (no por orden); la "orden 5836" no aplica aquí (otro endpoint `/reportes/mano-obra-por-orden`, no tocado por el fix; además 5836 tiene 1 sola fila LDEA → no es caso de fan-out).
  - *Endpoint/dato:* integridad del deploy = 6 ocurrencias de `grp.np` en el archivo que corre en Dev. Re-ejecutada la lógica exacta de la Rama 2 contra `api_emp_194` (Dev, solo lectura): inflado = 8.060.397.074 s, corregido = 2.488.904.600 s, **ratio 3,239×** (reproduce la validación local de ayer; mínima deriva por `NOW()` en sesiones abiertas).
- **Pendiente:** aprobación del usuario para deploy a prod; NO desplegar a prod sin orden explícita.

### 6.9 Síntesis del barrido completo (4 archivos)

- `manufacturing.php`: `lotes_detalles` = núcleo de pagos/comisiones. KEEP. Único muerto: bloque `lotes_movimientos` (tabla vacía).
- `production.php`: tablero de producción (KEEP) + doble fuente de verdad `id_empleado` vs LDEA (REVIEW).
- `reports.php`: sin JOINs (limpio).
- `products_reports.php`: sin JOINs obsoletos, pero con **fan-out que infla costos** (CRÍTICO, corregible).
- **Conclusión global:** NO hay una masa de "JOINs obsoletos" que borrar; `lotes_detalles` está viva y es load-bearing. Los dos problemas reales son: (a) el fan-out de costos en reportes y (b) la FK `ldea.id_lotes_detalles` despoblada (96,5%), que es la raíz común. Jubilar la tabla no es viable a corto plazo (7.061 pagos dependen de su `_id`).

---

## 7. Auditoría FK de TODOS los endpoints de escritura (2026-07-01, solo lectura)

> Objetivo del usuario: tras activar las 53 FK en producción, verificar que **TODOS** los endpoints (no solo `/ordenes/*`) siguen funcionando correctamente con la integridad referencial ahora **exigida**. Auditoría de solo lectura sobre `app/routes/*.php` (30 archivos) + `app/model/LocalDB.php` + `src/Application/Handlers/HttpErrorHandler.php`. **Ningún cambio aplicado.**

### 7.1 Mecanismo real confirmado (importante — matiza la "red de seguridad" del §5.3)

1. `LocalDB::goQuery` (líneas 219-235) lanza `DatabaseConstraintException` **solo** para errno FK `1451/1452/1216/1217`; cualquier otro error (p. ej. 1062 duplicado) conserva el retorno `['status'=>'error']`.
2. `DatabaseConstraintException extends RuntimeException` → **es subclase de `Exception`**. Por tanto **cualquier `catch (Exception|\Throwable $e)` local de un endpoint la intercepta ANTES** de que llegue al `HttpErrorHandler` central (que la mapearía a 409). Hay ~130 `catch` en las rutas.
3. **Sin `catch` circundante → 409 limpio** con mensaje español (comportamiento ideal del §5.3).

⇒ La red 409 central **NO es universal**: solo cubre los endpoints sin `try/catch`. En los que sí lo tienen, manda el `catch` local.

### 7.2 Los FK NO rompen el camino feliz (conclusión tranquilizadora)

Ningún endpoint de operación normal se rompe al activar las FK: en el flujo feliz los padres (`ordenes`, `products`, `customers`, catálogos) **existen** cuando se insertan sus hijos. Los cambios de comportamiento ocurren **solo en caminos de error/borde** (id inválido, padre borrado, borrado con dependientes).

- **Verificado:** NO existe `DELETE FROM customers` ni `DELETE FROM ordenes` en ninguna ruta → los CASCADE de 27 hijos de `ordenes` y 4 de `customers` **nunca los dispara un endpoint** (las órdenes se anulan por estado, no se borran). El riesgo de "borrado en cascada catastrófico" queda descartado.
- **Verificado:** `geografia.php` **no tiene endpoints de borrado** de `catalogo_paises/estados/ciudades` (son datos semilla) → las 2 FK RESTRICT geográficas nunca se disparan desde la API.
- **Verificado:** no hay ningún `catch` que "trague y siga escribiendo" (swallow-and-continue). El peor patrón no existe.

### 7.3 Endpoints de BORRADO en tabla padre (cambio de comportamiento por FK)

| Endpoint | Tabla / acción FK | Comportamiento nuevo | Veredicto |
|---|---|---|---|
| `catalogs.php:245` `/catalogo-tintas/eliminar` | `catalogo_tintas` **RESTRICT** ×2 (impresoras, inventario) | Si la tinta está en uso → **409** (antes: borraba y dejaba huérfanos). Sin `try/catch` → 409 limpio. 1 sola sentencia (sin dato parcial). | 🟢 Correcto por diseño. Falta mensaje amable en frontend. |
| `catalogs.php:300` `/catalogo-colores-tintas/eliminar` | `catalogo_colores_tintas` **RESTRICT** ×4 | Igual que arriba → 409 si está referenciado. | 🟢 Correcto. Frontend debe manejar 409. |
| `catalogs.php:417` `/catalogo-telas/eliminar` | `catalogo_telas` **SET NULL** ×2 | Borra y pone NULL en hijos (silencioso). 1 sentencia. | 🟢 OK. |
| `printers.php:493` `DELETE /impresoras/{id}` | `catalogo_impresoras` **SET NULL** | Borra, hijos a NULL. Ya envuelto en `try/catch`→500. | 🟢 OK (no es RESTRICT, no falla). |
| `products.php:311` `DELETE /products/{id}` | `products` **CASCADE** ×6 + SET NULL ×5 | Protegido por chequeo `COUNT(ordenes_productos)=0` antes de borrar. Los `DELETE products_prices`/`products_attributes_values` posteriores **ahora son redundantes** (el CASCADE ya los borró) — inofensivo. | 🟡 OK; limpieza opcional del código redundante. |
| `products.php:1058` `DELETE sizes` | `sizes` SET NULL/CASCADE | 1 sentencia. | 🟢 OK. |
| `designs.php:329` `/disenador-asignado` | borra `revisiones`+`disenos`(CASCADE)+`pagos`, **multi-sentencia SIN transacción** | Si una falla, las previas ya se ejecutaron → borrado parcial. Los DELETE rara vez violan FK, pero no es atómico. | 🟠 Revisar: envolver en transacción. |
| `inventory.php:1050` `/insumos/eliminar` | `inventario` (padre de remanentes/movimientos) | Según la acción aplicada a `inventario_remanentes.id_insumo` (§5.1 propuso CASCADE): si CASCADE→borra remanentes; si aún sin FK o RESTRICT→**409**. 1 sentencia, retorna 200 en código pero 409 ganaría. | 🟠 Verificar qué acción quedó activa y confirmar UX. |

### 7.4 Clasificación de los `catch` que interceptan el 409 (bypass de la red central)

| Bucket | Descripción | Riesgo | Archivos representativos |
|---|---|---|---|
| **A — Seguro/atómico** | `catch` **con `rollBack()`** (o sin `catch` → 409 limpio). La violación se revierte; sin datos parciales. | Ninguno | `orders.php` (7 tx/7 rb), `payments.php` (5 rb), `production.php` (3), `finance.php` (2 tx/rb), 2 flujos de `inventory.php` y `manufacturing.php` |
| **B — Brecha de atomicidad** | Flujo **multi-escritura**, `catch` **sin `rollBack`** y **sin transacción**. Ante violación FK (raro, camino de borde) las escrituras previas **persisten** → dato parcial. El `catch` sí devuelve el mensaje, pero como 500. | Medio (solo en fallo FK) | **`manufacturing.php`**: los INSERT de `pagos`/`lotes_detalles` (936, 1018, 1099, 1138, 1253, 1605, 2513, 2610, 2679…) están **fuera** de sus 2 únicas transacciones (743-813). `designs.php` `/disenador-asignado`. `crm.php` (crear oportunidad + vendedores). |
| **C — Cosmético** | Endpoint de **1 sola escritura**, `catch` devuelve **500 en vez de 409** (el mensaje FK amable sí se muestra, pero el status es incorrecto; un frontend que discrimine por 409 no lo reconocerá). Sin riesgo de datos. | Bajo | `config.php` (14 catch), `crm.php`, `employees.php`, `catalogs.php`, `printers.php`, `communications.php`, `ai.php` |

### 7.5 Backlog priorizado (SIN cambios aplicados — pendiente de tu aprobación)

1. **[Atomicidad — Bucket B] `manufacturing.php`:** envolver los flujos de generación de comisiones/`pagos`+`lotes_detalles` en transacción con `rollBack` en el `catch` (patrón ya usado en `orders.php`). Es el mayor foco: es el corazón de pagos y hoy escribe fuera de transacción. *Impacto real bajo en camino feliz, pero es la brecha de datos más seria.*
2. **[Atomicidad — Bucket B] `designs.php:329` `/disenador-asignado`** y **`crm.php`** (oportunidad+vendedores): envolver el borrado/inserción multi-sentencia en transacción.
3. **[UX 409 — Bucket A/C] Homogeneizar el `catch` de FK:** en los `catch` que hoy devuelven 500, re-lanzar (o detectar) `DatabaseConstraintException` para responder **409 con el mensaje**, en lugar de 500. Cambio mínimo y transversal (un helper). Prioridad media: hoy el mensaje ya se ve, solo el status es incorrecto.
4. **[Frontend] Manejar 409** en los borrados de catálogos (tintas/colores) para mostrar "No se puede eliminar: está en uso" en vez de un error genérico.
5. **[Limpieza] `products.php:311`:** los `DELETE products_prices`/`products_attributes_values` son redundantes tras el CASCADE; opcional eliminarlos.
6. **[Verificar] `inventory.php:1050`:** confirmar la acción FK activa en `inventario_remanentes.id_insumo` y el UX del borrado de insumos con historial.

**Conclusión de la auditoría de endpoints:** activar las FK **no rompe ningún endpoint en operación normal**. Los ajustes pendientes son de robustez en caminos de error: (a) **atomicidad** en 2-3 flujos multi-escritura sin transacción (el más importante: `manufacturing.php`/pagos), y (b) **coherencia de status** (409 vs 500) en los `catch` que interceptan la excepción FK. Ninguno es urgente ni bloquea el uso; son endurecimiento incremental.

### 7.6 Inventario EXHAUSTIVO endpoint-por-endpoint (2026-07-01)

A petición del usuario se auditó **cada endpoint** (no por patrón). Script reproducible: `docs/auditoria_fk/audit_endpoints.py`; salida completa: `docs/auditoria_fk/endpoints_fk_audit.txt`. Método: se delimita cada endpoint por su declaración `$app->method(...)`, se detectan sus `INSERT/UPDATE/DELETE`, se cruza la tabla objetivo contra el conjunto FK (60 tablas hijas con FK saliente → riesgo 1452; 31 tablas padre → riesgo 1451/CASCADE), y se comprueba `beginTransaction`+`rollBack` en el bloque.

**Universo:** 112 endpoints POST/PUT/DELETE escriben sobre tablas que participan en FK. Estado:

| Estado | Nº | Significado |
|---|---|---|
| 🟢 **A — Atómico** (tx+rollback) | **17** | Ya endurecido. Ante violación FK revierte. Sin riesgo. |
| 🔴 **B-REAL** (≥2 tablas FK distintas, sin transacción) | **31** | **Prioridad.** Fallo FK a mitad → dato parcial. |
| 🟠 **B-single** (1 tabla, multi-sentencia, sin tx) | 24 | Menor: replace/upsert no atómico. |
| 🟡 **C — Cosmético** (1 sola escritura) | 43 | Sin riesgo de datos; solo status 500 en vez de 409. |
| ⚪ GET/OPTIONS con aparente escritura | 7 | Revisar (bad-REST o falso positivo de límite). |

**🟢 Los 17 ya atómicos:** `orders.php` (`/orden/abono`, `/ordenes/nueva`, `/presupuesto/nuevo`, `/ordenes/nueva/custom`, `/ordenes/nueva/sport`, `/ordenes/nueva/simple`, `/presupuesto/{id}/convertir-a-orden`), `payments.php` (`/pagos/terminar-planilla`, `/pagos/pagar-a-empleados`, `/pagos/procesar-lote-pagos`), `production.php` (`/ordenes/actualizar-filas-batch`, `/reposiciones/actualizar-filas-batch`, `/produccion/terminar/{id}`), `finance.php` (`/otro-abono`), `inventory.php` (`/inventario/consumo/{id_movimiento}`), `manufacturing.php` (`/registrar-paso-empleado`), `products.php` (`/lotes/empleados/reasignar-masiva`).

**🔴 Los 31 B-REAL (multi-tabla FK sin transacción) — backlog priorizado por nº de tablas:**

| Tablas | Endpoint | Archivo:línea |
|---|---|---|
| **10** | `POST /lotes/{id}/finalizar-departamento` | manufacturing.php:1641 |
| **8** | `POST /lotes/{id}/finalizar-impresion` | manufacturing.php:1958 |
| **8** | `POST /lotes/{id}/finalizar-corte` | manufacturing.php:2133 |
| **7** | `POST /ordenes/nueva/custom/edit` | orders.php:2352 |
| **5** | `POST /inventario-movimientos/empleados/update-insumo` | inventory.php:1178 |
| 4 | `POST /comercializacion/revisiones-estatus/...` | orders.php:3637 |
| 3 | `POST /empleados/registrar-paso/...` | manufacturing.php:2384 |
| 3 | `POST /empleados/registrar-paso-por-lotes/{departamento}` | manufacturing.php:2535 |
| 3 | `POST /orden/actualizar-estado` | orders.php:154 |
| 3 | `POST /ordenes/contexto-ia` | orders.php:4723 |
| 3 | `POST /lotes/empleados/reasignar` | products.php:1321 |
| 2 | `/asignacion/{orden}/{departamento}/{empleado}` | assignments.php:65 |
| 2 | `/crm/oportunidades/nueva` | crm.php:63 |
| 2 | `/diseno/update-tipo`, `/disenos/parobacion-de-cliente`, `/diseno/ajustes-y-personalizaciones`, `/disenador-asignado`, `/disenos/nuevo-con-revision`, `PUT /disenos/asign/...` | designs.php:106/177/194/323/449/738 |
| 2 | `/cierre-de-caja-vendedor` | finance.php:279 |
| 2 | `/inventario-movimientos/update-insumo` | inventory.php:1571 |
| 2 | `/lotes`, `/lotes/{id}/iniciar`, `/lotes/{id}/terminar`, `/lotes/empleados/reasignar_old`, `/lotes/update/cantidad`, `/truncate`, `/pausas` | manufacturing.php:20/72/143/1197/1294/1552/2791 |
| 2 | `/orden/editar` | orders.php:14 |
| 2 | `/inventario-tintas` | printers.php:415 |
| 2 | `/produccion/reposicion` | production.php:1104 |

**Hallazgo relevante:** `orders.php` está mayormente endurecido (sus 7 creadores son atómicos) pero **quedaron sin transacción 4 endpoints de escritura multi-tabla del mismo archivo**: `/ordenes/nueva/custom/edit` (¡7 tablas!), `/orden/actualizar-estado`, `/orden/editar`, `/ordenes/contexto-ia`. El grueso del backlog de atomicidad vive en **`manufacturing.php`** (las 3 finalizaciones de departamento/impresión/corte, de 8-10 tablas cada una) y en las 6 escrituras de **`designs.php`**.

⚠️ **Recordatorio de severidad:** todos estos gaps solo se manifiestan en el **camino de error** (una violación FK real). En operación normal los padres existen y el flujo no falla. Son mejoras de robustez, NO fallos funcionales actuales.
