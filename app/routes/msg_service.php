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
     * Devuelve UNA orden concreta por su _id, con el mismo detalle que by-phone
     * (estado, entrega, totales, saldo, productos) más los datos del cliente.
     * Header: Authorization: {id_empresa}
     * Respuesta 200: { found:true, customer_id, customer_name, orden:{...} } | { found:false }
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

            $ordersQuery = "
                SELECT
                    o._id AS id_orden,
                    o.status,
                    o.fecha_entrega,
                    o.pago_total,
                    o.id_wp,
                    COALESCE((SELECT SUM(a.abono) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_abonos,
                    COALESCE((SELECT SUM(a.descuento) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_descuentos,
                    COALESCE((SELECT SUM(a.nota_credito) FROM {$dbName}abonos a WHERE a.id_orden = o._id), 0) AS total_notas_credito
                FROM {$dbName}ordenes o
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
            $customerId = (int) $o['id_wp'];

            // Datos del cliente dueño de la orden
            $customerName = '';
            $cust = $tenantConnection->goQuery(
                "SELECT first_name, last_name FROM {$dbName}customers WHERE _id = ? LIMIT 1",
                [$customerId]
            );
            if (!isset($cust['status']) && !empty($cust)) {
                $customerName = trim($cust[0]['first_name'] . ' ' . $cust[0]['last_name']);
            }

            // Productos de la orden
            $products = $tenantConnection->goQuery(
                "SELECT name, cantidad, talla AS detalle_tallas FROM {$dbName}ordenes_productos WHERE id_orden = ?",
                [$idOrden]
            );
            if (isset($products['status'])) {
                $products = [];
            }

            $totalAbonos = (float) $o['total_abonos'];
            $totalDescuentos = (float) $o['total_descuentos'];
            $totalNotasCredito = (float) $o['total_notas_credito'];
            $pagoTotal = (float) $o['pago_total'];
            $saldoPendiente = $pagoTotal - $totalAbonos - $totalDescuentos + $totalNotasCredito;

            $orden = [
                'id_orden'         => (int) $o['id_orden'],
                'status'           => $o['status'],
                'fecha_entrega'    => $o['fecha_entrega'],
                'pago_total'       => $pagoTotal,
                'total_abonos'     => $totalAbonos,
                'total_descuentos' => $totalDescuentos,
                'saldo_pendiente'  => $saldoPendiente,
                'productos'        => array_map(function ($p) {
                    return [
                        'name'           => $p['name'],
                        'cantidad'       => (int) $p['cantidad'],
                        'detalle_tallas' => $p['detalle_tallas'] ?? '',
                    ];
                }, (array) $products),
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
            'orden'         => $orden,
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
        $incluirPagadas = in_array(strtolower((string) ($qp['incluir_entregadas_pagadas'] ?? '')), ['1', 'true', 'si', 'sí'], true);
        if (($customerIdParam === false || $customerIdParam <= 0) && $phone === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Se requiere customer_id o phone.'], 400);
        }

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

            // 1. Cliente (por id preferido, o por teléfono)
            if ($customerIdParam !== false && $customerIdParam > 0) {
                $cust = $db->goQuery(
                    "SELECT _id, first_name, last_name, phone, cedula FROM {$dbName}customers WHERE _id = ? LIMIT 1",
                    [$customerIdParam]
                );
            } else {
                $cust = $db->goQuery(
                    "SELECT _id, first_name, last_name, phone, cedula FROM {$dbName}customers WHERE phone = ? LIMIT 1",
                    [$phone]
                );
            }
            if (isset($cust['status']) || empty($cust)) {
                $db->disconnect();
                return $respondJson(['found' => false], 200);
            }
            $c = $cust[0];
            $customerId = (int) $c['_id'];

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
                // Regla: no entregadas siempre; entregadas solo con deuda (o si se piden las pagadas).
                if ($entregada && $saldo <= 0 && !$incluirPagadas) {
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
            'found'    => true,
            'customer' => [
                '_id'    => $customerId,
                'nombre' => trim($c['first_name'] . ' ' . $c['last_name']),
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
