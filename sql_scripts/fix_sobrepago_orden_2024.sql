-- Corrección final para orden 2024
-- Según la imagen original, la orden mostraba SOBREPAGO, lo que significa que está pagada
-- Ajustamos pago_abono para reflejar que la orden está completamente pagada

UPDATE ordenes 
SET pago_abono = 46.00, pago_descuento = 0.00
WHERE _id = 2024;

-- Agregar un registro de descuento de $10.89 para cuadrar la orden
INSERT INTO abonos (id_orden, id_empleado, abono, descuento, detalle, moment)
VALUES (2024, 10, 0.00, 10.89, 'Ajuste corrección sobrepago', NOW());

-- Actualizar pago_descuento
UPDATE ordenes SET pago_descuento = 10.89 WHERE _id = 2024;

-- VERIFICACIÓN FINAL
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
