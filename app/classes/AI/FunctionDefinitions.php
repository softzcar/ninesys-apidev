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

            default:
                return false;
        }
    }
}
