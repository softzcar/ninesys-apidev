# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/), versionado según [SemVer](https://semver.org/lang/es/).

Ver también `ninesys-hub/releases/` para el contexto de negocio detrás de cada cambio (qué problema resolvía, qué otros repos se tocaron junto con este).

## [v1.0.50] - 2026-09-22
- Fix critico: /presupuesto/nuevo rechazaba con 401 las llamadas de servicio (19print_app/DTF) desde la auditoria de seguridad del 14/09 -- rompia por completo "Enviar pedido por WhatsApp" en la app DTF

## [v1.0.49] - 2026-09-22
- Comisiones y departamento en plantilla/bulk-load de carga masiva de productos

## [v1.0.48] - 2026-09-22
- Comisiones a 3 decimales en el esquema de nueva empresa (products_comisiones, products, pagos)

## [v1.0.47] - 2026-09-22
- Fix critico: reparto de comision entre 2+ empleados podia quedar mal armado y pagar 100% a cada uno (orden 6707, ~$9.84 corregidos en Produccion; 77 casos historicos mas detectados en CSV, sin corregir aun)
- Fix: /lotes/empleados/asignar-productos y /lotes/empleados/reasignar reconcilian ahora contra empleados retirados de la asignacion
- Fix: /lotes/empleados/reasignar-masiva valida que los porcentajes sumen 100%
- Fix: pagos.cantidad ahora se escala por el % del empleado en registrar-paso-empleado, finalizar-departamento, finalizar-impresion y finalizar-corte (antes solo el monto escalaba bien)

## [v1.0.46] - 2026-09-18
- Fix critico: /registrar-paso-empleado permitia completar y cobrar una reposicion eliminada/cancelada (orden 5186/reposicion #38, pago erroneo de $0.66 anulado en Produccion)

## [v1.0.45] - 2026-09-18
- Feat: exponer id_reposicion en /pagos/reporte-empleado y permitir filtrar /reposiciones-reporte por id_reposicion puntual (soporte para columna Origen clicable en Planilla de Pagos)

## [v1.0.44] - 2026-09-18
- Fix critico: pago de reposicion con comision Fija cobraba el monto del pedido original en vez del de la reposicion (corregidos $5.69 pendientes en Produccion, empresa 194)

## [v1.0.43] - 2026-09-18
- Fix: /sse/produccion mezclaba en "Asignacion de Personal" las filas de reposicion (0% por diseno) con la asignacion real, mostrando al mismo empleado como si tuviera carga duplicada

## [v1.0.42] - 2026-09-18
- Fix: aislar con SAVEPOINT los INSERT de pagos que corren dentro de transaccion explicita (evita que una carrera de duplicado tumbe otras escrituras legitimas del mismo request)

## [v1.0.41] - 2026-09-18
- Fix: blindaje completo contra pagos/cierres duplicados en todos los endpoints de lote (indices UNIQUE + guards de idempotencia faltantes + saneamiento de 425 filas duplicadas historicas)

## [v1.0.40] - 2026-09-18
- Fix urgente: /departamentos-empleado/{id} bloqueaba "Reposiciones por aprobar"/"Asignar reposicion" en Control de Produccion (/test) al consultar otro empleado -- ampliado a modulo 5

## [v1.0.39] - 2026-09-18
- Fix: comision de venta (vendedor) ahora se incluye en el costo de mano de obra del Reporte de Costos de Produccion (antes excluida a proposito, decision de negocio revertida)

## [v1.0.38] - 2026-09-17
- Fix: tercera copia del bug de empleados no trackeados, alimentaba el TOTAL MANO DE OBRA del reporte principal

## [v1.0.37] - 2026-09-17
- Fix: activacion de empleados y reporte de mano de obra usaban flag global en vez de asignacion por empresa

## [v1.0.36] - 2026-09-17
- Fix: login se colgaba al elegir empresa (Turnstile reutilizado en el segundo request)

## [v1.0.35] - 2026-09-17
- Fix: bloqueo enganoso al vincular empleado existente a otra empresa, ahora pide confirmacion y actualiza datos + fix plantilla pagos.cantidad/detalle

## [v1.0.33] - 2026-09-17
- Fix 403 en /ordenes/proyeccion-entrega para empleados de Comercializacion, Diseno y planta (modulos 2/3/4)

## [v1.0.32] - 2026-09-16
- Fase de seguridad completa: autorización backend, WhatsApp/CDN/19print, sesión única, aprobación de cliente por WhatsApp, esquema de BD sincronizado

## [v1.0.31] - 2026-09-10
- Auditoria de seguridad: fix real de CORS -- ResponseEmitter sobrescribia el fix anterior y agregaba Allow-Credentials sin validar.

## [v1.0.30] - 2026-09-10
- Auditoria de seguridad Fase 1: acotar CORS a origenes reales en vez de '*'.

## [v1.0.29] - 2026-09-09
- Auditoria de seguridad Fase 1: cabeceras de seguridad HTTP en el origen.

## [v1.0.28] - 2026-09-09
- Auditoria de seguridad: quitar SQL de respuestas, fix inyeccion en creacion de orden, whitelist de subida de archivos, quitar volcado a /tmp.

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
