<?php

/**
 * GeminiAssistant - Clase base abstracta para asistentes de IA con Gemini
 * 
 * Proporciona funcionalidad común para:
 * - Conexión a la API de Gemini
 * - Validación de consultas SQL (seguridad)
 * - Ejecución segura de consultas
 * 
 * @package NineSys\AI
 */
abstract class GeminiAssistant
{
    protected string $apiKey;
    protected string $model = 'gemini-2.0-flash';
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    protected array $dbSchema;
    protected $dbConnection;
    protected string $basePrompt;

    /**
     * Constructor
     * 
     * @param string $apiKey API Key de Gemini
     * @param array $dbSchema Schema de la BD para el prompt
     * @param mixed $dbConnection Conexión a la base de datos (LocalDB)
     */
    public function __construct(string $apiKey, array $dbSchema, $dbConnection)
    {
        $this->apiKey = $apiKey;
        $this->dbSchema = $dbSchema;
        $this->dbConnection = $dbConnection;
        $this->basePrompt = $this->buildBasePrompt();
    }

    /**
     * Construye el prompt base con el schema de la BD
     */
    protected function buildBasePrompt(): string
    {
        $prompt = $this->dbSchema['prompt_base'] ?? '';

        // Agregar recetas SQL curadas como ejemplos
        if (!empty($this->dbSchema['sql_recipes'])) {
            $prompt .= "\n\nEJEMPLOS DE CONSULTAS SQL:\n";
            $prompt .= "IMPORTANTE: Usa estos ejemplos como base para generar consultas similares. Son consultas probadas que funcionan correctamente.\n\n";
            foreach ($this->dbSchema['sql_recipes'] as $descripcion => $sql) {
                $prompt .= "• {$descripcion}:\n  {$sql}\n\n";
            }
        }

        $prompt .= "\n\nTABLAS DISPONIBLES:\n\n";

        foreach ($this->dbSchema['tables'] as $tableName => $tableInfo) {
            $prompt .= "TABLA: {$tableName}\n";
            $prompt .= "Descripción: " . ($tableInfo['description'] ?? '') . "\n";
            $prompt .= "Campos:\n";

            foreach ($tableInfo['fields'] as $fieldName => $fieldDesc) {
                $prompt .= "  - {$fieldName}: {$fieldDesc}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "\nRELACIONES:\n";
        foreach ($this->dbSchema['relations'] ?? [] as $relation) {
            $prompt .= "  - {$relation}\n";
        }

        $prompt .= "\n\nREGLAS DE SEGURIDAD:\n";
        $prompt .= "- Solo puedes generar consultas SELECT\n";
        $prompt .= "- NUNCA generes INSERT, UPDATE, DELETE, DROP, TRUNCATE, ALTER\n";
        $prompt .= "- Responde en español\n";

        return $prompt;
    }

    /**
     * Obtiene contexto de queries relevantes según la pregunta del usuario
     * 
     * @param string $userQuery Pregunta del usuario
     * @return string Contexto de queries para agregar al prompt
     */
    protected function getRelevantQueriesContext(string $userQuery): string
    {
        try {
            require_once __DIR__ . '/QueryExtractor.php';

            $extractor = new QueryExtractor();
            $categories = $extractor->detectCategories($userQuery);

            return $extractor->generatePromptContext($categories);
        } catch (\Throwable $e) {
            // Si hay error, continuar sin contexto adicional
            return '';
        }
    }

    /**
     * Llama a la API de Gemini
     * 
     * @param string $userQuery Pregunta del usuario
     * @param bool $requestSQL Si debe solicitar SQL como function call
     * @param array $history Historial de conversación [['role' => 'user'|'model', 'text' => '...'], ...]
     * @return array Respuesta de Gemini
     */
    protected function callGeminiAPI(string $userQuery, bool $requestSQL = true, array $history = []): array
    {
        $url = $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $systemInstruction = $this->basePrompt;

        // Agregar contexto de queries relevantes según la pregunta
        $systemInstruction .= $this->getRelevantQueriesContext($userQuery);

        if ($requestSQL) {
            $systemInstruction .= "\n\nCuando el usuario haga una pregunta sobre datos, genera la consulta SQL necesaria.";
            $systemInstruction .= "\nResponde con un JSON en este formato exacto:";
            $systemInstruction .= "\n{\"sql\": \"SELECT ...\", \"description\": \"Descripción de lo que hace la consulta\", \"columns\": [{\"key\": \"campo\", \"label\": \"Etiqueta\", \"sortable\": true}]}";
        }

        // Construir contents con historial de conversación
        $contents = [];

        // Agregar mensajes del historial
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg['text']]
                ]
            ];
        }

        // Agregar mensaje actual del usuario
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $userQuery]
            ]
        ];

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ]
        ];

        // Logging del prompt para debugging
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/gemini_prompts_' . date('Y-m-d') . '.log';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_query' => $userQuery,
            'prompt_length' => strlen($systemInstruction),
            'system_instruction' => $systemInstruction
        ];
        file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n---\n\n", FILE_APPEND);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'Error de conexión: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['error' => 'Error HTTP ' . $httpCode . ': ' . $response];
        }

        $data = json_decode($response, true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return ['error' => 'Respuesta inválida de Gemini', 'raw' => $data];
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        // Intentar parsear como JSON
        $jsonMatch = [];
        if (preg_match('/\{[\s\S]*\}/', $text, $jsonMatch)) {
            $parsed = json_decode($jsonMatch[0], true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed, 'raw_text' => $text];
            }
        }

        return ['success' => true, 'text' => $text];
    }

    /**
     * Valida que la consulta SQL sea segura (solo SELECT)
     * 
     * @param string $sql Consulta SQL a validar
     * @return bool true si es válida, false si no
     */
    protected function validateSqlQuery(string $sql): bool
    {
        $sql = strtoupper(trim($sql));

        // Debe empezar con SELECT
        if (strpos($sql, 'SELECT') !== 0) {
            return false;
        }

        // No debe contener comandos peligrosos
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', '--', '/*', 'UNION'];

        foreach ($forbidden as $word) {
            if (strpos($sql, $word) !== false) {
                // Permitir UNION solo si es UNION SELECT (no inyección)
                if ($word === 'UNION' && strpos($sql, 'UNION SELECT') !== false) {
                    continue;
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Ejecuta una consulta SQL de forma segura
     * 
     * @param string $sql Consulta SQL a ejecutar
     * @return array Resultados de la consulta
     * @throws Exception Si la consulta no es válida
     */
    protected function executeSqlQuery(string $sql): array
    {
        if (!$this->validateSqlQuery($sql)) {
            throw new \Exception('Consulta SQL no permitida por razones de seguridad');
        }

        try {
            $results = $this->dbConnection->goQuery($sql);
            return $results ?: [];
        } catch (\Exception $e) {
            throw new \Exception('Error al ejecutar consulta: ' . $e->getMessage());
        }
    }

    /**
     * Procesa la consulta del usuario
     * Método abstracto que debe implementar cada subclase
     * 
     * @param string $query Pregunta del usuario
     * @return mixed Respuesta formateada
     */
    abstract public function processUserQuery(string $query);
}
