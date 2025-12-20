<?php

/**
 * Rutas del Asistente de IA con Gemini
 * 
 * Endpoints:
 * - POST /ai/chat   - Chat conversacional con respuestas en lenguaje natural
 * - POST /ai/report - Generador de reportes con formato bootstrap-vue
 * 
 * @package NineSys\Routes
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    /**
     * POST /ai/chat
     * 
     * Procesa una pregunta en lenguaje natural y devuelve una respuesta conversacional.
     * 
     * Body:
     *   - query: string (pregunta del usuario)
     * 
     * Response:
     *   - success: boolean
     *   - response: string (respuesta en lenguaje natural)
     *   - data: array|null (datos crudos si aplica)
     */
    $app->post('/ai/chat', function (Request $request, Response $response) {
        require_once __DIR__ . '/../classes/AI/GeminiChatAssistant.php';
        require_once __DIR__ . '/../schemas/db-schema-gemini.php';
        require_once __DIR__ . '/../config.php';

        // Parsear body JSON
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        if ($data === null) {
            $data = $request->getParsedBody() ?? [];
        }

        // Validar que se envió una consulta
        if (empty($data['query'])) {
            $result = [
                'success' => false,
                'error' => 'Se requiere el parámetro "query" con la pregunta'
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        // Verificar API Key
        if (empty(GEMINI_API_KEY)) {
            $result = [
                'success' => false,
                'error' => 'La API Key de Gemini no está configurada'
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        // Obtener schema de la BD
        $schema = require __DIR__ . '/../schemas/db-schema-gemini.php';

        // Crear conexión a la BD
        $localConnection = new LocalDB();

        try {
            // Crear asistente y procesar consulta
            $assistant = new GeminiChatAssistant(GEMINI_API_KEY, $schema, $localConnection);
            $result = $assistant->processUserQuery($data['query']);

            $localConnection->disconnect();

            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['success'] ? 200 : 400);

        } catch (Exception $e) {
            $localConnection->disconnect();

            $result = [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    /**
     * POST /ai/report
     * 
     * Procesa una pregunta y devuelve datos estructurados para una tabla.
     * Compatible con el formato de bootstrap-vue b-table.
     * 
     * Body:
     *   - query: string (descripción del reporte deseado)
     * 
     * Response:
     *   - success: boolean
     *   - fields: array (estructura de columnas para b-table)
     *   - items: array (datos del reporte)
     *   - total: int (cantidad de registros)
     *   - description: string (descripción del reporte)
     */
    $app->post('/ai/report', function (Request $request, Response $response) {
        require_once __DIR__ . '/../classes/AI/GeminiReportAssistant.php';
        require_once __DIR__ . '/../schemas/db-schema-gemini.php';
        require_once __DIR__ . '/../config.php';

        // Parsear body JSON
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        if ($data === null) {
            $data = $request->getParsedBody() ?? [];
        }

        // Validar que se envió una consulta
        if (empty($data['query'])) {
            $result = [
                'success' => false,
                'error' => 'Se requiere el parámetro "query" con la descripción del reporte',
                'fields' => [],
                'items' => []
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        // Verificar API Key
        if (empty(GEMINI_API_KEY)) {
            $result = [
                'success' => false,
                'error' => 'La API Key de Gemini no está configurada',
                'fields' => [],
                'items' => []
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        // Obtener schema de la BD
        $schema = require __DIR__ . '/../schemas/db-schema-gemini.php';

        // Crear conexión a la BD
        $localConnection = new LocalDB();

        try {
            // Crear asistente y procesar consulta
            $assistant = new GeminiReportAssistant(GEMINI_API_KEY, $schema, $localConnection);
            $result = $assistant->processUserQuery($data['query']);

            $localConnection->disconnect();

            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['success'] ? 200 : 400);

        } catch (Exception $e) {
            $localConnection->disconnect();

            $result = [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage(),
                'fields' => [],
                'items' => []
            ];
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    /**
     * GET /ai/status
     * 
     * Verifica el estado del servicio de IA.
     */
    $app->get('/ai/status', function (Request $request, Response $response) {
        require_once __DIR__ . '/../config.php';

        $result = [
            'service' => 'AI Assistant',
            'status' => !empty(GEMINI_API_KEY) ? 'configured' : 'missing_api_key',
            'model' => 'gemini-2.0-flash',
            'endpoints' => [
                '/ai/chat' => 'POST - Chat conversacional',
                '/ai/report' => 'POST - Generador de reportes'
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

}; // Fin de la función que envuelve las rutas
