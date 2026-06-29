-- AUDITORIA DE INTEGRIDAD FK (SOLO LECTURA) - api_emp_N
-- Genera 1 fila por relacion candidata: filas, nulos, huerfanos (valor sin padre).
-- huerfanos > 0  => NO se puede agregar la FK sin limpiar/normalizar antes.
-- huerfanos = 0  => FK viable directamente.
-- (no_nulos = 0  => columna probablemente muerta/sin uso)

SELECT 'catalogo_insumos_productos.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `catalogo_insumos_productos` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'catalogo_insumos_productos.id_departamento -> departamentos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_departamento` IS NULL) AS nulos,
       SUM(c.`id_departamento` IS NOT NULL) AS no_nulos,
       SUM(c.`id_departamento` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `catalogo_insumos_productos` c LEFT JOIN `departamentos` p ON p.`_id` = c.`id_departamento`
UNION ALL
SELECT 'check_tareas.id_ordenes_productos -> ordenes_productos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_ordenes_productos` IS NULL) AS nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL) AS no_nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `check_tareas` c LEFT JOIN `ordenes_productos` p ON p.`_id` = c.`id_ordenes_productos`
UNION ALL
SELECT 'disenos.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `disenos` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'gastos_auditoria.id_registro -> gastos_registros._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_registro` IS NULL) AS nulos,
       SUM(c.`id_registro` IS NOT NULL) AS no_nulos,
       SUM(c.`id_registro` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `gastos_auditoria` c LEFT JOIN `gastos_registros` p ON p.`_id` = c.`id_registro`
UNION ALL
SELECT 'inventario_corte.id_orden -> ordenes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_orden` IS NULL) AS nulos,
       SUM(c.`id_orden` IS NOT NULL) AS no_nulos,
       SUM(c.`id_orden` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_corte` c LEFT JOIN `ordenes` p ON p.`_id` = c.`id_orden`
UNION ALL
SELECT 'inventario_corte.id_ordenes_productos -> ordenes_productos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_ordenes_productos` IS NULL) AS nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL) AS no_nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_corte` c LEFT JOIN `ordenes_productos` p ON p.`_id` = c.`id_ordenes_productos`
UNION ALL
SELECT 'inventario_movimientos.id_producto -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_producto` IS NULL) AS nulos,
       SUM(c.`id_producto` IS NOT NULL) AS no_nulos,
       SUM(c.`id_producto` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_movimientos` c LEFT JOIN `products` p ON p.`_id` = c.`id_producto`
UNION ALL
SELECT 'inventario_movimientos.id_reposicion -> reposiciones._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_reposicion` IS NULL) AS nulos,
       SUM(c.`id_reposicion` IS NOT NULL) AS no_nulos,
       SUM(c.`id_reposicion` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_movimientos` c LEFT JOIN `reposiciones` p ON p.`_id` = c.`id_reposicion`
UNION ALL
SELECT 'inventario_movimientos_historial.id_movimiento -> inventario_movimientos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_movimiento` IS NULL) AS nulos,
       SUM(c.`id_movimiento` IS NOT NULL) AS no_nulos,
       SUM(c.`id_movimiento` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_movimientos_historial` c LEFT JOIN `inventario_movimientos` p ON p.`_id` = c.`id_movimiento`
UNION ALL
SELECT 'inventario_remanentes.id_insumo -> inventario._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_insumo` IS NULL) AS nulos,
       SUM(c.`id_insumo` IS NOT NULL) AS no_nulos,
       SUM(c.`id_insumo` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `inventario_remanentes` c LEFT JOIN `inventario` p ON p.`_id` = c.`id_insumo`
UNION ALL
SELECT 'lotes_corte_ajustes.id_orden -> ordenes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_orden` IS NULL) AS nulos,
       SUM(c.`id_orden` IS NOT NULL) AS no_nulos,
       SUM(c.`id_orden` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_corte_ajustes` c LEFT JOIN `ordenes` p ON p.`_id` = c.`id_orden`
UNION ALL
SELECT 'lotes_corte_ajustes.id_ordenes_productos -> ordenes_productos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_ordenes_productos` IS NULL) AS nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL) AS no_nulos,
       SUM(c.`id_ordenes_productos` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_corte_ajustes` c LEFT JOIN `ordenes_productos` p ON p.`_id` = c.`id_ordenes_productos`
UNION ALL
SELECT 'lotes_detalles_empleados_asignados.id_reposicion -> reposiciones._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_reposicion` IS NULL) AS nulos,
       SUM(c.`id_reposicion` IS NOT NULL) AS no_nulos,
       SUM(c.`id_reposicion` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_detalles_empleados_asignados` c LEFT JOIN `reposiciones` p ON p.`_id` = c.`id_reposicion`
UNION ALL
SELECT 'ordenes_auditoria.id_orden -> ordenes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_orden` IS NULL) AS nulos,
       SUM(c.`id_orden` IS NOT NULL) AS no_nulos,
       SUM(c.`id_orden` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes_auditoria` c LEFT JOIN `ordenes` p ON p.`_id` = c.`id_orden`
UNION ALL
SELECT 'presupuestos_productos.id_size -> sizes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_size` IS NULL) AS nulos,
       SUM(c.`id_size` IS NOT NULL) AS no_nulos,
       SUM(c.`id_size` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos_productos` c LEFT JOIN `sizes` p ON p.`_id` = c.`id_size`
UNION ALL
SELECT 'presupuestos_productos.id_tela -> catalogo_telas._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_tela` IS NULL) AS nulos,
       SUM(c.`id_tela` IS NOT NULL) AS no_nulos,
       SUM(c.`id_tela` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos_productos` c LEFT JOIN `catalogo_telas` p ON p.`_id` = c.`id_tela`
UNION ALL
SELECT 'product_insumos_asignados.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `product_insumos_asignados` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'products_attributes_values.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `products_attributes_values` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'products_comisiones.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `products_comisiones` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'products_prices.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `products_prices` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'products_tiempos_de_produccion.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `products_tiempos_de_produccion` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'rendimiento.id_departamento -> departamentos._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_departamento` IS NULL) AS nulos,
       SUM(c.`id_departamento` IS NOT NULL) AS no_nulos,
       SUM(c.`id_departamento` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `rendimiento` c LEFT JOIN `departamentos` p ON p.`_id` = c.`id_departamento`
UNION ALL
SELECT 'revisiones.id_product -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_product` IS NULL) AS nulos,
       SUM(c.`id_product` IS NOT NULL) AS no_nulos,
       SUM(c.`id_product` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `revisiones` c LEFT JOIN `products` p ON p.`_id` = c.`id_product`
UNION ALL
SELECT 'ordenes.id_wp -> customers._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_wp` IS NULL) AS nulos,
       SUM(c.`id_wp` IS NOT NULL) AS no_nulos,
       SUM(c.`id_wp` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes` c LEFT JOIN `customers` p ON p.`_id` = c.`id_wp`
UNION ALL
SELECT 'ordenes_productos.id_woo -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_woo` IS NULL) AS nulos,
       SUM(c.`id_woo` IS NOT NULL) AS no_nulos,
       SUM(c.`id_woo` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes_productos` c LEFT JOIN `products` p ON p.`_id` = c.`id_woo`
UNION ALL
SELECT 'ordenes_productos.id_category -> categories._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_category` IS NULL) AS nulos,
       SUM(c.`id_category` IS NOT NULL) AS no_nulos,
       SUM(c.`id_category` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes_productos` c LEFT JOIN `categories` p ON p.`_id` = c.`id_category`
UNION ALL
SELECT 'ordenes_productos.id_products_attributes -> products_attributes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_products_attributes` IS NULL) AS nulos,
       SUM(c.`id_products_attributes` IS NOT NULL) AS no_nulos,
       SUM(c.`id_products_attributes` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes_productos` c LEFT JOIN `products_attributes` p ON p.`_id` = c.`id_products_attributes`
UNION ALL
SELECT 'presupuestos_productos.id_woo -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_woo` IS NULL) AS nulos,
       SUM(c.`id_woo` IS NOT NULL) AS no_nulos,
       SUM(c.`id_woo` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos_productos` c LEFT JOIN `products` p ON p.`_id` = c.`id_woo`
UNION ALL
SELECT 'presupuestos_productos.id_category -> categories._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_category` IS NULL) AS nulos,
       SUM(c.`id_category` IS NOT NULL) AS no_nulos,
       SUM(c.`id_category` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos_productos` c LEFT JOIN `categories` p ON p.`_id` = c.`id_category`
UNION ALL
SELECT 'presupuestos_productos.id_products_attributes -> products_attributes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_products_attributes` IS NULL) AS nulos,
       SUM(c.`id_products_attributes` IS NOT NULL) AS no_nulos,
       SUM(c.`id_products_attributes` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos_productos` c LEFT JOIN `products_attributes` p ON p.`_id` = c.`id_products_attributes`
UNION ALL
SELECT 'lotes_detalles.id_woo -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_woo` IS NULL) AS nulos,
       SUM(c.`id_woo` IS NOT NULL) AS no_nulos,
       SUM(c.`id_woo` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_detalles` c LEFT JOIN `products` p ON p.`_id` = c.`id_woo`
UNION ALL
SELECT 'lotes_fisicos.id_woo -> products._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_woo` IS NULL) AS nulos,
       SUM(c.`id_woo` IS NOT NULL) AS no_nulos,
       SUM(c.`id_woo` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_fisicos` c LEFT JOIN `products` p ON p.`_id` = c.`id_woo`
UNION ALL
SELECT 'ordenes.id_wp_order -> ordenes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_wp_order` IS NULL) AS nulos,
       SUM(c.`id_wp_order` IS NOT NULL) AS no_nulos,
       SUM(c.`id_wp_order` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `ordenes` c LEFT JOIN `ordenes` p ON p.`_id` = c.`id_wp_order`
UNION ALL
SELECT 'presupuestos.id_wp_order -> ordenes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_wp_order` IS NULL) AS nulos,
       SUM(c.`id_wp_order` IS NOT NULL) AS no_nulos,
       SUM(c.`id_wp_order` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `presupuestos` c LEFT JOIN `ordenes` p ON p.`_id` = c.`id_wp_order`
UNION ALL
SELECT 'lotes_corte_ajustes.id_lote -> lotes._id' AS relacion,
       COUNT(*) AS filas,
       SUM(c.`id_lote` IS NULL) AS nulos,
       SUM(c.`id_lote` IS NOT NULL) AS no_nulos,
       SUM(c.`id_lote` IS NOT NULL AND p.`_id` IS NULL) AS huerfanos
FROM `lotes_corte_ajustes` c LEFT JOIN `lotes` p ON p.`_id` = c.`id_lote`
ORDER BY huerfanos DESC, relacion;