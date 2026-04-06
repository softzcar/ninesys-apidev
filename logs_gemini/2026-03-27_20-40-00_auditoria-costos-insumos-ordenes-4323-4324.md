# Auditoría: Costos de Insumos en Reporte de Producción (Empresa 163)
Fecha: 2026-03-27 20:40:00

## Alcance
- Endpoint auditado: `GET https://api.nineteengreen.com/reportes/costos-produccion/2026-03-16/2026-03-20?debug=1`
- Empresa: 163 (Authorization: 163)
- Órdenes analizadas: 4324 (principal) y 4323 (referencia), más rango completo de la semana.

## Evidencias Recolectadas
- 4324 en el reporte:
  - status: "entregada"
  - total_productos: 2
  - costos_de_insumos: 0
  - costo_mano_de_obra: 2.25
  - debug_movimientos_insumos: 0
  - debug_tintas: 0
  - debug_tareas_total: 0
  - debug_pagos: 1
- Mano de obra por orden 4324:
  - `GET /reportes/mano-obra-por-orden/4324` → [{"id_empleado":10,"nombre_empleado":"Sayerlin","departamento":"Comercialización","monto_pago":2.25}]
  - Concluye que el pago registrado corresponde a Comercialización (vendedor), no a Producción.
- Materiales estimados (asignaciones) para 4324:
  - `POST /ordenes/materiales-lote` body: `{"id_ordenes":[4324]}`
  - Respuesta: [{"catalogo":"Papel SUBLIMACION","cantidad_estimada_de_consumo":"2.00","unidades":"2.0","unidad_de_medida":"Mt","id_orden":4324}]
  - Esto confirma que el producto tiene insumos asignados (catálogo) para Impresión, pero no se materializó consumo real.
- Inventario movimientos (consulta externa reportada por el usuario):
  - `SELECT COUNT(*) FROM inventario_movimientos WHERE id_orden IN (4323,4324)` → 2 registros pertenecen a 4323 (no a 4324).

## Análisis de Flujo (backend/frontend)
- Pagos se insertan al finalizar tareas:
  - Lógica en [manufacturing.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/manufacturing.php): registrar-paso, registrar-paso-por-lotes, registrar-paso-empleado (vinculan `pagos.id_lotes_detalles` a `lotes_detalles` o `lotes_detalles_empleados_asignados`).
  - Se puede generar pago aun sin registrar consumos de inventario en esa orden.
- Consumo de insumos se registra por separado:
  - `POST /inventario-movimientos/empleados/update-insumo` ([inventory.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/inventory.php)).
  - `POST /lotes/{id}/finalizar-departamento` con `consumos_lote` ([manufacturing.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/manufacturing.php)).
  - Si el frontend no envía consumos_lote o no dispara el endpoint de inventario, no habrá `inventario_movimientos`.
- Configuración que “solicita” (no obliga) registrar consumos:
  - Flags `sys_mostrar_insumo_en_empleado_*` y `sys_mostrar_rollo_en_empleado_*` ([auth.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/auth.php), [communications.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/communications.php), [config.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/config.php)).
  - En frontend se usan para mostrar/ocultar UI de consumo ([FinalizarLoteModal.vue](file:///home/developer/Escritorio/niesys/app_multi/components/empleados/FinalizarLoteModal.vue), [SseOrdenesAsignadasModalExtra.vue](file:///home/developer/Escritorio/niesys/app_multi/components/empleados/SseOrdenesAsignadasModalExtra.vue)).
  - El flujo actual permite finalizar sin consumos aunque las flags estén activas.

## Conclusión
- La orden 4324 se “entregó” con pago de comercialización pero **sin producción registrada** y **sin `inventario_movimientos`**; por ello el reporte muestra **Costo Insumos = 0**.
- La orden 4323 sí tiene producción/pagos y movimientos, lo cual explica el `COUNT(*)=2` reportado por el usuario.
- No es un problema del cálculo del reporte; es una **inconsistencia de flujo de datos** (consumo no registrado en la orden entregada).

## Verificaciones sugeridas
1) Relaciones y consistencia:
   - `SELECT _id, status, vinculada, responsable FROM ordenes WHERE _id IN (4323,4324);`
   - `SELECT id_orden, COUNT(*) tareas FROM lotes_detalles_empleados_asignados WHERE id_orden IN (4323,4324) GROUP BY id_orden;`
   - `SELECT id_orden, COUNT(*) movs FROM inventario_movimientos WHERE id_orden IN (4323,4324) GROUP BY id_orden;`
   - Objetivo: confirmar si 4324 está vinculada a 4323 y si producción/consumo se cargó en 4323.
2) Revisar UI de empleados/lote:
   - Confirmar si al finalizar lote se envía `consumos_lote` con los insumos para esa orden.
   - Confirmar si el dashboard llama al endpoint de inventario cuando se registra consumo manual.

## Propuesta de Corrección (no “tapar”, corregir origen)
- Política A (estricta): bloquear “terminar/entregar” si la orden con insumos asignados no tiene al menos 1 `inventario_movimientos` (o `tintas` cuando aplique).
- Política B (práctica): al finalizar departamentos críticos (Impresión/Estampado), obligar a registrar consumo (frontend) o autogenerarlo desde `product_insumos_asignados` (backend) en `POST /lotes/{id}/finalizar-departamento`.
- Backfill controlado: para órdenes ya terminadas/entregadas con consumo=0, generar movimientos proporcionales desde asignaciones (similar a `/ordenes/materiales-lote`), con auditoría.

## Cambios relacionados ya preparados (local)
- Backend:
  - Normalización de `cantidad_inicial` al crear insumos para evitar costos nulos (inserciones en [inventory.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/inventory.php) y [installer/routes.php](file:///home/developer/Escritorio/niesys/ninesys-api/installer/api/app/routes.php)).
  - Ajustes de cálculo de costos e inclusión de `costo_por_hora` en el reporte ([reports.php](file:///home/developer/Escritorio/niesys/ninesys-api/app/routes/reports.php)).
- Frontend:
  - Unificación de columnas (insumos+tintas, mano de obra+salarios) y robustez de IDs para salarios.

## Observaciones finales
- El diseño actual permite generar pagos sin que exista consumo registrado; esto es válido en casos de comercialización, pero no debe ser válido en producción cuando el producto tiene insumos asignados.
- Se recomienda implementar la política elegida (A o B) y ejecutar verificación/backfill sobre el histórico del rango observado.
