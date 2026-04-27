<?php

/**
 * Rutas internas consumidas por el servicio msg_ninesys (Node.js / Baileys).
 *
 * Estas rutas son estrictamente servidor-a-servidor y NUNCA deben ser expuestas
 * al frontend. La autorización se hace con un token compartido vía header
 * `X-Internal-Token`, que debe coincidir con la variable de entorno
 * `MSG_SERVICE_INTERNAL_TOKEN` definida en el `.env` de la API.
 *
 * Responsabilidad: resolver las credenciales de la base de datos de una
 * empresa (`api_emp_{id_empresa}`) para que msg_ninesys pueda conectarse de
 * forma multi-tenant sin duplicar la lógica de autenticación de empresas.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    /**
     * GET /internal/db-credentials/{id_empresa}
     *
     * Devuelve las credenciales de conexión MySQL de la empresa solicitada.
     * Protegido por token interno compartido.
     */
    $app->get('/internal/db-credentials/{id_empresa}', function (Request $request, Response $response, $args) {
        // --- 1. Validar token interno (comparación constant-time) ---
        $providedToken = $request->getHeaderLine('X-Internal-Token');
        $expectedToken = getenv('MSG_SERVICE_INTERNAL_TOKEN') ?: '';

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            $response->getBody()->write(json_encode([
                'error' => 'Unauthorized',
                'message' => 'Token interno inválido o ausente.'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        // --- 2. Validar id_empresa ---
        $idEmpresa = filter_var($args['id_empresa'] ?? null, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Bad Request',
                'message' => 'id_empresa debe ser un entero positivo.'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        // --- 3. Consultar la base central api_empresas ---
        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

            $sql = 'SELECT id_empresa, nombre, activo, db_host, db_user, db_password, db_name
                    FROM empresas
                    WHERE id_empresa = ?';
            $rows = $localConnection->goQuery($sql, [$idEmpresa]);
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service] Error consultando empresa ' . $idEmpresa . ': ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Internal Server Error',
                'message' => 'No se pudo consultar la base central de empresas.'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        if (empty($rows)) {
            $response->getBody()->write(json_encode([
                'error' => 'Not Found',
                'message' => "Empresa {$idEmpresa} no existe."
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $empresa = $rows[0];

        if ((int) $empresa['activo'] !== 1) {
            $response->getBody()->write(json_encode([
                'error' => 'Not Found',
                'message' => "Empresa {$idEmpresa} está inactiva."
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        if (empty($empresa['db_name']) || empty($empresa['db_host']) || empty($empresa['db_user'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Unprocessable Entity',
                'message' => "Empresa {$idEmpresa} no tiene credenciales de BD configuradas."
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        // --- 4. Log de auditoría (sin exponer el password) ---
        error_log(sprintf(
            '[msg_service] Credenciales entregadas para empresa %d (%s) → %s@%s/%s',
            $idEmpresa,
            $empresa['nombre'] ?? '',
            $empresa['db_user'],
            $empresa['db_host'],
            $empresa['db_name']
        ));

        // --- 5. Respuesta ---
        $payload = [
            'id_empresa'  => (int) $empresa['id_empresa'],
            'nombre'      => $empresa['nombre'],
            'db_host'     => $empresa['db_host'],
            'db_user'     => $empresa['db_user'],
            'db_password' => $empresa['db_password'],
            'db_name'     => $empresa['db_name'],
        ];

        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * GET /internal/business-hours
     *
     * Devuelve el horario laboral de la empresa, parseado y validado, para que
     * msg_ninesys pueda calcular timeouts de asignación en minutos hábiles
     * (Fase D.3).
     *
     * Convención de identificación de tenant:
     *   Header `Authorization: {id_empresa}` (misma que el resto de rutas de
     *   la app — ver communications.php, orders.php, config.php).
     *
     * Contrato de respuesta por cada caso (la IA/cliente debe poder distinguir
     * entre "no hay horario configurado", "el JSON está roto" y "falta una
     * clave"):
     *   200 { id_empresa, nombre, horario_laboral: { horaInicioManana, ... } }
     *   400 { error: 'bad_request', message }   (Authorization ausente/inválido)
     *   404 { error: 'not_found', message }   (empresa no existe o inactiva)
     *   422 { error: 'unprocessable_entity', message, reason, ... }
     *   500 { error: 'internal_error', message }
     *
     * Formato esperado del horario_laboral en api_empresas.empresas:
     *   - horaInicioManana / horaFinManana / horaInicioTarde / horaFinTarde:
     *     número decimal en horas (ej: 8.5 = 08:30, 12 = 12:00, 17.5 = 17:30).
     *     Rango válido: [0, 24]. Pueden ser null/vacío para turnos no usados.
     *   - diasLaborales: array de enteros (convención: 1=Lun..7=Dom o 0=Dom..6=Sáb).
     *
     * `reason` posibles en 422:
     *   - horario_laboral_empty
     *   - horario_laboral_invalid_json
     *   - horario_laboral_not_object
     *   - horario_laboral_missing_keys  (+ `missing: [...]`)
     *   - horario_laboral_invalid_time_range  (+ `invalid: {...}`)
     *   - dias_laborales_not_array
     *   - dias_laborales_invalid_items  (+ `invalid: [...]`)
     */
    $app->get('/internal/business-hours', function (Request $request, Response $response) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        };

        // --- 1. Leer id_empresa desde el header Authorization (convención del
        //     resto de la app: el valor del header es directamente el id). ---
        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson([
                'error'   => 'bad_request',
                'message' => 'Header Authorization ausente o inválido. Debe contener id_empresa (entero positivo).',
            ], 400);
        }

        // --- 3. Consultar la base central api_empresas ---
        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $sql = 'SELECT id_empresa, nombre, activo, horario_laboral
                    FROM empresas
                    WHERE id_empresa = ?';
            $rows = $localConnection->goQuery($sql, [$idEmpresa]);
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][business-hours] Excepción de conexión empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson([
                'error'   => 'internal_error',
                'message' => 'No se pudo conectar a la base central de empresas.',
            ], 500);
        }

        // goQuery() atrapa PDOException y devuelve ['status' => 'error', ...]
        // en vez de lanzar. Hay que detectar ese shape antes de tratar $rows
        // como un array de filas.
        if (isset($rows['status']) && $rows['status'] === 'error') {
            error_log('[msg_service][business-hours] Error SQL empresa ' . $idEmpresa . ': ' . ($rows['message'] ?? 'sin detalle'));
            return $respondJson([
                'error'   => 'internal_error',
                'message' => 'Error al ejecutar la consulta de empresa.',
            ], 500);
        }

        // --- 4. Empresa no existe ---
        if (empty($rows)) {
            return $respondJson([
                'error'   => 'not_found',
                'message' => "Empresa {$idEmpresa} no existe.",
            ], 404);
        }

        $empresa = $rows[0];

        // --- 5. Empresa inactiva ---
        if ((int) ($empresa['activo'] ?? 0) !== 1) {
            return $respondJson([
                'error'   => 'not_found',
                'message' => "Empresa {$idEmpresa} está inactiva.",
            ], 404);
        }

        // --- 6. horario_laboral vacío o null ---
        $raw = $empresa['horario_laboral'] ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => "La empresa {$idEmpresa} no tiene horario laboral configurado.",
                'reason'  => 'horario_laboral_empty',
            ], 422);
        }

        // --- 7. Parsear JSON ---
        $horario = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[msg_service][business-hours] JSON inválido empresa ' . $idEmpresa . ': ' . json_last_error_msg());
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => "El campo horario_laboral de la empresa {$idEmpresa} tiene un formato JSON inválido.",
                'reason'  => 'horario_laboral_invalid_json',
            ], 422);
        }

        if (!is_array($horario)) {
            // Cubre el caso en que el JSON es literalmente `null` o un escalar.
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => "El campo horario_laboral debe ser un objeto JSON (empresa {$idEmpresa}).",
                'reason'  => 'horario_laboral_not_object',
            ], 422);
        }

        // --- 8. Validar claves requeridas ---
        $requiredKeys = [
            'horaInicioManana',
            'horaFinManana',
            'horaInicioTarde',
            'horaFinTarde',
            'diasLaborales',
        ];
        $missing = [];
        foreach ($requiredKeys as $k) {
            if (!array_key_exists($k, $horario)) {
                $missing[] = $k;
            }
        }
        if (!empty($missing)) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => "El horario laboral de la empresa {$idEmpresa} no contiene todas las claves requeridas.",
                'reason'  => 'horario_laboral_missing_keys',
                'missing' => $missing,
            ], 422);
        }

        // --- 9. Validar horas como decimales en [0, 24]. Vacíos/null permitidos
        //     para tramos opcionales (ej: empresa sin turno tarde). ---
        $timeKeys = ['horaInicioManana', 'horaFinManana', 'horaInicioTarde', 'horaFinTarde'];
        $invalidTimes = [];
        foreach ($timeKeys as $k) {
            $v = $horario[$k];
            if ($v === null || $v === '') continue;
            if (!is_numeric($v) || $v < 0 || $v > 24) {
                $invalidTimes[$k] = $v;
            }
        }
        if (!empty($invalidTimes)) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => 'Uno o más horarios están fuera de rango (se esperan horas decimales en [0, 24]).',
                'reason'  => 'horario_laboral_invalid_time_range',
                'invalid' => $invalidTimes,
            ], 422);
        }

        // --- 10. Validar diasLaborales como array de enteros ---
        if (!is_array($horario['diasLaborales'])) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => 'El campo diasLaborales debe ser un array.',
                'reason'  => 'dias_laborales_not_array',
            ], 422);
        }
        $invalidDays = [];
        foreach ($horario['diasLaborales'] as $d) {
            // Aceptamos 0-7 para cubrir ambas convenciones (0=Dom..6=Sáb o 1=Lun..7=Dom).
            if (!is_int($d) || $d < 0 || $d > 7) {
                $invalidDays[] = $d;
            }
        }
        if (!empty($invalidDays)) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => 'diasLaborales contiene valores no válidos (se esperan enteros en [0, 7]).',
                'reason'  => 'dias_laborales_invalid_items',
                'invalid' => $invalidDays,
            ], 422);
        }

        // --- 11. Log de auditoría ---
        error_log(sprintf(
            '[msg_service][business-hours] OK empresa %d (%s)',
            $idEmpresa,
            $empresa['nombre'] ?? ''
        ));

        // --- 12. Respuesta exitosa ---
        return $respondJson([
            'id_empresa'      => (int) $empresa['id_empresa'],
            'nombre'          => $empresa['nombre'],
            'horario_laboral' => $horario,
        ], 200);
    });

    /**
     * GET /internal/ping
     *
     * Health check del endpoint interno. Útil para que msg_ninesys verifique
     * conectividad y validez del token en el arranque.
     */
    $app->get('/internal/ping', function (Request $request, Response $response) {
        $providedToken = $request->getHeaderLine('X-Internal-Token');
        $expectedToken = getenv('MSG_SERVICE_INTERNAL_TOKEN') ?: '';

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        $response->getBody()->write(json_encode([
            'ok' => true,
            'service' => 'ninesys-api',
            'timestamp' => date('c'),
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * GET /internal/catalog-test
     *
     * Simple test endpoint to verify routes are being loaded correctly.
     */
    $app->get('/internal/catalog-test', function (Request $request, Response $response) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        };

        return $respondJson([
            'ok' => true,
            'message' => 'Catalog routes are loaded correctly',
            'timestamp' => date('c'),
        ], 200);
    });

    /**
     * GET /internal/catalog-test/{id}
     *
     * Test endpoint with path parameter to verify Slim can handle routes with params.
     */
    $app->get('/internal/catalog-test/{id}', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        };

        return $respondJson([
            'ok' => true,
            'message' => 'Path parameters work correctly',
            'received_id' => $args['id'] ?? 'not found',
            'timestamp' => date('c'),
        ], 200);
    });

    /**
     * GET /internal/catalog/:idEmpresa?search=término
     *
     * Devuelve el catálogo de productos de una empresa para enriquecer
     * respuestas de IA (búsqueda por nombre, precios, atributos, categorías).
     *
     * Convención de identificación: header `Authorization: {id_empresa}`
     *
     * Parámetros query:
     *   - search: término de búsqueda (nombre o descripción)
     *   - limit: máximo de productos (default 20)
     *
     * Respuesta (200):
     *   {
     *     "id_empresa": 163,
     *     "search_term": "remera",
     *     "products": [
     *       {
     *         "id": 5,
     *         "name": "Remera Básica",
     *         "description": "...",
     *         "is_physical": true,
     *         "is_design": false,
     *         "price_min": 12.50,
     *         "price_max": 15.00,
     *         "categories": ["Prendas", "Casual"],
     *         "attributes": [
     *           {"name": "Color", "values": ["Rojo", "Azul"]}
     *         ]
     *       }
     *     ]
     *   }
     *
     * Errores:
     *   400: Authorization ausente/inválido
     *   404: empresa no existe
     *   500: error al conectar a la BD del tenant
     */
    $app->get('/internal/catalog/{id_empresa}', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        };

        // --- 1. Validar Authorization header ---
        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson([
                'error' => 'bad_request',
                'message' => 'Header Authorization ausente o inválido.',
            ], 400);
        }

        // --- 2. Parámetros query ---
        $searchTerm = trim($request->getQueryParams()['search'] ?? '');
        $limit = (int) ($request->getQueryParams()['limit'] ?? 20);
        $limit = min(max($limit, 1), 100); // Clamp entre 1 y 100

        // --- 3. Obtener credenciales del tenant ---
        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $sql = 'SELECT id_empresa, db_host, db_user, db_password, db_name
                    FROM empresas
                    WHERE id_empresa = ?';
            $rows = $localConnection->goQuery($sql, [$idEmpresa]);
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][catalog] Error consultando empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson([
                'error' => 'internal_error',
                'message' => 'No se pudo consultar la base central de empresas.',
            ], 500);
        }

        if (isset($rows['status']) && $rows['status'] === 'error') {
            error_log('[msg_service][catalog] Error SQL empresa ' . $idEmpresa);
            return $respondJson([
                'error' => 'internal_error',
                'message' => 'Error al ejecutar la consulta de empresa.',
            ], 500);
        }

        if (empty($rows)) {
            return $respondJson([
                'error' => 'not_found',
                'message' => "Empresa {$idEmpresa} no existe.",
            ], 404);
        }

        $empresa = $rows[0];
        $tenantDb = $empresa['db_name'];
        $tenantUser = $empresa['db_user'];
        $tenantPass = $empresa['db_password'];
        $tenantHost = $empresa['db_host'];

        // --- 4. Conectar a la BD del tenant y obtener productos ---
        try {
            $tenantConnection = new LocalDB();

            // Búsqueda por nombre o descripción, solo productos físicos o diseños
            $likePattern = '%' . $searchTerm . '%';
            $dbName = "`{$tenantDb}`";  // Backticks para seguridad
            $sql = <<<SQL
                SELECT p._id as id, p.product as name, p.product_description as description,
                       p.fisico as is_physical, p.es_diseno as is_design,
                       p.category_ids, MIN(pp.price) as price_min, MAX(pp.price) as price_max
                FROM {$dbName}.products p
                LEFT JOIN {$dbName}.products_prices pp ON p._id = pp.id_product
                WHERE (p.product LIKE ? OR p.product_description LIKE ?)
                  AND (p.fisico = 1 OR p.es_diseno = 1)
                GROUP BY p._id
                ORDER BY p.product ASC
                LIMIT {$limit}
            SQL;

            $products = $tenantConnection->goQuery($sql, [$likePattern, $likePattern]);
            if (isset($products['status']) && $products['status'] === 'error') {
                throw new \Exception($products['message'] ?? 'Error desconocido');
            }

            // --- 5. Enriquecer cada producto con categorías y atributos ---
            $enriched = [];
            foreach ((array)$products as $p) {
                $productId = (int)$p['id'];

                // Obtener categorías (category_ids es "1,2,5")
                $categories = [];
                if (!empty($p['category_ids'])) {
                    $catIds = array_map('intval', explode(',', $p['category_ids']));
                    $catIds = implode(',', $catIds);
                    $catSql = "SELECT nombre FROM {$dbName}.categories WHERE _id IN ({$catIds}) ORDER BY nombre";
                    $catRows = $tenantConnection->goQuery($catSql);
                    if (!isset($catRows['status'])) {
                        $categories = array_column((array)$catRows, 'nombre');
                    }
                }

                // Obtener atributos (solo si el producto es físico o diseño)
                $attributes = [];
                if ((int)$p['is_physical'] === 1 || (int)$p['is_design'] === 1) {
                    $attrSql = <<<SQL
                        SELECT pa._id as id, pa.attribute_name as name,
                               GROUP_CONCAT(pav.attribute_value ORDER BY pav.attribute_value) as values
                        FROM {$dbName}.products_attributes pa
                        JOIN {$dbName}.products_attributes_values pav ON pa._id = pav.id_product_attribute
                        WHERE pav.id_product = ?
                        GROUP BY pa._id
                        ORDER BY pa.attribute_name ASC
                    SQL;
                    $attrRows = $tenantConnection->goQuery($attrSql, [$productId]);
                    if (!isset($attrRows['status'])) {
                        foreach ((array)$attrRows as $attr) {
                            $attributes[] = [
                                'name' => $attr['name'],
                                'values' => array_map('trim', explode(',', $attr['values'])),
                            ];
                        }
                    }
                }

                $enriched[] = [
                    'id' => $productId,
                    'name' => $p['name'],
                    'description' => $p['description'] ?? '',
                    'is_physical' => (bool)(int)$p['is_physical'],
                    'is_design' => (bool)(int)$p['is_design'],
                    'price_min' => $p['price_min'] ? (float)$p['price_min'] : null,
                    'price_max' => $p['price_max'] ? (float)$p['price_max'] : null,
                    'categories' => $categories,
                    'attributes' => $attributes,
                ];
            }

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][catalog] Error conectando tenant ' . $idEmpresa . ': ' . $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine());
            error_log('[msg_service][catalog] Stack: ' . $e->getTraceAsString());
            return $respondJson([
                'error' => 'internal_error',
                'message' => 'Error al conectar a la base de datos del tenant.',
            ], 500);
        }

        // --- 6. Respuesta exitosa ---
        return $respondJson([
            'id_empresa' => $idEmpresa,
            'search_term' => $searchTerm,
            'product_count' => count($enriched),
            'products' => $enriched,
        ], 200);
    });

};
