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

        // Obtener historial de conversación (si se envió)
        $history = $data['history'] ?? [];

        try {
            // Crear asistente y procesar consulta (con historial)
            $assistant = new GeminiChatAssistant(GEMINI_API_KEY, $schema, $localConnection);
            $result = $assistant->processUserQuery($data['query'], $history);

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
     * POST /ai/chat-orden
     * 
     * Procesa consultas relacionadas con órdenes con contexto enriquecido de BD.
     * Este endpoint pasa información relevante de la BD a Gemini para que tome
     * decisiones inteligentes (selección de clientes, productos, tallas, etc.)
     * 
     * Body:
     *   - query: string (mensaje del usuario)
     *   - history: array (historial de conversación)
     *   - contexto_bd: object (datos de BD: clientes, productos, tallas, telas)
     *   - orden_en_progreso: object|null (estado actual de la orden)
     */
    $app->post('/ai/chat-orden', function (Request $request, Response $response) {
        require_once __DIR__ . '/../classes/AI/GeminiChatAssistant.php';
        require_once __DIR__ . '/../schemas/db-schema-gemini.php';
        require_once __DIR__ . '/../config.php';

        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        if ($data === null) {
            $data = $request->getParsedBody() ?? [];
        }

        if (empty($data['query'])) {
            $result = [
                'success' => false,
                'error' => 'Se requiere el parámetro "query"'
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (empty(GEMINI_API_KEY)) {
            $result = [
                'success' => false,
                'error' => 'La API Key de Gemini no está configurada'
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $schema = require __DIR__ . '/../schemas/db-schema-gemini.php';
        $localConnection = new LocalDB();

        $history = $data['history'] ?? [];
        $contextoBD = $data['contexto_bd'] ?? [];
        $ordenEnProgreso = $data['orden_en_progreso'] ?? null;

        try {
            $assistant = new GeminiChatAssistant(GEMINI_API_KEY, $schema, $localConnection);

            // USAR NUEVO MÉTODO CON FUNCTION CALLING
            // Ya no pasamos contexto_bd porque Gemini lo obtendrá bajo demanda
            $result = $assistant->processOrderQueryWithFunctions($data['query'], $history, $ordenEnProgreso);

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
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * POST /ai/validar-orden-rapida
     * 
     * Endpoint híbrido para validación rápida y determinística de órdenes.
     * 
     * FLUJO:
     * 1. Gemini extrae cliente y productos del mensaje libre del usuario
     * 2. Sistema llama DIRECTAMENTE a handleValidarOrdenMasiva (sin Function Calling)
     * 3. Devuelve resultados estructurados con validación de todos los productos
     * 
     * REGLA: Solo permite crear orden si con_errores === 0
     * 
     * Body:
     *   - mensaje: string (orden escrita libremente por el usuario)
     * 
     * Response:
     *   - success: boolean
     *   - datos_extraidos: object (cliente y productos parseados)
     *   - validacion: object (resultados de validación)
     *   - puede_crear_orden: boolean (true solo si con_errores === 0)
     */
    $app->post('/ai/validar-orden-rapida', function (Request $request, Response $response) {
        require_once __DIR__ . '/../classes/AI/GeminiChatAssistant.php';
        require_once __DIR__ . '/../schemas/db-schema-gemini.php';
        require_once __DIR__ . '/../config.php';

        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        if ($data === null) {
            $data = $request->getParsedBody() ?? [];
        }

        if (empty($data['mensaje'])) {
            $result = [
                'success' => false,
                'error' => 'Se requiere el parámetro "mensaje"'
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (empty(GEMINI_API_KEY)) {
            $result = [
                'success' => false,
                'error' => 'La API Key de Gemini no está configurada'
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $schema = require __DIR__ . '/../schemas/db-schema-gemini.php';
        $localConnection = new LocalDB();

        try {
            $assistant = new GeminiChatAssistant(GEMINI_API_KEY, $schema, $localConnection);

            // PASO 1: Extraer datos del mensaje usando Gemini
            $extractionResult = $assistant->extractOrderData($data['mensaje']);

            if (!$extractionResult['success']) {
                $localConnection->disconnect();
                $result = [
                    'success' => false,
                    'error' => $extractionResult['error']
                ];
                $response->getBody()->write(json_encode($result));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $datosExtraidos = $extractionResult['data'];

            // PASO 2: Validar DIRECTAMENTE (sin pasar por Function Calling de Gemini)
            $validacionResult = $assistant->handleValidarOrdenMasiva(
                $localConnection,
                $datosExtraidos['cliente'],
                $datosExtraidos['productos'],
                $datosExtraidos['observaciones'] ?? ''
            );

            $localConnection->disconnect();

            // PASO 3: Determinar si se puede crear la orden (solo si con_errores === 0)
            $puedeCrearOrden = false;
            $conErrores = 0;

            if (isset($validacionResult['resumen'])) {
                $conErrores = $validacionResult['resumen']['con_errores'] ?? 0;
                $puedeCrearOrden = ($conErrores === 0);
            }

            // PASO 4: Devolver resultados estructurados
            $result = [
                'success' => true,
                'datos_extraidos' => $datosExtraidos,
                'validacion' => $validacionResult,
                'puede_crear_orden' => $puedeCrearOrden,
                'mensaje' => $puedeCrearOrden
                    ? "Orden validada correctamente. Todos los productos están listos para crear la orden."
                    : "Se encontraron {$conErrores} productos con errores. Corrige los errores antes de crear la orden."
            ];

            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $localConnection->disconnect();

            $result = [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
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
