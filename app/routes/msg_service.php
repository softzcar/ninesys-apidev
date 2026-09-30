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
        // Deliberadamente NO se generalizó a validarTokenInterno() (que
        // acepta también PRINT_SERVICE_INTERNAL_TOKEN): este endpoint
        // devuelve credenciales REALES de conexión a base de datos, solo
        // msg_ninesys debe poder pedirlas -- no se amplía ese alcance sin
        // necesidad real (auditoría de seguridad 2026-09-10).
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

        // --- 4. Log de auditoría (sin exponer credenciales ni metadata de BD) ---
        error_log(sprintf(
            '[msg_service] Credenciales de BD entregadas para empresa %d (%s)',
            $idEmpresa,
            $empresa['nombre'] ?? ''
        ));

        // --- 5. Respuesta ---
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        $port   = defined('DB_PORT') ? (int) DB_PORT : ($driver === 'pgsql' ? 5432 : 3306);

        $payload = [
            'id_empresa'  => (int) $empresa['id_empresa'],
            'nombre'      => $empresa['nombre'],
            'db_host'     => $empresa['db_host'],
            'db_user'     => $empresa['db_user'],
            'db_password' => $empresa['db_password'],
            'db_name'     => $empresa['db_name'],
            'db_driver'   => $driver,
            'db_port'     => $port,
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
     *   - horaInicioNoche / horaFinNoche (OPCIONALES, no forman parte de
     *     $requiredKeys -- pueden estar ausentes en horarios guardados antes
     *     de que existiera el turno Noche): mismo formato/rango que arriba.
     *     Si vienen presentes, se validan igual; si están ausentes o
     *     null/vacías, se interpreta como "la empresa no usa turno Noche".
     *   - diasLaborales: array de enteros (convención: 1=Lun..7=Dom o 0=Dom..6=Sáb).
     *   - diasManana / diasTarde / diasNoche (OPCIONALES, mismo criterio que
     *     Noche arriba): lista de días en que ESE turno específico aplica --
     *     permite omitir un turno en un día puntual (ej. diasTarde sin
     *     sábado). Ausentes ⇒ el consumidor debe asumir diasLaborales como
     *     respaldo para ese turno.
     *   - overridesManana / overridesTarde / overridesNoche (OPCIONALES,
     *     Fase 2): objeto `{ "<día 0-6>": { horaInicio, horaFin } }` con
     *     horas específicas para días puntuales que difieren de la base del
     *     turno (ej. Sábado con menos horas que el resto de la semana). Este
     *     endpoint los devuelve tal cual vienen en la BD (sin resolverlos),
     *     junto con la hora base -- la hora base (horaInicioX/horaFinX) NO
     *     refleja las excepciones, es solo un valor de referencia general.
     *     ⚠️ Si `msg_ninesys` (servicio externo, repo aparte) calcula
     *     timeouts usando solo la hora base sin consultar overridesX, esos
     *     cálculos serán incorrectos para los días con excepción -- no
     *     forma parte de este cambio actualizar ese servicio.
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
        // Auditoría de seguridad 2026-09-10: este endpoint /internal/* no
        // validaba X-Internal-Token pese a que el comentario de cabecera del
        // archivo afirma que todos lo hacen -- gap real, cerrado aquí.
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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
        //     para tramos opcionales (ej: empresa sin turno tarde o sin turno
        //     Noche). horaInicioNoche/horaFinNoche pueden no existir siquiera
        //     como clave en horarios guardados antes de esta funcionalidad --
        //     ?? null evita el warning de clave ausente. ---
        $timeKeys = ['horaInicioManana', 'horaFinManana', 'horaInicioTarde', 'horaFinTarde', 'horaInicioNoche', 'horaFinNoche'];
        $invalidTimes = [];
        foreach ($timeKeys as $k) {
            $v = $horario[$k] ?? null;
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

        // --- 10b. Validar diasManana/diasTarde/diasNoche (OPCIONALES) como
        //     arrays de enteros, mismo criterio que diasLaborales. Ausentes
        //     no es error -- el consumidor debe usar diasLaborales como
        //     respaldo para ese turno. ---
        foreach (['diasManana', 'diasTarde', 'diasNoche'] as $diasKey) {
            if (!array_key_exists($diasKey, $horario) || $horario[$diasKey] === null) {
                continue;
            }
            if (!is_array($horario[$diasKey])) {
                return $respondJson([
                    'error'   => 'unprocessable_entity',
                    'message' => "El campo {$diasKey} debe ser un array.",
                    'reason'  => 'dias_laborales_not_array',
                ], 422);
            }
            $invalidDaysTurno = [];
            foreach ($horario[$diasKey] as $d) {
                if (!is_int($d) || $d < 0 || $d > 7) {
                    $invalidDaysTurno[] = $d;
                }
            }
            if (!empty($invalidDaysTurno)) {
                return $respondJson([
                    'error'   => 'unprocessable_entity',
                    'message' => "{$diasKey} contiene valores no válidos (se esperan enteros en [0, 7]).",
                    'reason'  => 'dias_laborales_invalid_items',
                    'invalid' => $invalidDaysTurno,
                ], 422);
            }
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
        // Health check inofensivo -- generalizado para aceptar cualquier
        // token de servicio válido (MSG_SERVICE_INTERNAL_TOKEN o
        // PRINT_SERVICE_INTERNAL_TOKEN), a diferencia de
        // /internal/db-credentials que sigue exigiendo específicamente el de
        // msg_ninesys (devuelve credenciales reales de BD, no se amplía su
        // alcance sin necesidad real).
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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
            // $tenantConnection ya está conectado directamente a la BD del tenant (vía LOCAL_DNS
            // resuelto por el middleware a partir del mismo header Authorization), así que el
            // prefijo de base de datos es redundante. En pgsql, además, la notación "bd.tabla"
            // cross-database no existe en una sola conexión (rompe con "schema does not existe").
            $dbName = DB_DRIVER === 'pgsql' ? '' : "`{$tenantDb}`.";  // Backticks para seguridad (solo mysql)

            $likeOp = DB_DRIVER === 'pgsql' ? 'ILIKE' : 'LIKE';
            if ($onlyDesign) {
                $whereClause = 'p.es_diseno = 1';
                $bindParams = [];
            } else {
                $whereClause = "p.product {$likeOp} ? AND (p.fisico = 1 OR p.es_diseno = 1)";
                $bindParams = ['%' . $searchTerm . '%'];
            }

            // Obtener productos sin agrupar por precio
            $sqlProducts = <<<SQL
                SELECT DISTINCT p._id as id, p.product as name, p.product_description as description,
                       p.fisico as is_physical, p.es_diseno as is_design, p.category_ids
                FROM {$dbName}products p
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
                $sqlPrices = "SELECT price, descripcion FROM {$dbName}products_prices
                              WHERE id_product = ? ORDER BY price DESC";
                $prices = $tenantConnection->goQuery($sqlPrices, [$productId]) ?? [];

                // Obtener categorías (category_ids es "1,2,5")
                $categories = [];
                $firstCategoryId = 0;
                if (!empty($p['category_ids'])) {
                    $catIdsArr = array_map('intval', explode(',', $p['category_ids']));
                    $firstCategoryId = $catIdsArr[0];
                    $catIds = implode(',', $catIdsArr);
                    $catSql = "SELECT nombre FROM {$dbName}categories WHERE _id IN ({$catIds}) ORDER BY nombre";
                    $catRows = $tenantConnection->goQuery($catSql);
                    if (!isset($catRows['status'])) {
                        $categories = array_column((array)$catRows, 'nombre');
                    }
                }

                // Obtener atributos (solo si el producto es físico o diseño)
                $attributes = [];
                if ((int)$p['is_physical'] === 1 || (int)$p['is_design'] === 1) {
                    $valuesExpr = DB_DRIVER === 'pgsql'
                        ? 'string_agg(pav.attribute_value, \',\' ORDER BY pav.attribute_value)'
                        : 'GROUP_CONCAT(pav.attribute_value ORDER BY pav.attribute_value)';
                    $attrSql = <<<SQL
                        SELECT pa._id as id, pa.attribute_name as name,
                               {$valuesExpr} as values
                        FROM {$dbName}products_attributes pa
                        JOIN {$dbName}products_attributes_values pav ON pa._id = pav.id_product_attribute
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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

        // $tenantConnection ya está conectado directamente a la BD del tenant; el prefijo solo
        // aplica en mysql (en pgsql "bd.tabla" cross-database no existe en una sola conexión).
        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            $customers = $tenantConnection->goQuery(
                "SELECT _id, first_name, last_name, cedula, phone, email, address
                 FROM {$dbName}customers WHERE phone = ? LIMIT 1",
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
                "SELECT responsable FROM {$dbName}presupuestos
                 WHERE id_wp = ? AND responsable IS NOT NULL ORDER BY _id DESC LIMIT 1",
                [$customerId]
            );
            if (!empty($vendedorRows) && !isset($vendedorRows['status'])) {
                $lastVendedorId = (int) $vendedorRows[0]['responsable'];
            }

            if (!$lastVendedorId) {
                $vendedorRows = $tenantConnection->goQuery(
                    "SELECT responsable FROM {$dbName}ordenes
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
     * GET /internal/vendedor-aleatorio/{id_empresa}
     *
     * Devuelve un vendedor activo elegido al azar entre los del departamento de
     * ventas/comercialización (5, 6) de la empresa -- para el caso "cliente
     * nuevo o sin historial" al crear un presupuesto desde una app externa
     * (19print), como complemento de /internal/cliente/{id_empresa}/by-phone
     * (que cubre el caso "cliente existente -> último vendedor"). Mismo
     * criterio de departamento que ya usa msg_ninesys/src/services/assignmentPolicy.js,
     * sin el filtro de disponibilidad de chat (wa_vendor_state.allow_auto_assign),
     * que es específico del módulo de WhatsApp y no aplica acá.
     *
     * Header: Authorization: {id_empresa}
     *
     * Respuesta 200: { "vendedor_id": int|null }
     */
    $app->get('/internal/vendedor-aleatorio/{id_empresa}', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $randomFn = DB_DRIVER === 'pgsql' ? 'RANDOM()' : 'RAND()';

        try {
            $centralConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $centralConnection->goQuery(
                "SELECT u.id_usuario
                 FROM empresas_usuarios u
                 JOIN empresas_usuarios_departamentos d ON d.id_empleado = u.id_usuario
                 WHERE u.id_empresa = ? AND u.activo = 1 AND d.id_departamento IN (5, 6)
                 ORDER BY {$randomFn} LIMIT 1",
                [$idEmpresa]
            );
            $centralConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][vendedor-aleatorio] Error empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al elegir vendedor.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['vendedor_id' => null], 200);
        }

        return $respondJson(['vendedor_id' => (int) $rows[0]['id_usuario']], 200);
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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

        // $tenantConnection ya está conectado directamente a la BD del tenant; el prefijo solo
        // aplica en mysql (en pgsql "bd.tabla" cross-database no existe en una sola conexión).
        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            // 1. Buscar cliente por teléfono
            $customers = $tenantConnection->goQuery(
                "SELECT _id, first_name, last_name 
                 FROM {$dbName}customers WHERE phone = ? LIMIT 1",
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
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
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
                    FROM {$dbName}ordenes_productos 
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
     * GET /internal/ordenes/{id_empresa}/by-id?id={id_orden}
     *
     * Devuelve UNA orden concreta por su _id con detalle exhaustivo:
     * - Datos del cliente (id, nombre, teléfono, cédula, email, dirección)
     * - Datos de la orden (status, vendedor, fecha_inicio, fecha_entrega, cliente_nombre)
     * - Resumen financiero (pago_total, total_abonos, total_descuentos, total_notas_credito,
     *   saldo_pendiente, sobrepago, estado_pago, descuento_detalle)
     * - Métodos de pago registrados (moneda, metodo_pago, monto, tasa, detalle)
     * - Diseño (tipo)
     * - Observaciones (limpias de HTML)
     * - Productos detallados (nombre, cantidad, precio, subtotal, talla, tela, corte, atributo)
     * Header: Authorization: {id_empresa}
     * Respuesta 200: { found:true, customer_id, customer_name, cliente:{...}, orden:{...} } | { found:false }
     */
    $app->get('/internal/ordenes/{id_empresa}/by-id', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $idOrden = filter_var($request->getQueryParams()['id'] ?? '', FILTER_VALIDATE_INT);
        if ($idOrden === false || $idOrden <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Parámetro id (id_orden) requerido.'], 400);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-id] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            // 1. Cabecera de la orden y totales de abonos / notas de crédito
            $ordersQuery = "
                SELECT
                    o._id AS id_orden,
                    o.status,
                    o.cliente_nombre,
                    c.nombre AS vendedor,
                    o.fecha_inicio,
                    o.fecha_entrega,
                    o.pago_total,
                    o.id_wp,
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
                LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = o.responsable
                WHERE o._id = ?
                LIMIT 1
            ";
            $orders = $tenantConnection->goQuery($ordersQuery, [$idOrden]);
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al buscar la orden');
            }
            if (empty($orders)) {
                $tenantConnection->disconnect();
                return $respondJson(['found' => false], 200);
            }

            $o = $orders[0];
            $customerId = (int) ($o['id_wp'] ?? 0);

            // 2. Detalle de descuentos en abonos
            $descuentoDetalleSql = DB_DRIVER === 'pgsql'
                ? "SELECT STRING_AGG(CASE WHEN descuento > 0 AND detalle IS NOT NULL AND TRIM(detalle) != '' THEN detalle ELSE NULL END, ', ') AS descuento_detalle FROM {$dbName}abonos WHERE id_orden = ?"
                : "SELECT GROUP_CONCAT(CASE WHEN descuento > 0 AND detalle IS NOT NULL AND TRIM(detalle) != '' THEN detalle ELSE NULL END SEPARATOR ', ') AS descuento_detalle FROM {$dbName}abonos WHERE id_orden = ?";
            $descRows = $tenantConnection->goQuery($descuentoDetalleSql, [$idOrden]);
            $descuentoDetalle = (!empty($descRows) && !isset($descRows['status'])) ? ($descRows[0]['descuento_detalle'] ?? '') : '';

            // 3. Datos del cliente
            $customerName = trim($o['cliente_nombre'] ?? '');
            $clienteData = [
                'id'        => $customerId,
                'nombre'    => $customerName,
                'telefono'  => '',
                'cedula'    => '',
                'email'     => '',
                'direccion' => '',
            ];
            if ($customerId > 0) {
                $cust = $tenantConnection->goQuery(
                    "SELECT _id, first_name, last_name, phone, cedula, email, address FROM {$dbName}customers WHERE _id = ? LIMIT 1",
                    [$customerId]
                );
                if (!isset($cust['status']) && !empty($cust)) {
                    $cRow = $cust[0];
                    $nombreCompuesto = trim(($cRow['first_name'] ?? '') . ' ' . ($cRow['last_name'] ?? ''));
                    if ($nombreCompuesto !== '') {
                        $customerName = $nombreCompuesto;
                    }
                    $clienteData = [
                        'id'        => (int) $cRow['_id'],
                        'nombre'    => $customerName,
                        'telefono'  => (string) ($cRow['phone'] ?? ''),
                        'cedula'    => (string) ($cRow['cedula'] ?? ''),
                        'email'     => (string) ($cRow['email'] ?? ''),
                        'direccion' => (string) ($cRow['address'] ?? ''),
                    ];
                }
            }

            // 4. Métodos de pago registrados
            $metodosRows = $tenantConnection->goQuery(
                "SELECT moneda, metodo_pago, detalle, monto, tasa FROM {$dbName}metodos_de_pago WHERE id_orden = ? ORDER BY _id ASC",
                [$idOrden]
            );
            $metodosPago = [];
            if (!isset($metodosRows['status']) && is_array($metodosRows)) {
                foreach ($metodosRows as $mp) {
                    $metodosPago[] = [
                        'moneda'      => $mp['moneda'] ?? '',
                        'metodo_pago' => $mp['metodo_pago'] ?? '',
                        'monto'       => (float) ($mp['monto'] ?? 0),
                        'tasa'        => (float) ($mp['tasa'] ?? 0),
                        'detalle'     => (string) ($mp['detalle'] ?? ''),
                    ];
                }
            }

            // 5. Tipo de diseño
            $disenoRows = $tenantConnection->goQuery(
                "SELECT tipo FROM {$dbName}disenos WHERE id_orden = ? LIMIT 1",
                [$idOrden]
            );
            $disenoTipo = (!empty($disenoRows) && !isset($disenoRows['status'])) ? ($disenoRows[0]['tipo'] ?? 'Ninguno') : 'Ninguno';

            // 6. Observaciones de la orden (sanitizadas de HTML) e imágenes adjuntas
            $obsRows = $tenantConnection->goQuery(
                "SELECT observaciones FROM {$dbName}ordenes_observaciones WHERE id_orden = ?",
                [$idOrden]
            );
            $observacionesLimpias = '';
            $imagenesObservaciones = [];
            if (!empty($obsRows) && !isset($obsRows['status'])) {
                $obsTextos = [];
                $isDev = (strpos($_SERVER['HTTP_HOST'] ?? '', 'nineteengreen.com') !== false);
                $apiBaseUrl = $isDev ? 'https://api.nineteengreen.com' : 'https://api.ninesys19.com';
                $cdnBaseUrl = getenv('NINESYS_CDN_URL') ?: (
                    $isDev ? 'https://cdn.nineteengreen.com' : 'https://cdn.ninesys19.com'
                );
                $internalToken = getenv('MSG_SERVICE_INTERNAL_TOKEN') ?: (defined('MSG_SERVICE_INTERNAL_TOKEN') ? MSG_SERVICE_INTERNAL_TOKEN : '');

                $imgIndex = 0;
                foreach ($obsRows as $ob) {
                    $rawObs = (string) ($ob['observaciones'] ?? '');
                    if ($rawObs !== '') {
                        // Extraer imágenes embebidas (<img src="...">)
                        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawObs, $imgMatches)) {
                            foreach ($imgMatches[1] as $src) {
                                $src = trim($src);
                                if ($src === '') {
                                    continue;
                                }
                                if (strpos($src, 'data:image/') === 0) {
                                    // Imagen base64 legacy fallback: guardar en CDN para URL HTTPS limpia
                                    try {
                                        $guzzle = new \GuzzleHttp\Client(['timeout' => 10]);
                                        $cdnRes = $guzzle->post($cdnBaseUrl . '/?action=save_obs_image', [
                                            'headers' => [
                                                'X-Internal-Token' => $internalToken,
                                                'Content-Type'     => 'application/json',
                                            ],
                                            'json' => [
                                                'id_empresa' => $idEmpresa,
                                                'id_orden'   => $idOrden,
                                                'index'      => $imgIndex,
                                                'data'       => $src,
                                            ],
                                            'http_errors' => false,
                                        ]);
                                        $cdnData = json_decode((string) $cdnRes->getBody(), true);
                                        if (!empty($cdnData['success']) && !empty($cdnData['url'])) {
                                            $imagenesObservaciones[] = [
                                                'url'     => $cdnData['url'],
                                                'caption' => "Orden #{$idOrden} — observación" . ($imgIndex > 0 ? " (" . ($imgIndex + 1) . ")" : ""),
                                            ];
                                            $imgIndex++;
                                        }
                                    } catch (\Throwable $e) {
                                        error_log('[msg_service][ordenes/by-id] Error guardando imagen obs en CDN: ' . $e->getMessage());
                                    }
                                } else {
                                    // Imagen por URL (subida a images-orders-details o externa)
                                    if (strpos($src, '//') === 0) {
                                        $src = 'https:' . $src;
                                    } elseif (strpos($src, '/') === 0) {
                                        $src = $apiBaseUrl . $src;
                                    } elseif (!preg_match('/^https?:\/\//i', $src)) {
                                        $src = $apiBaseUrl . '/' . $src;
                                    }

                                    // Forzar https
                                    if (strpos($src, 'http://') === 0) {
                                        $src = 'https://' . substr($src, 7);
                                    }

                                    $imagenesObservaciones[] = [
                                        'url'     => $src,
                                        'caption' => "Orden #{$idOrden} — observación" . ($imgIndex > 0 ? " (" . ($imgIndex + 1) . ")" : ""),
                                    ];
                                    $imgIndex++;
                                }
                            }
                        }

                        // Quitar HTML y decodificar caracteres &aacute; etc.
                        $limpio = trim(html_entity_decode(strip_tags($rawObs), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if ($limpio !== '') {
                            $obsTextos[] = $limpio;
                        }
                    }
                }
                $observacionesLimpias = implode("\n", $obsTextos);
            }

            // 7. Productos de la orden con tallas, tela, corte y atributos
            $productsRows = $tenantConnection->goQuery(
                "SELECT
                    op._id,
                    op.name,
                    pr.sku AS sku,
                    pr._id AS cod,
                    op.cantidad,
                    op.id_size AS id_talla,
                    s.nombre AS talla_nombre,
                    op.talla AS talla_raw,
                    op.id_tela,
                    op.tela,
                    op.corte,
                    op.precio_unitario AS precio,
                    (SELECT attribute_name FROM {$dbName}products_attributes WHERE _id = op.id_products_attributes) AS atributo_nombre
                FROM {$dbName}ordenes_productos op
                LEFT JOIN {$dbName}products pr ON pr._id = op.id_woo
                LEFT JOIN {$dbName}sizes s ON s._id = op.id_size
                WHERE op.id_orden = ?
                ORDER BY op._id ASC",
                [$idOrden]
            );
            if (isset($productsRows['status'])) {
                $productsRows = [];
            }

            $productos = [];
            foreach ((array) $productsRows as $p) {
                $cant = (float) ($p['cantidad'] ?? 0);
                $precio = (float) ($p['precio'] ?? 0);
                $tallaVal = !empty($p['talla_nombre']) ? $p['talla_nombre'] : ($p['talla_raw'] ?? '');
                $productos[] = [
                    'id'             => (int) ($p['_id'] ?? 0),
                    'cod'            => $p['cod'] !== null ? (int) $p['cod'] : null,
                    'sku'            => $p['sku'] ?? '',
                    'name'           => (string) ($p['name'] ?? ''),
                    'cantidad'       => $cant,
                    'precio'         => $precio,
                    'subtotal'       => round($cant * $precio, 2),
                    'talla'          => (string) $tallaVal,
                    'detalle_tallas' => (string) ($p['talla_raw'] ?? $tallaVal),
                    'tela'           => (string) ($p['tela'] ?? ''),
                    'corte'          => (string) ($p['corte'] ?? ''),
                    'atributo'       => (string) ($p['atributo_nombre'] ?? ''),
                ];
            }

            // 8. Cálculos financieros
            $totalAbonos = (float) $o['total_abonos'];
            $totalDescuentos = (float) $o['total_descuentos'];
            $totalNotasCredito = (float) $o['total_notas_credito'];
            $pagoTotal = (float) $o['pago_total'];

            $balance = round($pagoTotal - $totalAbonos - $totalDescuentos + $totalNotasCredito, 2);
            $saldoPendiente = $balance > 0 ? $balance : 0.0;
            $sobrepago = $balance < 0 ? abs($balance) : 0.0;

            $estadoPago = 'pendiente_pago';
            if ($pagoTotal <= 0) {
                $estadoPago = 'sin_costo';
            } elseif ($sobrepago > 0) {
                $estadoPago = 'sobrepago';
            } elseif ($saldoPendiente == 0.0) {
                $estadoPago = 'pagado_total';
            } elseif ($totalAbonos > 0) {
                $estadoPago = 'abono_parcial';
            }

            $orden = [
                'id_orden'            => (int) $o['id_orden'],
                'status'              => $o['status'],
                'cliente_nombre'      => $customerName,
                'vendedor'            => $o['vendedor'] ?? 'Sin asignar',
                'fecha_inicio'        => $o['fecha_inicio'],
                'fecha_entrega'       => $o['fecha_entrega'],
                'pago_total'          => $pagoTotal,
                'total_abonos'        => $totalAbonos,
                'total_descuentos'    => $totalDescuentos,
                'total_notas_credito' => $totalNotasCredito,
                'saldo_pendiente'     => $saldoPendiente,
                'sobrepago'           => $sobrepago,
                'estado_pago'         => $estadoPago,
                'descuento_detalle'   => $descuentoDetalle,
                'diseno_tipo'         => $disenoTipo,
                'observaciones'       => $observacionesLimpias,
                'metodos_pago'        => $metodosPago,
                'productos'           => $productos,
                'imagenes_observaciones' => $imagenesObservaciones,
            ];

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-id] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar la orden.'], 500);
        }

        return $respondJson([
            'found'         => true,
            'customer_id'   => $customerId,
            'customer_name' => $customerName,
            'cliente'       => $clienteData,
            'orden'         => $orden,
        ], 200);
    });


    /**
     * GET /internal/ordenes/{id_empresa}/by-status?status={status}&limit={limit}&offset={offset}
     *
     * Consulta y filtra órdenes por estado (ej: 'activa', 'en espera', 'terminada', 'entregada', 'pausada', 'cancelada', o 'todas').
     * Devuelve información financiera resumida, cliente, vendedor, fechas y productos principales.
     * Header: Authorization: {id_empresa}
     * Respuesta 200: { total: number, status_filter: string, ordenes: [...] }
     */
    $app->get('/internal/ordenes/{id_empresa}/by-status', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $params = $request->getQueryParams();
        $statusQuery = trim((string) ($params['status'] ?? ''));
        $limit = filter_var($params['limit'] ?? 20, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $offset = filter_var($params['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($offset === false || $offset < 0) $offset = 0;

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-status] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            $whereClause = '';
            $queryParams = [];

            if ($statusQuery !== '' && !in_array(strtolower($statusQuery), ['todas', 'todos', 'all', '*'])) {
                $st = strtolower($statusQuery);
                if (strpos($st, 'activa') !== false || strpos($st, 'producc') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%activa%'";
                } elseif (strpos($st, 'espera') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%espera%'";
                } elseif (strpos($st, 'terminad') !== false || strpos($st, 'lista') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%terminad%'";
                } elseif (strpos($st, 'entregad') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%entregad%'";
                } elseif (strpos($st, 'pausad') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%pausad%'";
                } elseif (strpos($st, 'cancelad') !== false) {
                    $whereClause = "WHERE LOWER(o.status) LIKE '%cancelad%'";
                } else {
                    $whereClause = "WHERE LOWER(o.status) = LOWER(?)";
                    $queryParams[] = $statusQuery;
                }
            }

            $sql = "
                SELECT
                    o._id AS id_orden,
                    o.status,
                    o.cliente_nombre,
                    c.nombre AS vendedor,
                    o.fecha_inicio,
                    o.fecha_entrega,
                    o.pago_total,
                    o.id_wp,
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
                LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = o.responsable
                {$whereClause}
                ORDER BY o._id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $orders = $tenantConnection->goQuery($sql, $queryParams);
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al listar órdenes por estado');
            }

            if (empty($orders)) {
                $tenantConnection->disconnect();
                return $respondJson([
                    'total'         => 0,
                    'status_filter' => $statusQuery ?: 'todas',
                    'ordenes'       => [],
                ], 200);
            }

            // Cargar productos en lote para las órdenes devueltas
            $orderIds = array_column($orders, 'id_orden');
            $productsByOrder = [];
            if (!empty($orderIds)) {
                $idsStr = implode(',', array_map('intval', $orderIds));
                $prodRows = $tenantConnection->goQuery("
                    SELECT id_orden, name, cantidad, talla
                    FROM {$dbName}ordenes_productos
                    WHERE id_orden IN ({$idsStr})
                    ORDER BY _id ASC
                ");
                if (!isset($prodRows['status']) && is_array($prodRows)) {
                    foreach ($prodRows as $pr) {
                        $oid = (int) $pr['id_orden'];
                        $productsByOrder[$oid][] = [
                            'name'     => (string) ($pr['name'] ?? ''),
                            'cantidad' => (float) ($pr['cantidad'] ?? 0),
                            'talla'    => (string) ($pr['talla'] ?? ''),
                        ];
                    }
                }
            }

            $formatted = [];
            foreach ($orders as $o) {
                $oid = (int) $o['id_orden'];
                $pagoTotal = (float) $o['pago_total'];
                $totalAbonos = (float) $o['total_abonos'];
                $totalDescuentos = (float) $o['total_descuentos'];
                $totalNotasCredito = (float) $o['total_notas_credito'];

                $balance = round($pagoTotal - $totalAbonos - $totalDescuentos + $totalNotasCredito, 2);
                $saldoPendiente = $balance > 0 ? $balance : 0.0;
                $sobrepago = $balance < 0 ? abs($balance) : 0.0;

                $estadoPago = 'pendiente_pago';
                if ($pagoTotal <= 0) {
                    $estadoPago = 'sin_costo';
                } elseif ($sobrepago > 0) {
                    $estadoPago = 'sobrepago';
                } elseif ($saldoPendiente == 0.0) {
                    $estadoPago = 'pagado_total';
                } elseif ($totalAbonos > 0) {
                    $estadoPago = 'abono_parcial';
                }

                $prods = $productsByOrder[$oid] ?? [];

                $formatted[] = [
                    'id_orden'          => $oid,
                    'status'            => $o['status'],
                    'cliente_nombre'    => (string) ($o['cliente_nombre'] ?? ''),
                    'vendedor'          => (string) ($o['vendedor'] ?? 'Sin asignar'),
                    'fecha_inicio'      => $o['fecha_inicio'],
                    'fecha_entrega'     => $o['fecha_entrega'],
                    'pago_total'        => $pagoTotal,
                    'total_abonos'      => $totalAbonos,
                    'total_descuentos'  => $totalDescuentos,
                    'saldo_pendiente'   => $saldoPendiente,
                    'sobrepago'         => $sobrepago,
                    'estado_pago'       => $estadoPago,
                    'productos_resumen' => $prods,
                ];
            }

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/by-status] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar órdenes por estado.'], 500);
        }

        return $respondJson([
            'total'         => count($formatted),
            'status_filter' => $statusQuery ?: 'todas',
            'ordenes'       => $formatted,
        ], 200);
    });


    /**
     * GET /internal/ordenes/{id_empresa}/en-curso
     *
     * "Órdenes en curso" con EXACTAMENTE el mismo criterio que la pantalla
     * Control de producción de app_multi (GET /sse/produccion en
     * production.php + filtro de producto físico de controlDeProduccionPro.vue):
     * status 'activa' | 'pausada' | 'En espera', con lote de producción y con
     * al menos un producto físico (products.fisico = 1). Es la fuente correcta
     * para "¿cuántas órdenes hay en producción?" -- devuelve el total real (sin
     * límite), un resumen y la lista en el mismo orden de la pantalla.
     *
     * Header: Authorization: {id_empresa}
     * Respuesta 200: { total, resumen: {por_estado, por_paso, urgentes, atrasadas, por_asignar}, ordenes: [...] }
     */
    $app->get('/internal/ordenes/{id_empresa}/en-curso', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        try {
            $tenantConnection = new LocalDB();
            // Mismo criterio que /sse/produccion (items) + filtro físico del
            // frontend. Subconsultas escalares en vez de JOIN para no
            // multiplicar filas ni depender de GROUP BY.
            $orders = $tenantConnection->goQuery("
                SELECT
                    a._id AS id_orden,
                    a.status,
                    COALESCE(NULLIF(TRIM(CONCAT(cus.first_name, ' ', cus.last_name)), ''), a.cliente_nombre) AS cliente,
                    a.fecha_inicio,
                    a.fecha_entrega,
                    (SELECT MAX(l.prioridad) FROM lotes l WHERE l.id_orden = a._id) AS prioridad,
                    (SELECT f.orden_fila FROM ordenes_fila_orden f WHERE f.id_orden = a._id LIMIT 1) AS orden_fila,
                    (SELECT SUM(op.cantidad) FROM ordenes_productos op JOIN products p ON p._id = op.id_woo
                      WHERE op.id_orden = a._id AND p.fisico = 1) AS unidades,
                    (SELECT COUNT(DISTINCT ld.id_departamento) FROM lotes_detalles_empleados_asignados ld
                      WHERE ld.id_orden = a._id) AS total_departamentos,
                    (SELECT COUNT(DISTINCT ld.id_departamento) FROM lotes_detalles_empleados_asignados ld
                      WHERE ld.id_orden = a._id AND ld.fecha_terminado IS NOT NULL) AS departamentos_terminados,
                    (SELECT dep.departamento FROM lotes_detalles_empleados_asignados ld
                      JOIN departamentos dep ON dep._id = ld.id_departamento
                      WHERE ld.id_orden = a._id AND ld.fecha_terminado IS NULL
                      ORDER BY dep.orden_proceso ASC LIMIT 1) AS paso_actual
                FROM ordenes a
                LEFT JOIN customers cus ON cus._id = a.id_wp
                WHERE a.status IN ('activa', 'pausada', 'En espera')
                  AND EXISTS (SELECT 1 FROM lotes l WHERE l.id_orden = a._id)
                  AND EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON p._id = op.id_woo
                              WHERE op.id_orden = a._id AND p.fisico = 1)
                ORDER BY orden_fila ASC NULLS LAST, a._id ASC
            ");
            $tenantConnection->disconnect();
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al listar órdenes en curso');
            }
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/en-curso] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar órdenes en curso.'], 500);
        }

        $hoy = date('Y-m-d');
        $resumen = ['por_estado' => [], 'por_paso' => [], 'urgentes' => 0, 'atrasadas' => 0, 'por_asignar' => 0];
        $formatted = [];
        foreach ((array) $orders as $o) {
            $total = (int) $o['total_departamentos'];
            $terminados = (int) $o['departamentos_terminados'];
            // Mismo cálculo de "paso" que la pantalla.
            if ($total === 0) {
                $paso = 'Por asignar';
            } elseif ($o['paso_actual'] === null) {
                $paso = 'Terminado';
            } else {
                $paso = $o['paso_actual'];
            }
            $entrega = substr((string) ($o['fecha_entrega'] ?? ''), 0, 10);
            $atrasada = $entrega !== '' && $entrega < $hoy;
            $urgente = (int) ($o['prioridad'] ?? 0) === 1;

            $resumen['por_estado'][$o['status']] = ($resumen['por_estado'][$o['status']] ?? 0) + 1;
            $resumen['por_paso'][$paso] = ($resumen['por_paso'][$paso] ?? 0) + 1;
            if ($urgente) $resumen['urgentes']++;
            if ($atrasada) $resumen['atrasadas']++;
            if ($paso === 'Por asignar') $resumen['por_asignar']++;

            $formatted[] = [
                'id_orden'      => (int) $o['id_orden'],
                'status'        => $o['status'],
                'cliente'       => $o['cliente'],
                'paso'          => $paso,
                'progreso'      => $total > 0 ? (int) round($terminados * 100 / $total) : 0,
                'unidades'      => (float) ($o['unidades'] ?? 0),
                'urgente'       => $urgente,
                'fecha_inicio'  => $o['fecha_inicio'],
                'fecha_entrega' => $o['fecha_entrega'],
                'atrasada'      => $atrasada,
            ];
        }
        arsort($resumen['por_paso']);

        return $respondJson([
            'total'   => count($formatted),
            'resumen' => $resumen,
            'ordenes' => $formatted,
        ], 200);
    });


    /**
     * GET /internal/ordenes/{id_empresa}/search-by-product
     *
     * Busca órdenes según los productos que contienen, permitiendo filtrar por:
     * - producto (o q): texto en el nombre del producto (ej: 'franela', 'franelas sublimadas', 'dtf')
     * - talla: talla (ej: 'S', 'M', 'L', 'XL', '14', 'Unica')
     * - tela: nombre o tipo de tela (ej: 'ESCOSIA', 'LICRA SPRINT', 'DRY FIT')
     * - corte: tipo de corte (ej: 'Damas', 'Caballeros', 'Niños')
     * - status: estado de la orden. Por defecto ('en_curso' o vacío) excluye órdenes 'entregada' y 'cancelada'.
     *   Permite también 'todas' o un estado puntual.
     * - limit: cantidad máxima (default 20, max 50)
     * - offset: paginación
     *
     * Header: Authorization: {id_empresa}
     * Respuesta 200: { total: number, filters: {...}, ordenes: [...] }
     */
    $app->get('/internal/ordenes/{id_empresa}/search-by-product', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $params = $request->getQueryParams();
        $productoParam = trim((string) ($params['producto'] ?? ($params['q'] ?? '')));
        $tallaParam = trim((string) ($params['talla'] ?? ''));
        $telaParam = trim((string) ($params['tela'] ?? ''));
        $corteParam = trim((string) ($params['corte'] ?? ''));
        $statusParam = trim((string) ($params['status'] ?? ''));

        if ($productoParam === '' && $tallaParam === '' && $telaParam === '' && $corteParam === '') {
            return $respondJson([
                'error'   => 'bad_request',
                'message' => 'Debe especificar al menos un criterio de búsqueda (producto, talla, tela o corte).',
            ], 400);
        }

        $limit = filter_var($params['limit'] ?? 20, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $offset = filter_var($params['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($offset === false || $offset < 0) $offset = 0;

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/search-by-product] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';
        $likeOp = DB_DRIVER === 'pgsql' ? 'ILIKE' : 'LIKE';

        try {
            $tenantConnection = new LocalDB();

            $whereConditions = [];
            $queryParams = [];

            // Helper para despluralizar en español (ej. franelas -> franela, gorras -> gorra, pantalones -> pantalon)
            $stripSpanishPlural = function (string $term): string {
                $term = trim($term);
                $len = mb_strlen($term);
                if ($len > 4 && mb_substr($term, -2) === 'es') {
                    return mb_substr($term, 0, $len - 2);
                } elseif ($len > 3 && mb_substr($term, -1) === 's') {
                    return mb_substr($term, 0, $len - 1);
                }
                return $term;
            };

            // 1. Filtro por producto (nombre)
            if ($productoParam !== '') {
                $termNorm = $stripSpanishPlural($productoParam);
                if (strtolower($termNorm) !== strtolower($productoParam)) {
                    $whereConditions[] = "(op.name {$likeOp} ? OR op.name {$likeOp} ?)";
                    $queryParams[] = '%' . $productoParam . '%';
                    $queryParams[] = '%' . $termNorm . '%';
                } else {
                    $whereConditions[] = "op.name {$likeOp} ?";
                    $queryParams[] = '%' . $productoParam . '%';
                }
            }

            // 2. Filtro por talla
            if ($tallaParam !== '') {
                $whereConditions[] = "(LOWER(TRIM(COALESCE(s.nombre, ''))) = LOWER(?) OR LOWER(TRIM(COALESCE(op.talla, ''))) = LOWER(?) OR COALESCE(op.talla, '') {$likeOp} ?)";
                $queryParams[] = $tallaParam;
                $queryParams[] = $tallaParam;
                $queryParams[] = '%' . $tallaParam . '%';
            }

            // 3. Filtro por tela
            if ($telaParam !== '') {
                $whereConditions[] = "(COALESCE(op.tela, '') {$likeOp} ? OR COALESCE(ct.tela, '') {$likeOp} ?)";
                $queryParams[] = '%' . $telaParam . '%';
                $queryParams[] = '%' . $telaParam . '%';
            }

            // 4. Filtro por corte
            if ($corteParam !== '') {
                $whereConditions[] = "COALESCE(op.corte, '') {$likeOp} ?";
                $queryParams[] = '%' . $corteParam . '%';
            }

            // 5. Filtro por status de la orden
            $stLower = strtolower($statusParam);
            if ($statusParam === '' || in_array($stLower, ['en_curso', 'activas', 'activas_o_pendientes', 'vivas', 'taller'])) {
                // Comportamiento por defecto solicitado por el usuario: órdenes en curso (no entregadas ni canceladas)
                $whereConditions[] = "LOWER(o.status) NOT IN ('entregada', 'cancelada')";
            } elseif (in_array($stLower, ['todas', 'todos', 'all', '*'])) {
                // Sin filtro de status
            } else {
                if (strpos($stLower, 'activa') !== false || strpos($stLower, 'producc') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%activa%'";
                } elseif (strpos($stLower, 'espera') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%espera%'";
                } elseif (strpos($stLower, 'terminad') !== false || strpos($stLower, 'lista') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%terminad%'";
                } elseif (strpos($stLower, 'entregad') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%entregad%'";
                } elseif (strpos($stLower, 'pausad') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%pausad%'";
                } elseif (strpos($stLower, 'cancelad') !== false) {
                    $whereConditions[] = "LOWER(o.status) LIKE '%cancelad%'";
                } else {
                    $whereConditions[] = "LOWER(o.status) = LOWER(?)";
                    $queryParams[] = $statusParam;
                }
            }

            $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "
                SELECT DISTINCT
                    o._id AS id_orden,
                    o.status,
                    o.cliente_nombre,
                    c.nombre AS vendedor,
                    o.fecha_inicio,
                    o.fecha_entrega,
                    o.pago_total,
                    o.id_wp,
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
                JOIN {$dbName}ordenes_productos op ON op.id_orden = o._id
                LEFT JOIN {$dbName}sizes s ON s._id = op.id_size
                LEFT JOIN {$dbName}catalogo_telas ct ON ct._id = op.id_tela
                LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = o.responsable
                {$whereSql}
                ORDER BY o._id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $orders = $tenantConnection->goQuery($sql, $queryParams);
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al buscar órdenes por producto');
            }

            if (empty($orders)) {
                $tenantConnection->disconnect();
                return $respondJson([
                    'total'   => 0,
                    'filters' => [
                        'producto' => $productoParam ?: null,
                        'talla'    => $tallaParam ?: null,
                        'tela'     => $telaParam ?: null,
                        'corte'    => $corteParam ?: null,
                        'status'   => $statusParam ?: 'en_curso (no entregadas ni canceladas)',
                    ],
                    'ordenes' => [],
                ], 200);
            }

            // Consultar todos los productos de las órdenes coincidentes
            $orderIds = array_column($orders, 'id_orden');
            $productsByOrder = [];
            if (!empty($orderIds)) {
                $idsStr = implode(',', array_map('intval', $orderIds));
                $prodRows = $tenantConnection->goQuery("
                    SELECT
                        op._id,
                        op.id_orden,
                        op.name,
                        op.cantidad,
                        COALESCE(s.nombre, op.talla, '') AS talla,
                        COALESCE(op.tela, ct.tela, '') AS tela,
                        op.corte,
                        op.precio_unitario AS precio
                    FROM {$dbName}ordenes_productos op
                    LEFT JOIN {$dbName}sizes s ON s._id = op.id_size
                    LEFT JOIN {$dbName}catalogo_telas ct ON ct._id = op.id_tela
                    WHERE op.id_orden IN ({$idsStr})
                    ORDER BY op._id ASC
                ");
                if (!isset($prodRows['status']) && is_array($prodRows)) {
                    foreach ($prodRows as $pr) {
                        $oid = (int) $pr['id_orden'];
                        $cant = (float) ($pr['cantidad'] ?? 0);
                        $precio = (float) ($pr['precio'] ?? 0);
                        $productsByOrder[$oid][] = [
                            'id'       => (int) $pr['_id'],
                            'name'     => (string) ($pr['name'] ?? ''),
                            'cantidad' => $cant,
                            'talla'    => (string) ($pr['talla'] ?? ''),
                            'tela'     => (string) ($pr['tela'] ?? ''),
                            'corte'    => (string) ($pr['corte'] ?? ''),
                            'precio'   => $precio,
                            'subtotal' => round($cant * $precio, 2),
                        ];
                    }
                }
            }

            $formatted = [];
            foreach ($orders as $o) {
                $oid = (int) $o['id_orden'];
                $pagoTotal = (float) $o['pago_total'];
                $totalAbonos = (float) $o['total_abonos'];
                $totalDescuentos = (float) $o['total_descuentos'];
                $totalNotasCredito = (float) $o['total_notas_credito'];

                $balance = round($pagoTotal - $totalAbonos - $totalDescuentos + $totalNotasCredito, 2);
                $saldoPendiente = $balance > 0 ? $balance : 0.0;
                $sobrepago = $balance < 0 ? abs($balance) : 0.0;

                $estadoPago = 'pendiente_pago';
                if ($pagoTotal <= 0) {
                    $estadoPago = 'sin_costo';
                } elseif ($sobrepago > 0) {
                    $estadoPago = 'sobrepago';
                } elseif ($saldoPendiente == 0.0) {
                    $estadoPago = 'pagado_total';
                } elseif ($totalAbonos > 0) {
                    $estadoPago = 'abono_parcial';
                }

                $allProds = $productsByOrder[$oid] ?? [];

                // Separar productos coincidentes con los filtros para facilitar lectura
                $coincidentes = [];
                foreach ($allProds as $p) {
                    $match = true;
                    if ($productoParam !== '') {
                        $pNameLower = mb_strtolower($p['name']);
                        $termNorm = $stripSpanishPlural($productoParam);
                        if (mb_strpos($pNameLower, mb_strtolower($productoParam)) === false && mb_strpos($pNameLower, mb_strtolower($termNorm)) === false) {
                            $match = false;
                        }
                    }
                    if ($match && $tallaParam !== '') {
                        $pTallaLower = mb_strtolower(trim($p['talla']));
                        $tParamLower = mb_strtolower($tallaParam);
                        if ($pTallaLower !== $tParamLower && mb_strpos($pTallaLower, $tParamLower) === false) {
                            $match = false;
                        }
                    }
                    if ($match && $telaParam !== '') {
                        if (mb_strpos(mb_strtolower($p['tela']), mb_strtolower($telaParam)) === false) {
                            $match = false;
                        }
                    }
                    if ($match && $corteParam !== '') {
                        if (mb_strpos(mb_strtolower($p['corte']), mb_strtolower($corteParam)) === false) {
                            $match = false;
                        }
                    }
                    if ($match) {
                        $coincidentes[] = $p;
                    }
                }

                $formatted[] = [
                    'id_orden'               => $oid,
                    'status'                 => $o['status'],
                    'cliente_nombre'         => (string) ($o['cliente_nombre'] ?? ''),
                    'vendedor'               => (string) ($o['vendedor'] ?? 'Sin asignar'),
                    'fecha_inicio'           => $o['fecha_inicio'],
                    'fecha_entrega'          => $o['fecha_entrega'],
                    'pago_total'             => $pagoTotal,
                    'total_abonos'           => $totalAbonos,
                    'total_descuentos'       => $totalDescuentos,
                    'saldo_pendiente'        => $saldoPendiente,
                    'sobrepago'              => $sobrepago,
                    'estado_pago'            => $estadoPago,
                    'productos_coincidentes' => !empty($coincidentes) ? $coincidentes : $allProds,
                    'total_productos_orden'  => count($allProds),
                ];
            }

            // Calcular acumulados de prendas/unidades y desglose
            $totalUnidades = 0.0;
            $unidadesPorTalla = [];
            $unidadesPorTela = [];
            $unidadesPorProducto = [];

            foreach ($formatted as $ordItem) {
                foreach ($ordItem['productos_coincidentes'] as $cp) {
                    $cCant = (float) ($cp['cantidad'] ?? 0);
                    $totalUnidades += $cCant;

                    $cTalla = trim((string) ($cp['talla'] ?? ''));
                    if ($cTalla === '') $cTalla = 'Sin talla';
                    $unidadesPorTalla[$cTalla] = round(($unidadesPorTalla[$cTalla] ?? 0.0) + $cCant, 2);

                    $cTela = trim((string) ($cp['tela'] ?? ''));
                    if ($cTela === '') $cTela = 'Sin tela';
                    $unidadesPorTela[$cTela] = round(($unidadesPorTela[$cTela] ?? 0.0) + $cCant, 2);

                    $cProd = trim((string) ($cp['name'] ?? ''));
                    if ($cProd === '') $cProd = 'Sin nombre';
                    $unidadesPorProducto[$cProd] = round(($unidadesPorProducto[$cProd] ?? 0.0) + $cCant, 2);
                }
            }

            $resumen = [
                'total_ordenes'         => count($formatted),
                'total_unidades'        => round($totalUnidades, 2),
                'unidades_por_talla'    => $unidadesPorTalla,
                'unidades_por_tela'     => $unidadesPorTela,
                'unidades_por_producto' => $unidadesPorProducto,
            ];

            $tenantConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/search-by-product] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al buscar órdenes por producto.'], 500);
        }

        return $respondJson([
            'total'   => count($formatted),
            'resumen' => $resumen,
            'filters' => [
                'producto' => $productoParam ?: null,
                'talla'    => $tallaParam ?: null,
                'tela'     => $telaParam ?: null,
                'corte'    => $corteParam ?: null,
                'status'   => $statusParam ?: 'en_curso (no entregadas ni canceladas)',
            ],
            'ordenes' => $formatted,
        ], 200);
    });


    /**
     * GET /internal/clientes/{id_empresa}/search?q={texto}
     *
     * Busca clientes por NOMBRE COMPLETO (multi-palabra) + teléfono/cédula, usando
     * el helper ninesys_customer_search_where (tokens sobre first_name+' '+last_name,
     * con unaccent en Postgres). Resuelve "nombre apellido" juntos y nombres
     * compuestos. Header: Authorization: {id_empresa}. Respuesta 200:
     * { count, customers: [{ _id, first_name, last_name, phone, cedula, email }] }
     */
    $app->get('/internal/clientes/{id_empresa}/search', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        require_once __DIR__ . '/../lib/customer_search.php';

        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $q = trim($request->getQueryParams()['q'] ?? '');
        if ($q === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Parámetro q (texto de búsqueda) requerido.'], 400);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][clientes/search] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }

        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';
        $driver = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') ? 'pgsql' : 'mysql';

        try {
            $tenantConnection = new LocalDB();
            [$where, $params] = ninesys_customer_search_where($q, 'c.', $driver);
            // ordenes_en_curso / ultima_orden: permiten al operario distinguir
            // clientes homónimos sin conocer su teléfono ni cédula.
            $sql = "SELECT c._id, c.first_name, c.last_name, c.phone, c.cedula, c.email,
                           (SELECT COUNT(*) FROM {$dbName}ordenes o
                             WHERE o.id_wp = c._id AND LOWER(o.status) NOT IN ('entregada', 'cancelada')) AS ordenes_en_curso,
                           (SELECT o._id FROM {$dbName}ordenes o
                             WHERE o.id_wp = c._id ORDER BY o._id DESC LIMIT 1) AS ultima_orden,
                           (SELECT o.fecha_creacion FROM {$dbName}ordenes o
                             WHERE o.id_wp = c._id ORDER BY o._id DESC LIMIT 1) AS fecha_ultima_orden
                    FROM {$dbName}customers c
                    WHERE c.eliminado = 0" . ($where !== '' ? ' AND ' . $where : '') . "
                    ORDER BY c.first_name ASC, c.last_name ASC
                    LIMIT 15";
            $customers = $tenantConnection->goQuery($sql, $params);
            $tenantConnection->disconnect();
            if (isset($customers['status'])) {
                throw new \Exception($customers['message'] ?? 'Error al buscar clientes');
            }
        } catch (\Throwable $e) {
            error_log('[msg_service][clientes/search] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al buscar clientes.'], 500);
        }

        $list = array_map(function ($c) {
            return [
                '_id'        => (int) $c['_id'],
                'first_name' => $c['first_name'],
                'last_name'  => $c['last_name'],
                'phone'      => $c['phone'],
                'cedula'     => $c['cedula'],
                'email'      => $c['email'],
                'ordenes_en_curso'   => (int) ($c['ordenes_en_curso'] ?? 0),
                'ultima_orden'       => isset($c['ultima_orden']) ? (int) $c['ultima_orden'] : null,
                'fecha_ultima_orden' => $c['fecha_ultima_orden'] ?? null,
            ];
        }, (array) $customers);

        return $respondJson(['count' => count($list), 'customers' => $list], 200);
    });


    /**
     * GET /internal/clientes/{id_empresa}/estado-cuenta?customer_id={id}[&phone=][&incluir_entregadas_pagadas=1]
     *
     * Estado de cuenta de un cliente: órdenes relevantes con sus totales/saldo,
     * pagos (método, moneda, monto, tasa, equivalente en moneda base, referencia,
     * verificado) y ajustes (descuentos / notas de crédito).
     *
     * Órdenes relevantes (regla de negocio 2026-09-29): toda orden NO entregada ni
     * cancelada (cualquier estado, incluidos futuros), más las entregadas que aún
     * tengan saldo pendiente. Canceladas nunca. Con incluir_entregadas_pagadas=1 se
     * incluyen también las entregadas ya saldadas.
     * saldo = total - abonos - descuentos + notas_credito (misma fórmula que by-phone).
     */
    $app->get('/internal/clientes/{id_empresa}/estado-cuenta', function (Request $request, Response $response, $args) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };

        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }

        $qp = $request->getQueryParams();
        $customerIdParam = filter_var($qp['customer_id'] ?? '', FILTER_VALIDATE_INT);
        $phone = trim($qp['phone'] ?? '');
        $idOrdenParam = filter_var($qp['id_orden'] ?? '', FILTER_VALIDATE_INT);
        // Nombre del cliente tal como lo escribió el usuario: se usa para verificar que
        // el customer_id corresponda a esa persona (el LLM a veces inventa IDs).
        $nombreParam = trim((string) ($qp['nombre'] ?? ''));
        $incluirPagadas = in_array(strtolower((string) ($qp['incluir_entregadas_pagadas'] ?? '')), ['1', 'true', 'si', 'sí'], true);
        $tieneId = $customerIdParam !== false && $customerIdParam > 0;
        $tieneOrden = $idOrdenParam !== false && $idOrdenParam > 0;
        if (!$tieneId && !$tieneOrden && $phone === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Se requiere id_orden, customer_id o phone.'], 400);
        }

        // ¿Todas las palabras del nombre dado aparecen en el nombre real? (sin tildes/mayúsculas)
        $norm = function ($s) {
            $s = mb_strtolower((string) $s, 'UTF-8');
            return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        };
        $nombreCoincide = function ($dado, $real) use ($norm) {
            $real = $norm($real);
            $tokens = array_filter(preg_split('/\s+/', $norm($dado)), fn($t) => mb_strlen($t) >= 3);
            foreach ($tokens as $t) {
                if (strpos($real, $t) === false) {
                    return false;
                }
            }
            return true;
        };

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1',
                [$idEmpresa]
            );
            $localConnection->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][estado-cuenta] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }
        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $db = new LocalDB();

            // 1. Cliente. Prioridad: la orden (el servidor deduce el dueño, no depende de
            //    un ID que el modelo pudo inventar) > customer_id > teléfono.
            $clienteDesdeOrden = null;
            if ($tieneOrden) {
                $ord = $db->goQuery("SELECT id_wp FROM {$dbName}ordenes WHERE _id = ? LIMIT 1", [$idOrdenParam]);
                if (isset($ord['status']) || empty($ord)) {
                    $db->disconnect();
                    return $respondJson(['found' => false, 'motivo' => 'orden_no_existe', 'id_orden' => $idOrdenParam], 200);
                }
                $clienteDesdeOrden = (int) $ord[0]['id_wp'];
            }

            if ($clienteDesdeOrden !== null || $tieneId) {
                $cust = $db->goQuery(
                    "SELECT _id, first_name, last_name, phone, cedula FROM {$dbName}customers WHERE _id = ? LIMIT 1",
                    [$clienteDesdeOrden ?? $customerIdParam]
                );
            } else {
                $cust = $db->goQuery(
                    "SELECT _id, first_name, last_name, phone, cedula FROM {$dbName}customers WHERE phone = ? LIMIT 1",
                    [$phone]
                );
            }
            if (isset($cust['status']) || empty($cust)) {
                $db->disconnect();
                return $respondJson(['found' => false, 'motivo' => 'cliente_no_existe'], 200);
            }
            $c = $cust[0];
            $customerId = (int) $c['_id'];
            $nombreReal = trim(preg_replace('/\s+/', ' ', $c['first_name'] . ' ' . $c['last_name']));

            // Verificación de identidad: un customer_id que no corresponde al nombre dado
            // NO devuelve datos (evita mostrar el estado de cuenta de otra persona).
            if ($clienteDesdeOrden === null && $tieneId && $nombreParam !== '' && !$nombreCoincide($nombreParam, $nombreReal)) {
                $db->disconnect();
                return $respondJson([
                    'found'         => false,
                    'motivo'        => 'id_no_coincide_con_nombre',
                    'customer_id'   => $customerId,
                    'nombre_dado'   => $nombreParam,
                    'nombre_del_id' => $nombreReal,
                ], 200);
            }
            // Si vino por orden y el nombre dado no es el del dueño, se avisa (los datos
            // son de la orden pedida, que es lo verificable).
            $advertencia = null;
            if ($clienteDesdeOrden !== null && $nombreParam !== '' && !$nombreCoincide($nombreParam, $nombreReal)) {
                $advertencia = "La orden {$idOrdenParam} pertenece a {$nombreReal}, no a \"{$nombreParam}\".";
            }

            // 2. Órdenes no canceladas del cliente, con totales
            $orders = $db->goQuery("
                SELECT o._id AS id_orden, o.status, o.fecha_creacion, o.fecha_entrega, o.pago_total,
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
                WHERE o.id_wp = ? AND LOWER(o.status) <> 'cancelada'
                ORDER BY o._id DESC
            ", [$customerId]);
            if (isset($orders['status'])) {
                throw new \Exception($orders['message'] ?? 'Error al buscar órdenes');
            }

            $saldoTotal = 0.0;
            $ordenes = [];
            foreach ((array) $orders as $o) {
                $total = (float) $o['pago_total'];
                $abonos = (float) $o['total_abonos'];
                $desc = (float) $o['total_descuentos'];
                $nc = (float) $o['total_notas_credito'];
                $saldo = round($total - $abonos - $desc + $nc, 2);
                if ($saldo > 0) {
                    $saldoTotal += $saldo;
                }
                $entregada = strtolower((string) $o['status']) === 'entregada';
                if ($tieneOrden) {
                    // Consulta de una orden concreta: solo esa, en cualquier estado.
                    if ((int) $o['id_orden'] !== $idOrdenParam) {
                        continue;
                    }
                } elseif ($entregada && $saldo <= 0 && !$incluirPagadas) {
                    // Regla: no entregadas siempre; entregadas solo con deuda (o si se piden las pagadas).
                    continue;
                }
                $ordenes[] = [
                    'id_orden'            => (int) $o['id_orden'],
                    'status'              => $o['status'],
                    'fecha_creacion'      => $o['fecha_creacion'],
                    'fecha_entrega'       => $o['fecha_entrega'],
                    'pago_total'          => $total,
                    'total_abonos'        => $abonos,
                    'total_descuentos'    => $desc,
                    'total_notas_credito' => $nc,
                    'saldo_pendiente'     => $saldo,
                    'entregada_con_deuda' => $entregada && $saldo > 0,
                ];
            }

            $ids = array_column($ordenes, 'id_orden');
            $pagos = [];
            $ajustes = [];
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));

                // 3. Pagos (desglose por método/moneda). Más recientes primero, tope 100.
                $mp = $db->goQuery("
                    SELECT m.id_orden, m.moment, m.metodo_pago, m.moneda, m.monto, m.tasa, m.detalle,
                           m.tipo_de_pago, m.verificado
                    FROM {$dbName}metodos_de_pago m
                    WHERE m.id_orden IN ({$ph})
                    ORDER BY m.moment DESC
                    LIMIT 100
                ", $ids);
                if (!isset($mp['status'])) {
                    foreach ((array) $mp as $p) {
                        $monto = (float) $p['monto'];
                        $tasa = (float) $p['tasa'];
                        $pagos[] = [
                            'fecha'        => $p['moment'],
                            'id_orden'     => (int) $p['id_orden'],
                            'metodo_pago'  => $p['metodo_pago'],
                            'moneda'       => $p['moneda'],
                            'monto'        => $monto,
                            'tasa'         => $tasa,
                            'monto_base'   => $tasa > 0 ? round($monto / $tasa, 2) : $monto,
                            'referencia'   => $p['detalle'],
                            'tipo_de_pago' => $p['tipo_de_pago'],
                            'verificado'   => in_array($p['verificado'], [true, 1, '1', 't', 'true'], true),
                            'sin_abono'    => false,
                        ];
                    }

                    // Detección de posibles duplicados (independiente de la hora: en el
                    // histórico abonos y metodos_de_pago tienen un desfase de zona horaria,
                    // así que emparejar por instante da falsos positivos). Se marca un pago
                    // solo si (1) los pagos de la orden EXCEDEN lo abonado y (2) existe un
                    // pago idéntico anterior en la misma orden (método, moneda, monto,
                    // referencia). Esos registros no suman al saldo (que sale de abonos).
                    $abonadoPorOrden = [];
                    foreach ($ordenes as $o) {
                        $abonadoPorOrden[$o['id_orden']] = $o['total_abonos'];
                    }
                    $basePorOrden = [];
                    foreach ($pagos as $p) {
                        $basePorOrden[$p['id_orden']] = ($basePorOrden[$p['id_orden']] ?? 0) + $p['monto_base'];
                    }
                    $vistos = [];
                    // $pagos viene del más reciente al más antiguo: recorrer al revés para
                    // conservar el primer registro y marcar las repeticiones posteriores.
                    for ($i = count($pagos) - 1; $i >= 0; $i--) {
                        $p = $pagos[$i];
                        $exceso = ($basePorOrden[$p['id_orden']] ?? 0) - ($abonadoPorOrden[$p['id_orden']] ?? 0);
                        $clave = $p['id_orden'] . '|' . $p['metodo_pago'] . '|' . $p['moneda'] . '|'
                            . number_format($p['monto'], 2, '.', '') . '|' . trim((string) $p['referencia']);
                        if (isset($vistos[$clave]) && $exceso >= $p['monto_base'] - 0.5) {
                            $pagos[$i]['sin_abono'] = true;
                            $basePorOrden[$p['id_orden']] -= $p['monto_base'];
                        } else {
                            $vistos[$clave] = true;
                        }
                    }
                }

                // 4. Ajustes: descuentos y notas de crédito
                $aj = $db->goQuery("
                    SELECT id_orden, moment, descuento, nota_credito, detalle
                    FROM {$dbName}abonos
                    WHERE id_orden IN ({$ph}) AND (descuento > 0 OR nota_credito > 0)
                    ORDER BY moment DESC
                ", $ids);
                if (!isset($aj['status'])) {
                    foreach ((array) $aj as $a) {
                        $ajustes[] = [
                            'fecha'        => $a['moment'],
                            'id_orden'     => (int) $a['id_orden'],
                            'descuento'    => (float) $a['descuento'],
                            'nota_credito' => (float) $a['nota_credito'],
                            'detalle'      => $a['detalle'],
                        ];
                    }
                }
            }
            $db->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][estado-cuenta] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar el estado de cuenta.'], 500);
        }

        $resumen = [
            'ordenes_listadas'           => count($ordenes),
            'total_facturado'            => round(array_sum(array_column($ordenes, 'pago_total')), 2),
            'total_abonado'              => round(array_sum(array_column($ordenes, 'total_abonos')), 2),
            'total_descuentos'           => round(array_sum(array_column($ordenes, 'total_descuentos')), 2),
            'total_notas_credito'        => round(array_sum(array_column($ordenes, 'total_notas_credito')), 2),
            'saldo_total_pendiente'      => round($saldoTotal, 2),
            'entregadas_con_deuda'       => count(array_filter($ordenes, fn($o) => $o['entregada_con_deuda'])),
            'pagos_sin_verificar'        => count(array_filter($pagos, fn($p) => !$p['verificado'] && !$p['sin_abono'])),
            'pagos_sin_abono'            => count(array_filter($pagos, fn($p) => $p['sin_abono'])),
        ];

        return $respondJson([
            'found'       => true,
            'id_orden'    => $tieneOrden ? $idOrdenParam : null,
            'advertencia' => $advertencia,
            'customer' => [
                '_id'    => $customerId,
                'nombre' => $nombreReal,
                'phone'  => $c['phone'],
                'cedula' => $c['cedula'],
            ],
            'resumen'  => $resumen,
            'ordenes'  => $ordenes,
            'pagos'    => $pagos,
            'ajustes'  => $ajustes,
        ], 200);
    });


    /**
     * Resuelve el db_name de la empresa (activa) a partir del header Authorization.
     * Devuelve [idEmpresa, dbPrefix] o una Response de error.
     */
    $resolverEmpresaInterna = function (Request $request, callable $respondJson) {
        $authHeader = $request->getHeader('Authorization')[0] ?? '';
        $idEmpresa = filter_var($authHeader, FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Authorization inválido.'], 400);
        }
        try {
            $central = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $central->goQuery('SELECT db_name FROM empresas WHERE id_empresa = ? AND activo = 1', [$idEmpresa]);
            $central->disconnect();
        } catch (\Throwable $e) {
            error_log('[msg_service][disenos] Error central empresa ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar empresa.'], 500);
        }
        if (empty($rows) || isset($rows['status'])) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }
        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';
        return [$idEmpresa, $dbName];
    };


    /**
     * GET /internal/ordenes/{id_empresa}/disenos?id={id_orden}
     *
     * Propuestas de diseño (tabla revisiones) de una orden: tipo, estado
     * (Aprobado / Rechazado / Esperando Respuesta), nº de revisión, imagen y detalles.
     * La "imagen aprobada" final de la orden no está aquí (vive en el CDN como
     * {orden}-a.{ext}); la resuelve el MCP contra el CDN.
     */
    $app->get('/internal/ordenes/{id_empresa}/disenos', function (Request $request, Response $response, $args) use ($resolverEmpresaInterna) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };
        $res = $resolverEmpresaInterna($request, $respondJson);
        if (!is_array($res)) {
            return $res;
        }
        [$idEmpresa, $dbName] = $res;

        $idOrden = filter_var($request->getQueryParams()['id'] ?? '', FILTER_VALIDATE_INT);
        if ($idOrden === false || $idOrden <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Parámetro id (id_orden) requerido.'], 400);
        }

        try {
            $db = new LocalDB();
            $orden = $db->goQuery("SELECT _id FROM {$dbName}ordenes WHERE _id = ? LIMIT 1", [$idOrden]);
            if (isset($orden['status']) || empty($orden)) {
                $db->disconnect();
                return $respondJson(['found' => false], 200);
            }
            $rows = $db->goQuery("
                SELECT _id, revision, tipo, estatus, url_image, detalles, moment, id_product
                FROM {$dbName}revisiones
                WHERE id_orden = ?
                ORDER BY _id ASC
            ", [$idOrden]);
            $db->disconnect();
            if (isset($rows['status'])) {
                throw new \Exception($rows['message'] ?? 'Error al buscar revisiones');
            }
        } catch (\Throwable $e) {
            error_log('[msg_service][ordenes/disenos] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar los diseños.'], 500);
        }

        $revisiones = array_map(function ($r) {
            return [
                '_id'        => (int) $r['_id'],
                'revision'   => $r['revision'] !== null ? (int) $r['revision'] : null,
                'tipo'       => $r['tipo'],
                'estatus'    => $r['estatus'],
                'url_image'  => $r['url_image'] ?: null,
                'detalles'   => $r['detalles'],
                'fecha'      => $r['moment'],
                'id_product' => $r['id_product'] !== null ? (int) $r['id_product'] : null,
            ];
        }, (array) $rows);

        return $respondJson(['found' => true, 'id_orden' => $idOrden, 'revisiones' => $revisiones], 200);
    });


    /**
     * GET /internal/disenos/{id_empresa}/pendientes
     *
     * Propuestas de diseño esperando respuesta del cliente (con imagen), con el nº
     * de orden y el nombre del cliente. Tope 30, más recientes primero.
     */
    $app->get('/internal/disenos/{id_empresa}/pendientes', function (Request $request, Response $response, $args) use ($resolverEmpresaInterna) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };
        $res = $resolverEmpresaInterna($request, $respondJson);
        if (!is_array($res)) {
            return $res;
        }
        [$idEmpresa, $dbName] = $res;

        try {
            $db = new LocalDB();
            $rows = $db->goQuery("
                SELECT r._id, r.id_orden, r.revision, r.tipo, r.url_image, r.detalles, r.moment,
                       o.status AS status_orden,
                       CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) AS cliente
                FROM {$dbName}revisiones r
                JOIN {$dbName}ordenes o ON o._id = r.id_orden
                LEFT JOIN {$dbName}customers c ON c._id = o.id_wp
                WHERE r.estatus = 'Esperando Respuesta'
                  AND r.url_image IS NOT NULL AND r.url_image <> ''
                  AND LOWER(o.status) <> 'cancelada'
                ORDER BY r.moment DESC
                LIMIT 30
            ", []);
            $db->disconnect();
            if (isset($rows['status'])) {
                throw new \Exception($rows['message'] ?? 'Error al buscar pendientes');
            }
        } catch (\Throwable $e) {
            error_log('[msg_service][disenos/pendientes] Error tenant ' . $idEmpresa . ': ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar diseños pendientes.'], 500);
        }

        $list = array_map(function ($r) {
            return [
                '_id'          => (int) $r['_id'],
                'id_orden'     => (int) $r['id_orden'],
                'revision'     => $r['revision'] !== null ? (int) $r['revision'] : null,
                'tipo'         => $r['tipo'],
                'url_image'    => $r['url_image'],
                'detalles'     => $r['detalles'],
                'fecha'        => $r['moment'],
                'status_orden' => $r['status_orden'],
                'cliente'      => trim(preg_replace('/\s+/', ' ', $r['cliente'])),
            ];
        }, (array) $rows);

        return $respondJson(['count' => count($list), 'pendientes' => $list], 200);
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
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
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
        $nombre    = trim($body['nombre']    ?? '');
        $apellido  = trim($body['apellido']  ?? '');
        $cedula    = trim($body['cedula']    ?? '');
        $telefono  = trim($body['telefono']  ?? '');
        $email     = strtolower(trim($body['email']    ?? ''));
        $direccion = trim($body['direccion'] ?? '');

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

        // $tenantConnection ya está conectado directamente a la BD del tenant; el prefijo solo
        // aplica en mysql (en pgsql "bd.tabla" cross-database no existe en una sola conexión).
        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            $conditions = [];
            $condParams = [];
            $digits = preg_replace('/\D/', '', $telefono);
            $regexpReplaceExpr = DB_DRIVER === 'pgsql'
                ? "REGEXP_REPLACE(phone, '[^0-9]', '', 'g')"
                : "REGEXP_REPLACE(phone, '[^0-9]', '')";
            if (strlen($digits) >= 7) {
                $last10 = substr($digits, -10);
                $conditions[] = "{$regexpReplaceExpr} LIKE ?";
                $condParams[] = '%' . $last10;
            } else {
                $conditions[] = 'phone = ?';
                $condParams[] = $telefono;
            }
            if ($cedula !== '' && $cedula !== 'none') {
                $conditions[] = 'cedula = ?';
                $condParams[] = $cedula;
            }
            $existingRows = $tenantConnection->goQuery(
                "SELECT _id FROM {$dbName}customers WHERE (" . implode(' OR ', $conditions) . ') LIMIT 1',
                $condParams
            );

            if (!empty($existingRows) && !isset($existingRows['status'])) {
                $customerId = (int) $existingRows[0]['_id'];
                $tenantConnection->goQuery(
                    "UPDATE {$dbName}customers
                     SET first_name = ?, last_name = ?, cedula = ?,
                         phone = ?, email = ?, address = ?
                     WHERE _id = ?",
                    [$nombre, $apellido, $cedula, $telefono, $email, $direccion, $customerId]
                );
                $tenantConnection->disconnect();
                return $respondJson(['id_customer' => $customerId, 'upserted' => true], 200);
            }

            $createResult = $tenantConnection->goQuery(
                "INSERT INTO {$dbName}customers (first_name, last_name, cedula, phone, email, address)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$nombre, $apellido, $cedula, $telefono, $email, $direccion]
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

        // $tenantConnection ya está conectado directamente a la BD del tenant; el prefijo solo
        // aplica en mysql (en pgsql "bd.tabla" cross-database no existe en una sola conexión).
        $dbName = DB_DRIVER === 'pgsql' ? '' : '`' . $rows[0]['db_name'] . '`.';

        try {
            $tenantConnection = new LocalDB();

            $existing = $tenantConnection->goQuery(
                "SELECT _id FROM {$dbName}presupuestos WHERE _id = ? LIMIT 1",
                [$idPresupuesto]
            );

            if (empty($existing) || isset($existing['status'])) {
                $tenantConnection->disconnect();
                return $respondJson(['error' => 'not_found', 'message' => "Presupuesto {$idPresupuesto} no encontrado."], 404);
            }

            $tenantConnection->goQuery(
                "UPDATE {$dbName}presupuestos SET responsable = ? WHERE _id = ?",
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
