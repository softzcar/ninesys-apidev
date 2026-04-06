<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


    // CONFIGURACIÓN WIZARD - ADMIN
    $app->post('/configuracion/admin/{id}', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $id = $args['id'];

        // Conectar a la base de datos de empresas
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        $updateFields = [];
        $params = [];

        if (isset($data['nombre'])) {
            $updateFields[] = 'nombre = ?';
            $params[] = $data['nombre'];
        }

        if (isset($data['telefono'])) {
            $updateFields[] = 'telefono = ?';
            $params[] = $data['telefono'];
        }

        if (isset($data['password']) && $data['password'] !== 'null' && !empty($data['password'])) {
            $updateFields[] = 'password = ?';
            $params[] = $data['password'];
        }

        $updateFields[] = 'fecha_actualizacion = NOW()';

        $sql = 'UPDATE empresas_usuarios SET ' . implode(', ', $updateFields) . ' WHERE id_usuario = ?';
        $params[] = $id;

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar el usuario: ' . $result['message']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Usuario actualizado correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CONFIGURACIÓN WIZARD - EMPRESA
    $app->post('/configuracion/empresa/{id}', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $employeeId = $args['id'];

        // Conectar a la base de datos de empresas
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Obtener el id_empresa del empleado
        $sqlEmpresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
        $empresaResult = $localConnection->goQuery($sqlEmpresa, [$employeeId]);

        if (empty($empresaResult) || !isset($empresaResult[0]['id_empresa'])) {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'Empleado no encontrado o no tiene empresa asignada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $companyId = $empresaResult[0]['id_empresa'];

        $updateFields = [];
        $params = [];

        if (isset($data['nombre'])) {
            $updateFields[] = 'nombre = ?';
            $params[] = $data['nombre'];
        }

        if (isset($data['numero_registro_legal'])) {
            $updateFields[] = 'numero_registro_legal = ?';
            $params[] = $data['numero_registro_legal'];
        }

        if (isset($data['pais'])) {
            $updateFields[] = 'pais = ?';
            $params[] = $data['pais'];
        }

        if (isset($data['direccion'])) {
            $updateFields[] = 'direccion = ?';
            $params[] = $data['direccion'];
        }

        if (isset($data['telefono'])) {
            $updateFields[] = 'telefono = ?';
            $params[] = $data['telefono'];
        }

        if (isset($data['email'])) {
            $updateFields[] = 'email = ?';
            $params[] = $data['email'];
        }

        if (empty($updateFields)) {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos para actualizar']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $sql = 'UPDATE empresas SET ' . implode(', ', $updateFields) . ' WHERE id_empresa = ?';
        $params[] = $companyId;

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar la empresa: ' . $result['message']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Empresa actualizada correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CONFIGURACIÓN WIZARD - MONEDAS
    $app->post('/configuracion/monedas', function (Request $request, Response $response, array $args) {
        $rawBody = $request->getBody()->getContents();
        $data = json_decode($rawBody, true);

        // Si falló el decode de JSON (ej: es form-data) o no es array, intentamos getParsedBody
        if (!is_array($data)) {
            $data = $request->getParsedBody();

            // Si getParsedBody falla (puede pasar si no hay Middleware o si ya se leyó el stream), 
            // intentamos parsear manualmente el string raw (que es query string)
            if (empty($data)) {
                parse_str($rawBody, $parsed);
                if (!empty($parsed)) {
                    $data = $parsed;
                }
            }
        }

        $employeeId = $data['id_empleado'] ?? null;
        $monedas = $data['monedas'] ?? null;

        // Si se envió como string JSON (caso URLSearchParams), decodificarlo
        if (is_string($monedas)) {
            $monedas = json_decode($monedas, true);
        }

        if (!$employeeId || !is_array($monedas)) {
            $response->getBody()->write(json_encode(['error' => 'Datos inválidos. Se espera id_empleado y monedas (array o json string).']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withStatus(400);
        }

        // Conectar a la base de datos de empresas
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Obtener el id_empresa del empleado
        $sqlEmpresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
        $empresaResult = $localConnection->goQuery($sqlEmpresa, [$employeeId]);

        if (empty($empresaResult) || !isset($empresaResult[0]['id_empresa'])) {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'Empleado no encontrado']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withStatus(404);
        }

        $companyId = $empresaResult[0]['id_empresa'];

        // Actualizar tipos_de_monedas como JSON
        $monedasJson = json_encode($monedas);
        $sql = 'UPDATE empresas SET tipos_de_monedas = ? WHERE id_empresa = ?';
        $result = $localConnection->goQuery($sql, [$monedasJson, $companyId]);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al guardar monedas: ' . $result['message']]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Monedas guardadas correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*') // CORS FIX
            ->withStatus(200);
    });

    // CONFIGURACIÓN WIZARD - HORARIO
    $app->post('/configuracion/horario', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $employeeId = $data['id_empleado'] ?? null;

        if (!$employeeId) {
            $response->getBody()->write(json_encode(['error' => 'ID de empleado requerido']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Extraer datos de horario (excluir id_empleado)
        $horarioData = array_diff_key($data, ['id_empleado' => '']);

        // Conectar a la base de datos de empresas
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Obtener el id_empresa del empleado
        $sqlEmpresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
        $empresaResult = $localConnection->goQuery($sqlEmpresa, [$employeeId]);

        if (empty($empresaResult) || !isset($empresaResult[0]['id_empresa'])) {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'Empleado no encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $companyId = $empresaResult[0]['id_empresa'];

        // Actualizar horario_laboral como JSON
        $horarioJson = json_encode($horarioData);
        $sql = 'UPDATE empresas SET horario_laboral = ? WHERE id_empresa = ?';
        $result = $localConnection->goQuery($sql, [$horarioJson, $companyId]);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al guardar horario: ' . $result['message']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Horario guardado correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CONFIGURACIÓN PERSONALIZACION

    $app->post('/configuracion/personalizacion', function (Request $request, Response $response) {
        try {
            // Obtener el contenido JSON del body
            $json = $request->getBody()->getContents();
            $data = json_decode($json, true);

            // Verificar que el JSON sea válido
            if (json_last_error() !== JSON_ERROR_NONE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'JSON inválido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $id_empleado = $data['id_empleado'] ?? null;
            $personalizacion = $data['personalizacion'] ?? null;

            // Validar datos requeridos
            if (!$id_empleado || !$personalizacion) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Faltan datos requeridos: id_empleado y personalizacion'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Conectar a la base de datos de empresas
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

            // Obtener información de conexión de la empresa usando el id_empleado
            $sql_empresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
            $conn = $localConnection->goQuery($sql_empresa, [$id_empleado]);

            if (!$conn || empty($conn)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $id_empresa = $conn[0]['id_empresa'];

            // Obtener detalles de conexión de la empresa
            $connectionDetails = $localConnection->getConnectionDetails($id_empresa);

            if (!$connectionDetails) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'No se pudieron obtener los detalles de conexión de la empresa'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Cambiar a la base de datos de la empresa
            $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
            $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

            // Verificar si ya existe un registro de config para esta empresa
            $existingConfig = $localConnection->goQuery('SELECT _id FROM config WHERE _id = 1');

            // Preparar valores para la consulta (7 campos ahora)
            $valores = [
                $personalizacion['sys_mostrar_detalle_terminar_indicidual'] ? 1 : 0,
                $personalizacion['sys_mostrar_rollo_en_empleado_corte'] ? 1 : 0,
                $personalizacion['sys_mostrar_rollo_en_empleado_estampado'] ? 1 : 0,
                $personalizacion['sys_mostrar_insumo_en_empleado_costura'] ? 1 : 0,
                $personalizacion['sys_mostrar_insumo_en_empleado_limpieza'] ? 1 : 0,
                $personalizacion['sys_mostrar_insumo_en_empleado_revision'] ? 1 : 0,
                $personalizacion['sys_comision_de_costura'] ? 1 : 0
            ];

            $debug_info = [
                'id_empleado' => $id_empleado,
                'id_empresa' => $id_empresa,
                'personalizacion_recibida' => $personalizacion,
                'valores_a_guardar' => $valores,
                'config_existe' => !empty($existingConfig)
            ];

            if ($existingConfig && !empty($existingConfig)) {
                // Actualizar registro existente
                $updateQuery = '
                UPDATE config SET
                    sys_mostrar_detalle_terminar_indicidual = ?,
                    sys_mostrar_rollo_en_empleado_corte = ?,
                    sys_mostrar_rollo_en_empleado_estampado = ?,
                    sys_mostrar_insumo_en_empleado_costura = ?,
                    sys_mostrar_insumo_en_empleado_limpieza = ?,
                    sys_mostrar_insumo_en_empleado_revision = ?,
                    sys_comision_de_costura = ?
                WHERE _id = 1
            ';

                $updateResult = $localConnection->goQuery($updateQuery, $valores);

                $debug_info['sql_ejecutado'] = $updateQuery;
                $debug_info['parametros'] = $valores;
                $debug_info['resultado_update'] = $updateResult;

                // Verificar si realmente se actualizó
                $checkUpdate = $localConnection->goQuery('SELECT * FROM config WHERE _id = 1');
                $debug_info['config_despues_update'] = $checkUpdate[0] ?? null;
            } else {
                // Crear nuevo registro
                $insertQuery = '
                INSERT INTO config (
                    _id,
                    sys_mostrar_detalle_terminar_indicidual,
                    sys_mostrar_rollo_en_empleado_corte,
                    sys_mostrar_rollo_en_empleado_estampado,
                    sys_mostrar_insumo_en_empleado_costura,
                    sys_mostrar_insumo_en_empleado_limpieza,
                    sys_mostrar_insumo_en_empleado_revision,
                    sys_comision_de_costura,
                    created_at
                ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, NOW())
            ';

                $insertResult = $localConnection->goQuery($insertQuery, $valores);

                $debug_info['sql_ejecutado'] = $insertQuery;
                $debug_info['parametros'] = $valores;
                $debug_info['resultado_insert'] = $insertResult;
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Opciones de personalización guardadas correctamente',
                'debug_info' => $debug_info
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'debug_info' => [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // CONFIGURACIÓN DE GASTOS FIJOS

    $app->post('/configuracion/gastos', function (Request $request, Response $response) {
        try {
            // Obtener el contenido JSON del body
            $json = $request->getBody()->getContents();
            $data = json_decode($json, true);

            // Verificar que el JSON sea válido
            if (json_last_error() !== JSON_ERROR_NONE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'JSON inválido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $id_empleado = $data['id_empleado'] ?? null;
            $gastos = $data['gastos'] ?? null;

            // Validar datos requeridos
            if (!$id_empleado || !is_array($gastos)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Faltan datos requeridos: id_empleado y gastos (array)'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Conectar a la base de datos de empresas
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

            // Obtener id_empresa del empleado
            $sql_empresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
            $conn = $localConnection->goQuery($sql_empresa, [$id_empleado]);

            if (!$conn || empty($conn)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $id_empresa = $conn[0]['id_empresa'];

            $debug_info = [
                'id_empleado' => $id_empleado,
                'id_empresa' => $id_empresa,
                'gastos_recibidos' => $gastos
            ];

            // Eliminar gastos existentes para esta empresa
            $deleteQuery = 'DELETE FROM empresas_gastos WHERE id_empresa = ?';
            $deleteResult = $localConnection->goQuery($deleteQuery, [$id_empresa]);
            $debug_info['resultado_delete'] = $deleteResult;

            // Insertar los nuevos gastos
            $insertCount = 0;
            foreach ($gastos as $gasto) {
                // Validar campos requeridos
                if (empty($gasto['nombre']) || !isset($gasto['monto'])) {
                    continue;  // Saltar gastos inválidos
                }

                $insertQuery = '
                INSERT INTO empresas_gastos (
                    id_empresa,
                    nombre,
                    descripcion,
                    monto,
                    moneda,
                    periodicidad,
                    estatus,
                    fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ';

                $insertResult = $localConnection->goQuery($insertQuery, [
                    $id_empresa,
                    $gasto['nombre'],
                    $gasto['descripcion'] ?? '',
                    $gasto['monto'],
                    $gasto['moneda'] ?? 'USD',
                    $gasto['periodicidad'] ?? 'mensual',
                    $gasto['estatus'] ?? 'activo'
                ]);

                if ($insertResult) {
                    $insertCount++;
                }

                $debug_info['sql_ejecutado'] = $insertQuery;
                $debug_info['ultimo_parametro'] = [
                    $id_empresa,
                    $gasto['nombre'],
                    $gasto['descripcion'] ?? '',
                    $gasto['monto'],
                    $gasto['moneda'] ?? 'USD',
                    $gasto['periodicidad'] ?? 'mensual',
                    $gasto['estatus'] ?? 'activo'
                ];
            }

            $debug_info['gastos_insertados'] = $insertCount;

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Gastos fijos guardados correctamente. Se insertaron $insertCount gastos.",
                'debug_info' => $debug_info
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'debug_info' => [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
    // =================================================================
    // ENDPOINTS PARA GESTIÓN DE PLANTILLAS DE GASTOS (LOCAL)
    // =================================================================

    /**
     * GET /gastos
     * Obtiene todos los gastos (fijos/variables) de la base de datos local de la empresa.
     */
    $app->get('/gastos', function (Request $request, Response $response, array $args) {
        if (!defined('ID_EMPRESA') || !ID_EMPRESA) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $db = new LocalDB();
            $sql = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, tipo, estatus, moment FROM gastos';
            $gastos = $db->goQuery($sql);
            $db->disconnect();

            $response->getBody()->write(json_encode($gastos, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * POST /gastos
     * Crea una nueva plantilla de gasto en la base de datos local.
     */
    $app->post('/gastos', function (Request $request, Response $response, array $args) {
        if (!defined('ID_EMPRESA') || !ID_EMPRESA) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $data = $request->getParsedBody();
        if (empty($data['nombre']) || !isset($data['monto'])) {
            $response->getBody()->write(json_encode(['error' => 'Nombre y monto son obligatorios.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $db = new LocalDB();
            $sql = 'INSERT INTO gastos (nombre, descripcion, monto, moneda, periodicidad, tipo, estatus) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $params = [
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['monto'],
                $data['moneda'] ?? 'USD',
                $data['periodicidad'] ?? 'mensual',
                $data['tipo'] ?? 'fijo',
                $data['estatus'] ?? 'activo'
            ];

            $db->goQuery($sql, $params);
            $newId = $db->getLastID();
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Plantilla de gasto creada exitosamente', 'id' => $newId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al crear la plantilla: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * PUT /gastos/{id}
     * Actualiza una plantilla de gasto existente.
     */
    $app->put('/gastos/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $put_body = $request->getBody()->getContents();
        parse_str($put_body, $data);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos para actualizar.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $db = new LocalDB();
            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                // Evitar inyección base de nombres de campos si fuera dinámico, 
                // pero aquí confiamos en el input filtrado o validado si fuera necesario.
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $id;

            $sql = "UPDATE gastos SET " . implode(', ', $fields) . " WHERE _id = ?";
            $db->goQuery($sql, $params);
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Plantilla de gasto actualizada exitosamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar la plantilla: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * DELETE /gastos/{id}
     * Elimina una plantilla de gasto.
     */
    $app->delete('/gastos/{id}', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $db->goQuery('DELETE FROM gastos WHERE _id = ?', [$args['id']]);
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Plantilla de gasto eliminada exitosamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al eliminar la plantilla: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // ENDPOINTS PARA REGISTROS DE PAGOS (GASTOS REALES)
    // =================================================================

    /**
     * GET /gastos/registros
     * Obtiene el historial de pagos de gastos registrados.
     */
    $app->get('/gastos/registros', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $q = $request->getQueryParams();

            $conditions = [];
            $params = [];

            // Filtro por tipo (fijo / variable / adicional)
            if (!empty($q['tipo'])) {
                $conditions[] = 'tipo = ?';
                $params[] = $q['tipo'];
            }

            // Filtro por periodo exacto (YYYY-MM) — usado por GastosAdicionales
            if (!empty($q['periodo'])) {
                $conditions[] = 'periodo = ?';
                $params[] = $q['periodo'];
            }

            // Filtro por rango de fechas — usado por GastosHistorial
            if (!empty($q['desde'])) {
                $conditions[] = 'fecha_de_gasto >= ?';
                $params[] = $q['desde'];
            }
            if (!empty($q['hasta'])) {
                $conditions[] = 'fecha_de_gasto <= ?';
                $params[] = $q['hasta'];
            }

            $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $sql = "SELECT * FROM gastos_registros $where ORDER BY fecha_de_gasto DESC";

            $registros = $db->goQuery($sql, $params);
            $db->disconnect();

            $response->getBody()->write(json_encode($registros, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * POST /gastos/registros
     * Registra un nuevo pago de gasto (fijo, variable o adicional).
     */
    $app->post('/gastos/registros', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        if (empty($data['nombre']) || empty($data['monto']) || empty($data['fecha_de_gasto'])) {
            $response->getBody()->write(json_encode(['error' => 'Nombre, monto y fecha son obligatorios.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $db = new LocalDB();
            $periodo = substr($data['fecha_de_gasto'], 0, 7); // Generar YYYY-MM

            $sql = 'INSERT INTO gastos_registros 
                    (id_gasto_plantilla, tipo, nombre, descripcion, monto, moneda, fecha_de_gasto, periodo, id_usuario) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            
            $id_usuario = (isset($data['id_usuario']) && is_numeric($data['id_usuario'])) ? (int)$data['id_usuario'] : null;

            $params = [
                $data['id_gasto_plantilla'] ?? null,
                $data['tipo'] ?? 'adicional',
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['monto'],
                $data['moneda'] ?? 'USD',
                $data['fecha_de_gasto'],
                $periodo,
                $id_usuario
            ];

            $result = $db->goQuery($sql, $params);

            // goQuery retorna ['insert_id' => X] en INSERTs o ['status' => 'error'] si falló
            if (isset($result['status']) && $result['status'] === 'error') {
                throw new Exception($result['message'] ?? 'Error desconocido al insertar.');
            }

            $newId = $result['insert_id'] ?? 0;
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Gasto registrado correctamente', 'id' => $newId]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * PUT /gastos/registros/{id}
     * Edita un registro de gasto y guarda auditoría obligatoria.
     */
    $app->put('/gastos/registros/{id}', function (Request $request, Response $response, array $args) {
        $id_registro = $args['id'];
        $put_body = $request->getBody()->getContents();
        parse_str($put_body, $data);

        if (empty($data['id_usuario']) || empty($data['nombre_usuario'])) {
            $response->getBody()->write(json_encode(['error' => 'ID y Nombre de usuario son requeridos para la auditoría.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $db = new LocalDB();
            
            // 1. Obtener datos actuales para la bitácora
            $current = $db->goQuery('SELECT monto FROM gastos_registros WHERE _id = ?', [$id_registro]);
            if (empty($current)) throw new Exception('Registro de gasto no encontrado.');
            $monto_anterior = $current[0]['monto'];

            // 2. Actualizar el registro
            $fields = []; $params = [];
            // Campos permitidos para actualizar (whitelist)
            $camposPermitidos = ['nombre', 'descripcion', 'monto', 'moneda', 'fecha_de_gasto'];
            foreach ($data as $key => $value) {
                if (!in_array($key, $camposPermitidos)) continue;
                if ($key === 'fecha_de_gasto') {
                    $fields[] = 'periodo = ?';
                    $params[] = substr($value, 0, 7);
                }
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            if (empty($fields)) throw new Exception('No se proporcionaron campos válidos para actualizar.');
            $params[] = $id_registro;
            $db->goQuery('UPDATE gastos_registros SET ' . implode(', ', $fields) . ' WHERE _id = ?', $params);

            // 3. Insertar en auditoría
            $sqlAudit = 'INSERT INTO gastos_auditoria 
                (id_registro, accion, id_usuario, nombre_usuario, monto_anterior, monto_nuevo, detalle) 
                VALUES (?, ?, ?, ?, ?, ?, ?)';
            
            $id_usuario_audit = (isset($data['id_usuario']) && is_numeric($data['id_usuario'])) ? (int)$data['id_usuario'] : null;

            $db->goQuery($sqlAudit, [
                $id_registro,
                'editado',
                $id_usuario_audit,
                $data['nombre_usuario'] ?? 'Sistema',
                $monto_anterior,
                $data['monto'] ?? $monto_anterior,
                $data['detalle'] ?? 'Actualización de registro de gasto'
            ]);

            $db->disconnect();
            $response->getBody()->write(json_encode(['message' => 'Registro actualizado y auditado correctamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * DELETE /gastos/registros/{id}
     * Elimina un registro de gasto y guarda auditoría.
     */
    $app->delete('/gastos/registros/{id}', function (Request $request, Response $response, array $args) {
        $id_registro = $args['id'];

        // Axios con {data: ...} en DELETE envía como body con content-type application/x-www-form-urlencoded
        // También soportamos query params como fallback
        $bodyRaw = $request->getBody()->getContents();
        parse_str($bodyRaw, $bodyData);
        $queryParams = $request->getQueryParams();

        // Combinar: body tiene prioridad sobre query params
        $auditData = array_merge($queryParams, $bodyData);

        if (empty($auditData['id_usuario']) || empty($auditData['nombre_usuario'])) {
            $response->getBody()->write(json_encode(['error' => 'ID y Nombre de usuario son requeridos para la auditoría.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $db = new LocalDB();

            // 1. Obtener datos actuales para la bitácora
            $current = $db->goQuery('SELECT monto, nombre FROM gastos_registros WHERE _id = ?', [$id_registro]);
            if (empty($current)) throw new Exception('Registro no encontrado.');
            $monto_anterior = $current[0]['monto'];

            // 2. Registrar auditoría ANTES de eliminar
            $sqlAudit = 'INSERT INTO gastos_auditoria 
                (id_registro, accion, id_usuario, nombre_usuario, monto_anterior, monto_nuevo, detalle) 
                VALUES (?, ?, ?, ?, ?, ?, ?)';

            $id_usuario_delete = (isset($auditData['id_usuario']) && is_numeric($auditData['id_usuario'])) ? (int)$auditData['id_usuario'] : null;

            $db->goQuery($sqlAudit, [
                $id_registro,
                'eliminado',
                $id_usuario_delete,
                $auditData['nombre_usuario'] ?? 'Sistema',
                $monto_anterior,
                null,
                $auditData['detalle'] ?? 'Eliminación manual de registro'
            ]);

            // 3. Eliminar el registro
            $db->goQuery('DELETE FROM gastos_registros WHERE _id = ?', [$id_registro]);
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Registro eliminado y auditado correctamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * GET /gastos/auditoria
     * Obtiene el historial general de auditoría con filtros.
     */
    $app->get('/gastos/auditoria', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $p = $request->getQueryParams();
            
            $conditions = [];
            $params = [];
            
            if (!empty($p['desde'])) {
                $conditions[] = 'fecha_accion >= ?';
                $params[] = $p['desde'] . ' 00:00:00';
            }
            if (!empty($p['hasta'])) {
                $conditions[] = 'fecha_accion <= ?';
                $params[] = $p['hasta'] . ' 23:59:59';
            }
            if (!empty($p['accion'])) {
                $conditions[] = 'accion = ?';
                $params[] = $p['accion'];
            }
            
            $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $sql = "SELECT * FROM gastos_auditoria $where ORDER BY fecha_accion DESC";
            
            $auditoria = $db->goQuery($sql, $params);
            $db->disconnect();
            
            $response->getBody()->write(json_encode($auditoria, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * GET /gastos/auditoria/{id_registro}
     * Obtiene el historial de auditoría para un registro específico.
     */
    $app->get('/gastos/auditoria/{id_registro}', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $sql = 'SELECT * FROM gastos_auditoria WHERE id_registro = ? ORDER BY fecha_accion DESC';
            $auditoria = $db->goQuery($sql, [$args['id_registro']]);
            $db->disconnect();

            $response->getBody()->write(json_encode($auditoria, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * GET /gastos/pendientes
     * Obtiene las plantillas de gastos que aún no tienen un registro de pago en el periodo base (mes actual).
     */
    $app->get('/gastos/pendientes', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $periodoActual = date('Y-m');

            $sql = "SELECT g.* 
                    FROM gastos g
                    WHERE g.estatus = 'activo'
                    AND g._id NOT IN (
                        SELECT id_gasto_plantilla 
                        FROM gastos_registros 
                        WHERE periodo = ? 
                        AND id_gasto_plantilla IS NOT NULL
                    )";
            
            $pendientes = $db->goQuery($sql, [$periodoActual]);
            $db->disconnect();

            $response->getBody()->write(json_encode($pendientes, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // FIN GESTIÓN GASTOS (LOCAL POR EMPRESA)

    /**
     * POST /config/multiplicador-precio
     * Actualiza el multiplicador de precio para conversión USD → VES
     */
    $app->post('/config/multiplicador-precio', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $object = ['success' => false];

        // Validar que se recibió el multiplicador
        if (!isset($data['multiplicador_precio'])) {
            $object['message'] = 'El campo multiplicador_precio es requerido';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $multiplicador = floatval($data['multiplicador_precio']);

        // Validar rango
        if ($multiplicador < 0 || $multiplicador > 100) {
            $object['message'] = 'El multiplicador debe estar entre 0 y 100';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Obtener la empresa del header Authorization
        $id_empresa = $request->getHeader('Authorization')[0] ?? 0;

        if (!$id_empresa || $id_empresa == 0) {
            $object['message'] = 'No se pudo identificar la empresa';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

            // Obtener credenciales de la empresa
            $connectionDetails = $localConnection->getConnectionDetails($id_empresa);

            if (!$connectionDetails) {
                $object['message'] = 'No se encontraron los datos de conexión de la empresa';
                $response->getBody()->write(json_encode($object));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Conectar a la base de datos de la empresa
            $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
            $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

            // Actualizar el multiplicador en la tabla config
            $sql = 'UPDATE config SET multiplicador_precio = ? WHERE _id = 1';
            $result = $localConnection->goQuery($sql, [$multiplicador]);

            if ($result !== false) {
                $object['success'] = true;
                $object['message'] = 'Multiplicador actualizado correctamente';
                $object['data'] = ['multiplicador_precio' => $multiplicador];
                $statusCode = 200;
            } else {
                $object['message'] = 'No se pudo actualizar el multiplicador';
                $statusCode = 500;
            }

            $localConnection->disconnect();

        } catch (Exception $e) {
            $object['message'] = 'Error al actualizar el multiplicador: ' . $e->getMessage();
            $statusCode = 500;
        }

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    });

}; // Fin de la función que envuelve las rutas
