<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    /** * Login */
    $app->post('/login', function (Request $request, Response $response, $args) {
        $datosAcceso = $request->getParsedBody();
        $object = ['debug' => []];

        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Paso 1: Buscar usuario solo por email
        $sql_user = 'SELECT id_usuario, email, `password`, nombre, telefono, departamento, id_empresa, activo, acceso, comision FROM empresas_usuarios WHERE email = ?';
        $object['debug'][] = 'Buscando usuario por email.';
        $credenciales = $localConnection->goQuery($sql_user, [$datosAcceso['email']]);

        if (empty($credenciales)) {
            $object['msg'] = 'El email ' . $datosAcceso['email'] . ' no está registrado en el sistema.';
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $usuario_data = $credenciales[0];

        // Paso 2: Verificar contraseña ANTES de cualquier otra validación
        $object['debug'][] = 'Pass DB: ' . $usuario_data['password'] . ' (' . strlen($usuario_data['password']) . ')';
        $object['debug'][] = 'Pass Req: ' . $datosAcceso['password'] . ' (' . strlen($datosAcceso['password']) . ')';
        $object['debug'][] = 'Match (Strict): ' . ($usuario_data['password'] === $datosAcceso['password'] ? 'YES' : 'NO');
        $object['debug'][] = 'Match (Loose): ' . ($usuario_data['password'] == $datosAcceso['password'] ? 'YES' : 'NO');

        if ($usuario_data['password'] !== $datosAcceso['password']) {
            $object['msg'] = 'Los datos de acceso proporcionados no son correctos';
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Paso 3: Obtener datos de la empresa
        $sql_empresa = 'SELECT id_empresa, nombre, direccion, telefono, email, pais, numero_registro_legal, horario_laboral, tipos_de_monedas, activo, db_host, db_user, db_password, `db_name` FROM empresas WHERE id_empresa = ?';
        $object['debug'][] = 'Obteniendo datos de la empresa.';
        $data_empresa = $localConnection->goQuery($sql_empresa, [$usuario_data['id_empresa']]);

        if (empty($data_empresa)) {
            $object['msg'] = 'No se encontró una empresa asociada a este usuario.';
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $empresa_data = $data_empresa[0];

        // Paso 4: Ejecutar la validación de configuración
        $datos_faltantes = [];

        // Verificar datos de la empresa (siempre se verifica)
        if (empty(trim($empresa_data['nombre'] ?? ''))) {
            $datos_faltantes[] = 'Nombre de la empresa (en empresas)';
        }
        if (empty(trim($empresa_data['numero_registro_legal'] ?? ''))) {
            $datos_faltantes[] = 'Número de registro legal de la empresa (en empresas)';
        }
        if (empty(trim($empresa_data['direccion'] ?? ''))) {
            $datos_faltantes[] = 'Dirección de la empresa (en empresas)';
        }
        if (empty(trim($empresa_data['telefono'] ?? ''))) {
            $datos_faltantes[] = 'Teléfono de la empresa (en empresas)';
        }
        if (empty(trim($empresa_data['email'] ?? ''))) {
            $datos_faltantes[] = 'Email de la empresa (en empresas)';
        }
        if (empty(trim($empresa_data['pais'] ?? ''))) {
            $datos_faltantes[] = 'País de la empresa (en empresas)';
        }

        // Verificar que al menos UN administrador tenga teléfono
        $sql_admin_check = 'SELECT id_usuario FROM empresas_usuarios WHERE id_empresa = ? AND departamento = "Administración" AND telefono IS NOT NULL AND telefono != "" LIMIT 1';
        $admin_with_phone = $localConnection->goQuery($sql_admin_check, [$usuario_data['id_empresa']]);

        if (empty($admin_with_phone)) {
            $datos_faltantes[] = 'Teléfono del usuario administrador';
        }

        // Paso 4: Decisión crítica
        if (!empty($datos_faltantes)) {
            $error_response_object = [
                'company_full_config' => false,
                'datos_faltantes' => $datos_faltantes,
                'datos_empresa' => [
                    'nombre' => $empresa_data['nombre'],
                    'numero_registro_legal' => $empresa_data['numero_registro_legal'],
                    'direccion' => $empresa_data['direccion'],
                    'telefono' => $empresa_data['telefono'],
                    'email' => $empresa_data['email'],
                    'pais' => $empresa_data['pais']
                ],
                'datos_usuario' => [
                    'nombre' => $usuario_data['nombre'],
                    'telefono' => $usuario_data['telefono'],
                    'id_empleado' => $usuario_data['id_usuario']
                ]
            ];

            // AÑADIR DATOS ADICIONALES SI LA CONFIGURACIÓN ESTÁ INCOMPLETA
            $error_response_object['datos_empresa']['horario_laboral'] = json_decode($empresa_data['horario_laboral']);
            $error_response_object['datos_empresa']['tipos_de_monedas'] = json_decode($empresa_data['tipos_de_monedas']);

            // Consultar gastos fijos de la empresa
            $sql_gastos = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, estatus FROM empresas_gastos WHERE id_empresa = ? AND estatus = "activo"';
            $gastos_data = $localConnection->goQuery($sql_gastos, [$usuario_data['id_empresa']]);

            if (!empty($gastos_data)) {
                $error_response_object['datos_empresa']['gastos_fijos'] = $gastos_data;
            }

            $connectionDetails = $localConnection->getConnectionDetails($usuario_data['id_empresa']);
            if ($connectionDetails) {
                $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
                $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

                $sql_config = 'SELECT sys_mostrar_detalle_terminar_indicidual, sys_mostrar_rollo_en_empleado_corte, sys_mostrar_rollo_en_empleado_estampado, sys_mostrar_insumo_en_empleado_costura, sys_mostrar_insumo_en_empleado_limpieza, sys_mostrar_insumo_en_empleado_revision, sys_comision_de_costura, multiplicador_precio FROM config WHERE _id = 1';
                $config_data = $localConnection->goQuery($sql_config);

                if (!empty($config_data)) {
                    $error_response_object['datos_personalizacion'] = [
                        'sys_mostrar_detalle_terminar_indicidual' => (bool) $config_data[0]['sys_mostrar_detalle_terminar_indicidual'],
                        'sys_mostrar_rollo_en_empleado_corte' => (bool) $config_data[0]['sys_mostrar_rollo_en_empleado_corte'],
                        'sys_mostrar_rollo_en_empleado_estampado' => (bool) $config_data[0]['sys_mostrar_rollo_en_empleado_estampado'],
                        'sys_mostrar_insumo_en_empleado_costura' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_costura'],
                        'sys_mostrar_insumo_en_empleado_limpieza' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_limpieza'],
                        'sys_mostrar_insumo_en_empleado_revision' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_revision'],
                        'sys_comision_de_costura' => (bool) $config_data[0]['sys_comision_de_costura'],
                        'multiplicador_precio' => (float) $config_data[0]['multiplicador_precio'],
                    ];
                }
            }
            // FIN DE DATOS ADICIONALES

            $response->getBody()->write(json_encode($error_response_object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Paso 5: Si la configuración está completa, continuar con el login normal

        // --- A partir de aquí, el resto del proceso de login ---
        $login_successful = true;
        $error_messages = [];

        if (empty($empresa_data['db_name'])) {
            $object['msg'] = 'La base de datos para esta empresa no está configurada.';
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $test_dns = 'mysql:host=' . $empresa_data['db_host'] . ';dbname=' . $empresa_data['db_name'];
            $test_user = $empresa_data['db_user'];
            $test_pass = $empresa_data['db_password'];
            $test_pdo = new PDO($test_dns, $test_user, $test_pass);
            $test_pdo = null;
        } catch (PDOException $e) {
            $object['msg'] = 'No se pudo conectar a la base de datos de la empresa. Verifique la configuración.';
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        define('EMPRESA_ID', $usuario_data['id_empresa']);
        $object['empresa_id'] = EMPRESA_ID;

        $sql_modulos = 'SELECT _id, modulo, folder, descripcion from api_empresas.modulos ORDER BY modulo ASC';
        $object['modulos'] = $localConnection->goQuery($sql_modulos);

        $company_dns = 'mysql:host=' . $empresa_data['db_host'] . ';dbname=' . $empresa_data['db_name'];
        $company_user = $empresa_data['db_user'];
        $company_pass = $empresa_data['db_password'];
        $localConnection->switchDatabase($company_dns, $company_user, $company_pass);

        $sql_config = 'SELECT msg_welcome, msg_bye, msg_saldo, sys_mostrar_detalle_terminar_indicidual, sys_mostrar_rollo_en_empleado_corte, sys_mostrar_rollo_en_empleado_estampado, sys_mostrar_insumo_en_empleado_costura, sys_mostrar_insumo_en_empleado_limpieza, sys_mostrar_insumo_en_empleado_revision, multiplicador_precio FROM config';
        $config_empresa = $localConnection->goQuery($sql_config);
        if (isset($config_empresa['status']) && $config_empresa['status'] === 'error') {
            $login_successful = false;
            $error_messages[] = 'Error al obtener la configuración de la empresa: ' . $config_empresa['message'];
        } else {
            $object['empresa']['config_empresa'] = empty($config_empresa) ? null : $config_empresa[0];
        }

        $sql_departamentos = 'SELECT * from departamentos ORDER BY orden_proceso ASC';
        $departamentos = $localConnection->goQuery($sql_departamentos);
        if (isset($departamentos['status']) && $departamentos['status'] === 'error') {
            $login_successful = false;
            $error_messages[] = 'Error al obtener departamentos: ' . $departamentos['message'];
        } else {
            $object['departamentos'] = $departamentos;
        }

        $sql_empleado = "SELECT a.id_usuario AS _id, a.email AS username, a.password, a.nombre, a.email, a.departamento, c.orden_proceso, a.comision, a.comision_tipo, a.acceso, IFNULL( CONCAT( '[', GROUP_CONCAT( CONCAT( '{\"id\":', b.id_departamento, ',\"modulo\":\"', d.folder, '\",\"id_modulo\":\"', c.id_modulo, '\",\"orden_proceso\":\"', c.orden_proceso, '\",\"nombre\":\"', c.departamento, '\"}' ) SEPARATOR ',' ), ']' ), '[]' ) AS departamentos FROM api_empresas.empresas_usuarios a LEFT JOIN api_empresas.empresas_usuarios_departamentos b ON b.id_empleado = a.id_usuario LEFT JOIN departamentos c ON c._id = b.id_departamento LEFT JOIN api_empresas.modulos d ON d._id = c.id_modulo WHERE a.id_usuario = ? AND a.activo = 1 AND a.id_empresa = ? GROUP BY a.id_usuario, a.email, a.password, a.nombre, a.departamento, a.comision, a.comision_tipo, a.acceso;";
        $items = $localConnection->goQuery($sql_empleado, [$usuario_data['id_usuario'], $empresa_data['id_empresa']]);

        if (isset($items['status']) && $items['status'] === 'error') {
            $login_successful = false;
            $error_messages[] = 'Error al obtener datos del empleado: ' . $items['message'];
        } else {
            foreach ($items as &$item) {
                if (!empty($item['departamentos'])) {
                    $item['departamentos'] = json_decode($item['departamentos'], true);
                }
            }
            $object['empleado'] = $items;
        }

        $localConnection->disconnect();

        $object['empresa']['id'] = $empresa_data['id_empresa'];
        $object['empresa']['nombre'] = $empresa_data['nombre'];
        $object['empresa']['direccion'] = $empresa_data['direccion'];
        $object['empresa']['telefono'] = $empresa_data['telefono'];
        $object['empresa']['email'] = $empresa_data['email'];
        $object['empresa']['horario_laboral'] = json_decode($empresa_data['horario_laboral']);
        $object['empresa']['tipos_de_monedas'] = json_decode($empresa_data['tipos_de_monedas']);
        $object['empresa']['pais'] = $empresa_data['pais'];
        $object['empresa']['numero_registro_legal'] = $empresa_data['numero_registro_legal'];
        $object['empresa']['activo'] = $empresa_data['activo'];

        if ($login_successful) {
            $object['msg'] = 'Bienvenido ' . $usuario_data['nombre'] . '.';
            $object['data']['access'] = true;
            $object['company_full_config'] = true;
            $object['data']['id_empleado'] = $usuario_data['id_usuario'];
            $object['data']['departamento'] = $usuario_data['departamento'];
            $object['data']['nombre'] = $usuario_data['nombre'];
            $object['data']['username'] = $usuario_data['email'];
            $object['data']['email'] = $usuario_data['email'];
            $object['data']['comision'] = $usuario_data['comision'];
            $object['data']['acceso'] = intval($usuario_data['acceso']);

            // AÑADIR DATOS ADICIONALES PARA QUE EL WIZARD FUNCIONE CORRECTAMENTE
            // Estos datos son necesarios para el componente configuracionWizard.vue
            $object['datos_empresa'] = [
                'nombre' => $empresa_data['nombre'],
                'numero_registro_legal' => $empresa_data['numero_registro_legal'],
                'direccion' => $empresa_data['direccion'],
                'telefono' => $empresa_data['telefono'],
                'email' => $empresa_data['email'],
                'pais' => $empresa_data['pais'],
                'horario_laboral' => json_decode($empresa_data['horario_laboral']),
                'tipos_de_monedas' => json_decode($empresa_data['tipos_de_monedas'])
            ];

            $object['datos_usuario'] = [
                'nombre' => $usuario_data['nombre'],
                'telefono' => $usuario_data['telefono'],
                'id_empleado' => $usuario_data['id_usuario']
            ];

            // Consultar gastos fijos de la empresa
            $sql_gastos = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, estatus FROM empresas_gastos WHERE id_empresa = ? AND estatus = "activo"';
            $gastos_data = $localConnection->goQuery($sql_gastos, [$usuario_data['id_empresa']]);

            if (!empty($gastos_data)) {
                $object['datos_empresa']['gastos_fijos'] = $gastos_data;
            }

            // Obtener datos de personalización desde la base de datos de la empresa
            $companyDsn = 'mysql:host=' . $empresa_data['db_host'] . ';dbname=' . $empresa_data['db_name'];
            $localConnection->switchDatabase($companyDsn, $empresa_data['db_user'], $empresa_data['db_password']);

            $sql_config = 'SELECT sys_mostrar_detalle_terminar_indicidual, sys_mostrar_rollo_en_empleado_corte, sys_mostrar_rollo_en_empleado_estampado, sys_mostrar_insumo_en_empleado_costura, sys_mostrar_insumo_en_empleado_limpieza, sys_mostrar_insumo_en_empleado_revision, sys_comision_de_costura, multiplicador_precio FROM config WHERE _id = 1';
            $config_data = $localConnection->goQuery($sql_config);

            if (!empty($config_data)) {
                $object['datos_personalizacion'] = [
                    'sys_mostrar_detalle_terminar_indicidual' => (bool) $config_data[0]['sys_mostrar_detalle_terminar_indicidual'],
                    'sys_mostrar_rollo_en_empleado_corte' => (bool) $config_data[0]['sys_mostrar_rollo_en_empleado_corte'],
                    'sys_mostrar_rollo_en_empleado_estampado' => (bool) $config_data[0]['sys_mostrar_rollo_en_empleado_estampado'],
                    'sys_mostrar_insumo_en_empleado_costura' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_costura'],
                    'sys_mostrar_insumo_en_empleado_limpieza' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_limpieza'],
                    'sys_mostrar_insumo_en_empleado_revision' => (bool) $config_data[0]['sys_mostrar_insumo_en_empleado_revision'],
                    'sys_comision_de_costura' => (bool) $config_data[0]['sys_comision_de_costura'],
                    'multiplicador_precio' => (float) $config_data[0]['multiplicador_precio'],
                ];
            }
            // FIN DE DATOS ADICIONALES PARA EL WIZARD
        } else {
            $object['msg'] = 'Error durante el inicio de sesión. No se pudieron cargar todos los datos de la empresa.';
            $object['errors'] = $error_messages;
            $object['data']['access'] = false;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response;
    });

    /** * Login mensajes */
    $app->post('/verify-credentials', function (Request $request, Response $response, $args) {
        $datosAcceso = $request->getParsedBody();
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        $sql = "SELECT
            b.id_empresa empresa_id,
            b.activo empresa_activa,
            b.nombre empresa_nombre,    
            b.telefono empresa_telefono,
            b.email email_empresa,
            a.activo usuario_activo,
            a.acceso usuario_nivel_acceso,
            a.id_usuario usuario_id,
            a.email usuario_email,
            a.telefono usuario_email,
            a.departamento usuario_departamento,
            a.nombre usuario_nombre
        FROM
            empresas_usuarios a
        JOIN empresas b ON a.id_empresa = b.id_empresa
        WHERE
            a.email = '" . $datosAcceso['username'] . "' AND a.password = '" . $datosAcceso['password'] . "';";
        // $object['sql'] = $sql;
        $resp = $localConnection->goQuery($sql);

        if (empty($resp)) {
            $object['access'] = false;
            $object['user_data'] = null;
            $object['msg'] = 'Las credenciales proporcionadas no son válidas';
        } else {
            $ban = true;

            // VERIFICAR QUE LA EMPRESA ESTÉ ACTIVA
            if (!$resp[0]['empresa_activa']) {
                $ban = false;
                $object['access'] = false;
                $object['msg'] = 'La empresa ' . $resp[0]['empresa_nombre'] . ' no se encuentra activa';
                $object['user_data'] = $resp[0];
            }

            // VERIFICAR QUE EL USUARIO ESTÉ ACTIVO
            if (!$resp[0]['usuario_activo']) {
                $ban = false;
                $object['access'] = false;
                $object['msg'] = 'El usuario ' . $resp[0]['usuario_nombre'] . ' no se encuentra activo';
                $object['user_data'] = $resp[0];
            }

            // VERIFICAR NIVEL DE ACCESO DEL USUARIO
            if (!$resp[0]['usuario_nivel_acceso']) {
                $ban = false;
                $object['access'] = false;
                $object['msg'] = 'El usuario ' . $resp[0]['usuario_nombre'] . ' no tiene permisos suficientes';
                $object['user_data'] = $resp[0];
            }

            if ($ban) {
                $object['access'] = true;
                $object['msg'] = 'Bienvenido ' . $resp[0]['usuario_nombre'];
                $object['user_data'] = $resp[0];
            }
        }

        $localConnection->disconnect();

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . generateRandomToken())  // Añade el token en la cabecera
            ->withStatus(200);

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response;
    });

    /** FIN LOGIN */

}; // Fin de la función que envuelve las rutas
