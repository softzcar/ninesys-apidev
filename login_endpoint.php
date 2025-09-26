/** * Login */
  $app->post('/login', function (Request $request, Response $response, $args) {
    $datosAcceso = $request->getParsedBody();
    $object = ['debug' => []]; // Initialize debug array
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // VERIFICAR ACCCESO DEL EMPLEADO
    $sql = "SELECT email FROM api_empresas.empresas_usuarios WHERE email = '" . $datosAcceso['email'] . "'";
    $object['debug'][] = "Verificando email: " . $sql;
    $verificar_email = $localConnection->goQuery($sql);

    if (empty($verificar_email)) {
      $object['msg'] = 'El email ' . $datosAcceso['email'] . ' no está registrado en el sistema.';
      $object['data']['access'] = false;
      $object['data']['id_empleado'] = null;
      $object['data']['departamento'] = null;
      $object['data']['nombre'] = null;
      $object['data']['username'] = null;
      $object['data']['email'] = null;
      $object['data']['comision'] = 0;
      $object['data']['acceso'] = 0;
      $object['data']['orden_proceso'] = 0;
    } else {
      $sql = "SELECT id_usuario, email, `password`, nombre, departamento, id_empresa, activo, acceso, comision FROM empresas_usuarios WHERE email = '" . $datosAcceso['email'] . "' AND `password` = '" . $datosAcceso['password'] . "';";
      $object['debug'][] = "Verificando credenciales: " . $sql;
      $credenciales = $localConnection->goQuery($sql);

      if (empty($credenciales)) {
        $object['msg'] = 'Los datos de acceso proporcionados no son correctos';
        $object['data']['access'] = false;
        $object['data']['id_empleado'] = null;
        $object['data']['departamento'] = null;
        $object['data']['nombre'] = null;
        $object['data']['username'] = null;
        $object['data']['email'] = null;
        $object['data']['comision'] = 0;
        $object['data']['acceso'] = 0;
        $object['data']['orden_proceso'] = 0;
      } else {
        // OBTENER DATOS DE LA EMPRESA
        $sql = 'SELECT id_empresa, nombre, direccion, telefono, email, pais, numero_registro_legal, horario_laboral, tipos_de_monedas, activo, db_host, db_user, db_password, `db_name` FROM empresas WHERE id_empresa = ' . $credenciales[0]['id_empresa'];
        $object['debug'][] = "Obteniendo datos de la empresa: " . $sql;
        $data_empresa = $localConnection->goQuery($sql);

        if (empty($data_empresa)) {
            $object['msg'] = 'No se encontraron datos para la empresa con ID ' . $credenciales[0]['id_empresa'];
            $object['data']['access'] = false;
            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        // VERIFICAR CONFIGURACION DE BASE DE DATOS DE LA EMPRESA
        if (empty($data_empresa[0]['db_name'])) {
          $object['msg'] = 'La base de datos para esta empresa no está configurada.';
          $object['data']['access'] = false;
          $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
        $object['debug'][] = "db_name encontrado: " . $data_empresa[0]['db_name'];

        try {
          $test_dns = 'mysql:host=' . $data_empresa[0]['db_host'] . ';dbname=' . $data_empresa[0]['db_name'];
          $test_user = $data_empresa[0]['db_user'];
          $test_pass = $data_empresa[0]['db_password'];
          $object['debug'][] = "Intentando conectar a: " . $test_dns;
          $test_pdo = new PDO($test_dns, $test_user, $test_pass);
          $test_pdo = null;
          $object['debug'][] = "Conexión a la base de datos de la empresa exitosa.";
        } catch (PDOException $e) {
          $object['msg'] = 'No se pudo conectar a la base de datos de la empresa. Verifique la configuración.';
          $object['debug'][] = "Error de conexión PDO: " . $e->getMessage();
          $object['data']['access'] = false;
          $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        // ESTABLECEMOS LOS DATOS DE CONEXIÓN A LA BASE DE DATOS
        define('EMPRESA_ID', $credenciales[0]['id_empresa']);
        $object['empresa_id'] = EMPRESA_ID;
        $object['debug'][] = "ID de empresa establecido: " . EMPRESA_ID;

        // OBTENER MODULOS DEL SISTEMA
        $sql = 'SELECT _id, modulo, folder, descripcion from api_empresas.modulos ORDER BY modulo ASC';
        $object['debug'][] = "Obteniendo módulos: " . $sql;
        $object['modulos'] = $localConnection->goQuery($sql);

        // SINCRONIZAMOS LOS EMPLEADOS
        try {
          $object['debug'][] = "Sincronizando empleados...";
          $localConnection->syncEmpleados(EMPRESA_ID);
          $data = ['status' => 'success', 'message' => 'Empleados sincronizados correctamente'];
          $object['debug'][] = "Sincronización de empleados completada.";
        } catch (Exception $e) {
          $data = ['status' => 'error', 'message' => 'Error al sincronizar empleados: ' . $e->getMessage()];
          $object['debug'][] = "Error en sincronización de empleados: " . $e->getMessage();
        }

        // OBTENER DEPARTEMNTOS
        $sql_config = 'SELECT msg_welcome, msg_bye, msg_saldo, sys_mostrar_detalle_terminar_indicidual, sys_mostrar_rollo_en_empleado_corte, sys_mostrar_rollo_en_empleado_estampado, sys_mostrar_insumo_en_empleado_costura, sys_mostrar_insumo_en_empleado_limpieza, sys_mostrar_insumo_en_empleado_revision FROM config';
        $object['debug'][] = "Obteniendo config: " . $sql_config;
        $config_empresa = $localConnection->goQuery($sql_config);
        if (isset($config_empresa['status']) && $config_empresa['status'] === 'error') {
          $object['empresa']['config_empresa'] = null;
          $object['empresa']['config_empresa_error'] = $config_empresa;
          $object['debug'][] = "Error al obtener config: " . json_encode($config_empresa);
        } else {
          $object['empresa']['config_empresa'] = empty($config_empresa) ? null : $config_empresa[0];
          $object['debug'][] = "Configuración de empresa obtenida.";
        }

        // OBTENER DATOS DE CONFIGURACION DE LA EMPRESA
        $sql_departamentos = 'SELECT * from departamentos ORDER BY orden_proceso ASC';
        $object['debug'][] = "Obteniendo departamentos: " . $sql_departamentos;
        $departamentos = $localConnection->goQuery($sql_departamentos);

        if (isset($departamentos['status']) && $departamentos['status'] === 'error') {
          $object['departamentos'] = $departamentos;
          $object['debug'][] = "Error al obtener departamentos: " . json_encode($departamentos);
        } else if (empty($departamentos)) {
          $object['departamentos'] = ['status' => 'success', 'message' => 'No hay departamentos configurados en la empresa.'];
          $object['debug'][] = "No se encontraron departamentos.";
        } else {
          $object['departamentos'] = $departamentos;
          $object['debug'][] = "Departamentos obtenidos.";
        }

        // OBTENER DATOS DEL EMPLEADO
        $sql_empleado = "SELECT a.id_usuario AS _id, a.email AS username, a.password, a.nombre, a.email, a.departamento, c.orden_proceso, a.comision, a.comision_tipo, a.acceso, IFNULL( CONCAT( '[', GROUP_CONCAT( CONCAT( '{\"id\":', b.id_departamento, ',\"modulo\":\"', d.folder, '\",\"id_modulo\":\"', c.id_modulo, '\",\"orden_proceso\":\"', c.orden_proceso, '\",\"nombre\":\"', c.departamento, '\"}' ) SEPARATOR ',' ), ']' ), '[]' ) AS departamentos FROM api_empresas.empresas_usuarios a LEFT JOIN api_empresas.empresas_usuarios_departamentos b ON b.id_empleado = a.id_usuario LEFT JOIN departamentos c ON c._id = b.id_departamento LEFT JOIN api_empresas.modulos d ON d._id = c.id_modulo WHERE a.id_usuario = " . $credenciales[0]['id_usuario'] . " AND a.activo = 1  AND a.id_empresa = " . $data_empresa[0]['id_empresa'] . " GROUP BY a.id_usuario, a.email, a.password, a.nombre, a.departamento, a.comision, a.comision_tipo, a.acceso;";
        $object['debug'][] = "Obteniendo datos del empleado: " . $sql_empleado;
        $object['sql_empleado'] = $sql_empleado;
        $items = $localConnection->goQuery($sql_empleado);

        // Decodificar el campo `departamentos`
        foreach ($items as &$item) {
          if (!empty($item['departamentos'])) {
            $item['departamentos'] = json_decode($item['departamentos'], true);
          }
        }
        $object['empleado'] = $items;
        $object['debug'][] = "Datos del empleado obtenidos.";

        $object['statys_emp_sync'] = $data;

        $localConnection->disconnect();

        // CARGAMOS DATOS DE INICIO DE SESIÓN
        $object['msg'] = 'Bienvenido ' . $credenciales[0]['nombre'] . '.';

        $object['empresa']['id'] = $data_empresa[0]['id_empresa'];
        $object['empresa']['nombre'] = $data_empresa[0]['nombre'];
        $object['empresa']['direccion'] = $data_empresa[0]['direccion'];
        $object['empresa']['telefono'] = $data_empresa[0]['telefono'];
        $object['empresa']['email'] = $data_empresa[0]['email'];
        $object['empresa']['horario_laboral'] = json_decode($data_empresa[0]['horario_laboral']);
        $object['empresa']['tipos_de_monedas'] = json_decode($data_empresa[0]['tipos_de_monedas']);
        $object['empresa']['pais'] = $data_empresa[0]['pais'];
        $object['empresa']['numero_registro_legal'] = $data_empresa[0]['numero_registro_legal'];
        $object['empresa']['activo'] = $data_empresa[0]['activo'];

        $object['data']['access'] = true;
        $object['data']['id_empleado'] = $credenciales[0]['id_usuario'];
        $object['data']['departamento'] = $credenciales[0]['departamento'];
        $object['data']['nombre'] = $credenciales[0]['nombre'];
        $object['data']['username'] = $credenciales[0]['email'];
        $object['data']['email'] = $credenciales[0]['email'];
        $object['data']['comision'] = $credenciales[0]['comision'];
        $object['data']['acceso'] = intval($credenciales[0]['acceso']);
      }
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