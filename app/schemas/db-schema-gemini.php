<?php

/**
 * Schema de la Base de Datos para el Asistente IA Gemini
 * 
 * Este archivo define la estructura de las tablas que Gemini puede consultar.
 * Las descripciones provienen de los comentarios reales de la BD empresa 163.
 * 
 * @package NineSys\AI
 */

return [
    'prompt_base' => "Eres un asistente de consultas para una empresa de confección y manufactura textil llamada Nineteen Custom.
Tu objetivo es responder preguntas sobre órdenes de producción, productos, inventario, clientes y estado de fabricación.
Debes generar consultas SQL SELECT basándote en las tablas disponibles.
Siempre responde en español de forma clara y profesional.",

    'tables' => [
        'ordenes' => [
            'description' => 'Órdenes de producción de la empresa',
            'fields' => [
                '_id' => 'ID único de la orden (clave primaria)',
                'id_wp' => 'ID del cliente (FK → customers._id)',
                'status' => "Estado de la orden: 'entregada', 'cancelada', 'terminada', 'En espera'",
                'tipo' => "Tipo de orden: 'custom' (personalizada) o 'catalogo' (de catálogo)",
                'responsable' => 'ID del vendedor responsable',
                'cliente_nombre' => 'Nombre completo del cliente',
                'cliente_cedula' => 'Cédula o documento del cliente',
                'fecha_inicio' => 'Fecha de inicio de producción',
                'fecha_entrega' => 'Fecha prometida de entrega al cliente',
                'pago_descuento' => 'Monto de descuento aplicado a la orden',
                'pago_total' => 'Monto total de la orden en USD',
                'pago_abono' => 'Monto abonado por el cliente',
                'pago_comision' => "Estado de la comisión: 'pendiente' o 'pagado'",
            ]
        ],

        'ordenes_productos' => [
            'description' => 'Productos que conforman cada orden de producción',
            'fields' => [
                '_id' => 'ID del registro',
                'id_orden' => 'FK → ordenes._id (a qué orden pertenece)',
                'id_woo' => 'ID del producto en el catálogo (FK → products._id)',
                'id_tela' => 'ID de la tela a utilizar (FK → catalogo_telas)',
                'name' => 'Nombre del producto',
                'cantidad' => 'Cantidad de unidades a producir',
                'talla' => 'Talla del producto (S, M, L, XL, etc.)',
                'corte' => "Tipo de corte: 'Dama', 'Caballero', 'Niño'",
                'metros' => 'Metros de tela a utilizar para este producto',
                'desperdicio' => 'Metros de tela desperdiciados',
                'tela' => 'Nombre de la tela principal seleccionada',
                'precio_unitario' => 'Precio por unidad del producto',
            ]
        ],

        'lotes' => [
            'description' => 'Lotes de fabricación - representa el estado actual de producción de cada orden',
            'fields' => [
                '_id' => 'ID del lote',
                'lote' => 'Código del lote de producción',
                'id_orden' => 'FK → ordenes._id',
                'id_departamento_actual' => 'FK → departamentos._id (en qué departamento está actualmente)',
                'prioridad' => '0 = Normal, 1 = Urgente',
                'piezas_actuales' => 'Cantidad de piezas en proceso actualmente',
                'paso' => "Paso actual del proceso: 'Corte', 'Impresión', 'Estampado', 'Costura', 'Limpieza', 'terminado'",
            ]
        ],

        'departamentos' => [
            'description' => 'Departamentos de la empresa que participan en el proceso de fabricación',
            'fields' => [
                '_id' => 'ID del departamento',
                'departamento' => 'Nombre del departamento',
                'orden_proceso' => 'Número que indica el orden en el proceso de fabricación (1=primero, mayor=después)',
            ]
        ],

        'inventario' => [
            'description' => 'Stock de insumos y materiales de la empresa',
            'fields' => [
                '_id' => 'ID único del ítem de inventario',
                'sku' => 'Código SKU del insumo',
                'id_catalogo' => 'FK → catalogo_insumos_productos',
                'insumo' => 'Nombre del insumo o material',
                'unidad' => "Unidad de medida: 'Kilos', 'LTS', 'ML', 'UND', 'Metros'",
                'costo' => 'Precio de costo unitario',
                'rendimiento' => 'Rendimiento del material (metros por kilo para telas)',
                'cantidad' => 'Cantidad actual en stock',
                'color' => 'Color del insumo',
                'departamento' => 'Departamento al que pertenece el insumo',
            ]
        ],

        'customers' => [
            'description' => 'Clientes de la empresa',
            'fields' => [
                '_id' => 'ID único del cliente',
                'first_name' => 'Nombre del cliente',
                'last_name' => 'Apellido del cliente',
                'cedula' => 'Cédula o documento de identidad',
                'address' => 'Dirección del cliente',
                'phone' => 'Teléfono del cliente',
                'email' => 'Correo electrónico',
            ]
        ],

        'products' => [
            'description' => 'Catálogo de productos de la empresa',
            'fields' => [
                '_id' => 'ID único del producto',
                'product' => 'Nombre del producto',
                'sku' => 'Código SKU',
                'fisico' => '1 = producto físico, 0 = producto virtual/digital',
                'es_diseno' => '1 = pertenece al departamento de diseño',
                'price' => 'Precio del producto',
                'comision' => 'Monto para cálculo de comisión variable',
                'stock_quantity' => 'Cantidad en existencia',
            ]
        ],

        'catalogo_telas' => [
            'description' => 'Catálogo de telas disponibles',
            'fields' => [
                '_id' => 'ID único de la tela',
                'tela' => 'Nombre de la tela',
            ]
        ],
    ],

    'relations' => [
        'ordenes.id_wp → customers._id (cliente de la orden)',
        'ordenes_productos.id_orden → ordenes._id (productos de cada orden)',
        'ordenes_productos.id_woo → products._id (referencia al catálogo)',
        'ordenes_productos.id_tela → catalogo_telas._id (tela asignada)',
        'lotes.id_orden → ordenes._id (lote de cada orden)',
        'lotes.id_departamento_actual → departamentos._id (departamento actual)',
    ],

    'examples' => [
        'Órdenes de un cliente' => "SELECT _id, status, pago_total, fecha_entrega FROM ordenes WHERE cliente_nombre LIKE '%nombre%'",
        'Órdenes listas para entregar' => "SELECT _id, cliente_nombre, fecha_entrega FROM ordenes WHERE status = 'terminada'",
        'Productos de una orden' => "SELECT name, cantidad, talla, tela, precio_unitario FROM ordenes_productos WHERE id_orden = X",
        'Eficiencia de una tela' => "SELECT sku, insumo, rendimiento, cantidad FROM inventario WHERE insumo LIKE '%nombre_tela%'",
        'Estado de producción de una orden' => "SELECT o._id, o.status, l.paso, d.departamento FROM ordenes o LEFT JOIN lotes l ON o._id = l.id_orden LEFT JOIN departamentos d ON l.id_departamento_actual = d._id WHERE o.cliente_nombre LIKE '%nombre%'",
        'Deuda de una orden' => "SELECT _id, cliente_nombre, pago_total, pago_abono, pago_descuento, (pago_total - pago_descuento - pago_abono) as deuda FROM ordenes WHERE _id = X",
    ]
];
