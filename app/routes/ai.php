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
        $ordenConfirmada = $data['orden_confirmada'] ?? null;

        try {
            $assistant = new GeminiChatAssistant(GEMINI_API_KEY, $schema, $localConnection);

            // SI HAY ORDEN CONFIRMADA, CREARLA DIRECTAMENTE
            if ($ordenConfirmada && isset($ordenConfirmada['cliente_id']) && isset($ordenConfirmada['productos'])) {
                $resultadoCreacion = $assistant->callFunctionHandler('crearOrdenFinal', [
                    'id_cliente' => $ordenConfirmada['cliente_id'],
                    'productos' => $ordenConfirmada['productos'],
                    'observaciones' => $ordenConfirmada['observaciones'] ?? ''
                ]);

                // Log para debugging
                error_log("Resultado de crearOrdenFinal: " . json_encode($resultadoCreacion));

                $localConnection->disconnect();

                // Verificar que el resultado sea un array y tenga la clave 'success'
                if (!is_array($resultadoCreacion)) {
                    $result = [
                        'success' => false,
                        'error' => 'Error: La creación de orden no retornó un resultado válido'
                    ];
                } elseif (!isset($resultadoCreacion['success'])) {
                    $result = [
                        'success' => false,
                        'error' => 'Error: La creación de orden no retornó el formato esperado'
                    ];
                } elseif ($resultadoCreacion['success']) {
                    $result = [
                        'success' => true,
                        'response' => "✅ **¡Orden #{$resultadoCreacion['id_orden']} creada exitosamente!**\n\n" .
                            "👤 Cliente: {$resultadoCreacion['cliente']}\n" .
                            "💰 Total: \${$resultadoCreacion['total']}\n" .
                            "📦 Productos: " . count($ordenConfirmada['productos']) . " items"
                    ];
                } else {
                    $result = [
                        'success' => false,
                        'error' => $resultadoCreacion['error'] ?? 'Error al crear la orden'
                    ];
                }

                $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($result['success'] ? 200 : 400);
            }

            // DETECTAR SI ES UNA ORDEN (palabras clave)
            $esOrden = preg_match('/\b(orden|ordenes|pedido|crear|crea|hacer)\b/i', $data['query']);

            if ($esOrden) {
                // FLUJO HÍBRIDO PARA ÓRDENES
                // 1. Extraer datos con Gemini
                $extractionResult = $assistant->extractOrderData($data['query']);

                if (!$extractionResult['success']) {
                    $result = [
                        'success' => false,
                        'response' => "No pude entender la orden. " . $extractionResult['error']
                    ];
                } else {
                    $datosExtraidos = $extractionResult['data'];

                    // 2. Validar directamente
                    $ordenData = [
                        'cliente' => $datosExtraidos['cliente'],
                        'productos' => $datosExtraidos['productos'],
                        'descripcion' => $datosExtraidos['observaciones'] ?? ''
                    ];

                    $validacionResult = $assistant->callFunctionHandler('validarOrdenMasiva', $ordenData);

                    // 3. Verificar si se puede crear
                    $conProblemas = $validacionResult['con_problemas'] ?? 0;
                    $clienteEncontrado = $validacionResult['cliente']['encontrado'] ?? false;
                    $puedeCrear = ($clienteEncontrado && $conProblemas === 0);

                    // 4. Formatear respuesta amigable
                    if (!$clienteEncontrado) {
                        $nombreBuscado = $validacionResult['cliente']['nombre_buscado'] ?? 'desconocido';
                        $respuesta = "❌ No encontré el cliente '{$nombreBuscado}'. Por favor verifica el nombre o créalo primero.";
                    } elseif ($conProblemas > 0) {
                        $respuesta = "He validado la orden pero encontré {$conProblemas} productos con errores:\n\n";
                        foreach ($validacionResult['productos'] as $i => $p) {
                            if ($p['tiene_errores']) {
                                $respuesta .= "⚠️ Producto " . ($i + 1) . ": " . $p['original']['product'] . "\n";
                                $respuesta .= "   Errores: " . implode(", ", $p['errores']) . "\n";
                            }
                        }
                        $respuesta .= "\nPor favor corrige estos productos antes de crear la orden.";
                    } else {
                        $cliente = $validacionResult['cliente']['datos'];
                        $respuesta = "✅ Orden validada correctamente para {$cliente['nombre_completo']}!\n\n";
                        $respuesta .= "📦 {$validacionResult['correctos']} productos listos\n\n";
                        $respuesta .= "¿Deseas que cree la orden? (Responde 'sí' o 'crear' para confirmar)";
                    }

                    $result = [
                        'success' => true,
                        'response' => $respuesta,
                        'data' => $validacionResult,
                        'puede_crear' => $puedeCrear
                    ];
                }
            } else {
                // FLUJO NORMAL PARA CONSULTAS (no órdenes)
                $result = $assistant->processOrderQueryWithFunctions($data['query'], $history, $ordenEnProgreso);
            }

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
