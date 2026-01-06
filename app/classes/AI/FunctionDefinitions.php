<?php

namespace App\Classes\AI;

/**
 * Definición de Function Calling para Gemini API
 * 
 * Define las funciones que Gemini puede llamar para obtener datos reales
 * de la base de datos durante el flujo conversacional de órdenes.
 */
class FunctionDefinitions
{
    /**
     * Obtiene las definiciones de funciones para creación de órdenes
     * 
     * @return array Array de funciones con esquemas JSON según especificación de Gemini
     */
    public static function getOrderFunctions(): array
    {
        return [
            // Función 1: Buscar clientes
            [
                'name' => 'buscarClientes',
                'description' => 'Busca clientes en la base de datos por nombre. Usa esta función cuando el usuario mencione un nombre de cliente o quiera crear una orden para alguien. Retorna una lista de clientes que coinciden con la búsqueda.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Nombre o parte del nombre del cliente a buscar. Ejemplo: "José", "María Rodríguez", "Ozcar"'
                        ]
                    ],
                    'required' => ['query']
                ]
            ],

            // Función 2: Buscar productos
            [
                'name' => 'buscarProductos',
                'description' => 'Busca productos en el catálogo por nombre o tipo. Usa esta función cuando el usuario mencione un producto (franela, chemise, gorra, etc.). Retorna lista de productos con ID, nombre y precio.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Nombre o tipo de producto a buscar. Ejemplo: "franela", "chemise", "gorra sublimada"'
                        ]
                    ],
                    'required' => ['query']
                ]
            ],

            // Función 3: Obtener tallas disponibles
            [
                'name' => 'obtenerTallas',
                'description' => 'Obtiene la lista completa de tallas disponibles en el sistema. Usa esta función cuando necesites mostrar opciones de tallas al usuario o validar una talla mencionada.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [] // Force JSON object {}
                ]
            ],

            // Función 4: Obtener telas disponibles
            [
                'name' => 'obtenerTelas',
                'description' => 'Obtiene la lista completa de telas/materiales disponibles en el catálogo. Usa esta función cuando el usuario necesite elegir el tipo de tela para un producto.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [] // Force JSON object {}
                ]
            ],

            // Función 5: Validar orden masiva (múltiples productos)
            [
                'name' => 'validarOrdenMasiva',
                'description' => 'Valida una orden completa con múltiples productos en una sola operación. Usa ESTA función cuando el usuario proporcione 3 o más productos en un mensaje. Esta función valida cliente, productos, telas y tallas de todos los productos de una vez, evitando llamadas individuales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cliente' => [
                            'type' => 'string',
                            'description' => 'Nombre del cliente extraído del mensaje'
                        ],
                        'productos' => [
                            'type' => 'array',
                            'description' => 'Array de productos extraídos del mensaje',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'nombre' => [
                                        'type' => 'string',
                                        'description' => 'Nombre del producto'
                                    ],
                                    'tipo_corte' => [
                                        'type' => 'string',
                                        'description' => 'Tipo de corte: damas, caballeros o niños'
                                    ],
                                    'talla' => [
                                        'type' => 'string',
                                        'description' => 'Talla del producto (XS, S, M, L, XL, etc)'
                                    ],
                                    'cantidad' => [
                                        'type' => 'integer',
                                        'description' => 'Cantidad de unidades'
                                    ],
                                    'tela' => [
                                        'type' => 'string',
                                        'description' => 'Tipo de tela/material'
                                    ]
                                ],
                                'required' => ['nombre']
                            ]
                        ],
                        'descripcion' => [
                            'type' => 'string',
                            'description' => 'Descripción o notas adicionales de la orden (opcional)'
                        ]
                    ],
                    'required' => ['productos']
                ]
            ],

            // Función 6: Crear orden final
            [
                'name' => 'crearOrdenFinal',
                'description' => 'Crea la orden definitiva en la base de datos. Úsala SOLO cuando el usuario haya confirmado que el resumen es correcto y todos los productos tengan ✓. Esta función guarda la orden y retorna el ID de la orden creada.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id_cliente' => [
                            'type' => 'integer',
                            'description' => 'ID del cliente confirmado'
                        ],
                        'productos' => [
                            'type' => 'array',
                            'description' => 'Lista de productos validados con sus IDs finales',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id_producto' => ['type' => 'integer'],
                                    'nombre' => ['type' => 'string'],
                                    'cantidad' => ['type' => 'integer'],
                                    'talla' => ['type' => 'string'],
                                    'corte' => ['type' => 'string'],
                                    'tela' => ['type' => 'string'],
                                    'id_tela' => ['type' => 'integer'],
                                    'precio_unitario' => ['type' => 'number']
                                ],
                                'required' => ['id_producto', 'cantidad', 'talla', 'corte', 'tela', 'precio_unitario']
                            ]
                        ],
                        'observaciones' => [
                            'type' => 'string',
                            'description' => 'Notas u observaciones finales de la orden'
                        ],
                        'fecha_entrega' => [
                            'type' => 'string',
                            'description' => 'Fecha estimada de entrega (YYYY-MM-DD)'
                        ],
                        'pago_abono' => [
                            'type' => 'number',
                            'description' => 'Monto del abono inicial (si se mencionó)'
                        ],
                        'pago_descuento' => [
                            'type' => 'number',
                            'description' => 'Monto del descuento (si se mencionó)'
                        ]
                    ],
                    'required' => ['id_cliente', 'productos']
                ]
            ]
        ];
    }

    /**
     * Valida que los parámetros de una función sean correctos
     * 
     * @param string $functionName Nombre de la función
     * @param array $args Argumentos recibidos
     * @return bool True si son válidos
     */
    public static function validateFunctionArgs(string $functionName, array $args): bool
    {
        switch ($functionName) {
            case 'buscarClientes':
            case 'buscarProductos':
                return isset($args['query']) && is_string($args['query']) && !empty(trim($args['query']));

            case 'obtenerTallas':
            case 'obtenerTelas':
                return true; // No requieren parámetros

            case 'validarOrdenMasiva':
                return isset($args['productos']) && is_array($args['productos']) && !empty($args['productos']);

            case 'crearOrdenFinal':
                return isset($args['id_cliente']) && isset($args['productos']) && is_array($args['productos']);

            default:
                return false;
        }
    }

    /**
     * Obtiene solo las funciones necesarias para validación batch
     * Usado en la primera iteración de órdenes grandes para forzar el uso de validarOrdenMasiva
     * 
     * @return array Array con buscarClientes y validarOrdenMasiva solamente
     */
    public static function getBatchValidationFunctions(): array
    {
        $allFunctions = self::getOrderFunctions();

        // Filtrar solo buscarClientes (índice 0) y validarOrdenMasiva (índice 4)
        return [
            $allFunctions[0],  // buscarClientes
            $allFunctions[4]   // validarOrdenMasiva  
        ];
    }

    /**
     * Obtiene solo las funciones esenciales para el flujo rápido de órdenes
     * FLUJO OPTIMIZADO: buscarClientes -> validarOrdenMasiva -> crearOrdenFinal
     * Sin validaciones individuales para maximizar velocidad
     * 
     * @return array Array con las 3 funciones esenciales solamente
     */
    public static function getSimplifiedOrderFunctions(): array
    {
        $allFunctions = self::getOrderFunctions();

        // Solo las funciones críticas: buscarClientes (0), validarOrdenMasiva (4), crearOrdenFinal (5)
        return [
            $allFunctions[0],  // buscarClientes
            $allFunctions[4],  // validarOrdenMasiva
            $allFunctions[5]   // crearOrdenFinal
        ];
    }
}
