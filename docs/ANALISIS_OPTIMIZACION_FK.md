# Análisis de optimización de claves foráneas — `api_emp_N`

> Documento de trabajo persistente. Creado el 2026-06-29.
> Estado: **ANÁLISIS — sin cambios aplicados en BD ni en el esquema maestro.**
> Esquema maestro: `ninesys-api/public/model/create_new_company_api_emp_N.sql`
> BD auditada: `api_emp_194` en `vps-contabo-prod` (producción real). `api_emp_163` = legacy/solo lectura.

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
- `presupuestos_productos` usa **`id_catalogo_telas`** como FK → `catalogo_telas`, y además tiene una columna `id_tela` separada.
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

- [ ] Investigar filas huérfanas concretas (IDs) y confirmar si el sentinela `0` de `id_category` es el culpable.
- [ ] Resolver duplicado `id_tela` vs `id_catalogo_telas` en `presupuestos_productos`.
- [ ] Preparar `ALTER TABLE` solo para el grupo "viable YA" (no tocar las que requieren limpieza).
- [ ] Corregir Hallazgo 1 (`ON UPDATE` faltante) y el comentario "95 FKs"→"104 FKs" en el maestro.
- [ ] **EN CURSO:** Análisis de viabilidad de eliminar la tabla `lotes_detalles` y reemplazarla por `ordenes` (ver sección 6).

---

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
- **Pendiente:** sincronizar a Contabo Dev y verificar el endpoint completo; NO desplegar a prod sin orden explícita.

### 6.9 Síntesis del barrido completo (4 archivos)

- `manufacturing.php`: `lotes_detalles` = núcleo de pagos/comisiones. KEEP. Único muerto: bloque `lotes_movimientos` (tabla vacía).
- `production.php`: tablero de producción (KEEP) + doble fuente de verdad `id_empleado` vs LDEA (REVIEW).
- `reports.php`: sin JOINs (limpio).
- `products_reports.php`: sin JOINs obsoletos, pero con **fan-out que infla costos** (CRÍTICO, corregible).
- **Conclusión global:** NO hay una masa de "JOINs obsoletos" que borrar; `lotes_detalles` está viva y es load-bearing. Los dos problemas reales son: (a) el fan-out de costos en reportes y (b) la FK `ldea.id_lotes_detalles` despoblada (96,5%), que es la raíz común. Jubilar la tabla no es viable a corto plazo (7.061 pagos dependen de su `_id`).
