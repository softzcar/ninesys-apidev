<?php

require_once __DIR__ . '/GeminiAssistant.php';

/**
 * GeminiChatAssistant - Asistente de chat con respuestas en lenguaje natural
 * 
 * Procesa preguntas del usuario y devuelve respuestas formateadas
 * en lenguaje natural, fáciles de leer.
 * 
 * @package NineSys\AI
 */
class GeminiChatAssistant extends GeminiAssistant
{
    /**
     * Procesa la consulta del usuario y devuelve respuesta en lenguaje natural
     * 
     * @param string $query Pregunta del usuario
     * @return array ['success' => bool, 'response' => string, 'data' => array|null]
     */
    public function processUserQuery(string $query): array
    {
        try {
            // 1. Llamar a Gemini para obtener SQL
            $geminiResponse = $this->callGeminiAPI($query, true);

            if (isset($geminiResponse['error'])) {
                return [
                    'success' => false,
                    'response' => 'Error al procesar la consulta: ' . $geminiResponse['error']
                ];
            }

            // 2. Si Gemini devolvió SQL, ejecutarlo
            if (isset($geminiResponse['data']['sql'])) {
                $sql = $geminiResponse['data']['sql'];

                try {
                    $results = $this->executeSqlQuery($sql);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'response' => 'Error al ejecutar la consulta: ' . $e->getMessage()
                    ];
                }

                // 3. Formatear respuesta en lenguaje natural
                try {
                    $naturalResponse = $this->formatNaturalResponse($results, $query, $geminiResponse['data']);
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'response' => 'Error formateando respuesta en línea ' . $e->getLine() . ': ' . $e->getMessage(),
                        'debug' => [
                            'file' => $e->getFile(),
                            'results_count' => count($results),
                            'gemini_data' => $geminiResponse['data']
                        ]
                    ];
                }

                return [
                    'success' => true,
                    'response' => $naturalResponse,
                    'data' => $results,
                    'sql_generated' => $sql
                ];
            }

            // Si no hay SQL, simplemente devolver el texto de Gemini
            return [
                'success' => true,
                'response' => $geminiResponse['text'] ?? 'No pude procesar tu consulta.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'response' => 'Error inesperado en línea ' . $e->getLine() . ': ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Formatea los resultados en una respuesta natural
     * 
     * @param array $results Resultados de la consulta SQL
     * @param string $originalQuery Pregunta original del usuario
     * @param array $geminiData Datos adicionales de Gemini
     * @return string Respuesta en lenguaje natural
     */
    private function formatNaturalResponse(array $results, string $originalQuery, array $geminiData): string
    {
        if (empty($results)) {
            return "No encontré resultados para tu consulta. Por favor, verifica los datos o reformula tu pregunta.";
        }

        $count = count($results);
        $description = $geminiData['description'] ?? '';

        // Construir respuesta basada en el tipo de datos
        $response = "";

        if ($count === 1) {
            // Respuesta para un solo registro
            $row = $results[0];
            $response = "Encontré 1 resultado:\n\n";
            foreach ($row as $key => $value) {
                $label = $this->formatFieldLabel($key);
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $response .= "• **{$label}**: {$displayValue}\n";
            }
        } else {
            // Respuesta para múltiples registros
            $response = "Encontré **{$count} resultados**:\n\n";

            // Limitar a 10 resultados para la respuesta de chat
            $displayResults = array_slice($results, 0, 10);

            foreach ($displayResults as $index => $row) {
                $response .= ($index + 1) . ". ";
                $parts = [];
                foreach ($row as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $label = $this->formatFieldLabel($key);
                        $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                        $parts[] = "{$label}: {$displayValue}";
                    }
                }
                $response .= implode(" | ", $parts) . "\n";
            }

            if ($count > 10) {
                $response .= "\n... y " . ($count - 10) . " resultados más.";
            }
        }

        if ($description) {
            $response = $description . "\n\n" . $response;
        }

        return $response;
    }

    /**
     * Convierte nombres de campos a etiquetas legibles
     */
    private function formatFieldLabel(string $fieldName): string
    {
        $labels = [
            '_id' => 'ID',
            'id_orden' => 'Orden',
            'cliente_nombre' => 'Cliente',
            'status' => 'Estado',
            'pago_total' => 'Total',
            'pago_abono' => 'Abonado',
            'fecha_entrega' => 'Entrega',
            'fecha_inicio' => 'Inicio',
            'name' => 'Producto',
            'cantidad' => 'Cantidad',
            'talla' => 'Talla',
            'tela' => 'Tela',
            'precio_unitario' => 'Precio Unit.',
            'paso' => 'Paso Actual',
            'departamento' => 'Departamento',
            'insumo' => 'Insumo',
            'rendimiento' => 'Rendimiento',
        ];

        return $labels[$fieldName] ?? ucfirst(str_replace('_', ' ', $fieldName));
    }
}
