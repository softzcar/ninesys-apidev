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
     * `reason` posibles en 422:
     *   - horario_laboral_empty
     *   - horario_laboral_invalid_json
     *   - horario_laboral_not_object
     *   - horario_laboral_missing_keys  (+ `missing: [...]`)
     *   - horario_laboral_invalid_time_format  (+ `invalid: {...}`)
     *   - dias_laborales_not_array
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

        // --- 9. Validar formato de horas (HH:MM 24h). Vacíos permitidos para
        //     tramos opcionales (ej: empresa que no tiene turno tarde). ---
        $timeKeys = ['horaInicioManana', 'horaFinManana', 'horaInicioTarde', 'horaFinTarde'];
        $invalidTimes = [];
        foreach ($timeKeys as $k) {
            $v = $horario[$k];
            if ($v === null || $v === '') continue;
            if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                $invalidTimes[$k] = $v;
            }
        }
        if (!empty($invalidTimes)) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => 'Uno o más horarios tienen un formato inválido (esperado HH:MM 24h).',
                'reason'  => 'horario_laboral_invalid_time_format',
                'invalid' => $invalidTimes,
            ], 422);
        }

        // --- 10. Validar diasLaborales como array ---
        if (!is_array($horario['diasLaborales'])) {
            return $respondJson([
                'error'   => 'unprocessable_entity',
                'message' => 'El campo diasLaborales debe ser un array.',
                'reason'  => 'dias_laborales_not_array',
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

};
