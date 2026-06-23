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
     *   - only_design: si es "1"/true, ignora "search" y devuelve TODOS los
     *     productos con es_diseno=1 (catálogo completo de servicios de diseño)
     *   - limit: máximo de productos (default 20)
     *
     * Respuesta (200):
     *   {
     *     "id_empresa": 163,
     *     "search_term": "remera",
     *     "only_design": false,
     *     "product_count": 1,
     *     "products": [
     *       {
     *         "id": 5,
     *         "name": "Remera Básica",
     *         "description": "...",
     *         "is_physical": true,
     *         "is_design": false,
     *         "prices": [
     *           {"price": 15.00, "descripcion": "Precio X 1"},
     *           {"price": 12.50, "descripcion": "Precio X 3"}
     *         ],
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
        $onlyDesign = filter_var($request->getQueryParams()['only_design'] ?? false, FILTER_VALIDATE_BOOLEAN);
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

            // Búsqueda por nombre o descripción, solo productos físicos o diseños.
            // Modo only_design=1: ignora "search" y lista TODOS los productos de
            // diseño gráfico (es_diseno=1), para que la IA pueda mostrar el catálogo
            // completo de servicios de diseño sin depender de un match de nombre.
            $dbName = "`{$tenantDb}`";  // Backticks para seguridad

            if ($onlyDesign) {
                $whereClause = 'p.es_diseno = 1';
                $bindParams = [];
            } else {
                $whereClause = 'p.product LIKE ? AND (p.fisico = 1 OR p.es_diseno = 1)';
                $bindParams = ['%' . $searchTerm . '%'];
            }

            // Obtener productos sin agrupar por precio
            $sqlProducts = <<<SQL
                SELECT DISTINCT p._id as id, p.product as name, p.product_description as description,
                       p.fisico as is_physical, p.es_diseno as is_design, p.category_ids
                FROM {$dbName}.products p
                WHERE {$whereClause}
                ORDER BY p.product ASC
                LIMIT {$limit}
            SQL;

            $products = $tenantConnection->goQuery($sqlProducts, $bindParams);
            if (isset($products['status']) && $products['status'] === 'error') {
                throw new \Exception($products['message'] ?? 'Error desconocido');
            }

            // --- 5. Enriquecer cada producto con categorías, atributos y TODOS los precios ---
            $enriched = [];
            foreach ((array)$products as $p) {
                $productId = (int)$p['id'];

                // Obtener TODOS los precios de este producto
                $sqlPrices = "SELECT price, descripcion FROM {$dbName}.products_prices
                              WHERE id_product = ? ORDER BY price DESC";
                $prices = $tenantConnection->goQuery($sqlPrices, [$productId]) ?? [];

                // Obtener categorías (category_ids es "1,2,5")
                $categories = [];
                $firstCategoryId = 0;
                if (!empty($p['category_ids'])) {
                    $catIdsArr = array_map('intval', explode(',', $p['category_ids']));
                    $firstCategoryId = $catIdsArr[0];
                    $catIds = implode(',', $catIdsArr);
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

                // Formatear precios: convertir a array de {price, descripcion}
                $formattedPrices = [];
                if (!isset($prices['status'])) {
                    foreach ((array)$prices as $pr) {
                        $formattedPrices[] = [
                            'price' => (float)$pr['price'],
                            'descripcion' => $pr['descripcion'] ?? '',
                        ];
                    }
                }

                $enriched[] = [
                    'id' => $productId,
                    'name' => $p['name'],
                    'description' => $p['description'] ?? '',
                    'is_physical' => (bool)(int)$p['is_physical'],
                    'is_design' => (bool)(int)$p['is_design'],
                    'prices' => $formattedPrices,
                    'categories' => $categories,
                    'category_id' => $firstCategoryId,
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
            'search_term' => $onlyDesign ? null : $searchTerm,
            'only_design' => $onlyDesign,
            'product_count' => count($enriched),
            'products' => $enriched,
        ], 200);
    });

    /**
     * GET /internal/cliente/{id_empresa}/by-phone?phone={telefono}
     *
     * Busca un cliente en la tabla customers del tenant por número de teléfono.
     * Si existe, también devuelve el id del último vendedor que lo atendió
     * (buscando en presupuestos y ordenes, en ese orden).
     *
     * Header: Authorization: {id_empresa}
     *
     * Respuesta 200 — cliente encontrado:
     *   { "found": true, "customer": { "_id", "first_name", "last_name", "cedula",
     *     "phone", "email", "address" }, "last_vendedor_id": int|null }
     * Respuesta 200 — no encontrado:
     *   { "found": false }
     */
    $app->get('/internal/cliente/{id_empresa}/by-phone', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $phone = trim($request->getQueryParams()['phone'] ?? '');
        if ($phone === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Parámetro phone requerido.'], 400);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][cliente/by-phone] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = '`' . $rows[0]['db_name'] . '`';

        try {
            $tenantConnection = new LocalDB();

            $customers = $tenantConnection->goQuery(
                "SELECT _id, first_name, last_name, cedula, phone, email, address
                 FROM {$dbName}.customers WHERE phone = ? LIMIT 1",
                [$phone]
            );

            if (isset($customers['status']) || empty($customers)) {
                $tenantConnection->disconnect();
                return $respondJson(['found' => false], 200);
            }

            $customer = $customers[0];
            $customerId = (int) $customer['_id'];

            // Buscar último vendedor en presupuestos, luego en ordenes
            $lastVendedorId = null;
            $vendedorRows = $tenantConnection->goQuery(
                "SELECT responsable FROM {$dbName}.presupuestos
                 WHERE id_wp = ? AND responsable IS NOT NULL ORDER BY _id DESC LIMIT 1",
                [$customerId]
            );
            if (!empty($vendedorRows) && !isset($vendedorRows['status'])) {
                $lastVendedorId = (int) $vendedorRows[0]['responsable'];
            }

            if (!$lastVendedorId) {
                $vendedorRows = $tenantConnection->goQuery(
                    "SELECT responsable FROM {$dbName}.ordenes
                     WHERE id_wp = ? AND responsable IS NOT NULL ORDER BY _id DESC LIMIT 1",
                    [$customerId]
                );
                if (!empty($vendedorRows) && !isset($vendedorRows['status'])) {
                    $lastVendedorId = (int) $vendedorRows[0]['responsable'];
                }
            }

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][cliente/by-phone] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar cliente.'], 500);
        }

        return $respondJson([
            'found'            => true,
            'customer'         => [
                '_id'        => $customerId,
                'first_name' => $customer['first_name'],
                'last_name'  => $customer['last_name'],
                'cedula'     => $customer['cedula'],
                'phone'      => $customer['phone'],
                'email'      => $customer['email'],
                'address'    => $customer['address'],
            ],
            'last_vendedor_id' => $lastVendedorId,
        ], 200);
    });

    /**
     * GET /internal/ordenes/{id_empresa}/by-phone?phone={telefono}
     *
     * Busca las órdenes de un cliente en el tenant por número de teléfono.
     * Devuelve el listado de órdenes con su estado, fecha de entrega, total,
     * abonos, descuentos y saldo pendiente, además de los productos correspondientes.
     *
     * Header: Authorization: {id_empresa}
     *
     * Respuesta 200 — cliente encontrado:
     *   {
     *     "found": true,
     *     "customer_id": int,
     *     "customer_name": string,
     *     "ordenes": [...]
     *   }
     * Respuesta 200 — no encontrado:
     *   { "found": false }
     */
    $app->get('/internal/ordenes/{id_empresa}/by-phone', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $phone = trim($request->getQueryParams()['phone'] ?? '');
        if ($phone === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Parámetro phone requerido.'], 400);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-phone] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = '`' . $rows[0]['db_name'] . '`';

        try {
            $tenantConnection = new LocalDB();

            // 1. Buscar cliente por teléfono
            $customers = $tenantConnection->goQuery(
                "SELECT _id, first_name, last_name 
                 FROM {$dbName}.customers WHERE phone = ? LIMIT 1",
                [$phone]
            );

            if (isset($customers['status']) || empty($customers)) {
                $tenantConnection->disconnect();
                return $respondJson(['found' => false], 200);
            }

            $customer = $customers[0];
            $customerId = (int) $customer['_id'];
            $customerName = trim($customer['first_name'] . ' ' . $customer['last_name']);

            // 2. Buscar todas las órdenes de este cliente (excepto canceladas)
            $ordersQuery = "
                SELECT 
                    o._id AS id_orden, 
                    o.status, 
                    o.fecha_entrega, 
                    o.pago_total,
                    o.pago_descuento,
                    o.pago_abono,
                    IFNULL((SELECT SUM(a.abono) FROM {$dbName}.abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    IFNULL((SELECT SUM(a.descuento) FROM {$dbName}.abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    IFNULL((SELECT SUM(a.nota_credito) FROM {$dbName}.abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}.ordenes o
                WHERE o.id_wp = ? AND o.status != 'cancelada'
                ORDER BY o._id DESC
            ";

            $orders = $tenantConnection->goQuery($ordersQuery, [$customerId]);
            
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al buscar órdenes');
            }

            $formattedOrders = [];
            foreach ((array)$orders as $o) {
                $idOrden = (int) $o['id_orden'];
                
                // Buscar productos de la orden
                $productsQuery = "
                    SELECT name, cantidad, talla AS detalle_tallas 
                    FROM {$dbName}.ordenes_productos 
                    WHERE id_orden = ?
                ";
                $products = $tenantConnection->goQuery($productsQuery, [$idOrden]);
                if (isset($products['status'])) {
                    $products = [];
                }

                $totalAbonos = (float)$o['total_abonos'];
                $totalDescuentos = (float)$o['total_descuentos'];
                $totalNotasCredito = (float)$o['total_notas_credito'];
                $pagoTotal = (float)$o['pago_total'];

                // Calcular el saldo pendiente usando la fórmula de la base de datos
                $saldoPendiente = $pagoTotal - $totalAbonos - $totalDescuentos + $totalNotasCredito;

                $formattedOrders[] = [
                    'id_orden' => $idOrden,
                    'status' => $o['status'],
                    'fecha_entrega' => $o['fecha_entrega'],
                    'pago_total' => $pagoTotal,
                    'total_abonos' => $totalAbonos,
                    'total_descuentos' => $totalDescuentos,
                    'saldo_pendiente' => $saldoPendiente,
                    'productos' => array_map(function($p) {
                        return [
                            'name' => $p['name'],
                            'cantidad' => (int)$p['cantidad'],
                            'detalle_tallas' => $p['detalle_tallas'] ?? '',
                        ];
                    }, (array)$products)
                ];
            }

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-phone] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar órdenes.'], 500);
        }

        return $respondJson([
            'found'         => true,
            'customer_id'   => $customerId,
            'customer_name' => $customerName,
            'ordenes'       => $formattedOrders
        ], 200);
    });


    /**
     * POST /internal/cliente/{id_empresa}
     *
     * Crea un nuevo cliente en la tabla customers del tenant.
     * Si ya existe uno con el mismo teléfono o cédula, lo actualiza (upsert).
     *
     * Header: Authorization: {id_empresa}
     * Body JSON: { nombre, apellido, cedula, telefono, email?, direccion? }
     *
     * Respuesta 200: { "id_customer": int, "upserted": bool }
     */
    $app->post('/internal/cliente/{id_empresa}', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $nombre    = addslashes(trim($body['nombre']    ?? ''));
        $apellido  = addslashes(trim($body['apellido']  ?? ''));
        $cedula    = addslashes(trim($body['cedula']    ?? ''));
        $telefono  = addslashes(trim($body['telefono']  ?? ''));
        $email     = addslashes(strtolower(trim($body['email']    ?? '')));
        $direccion = addslashes(trim($body['direccion'] ?? ''));

        if ($nombre === '' || $telefono === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'nombre y telefono son requeridos.'], 400);
        }

        if ($email === '') {
            $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            $email = strtolower(substr($nombre, 0, 1)) . $randomString . '@email.com';
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][cliente/crear] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe."], 404);
        }

        $dbName = '`' . $rows[0]['db_name'] . '`';

        try {
            $tenantConnection = new LocalDB();

            $conditions = ["phone = '$telefono'"];
            if ($cedula !== '' && $cedula !== 'none') {
                $conditions[] = "cedula = '$cedula'";
            }
            $existingRows = $tenantConnection->goQuery(
                "SELECT _id FROM {$dbName}.customers WHERE (" . implode(' OR ', $conditions) . ") LIMIT 1"
            );

            if (!empty($existingRows) && !isset($existingRows['status'])) {
                $customerId = (int) $existingRows[0]['_id'];
                $tenantConnection->goQuery(
                    "UPDATE {$dbName}.customers
                     SET first_name = '$nombre', last_name = '$apellido', cedula = '$cedula',
                         phone = '$telefono', email = '$email', address = '$direccion'
                     WHERE _id = $customerId"
                );
                $tenantConnection->disconnect();
                return $respondJson(['id_customer' => $customerId, 'upserted' => true], 200);
            }

            $createResult = $tenantConnection->goQuery(
                "INSERT INTO {$dbName}.customers (first_name, last_name, cedula, phone, email, address)
                 VALUES ('$nombre', '$apellido', '$cedula', '$telefono', '$email', '$direccion')"
            );
            $tenantConnection->disconnect();

            if (!isset($createResult['insert_id'])) {
                error_log('[msg_service][cliente/crear] insert_id ausente para empresa ' . $idEmpresa);
                return $respondJson(['error' => 'internal_error', 'message' => 'No se pudo crear el cliente.'], 500);
            }

            return $respondJson(['id_customer' => (int) $createResult['insert_id'], 'upserted' => false], 200);
        } catch (\Throwable $e) {
            error_log('[msg_service][cliente/crear] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al crear cliente.'], 500);
        }
    });

    /**
     * PATCH /internal/presupuesto/{id_presupuesto}/vendedor
     *
     * Asigna un vendedor (campo responsable) a un presupuesto existente.
     *
     * Header: Authorization: {id_empresa}
     * Body JSON: { "id_vendedor": int }
     *
     * Respuesta 200: { "ok": true }
     */
    $app->patch('/internal/presupuesto/{id_presupuesto}/vendedor', function (Request $request, Response $response, $args) {
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $idPresupuesto = filter_var($args['id_presupuesto'] ?? null, FILTER_VALIDATE_INT);
        if ($idPresupuesto === false || $idPresupuesto <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_presupuesto inválido.'], 400);
        }

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $idVendedor = filter_var($body['id_vendedor'] ?? null, FILTER_VALIDATE_INT);
        if ($idVendedor === false || $idVendedor <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_vendedor inválido.'], 400);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][presupuesto/vendedor] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe."], 404);
        }

        $dbName = '`' . $rows[0]['db_name'] . '`';

        try {
            $tenantConnection = new LocalDB();

            $existing = $tenantConnection->goQuery(
                "SELECT _id FROM {$dbName}.presupuestos WHERE _id = ? LIMIT 1",
                [$idPresupuesto]
            );

            if (empty($existing) || isset($existing['status'])) {
                $tenantConnection->disconnect();
                return $respondJson(['error' => 'not_found', 'message' => "Presupuesto {$idPresupuesto} no encontrado."], 404);
            }

            $tenantConnection->goQuery(
                "UPDATE {$dbName}.presupuestos SET responsable = ? WHERE _id = ?",
                [$idVendedor, $idPresupuesto]
            );

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][presupuesto/vendedor] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al actualizar presupuesto.'], 500);
        }

        return $respondJson(['ok' => true], 200);
    });

};
