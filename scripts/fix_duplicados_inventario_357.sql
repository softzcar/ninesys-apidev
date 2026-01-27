-- Script para eliminar registros duplicados en inventario_movimientos
-- Fecha: 2026-01-22
-- Insumo afectado: 357 (Papel de sublimacion 80gsm 1.6m)
-- 
-- Los duplicados fueron causados por llamadas dobles a terminarTodo() en el frontend
-- 
-- Registros a eliminar (mantenemos el _id más bajo de cada par):

-- Orden 3593: eliminar 555 (mantener 554)
-- Orden 3608: eliminar 570 (mantener 569)
-- Orden 3617: eliminar 598 (mantener 597)
-- Orden 3624: eliminar 612 (mantener 611)
-- Orden 3635: eliminar 626 (mantener 625)

DELETE FROM inventario_movimientos WHERE _id IN (555, 570, 598, 612, 626);

-- Verificar que se eliminaron correctamente:
-- SELECT _id, id_orden, id_insumo, valor_inicial, valor_final, moment 
-- FROM inventario_movimientos 
-- WHERE id_insumo = 357 
-- ORDER BY moment DESC;
