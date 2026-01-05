<?php

/**
 * Schema de la Base de Datos para el Asistente IA Gemini
 * 
 * Este archivo define la estructura de todas las tablas que Gemini puede consultar.
 * Incluye 57 tablas con descripciones de campos.
 * 
 * @package NineSys\AI
 */

return [
    'prompt_base' => "Eres un asistente de consultas para una empresa de confección y manufactura textil llamada Nineteen Custom.
Tu objetivo es responder preguntas sobre órdenes de producción, productos, inventario, clientes, pagos, empleados y estado de fabricación.
Debes generar consultas SQL SELECT basándote en las tablas disponibles.
Siempre responde en español de forma clara y profesional.

IMPORTANTE: La base de datos es MySQL. Usa la sintaxis de MySQL, NO SQLite.
Para fechas usa las funciones de MySQL:
- MONTH(campo) en lugar de strftime('%m', campo)
- YEAR(campo) en lugar de strftime('%Y', campo)
- DATE(campo) para extraer solo la fecha
- NOW() o CURDATE() para fecha actual
- Para filtrar por mes y año: WHERE MONTH(fecha_inicio) = 12 AND YEAR(fecha_inicio) = 2025
- Para rango de fechas: WHERE fecha_inicio BETWEEN '2025-12-01' AND '2025-12-31'

IMPORTANTE SOBRE EMPLEADOS:
Los datos de empleados están en una base de datos diferente llamada 'api_empresas'.
La tabla se llama 'empresas_usuarios' y debes referenciarla como 'api_empresas.empresas_usuarios'.
Los campos id_empleado, responsable, id_empleado_emisor, etc. en las tablas de la empresa hacen referencia a 'api_empresas.empresas_usuarios.id_usuario'.
Para obtener el nombre del empleado, haz JOIN con: api_empresas.empresas_usuarios eu ON campo_id_empleado = eu.id_usuario
IMPORTANTE: Cuando consultes empleados SIEMPRE filtra por id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " para obtener solo los empleados de esta empresa.

BÚSQUEDA DE EMPLEADOS POR NOMBRE:
- NUNCA pidas el nombre completo. SIEMPRE intenta buscar con el nombre proporcionado usando LIKE '%nombre%'
- Los empleados pueden tener nombres cortos (ej: 'Maru', 'Jesus') - busca con: WHERE nombre LIKE '%Maru%'
- Si el usuario dice 'empleado X' o 'empleada X', genera la consulta directamente con: eu.nombre LIKE '%X%'
- NO solicites más información sobre el nombre, INTENTA SIEMPRE generar la consulta SQL

DEFINICIÓN DE ESTADOS DE ÓRDENES:
- 'cancelada': Orden cancelada, no se procesará.
- 'entregada': Orden ya entregada al cliente, proceso terminado.
- 'terminada': Orden lista para entregar, producción finalizada.
- 'activa': Orden en proceso de producción.
- 'En espera': Orden pendiente de iniciar producción.

IMPORTANTE: Cuando el usuario pregunte por 'órdenes en curso', 'órdenes activas', 'órdenes en proceso' o similares, se refiere a órdenes que NO están 'cancelada' ni 'entregada'. Es decir: status IN ('En espera', 'terminada', 'activa') o también: status NOT IN ('cancelada', 'entregada').

PATRONES DE BÚSQUEDA GENÉRICOS:
Cuando el usuario pregunte por stock, inventario o cantidad de algún insumo/material, usa la tabla 'inventario' con LIKE para buscar por nombre o características:

1. Para buscar insumos por tipo: WHERE insumo LIKE '%tinta%' o WHERE insumo LIKE '%tela%' o WHERE insumo LIKE '%hilo%'
2. Para filtrar por característica adicional (color, tipo, tamaño): AND insumo LIKE '%{caracteristica}%'
   Ejemplo: WHERE insumo LIKE '%tinta%' AND insumo LIKE '%magenta%'
   Ejemplo: WHERE insumo LIKE '%tela%' AND insumo LIKE '%blanca%'
3. Campos útiles de inventario: insumo (nombre), cantidad, unidad, color, costo, departamento

IMPORTANTE SOBRE TINTAS:
- Las tintas DISPONIBLES (stock) están en la tabla 'inventario' (campo insumo contiene 'tinta')
- La tabla 'tintas' es SOLO para registrar CONSUMO de tintas CMYK cuando se imprime una orden
- Si preguntan '¿cuánta tinta X tenemos?' → buscar en inventario: WHERE insumo LIKE '%tinta%' AND insumo LIKE '%X%'

IMPORTANTE SOBRE PRODUCCIÓN Y ASIGNACIONES:
- La tabla 'lotes_detalles' es SOLO para ver el PROGRESO general de la orden (no tiene info de empleados ni tiempos)
- Para preguntas sobre: quién trabajó en qué, tiempos de producción, asignaciones, usar 'lotes_detalles_empleados_asignados'
- Campos clave de lotes_detalles_empleados_asignados: id_orden, id_empleado, id_departamento, fecha_inicio, fecha_terminado, progreso, terminado
- Para calcular tiempo de producción: TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_terminado) FROM lotes_detalles_empleados_asignados

CREACIÓN DE ÓRDENES CONVERSACIONAL:
Eres un asistente virtual de NINETEEN, especializado en ayudar a crear órdenes de producción de forma conversacional, profesional y amigable. 😊

📌 TU ROL:
- Guía al usuario paso a paso para crear la orden correcta
- Mantén un tono profesional pero cercano
- Usa emojis ocasionalmente para hacer la conversación más amigable (sin excederte)
- Sé claro, directo y evita tecnicismos innecesarios

🚫 CRÍTICO - NO GENERES SQL NI INVENTES DATOS:
Los datos de clientes, productos, tallas y telas YA VIENEN en el campo 'contexto_bd'.
- NO generes consultas SQL ni muestres código al usuario
- SOLO usa los datos proporcionados en 'contexto_bd'
- NUNCA inventes IDs, nombres, precios o datos que NO estén en el contexto
- Si 'contexto_bd' está vacío, pide más detalles de forma amigable

Ejemplo de datos reales:
Si recibes: [{\"id\":2,\"nombre\":\"Ozcar Atencio\"}]
DEBES usar: ID:2, nombre \"Ozcar Atencio\"
NUNCA uses: ID:12, ID:45 u otros inventados

⚠️ IMPORTANTE: Solo usuarios de 'Administración' o 'Comercialización' pueden crear órdenes.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 FLUJO DE 6 PASOS (OBLIGATORIO - EN ORDEN)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🎯 PASO 1 - IDENTIFICAR CLIENTE:

Si hay varios clientes en contexto_bd:
\"He encontrado varios clientes con ese nombre. ¿Cuál es el correcto?
1. Ozcar Atencio (ID: 2) - Tel: 0414-123-4567
2. Ozcar Daniel (ID: 45) - Tel: 0424-987-6543
Por favor indica el número.\"

Si hay solo uno:
\"Perfecto, encontré al cliente **Ozcar Atencio** (ID: 2). ¿Es correcto? 👍\"

Si NO hay coincidencias:
\"No encontré ese cliente en el sistema. ¿Podrías verificar el nombre? O si es un cliente nuevo, avísame para registrarlo.\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🛍️ PASO 2 - SELECCIONAR PRODUCTOS:

\"¿Qué productos necesitas para esta orden?\"

Si hay múltiples opciones similares:
\"Tenemos varias opciones de 'Franela':
1. Franela Oversize - $15.00
2. Franela Slim Fit - $12.00
3. Franela Deportiva - $18.00
¿Cuál prefieres? Puedes agregar varios productos.\"

Si el producto está claro:
\"Perfecto, agregaré 'Franela Oversize' a la orden. ✅\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📏 PASO 3 - TALLAS Y CANTIDADES:

Para CADA producto:
\"Para la 'Franela Oversize':
- ¿Qué talla necesitas? (S, M, L, XL, etc.)
- ¿Cuántas unidades?\"

Si el usuario dice \"4 talla S, 3 talla M\":
\"Entendido: 4 unidades talla S + 3 unidades talla M. ✅\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🧵 PASO 4 - TIPO DE TELA (OBLIGATORIO):

SIEMPRE pregunta la tela, mostrando las disponibles del catálogo:
\"¿Qué tipo de tela prefieres? Opciones disponibles:
1. 🌿 ALGODÓN
2. 💨 DRY FIT 1.60
3. 🏃 ATLÉTICA 1.60
4. ⚡ MICROPERFORADA
Indica el número o nombre.\"

Si la tela no existe:
\"Esa tela no está disponible. Te sugiero estas opciones: [lista del catálogo]\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✨ PASO 5 - ATRIBUTOS ESPECIALES (OBLIGATORIO):

\"¿Deseas agregar algún detalle especial a esta orden?
Por ejemplo: personalización, bordado, estampado, corte especial, etc.\"

Si dice SÍ:
\"Perfecto, ¿qué detalles quieres agregar?\" → Guárdalos en 'observaciones'

Si dice NO:
\"Entendido, sin atributos adicionales. 👍\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ PASO 6 - CONFIRMACIÓN FINAL:

Muestra un resumen CLARO antes de crear:
\"📋 **Resumen de la orden:**

👤 Cliente: **Ozcar Atencio**

🛍️ Productos:
• 4x Franela Oversize - Talla S - Tela: Algodón
• 3x Franela Oversize - Talla M - Tela: DRY FIT

💬 Observaciones: Estampado en el pecho

¿Todo correcto? Responde 'SÍ' para crear la orden o 'NO' para modificar algo.\"

Si confirma → Genera el JSON de create_order
Si rechaza → Pregunta qué quiere cambiar

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📊 CONTEXTO DE BASE DE DATOS (contexto_bd):

El sistema te proporciona:
- **clientes**: [{id, nombre_completo, telefono}]
- **productos**: [{id, nombre, precio}]
- **tallas**: [{id, nombre}]
- **telas**: [\"ALGODÓN\", \"DRY FIT\", ...]

🔍 USA ESTOS DATOS para validar y construir la orden.
🚫 NO uses datos que NO estén en este contexto.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📤 FORMATOS DE RESPUESTA:

A) ORDEN LISTA PARA CREAR (después de confirmación):
{\"action\":\"create_order\",\"ready\":true,\"data\":{\"cliente\":{\"id\":2,\"nombre\":\"Ozcar Atencio\"},\"productos\":[{\"id\":45,\"nombre\":\"Franela Oversize\",\"cantidad\":4,\"talla\":\"S\",\"tela\":\"ALGODÓN\"}],\"observaciones\":\"Estampado en el pecho\"}}

B) ORDEN EN PROGRESO (haciendo preguntas):
{\"action\":\"create_order\",\"ready\":false,\"step\":1,\"prompt\":\"¿Podrías confirmar el cliente? ¿Es 'Ozcar Atencio' el nombre correcto?\"}

C) ERROR O DATO FALTANTE:
Responde conversacionalmente: \"No encontré ese producto. ¿Podrías darme más detalles o elegir de estas opciones: [lista]?\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

💡 CONSEJOS DE TONO:

✅ SÍ usar:
- \"Perfecto, encontré...\"
- \"Entendido, agregaré...\"
- \"¿Todo correcto?\"
- \"¿Qué más necesitas?\"
- Emojis ocasionales: ✅ 📋 👍 🛍️

❌ NO usar:
- Lenguaje robótico o muy técnico
- \"Procesando solicitud...\" (muy formal)
- Muchos emojis seguidos 🎉🎊✨🌟 (exagerado)
- Términos como \"query\", \"payload\", \"endpoint\"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SELECCIÓN INTELIGENTE:
- Si el usuario dice \"la 2\" o \"el segundo\", selecciona la opción 2 de la lista que mostraste
- Si el usuario quiere corregir algo (\"ese no\", \"el otro\"), permite hacerlo

NOTA: La fecha actual es " . date('Y-m-d') . ".",

    // ===================================================================================
    'sql_recipes' => [
        // ==================== CAJA Y EFECTIVO ====================
        'Dólares en caja (efectivo)' => "SELECT SUM(monto) as total FROM caja WHERE moneda = 'Dólares' AND tipo = 'ingreso'",
        'Bolívares en caja (efectivo)' => "SELECT SUM(monto) as total FROM caja WHERE moneda = 'Bolívares' AND tipo = 'ingreso'",
        'Balance de caja por moneda' => "SELECT moneda, SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END) as balance FROM caja GROUP BY moneda",
        'Movimientos de caja de hoy' => "SELECT moneda, monto, tipo, detalle, moment FROM caja WHERE DATE(moment) = CURDATE()",
        'Egresos de caja del mes' => "SELECT SUM(monto) as total, moneda FROM caja WHERE tipo = 'egreso' AND MONTH(moment) = MONTH(CURDATE()) GROUP BY moneda",

        // ==================== PAGOS RECIBIDOS (metodos_de_pago) ====================
        'Total recibido por Zelle este mes' => "SELECT SUM(monto) as total FROM metodos_de_pago WHERE metodo_pago = 'Zelle' AND MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE())",
        'Total recibido por Pagomovil este mes' => "SELECT SUM(monto) as total FROM metodos_de_pago WHERE metodo_pago = 'Pagomovil' AND MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE())",
        'Total recibido por Transferencia este mes' => "SELECT SUM(monto) as total FROM metodos_de_pago WHERE metodo_pago = 'Transferencia' AND MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE())",
        'Pagos en efectivo de hoy' => "SELECT SUM(monto) as total FROM metodos_de_pago WHERE metodo_pago = 'Efectivo' AND DATE(moment) = CURDATE()",
        'Resumen de pagos por método' => "SELECT metodo_pago, SUM(monto) as total, COUNT(*) as cantidad FROM metodos_de_pago GROUP BY metodo_pago ORDER BY total DESC",
        'Resumen de pagos por moneda' => "SELECT moneda, SUM(monto) as total, COUNT(*) as cantidad FROM metodos_de_pago GROUP BY moneda ORDER BY total DESC",
        'Pagos recibidos de una orden' => "SELECT metodo_pago, moneda, monto, tasa, moment FROM metodos_de_pago WHERE id_orden = {ID_ORDEN}",
        'Pagos recibidos hoy' => "SELECT id_orden, metodo_pago, moneda, monto FROM metodos_de_pago WHERE DATE(moment) = CURDATE() ORDER BY moment DESC",
        'Total recibido hoy por método' => "SELECT metodo_pago, SUM(monto) as total FROM metodos_de_pago WHERE DATE(moment) = CURDATE() GROUP BY metodo_pago",

        // ==================== ÓRDENES ====================
        'Órdenes en curso (no canceladas ni entregadas)' => "SELECT _id, cliente_nombre, status, fecha_entrega, pago_total, pago_abono FROM ordenes WHERE status IN ('En espera', 'terminada', 'activa') ORDER BY fecha_entrega ASC",
        'Órdenes pendientes de entrega (terminadas pero no entregadas)' => "SELECT _id, cliente_nombre, fecha_entrega, (pago_total - pago_abono) as saldo FROM ordenes WHERE status = 'terminada' ORDER BY fecha_entrega ASC",
        'Órdenes con saldo pendiente' => "SELECT _id, cliente_nombre, pago_total, pago_abono, (pago_total - pago_abono) as saldo_pendiente FROM ordenes WHERE (pago_total - pago_abono) > 0 AND status NOT IN ('cancelada') ORDER BY saldo_pendiente DESC",
        'Total de ventas del mes' => "SELECT SUM(pago_total) as total_ventas, COUNT(*) as ordenes FROM ordenes WHERE MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE()) AND status != 'cancelada'",
        'Total de ventas de hoy' => "SELECT SUM(pago_total) as total_ventas, COUNT(*) as ordenes FROM ordenes WHERE DATE(moment) = CURDATE() AND status != 'cancelada'",
        'Buscar orden por cliente' => "SELECT _id, cliente_nombre, status, pago_total, pago_abono, fecha_entrega FROM ordenes WHERE cliente_nombre LIKE '%{NOMBRE}%' ORDER BY _id DESC",
        'Buscar orden por ID' => "SELECT _id, cliente_nombre, status, pago_total, pago_abono, fecha_entrega, fecha_inicio FROM ordenes WHERE _id = {ID}",
        'Órdenes canceladas del mes' => "SELECT _id, cliente_nombre, pago_total, moment FROM ordenes WHERE status = 'cancelada' AND MONTH(moment) = MONTH(CURDATE())",
        'Órdenes entregadas del mes' => "SELECT _id, cliente_nombre, pago_total, moment FROM ordenes WHERE status = 'entregada' AND MONTH(moment) = MONTH(CURDATE())",
        'Órdenes creadas esta semana' => "SELECT _id, cliente_nombre, status, pago_total, fecha_entrega FROM ordenes WHERE YEARWEEK(moment, 1) = YEARWEEK(CURDATE(), 1) ORDER BY moment DESC",
        'Cuántas órdenes hay en cada status' => "SELECT status, COUNT(*) as cantidad FROM ordenes GROUP BY status ORDER BY cantidad DESC",

        // ==================== PRODUCTOS DE ÓRDENES ====================
        'Productos de una orden' => "SELECT op._id, op.name as producto, op.cantidad, op.talla, op.tela, op.precio_unitario FROM ordenes_productos op WHERE op.id_orden = {ID_ORDEN}",
        'Total de piezas en producción' => "SELECT SUM(op.cantidad) as total_piezas FROM ordenes_productos op JOIN ordenes o ON op.id_orden = o._id WHERE o.status IN ('En espera', 'activa')",
        'Productos más vendidos del mes' => "SELECT op.name as producto, SUM(op.cantidad) as cantidad_vendida FROM ordenes_productos op JOIN ordenes o ON op.id_orden = o._id WHERE MONTH(o.moment) = MONTH(CURDATE()) AND o.status != 'cancelada' GROUP BY op.name ORDER BY cantidad_vendida DESC LIMIT 10",

        // ==================== PRODUCCIÓN Y DEPARTAMENTOS ====================
        'Órdenes en un departamento' => "SELECT DISTINCT l.id_orden, o.cliente_nombre, o.status, o.fecha_entrega FROM lotes l JOIN ordenes o ON l.id_orden = o._id JOIN departamentos d ON l.id_departamento_actual = d._id WHERE d.departamento = '{DEPARTAMENTO}'",
        'Órdenes en Costura' => "SELECT DISTINCT l.id_orden, o.cliente_nombre FROM lotes l JOIN ordenes o ON l.id_orden = o._id JOIN departamentos d ON l.id_departamento_actual = d._id WHERE d.departamento = 'Costura'",
        'Órdenes en Impresión' => "SELECT DISTINCT l.id_orden, o.cliente_nombre FROM lotes l JOIN ordenes o ON l.id_orden = o._id JOIN departamentos d ON l.id_departamento_actual = d._id WHERE d.departamento = 'Impresión'",
        'Órdenes en Corte' => "SELECT DISTINCT l.id_orden, o.cliente_nombre FROM lotes l JOIN ordenes o ON l.id_orden = o._id JOIN departamentos d ON l.id_departamento_actual = d._id WHERE d.departamento = 'Corte'",
        'Órdenes en Estampado' => "SELECT DISTINCT l.id_orden, o.cliente_nombre FROM lotes l JOIN ordenes o ON l.id_orden = o._id JOIN departamentos d ON l.id_departamento_actual = d._id WHERE d.departamento = 'Estampado'",
        'Resumen de carga por departamento' => "SELECT d.departamento, COUNT(DISTINCT l.id_orden) as ordenes FROM departamentos d LEFT JOIN lotes l ON d._id = l.id_departamento_actual WHERE d.asignar_numero_de_paso = 1 GROUP BY d._id ORDER BY d.orden_proceso",
        'Lista de departamentos' => "SELECT _id, departamento, orden_proceso FROM departamentos WHERE asignar_numero_de_paso = 1 ORDER BY orden_proceso",

        // ==================== ASIGNACIONES Y TIEMPOS DE PRODUCCIÓN (lotes_detalles_empleados_asignados) ====================
        // IMPORTANTE: Usar esta tabla para todo lo relacionado con empleados, asignaciones y tiempos de trabajo
        'Tareas asignadas a un empleado' => "SELECT ldea.id_orden, o.cliente_nombre, d.departamento, ldea.progreso, ldea.fecha_inicio FROM lotes_detalles_empleados_asignados ldea JOIN ordenes o ON ldea.id_orden = o._id JOIN departamentos d ON ldea.id_departamento = d._id WHERE ldea.id_empleado = {ID_EMPLEADO} AND ldea.terminado = 0 ORDER BY ldea.fecha_inicio DESC",
        'Tareas completadas hoy por empleados' => "SELECT ldea.id_orden, d.departamento, eu.nombre as empleado, ldea.fecha_inicio, ldea.fecha_terminado FROM lotes_detalles_empleados_asignados ldea LEFT JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario LEFT JOIN departamentos d ON ldea.id_departamento = d._id WHERE DATE(ldea.fecha_terminado) = CURDATE() AND ldea.terminado = 1",
        'Tiempo de producción de una orden' => "SELECT ldea.id_orden, d.departamento, eu.nombre as empleado, TIMESTAMPDIFF(SECOND, ldea.fecha_inicio, ldea.fecha_terminado) as tiempo_segundos FROM lotes_detalles_empleados_asignados ldea LEFT JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario LEFT JOIN departamentos d ON ldea.id_departamento = d._id WHERE ldea.id_orden = {ID_ORDEN} AND ldea.terminado = 1",
        'Empleados trabajando ahora' => "SELECT ldea.id_orden, o.cliente_nombre, d.departamento, eu.nombre as empleado, ldea.fecha_inicio FROM lotes_detalles_empleados_asignados ldea JOIN ordenes o ON ldea.id_orden = o._id LEFT JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario LEFT JOIN departamentos d ON ldea.id_departamento = d._id WHERE ldea.progreso = 'en curso' AND ldea.terminado = 0",
        'Resumen de progreso de lotes' => "SELECT progreso, COUNT(*) as cantidad FROM lotes_detalles GROUP BY progreso",

        // ==================== COMISIONES Y PAGOS A EMPLEADOS ====================
        'Comisiones pendientes de un empleado por nombre' => "SELECT SUM(p.monto_pago) as total FROM pagos p JOIN api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario WHERE eu.nombre LIKE '%{NOMBRE}%' AND eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " AND p.estatus = 'aprobado' AND p.fecha_pago IS NULL",
        'Empleados con comisiones pendientes' => "SELECT eu.nombre, SUM(p.monto_pago) as total_pendiente FROM pagos p JOIN api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario WHERE eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " AND p.estatus = 'aprobado' AND p.fecha_pago IS NULL GROUP BY eu.id_usuario, eu.nombre HAVING total_pendiente > 0 ORDER BY total_pendiente DESC",
        'Buscar empleado por nombre' => "SELECT id_usuario, nombre, departamento, comision FROM api_empresas.empresas_usuarios WHERE nombre LIKE '%{NOMBRE}%' AND id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " AND activo = 1",
        'Lista de empleados activos' => "SELECT id_usuario, nombre, departamento, comision, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " AND activo = 1 ORDER BY nombre",
        'Pagos realizados a empleados este mes' => "SELECT eu.nombre, SUM(p.monto_pago) as total_pagado FROM pagos p JOIN api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario WHERE eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163) . " AND p.fecha_pago IS NOT NULL AND MONTH(p.fecha_pago) = MONTH(CURDATE()) GROUP BY eu.id_usuario, eu.nombre ORDER BY total_pagado DESC",

        // ==================== DEPARTAMENTOS DE EMPLEADOS ====================
        // Un empleado puede tener MÚLTIPLES departamentos asignados (relación 1:N)
        'Departamentos asignados a un empleado' => "SELECT d.departamento FROM api_empresas.empresas_usuarios_departamentos eud JOIN departamentos d ON eud.id_departamento = d._id JOIN api_empresas.empresas_usuarios eu ON eud.id_empleado = eu.id_usuario WHERE eu.nombre LIKE '%{NOMBRE}%' AND eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163),
        'Cuántos departamentos tiene un empleado' => "SELECT COUNT(*) as cantidad_departamentos FROM api_empresas.empresas_usuarios_departamentos eud JOIN api_empresas.empresas_usuarios eu ON eud.id_empleado = eu.id_usuario WHERE eu.nombre LIKE '%{NOMBRE}%' AND eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163),
        'Empleados de un departamento' => "SELECT eu.nombre FROM api_empresas.empresas_usuarios_departamentos eud JOIN departamentos d ON eud.id_departamento = d._id JOIN api_empresas.empresas_usuarios eu ON eud.id_empleado = eu.id_usuario WHERE d.departamento LIKE '%{DEPARTAMENTO}%' AND eu.id_empresa = " . (defined('ID_EMPRESA') ? ID_EMPRESA : 163),

        // ==================== ABONOS Y SALDOS ====================
        'Abonos recibidos hoy' => "SELECT a.id_orden, o.cliente_nombre, a.abono, a.moment FROM abonos a JOIN ordenes o ON a.id_orden = o._id WHERE DATE(a.moment) = CURDATE() ORDER BY a.moment DESC",
        'Total abonado hoy' => "SELECT SUM(abono) as total FROM abonos WHERE DATE(moment) = CURDATE()",
        'Total abonado este mes' => "SELECT SUM(abono) as total FROM abonos WHERE MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE())",
        'Descuentos aplicados este mes' => "SELECT SUM(descuento) as total FROM abonos WHERE descuento > 0 AND MONTH(moment) = MONTH(CURDATE())",
        'Historial de abonos de una orden' => "SELECT abono, descuento, detalle, moment FROM abonos WHERE id_orden = {ID_ORDEN} ORDER BY moment DESC",

        // ==================== INVENTARIO ====================
        'Stock actual de inventario' => "SELECT _id, insumo, cantidad, unidad, color FROM inventario WHERE cantidad > 0 ORDER BY insumo",
        'Insumos con stock bajo (menos de 10)' => "SELECT _id, insumo, cantidad, unidad, color FROM inventario WHERE cantidad < 10 AND cantidad >= 0 ORDER BY cantidad ASC",
        'Insumos agotados' => "SELECT _id, insumo, unidad, color FROM inventario WHERE cantidad <= 0",
        'Buscar insumo por nombre' => "SELECT _id, insumo, cantidad, unidad, color, costo FROM inventario WHERE insumo LIKE '%{NOMBRE}%'",
        'Valor total del inventario' => "SELECT SUM(cantidad * costo) as valor_total FROM inventario WHERE cantidad > 0",
        'Inventario por departamento' => "SELECT departamento, COUNT(*) as insumos, SUM(cantidad) as cantidad_total FROM inventario GROUP BY departamento",

        // ==================== MÉTRICAS GENERALES ====================
        'Tasa de cambio promedio del día (Bolívares)' => "SELECT AVG(tasa) as tasa_promedio FROM metodos_de_pago WHERE moneda = 'Bolívares' AND metodo_pago IN ('Pagomovil', 'Transferencia') AND DATE(moment) = CURDATE()",
        'Última tasa de cambio registrada' => "SELECT tasa, metodo_pago, moment FROM metodos_de_pago WHERE moneda = 'Bolívares' ORDER BY moment DESC LIMIT 1",
        'Clientes con más órdenes' => "SELECT cliente_nombre, COUNT(*) as ordenes, SUM(pago_total) as total_compras FROM ordenes WHERE status != 'cancelada' GROUP BY cliente_nombre ORDER BY ordenes DESC LIMIT 10",
        'Ventas por mes (últimos 6 meses)' => "SELECT DATE_FORMAT(moment, '%Y-%m') as mes, SUM(pago_total) as ventas, COUNT(*) as ordenes FROM ordenes WHERE moment >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status != 'cancelada' GROUP BY mes ORDER BY mes DESC",

        // ==================== IMPRESORAS ====================
        'Lista de impresoras' => "SELECT _id, codigo_interno, marca, modelo, tipo_tecnologia, estado FROM catalogo_impresoras ORDER BY codigo_interno",
        'Impresoras activas' => "SELECT _id, codigo_interno, marca, modelo, tipo_tecnologia FROM catalogo_impresoras WHERE estado = 'activa'",
        'Buscar impresora por nombre' => "SELECT _id, codigo_interno, marca, modelo, tipo_tecnologia, estado FROM catalogo_impresoras WHERE codigo_interno LIKE '%{NOMBRE}%' OR marca LIKE '%{NOMBRE}%'",

        // ==================== BÚSQUEDA GENÉRICA EN INVENTARIO ====================
        // Estos son PATRONES genéricos. La IA debe adaptar el valor de búsqueda según lo que pregunte el usuario.
        'Stock de un tipo de insumo (patrón)' => "SELECT _id, insumo, cantidad, unidad, color FROM inventario WHERE insumo LIKE '%{TIPO_INSUMO}%' ORDER BY insumo",
        'Buscar insumo por tipo y característica' => "SELECT _id, insumo, cantidad, unidad FROM inventario WHERE insumo LIKE '%{TIPO}%' AND insumo LIKE '%{CARACTERISTICA}%'",
        'Stock de tintas' => "SELECT _id, insumo, cantidad, unidad FROM inventario WHERE insumo LIKE '%tinta%' ORDER BY insumo",
        'Buscar tinta por color' => "SELECT _id, insumo, cantidad, unidad FROM inventario WHERE insumo LIKE '%tinta%' AND insumo LIKE '%{COLOR}%'",
        'Stock de telas' => "SELECT _id, insumo, cantidad, unidad, color FROM inventario WHERE insumo LIKE '%tela%' ORDER BY insumo",
        'Buscar tela por color' => "SELECT _id, insumo, cantidad, unidad, color FROM inventario WHERE insumo LIKE '%tela%' AND (insumo LIKE '%{COLOR}%' OR color LIKE '%{COLOR}%')",
        'Insumos con stock bajo' => "SELECT _id, insumo, cantidad, unidad FROM inventario WHERE cantidad < 10 ORDER BY cantidad ASC",
    ],

    'tables' => [
        // ==================== EMPLEADOS (Base de datos externa: api_empresas) ====================
        'api_empresas.empresas_usuarios' => [
            'description' => 'Empleados/usuarios de la empresa (TABLA EN BD EXTERNA: api_empresas)',
            'fields' => [
                'id_usuario' => 'ID del empleado (PK) - Este es el ID que se usa en id_empleado, responsable, etc.',
                'id_empresa' => 'ID de la empresa a la que pertenece el empleado - SIEMPRE filtrar por este campo',
                'nombre' => 'Nombre completo del empleado',
                'email' => 'Email/username del empleado',
                'telefono' => 'Teléfono del empleado',
                'departamento' => 'Nombre del departamento asignado',
                'activo' => '1 si está activo, 0 si no',
                'acceso' => '0 = empleado, 1 = administrador',
                'salario_monto' => 'Monto del salario',
                'salario_periodo' => "'semanal', 'quincenal', 'mensual'",
                'salario_tipo' => "'Salario', 'Comisión', 'Salario más Comisión'",
                'comision' => 'Porcentaje de comisión',
                'comision_tipo' => "'fija' o 'variable'",
                'dni' => 'Documento de identidad',
                'fecha_ingreso' => 'Fecha de ingreso a la empresa',
            ]
        ],

        'api_empresas.empresas_usuarios_departamentos' => [
            'description' => 'TABLA DE RELACIÓN: Departamentos asignados a cada empleado (UN empleado puede tener MÚLTIPLES departamentos). Usar esta tabla para saber en qué departamentos puede trabajar un empleado.',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_empleado' => 'FK → api_empresas.empresas_usuarios.id_usuario',
                'id_departamento' => 'FK → departamentos._id (de la BD de la empresa)',
            ]
        ],

        // ==================== ÓRDENES ====================
        'ordenes' => [
            'description' => 'Órdenes de producción de la empresa',
            'fields' => [
                '_id' => 'ID único de la orden (PK)',
                'id_wp' => 'ID del cliente (FK → customers._id)',
                'status' => "Estado: 'entregada', 'cancelada', 'terminada', 'En espera'",
                'tipo' => "Tipo: 'custom' (personalizada) o 'catalogo'",
                'responsable' => 'ID del vendedor responsable',
                'cliente_nombre' => 'Nombre completo del cliente',
                'cliente_cedula' => 'Cédula del cliente',
                'fecha_inicio' => 'Fecha de inicio de producción',
                'fecha_entrega' => 'Fecha prometida de entrega',
                'pago_descuento' => 'Monto de descuento aplicado',
                'pago_total' => 'Monto total de la orden en USD',
                'pago_abono' => 'Monto abonado por el cliente',
                'pago_comision' => "Estado comisión: 'pendiente' o 'pagado'",
                'moment' => 'Fecha de creación del registro',
            ]
        ],

        'ordenes_productos' => [
            'description' => 'Productos dentro de cada orden de producción',
            'fields' => [
                '_id' => 'ID del registro (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_woo' => 'FK → products._id',
                'id_tela' => 'FK → catalogo_telas._id',
                'name' => 'Nombre del producto',
                'cantidad' => 'Cantidad de unidades',
                'talla' => 'Talla del producto',
                'corte' => "Tipo de corte: 'Dama', 'Caballero', 'Niño'",
                'metros' => 'Metros de tela a utilizar',
                'desperdicio' => 'Metros de tela desperdiciados',
                'tela' => 'Nombre de la tela principal',
                'precio_unitario' => 'Precio por unidad',
            ]
        ],

        'ordenes_observaciones' => [
            'description' => 'Notas y observaciones de las órdenes',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_empleado' => 'Empleado que registró la observación',
                'observaciones' => 'Texto de la observación',
                'moment' => 'Fecha de registro',
            ]
        ],

        'ordenes_vinculadas' => [
            'description' => 'Relación entre órdenes vinculadas (paquetes)',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden_padre' => 'Orden principal',
                'id_orden_hijo' => 'Orden vinculada',
            ]
        ],

        'ordenes_fila_orden' => [
            'description' => 'Posición de órdenes en la fila de producción',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_departamento' => 'FK → departamentos._id',
                'posicion' => 'Posición en la fila',
            ]
        ],

        'ordenes_fila_reposiciones' => [
            'description' => 'Fila de reposiciones pendientes',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_reposicion' => 'FK → reposiciones._id',
                'id_departamento' => 'FK → departamentos._id',
                'posicion' => 'Posición en la fila',
            ]
        ],

        // ==================== PRODUCCIÓN / LOTES ====================
        'lotes' => [
            'description' => 'Estado de producción de cada orden',
            'fields' => [
                '_id' => 'ID del lote (PK)',
                'lote' => 'Código del lote',
                'id_orden' => 'FK → ordenes._id',
                'id_departamento_actual' => 'FK → departamentos._id (dónde está)',
                'prioridad' => '0 = Normal, 1 = Urgente',
                'piezas_actuales' => 'Cantidad de piezas en proceso',
                'paso' => "Paso actual: 'Corte', 'Impresión', 'Estampado', 'Costura', 'Limpieza', 'terminado'",
            ]
        ],

        'lotes_detalles' => [
            'description' => 'SOLO PARA PROGRESO de la orden. NO usar para buscar asignaciones ni tiempos. Para tareas asignadas a empleados y tiempos de producción usar lotes_detalles_empleados_asignados.',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_ordenes_productos' => 'FK → ordenes_productos._id',
                'id_departamento' => 'FK → departamentos._id',
                'departamento' => 'Nombre del departamento (histórico)',
                'unidades_solicitadas' => 'Unidades a producir',
                'progreso' => "'por iniciar', 'en curso', 'terminada' - Estado del lote",
                'terminado' => '1 si la tarea del lote está terminada',
            ]
        ],

        'lotes_detalles_empleados_asignados' => [
            'description' => 'TABLA PRINCIPAL para asignaciones, tareas de empleados y tiempos de producción. Usar esta tabla para preguntas sobre: quién trabajó en qué orden, cuánto tiempo tardó, fechas de inicio/fin de trabajo.',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_lotes_detalles' => 'FK → lotes_detalles._id',
                'id_orden' => 'FK → ordenes._id',
                'id_empleado' => 'ID del empleado asignado (FK → api_empresas.empresas_usuarios.id_usuario)',
                'id_departamento' => 'FK → departamentos._id - Departamento donde trabajó',
                'procentaje_comision' => 'Porcentaje de comisión asignado al empleado',
                'progreso' => "'por iniciar', 'en curso', 'terminada' - Estado de la asignación",
                'terminado' => '1 si el empleado terminó su trabajo',
                'fecha_inicio' => 'Cuándo el empleado comenzó a trabajar',
                'fecha_terminado' => 'Cuándo el empleado terminó de trabajar',
            ]
        ],

        'lotes_detalles_empleados_asignados_pausas' => [
            'description' => 'Pausas de los empleados en tareas',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_lotes_detalles_empleados_asignados' => 'FK → lotes_detalles_empleados_asignados._id',
                'fecha_inicio' => 'Inicio de la pausa',
                'fecha_fin' => 'Fin de la pausa',
            ]
        ],

        'lotes_movimientos' => [
            'description' => 'Historial de movimientos de lotes entre departamentos',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_lote' => 'FK → lotes._id',
                'id_departamento_origen' => 'Departamento de origen',
                'id_departamento_destino' => 'Departamento destino',
                'moment' => 'Fecha del movimiento',
            ]
        ],

        'lotes_fisicos' => [
            'description' => 'Lotes físicos para manejo de inventario',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'codigo' => 'Código del lote físico',
            ]
        ],

        'empleados_lotes_fabricacion' => [
            'description' => 'Lotes de trabajo asignados a empleados',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_empleado' => 'Empleado que ejecuta',
                'id_departamento_actual' => 'Departamento actual',
                'estado' => "'pendiente', 'en_curso', 'terminado'",
                'fecha_inicio' => 'Inicio del lote',
                'fecha_fin' => 'Fin del lote',
            ]
        ],

        'empleados_lotes_fabricacion_items' => [
            'description' => 'Órdenes dentro de un lote de fabricación',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_lote' => 'FK → empleados_lotes_fabricacion._id',
                'id_orden' => 'FK → ordenes._id',
            ]
        ],

        // ==================== DEPARTAMENTOS ====================
        'departamentos' => [
            'description' => 'Departamentos de producción. Nombres: Administración, Montar Tallas, Impresión, Estampado, Corte, Costura, Limpieza, Comercialización, Revisión, Diseño, revision 2, Producción',
            'fields' => [
                '_id' => 'ID (PK)',
                'departamento' => 'Nombre del departamento (ver lista en description)',
                'orden_proceso' => 'Orden en el proceso de fabricación (1=primero)',
                'asignar_numero_de_paso' => '1 si es paso de producción, 0 si no',
                'enviar_mensaje' => '1 si envía mensaje al cliente al iniciar',
            ]
        ],

        // ==================== CLIENTES ====================
        'customers' => [
            'description' => 'Clientes de la empresa',
            'fields' => [
                '_id' => 'ID (PK)',
                'first_name' => 'Nombre',
                'last_name' => 'Apellido',
                'cedula' => 'Cédula o documento',
                'address' => 'Dirección',
                'phone' => 'Teléfono',
                'email' => 'Correo electrónico',
                'moment' => 'Fecha de registro',
            ]
        ],

        // ==================== PRODUCTOS ====================
        'products' => [
            'description' => 'Catálogo de productos',
            'fields' => [
                '_id' => 'ID (PK)',
                'product' => 'Nombre del producto',
                'sku' => 'Código SKU',
                'fisico' => '1 = producto físico, 0 = digital',
                'es_diseno' => '1 = pertenece a diseño',
                'price' => 'Precio del producto',
                'comision' => 'Monto para comisión variable',
                'stock_quantity' => 'Cantidad en stock',
            ]
        ],

        'products_attributes' => [
            'description' => 'Atributos de productos (extras)',
            'fields' => [
                '_id' => 'ID (PK)',
                'attribute_name' => 'Nombre del atributo',
                'precio' => 'Precio adicional del atributo',
            ]
        ],

        'products_comisiones' => [
            'description' => 'Comisiones por producto y departamento',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_product' => 'FK → products._id',
                'id_departamento' => 'FK → departamentos._id',
                'comision' => 'Porcentaje de comisión',
            ]
        ],

        'products_prices' => [
            'description' => 'Precios adicionales de productos',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_product' => 'FK → products._id',
                'price' => 'Precio',
                'descripcion' => 'Descripción del precio',
            ]
        ],

        'products_tiempos_de_produccion' => [
            'description' => 'Tiempos estimados de producción por producto',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_product' => 'FK → products._id',
                'id_departamento' => 'FK → departamentos._id',
                'tiempo' => 'Tiempo en segundos',
            ]
        ],

        'product_insumos_asignados' => [
            'description' => 'Insumos asignados a productos',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_product' => 'FK → products._id',
                'id_catalogo_insumos_productos' => 'FK → catalogo_insumos_productos._id',
                'id_departamento' => 'FK → departamentos._id',
                'cantidad' => 'Cantidad de insumo',
                'unidad' => 'Unidad de medida',
            ]
        ],

        'sizes' => [
            'description' => 'Catálogo de tallas',
            'fields' => [
                '_id' => 'ID (PK)',
                'nombre' => 'Nombre de la talla (S, M, L, XL, etc.)',
            ]
        ],

        'categories' => [
            'description' => 'Categorías de productos',
            'fields' => [
                '_id' => 'ID (PK)',
                'nombre' => 'Nombre de la categoría',
            ]
        ],

        // ==================== INVENTARIO ====================
        'inventario' => [
            'description' => 'Stock de insumos y materiales',
            'fields' => [
                '_id' => 'ID (PK)',
                'sku' => 'Código SKU',
                'id_catalogo' => 'FK → catalogo_insumos_productos._id',
                'insumo' => 'Nombre del insumo',
                'unidad' => "Unidad: 'Kilos', 'LTS', 'ML', 'UND', 'Metros'",
                'costo' => 'Precio de costo',
                'rendimiento' => 'Rendimiento del material',
                'cantidad' => 'Cantidad actual en stock',
                'color' => 'Color del insumo',
                'departamento' => 'Departamento al que pertenece',
            ]
        ],

        'inventario_movimientos' => [
            'description' => 'Historial de movimientos de inventario',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_insumo' => 'FK → inventario._id',
                'id_empleado' => 'Empleado que usó el insumo',
                'id_departamento' => 'FK → departamentos._id',
                'valor_inicial' => 'Cantidad antes del movimiento',
                'valor_final' => 'Cantidad después del movimiento',
                'fecha' => 'Fecha del movimiento',
            ]
        ],

        'catalogo_insumos_productos' => [
            'description' => 'Catálogo de tipos de insumos',
            'fields' => [
                '_id' => 'ID (PK)',
                'nombre' => 'Nombre del tipo de insumo',
                'unidad' => 'Unidad de medida',
            ]
        ],

        'catalogo_telas' => [
            'description' => 'Catálogo de telas disponibles',
            'fields' => [
                '_id' => 'ID (PK)',
                'tela' => 'Nombre de la tela',
            ]
        ],

        'catalogo_impresoras' => [
            'description' => 'Catálogo de impresoras de la empresa',
            'fields' => [
                '_id' => 'ID (PK)',
                'codigo_interno' => 'Código/nombre interno de la impresora (ej: IMPRESORA_MARU, DTF)',
                'marca' => 'Marca de la impresora (ej: AUDLEY, YinStar)',
                'modelo' => 'Modelo de la impresora',
                'capacidad_contenedor' => 'Capacidad del contenedor de tinta en ml',
                'ubicacion' => 'Ubicación física de la impresora',
                'tipo_tecnologia' => 'Tecnología de impresión (ej: CMYK, DTF)',
                'estado' => "'activa' o 'inactiva'",
                'notas' => 'Notas adicionales',
            ]
        ],

        'piezas_cortadas' => [
            'description' => 'Control de piezas cortadas',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'cantidad' => 'Cantidad de piezas',
            ]
        ],

        'rendimiento' => [
            'description' => 'Rendimiento de materiales por orden',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_insumo' => 'FK → inventario._id',
                'metros' => 'Metros utilizados',
                'desperdicio' => 'Desperdicio en gramos',
            ]
        ],

        // ==================== TINTAS ====================
        'tintas' => [
            'description' => 'REGISTRO DE CONSUMO de tintas por impresión (NO es el stock). Guarda cuánta tinta CMYK usó cada orden. Para ver stock de tintas, buscar en tabla inventario.',
            'fields' => [
                '_id' => 'ID (PK)',
                'c' => 'Consumo Cyan (ml)',
                'm' => 'Consumo Magenta (ml)',
                'y' => 'Consumo Yellow (ml)',
                'k' => 'Consumo Black (ml)',
                'w' => 'Consumo White (ml)',
                'id_catalogo_impresoras' => 'FK → catalogo_impresoras._id',
                'id_orden' => 'FK → ordenes._id',
                'id_empleado' => 'Empleado que imprimió',
            ]
        ],

        'tintas_recargas' => [
            'description' => 'Recargas de tintas',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_insumo' => 'FK → inventario._id',
                'id_catalogo_impresora' => 'FK → catalogo_impresoras._id',
                'color' => "Color: 'C', 'M', 'Y', 'K', 'W'",
                'cantidad' => 'Cantidad en ML',
                'fecha_recarga' => 'Fecha de recarga',
            ]
        ],

        'tinta_filtro' => [
            'description' => 'Filtro de tintas por inventario',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_inventario' => 'FK → inventario._id',
                'color' => "Color: 'C', 'M', 'Y', 'K', 'W'",
            ]
        ],

        // ==================== PAGOS / CAJA ====================
        'pagos' => [
            'description' => 'Pagos realizados',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'monto' => 'Monto del pago',
                'metodo_pago' => 'Método de pago',
                'referencia' => 'Número de referencia',
            ]
        ],

        'pagos_abonos' => [
            'description' => 'Abonos a órdenes',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'monto' => 'Monto del abono',
                'metodo_pago' => 'Método de pago',
            ]
        ],

        'pagos_descuentos' => [
            'description' => 'Descuentos aplicados a órdenes',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'monto' => 'Monto del descuento',
                'motivo' => 'Motivo del descuento',
            ]
        ],

        'pagos_salarios' => [
            'description' => 'Pagos de salarios a empleados',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_empleado' => 'ID del empleado',
                'monto' => 'Monto pagado',
                'tipo' => "'salario', 'comision', 'bono'",
            ]
        ],

        'metodos_de_pago' => [
            'description' => 'Registro de todos los pagos recibidos de clientes. Cada pago tiene método y moneda.',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_caja_cierres' => 'FK → caja_cierres._id (cierre al que pertenece)',
                'metodo_pago' => "'Pagomovil', 'Efectivo', 'Zelle', 'Transferencia', 'Punto'",
                'moneda' => "'Bolívares', 'Dólares', 'Pesos'",
                'tipo_de_pago' => "'Abono a orden' (pago parcial), 'Orden nueva' (pago inicial)",
                'monto' => 'Monto del pago',
                'tasa' => 'Tasa de cambio usada',
                'detalle' => 'Descripción o referencia del pago',
                'moment' => 'Fecha y hora del pago',
            ]
        ],

        'abonos' => [
            'description' => 'Abonos de clientes',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'monto' => 'Monto del abono',
                'moment' => 'Fecha del abono',
            ]
        ],

        'caja' => [
            'description' => 'Movimientos de EFECTIVO (dinero físico) en caja. El campo moneda indica la divisa (USD, VES, etc.)',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_caja_cierres' => 'FK → caja_cierres._id (cierre al que pertenece)',
                'monto' => 'Monto del movimiento en efectivo',
                'moneda' => "'Dólares', 'Bolívares', 'Pesos' - Esta es la divisa del efectivo",
                'tasa' => 'Tasa de cambio usada',
                'detalle' => 'Descripción del movimiento',
                'tipo' => "'ingreso' (entrada de efectivo) o 'egreso' (salida de efectivo)",
                'id_empleado' => 'FK → empleado que registró el movimiento',
                'moment' => 'Fecha y hora del movimiento',
            ]
        ],

        'caja_cierres' => [
            'description' => 'Cierres de caja',
            'fields' => [
                '_id' => 'ID (PK)',
                'fecha_cierre' => 'Fecha del cierre',
                'monto_inicial' => 'Monto inicial',
                'monto_final' => 'Monto final',
            ]
        ],

        'caja_fondos' => [
            'description' => 'Fondos de caja',
            'fields' => [
                '_id' => 'ID (PK)',
                'monto' => 'Monto del fondo',
                'moneda' => 'Moneda',
            ]
        ],

        'retiros' => [
            'description' => 'Retiros de dinero',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_empleado' => 'Empleado que retiró',
                'monto' => 'Monto retirado',
                'moneda' => 'Moneda',
                'metodo_pago' => 'Método de pago',
            ]
        ],

        // ==================== EMPLEADOS / SALARIOS ====================
        'empleados_salario' => [
            'description' => 'Configuración salarial de empleados',
            'fields' => [
                'id_empleado' => 'ID del empleado (PK)',
                'sueldo_base' => 'Salario mensual fijo',
                'moneda' => 'Moneda del salario',
                'bonos_fijos' => 'Bonos mensuales fijos',
                'fecha_inicio_contrato' => 'Fecha de inicio',
            ]
        ],

        'salario_carga_familiar' => [
            'description' => 'Cargas familiares de empleados',
            'fields' => [
                'id_carga' => 'ID (PK)',
                'id_empleado' => 'ID del empleado',
                'nombre_completo' => 'Nombre del familiar',
                'tipo_relacion' => "'hijo', 'esposa', 'padre', etc.",
                'es_deducible_impuesto' => '1 si califica para deducción',
            ]
        ],

        // ==================== DISEÑOS ====================
        'disenos' => [
            'description' => 'Diseños de productos',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_empleado' => 'Diseñador asignado',
                'id_product' => 'FK → products._id',
                'codigo_diseno' => 'Código interno XX-XXX',
                'tipo' => "'modas' o 'gráfico'",
                'terminado' => '1 si está terminado',
                'linkdrive' => 'Link a Google Drive',
            ]
        ],

        'disenos_ajustes_y_personalizaciones' => [
            'description' => 'Ajustes y personalizaciones de diseños',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_diseno' => 'FK → disenos._id',
                'tipo' => "'ajuste' o 'personalizacion'",
                'cantidad' => 'Cantidad de piezas',
            ]
        ],

        'revisiones' => [
            'description' => 'Revisiones de diseños',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_diseno' => 'FK → disenos._id',
                'id_empleado' => 'Diseñador',
                'revision' => 'Número de revisión',
                'estatus' => "'Esperando Respuesta', 'Rechazado', 'Aprobado'",
                'url_image' => 'URL de imagen',
            ]
        ],

        // ==================== REPOSICIONES ====================
        'reposiciones' => [
            'description' => 'Reposiciones de piezas defectuosas',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_departamento' => 'Departamento destino',
                'id_departamento_solicitante' => 'Departamento que solicita',
                'id_empleado' => 'Empleado asignado',
                'id_ordenes_productos' => 'FK → ordenes_productos._id',
                'unidades' => 'Unidades a reponer',
                'aprobada' => '1 aprobada, 0 rechazada, null pendiente',
                'terminada' => '1 si está terminada',
            ]
        ],

        // ==================== CONFIGURACIÓN ====================
        'config' => [
            'description' => 'Configuración del sistema',
            'fields' => [
                '_id' => 'ID (PK)',
                'sys_produccion_cierre_diario' => 'Hora de cierre producción',
                'sys_precio_sublimacion' => 'Precio sublimación',
                'sys_precio_dtf' => 'Precio DTF',
            ]
        ],
    ],

    'relations' => [
        'ordenes.id_wp → customers._id (cliente de la orden)',
        'ordenes.responsable → api_empresas.empresas_usuarios.id_usuario (vendedor responsable)',
        'ordenes_productos.id_orden → ordenes._id (productos de cada orden)',
        'ordenes_productos.id_woo → products._id (referencia al catálogo)',
        'ordenes_productos.id_tela → catalogo_telas._id (tela asignada)',
        'lotes.id_orden → ordenes._id (lote de cada orden)',
        'lotes.id_departamento_actual → departamentos._id (departamento actual)',
        'lotes_detalles.id_orden → ordenes._id (tareas por orden)',
        'lotes_detalles.id_ordenes_productos → ordenes_productos._id (producto de la tarea)',
        'lotes_detalles.id_empleado → api_empresas.empresas_usuarios.id_usuario (empleado asignado)',
        'lotes_detalles_empleados_asignados.id_lotes_detalles → lotes_detalles._id',
        'lotes_detalles_empleados_asignados.id_empleado → api_empresas.empresas_usuarios.id_usuario',
        'inventario.id_catalogo → catalogo_insumos_productos._id',
        'inventario_movimientos.id_insumo → inventario._id',
        'inventario_movimientos.id_empleado → api_empresas.empresas_usuarios.id_usuario',
        'pagos.id_orden → ordenes._id',
        'metodos_de_pago.id_orden → ordenes._id',
        'disenos.id_orden → ordenes._id',
        'disenos.id_empleado → api_empresas.empresas_usuarios.id_usuario (diseñador)',
        'reposiciones.id_orden → ordenes._id',
        'reposiciones.id_empleado → api_empresas.empresas_usuarios.id_usuario',
        'tintas.id_orden → ordenes._id',
        'tintas.id_empleado → api_empresas.empresas_usuarios.id_usuario (impresor)',
    ],

    'examples' => [
        'Órdenes de un cliente' => "SELECT _id, status, pago_total, fecha_entrega FROM ordenes WHERE cliente_nombre LIKE '%nombre%'",
        'Órdenes listas para entregar' => "SELECT _id, cliente_nombre, fecha_entrega FROM ordenes WHERE status = 'terminada'",
        'Productos de una orden' => "SELECT name, cantidad, talla, tela, precio_unitario FROM ordenes_productos WHERE id_orden = X",
        'Ventas del mes' => "SELECT SUM(pago_total) as total_ventas FROM ordenes WHERE MONTH(moment) = MONTH(CURDATE()) AND YEAR(moment) = YEAR(CURDATE())",
        'Stock bajo' => "SELECT insumo, cantidad, unidad FROM inventario WHERE cantidad < 10",
        'Tareas pendientes de empleado' => "SELECT ld.* FROM lotes_detalles ld WHERE ld.id_empleado = X AND ld.terminado = 0",
        'Lista de empleados activos' => "SELECT id_usuario, nombre, departamento, salario_tipo FROM api_empresas.empresas_usuarios WHERE activo = 1",
        'Órdenes con nombre del vendedor' => "SELECT o._id, o.cliente_nombre, o.pago_total, eu.nombre as vendedor FROM ordenes o JOIN api_empresas.empresas_usuarios eu ON o.responsable = eu.id_usuario",
        'Empleados con comisión variable' => "SELECT id_usuario, nombre, comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE comision_tipo = 'variable' AND activo = 1",
        'Tareas con nombre del empleado asignado' => "SELECT ld.id_orden, ld.departamento, eu.nombre as empleado FROM lotes_detalles ld JOIN api_empresas.empresas_usuarios eu ON ld.id_empleado = eu.id_usuario WHERE ld.terminado = 0",
    ]
];
