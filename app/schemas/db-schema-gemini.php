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

DEFINICIÓN DE ESTADOS DE ÓRDENES:
- 'cancelada': Orden cancelada, no se procesará.
- 'entregada': Orden ya entregada al cliente, proceso terminado.
- 'terminada': Orden lista para entregar, producción finalizada.
- 'activa': Orden en proceso de producción.
- 'En espera': Orden pendiente de iniciar producción.

IMPORTANTE: Cuando el usuario pregunte por 'órdenes en curso', 'órdenes activas', 'órdenes en proceso' o similares, se refiere a órdenes que NO están 'cancelada' ni 'entregada'. Es decir: status IN ('En espera', 'terminada', 'activa') o también: status NOT IN ('cancelada', 'entregada').

NOTA: La fecha actual es " . date('Y-m-d') . ".",


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
            'description' => 'Detalles de tareas por producto en cada lote',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'id_ordenes_productos' => 'FK → ordenes_productos._id',
                'id_empleado' => 'Empleado responsable',
                'id_departamento' => 'FK → departamentos._id',
                'departamento' => 'Nombre del departamento (histórico)',
                'unidades_solicitadas' => 'Unidades a producir',
                'comision' => 'Porcentaje de comisión',
                'progreso' => "'por iniciar', 'en curso', 'terminada'",
                'terminado' => '1 si tarea terminada',
                'fecha_inicio' => 'Inicio del trabajo',
                'fecha_terminado' => 'Fin del trabajo',
            ]
        ],

        'lotes_detalles_empleados_asignados' => [
            'description' => 'Empleados asignados a tareas específicas',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_lotes_detalles' => 'FK → lotes_detalles._id',
                'id_orden' => 'FK → ordenes._id',
                'id_empleado' => 'ID del empleado asignado',
                'porcentaje' => 'Porcentaje de trabajo asignado',
                'fecha_inicio' => 'Inicio del trabajo',
                'fecha_fin' => 'Fin del trabajo',
                'tiempo_total' => 'Tiempo trabajado en segundos',
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
            'description' => 'Departamentos de la empresa',
            'fields' => [
                '_id' => 'ID (PK)',
                'departamento' => 'Nombre del departamento',
                'orden_proceso' => 'Orden en el proceso de fabricación (1=primero)',
                'asignar_numero_de_paso' => 'Si es paso de proceso',
                'enviar_mensaje' => 'Enviar mensaje al cliente al iniciar',
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
            'description' => 'Catálogo de impresoras',
            'fields' => [
                '_id' => 'ID (PK)',
                'nombre' => 'Nombre de la impresora',
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
            'description' => 'Consumo de tintas por impresión',
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
            'description' => 'Métodos de pago utilizados',
            'fields' => [
                '_id' => 'ID (PK)',
                'id_orden' => 'FK → ordenes._id',
                'metodo' => 'Método de pago',
                'monto' => 'Monto',
                'tasa' => 'Tasa de cambio',
                'moneda' => 'Moneda',
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
                'moneda' => "'USD' (dólares), 'VES' (bolívares), etc. Esta es la divisa del efectivo",
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
