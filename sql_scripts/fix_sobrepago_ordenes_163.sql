-- Script de corrección para órdenes con sobrepago incorrecto
-- Empresa ID: 163
-- Fecha: 2026-02-05
-- Problema: Descuentos duplicados en tabla abonos

-- ==================================================
-- PASO 1: Eliminar registros duplicados en abonos
-- ==================================================

-- Orden 1788 (SAYERLIN QUINTERO)
-- Eliminar duplicado de descuento $45.00
DELETE FROM abonos WHERE _id = 3928;

-- Orden 1948 (MARIANGEL ARTEAGA) 
-- Eliminar duplicados de descuento $8.00
DELETE FROM abonos WHERE _id IN (4141, 4142);

-- Orden 2024 (YULI FERNANDEZ)
-- Eliminar duplicado de descuento $11.00 (el registro 4319 solo tiene descuento)
DELETE FROM abonos WHERE _id = 4319;

-- Orden 2092 (DANIELA VILLASMIL)
-- Eliminar duplicados de descuento $5.00
DELETE FROM abonos WHERE _id IN (4920, 4921);

-- Orden 2123 (YINETH RUIZ)
-- Eliminar duplicado de descuento $18.00
DELETE FROM abonos WHERE _id = 4752;

-- Orden 2366 (ASDRUBAL GARCIA)
-- Eliminar duplicado de descuento $34.00
DELETE FROM abonos WHERE _id = 5293;

-- ==================================================
-- PASO 2: Actualizar totales en tabla ordenes
-- ==================================================

-- Orden 1788: Total pagado en metodos_de_pago: $0.00
-- Descuento real: $45.00
UPDATE ordenes 
SET pago_abono = 0.00, pago_descuento = 45.00
WHERE _id = 1788;

-- Orden 1948: Total pagado en metodos_de_pago: $25.00
-- Descuento real: $8.00
UPDATE ordenes 
SET pago_abono = 25.00, pago_descuento = 8.00
WHERE _id = 1948;

-- Orden 2024: Total pagado en metodos_de_pago: $35.11 (4706.80 / 134.00)
-- Descuento real: $11.00
UPDATE ordenes 
SET pago_abono = 35.11, pago_descuento = 11.00
WHERE _id = 2024;

-- Orden 2092: Total pagado en metodos_de_pago: $205.00
-- Descuento real: $5.00
UPDATE ordenes 
SET pago_abono = 205.00, pago_descuento = 5.00
WHERE _id = 2092;

-- Orden 2123: Total pagado en metodos_de_pago: $57.00
-- Descuento real: $18.00
UPDATE ordenes 
SET pago_abono = 57.00, pago_descuento = 18.00
WHERE _id = 2123;

-- Orden 2366: Total pagado en metodos_de_pago: $122.00
-- Descuento real: $34.00
UPDATE ordenes 
SET pago_abono = 122.00, pago_descuento = 34.00
WHERE _id = 2366;

-- ==================================================
-- VERIFICACIÓN
-- ==================================================

-- Verificar que los registros duplicados fueron eliminados
SELECT 'Verificación de abonos' as verificacion;
SELECT 
    id_orden,
    SUM(abono) as total_abonos,
    SUM(descuento) as total_descuentos,
    COUNT(*) as num_registros
FROM abonos 
WHERE id_orden IN (2366, 2123, 2092, 2024, 1948, 1788)
GROUP BY id_orden
ORDER BY id_orden DESC;

-- Verificar pagos en metodos_de_pago
SELECT 'Verificación de metodos_de_pago' as verificacion;
SELECT 
    id_orden,
    SUM(monto / tasa) as total_pagado_usd,
    COUNT(*) as num_pagos
FROM metodos_de_pago 
WHERE id_orden IN (2366, 2123, 2092, 2024, 1948, 1788)
GROUP BY id_orden
ORDER BY id_orden DESC;

-- Verificar estado final en ordenes
SELECT 'Estado final de ordenes' as verificacion;
SELECT 
    _id as orden,
    cliente_nombre,
    pago_total as total,
    pago_descuento as descuento,
    pago_abono as abonado,
    ROUND(pago_total - pago_descuento - pago_abono, 2) as saldo_pendiente
FROM ordenes 
WHERE _id IN (2366, 2123, 2092, 2024, 1948, 1788)
ORDER BY _id DESC;
