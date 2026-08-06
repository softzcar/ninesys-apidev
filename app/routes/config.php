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

        if (isset($data['id_pais'])) {
            $updateFields[] = 'id_pais = ?';
            $params[] = (int) $data['id_pais'];
        }

        if (isset($data['timezone'])) {
            $updateFields[] = 'timezone = ?';
            $params[] = $data['timezone'];
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

        if (isset($result['status']) && $result['status'] === 'error') {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar la empresa: ' . $result['message']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Fase 8.5 (auditoría de creación de empresa nueva): si esta llamada
        // fija id_pais por primera vez y la empresa todavía no tiene ninguna
        // moneda configurada, se siembra automáticamente la moneda default
        // del país como base -- cierra el gap documentado desde la Fase 5
        // ("una empresa completamente nueva puede agregar monedas vía el
        // wizard, pero ninguna queda marcada es_base"). Nunca sobrescribe una
        // empresa que ya tiene monedas (ej. 194): solo actúa si
        // catalogo_monedas está vacío. Cualquier fallo aquí se registra pero
        // NO bloquea la respuesta -- el resto de la configuración de la
        // empresa (nombre, dirección, etc.) no debe depender de esto.
        if (isset($data['id_pais'])) {
            try {
                $connectionDetails = $localConnection->getConnectionDetails($companyId);
                if ($connectionDetails && !empty($connectionDetails['db_name'])) {
                    $driver = DB_DRIVER;
                    $companyDsn = ($driver === 'pgsql')
                        ? 'pgsql:host=' . $connectionDetails['db_host'] . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . $connectionDetails['db_name']
                        : 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
                    $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

                    $existentes = $localConnection->goQuery('SELECT COUNT(*) AS total FROM catalogo_monedas');
                    $sinMonedas = !empty($existentes) && (int) $existentes[0]['total'] === 0;

                    if ($sinMonedas) {
                        $localConnection->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
                        $monedaDefault = $localConnection->goQuery(
                            'SELECT id_moneda_soportada, codigo, nombre, simbolo FROM monedas_soportadas_por_pais WHERE id_pais = ? ORDER BY es_default_para_pais DESC, nombre LIMIT 1',
                            [(int) $data['id_pais']]
                        );

                        if (!empty($monedaDefault)) {
                            $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);
                            $insertResult = $localConnection->goQuery(
                                'INSERT INTO catalogo_monedas (id_moneda_soportada, codigo, nombre, simbolo, es_base, activo) VALUES (?, ?, ?, ?, 1, 1)',
                                [$monedaDefault[0]['id_moneda_soportada'], $monedaDefault[0]['codigo'], $monedaDefault[0]['nombre'], $monedaDefault[0]['simbolo']]
                            );
                            $idMonedaNueva = $insertResult['insert_id'] ?? null;
                            if ($idMonedaNueva) {
                                // Deja sembrado desde el día 1 cuál es la moneda base de
                                // esta empresa nueva, para que el historial nunca quede
                                // con un hueco al principio.
                                $localConnection->goQuery(
                                    'INSERT INTO historial_moneda_base (id_moneda, desde) VALUES (?, CURRENT_TIMESTAMP)',
                                    [$idMonedaNueva]
                                );
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('WARN: no se pudo sembrar la moneda base automática para la empresa ' . $companyId . ': ' . $e->getMessage());
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['message' => 'Empresa actualizada correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CATÁLOGO DE PLATAFORMA - PAÍSES SOPORTADOS (compartido entre empresas, curado a mano)
    $app->get('/paises-soportados', function (Request $request, Response $response) {
        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery('SELECT id_pais, nombre, codigo_iso2, codigo_telefonico FROM paises_soportados ORDER BY nombre');
            $localConnection->disconnect();

            $data = array_map(function ($row) {
                $row['id_pais'] = (int) $row['id_pais'];
                return $row;
            }, $rows);

            $response->getBody()->write(json_encode(['data' => $data]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al obtener países soportados: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // CATÁLOGO DE PLATAFORMA - MONEDAS SOPORTADAS POR PAÍS
    $app->get('/monedas-soportadas/{id_pais}', function (Request $request, Response $response, array $args) {
        try {
            $idPais = (int) $args['id_pais'];
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $localConnection->goQuery(
                'SELECT id_moneda_soportada, id_pais, codigo, nombre, simbolo, es_default_para_pais FROM monedas_soportadas_por_pais WHERE id_pais = ? ORDER BY es_default_para_pais DESC, nombre',
                [$idPais]
            );
            $localConnection->disconnect();

            $data = array_map(function ($row) {
                $row['id_moneda_soportada'] = (int) $row['id_moneda_soportada'];
                $row['id_pais'] = (int) $row['id_pais'];
                $row['es_default_para_pais'] = (bool) $row['es_default_para_pais'];
                return $row;
            }, $rows);

            $response->getBody()->write(json_encode(['data' => $data]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al obtener monedas soportadas: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
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
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(200);
    });

    // CONFIGURACIÓN WIZARD - HORARIO
    $app->post('/configuracion/horario', function (Request $request, Response $response, array $args) {
        $rawBody = $request->getBody()->getContents();
        $data = json_decode($rawBody, true);

        if (!is_array($data)) {
            $data = $request->getParsedBody();
            if (empty($data)) {
                parse_str($rawBody, $parsed);
                if (!empty($parsed)) {
                    $data = $parsed;
                }
            }
        }

        $employeeId = $data['id_empleado'] ?? null;
        $horaInicioManana = $data['horaInicioManana'] ?? null;
        $horaFinManana = $data['horaFinManana'] ?? null;
        $horaInicioTarde = $data['horaInicioTarde'] ?? null;
        $horaFinTarde = $data['horaFinTarde'] ?? null;
        $diasLaborales = $data['diasLaborales'] ?? null;

        if (is_string($diasLaborales)) {
            $diasLaborales = json_decode($diasLaborales, true);
        }

        if (!$employeeId) {
            $response->getBody()->write(json_encode(['error' => 'ID de empleado requerido.']));
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

        $horarioLaboral = [
            'horaInicioManana' => (float)$horaInicioManana,
            'horaFinManana' => (float)$horaFinManana,
            'horaInicioTarde' => (float)$horaInicioTarde,
            'horaFinTarde' => (float)$horaFinTarde,
            'diasLaborales' => is_array($diasLaborales) ? $diasLaborales : []
        ];

        $horarioJson = json_encode($horarioLaboral);
        $sql = 'UPDATE empresas SET horario_laboral = ? WHERE id_empresa = ?';
        $result = $localConnection->goQuery($sql, [$horarioJson, $companyId]);
        $localConnection->disconnect();

        if (isset($result['status']) && $result['status'] === 'error') {
            $response->getBody()->write(json_encode(['error' => 'Error al guardar horario: ' . $result['message']]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['message' => 'Horario laboral guardado correctamente']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withStatus(200);
    });

    // CONFIGURACIÓN WIZARD - PERSONALIZACIÓN
    $app->post('/configuracion/personalizacion', function (Request $request, Response $response) {
        try {
            $json = $request->getBody()->getContents();
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'JSON inválido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $id_empleado = $data['id_empleado'] ?? null;
            $personalizacion = $data['personalizacion'] ?? null;

            if (!$id_empleado || !is_array($personalizacion)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Faltan datos requeridos: id_empleado y personalizacion (array)'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $sql_empresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
            $conn = $localConnection->goQuery($sql_empresa, [$id_empleado]);

            if (!$conn || empty($conn)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Empleado no encontrado']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $id_empresa = $conn[0]['id_empresa'];
            $connectionDetails = $localConnection->getConnectionDetails($id_empresa);

            if (!$connectionDetails) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'No se encontraron los datos de conexión de la empresa']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $driverPersonalizacion = getenv('DB_DRIVER') ?: 'mysql';
            if ($driverPersonalizacion === 'pgsql') {
                $portPersonalizacion = getenv('DB_PORT') ?: '5432';
                $companyDsn = 'pgsql:host=' . $connectionDetails['db_host'] . ';port=' . $portPersonalizacion . ';dbname=' . $connectionDetails['db_name'];
            } else {
                $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
            }
            $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

            $existingConfig = $localConnection->goQuery('SELECT * FROM config WHERE _id = 1');

            $valores = [
                ($personalizacion['sys_mostrar_detalle_terminar_indicidual'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_mostrar_rollo_en_empleado_corte'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_mostrar_rollo_en_empleado_estampado'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_mostrar_insumo_en_empleado_costura'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_mostrar_insumo_en_empleado_limpieza'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_mostrar_insumo_en_empleado_revision'] ?? 0) ? 1 : 0,
                ($personalizacion['sys_comision_de_costura'] ?? 0) ? 1 : 0
            ];

            if ($existingConfig && !empty($existingConfig)) {
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
                $localConnection->goQuery($updateQuery, $valores);
            } else {
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
                $localConnection->goQuery($insertQuery, $valores);
            }

            $localConnection->disconnect();

            $response->getBody()->write(json_encode(['success' => true, 'message' => 'Opciones de personalización guardadas correctamente']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // CONFIGURACIÓN DE GASTOS FIJOS -- endpoint del Wizard de Configuración de
    // Empresa (ConfigGastosForm.vue -> GastosManager.vue). GastosManager.vue
    // ya persiste cada alta/edición/baja en tiempo real contra los endpoints
    // reales (POST/PUT/DELETE /gastos), así que este endpoint NO vuelve a
    // escribir la tabla local `gastos` -- solo refresca el caché central
    // `empresas_gastos` (usado únicamente para precargar este mismo wizard
    // en el próximo login) a partir del estado real actual, nunca del
    // payload del cliente.
    //
    // Antes hacía un DELETE + reinsert de TODA la tabla local `gastos` a
    // partir de lo que el cliente mandara -- dos hallazgos reales corregidos
    // aquí (2026-08-06):
    //  1) No validaba que el id_empleado recibido perteneciera a la empresa
    //     autenticada (ID_EMPRESA) -- cualquiera podía mandar un id_empleado
    //     de OTRA empresa y sobreescribir sus gastos fijos.
    //  2) El DELETE+reinsert de la tabla local, combinado con que el wizard
    //     precarga desde el caché `empresas_gastos` (que puede estar
    //     desactualizado si alguien editó gastos desde la página dedicada
    //     "Gastos Fijos" en el medio), podía borrar silenciosamente cambios
    //     reales hechos por esa otra pantalla.
    $app->post('/configuracion/gastos', function (Request $request, Response $response) {
        if (!defined('ID_EMPRESA') || !ID_EMPRESA) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Acceso no autorizado.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        try {
            $json = $request->getBody()->getContents();
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'JSON inválido']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $id_empleado = $data['id_empleado'] ?? null;

            if (!$id_empleado) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Falta id_empleado.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $sql_empresa = 'SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?';
            $conn = $localConnection->goQuery($sql_empresa, [$id_empleado]);

            if (!$conn || empty($conn)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Empleado no encontrado']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $id_empresa = (int) $conn[0]['id_empresa'];

            // Límite de tenant: el empleado indicado debe pertenecer a la
            // misma empresa ya autenticada por el middleware, nunca a otra.
            if ($id_empresa !== (int) ID_EMPRESA) {
                $localConnection->disconnect();
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'El empleado indicado no pertenece a esta empresa.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Leer el estado REAL actual de la empresa (no el payload del
            // cliente) para refrescar el caché central.
            $connectionDetails = $localConnection->getConnectionDetails($id_empresa);
            $gastosReales = [];
            if ($connectionDetails) {
                $driverGastos = getenv('DB_DRIVER') ?: 'mysql';
                if ($driverGastos === 'pgsql') {
                    $portGastos = getenv('DB_PORT') ?: '5432';
                    $companyDsn = 'pgsql:host=' . $connectionDetails['db_host'] . ';port=' . $portGastos . ';dbname=' . $connectionDetails['db_name'];
                } else {
                    $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
                }
                $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);
                $gastosReales = $localConnection->goQuery("SELECT nombre, descripcion, monto, moneda, periodicidad, estatus FROM gastos WHERE tipo = 'fijo' AND eliminado = 0");

                // Volver a la conexión central para escribir el caché.
                $localConnection->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            }

            $localConnection->beginTransaction();
            $localConnection->goQuery('DELETE FROM empresas_gastos WHERE id_empresa = ?', [$id_empresa]);

            foreach ($gastosReales as $gasto) {
                $localConnection->goQuery('
                    INSERT INTO empresas_gastos (id_empresa, nombre, descripcion, monto, moneda, periodicidad, estatus, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $id_empresa,
                        $gasto['nombre'],
                        $gasto['descripcion'] ?? '',
                        $gasto['monto'],
                        $gasto['moneda'] ?? 'USD',
                        $gasto['periodicidad'] ?? 'mensual',
                        $gasto['estatus'] ?? 'activo'
                    ]
                );
            }

            $localConnection->commit();
            $localConnection->disconnect();

            $response->getBody()->write(json_encode(['success' => true, 'message' => "Gastos fijos sincronizados correctamente."]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            if (isset($localConnection) && $localConnection->inTransaction()) {
                $localConnection->rollback();
            }
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]));
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
            $sql = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, tipo, estatus, moment FROM gastos WHERE eliminado = 0';
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

        $tipo = $data['tipo'] ?? 'fijo';
        $reactivarId = (isset($data['reactivar_id']) && $data['reactivar_id'] !== '') ? (int) $data['reactivar_id'] : null;

        try {
            $db = new LocalDB();

            if ($reactivarId) {
                // Reactivar la plantilla existente en vez de crear un duplicado --
                // preserva el mismo _id, por lo que el historial de pagos que ya
                // la referencia (gastos_registros.id_gasto_plantilla) sigue intacto.
                $db->goQuery(
                    'UPDATE gastos SET nombre = ?, descripcion = ?, monto = ?, moneda = ?, periodicidad = ?, estatus = ?, eliminado = 0 WHERE _id = ?',
                    [$data['nombre'], $data['descripcion'] ?? null, $data['monto'], $data['moneda'] ?? 'USD', $data['periodicidad'] ?? 'mensual', $data['estatus'] ?? 'activo', $reactivarId]
                );

                // Auditar la reactivación (antes no quedaba ningún rastro de
                // este evento, dificultó reconstruir un incidente real el
                // 2026-08-06 -- mismo criterio ya usado para la baja).
                $idUsuarioReact = (isset($data['id_usuario']) && is_numeric($data['id_usuario'])) ? (int) $data['id_usuario'] : null;
                try {
                    $db->goQuery(
                        'INSERT INTO gastos_auditoria (id_registro, accion, id_usuario, nombre_usuario, monto_anterior, monto_nuevo, detalle) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$reactivarId, 'plantilla_reactivada', $idUsuarioReact, $data['nombre_usuario'] ?? 'Sistema', null, $data['monto'], 'Plantilla "' . $data['nombre'] . '" reactivada']
                    );
                } catch (Exception $eAudit) { /* no bloquear la reactivación si la auditoría falla */ }

                $db->disconnect();

                $response->getBody()->write(json_encode(['message' => 'Plantilla de gasto reactivada exitosamente.', 'id' => $reactivarId]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // Mismo patrón ya usado en Tallas/Telas: detectar un nombre ya
            // usado (activo o eliminado) antes de insertar, para no crear un
            // duplicado silencioso ni perder el historial de pagos ya
            // asociado al _id original si el usuario recrea un gasto que
            // había eliminado (hallazgo real reportado por el usuario,
            // 2026-08-06 -- "Internet" quedó duplicado al recrearlo).
            $existing = $db->goQuery(
                'SELECT _id, nombre, eliminado FROM gastos WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND tipo = ?',
                [$data['nombre'], $tipo]
            );

            if ($existing) {
                $match = $existing[0];
                if ((int) $match['eliminado'] === 1) {
                    $db->disconnect();
                    $response->getBody()->write(json_encode([
                        'eliminado_existente' => true,
                        'id' => $match['_id'],
                        'nombre' => $match['nombre'],
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                $db->disconnect();
                $response->getBody()->write(json_encode(['error' => 'Ya existe un gasto activo llamado "' . $match['nombre'] . '"']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $sql = 'INSERT INTO gastos (nombre, descripcion, monto, moneda, periodicidad, tipo, estatus) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $params = [
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['monto'],
                $data['moneda'] ?? 'USD',
                $data['periodicidad'] ?? 'mensual',
                $tipo,
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
        $id = (int) $args['id'];
        $put_body = $request->getBody()->getContents();
        parse_str($put_body, $data);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos para actualizar.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $db = new LocalDB();
            // Lista blanca de columnas editables -- sin esto, cualquier clave
            // enviada en el body se concatenaba directo como nombre de
            // columna en el UPDATE, sin validar (hallazgo real, 2026-08-06;
            // PUT /gastos/registros/{id} sí tenía esta protección, este
            // endpoint hermano no).
            $camposPermitidos = ['nombre', 'descripcion', 'monto', 'moneda', 'periodicidad', 'tipo', 'estatus'];
            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                if (!in_array($key, $camposPermitidos, true)) continue;
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            if (empty($fields)) {
                $db->disconnect();
                $response->getBody()->write(json_encode(['error' => 'No se proporcionaron campos válidos para actualizar.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
     * GET /gastos/{id}/uso
     * Cuenta cuántos pagos reales (gastos_registros) dependen de esta plantilla,
     * para avisar antes de eliminar (mismo patrón que /sizes/{id}/uso y /telas/{id}/uso).
     */
    $app->get('/gastos/{id}/uso', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $id = (int) $args['id'];
            $res = $db->goQuery('SELECT COUNT(*) AS total FROM gastos_registros WHERE id_gasto_plantilla = ?', [$id]);
            $db->disconnect();

            $total = !empty($res) ? (int) $res[0]['total'] : 0;
            $response->getBody()->write(json_encode(['uso' => $total]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    /**
     * DELETE /gastos/{id}
     * Da de baja (soft-delete) una plantilla de gasto -- antes era un DELETE
     * físico real (hallazgo real, 2026-08-06). El esquema tiene
     * gastos_registros.id_gasto_plantilla con ON DELETE SET NULL (no
     * CASCADE), así que un DELETE físico no borraba los pagos históricos,
     * pero les rompía para siempre el vínculo con su plantilla. Con
     * soft-delete el _id se preserva y ese vínculo nunca se pierde.
     * Además, ahora sí queda auditado en gastos_auditoria (antes, a
     * diferencia de eliminar un registro de pago, no dejaba ningún rastro).
     */
    $app->delete('/gastos/{id}', function (Request $request, Response $response, array $args) {
        try {
            $id = (int) $args['id'];
            $bodyRaw = $request->getBody()->getContents();
            parse_str($bodyRaw, $bodyData);

            $db = new LocalDB();

            $actual = $db->goQuery('SELECT nombre, monto FROM gastos WHERE _id = ?', [$id]);
            if (empty($actual)) {
                $db->disconnect();
                $response->getBody()->write(json_encode(['error' => 'Plantilla de gasto no encontrada.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $db->goQuery('UPDATE gastos SET eliminado = 1 WHERE _id = ?', [$id]);

            $idUsuario = (isset($bodyData['id_usuario']) && is_numeric($bodyData['id_usuario'])) ? (int) $bodyData['id_usuario'] : null;
            $nombreUsuario = $bodyData['nombre_usuario'] ?? 'Sistema';
            try {
                $db->goQuery(
                    'INSERT INTO gastos_auditoria (id_registro, accion, id_usuario, nombre_usuario, monto_anterior, monto_nuevo, detalle) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, 'plantilla_eliminada', $idUsuario, $nombreUsuario, $actual[0]['monto'], null, 'Plantilla "' . $actual[0]['nombre'] . '" dada de baja']
                );
            } catch (Exception $eAudit) {
                // No bloquear la eliminación si la auditoría falla -- ya se comportaba así en /gastos/registros/{id}.
            }

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

            if (!empty($q['tipo'])) {
                $conditions[] = 'tipo = ?';
                $params[] = $q['tipo'];
            }

            if (!empty($q['periodo'])) {
                $conditions[] = 'periodo = ?';
                $params[] = $q['periodo'];
            }

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
            
            $db->beginTransaction();
            $current = $db->goQuery('SELECT monto FROM gastos_registros WHERE _id = ?', [$id_registro]);
            if (empty($current)) throw new Exception('Registro de gasto no encontrado.');
            $monto_anterior = $current[0]['monto'];

            $fields = []; $params = [];
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

            $db->commit();
            $db->disconnect();
            $response->getBody()->write(json_encode(['message' => 'Registro actualizado y auditado correctamente.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    /**
     * DELETE /gastos/registros/{id}
     */
    $app->delete('/gastos/registros/{id}', function (Request $request, Response $response, array $args) {
        $id_registro = $args['id'];
        $bodyRaw = $request->getBody()->getContents();
        parse_str($bodyRaw, $bodyData);
        $queryParams = $request->getQueryParams();
        $auditData = array_merge($queryParams, $bodyData);

        if (empty($auditData['id_usuario']) || empty($auditData['nombre_usuario'])) {
            $response->getBody()->write(json_encode(['error' => 'ID y Nombre de usuario son requeridos para la auditoría.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $db = new LocalDB();

            $current = $db->goQuery('SELECT monto, nombre FROM gastos_registros WHERE _id = ?', [$id_registro]);
            if (empty($current)) throw new Exception('Registro no encontrado.');
            $monto_anterior = $current[0]['monto'];

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
     */
    $app->get('/gastos/pendientes', function (Request $request, Response $response, array $args) {
        try {
            $db = new LocalDB();
            $periodoActual = date('Y-m');

            $sql = "SELECT g.*
                    FROM gastos g
                    WHERE g.estatus = 'activo'
                    AND g.eliminado = 0
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

    /**
     * POST /config/multiplicador-precio
     */
    $app->post('/config/multiplicador-precio', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $object = ['success' => false];

        if (!isset($data['multiplicador_precio'])) {
            $object['message'] = 'El campo multiplicador_precio es requerido';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $multiplicador = floatval($data['multiplicador_precio']);
        if ($multiplicador < 0 || $multiplicador > 100) {
            $object['message'] = 'El multiplicador debe estar entre 0 y 100';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $id_empresa = $request->getHeader('Authorization')[0] ?? 0;
        if (!$id_empresa || $id_empresa == 0) {
            $object['message'] = 'No se pudo identificar la empresa';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $connectionDetails = $localConnection->getConnectionDetails($id_empresa);

            if (!$connectionDetails) {
                $object['message'] = 'No se encontraron los datos de conexión de la empresa';
                $response->getBody()->write(json_encode($object));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $driverMult = getenv('DB_DRIVER') ?: 'mysql';
            if ($driverMult === 'pgsql') {
                $portMult = getenv('DB_PORT') ?: '5432';
                $companyDsn = 'pgsql:host=' . $connectionDetails['db_host'] . ';port=' . $portMult . ';dbname=' . $connectionDetails['db_name'];
            } else {
                $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
            }
            $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

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

};
