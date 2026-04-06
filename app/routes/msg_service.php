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
