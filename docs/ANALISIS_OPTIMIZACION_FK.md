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
