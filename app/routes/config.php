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
        $data = json_decode($request->getBody()->getContents(), true);
        $employeeId = $data['id_empleado'] ?? null;
        $monedas = $data['monedas'] ?? null;

        if (!$employeeId || !is_array($monedas)) {
            $response->getBody()->write(json_encode(['error' => 'Datos inválidos']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

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

        // Actualizar tipos_de_monedas como JSON
        $monedasJson = json_encode($monedas);
        $sql = 'UPDATE empresas SET tipos_de_monedas = ? WHERE id_empresa = ?';
        $result = $localConnection->goQuery($sql, [$monedasJson, $companyId]);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al guardar monedas: ' . $result['message']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Monedas guardadas correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
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
    // ENDPOINTS PARA GESTIÓN DE GASTOS FIJOS DE LA EMPRESA
    // =================================================================

    /**
     * GET /gastos
     * Obtiene todos los gastos fijos de la empresa autenticada.
     */
    $app->get('/gastos', function (Request $request, Response $response, array $args) {
        $id_empresa = ID_EMPRESA;
        if (!$id_empresa) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado. No se pudo identificar la empresa.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $sql = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, estatus 
                FROM api_empresas.empresas_gastos 
                WHERE id_empresa = ?';
        $params = [$id_empresa];

        try {
            $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $gastos = $db->goQuery($sql, $params);
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
     * Crea un nuevo registro de gasto para la empresa.
     */
    $app->post('/gastos', function (Request $request, Response $response, array $args) {
        $id_empresa = ID_EMPRESA;
        if (!$id_empresa) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $data = $request->getParsedBody();

        if (empty($data['nombre']) || !isset($data['monto'])) {
            $response->getBody()->write(json_encode(['error' => 'Los campos "nombre" y "monto" son obligatorios.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $sql = 'INSERT INTO api_empresas.empresas_gastos 
                    (id_empresa, nombre, descripcion, monto, moneda, periodicidad, estatus) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?)';

        try {
            $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $params = [
                $id_empresa,
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['monto'],
                $data['moneda'] ?? 'USD',
                $data['periodicidad'] ?? 'mensual',
                $data['estatus'] ?? 'activo'
            ];

            $result = $db->goQuery($sql, $params);
            $newId = $db->getLastID();
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Gasto creado exitosamente', 'id_gasto' => $newId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al crear el gasto: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * PUT /gastos/{id_gasto}
     * Actualiza un gasto existente.
     */
    $app->put('/gastos/{id_gasto}', function (Request $request, Response $response, array $args) {
        $id_empresa = ID_EMPRESA;
        $id_gasto = $args['id_gasto'];
        if (!$id_empresa) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Corrección: Leer y parsear manualmente el cuerpo de la solicitud PUT
        $put_body = $request->getBody()->getContents();
        parse_str($put_body, $data);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos para actualizar.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id_gasto;
        $params[] = $id_empresa;
        $set_clause = implode(', ', $fields);

        $sql = "UPDATE api_empresas.empresas_gastos SET $set_clause WHERE _id = ? AND id_empresa = ?";

        try {
            $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $result = $db->goQuery($sql, $params);
            $db->disconnect();

            // Nota: El método goQuery no parece devolver el número de filas afectadas,
            // por lo que no podemos verificar si el gasto existía antes de actualizar.
            // Se asume éxito si no hay excepción.

            $response->getBody()->write(json_encode(['message' => 'Gasto actualizado exitosamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar el gasto: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * DELETE /gastos/{id_gasto}
     * Elimina un gasto.
     */
    $app->delete('/gastos/{id_gasto}', function (Request $request, Response $response, array $args) {
        $id_empresa = ID_EMPRESA;
        $id_gasto = $args['id_gasto'];
        if (!$id_empresa) {
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $sql = 'DELETE FROM api_empresas.empresas_gastos WHERE _id = ? AND id_empresa = ?';
        $params = [$id_gasto, $id_empresa];

        try {
            $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $result = $db->goQuery($sql, $params);
            $db->disconnect();

            $response->getBody()->write(json_encode(['message' => 'Gasto eliminado exitosamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al eliminar el gasto: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // FIN GESTIÓN GASTOS FIJOS DE LA EMPRESA

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
