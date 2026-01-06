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

            // 2b. Si Gemini devolvió una acción especial (como create_order), retornar el objeto
            if (isset($geminiResponse['data']['action'])) {
                // ✅ Devolver el OBJETO, no el JSON string
                // El endpoint que llama se encargará de hacer json_encode final
                return [
                    'success' => true,
                    'response' => $geminiResponse['data'],  // ✅ Objeto, NO string
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
    /**
     * Procesa consultas de órdenes usando FUNCTION CALLING
     * 
     * @param string $query Mensaje del usuario
     * @param array $history Historial de conversación
     * @param array|null $ordenEnProgreso Estado actual de la orden en progreso
     * @return array Respuesta de Gemini
     */
    public function processOrderQueryWithFunctions(string $query, array $history = [], ?array $ordenEnProgreso = null): array
    {
        require_once __DIR__ . '/FunctionDefinitions.php';

        try {
            // Inicializar historial para esta sesión de proceso
            $currentHistory = $history;
            $userQuery = $query;

            // Si hay orden en progreso, agregarla al contexto inicial (solo texto, sin enviar tools la primera vez si no es necesario, pero mejor siempre)
            if (!empty($ordenEnProgreso)) {
                $userQuery .= "\n[ORDEN EN PROGRESO]: " . json_encode($ordenEnProgreso, JSON_UNESCAPED_UNICODE);
            }

            // Obtener definiciones de funciones
            $tools = \App\Classes\AI\FunctionDefinitions::getOrderFunctions();

            // Límite de iteraciones para evitar bucles infinitos
            $maxIterations = 5;
            $iteration = 0;

            while ($iteration < $maxIterations) {
                $iteration++;

                // Llamar a Gemini enviando tools
                // Nota: requestSQL=false porque usamos FC nativo
                // Usamos el prompt específico para órdenes conversacionales
                $geminiResponse = $this->callGeminiAPI($userQuery, false, $currentHistory, $tools, $this->dbSchema['prompt_ordenes'] ?? null);

                if (isset($geminiResponse['error'])) {
                    return [
                        'success' => false,
                        'response' => 'Error al procesar: ' . $geminiResponse['error']
                    ];
                }

                // CASO 1: Gemini quiere llamar a una FUNCIÓN
                if (isset($geminiResponse['is_function_call']) && $geminiResponse['is_function_call']) {
                    $functionName = $geminiResponse['function_name'];
                    $functionArgs = $geminiResponse['function_args'];

                    // 0. Si es la primera iteración, agregar el mensaje del usuario al historial
                    // para mantener la secuencia lógica User -> Model -> Function
                    // PERO verificar que no esté ya en el historial (si el frontend lo envió)
                    $lastMsg = !empty($currentHistory) ? end($currentHistory) : null;
                    $lastText = $lastMsg['parts'][0]['text'] ?? '';

                    // Limpiar metadatos del query para comparación
                    $cleanQuery = preg_replace('/\n\[(ORDEN EN PROGRESO|CONTEXTO BD)\]:.*$/s', '', $userQuery);

                    // Normalización profunda para comparación (quitar espacios, lower case, saltos de línea)
                    $normLast = preg_replace('/\s+/', ' ', strtolower(trim($lastText)));
                    $normQuery = preg_replace('/\s+/', ' ', strtolower(trim($cleanQuery)));

                    $isAlreadyInHistory = $lastMsg &&
                        ($lastMsg['role'] === 'user') &&
                        ($normLast === $normQuery);

                    if ($iteration === 1 && !empty($userQuery) && !$isAlreadyInHistory) {
                        $currentHistory[] = [
                            'role' => 'user',
                            'parts' => [['text' => $userQuery]]
                        ];
                    }

                    // 1. Agregar la intención de llamada del modelo al historial
                    $currentHistory[] = [
                        'role' => 'model',
                        'parts' => [
                            [
                                'functionCall' => [
                                    'name' => $functionName,
                                    'args' => empty($functionArgs) ? (object) [] : $functionArgs
                                ]
                            ]
                        ]
                    ];

                    // 2. Ejecutar la función localmente
                    // Usamos $this->dbConnection que ya está configurada
                    $functionResult = $this->callFunctionHandler($functionName, $functionArgs);

                    // 3. Agregar el resultado de la función al historial (como 'functionResponse')
                    $currentHistory[] = [
                        'role' => 'function',
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $functionName,
                                    'response' => [
                                        'name' => $functionName,
                                        'content' => $functionResult
                                    ]
                                ]
                            ]
                        ]
                    ];

                    // PREPARAR SIGUIENTE ITERACIÓN:
                    // El userQuery ya está en el historial (o fue procesado), 
                    // para la siguiente llamada enviamos vacío o continuamos la conversación.
                    // En la API de Gemini, se envía el historial actualizado y un nuevo prompt o continuación.
                    // Para simplificar, en la siguiente vuelta el prompt puede ser vacío o una instrucción de "continua".
                    // Pero callGeminiAPI agrega el userQuery al final.
                    // Estrategia: El userQuery original YA SE PROCESÓ en la primera vuelta.
                    // En las siguientes vueltas, el 'userQuery' debería ser null o vacío para que solo se procese el historial,
                    // PERO callGeminiAPI estructura el payload esperando un texto de usuario al final.
                    // MODIFICACIÓN NECESARIA EN callGeminiAPI para soportar query vacío o manejarlo aquí.
                    // Por ahora, pasaremos un string vacío y modificaremos callGeminiAPI si es necesario, 
                    // o pasaremos una instrucción de sistema oculta.
                    $userQuery = ""; // Ya no enviamos el query original repetido
                    $augmentedQuery = ""; // CRÍTICO: También limpiar la query aumentada para evitar duplicación

                    continue; // Siguiente iteración del bucle
                }

                // CASO 2: Respuesta final (Texto o Acción Create Order)

                // Si es acción de crear orden
                if (isset($geminiResponse['data']['action']) && $geminiResponse['data']['action'] === 'create_order') {
                    return [
                        'success' => true,
                        'response' => $geminiResponse['data'],
                        'is_action' => true,
                        'action' => 'create_order',
                        'ready' => $geminiResponse['data']['ready'] ?? false,
                        'order_data' => $geminiResponse['data']['data'] ?? null
                    ];
                }

                // Respuesta de texto normal
                $responseText = $geminiResponse['text'] ?? $geminiResponse['raw_text'] ?? 'No pude procesar tu consulta.';

                // Filtrar bloques internos si quedaran (aunque con FC es menos probable)
                $responseText = preg_replace('/\[CONTEXTO DE BASE DE DATOS.*?\]/s', '', $responseText);

                return [
                    'success' => true,
                    'response' => $responseText
                ];
            }

            return [
                'success' => false,
                'response' => 'Se excedió el límite de iteraciones (demasiadas llamadas a funciones).'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'response' => 'Error inesperado: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Procesa consultas de órdenes (MÉTODO LEGACY - DEPRECADO)
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
                    'response' => $geminiResponse['data'],  // ✅ Devolver objeto, NO string JSON
                    'is_action' => true,
                    'action' => 'create_order',
                    'ready' => $geminiResponse['data']['ready'] ?? false,
                    'order_data' => $geminiResponse['data']['data'] ?? null
                ];
            }

            // Respuesta en texto natural
            $responseText = $geminiResponse['text'] ?? $geminiResponse['raw_text'] ?? 'No pude procesar tu consulta.';

            // ============================================================
            // FILTRAR CÓDIGO DE LA RESPUESTA (SEGURIDAD + UX)
            // ============================================================
            // Gemini a veces devuelve código SQL, JSON o ejemplos que no deben mostrarse al usuario

            // 1. Detectar bloques de código markdown ```lenguaje...```
            $tieneCodigoMarkdown = preg_match('/```/s', $responseText);

            // 2. Detectar SQL plano
            $tieneSQLPlano = preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\s+(FROM|INTO|SET)\b/is', $responseText);

            // 3. Detectar JSON estructurado (objetos grandes con múltiples campos)
            $tieneJSONEstructurado = preg_match('/\{\s*"[\w_]+"\s*:.*"[\w_]+"\s*:/s', $responseText);

            // 4. Detectar bloque de CONTEXTO DE BASE DE DATOS (NO mostrar al usuario)
            $tieneContextoBD = preg_match('/\[CONTEXTO DE BASE DE DATOS/i', $responseText);

            if ($tieneCodigoMarkdown || $tieneSQLPlano || $tieneJSONEstructurado || $tieneContextoBD) {
                $textoOriginal = $responseText;

                // ESTRATEGIA 1: Limpiar bloques markdown completos (```...```)
                // Usa modificador 's' para DOTALL (. incluye \n)
                $responseText = preg_replace('/```[a-z]*.*?```/s', '', $responseText);

                // ESTRATEGIA 2: Limpiar SQL plano  
                $responseText = preg_replace('/(SELECT|INSERT|UPDATE|DELETE)\s+.+?;/is', '', $responseText);

                // ESTRATEGIA 3: Limpiar objetos JSON grandes
                // Solo si tiene estructura compleja (múltiples líneas con campos)
                $responseText = preg_replace('/\{[^}]{150,}\}/s', '', $responseText);

                // ESTRATEGIA 4: Limpiar bloque de CONTEXTO DE BASE DE DATOS completo
                // Desde [CONTEXTO... hasta [ORDEN EN PROGRESO] o final de texto
                $responseText = preg_replace('/\[CONTEXTO DE BASE DE DATOS.*?\[ORDEN EN PROGRESO\]:[^\]]*\]/is', '', $responseText);
                // Si no hay ORDEN EN PROGRESO, limpiar hasta el final
                $responseText = preg_replace('/\[CONTEXTO DE BASE DE DATOS.*$/is', '', $responseText);

                // ESTRATEGIA 4: Limpiar líneas que empiezan con estructura de objeto
                $lineas = explode("\n", $responseText);
                $lineasLimpias = array_filter($lineas, function ($linea) {
                    // Eliminar líneas que parecen definiciones de objetos/arrays
                    return !preg_match('/^\s*[\{\}\[\]]?\s*$/', $linea) &&
                        !preg_match('/^\s*"[\w_]+"\s*:\s*/', $linea);
                });
                $responseText = implode("\n", $lineasLimpias);

                $responseText = trim($responseText);

                // Si quedó vacío o muy corto, extraer solo texto conversacional del original
                if (empty($responseText) || strlen($responseText) < 30) {
                    // Extracción manual línea por línea
                    $lineas = explode("\n", $textoOriginal);
                    $textoNatural = [];
                    $dentroBloque = false;

                    foreach ($lineas as $linea) {
                        // Detectar inicio/fin de bloque markdown
                        if (strpos($linea, '```') !== false) {
                            $dentroBloque = !$dentroBloque;
                            continue;
                        }

                        // Si NO está dentro de un bloque Y la línea parece texto natural
                        if (!$dentroBloque) {
                            $esTextoNatural = !preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|\{|\}|\[|\]|"[\w_]+":)/i', trim($linea));
                            $noEsVacia = strlen(trim($linea)) > 0;

                            if ($esTextoNatural && $noEsVacia) {
                                $textoNatural[] = $linea;
                            }
                        }
                    }

                    $responseText = trim(implode("\n", $textoNatural));

                    // Si TODAVÍA está vacío, dar mensaje genérico
                    if (empty($responseText) || strlen($responseText) < 10) {
                        $responseText = 'He procesado tu solicitud. ¿Podrías confirmarme algunos detalles para continuar?';
                    }
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

    /**
     * Ejecuta funciones llamadas por Gemini (Function Calling)
     * 
     * @param string $functionName Nombre de la función a ejecutar
     * @param array $args Argumentos de la función
     * @return array Resultado de la función ejecutada
     */
    public function callFunctionHandler(string $functionName, array $args): array
    {
        require_once __DIR__ . '/FunctionDefinitions.php';

        // Validar argumentos
        if (!\App\Classes\AI\FunctionDefinitions::validateFunctionArgs($functionName, $args)) {
            return [
                'error' => "Argumentos inválidos para función {$functionName}"
            ];
        }

        // Usar la conexión existente
        $db = $this->dbConnection;

        // DEBUG
        file_put_contents('/tmp/gemini_debug_v2.log', date('[Y-m-d H:i:s] ') . "Calling function handler: $functionName\n", FILE_APPEND);

        try {
            switch ($functionName) {
                case 'buscarClientes':
                    return $this->handleBuscarClientes($db, $args['query']);

                case 'buscarProductos':
                    return $this->handleBuscarProductos($db, $args['query']);

                case 'obtenerTallas':
                    return $this->handleObtenerTallas($db);

                case 'obtenerTelas':
                    return $this->handleObtenerTelas($db);

                case 'validarOrdenMasiva':
                    return $this->handleValidarOrdenMasiva($db, $args);

                case 'crearOrdenFinal':
                    return $this->handleCrearOrdenFinal($db, $args);

                default:
                    return ['error' => "Función desconocida: {$functionName}"];
            }
        } catch (\Throwable $e) {
            error_log("Error en callFunctionHandler ({$functionName}): " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Handler: Buscar clientes por nombre
     */
    private function handleBuscarClientes($db, string $query): array
    {
        $nombre = trim($query);
        $sql = "SELECT _id as id, 
                       CONCAT(first_name, ' ', IFNULL(last_name, '')) as nombre_completo,
                       cedula,
                       phone as telefono
                FROM customers 
                WHERE first_name LIKE ? 
                   OR last_name LIKE ? 
                   OR CONCAT(first_name, ' ', IFNULL(last_name, '')) LIKE ?
                ORDER BY first_name ASC
                LIMIT 10";

        $clientes = $db->goQuery($sql, ["%{$nombre}%", "%{$nombre}%", "%{$nombre}%"]);

        if (!empty($clientes) && !isset($clientes['status'])) {
            return ['clientes' => $clientes];
        }

        return ['clientes' => []];
    }

    /**
     * Handler: Buscar productos por nombre
     */
    private function handleBuscarProductos($db, string $query): array
    {
        $originalQuery = $query;
        // Palabras a ignorar para mejorar coincidencia
        $stopwords = ['disponibles', 'disponible', 'precio', 'busco', 'quiero', 'necesito', 'las', 'los', 'el', 'la', 'en', 'de', 'para', 'con', 'que', 'tenga'];

        // Limpiar y tokenizar
        $queryClean = strtolower(trim($query));
        $words = preg_split('/\s+/', $queryClean);
        $keywords = array_diff($words, $stopwords);

        // Si nos quedamos sin palabras (ej. solo buscó "disponibles"), usar el original
        if (empty($keywords)) {
            $keywords = $words;
        }

        $conditions = [];
        $params = [];

        foreach ($keywords as $word) {
            // Singularización básica (quita 'es' o 's' al final)
            // Solo si la palabra es suficientemente larga para evitar falsos positivos con 's'
            $wordBase = $word;
            if (strlen($word) > 3) {
                $wordBase = preg_replace('/(es|s)$/i', '', $word);
            }

            // Construir condición
            $conditions[] = "p.product LIKE ?";
            $params[] = "%{$wordBase}%";
        }

        // Fallback robusto por si acaso
        if (empty($conditions)) {
            $conditions[] = "p.product LIKE ?";
            $params[] = "%" . trim($query) . "%";
        }

        $sqlWhere = implode(' AND ', $conditions);

        // DEBUG:
        file_put_contents('/tmp/gemini_product_search.log', "Original: '$originalQuery' | Where: $sqlWhere | Params: " . json_encode($params) . "\n", FILE_APPEND);

        $sql = "SELECT p._id as id, p.product as nombre, 
                       COALESCE(pp.price, p.price) as precio
                FROM products p
                LEFT JOIN products_prices pp ON pp.id_product = p._id
                WHERE $sqlWhere
                GROUP BY p._id
                ORDER BY p.product ASC
                LIMIT 50";

        $productos = $db->goQuery($sql, $params);

        if (!empty($productos) && !isset($productos['status'])) {
            return ['productos' => $productos];
        }

        return ['productos' => []];
    }

    /**
     * Handler: Obtener todas las tallas
     */
    private function handleObtenerTallas($db): array
    {
        $sql = "SELECT _id as id, size as nombre 
                FROM sizes 
                WHERE status = 1 
                ORDER BY _id ASC";

        $tallas = $db->goQuery($sql, []);

        if (!empty($tallas) && !isset($tallas['status'])) {
            return ['tallas' => $tallas];
        }

        return ['tallas' => []];
    }

    /**
     * Handler: Obtener todas las telas
     */
    private function handleObtenerTelas($db): array
    {
        // DEBUG: Loguear conexión y ejecución
        try {
            $dbNameQuery = $db->goQuery("SELECT DATABASE() as db", []);
            $currentDb = $dbNameQuery[0]['db'] ?? 'unknown';
            $logMsg = date('[Y-m-d H:i:s] ') . "handleObtenerTelas called using DB: $currentDb\n";
            file_put_contents('/tmp/gemini_debug_v2.log', $logMsg, FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents('/tmp/gemini_debug_v2.log', "Error logging DB: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        $sql = "SELECT tela as nombre 
                FROM catalogo_telas 
                ORDER BY tela ASC";

        $telas = $db->goQuery($sql, []);

        // DEBUG RESULTS
        $count = count($telas);
        file_put_contents('/tmp/gemini_debug_v2.log', date('[Y-m-d H:i:s] ') . "Query result count: $count\n", FILE_APPEND);

        if (!empty($telas) && !isset($telas['status'])) {
            // DEVOLVER OBJETOS COMPLETOS (no aplanar con array_column)
            // Esto genera [{"nombre": "ALGODON"}, ...] que es más fácil de entender para Gemini
            // que un array plano ["ALGODON", ...]
            return ['telas' => $telas];
        }

        return ['telas' => []];
    }

    /**
     * Handler: Validar orden masiva con múltiples productos
     * Procesa toda la orden en una sola operación para evitar límite de iteraciones
     */
    private function handleValidarOrdenMasiva($db, array $ordenData): array
    {
        $resultado = [
            'cliente' => null,
            'productos' => [],
            'errores' => [],
            'correctos' => 0,
            'con_problemas' => 0
        ];

        // 1. VALIDAR CLIENTE
        if (isset($ordenData['cliente'])) {
            $clienteNombre = $ordenData['cliente'];
            $sql = "SELECT _id as id, 
                           CONCAT(first_name, ' ', IFNULL(last_name, '')) as nombre_completo,
                           cedula,
                           phone as telefono
                    FROM customers 
                    WHERE first_name LIKE ? 
                       OR last_name LIKE ? 
                       OR CONCAT(first_name, ' ', IFNULL(last_name, '')) LIKE ?
                    LIMIT 5";

            $clientes = $db->goQuery($sql, ["%{$clienteNombre}%", "%{$clienteNombre}%", "%{$clienteNombre}%"]);

            if (!empty($clientes) && !isset($clientes['status'])) {
                if (count($clientes) === 1) {
                    $resultado['cliente'] = [
                        'encontrado' => true,
                        'datos' => $clientes[0],
                        'multiple' => false
                    ];
                } else {
                    $resultado['cliente'] = [
                        'encontrado' => true,
                        'datos' => $clientes,
                        'multiple' => true
                    ];
                }
            } else {
                $resultado['cliente'] = [
                    'encontrado' => false,
                    'nombre_buscado' => $clienteNombre
                ];
                $resultado['errores'][] = "Cliente '{$clienteNombre}' no encontrado";
            }
        }

        // 2. VALIDAR CADA PRODUCTO
        if (isset($ordenData['productos']) && is_array($ordenData['productos'])) {
            foreach ($ordenData['productos'] as $index => $prod) {
                $prodValidado = [
                    'indice' => $index + 1,
                    'original' => $prod,
                    'validacion' => [
                        'producto' => null,
                        'tela' => null,
                        'talla' => null
                    ],
                    'tiene_errores' => false,
                    'errores' => []
                ];

                // Validar producto
                if (isset($prod['nombre'])) {
                    $nombreProd = trim($prod['nombre']);
                    $sql = "SELECT p._id as id, p.product as nombre, 
                                   COALESCE(pp.price, p.price) as precio
                            FROM products p
                            LEFT JOIN products_prices pp ON pp.id_product = p._id
                            WHERE p.product LIKE ?
                            GROUP BY p._id
                            LIMIT 5";

                    $productos = $db->goQuery($sql, ["%{$nombreProd}%"]);

                    if (!empty($productos) && !isset($productos['status'])) {
                        $prodValidado['validacion']['producto'] = [
                            'encontrado' => true,
                            'resultados' => $productos,
                            'multiple' => count($productos) > 1
                        ];
                        if (count($productos) > 1) {
                            $prodValidado['errores'][] = "Producto '{$nombreProd}' tiene múltiples coincidencias";
                            $prodValidado['tiene_errores'] = true;
                        }
                    } else {
                        $prodValidado['validacion']['producto'] = [
                            'encontrado' => false,
                            'nombre_buscado' => $nombreProd
                        ];
                        $prodValidado['errores'][] = "Producto '{$nombreProd}' no encontrado";
                        $prodValidado['tiene_errores'] = true;
                    }
                }

                // Validar tela
                if (isset($prod['tela'])) {
                    $telaNombre = trim($prod['tela']);
                    $sql = "SELECT tela as nombre FROM catalogo_telas WHERE LOWER(tela) LIKE LOWER(?)";
                    $telas = $db->goQuery($sql, ["%{$telaNombre}%"]);

                    if (!empty($telas) && !isset($telas['status'])) {
                        $prodValidado['validacion']['tela'] = [
                            'encontrada' => true,
                            'resultados' => $telas,
                            'multiple' => count($telas) > 1
                        ];
                        if (count($telas) > 1) {
                            $prodValidado['errores'][] = "Tela '{$telaNombre}' tiene múltiples coincidencias";
                            $prodValidado['tiene_errores'] = true;
                        }
                    } else {
                        $prodValidado['validacion']['tela'] = [
                            'encontrada' => false,
                            'nombre_buscado' => $telaNombre
                        ];
                        $prodValidado['errores'][] = "Tela '{$telaNombre}' no encontrada";
                        $prodValidado['tiene_errores'] = true;
                    }
                } else {
                    $prodValidado['errores'][] = "Falta especificar la tela";
                    $prodValidado['tiene_errores'] = true;
                }

                // Validar talla (si existe)
                if (!isset($prod['talla'])) {
                    $prodValidado['errores'][] = "Falta especificar la talla";
                    $prodValidado['tiene_errores'] = true;
                }

                // Validar corte (si existe)
                if (!isset($prod['tipo_corte'])) {
                    $prodValidado['errores'][] = "Falta especificar el tipo de corte (damas/caballeros/niños)";
                    $prodValidado['tiene_errores'] = true;
                }

                // Validar cantidad (si existe)
                if (!isset($prod['cantidad'])) {
                    $prodValidado['errores'][] = "Falta especificar la cantidad";
                    $prodValidado['tiene_errores'] = true;
                }

                // Contabilizar
                if ($prodValidado['tiene_errores']) {
                    $resultado['con_problemas']++;
                } else {
                    $resultado['correctos']++;
                }

                $resultado['productos'][] = $prodValidado;
            }
        }

        return $resultado;
    }

    /**
     * Handler: Crear orden final en la base de datos
     */
    private function handleCrearOrdenFinal($db, array $args): array
    {
        try {
            require_once __DIR__ . '/../../model/CustomTime.php';
            $time = new \CustomTime();
            $now = $time->today();
            $today = date('Y-m-d');

            $idCliente = $args['id_cliente'];
            $productos = $args['productos'];
            $observaciones = $args['observaciones'] ?? '';
            $fechaEntrega = $args['fecha_entrega'] ?? date('Y-m-d', strtotime('+7 days'));
            $descuento = $args['pago_descuento'] ?? 0;
            $abono = $args['pago_abono'] ?? 0;

            // 1. Obtener nombre del cliente para denuncias
            $clienteData = $db->goQuery("SELECT CONCAT(first_name, ' ', IFNULL(last_name, '')) as nombre, cedula FROM customers WHERE _id = ?", [$idCliente]);
            $clienteNombre = !empty($clienteData) ? $clienteData[0]['nombre'] : 'Cliente Desconocido';
            $clienteCedula = !empty($clienteData) ? $clienteData[0]['cedula'] : '';

            // 2. Calcular total
            $total = 0;
            foreach ($productos as $p) {
                $total += ($p['precio_unitario'] * $p['cantidad']);
            }

            // 3. Insertar Orden (responsable hardcodeado 1 como demo si no se provee)
            // En un sistema real, el ID del responsable vendría de la sesión del usuario
            $idResponsable = 1;

            $sqlOrden = "INSERT INTO ordenes (
                responsable, moment, pago_descuento, pago_abono, 
                cliente_cedula, observaciones, pago_total, cliente_nombre, 
                fecha_inicio, fecha_entrega, fecha_creacion, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $paramsOrden = [
                $idResponsable,
                $now,
                $descuento,
                $abono,
                $clienteCedula,
                $observaciones,
                $total,
                $clienteNombre,
                $today,
                $fechaEntrega,
                $today,
                'En espera'
            ];

            $db->goQuery($sqlOrden, $paramsOrden);

            // 4. Obtener ID de la orden creada
            $last = $db->goQuery('SELECT MAX(_id) as id FROM ordenes');
            $idOrden = intval($last[0]['id']);

            // 5. Insertar Productos
            foreach ($productos as $p) {
                $sqlProd = "INSERT INTO ordenes_productos (
                    moment, precio_unitario, name, id_orden, 
                    cantidad, talla, corte, tela
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $paramsProd = [
                    $now,
                    $p['precio_unitario'] ?? 0,
                    $p['nombre'] ?? $p['product'] ?? 'Producto Desconocido',
                    $idOrden,
                    $p['cantidad'] ?? 0,
                    $p['talla'] ?? 'N/A',
                    $p['corte'] ?? 'N/A',
                    $p['tela'] ?? 'N/A'
                ];

                $db->goQuery($sqlProd, $paramsProd);
            }

            // 6. Generar resumen textual para ordenes_observaciones (Detalle de la Orden)
            $detalleTexto = "Resumen de Orden para {$clienteNombre}:\n";
            foreach ($productos as $p) {
                $nombre = $p['nombre'] ?? $p['product'] ?? 'Producto';
                $corte = $p['corte'] ?? 'N/A';
                $talla = $p['talla'] ?? 'N/A';
                $tela = $p['tela'] ?? 'N/A';
                $cant = $p['cantidad'] ?? 0;
                $detalleTexto .= "- {$cant} {$nombre} ({$corte}, {$talla}) - Tela: {$tela}\n";
            }

            if (!empty($observaciones)) {
                $detalleTexto .= "\nNotas: {$observaciones}";
            }

            $db->goQuery("INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)", [$idOrden, $detalleTexto]);

            return [
                'success' => true,
                'id_orden' => $idOrden,
                'cliente' => $clienteNombre,
                'total' => $total,
                'mensaje' => "Orden #{$idOrden} creada exitosamente para {$clienteNombre}."
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => "Error al crear la orden: " . $e->getMessage()
            ];
        }
    }
}
