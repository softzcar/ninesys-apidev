-- Script de corrección PARTE 2
-- Eliminar registros de descuento que son duplicados de abonos

-- Orden 2366: Eliminar registro de descuento $34 
-- (el registro 5294 ya tiene el abono $34 correcto)
DELETE FROM abonos WHERE _id = 5292;

-- Orden 2092: Eliminar registro de descuento $5
-- (el registro 4922 ya tiene el abono $5 correcto)
DELETE FROM abonos WHERE _id = 4919;

-- Orden 2024: Corregir registro 4320 para eliminar el descuento duplicado
UPDATE abonos SET descuento = 0.00 WHERE _id = 4320;

-- Actualizar pago_descuento en ordenes a CERO para estas órdenes
UPDATE ordenes SET pago_descuento = 0.00 WHERE _id IN (2366, 2092, 2024);

-- VERIFICACIÓN FINAL
SELECT 'Verificación final de abonos' as verificacion;
SELECT 
    id_orden,
    SUM(abono) as total_abonos,
    SUM(descuento) as total_descuentos,
    COUNT(*) as num_registros
FROM abonos 
WHERE id_orden IN (2366, 2123, 2092, 2024, 1948, 1788)
GROUP BY id_orden
ORDER BY id_orden DESC;

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
