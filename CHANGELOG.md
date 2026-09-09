# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/), versionado según [SemVer](https://semver.org/lang/es/).

Ver también `ninesys-hub/releases/` para el contexto de negocio detrás de cada cambio (qué problema resolvía, qué otros repos se tocaron junto con este).

## [v1.0.27] - 2026-09-09
- Recuperar clave por WhatsApp (/login/solicitar-clave) + cambiar clave desde Configuracion (/empleados/cambiar-clave).

## [v1.0.26] - 2026-09-09
- Fix: /empleados/nuevo ya no bloquea sin salida cuando el telefono coincide con otra identidad existente (causa raiz de la duplicacion de Zenaida) -- ahora ofrece vincular/reactivar igual que con email.

## [v1.0.25] - 2026-09-09
- Fix: /products/tallas-asignadas quedaba tapado por /products/{id}

## [v1.0.24] - 2026-09-09
- Nuevo endpoint /products/tallas-asignadas para filtrar tallas por producto en nueva orden/presupuesto

## [v1.0.23] - 2026-09-09
- Mensajes de WhatsApp por defecto al crear empresa nueva (bienvenida/despedida/saldo + 4 departamentos)

## [v1.0.22] - 2026-09-09
- Fix: pais/estado/ciudad de cliente se pisaban a NULL al reactivar/editar sin enviar esos campos

## [v1.0.21] - 2026-09-08
- Guardas de consumo de insumo (finalizar-departamento, produccion/terminar, reporte de consumo) ya detectan productos solo-impresion

## [v1.0.20] - 2026-09-08
- Fix columna Eficiencia Material en N/A (tercera ocurrencia del bug de talla en productos solo-impresion)

## [v1.0.19] - 2026-09-08
- Fix eficiencia de insumos: N/A y consumo real en 0 para productos solo-impresion y ordenes antiguas

## [v1.0.18] - 2026-09-08
- Modo sin limite (todos=1) en /table/ordenes-activas, base para volver a paginador client-side

## [v1.0.17] - 2026-09-08
- Columna verificado en metodos_de_pago + endpoint toggle, base para check manual en pagos-abonos

## [v1.0.16] - 2026-09-08
- Exponer es_servicio_de_impresion en /sse/produccion, base para filtro SOLO IMPRESION

## [v1.0.15] - 2026-09-08
- Fix: cardinality violation (filas duplicadas de asignacion granular) en reports/manufacturing-time y eficiencia-orden

## [v1.0.14] - 2026-09-08
- Fix: unidades fraccionarias (ej. DTF por metro) truncadas a 0 en reparto de lote, orden quedaba sin papel/tinta

## [v1.0.13] - 2026-09-08
- Fix: error SQL GROUP BY + calculo de comision incorrecto en /finalizar-impresion (primer uso real)

## [v1.0.12] - 2026-09-08
- Fix: bug orden atascada al finalizar LOTE (EXISTS + status terminada), replicado en los 3 endpoints de finalizacion de lote

## [v1.0.11] - 2026-09-04
- Quitar LIMIT en busqueda de clientes, revertir combinacion nombre+apellido (pedido explicito del usuario)

## [v1.0.10] - 2026-09-04
- Fix: busqueda de clientes por palabras (nombre+apellido) para acotar nombres comunes

## [v1.0.9] - 2026-09-04
- Fix: ordenar alfabeticamente el typeahead de busqueda de clientes (nueva orden/presupuesto)

## [v1.0.8] - 2026-09-04
- Reporte pagos-abonos: total pagado y saldo pendiente ahora acotados al rango de fechas, no historial completo

## [v1.0.7] - 2026-09-04
- Endpoint para borrar imagenes huerfanas del editor Quill

## [v1.0.6] - 2026-09-03
- Endpoint liviano GET /produccion/ordenes-terminadas para la nueva seccion de reposiciones sobre ordenes terminadas

## [v1.0.5] - 2026-09-03
- Fix: telefono/email crudos (no json_decode) al resolver/crear cliente -- email real nunca se capturaba antes

## [v1.0.4] - 2026-09-03
- Mismo fix de presupuesto/nuevo (nombre/apellido crudos + merge sin pisar datos reales) portado a /ordenes/nueva

## [v1.0.3] - 2026-09-03
- Fix: no pisar email/datos reales del cliente al actualizar desde presupuesto (merge, no reemplazo ciego)

## [v1.0.2] - 2026-09-03
- Fix regresion: nombre/apellido crudos al crear cliente desde presupuesto (no json_decode)

## [v1.0.1] - 2026-09-03
- Resolver o crear cliente en customers desde /presupuesto/nuevo (antes se perdian telefono/email/apellido)

## [v1.0.0] - 2026-09-02
Punto de partida del sistema de versionado. No es la primera versión real de la app -- es donde arranca el control formal de versiones, tags de git y este archivo.
