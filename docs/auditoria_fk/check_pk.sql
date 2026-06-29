SELECT 'lotes_detalles total filas' k, COUNT(*) v FROM lotes_detalles
UNION ALL SELECT 'lotes_detalles _id distintos', COUNT(DISTINCT _id) FROM lotes_detalles
UNION ALL SELECT 'lotes_detalles id_orden distintos', COUNT(DISTINCT id_orden) FROM lotes_detalles
UNION ALL SELECT 'ordenes total filas', COUNT(*) FROM ordenes
UNION ALL SELECT 'ordenes _id distintos', COUNT(DISTINCT _id) FROM ordenes
UNION ALL SELECT 'filas donde ld._id = ld.id_orden', COUNT(*) FROM lotes_detalles WHERE _id = id_orden
UNION ALL SELECT 'filas donde ld._id <> id_orden', COUNT(*) FROM lotes_detalles WHERE _id <> id_orden OR id_orden IS NULL
UNION ALL SELECT 'MAX _id lotes_detalles', MAX(_id) FROM lotes_detalles
UNION ALL SELECT 'MAX _id ordenes', MAX(_id) FROM ordenes
UNION ALL SELECT 'ld._id que SI existen como ordenes._id', COUNT(*) FROM lotes_detalles ld JOIN ordenes o ON o._id = ld._id
UNION ALL SELECT 'ld._id que NO existen como ordenes._id', COUNT(*) FROM lotes_detalles ld LEFT JOIN ordenes o ON o._id = ld._id WHERE o._id IS NULL;
