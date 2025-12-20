<?php

require_once __DIR__ . '/GeminiAssistant.php';

/**
 * GeminiReportAssistant - Asistente para generación de reportes
 * 
 * Procesa preguntas del usuario y devuelve datos estructurados
 * con formato compatible con bootstrap-vue (fields + items).
 * 
 * @package NineSys\AI
 */
class GeminiReportAssistant extends GeminiAssistant
{
    /**
     * Procesa la consulta del usuario y devuelve datos para tabla
     * 
     * @param string $query Pregunta del usuario
     * @return array ['success' => bool, 'fields' => array, 'items' => array]
     */
    public function processUserQuery(string $query): array
    {
        try {
            // 1. Llamar a Gemini para obtener SQL y estructura de columnas
            $geminiResponse = $this->callGeminiAPI($query, true);

            if (isset($geminiResponse['error'])) {
                return [
                    'success' => false,
                    'error' => 'Error al procesar la consulta: ' . $geminiResponse['error'],
                    'fields' => [],
                    'items' => []
                ];
            }

            // 2. Si Gemini devolvió SQL, ejecutarlo
            if (isset($geminiResponse['data']['sql'])) {
                $sql = $geminiResponse['data']['sql'];
                $columnsInfo = $geminiResponse['data']['columns'] ?? [];

                try {
                    $results = $this->executeSqlQuery($sql);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'error' => 'Error al ejecutar la consulta: ' . $e->getMessage(),
                        'fields' => [],
                        'items' => []
                    ];
                }

                // 3. Generar fields para bootstrap-vue
                $fields = $this->generateTableFields($results, $columnsInfo);

                return [
                    'success' => true,
                    'fields' => $fields,
                    'items' => $results,
                    'total' => count($results),
                    'description' => $geminiResponse['data']['description'] ?? '',
                    'sql_generated' => $sql
                ];
            }

            // Si no hay SQL, devolver error
            return [
                'success' => false,
                'error' => 'No se pudo generar una consulta para tu pregunta',
                'fields' => [],
                'items' => []
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error inesperado: ' . $e->getMessage(),
                'fields' => [],
                'items' => []
            ];
        }
    }

    /**
     * Genera la estructura de fields compatible con bootstrap-vue
     * 
     * @param array $results Resultados de la consulta
     * @param array $columnsInfo Información de columnas de Gemini
     * @return array Estructura de fields para b-table
     */
    private function generateTableFields(array $results, array $columnsInfo): array
    {
        if (empty($results)) {
            return [];
        }

        // Obtener las claves del primer resultado
        $keys = array_keys($results[0]);
        $fields = [];

        // Mapeo de Gemini si existe
        $geminiColumns = [];
        foreach ($columnsInfo as $col) {
            if (isset($col['key'])) {
                $geminiColumns[$col['key']] = $col;
            }
        }

        foreach ($keys as $index => $key) {
            // Usar información de Gemini si está disponible
            if (isset($geminiColumns[$key])) {
                $fields[] = [
                    'key' => $key,
                    'label' => $geminiColumns[$key]['label'] ?? $this->formatLabel($key),
                    'sortable' => $geminiColumns[$key]['sortable'] ?? true
                ];
            } else {
                // Generar automáticamente
                $fields[] = [
                    'key' => $key,
                    'label' => $this->formatLabel($key),
                    'sortable' => true
                ];
            }
        }

        return $fields;
    }

    /**
     * Formatea un nombre de campo como etiqueta legible
     * 
     * @param string $key Nombre del campo
     * @return string Etiqueta formateada
     */
    private function formatLabel(string $key): string
    {
        // Mapeo de campos comunes
        $labels = [
            '_id' => 'ID',
            'id_orden' => 'Orden',
            'id_wp' => 'ID Cliente',
            'cliente_nombre' => 'Cliente',
            'cliente_cedula' => 'Cédula',
            'status' => 'Estado',
            'pago_total' => 'Total',
            'pago_abono' => 'Abonado',
            'pago_descuento' => 'Descuento',
            'fecha_entrega' => 'Fecha Entrega',
            'fecha_inicio' => 'Fecha Inicio',
            'fecha_creacion' => 'Creación',
            'name' => 'Producto',
            'cantidad' => 'Cantidad',
            'talla' => 'Talla',
            'tela' => 'Tela',
            'metros' => 'Metros',
            'precio_unitario' => 'Precio Unit.',
            'paso' => 'Paso Actual',
            'departamento' => 'Departamento',
            'prioridad' => 'Prioridad',
            'piezas_actuales' => 'Piezas',
            'insumo' => 'Insumo',
            'sku' => 'SKU',
            'unidad' => 'Unidad',
            'costo' => 'Costo',
            'rendimiento' => 'Rendimiento',
            'color' => 'Color',
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'phone' => 'Teléfono',
            'email' => 'Email',
            'address' => 'Dirección',
            'product' => 'Producto',
            'price' => 'Precio',
            'moment' => 'Fecha Registro',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        // Formato genérico: snake_case a Title Case
        return ucwords(str_replace('_', ' ', $key));
    }
}
