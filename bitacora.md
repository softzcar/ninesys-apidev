# Bitácora de Desarrollo

### Miércoles, 06 de agosto de 2025

- Se identificó el endpoint `POST /insumos/nuevo` para la creación de nuevos insumos en `app/routes.php`.
- Se diagnosticó un error `Array to string conversion` en el endpoint `POST /insumos/nuevo` relacionado con el manejo de la respuesta de la base de datos.
- Se corrigió el error aislando el `last_insert_id` en una variable antes de construir la siguiente consulta SQL.
- Se proporcionó el bloque de código corregido al usuario para su implementación manual, resolviendo el bug.

### Jueves, 07 de agosto de 2025

- Se creó el endpoint `POST /inventario-tintas` para registrar las recargas de tinta.

### Tareas Pendientes

- Crear el endpoint `GET /inventario-tintas` para consultar el inventario de tintas con stock disponible.
- Crear el endpoint `GET /tintas_recargas` para consultar el historial de recargas de tinta.
- Crear el endpoint `GET /impresoras` para consultar el catálogo de impresoras.
- Crear el endpoint `GET /insumos` para consultar el catálogo de insumos.