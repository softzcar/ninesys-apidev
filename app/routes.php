<?php declare(strict_types=1);

// ini_set('implicit_flush', 1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

// use LocalDB;

date_default_timezone_set('America/Caracas');

return function (App $app) {
  // $app->add(new CorsMiddleware());
  // $app->add(new IdEmpresaMiddleware());

  function generateRandomToken($length = 32)
  {
    return bin2hex(random_bytes($length));
  }

  function splitSqlStatements($sql)
  {
    $statements = [];
    $lines = explode("\n", $sql);
    $currentStatement = '';
    $inDelimiterBlock = false;
    $delimiter = ';';

    foreach ($lines as $line) {
      $line = trim($line);

      // Saltar líneas vacías
      if (empty($line))
        continue;

      // Manejar cambios de delimitador
      if (preg_match('/^DELIMITER\s+(\S+)$/i', $line, $matches)) {
        $delimiter = $matches[1];
        continue;
      }

      $currentStatement .= $line . "\n";

      // Si estamos en un bloque delimitado y encontramos el delimitador
      if ($inDelimiterBlock && strpos($line, $delimiter) !== false && substr($line, -strlen($delimiter)) === $delimiter) {
        // Remover el delimitador del final antes de guardar
        $cleanStatement = trim(substr($currentStatement, 0, -strlen($delimiter)));
        $statements[] = trim($cleanStatement);
        $currentStatement = '';
        $inDelimiterBlock = false;
        $delimiter = ';'; // Reset to default
        continue;
      }

      // Detectar inicio de bloque (CREATE TRIGGER, CREATE PROCEDURE, CREATE FUNCTION)
      if (!$inDelimiterBlock && preg_match('/^(CREATE\s+(TRIGGER|PROCEDURE|FUNCTION))/i', $line)) {
        $inDelimiterBlock = true;
      }

      // Para statements normales terminados con ;
      if (!$inDelimiterBlock && strpos($line, ';') !== false && substr($line, -1) === ';') {
        $currentStatement = trim(substr($currentStatement, 0, -1));
        if (!empty($currentStatement) && !preg_match('/^(\s*--|\s*\/\*|\s*#)/', $currentStatement)) {
          $statements[] = $currentStatement . ';';
        }
        $currentStatement = '';
      }
    }

    // Agregar cualquier statement restante
    if (!empty(trim($currentStatement))) {
      $statements[] = trim($currentStatement);
    }

    return $statements;
  }

  $app->options('/{routes:.*}', function (Request $request, Response $response, array $args) {
    // CORS Pre-Flight OPTIONS Request Handler
    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ROOT
  $app->get('/', function (Request $request, Response $response) {
    global $id_empresa;  // Acceder a la variable global

    // $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    /* $localConnection = new LocalDB();
    $sql = 'SELECT
        a.id_empleado,
        a.id_orden,
        b.nombre
    FROM
        disenos a
    JOIN api_empresas.empresas_usuarios b
    ON
        a.id_empleado = b.id_usuario
    ';
    $tableStructure = $localConnection->goQuery($sql);
    $createTableSQL = $tableStructure;
    // $createTableSQL = str_replace('empresas_usuarios', 'empleados', $createTableSQL);
    $array['join_dbs'] = $createTableSQL; */

    $array['api'] = 'ninesys DEVELOPEMENT';
    $array['Ver'] = '2.3';
    $array['Empresa'] = EMPRESA_NOMBRE;

    // Obtener el parámetro de consulta `id_empresa`
    $queryParams = $request->getQueryParams();
    $array['id_empresa'] = ID_EMPRESA;
    $array['test'] = 'Modificacion 02';

    $response->getBody()->write(json_encode($array, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** INICIO CONFIGURACION DEL SISTEMA */

  // RUTAS DE CONFIGURACIÓN
  (require __DIR__ . '/routes/config.php')($app);

  // RUTAS DE NÓMINA (PRUEBAS)
  (require __DIR__ . '/routes/payroll.php')($app);

  /** FIN CONFIGURACION DEL SISTEMA */

  // RUTAS DE AUTENTICACIÓN
  (require __DIR__ . '/routes/auth.php')($app);

  // RUTAS DE INVENTARIO Y PRODUCTOS
  (require __DIR__ . '/routes/inventory.php')($app);

  // RUTAS DE LOTES DE FABRICACIÓN
  (require __DIR__ . '/routes/manufacturing.php')($app);

  // RUTAS DE EMPLEADOS
  (require __DIR__ . '/routes/employees.php')($app);

  // RUTAS DE REPORTES
  (require __DIR__ . '/routes/reports.php')($app);

  // RUTAS DE PAGOS
  (require __DIR__ . '/routes/payments.php')($app);

  // RUTAS DE ÓRDENES
  (require __DIR__ . '/routes/orders.php')($app);

  // RUTAS DE PRODUCTOS
  (require __DIR__ . '/routes/products.php')($app);

  // RUTAS DE PRODUCCIÓN
  (require __DIR__ . '/routes/production.php')($app);

  // RUTAS DE ADMINISTRACIÓN
  (require __DIR__ . '/routes/administration.php')($app);

  // RUTAS DE DISEÑOS
  (require __DIR__ . '/routes/designs.php')($app);

  // RUTAS DE FINANZAS
  (require __DIR__ . '/routes/finance.php')($app);

  // RUTAS DE CATÁLOGOS
  (require __DIR__ . '/routes/catalogs.php')($app);

  // RUTAS DE TABLAS
  (require __DIR__ . '/routes/tables.php')($app);

  // RUTAS DE COMUNICACIONES
  (require __DIR__ . '/routes/communications.php')($app);

  // RUTAS DE ASIGNACIONES
  (require __DIR__ . '/routes/assignments.php')($app);

  // RUTAS DE IMPRESORAS
  (require __DIR__ . '/routes/printers.php')($app);

  // RUTAS DE SUBIDA DE IMÁGENES
  (require __DIR__ . '/routes/upload.php')($app);

  // RUTAS DEL ASISTENTE DE IA (Gemini)
  (require __DIR__ . '/routes/ai.php')($app);

  // RUTA DE BÚSQUEDA DE ÓRDENES
  (require __DIR__ . '/routes/buscar.php')($app);

  // RUTA DE ÓRDENES SIN ASIGNACIÓN
  (require __DIR__ . '/routes/ordenes-sin-asignacion.php')($app);

  /** PROXY PARA TASAS DE CAMBIO (CORS FIX) */
  $app->get('/bcv-rates', function (Request $request, Response $response) {
    $url = 'https://bcv.justcarlux.dev/api/v1/rates';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Opcional, dependiendo del entorno
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      $response->getBody()->write(json_encode(['error' => 'Error backend: ' . $error_msg]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    curl_close($ch);

    $response->getBody()->write($result);

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withHeader('Access-Control-Allow-Origin', '*') // Permitir acceso desde cualquier origen (incluyendo localhost)
      ->withStatus($httpCode);
  });
  /** ENVIAR EMAILS */
  $app->get('/send-email', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $recipient = 'ozcaratemcio@gmail.com';
    $subject = 'titulo del mensaje';
    $message = '<h3>Tiutlo H3</h3><p>Un parrafo...</p>';

    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type:text/html;charset=UTF-8' . "\r\n";
    $headers .= 'From: Your Name <your_email@example.com>' . "\r\n";

    $sent = mail($recipient, $subject, $message, $headers);

    if ($sent) {
      $response->getBody()->write(json_encode(['success' => true]));
    } else {
      $response->getBody()->write(json_encode(['success' => false]));
    }

    return $response->withHeader('Content-Type', 'application/json');
  });

  $app->get('/sendmail/{id_orden}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();

    // Aquí deberías llamar a la función de la clase WooMe para enviar el correo electrónico.
    // Por ejemplo:
    $html = '<!DOCTYPE html> <html> <head> <title>Confirmación de Pedido</title> <style> /* Estilos para el correo electrónico */ body { font-family: Arial, sans-serif; background-color: #f5f5f5; } .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); } h1 { color: #333333; margin-bottom: 10px; } p { color: #666666; margin-bottom: 20px; } table { width: 100%; border-collapse: collapse; margin-bottom: 20px; } th, td { border: 1px solid #e5e5e5; padding: 8px; text-align: left; } th { background-color: #f5f5f5; font-weight: bold; } .total { font-weight: bold; text-align: right; } </style> </head> <body> <div class="container"> <h1>Confirmación de Pedido</h1> <p>Estimado cliente, gracias por su pedido. A continuación, encontrará los detalles del pedido:</p> <table> <thead> <tr> <th>Producto</th> <th>Talla</th> <th>Cantidad</th> <th>Tipo de Tela</th> <th>Precio</th> </tr> </thead> <tbody> <tr> <td>Camiseta</td> <td>XL</td> <td>2</td> <td>Algodón</td> <td>$20.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> </tbody> <tfoot> <tr> <td colspan="4" class="total">Total:</td> <td>$XXX.XX</td> <!-- Reemplaza con el total real --> </tr> </tfoot> </table> <p>Gracias por elegir nuestros productos. Esperamos que disfrute de su compra.</p> </div> </body> </html>';

    // $object['dataOrder'] = $woo->getOrderById($args['id_orden']);
    // $html = '<h1>Prueba mensaje en html</h1><p>Esto es un parrafo </p> <p style="color:red">Este es otro con texto rojo</p>';
    // $result = $woo->sendMail($args['id_orden'], $html);

    $result = false; // Placeholder
    $object = [];

    if ($result) {
      $object['respuesta'] = 'Correo electrónico enviado con éxito';
    } else {
      $object['respuesta'] = 'No se envió el correo electrónico';
    }

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PUT /categories/{id} - Actualizar categoría
  $app->put('/categories/{id}', function (Request $request, Response $response, $args) {
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

      $id = $args['id'];
      $nombre = trim($data['nombre'] ?? '');

      if (empty($nombre)) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'El nombre de la categoría es obligatorio'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
      }

      if (!defined('EMPRESA_ID')) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'No se pudo identificar la empresa'
        ]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
      }

      $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $connectionDetails = $localConnection->getConnectionDetails(EMPRESA_ID);

      if (!$connectionDetails) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'No se pudieron obtener los detalles de conexión de la empresa'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }

      $companyDsn = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
      $localConnection->switchDatabase($companyDsn, $connectionDetails['db_user'], $connectionDetails['db_password']);

      // Verificar si existe la categoría
      $existingCategory = $localConnection->goQuery('SELECT _id FROM categories WHERE _id = ?', [$id]);

      if (empty($existingCategory)) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'Categoría no encontrada'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }

      // Verificar si ya existe otra categoría con el mismo nombre
      $duplicateCategory = $localConnection->goQuery('SELECT _id FROM categories WHERE nombre = ? AND _id != ?', [$nombre, $id]);

      if (!empty($duplicateCategory)) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'Ya existe otra categoría con este nombre'
        ]));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
      }

      // Actualizar categoría
      $updateResult = $localConnection->goQuery('UPDATE categories SET nombre = ? WHERE _id = ?', [$nombre, $id]);

      if ($updateResult !== false) {
        $response->getBody()->write(json_encode([
          'success' => true,
          'message' => 'Categoría actualizada exitosamente',
          'data' => [
            'id' => $id,
            'name' => $nombre
          ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
      } else {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'Error al actualizar la categoría'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
      }
    } catch (Exception $e) {
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
      ]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // DELETE /categories/{id} - Eliminar categoría
  $app->delete('/categories/{id}', function (Request $request, Response $response, $args) {
    $localConnection = new LocalDB();

    $sql = 'DELETE FROM categories WHERE _id =  ' . $args['id'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GET /setup/user
  $app->get('/setup/user', function (Request $request, Response $response) {
    $dsn = 'mysql:host=localhost;dbname=api_empresas';
    $user = 'setup_admin';
    $password = 'SetupAdmin2024!';
    $user = 'setup_admin';
    $password = 'SetupAdmin2024!';

    try {
      $pdo = new PDO($dsn, $user, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
      ]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Consulta básica - solo empleados administradores
      $sql_users = "SELECT id_usuario AS id_empleado, email, id_empresa FROM empresas_usuarios WHERE departamento LIKE 'Administración'";
      $stmt_users = $pdo->prepare($sql_users);
      $stmt_users->execute();
      $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

      $result = [];

      foreach ($users as $user) {
        // Consulta datos de empresa
        $sql_empresa = 'SELECT nombre, numero_registro_legal, direccion, telefono, email, pais FROM empresas WHERE id_empresa = ?';
        $stmt_empresa = $pdo->prepare($sql_empresa);
        $stmt_empresa->execute([$user['id_empresa']]);
        $empresa_data = $stmt_empresa->fetch(PDO::FETCH_ASSOC);

        // Evaluar datos faltantes
        $activo = true;
        if (
          empty(trim($empresa_data['nombre'] ?? '')) ||
          empty(trim($empresa_data['numero_registro_legal'] ?? '')) ||
          empty(trim($empresa_data['direccion'] ?? '')) ||
          empty(trim($empresa_data['telefono'] ?? '')) ||
          empty(trim($empresa_data['email'] ?? '')) ||
          empty(trim($empresa_data['pais'] ?? ''))
        ) {
          $activo = false;
        }

        $result[] = [
          'id_empleado' => $user['id_empleado'],
          'email' => $user['email'],
          'id_empresa' => $user['id_empresa'],
          'nombre_empresa' => !empty(trim($empresa_data['nombre'] ?? '')) ? $empresa_data['nombre'] : 'Sin nombre...',
          'activo' => $activo
        ];
      }

      $response->getBody()->write(json_encode($result));
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    return $response->withHeader('Content-Type', 'application/json');
  });
  // PUT /setup/user - Editar email del empleado
  $app->put('/setup/user', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);

    // Validar datos requeridos
    if (!isset($data['id_empleado']) || !isset($data['email'])) {
      $response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos: id_empleado y email']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $id_empleado = $data['id_empleado'];
    $email = trim($data['email']);

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $response->getBody()->write(json_encode(['error' => 'Formato de email inválido']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
      // Conexión única con setup_admin (ahora con permisos globales)
      $dsn = 'mysql:host=localhost;dbname=api_empresas';
      $user = 'setup_admin';
      $password = 'SetupAdmin2024!';
      $pdo = new PDO($dsn, $user, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
      ]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Obtener el id_empresa del empleado para el email
      $stmt_id = $pdo->prepare('SELECT id_empresa FROM empresas_usuarios WHERE id_usuario = ?');
      $stmt_id->execute([$id_empleado]);
      $user_row = $stmt_id->fetch(PDO::FETCH_ASSOC);
      $id_emp_val = $user_row ? $user_row['id_empresa'] : 0;

      // Cambiar el email del administrador para incluir el ID de empresa y hacerlo único
      $email = 'administrador@empresa' . $id_emp_val . '.com';

      // Verificar que las tablas tienen las columnas necesarias
      $stmt = $pdo->query('DESCRIBE empresas');
      $columns_empresas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
      $required_columns_empresas = ['db_name', 'db_user', 'db_password'];

      foreach ($required_columns_empresas as $col) {
        if (!in_array($col, $columns_empresas)) {
          throw new Exception("La tabla 'empresas' no tiene la columna requerida: {$col}");
        }
      }

      // Verificar que el email no esté asignado a otro usuario
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM empresas_usuarios WHERE email = ? AND id_usuario != ?');
      $stmt->execute([$email, $id_empleado]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['count'] > 0) {
        $response->getBody()->write(json_encode(['error' => 'El email ya está asignado a otro usuario']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
      }

      // Verificar que el empleado existe
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM empresas_usuarios WHERE id_usuario = ?');
      $stmt->execute([$id_empleado]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['count'] == 0) {
        $response->getBody()->write(json_encode(['error' => 'Empleado no encontrado']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
      }

      // Actualizar el email
      $stmt = $pdo->prepare('UPDATE empresas_usuarios SET email = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?');
      $stmt->execute([$email, $id_empleado]);

      $response->getBody()->write(json_encode(['message' => 'Email actualizado correctamente']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });
  // DELETE /setup/user/{id} - Eliminar usuario
  $app->delete('/setup/user/{id}', function (Request $request, Response $response, array $args) {
    $id_empleado = $args['id'];

    // Validar que el ID sea numérico
    if (!is_numeric($id_empleado)) {
      $response->getBody()->write(json_encode(['error' => 'ID de empleado inválido']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
      // Conectar a la base de datos
      $dsn = 'mysql:host=localhost;dbname=api_empresas';
      $user = 'setup_admin';
      $password = 'SetupAdmin2024!';
      $pdo = new PDO($dsn, $user, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
      ]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Verificar que el empleado existe y no está activo
      $stmt = $pdo->prepare('SELECT activo, id_empresa FROM empresas_usuarios WHERE id_usuario = ?');
      $stmt->execute([$id_empleado]);
      $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$usuario) {
        $response->getBody()->write(json_encode(['error' => 'Empleado no encontrado']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
      }

      // Verificar que el usuario no esté activo
      if ($usuario['activo']) {
        $response->getBody()->write(json_encode(['error' => 'No se puede eliminar un usuario activo']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      // 1. Obtener datos de la empresa y Validar seguridad (ID 163 intocable)
      $id_empresa = $usuario['id_empresa'];
      if ($id_empresa == 163) {
        $response->getBody()->write(json_encode(['error' => 'No se puede eliminar la empresa principal (Producción)']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
      }

      $stmt = $pdo->prepare('SELECT db_name, db_user FROM empresas WHERE id_empresa = ?');
      $stmt->execute([$id_empresa]);
      $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($empresa) {
        $db_name = $empresa['db_name'];
        $db_user = $empresa['db_user'];

        // Conexión con root para DDL
        $root_dsn = 'mysql:host=localhost;dbname=mysql';
        $root_user = 'root';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $root_password = (strpos($host, 'nineteengreen.com') !== false || strpos($host, 'localhost') !== false) ? 'ppbT5QsP5FgWIR' : 'MyR5jRHuwj6kWA';
        $root_pdo = new PDO($root_dsn, $root_user, $root_password, [
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
        ]);
        $root_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Eliminar BD y Usuario (Drop)
        if ($db_name) {
          $root_pdo->exec("DROP DATABASE IF EXISTS `{$db_name}`");
        }
        if ($db_user) {
          $root_pdo->exec("DROP USER IF EXISTS '{$db_user}'@'localhost'");
          $root_pdo->exec("DROP USER IF EXISTS '{$db_user}'@'%'");
        }
      }

      // 2. Eliminar referencias en api_empresas en orden para no violar FK
      $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
      $pdo->exec("DELETE FROM empresas_gastos WHERE id_empresa = {$id_empresa}");
      $pdo->exec("DELETE FROM empresas_usuarios_departamentos WHERE id_empleado IN (SELECT id_usuario FROM empresas_usuarios WHERE id_empresa = {$id_empresa})");
      $pdo->exec("DELETE FROM empresas_usuarios WHERE id_empresa = {$id_empresa}");
      $pdo->exec("DELETE FROM empresas WHERE id_empresa = {$id_empresa}");
      $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

      $response->getBody()->write(json_encode(['message' => 'Usuario eliminado correctamente']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });
  // POST /setup/user - Crear nuevo usuario (empleado)
  $app->post('/setup/user', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);

    // Validar datos requeridos
    if (!isset($data['email'])) {
      $response->getBody()->write(json_encode(['error' => 'El campo email es requerido']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $email = trim($data['email']);

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $response->getBody()->write(json_encode(['error' => 'Formato de email inválido']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
      // Conectar a la base de datos
      $dsn = 'mysql:host=localhost;dbname=api_empresas';
      $user = 'setup_admin';
      $password = 'SetupAdmin2024!';
      $pdo = new PDO($dsn, $user, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
      ]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Verificar que el email no esté registrado en empresas_usuarios
      $stmt = $pdo->prepare('SELECT eu.id_empresa, e.nombre FROM empresas_usuarios eu LEFT JOIN empresas e ON eu.id_empresa = e.id_empresa WHERE eu.email = ?');
      $stmt->execute([$email]);
      $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);

      // Verificar que el email no esté registrado en empresas
      $stmt = $pdo->prepare('SELECT nombre FROM empresas WHERE email = ?');
      $stmt->execute([$email]);
      $empresa_existente = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($usuario_existente) {
        $nombre_empresa_usuario = $usuario_existente['nombre'];
        if (empty($nombre_empresa_usuario) || $nombre_empresa_usuario === null) {
          $mensaje = 'El email ya está registrado como usuario en una empresa que aún no tiene nombre asignado';
        } else {
          $mensaje = 'El email ya está registrado como usuario en la empresa: ' . $nombre_empresa_usuario;
        }
        $response->getBody()->write(json_encode(['error' => $mensaje]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
      }

      if ($empresa_existente) {
        $nombre_empresa = $empresa_existente['nombre'];
        if (empty($nombre_empresa) || $nombre_empresa === null) {
          $mensaje = 'El email ya está registrado para una empresa que aún no tiene nombre asignado';
        } else {
          $mensaje = 'El email ya está registrado para la empresa: ' . $nombre_empresa;
        }
        $response->getBody()->write(json_encode(['error' => $mensaje]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
      }

      // 1. Crear registro en empresas con monedas por defecto
      $default_monedas = json_encode([
        ['moneda' => 'dolar', 'mondeda_nombre' => 'Dólar', 'activo' => true, 'valor' => 1],
        ['moneda' => 'bolivar', 'mondeda_nombre' => 'Bolívar', 'activo' => true, 'valor' => 1],
        ['moneda' => 'peso_colombiano', 'mondeda_nombre' => 'Peso Colombiano', 'activo' => false, 'valor' => 0],
      ]);
      $stmt = $pdo->prepare('INSERT INTO empresas (activo, tipos_de_monedas) VALUES (1, ?)');
      $stmt->execute([$default_monedas]);
      $id_empresa = $pdo->lastInsertId();
      error_log("DEBUG: Empresa creada con ID: {$id_empresa}");

      // Verificar que el registro se creó correctamente
      if (!$id_empresa) {
        throw new Exception('No se pudo obtener el ID de la empresa creada');
      }

      // Verificar que el registro existe
      $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM empresas WHERE id_empresa = ?');
      $stmt->execute([$id_empresa]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($result['count'] == 0) {
        throw new Exception('El registro de empresa no existe después del INSERT');
      }

      // 2. Crear base de datos y usuario MySQL para la empresa
      $db_name = 'api_emp_' . $id_empresa;
      $db_user = 'api_user_' . $id_empresa;
      $db_password = bin2hex(random_bytes(12));  // 24 caracteres aleatorios

      // Conexión con root para operaciones DDL
      $root_dsn = 'mysql:host=localhost;dbname=mysql';
      $root_user = 'root';
      $host = $_SERVER['HTTP_HOST'] ?? '';
      $root_password = (strpos($host, 'nineteengreen.com') !== false || strpos($host, 'localhost') !== false) ? 'ppbT5QsP5FgWIR' : 'MyR5jRHuwj6kWA';
      $root_pdo = new PDO($root_dsn, $root_user, $root_password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
      ]);
      $root_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      try {
        // Crear base de datos
        error_log("DEBUG: Creando BD {$db_name}");
        $root_pdo->exec("CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Crear usuario MySQL con permisos para localhost y %
        error_log("DEBUG: Creando usuario {$db_user}");
        $root_pdo->exec("CREATE USER '{$db_user}'@'localhost' IDENTIFIED BY '{$db_password}'");
        $root_pdo->exec("CREATE USER '{$db_user}'@'%' IDENTIFIED BY '{$db_password}'");

        // Otorgar privilegios al nuevo usuario en su BD
        error_log("DEBUG: Otorgando privilegios a {$db_user} en {$db_name}");
        $root_pdo->exec("GRANT ALL PRIVILEGES ON `{$db_name}`.* TO '{$db_user}'@'localhost'");
        $root_pdo->exec("GRANT ALL PRIVILEGES ON `{$db_name}`.* TO '{$db_user}'@'%'");

        // Otorgar permisos al usuario central en la nueva BD (necesario para consultas cruzadas en /empleados)
        $central_user = EMPRESAS_USER;
        $root_pdo->exec("GRANT SELECT ON `{$db_name}`.* TO '{$central_user}'@'localhost'");

        // Otorgar permisos de lectura en api_empresas
        error_log('DEBUG: Otorgando permisos de lectura en api_empresas');
        $root_pdo->exec("GRANT SELECT ON `api_empresas`.* TO '{$db_user}'@'localhost'");
        $root_pdo->exec("GRANT SELECT ON `api_empresas`.* TO '{$db_user}'@'%'");

        // Otorgar permisos para ejecutar funciones/rutinas en api_empresas
        error_log('DEBUG: Otorgando permisos EXECUTE en api_empresas');
        $root_pdo->exec("GRANT EXECUTE ON `api_empresas`.* TO '{$db_user}'@'localhost'");
        $root_pdo->exec("GRANT EXECUTE ON `api_empresas`.* TO '{$db_user}'@'%'");

        // Aplicar cambios de privilegios
        $root_pdo->exec('FLUSH PRIVILEGES');
        error_log("DEBUG: FLUSH PRIVILEGES ejecutado - Usuario {$db_user} creado con permisos");

        // 3. Crear tablas en la nueva base de datos
        error_log("DEBUG: Creando tablas en {$db_name}");

        // Leer archivo SQL
        $sql_file = __DIR__ . '/../public/model/create_new_company_api_emp_N.sql';
        if (!file_exists($sql_file)) {
          throw new Exception('Archivo SQL no encontrado: ' . $sql_file);
        }

        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
          throw new Exception('No se pudo leer el archivo SQL');
        }

        // Verificar que el usuario puede conectarse antes de ejecutar SQL
        error_log("DEBUG: Verificando conexión con nuevo usuario {$db_user}");
        try {
          $test_dsn = 'mysql:host=localhost;dbname=' . $db_name;
          $test_pdo = new PDO($test_dsn, $db_user, $db_password, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
          ]);
          $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          error_log("DEBUG: Conexión de prueba exitosa con {$db_user}");
          $test_pdo = null;  // Cerrar conexión de prueba
        } catch (Exception $e) {
          error_log('ERROR: Falló conexión de prueba: ' . $e->getMessage());
          throw new Exception('Usuario creado pero no puede conectarse: ' . $e->getMessage());
        }

        // Conectar con el nuevo usuario a la nueva base de datos
        $new_db_dsn = 'mysql:host=localhost;dbname=' . $db_name;
        $new_db_pdo = new PDO($new_db_dsn, $db_user, $db_password, [
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
        ]);
        $new_db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ejecutar el SQL por partes para manejar DELIMITER correctamente
        try {
          // Función para dividir SQL en statements
          $statements = splitSqlStatements($sql_content);

          foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
              // Saltar comentarios y líneas vacías, pero permitir /*! */ (comandos MySQL)
              if (strpos($statement, '--') === 0 || (strpos($statement, '/*') === 0 && strpos($statement, '/*!') !== 0)) {
                continue;
              }
              // Saltar comandos DELIMITER
              if (stripos($statement, 'DELIMITER') === 0) {
                continue;
              }

              try {
                $new_db_pdo->exec($statement);
              } catch (Exception $e) {
                error_log('ERROR: Falló statement: ' . substr($statement, 0, 100) . '... - ' . $e->getMessage());
                throw new Exception('Error al ejecutar sentencia SQL: ' . $e->getMessage() . ' - Sentencia: ' . substr($statement, 0, 200));
              }
            }
          }

          error_log("DEBUG: Tablas creadas exitosamente en {$db_name}");
        } catch (Exception $e) {
          error_log('ERROR: Falló creación de tablas: ' . $e->getMessage());
          throw new Exception('Error al crear tablas: ' . $e->getMessage());
        }
      } catch (Exception $e) {
        // Si falla la creación de BD/usuario, eliminar la empresa creada
        $stmt = $pdo->prepare('DELETE FROM empresas WHERE id_empresa = ?');
        $stmt->execute([$id_empresa]);
        throw new Exception('Error al crear infraestructura de base de datos: ' . $e->getMessage());
      }

      // Debug: Verificar valores antes del UPDATE
      error_log("DEBUG: id_empresa={$id_empresa}, db_name={$db_name}, db_user={$db_user}");

      // Actualizar la empresa con las credenciales de BD
      $stmt = $pdo->prepare('UPDATE empresas SET db_name = ?, db_user = ?, db_password = ? WHERE id_empresa = ?');
      $result = $stmt->execute([$db_name, $db_user, $db_password, $id_empresa]);
      error_log("DEBUG: UPDATE empresas result: " . ($result ? 'true' : 'false') . ", affected rows: " . $stmt->rowCount());

      // Verificar que la ejecución fue exitosa
      if (!$result) {
        throw new Exception('Error al ejecutar el UPDATE de empresas: ' . implode(', ', $stmt->errorInfo()));
      }

      // Verificar que la actualización afectó filas
      $affected_rows = $stmt->rowCount();
      error_log("DEBUG: UPDATE affected_rows={$affected_rows}");

      if ($affected_rows === 0) {
        // Verificar si el registro aún existe
        $stmt_check = $pdo->prepare('SELECT id_empresa FROM empresas WHERE id_empresa = ?');
        $stmt_check->execute([$id_empresa]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
          throw new Exception('El registro de empresa fue eliminado antes del UPDATE');
        } else {
          throw new Exception('UPDATE ejecutado pero no afectó filas. Registro existe pero WHERE no coincidió');
        }
      }

      // 3. Generar password aleatorio
      $password_generated = bin2hex(random_bytes(8));  // 16 caracteres hexadecimales
      $nombre = 'Administrador';  // Definir nombre por defecto para el administrador

      // 4. Crear registro en empresas_usuarios
      $stmt = $pdo->prepare('INSERT INTO empresas_usuarios (email, password, departamento, id_empresa, activo, acceso, comision, comision_tipo, comision_porcentaje) VALUES (?, ?, ?, ?, 1, 1, 1.00, ?, 0.00)');
      $stmt->execute([$email, $password_generated, 'Administración', $id_empresa, 'fija']);
      $id_usuario = $pdo->lastInsertId();
      error_log("DEBUG: Usuario creado con ID: {$id_usuario}");

      // Verificar que el usuario se creó correctamente
      if (!$id_usuario) {
        throw new Exception('No se pudo crear el registro del usuario en empresas_usuarios');
      }

      // 5. Crear registro en empresas_usuarios_departamentos
      $stmt_dept = $pdo->prepare('INSERT INTO empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES (?, 5)');
      $stmt_dept->execute([$id_usuario]);

      // 6. Crear empleados de ejemplo
      error_log("DEBUG: Creando empleados de ejemplo");

      // Array de empleados de ejemplo con sus datos
      $empleados_ejemplo = [
        [
          'nombre' => 'Empleado Impresión',
          'email' => 'juan.perez@empresa' . $id_empresa . '.com',
          'telefono' => '5841255501820',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Impresión',
          'id_departamento' => 1,
          'comision' => 1.00,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '43245432312',
          'dni' => '43543123'

        ],
        [
          'nombre' => 'Empleado Estampado',
          'email' => 'maria.gonzalez@empresa' . $id_empresa . '.com',
          'telefono' => '5841487633910',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Estampado',
          'id_departamento' => 2,
          'comision' => 1.00,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '9097897876',
          'dni' => '87987765'
        ],
        [
          'nombre' => 'Empleado Corte',
          'email' => 'carlos.rodriguez@empresa' . $id_empresa . '.com',
          'telefono' => '5841623477050',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Corte',
          'id_departamento' => 3,
          'comision' => 1.00,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '878878323433',
          'dni' => '23890763'
        ],
        [
          'nombre' => 'Empleado Costura',
          'email' => 'ana.martinez@empresa' . $id_empresa . '.com',
          'telefono' => '5842211299380',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Costura',
          'id_departamento' => 4,
          'comision' => 1.00,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '908787676566',
          'dni' => '12900834'
        ],
        [
          'nombre' => 'Empleado Diseño',
          'email' => 'luisa.fernandez@empresa' . $id_empresa . '.com',
          'telefono' => '5842460154200',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Diseño',
          'id_departamento' => 7,
          'comision' => 1.50,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '6678768768787',
          'dni' => '19899767'
        ],
        [
          'nombre' => 'Empleado Comercialización',
          'email' => 'roberto.diaz@empresa' . $id_empresa . '.com',
          'telefono' => '5841472588060',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Comercialización',
          'id_departamento' => 6,
          'comision' => 5.00,
          'comision_tipo' => 'variable',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '132234778653',
          'dni' => '20874445'
        ],
        [
          'nombre' => 'Empleado Producción',
          'email' => 'produccion@empresa' . $id_empresa . '.com',
          'telefono' => '5841298745630',
          'password' => bin2hex(random_bytes(8)),
          'departamento' => 'Producción',
          'id_departamento' => 8,
          'comision' => 1.00,
          'comision_tipo' => 'fija',
          'salario_monto' => 400.00,
          'id_seguridad_social' => '998877665544',
          'dni' => '15678234'
        ]
      ];

      $empleados_creados = [];

      // Crear cada empleado de ejemplo
      foreach ($empleados_ejemplo as $empleado_data) {
        // Crear registro en empresas_usuarios
        $stmt = $pdo->prepare('INSERT INTO empresas_usuarios (nombre, email, telefono, password, departamento, id_empresa, activo, acceso, comision, comision_tipo, comision_porcentaje, salario_monto, id_seguridad_social, dni) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, 0.00, ?, ?, ?)');
        $stmt->execute([
          $empleado_data['nombre'],
          $empleado_data['email'],
          $empleado_data['telefono'],
          $empleado_data['password'],
          $empleado_data['departamento'],
          $id_empresa,
          $empleado_data['comision'],
          $empleado_data['comision_tipo'],
          $empleado_data['salario_monto'],
          $empleado_data['id_seguridad_social'],
          $empleado_data['dni']
        ]);
        $id_empleado = $pdo->lastInsertId();

        // Crear registro en empresas_usuarios_departamentos
        $stmt_dept = $pdo->prepare('INSERT INTO empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES (?, ?)');
        $stmt_dept->execute([$id_empleado, $empleado_data['id_departamento']]);

        $empleados_creados[] = [
          'id_empleado' => $id_empleado,
          'nombre' => $empleado_data['nombre'],
          'email' => $empleado_data['email'],
          'telefono' => $empleado_data['telefono'],
          'departamento' => $empleado_data['departamento'],
          'id_departamento' => $empleado_data['id_departamento']
        ];

        error_log("DEBUG: Empleado creado: {$empleado_data['email']} (ID: {$id_empleado})");
      }

      // 7. Crear registros en empleados_salario para todos los empleados (incluyendo administrador)
      error_log("DEBUG: Creando registros en empleados_salario");

      // Array con todos los empleados (administrador + empleados de ejemplo)
      $todos_los_empleados = array_merge([
        [
          'id_empleado' => $id_usuario,
          'nombre' => $nombre,
          'email' => $email,
          'departamento' => 'Administración',
          'id_departamento' => 5,
          'salario_base' => 600.00,
          'bonos_fijos' => 50.00
        ]
      ], array_map(function ($emp) {
        return array_merge($emp, [
          'salario_base' => 450.00, // Salario base por defecto
          'bonos_fijos' => 25.00   // Bonos fijos por defecto
        ]);
      }, $empleados_creados));

      // Crear registros en empleados_salario en la base de datos de la empresa
      foreach ($todos_los_empleados as $empleado) {
        $stmt_salario = $new_db_pdo->prepare('INSERT INTO empleados_salario (id_empleado, sueldo_base, moneda, bonos_fijos, fecha_inicio_contrato, pago_mensual_fijo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt_salario->execute([
          $empleado['id_empleado'],
          $empleado['salario_base'],
          'USD',
          $empleado['bonos_fijos'],
          date('Y-m-d'),
          1
        ]);
        error_log("DEBUG: Registro en empleados_salario creado para empleado ID: {$empleado['id_empleado']}");
      }

      // 8. Crear datos de ejemplo para carga familiar
      error_log("DEBUG: Creando datos de ejemplo para carga familiar");

      // Array de carga familiar de ejemplo para algunos empleados
      $carga_familiar_ejemplo = [
        [
          'id_empleado' => $empleados_creados[0]['id_empleado'], // Juan Pérez
          'tipo_relacion' => 'Hijo',
          'fecha_nacimiento' => '2015-03-15',
          'es_deducible_impuesto' => 1
        ],
        [
          'id_empleado' => $empleados_creados[0]['id_empleado'], // Juan Pérez
          'nombre_completo' => 'María Pérez',
          'tipo_relacion' => 'Hija',
          'fecha_nacimiento' => '2018-07-22',
          'es_deducible_impuesto' => 1
        ],
        [
          'id_empleado' => $empleados_creados[1]['id_empleado'], // María González
          'nombre_completo' => 'José González',
          'tipo_relacion' => 'Cónyuge',
          'fecha_nacimiento' => '1988-03-22',
          'es_deducible_impuesto' => 1
        ],
        [
          'id_empleado' => $empleados_creados[3]['id_empleado'], // Ana Martínez
          'nombre_completo' => 'Miguel Martínez',
          'tipo_relacion' => 'Hijo',
          'fecha_nacimiento' => '2012-11-10',
          'es_deducible_impuesto' => 1
        ],
        [
          'id_empleado' => $empleados_creados[4]['id_empleado'], // Luisa Fernández
          'nombre_completo' => 'Antonio Fernández',
          'tipo_relacion' => 'Padre',
          'fecha_nacimiento' => '1960-09-05',
          'es_deducible_impuesto' => 0
        ]
      ];

      // Crear registros en salario_carga_familiar
      foreach ($carga_familiar_ejemplo as $carga) {
        $stmt_carga = $new_db_pdo->prepare('INSERT INTO salario_carga_familiar (id_empleado, nombre_completo, tipo_relacion, fecha_nacimiento, es_deducible_impuesto) VALUES (?, ?, ?, ?, ?)');
        $stmt_carga->execute([
          $carga['id_empleado'],
          $carga['nombre_completo'] ?? 'Nombre de ejemplo',
          $carga['tipo_relacion'],
          $carga['fecha_nacimiento'],
          $carga['es_deducible_impuesto']
        ]);
        error_log("DEBUG: Registro en salario_carga_familiar creado para empleado ID: {$carga['id_empleado']}");
      }

      // Retornar respuesta con los datos
      $response->getBody()->write(json_encode([
        'id_usuario' => $id_usuario,
        'nombre' => $nombre,
        'email' => $email,
        'password' => $password_generated,
        'id_empresa' => $id_empresa,
        'message' => 'Usuario creado exitosamente',
        'departamento' => 'Administración',
        'activo' => true,
        'acceso' => true,
        'comision' => 1.0,
        'comision_tipo' => 'fija'
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });
};
