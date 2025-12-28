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
     * @param array $history Historial de conversación [['role' => 'user'|'model', 'text' => '...'], ...]
     * @return array ['success' => bool, 'response' => string, 'data' => array|null]
     */
    public function processUserQuery(string $query, array $history = []): array
    {
        try {
            // 1. Llamar a Gemini para obtener SQL (con historial)
            $geminiResponse = $this->callGeminiAPI($query, true, $history);

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
                    'sql_generated' => $sql,
                    'debug_info' => $this->getDebugInfo()
                ];
            }

            // 2b. Si Gemini devolvió una acción especial (como create_order), retornar el JSON
            if (isset($geminiResponse['data']['action'])) {
                // Devolver el JSON como texto para que el frontend lo procese
                $jsonResponse = json_encode($geminiResponse['data'], JSON_UNESCAPED_UNICODE);
                return [
                    'success' => true,
                    'response' => $jsonResponse,
                    'is_action' => true,
                    'action' => $geminiResponse['data']['action']
                ];
            }

            // Si no hay SQL ni action, simplemente devolver el texto de Gemini
            // Usar raw_text si text no está disponible
            $responseText = $geminiResponse['text'] ?? $geminiResponse['raw_text'] ?? 'No pude procesar tu consulta.';
            return [
                'success' => true,
                'response' => $responseText
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
     * Procesa consultas de órdenes con contexto de BD
     * 
     * @param string $query Mensaje del usuario
     * @param array $history Historial de conversación
     * @param array $contextoBD Contexto de BD (clientes, productos, tallas, telas encontrados)
     * @param array|null $ordenEnProgreso Estado actual de la orden en progreso
     * @return array Respuesta de Gemini
     */
    public function processOrderQuery(string $query, array $history = [], array $contextoBD = [], ?array $ordenEnProgreso = null): array
    {
        try {
            // Enriquecer el query con contexto de BD
            $queryEnriquecido = $query;

            if (!empty($contextoBD)) {
                $contextoTexto = "\n\n[CONTEXTO DE BASE DE DATOS - USA ESTA INFORMACIÓN PARA TOMAR DECISIONES]\n";

                if (!empty($contextoBD['clientes'])) {
                    $contextoTexto .= "clientes_encontrados: " . json_encode($contextoBD['clientes'], JSON_UNESCAPED_UNICODE) . "\n";
                }
                if (!empty($contextoBD['productos'])) {
                    $contextoTexto .= "productos_encontrados: " . json_encode($contextoBD['productos'], JSON_UNESCAPED_UNICODE) . "\n";
                }
                if (!empty($contextoBD['tallas'])) {
                    $contextoTexto .= "tallas_disponibles: " . json_encode(array_column($contextoBD['tallas'], 'nombre'), JSON_UNESCAPED_UNICODE) . "\n";
                }
                if (!empty($contextoBD['telas'])) {
                    $contextoTexto .= "telas_disponibles: " . json_encode(array_column($contextoBD['telas'], 'nombre'), JSON_UNESCAPED_UNICODE) . "\n";
                }

                $queryEnriquecido = $query . $contextoTexto;
            }

            // Si hay orden en progreso, incluirla
            if (!empty($ordenEnProgreso)) {
                $queryEnriquecido .= "\n[ORDEN EN PROGRESO]: " . json_encode($ordenEnProgreso, JSON_UNESCAPED_UNICODE);
            }

            // Llamar a Gemini con el query enriquecido
            $geminiResponse = $this->callGeminiAPI($queryEnriquecido, false, $history);

            if (isset($geminiResponse['error'])) {
                return [
                    'success' => false,
                    'response' => 'Error al procesar: ' . $geminiResponse['error']
                ];
            }

            // Si Gemini devolvió un JSON de crear orden
            if (isset($geminiResponse['data']['action']) && $geminiResponse['data']['action'] === 'create_order') {
                return [
                    'success' => true,
                    'response' => json_encode($geminiResponse['data'], JSON_UNESCAPED_UNICODE),
                    'is_action' => true,
                    'action' => 'create_order',
                    'ready' => $geminiResponse['data']['ready'] ?? false,
                    'order_data' => $geminiResponse['data']['data'] ?? null
                ];
            }

            // Respuesta en texto natural
            $responseText = $geminiResponse['text'] ?? $geminiResponse['raw_text'] ?? 'No pude procesar tu consulta.';

            // Filtrar SQL de la respuesta (no debe mostrarse al usuario)
            if (preg_match('/```sql/i', $responseText) || preg_match('/\bSELECT\b.*\bFROM\b/i', $responseText)) {
                // Si la respuesta contiene SQL, limpiarla
                $responseText = preg_replace('/```sql[\s\S]*?```/i', '', $responseText);
                $responseText = preg_replace('/SELECT\s+[\s\S]*?;/i', '', $responseText);
                $responseText = trim($responseText);

                // Si quedó vacío, dar una respuesta genérica
                if (empty($responseText)) {
                    $responseText = 'Estoy procesando tu solicitud. ¿Podrías darme más detalles sobre lo que necesitas?';
                }
            }

            return [
                'success' => true,
                'response' => $responseText
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'response' => 'Error inesperado: ' . $e->getMessage()
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

            // Validar que $row sea array/objeto
            if (is_array($row) || is_object($row)) {
                foreach ($row as $key => $value) {
                    $label = $this->formatFieldLabel((string) $key);
                    $displayValue = is_array($value) ? json_encode($value) : (string) ($value ?? '');
                    $response .= "• **{$label}**: {$displayValue}\n";
                }
            } else {
                // Si es un valor escalar
                $response .= "• **Resultado**: " . (string) $row . "\n";
            }
        } else {
            // Respuesta para múltiples registros
            $response = "Encontré **{$count} resultados**:\n\n";

            // Limitar a 10 resultados para la respuesta de chat
            $displayResults = array_slice($results, 0, 10);

            $itemNumber = 1;
            foreach ($displayResults as $row) {
                $response .= $itemNumber . ". ";
                $parts = [];

                // Validar que $row sea array/objeto
                if (is_array($row) || is_object($row)) {
                    foreach ($row as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $label = $this->formatFieldLabel((string) $key);
                            $displayValue = is_array($value) ? json_encode($value) : (string) ($value ?? '');
                            $parts[] = "{$label}: {$displayValue}";
                        }
                    }
                    $response .= implode(" | ", $parts) . "\n";
                } else {
                    // Si es un valor escalar
                    $response .= (string) $row . "\n";
                }
                $itemNumber++;
            }

            if ($count > 10) {
                $remaining = (int) $count - 10;
                $response .= "\n... y " . $remaining . " resultados más.";
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
