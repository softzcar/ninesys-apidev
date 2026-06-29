SELECT 'LDEA total filas' k, COUNT(*) v FROM lotes_detalles_empleados_asignados
UNION ALL SELECT 'LDEA con id_lotes_detalles NOT NULL', COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_lotes_detalles IS NOT NULL
UNION ALL SELECT 'LDEA con id_lotes_detalles NULL', COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_lotes_detalles IS NULL
UNION ALL SELECT '-- pagos --','' 
UNION ALL SELECT 'pagos total', COUNT(*) FROM pagos
UNION ALL SELECT 'pagos con id_lotes_detalles NOT NULL', COUNT(*) FROM pagos WHERE id_lotes_detalles IS NOT NULL
UNION ALL SELECT '-- lotes_movimientos --',''
UNION ALL SELECT 'lotes_movimientos total', COUNT(*) FROM lotes_movimientos
UNION ALL SELECT 'lotes_movimientos id_lotes_detalles NOT NULL', COUNT(*) FROM lotes_movimientos WHERE id_lotes_detalles IS NOT NULL
UNION ALL SELECT '-- cobertura --',''
UNION ALL SELECT 'lotes_detalles con >=1 hijo LDEA', COUNT(DISTINCT ld._id) FROM lotes_detalles ld JOIN lotes_detalles_empleados_asignados a ON a.id_lotes_detalles = ld._id
UNION ALL SELECT 'lotes_detalles SIN hijos LDEA', (SELECT COUNT(*) FROM lotes_detalles) - (SELECT COUNT(DISTINCT ld._id) FROM lotes_detalles ld JOIN lotes_detalles_empleados_asignados a ON a.id_lotes_detalles = ld._id);
