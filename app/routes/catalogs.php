<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** * TELAS */
  $app->get('/telas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['data'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** * CATALOGO TINTAS (Con migración auto-ejecutable para Empresa 194) */
  $app->get('/catalogo-tintas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    try {
      // 1. Crear catalogo_tintas si no existe
      $localConnection->goQuery("CREATE TABLE IF NOT EXISTS `catalogo_tintas` (
        `_id` int(11) NOT NULL AUTO_INCREMENT,
        `nombre` varchar(128) NOT NULL,
        `moment` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");

      // 2. Poblar catalogo_tintas si está vacío
      $count = $localConnection->goQuery("SELECT COUNT(*) as total FROM `catalogo_tintas`");
      if (empty($count) || intval($count[0]['total'] ?? 0) === 0) {
        $localConnection->goQuery("INSERT INTO `catalogo_tintas` (`_id`, `nombre`) VALUES
          (1, 'Tinta de Sublimación'),
          (2, 'Tinta DTF (Direct to Film)'),
          (3, 'Tinta DTG (Direct to Garment)'),
          (4, 'Tinta Ácida'),
          (5, 'Tinta Reactiva'),
          (6, 'Tinta de Pigmento Textil (Directo a Tela)'),
          (7, 'Tinta UV Textil / Eco-Solvente'),
          (8, 'Plastisol'),
          (9, 'Tinta de Base Agua'),
          (10, 'Tintas de Descarga'),
          (11, 'Tintas de Silicona'),
          (12, 'Pastas de Pigmento'),
          (13, 'Pastas Reactivas y Dispersas'),
          (14, 'Tintas Metalizadas / Escarchadas (Glitter)'),
          (15, 'Tintas Fotocromáticas'),
          (16, 'Tintas Fluorescentes / Neón'),
          (17, 'Tintas Fosforescentes (Glow in the dark)'),
          (18, 'Tintas Foil / Adhesivos'),
          (19, 'Tintas de Alto Relieve (Puff / Espumantes)'),
          (20, 'Tintas Reflectivas');");
      }

      // 3. Alterar tinta_filtro si no tiene id_catalogo_tintas
      $columns = $localConnection->goQuery("SHOW COLUMNS FROM `tinta_filtro` LIKE 'id_catalogo_tintas'");
      if (empty($columns)) {
        // Añadir columna id_catalogo_tintas
        $localConnection->goQuery("ALTER TABLE `tinta_filtro` ADD COLUMN `id_catalogo_tintas` int(11) DEFAULT NULL;");

        // Rellenar valores por defecto (Tinta de Sublimación = 1)
        $localConnection->goQuery("UPDATE `tinta_filtro` SET `id_catalogo_tintas` = 1;");

        // Añadir la constraint de clave foránea
        $localConnection->goQuery("ALTER TABLE `tinta_filtro` ADD CONSTRAINT `tinta_filtro_ibfk_2` FOREIGN KEY (`id_catalogo_tintas`) REFERENCES `catalogo_tintas` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE;");
      }
    } catch (\Exception $e) {
      error_log("Error en migración auto-ejecutable de catalogo_tintas: " . $e->getMessage());
    }

    $sql = 'SELECT * FROM catalogo_tintas ORDER BY nombre';
    $object['data'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-tintas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $nombre = $data['nombre'] ?? '';

    $sql = "INSERT INTO catalogo_tintas (nombre) VALUES ('$nombre')";
    $result = $localConnection->goQuery($sql);
    $lastId = $localConnection->getLastID();

    $sqlNew = "SELECT * FROM catalogo_tintas WHERE _id = $lastId";
    $newItem = $localConnection->goQuery($sqlNew);

    $object['response'] = $result;
    $object['data'] = $newItem[0] ?? null;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-tintas/{_id}/{nombre}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $values = "nombre='" . $args['nombre'] . "'";
    $sql = 'UPDATE catalogo_tintas SET ' . $values . ' WHERE _id = ' . $args['_id'] . ';';
    $localConnection->goQuery($sql);

    $sqlAll = 'SELECT * FROM catalogo_tintas ORDER BY nombre';
    $object['data'] = $localConnection->goQuery($sqlAll);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-tintas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $body = $request->getParsedBody();
    $id = isset($body['id']) ? intval($body['id']) : 0;

    $sql = "DELETE FROM catalogo_tintas WHERE _id = $id";
    $object['response'] = $localConnection->goQuery($sql);
    
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** * CATALOGO INSUMOS PRODUCTOS */
  $app->get('/catalogo-insumos-productos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_insumos_productos ORDER BY nombre';
    $object['data'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-insumos-productos', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $nombre = $data['insumo']; // The payload uses 'insumo' for the name
    $id_product = isset($data['id_product']) ? intval($data['id_product']) : 0;
    $id_departamento = isset($data['id_departamento']) ? intval($data['id_departamento']) : 0;

    $sql = "INSERT INTO catalogo_insumos_productos (nombre, id_product, id_departamento) VALUES ('$nombre', $id_product, $id_departamento)";

    $result = $localConnection->goQuery($sql);
    $lastId = $localConnection->getLastID();

    // Fetch the newly created item to return it
    $sqlNew = "SELECT * FROM catalogo_insumos_productos WHERE _id = $lastId";
    $newItem = $localConnection->goQuery($sqlNew);

    $object['response'] = $result;
    $object['data'] = $newItem;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas', function (Request $request, Response $response) {
    $miTela = $request->getParsedBody();
    $object['miTela'] = $miTela;

    $miTela = $request->getParsedBody();

    // Crear estructura de valores para insertar nuevo cliente
    $values = '(';
    $values .= "'" . $miTela['tela'] . "')";

    $sql = 'INSERT INTO catalogo_telas (`tela`) VALUES ' . $values . ';';
    $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';

    $localConnection = new LocalDB();
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas/{_id}/{tela}', function (Request $request, Response $response, array $args) {
    // $miTela = $request->getParsedBody();
    $localConnection = new LocalDB();
    $values = "tela='" . $args['tela'] . "'";
    $sql = 'UPDATE catalogo_telas SET ' . $values . ' WHERE _id = ' . $args['_id'] . ';';
    $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $miEmpleado = $request->getParsedBody();
    $object['miEmpleado'] = $miEmpleado;
    $sql = 'DELETE FROM catalogo_telas WHERE _id =  ' . $miEmpleado['id'];
    $object['sql'] = $sql;

    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TELAS */
  $app->delete('/insumos-productos-asignados/{id_insumo}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'DELETE FROM product_insumos_asignados WHERE _id =  ' . $args['id_insumo'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/insumos-productos/{id_orden}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = "SELECT DISTINCT
                    a._id id_product_insumos_asignados,
                    b._id id_product,
                    e.id_orden,
                    d._id id_departamento,
                    b.product producto,
                    (SELECT nombre FROM sizes WHERE _id = e.talla) talla,
                    e.cantidad unidades_solicitadas,
                    c.nombre insumo,
                    d.departamento,
                    a.cantidad cantidad_estimada_de_consumo,
                    a.unidad,
                    (
                    SELECT
                        tiempo
                    FROM
                        products_tiempos_de_produccion
                    WHERE
                        id_product = b._id AND id_departamento = a.id_departamento
                ) tiempo_estimado_de_fabricación
                FROM
                    product_insumos_asignados a
                LEFT JOIN products b ON
                    b._id = a.id_product
                JOIN catalogo_insumos_productos c ON
                    c._id = a.id_catalogo_insumos_productos
                JOIN departamentos d ON
                    d._id = a.id_departamento
                JOIN ordenes_productos e ON a.id_product = e.id_woo
                WHERE e.id_orden = {$args['id_orden']} AND d._id = {$args['id_departamento']}        ";
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/insumos-productos-asignados', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
                a._id id_product_insumos_asignados,
                b._id id_product,
                d._id id_departamento,
                c._id id_catalogo_insumos_productos,
                a.id_talla,
                b.product producto,
                c.nombre insumo,
                d.departamento,
                a.cantidad,
                a.unidad,
                s.nombre AS talla,
                a.tiempo tiempo_cero,
                (SELECT tiempo FROM products_tiempos_de_produccion WHERE id_product = b._id AND id_departamento = a.id_departamento LIMIT 1) tiempo
            FROM
                product_insumos_asignados a
            LEFT JOIN products b ON b._id = a.id_product
            JOIN catalogo_insumos_productos c ON c._id = a.id_catalogo_insumos_productos
            JOIN departamentos d ON d._id = a.id_departamento
            LEFT JOIN sizes s ON s._id = a.id_talla
        ';
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** OBTENER TIEMPOS DE PRODUCCIÓN */
  $app->get('/tiempos-de-produccion', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
                tp._id id_tiempos_de_produccion,
                pr._id id_product,
                tp.tiempo,
                tp.usa_desperdicio,
                de._id id_departamento,
                pr.product,
                de.departamento
            FROM
                products_tiempos_de_produccion tp
            JOIN products pr ON pr._id = tp.id_product
            JOIN departamentos de ON de._id = tp.id_departamento 
            ORDER BY pr._id ASC
        ';
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** OBTENER TOTAL DE TIEMPOS DE PRODUCCIÓN */
  $app->get('/tiempos-de-produccion-total', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
            a._id id_product,
            a.product,
            SUM(b.tiempo) total_tiempo,
            CONCAT(
                "[",
                GROUP_CONCAT(
                    JSON_OBJECT(
                        "id_departamento",
                        c._id,
                        "departamento",
                        c.departamento,
                        "tiempo",
                        b.tiempo,
                        "usa_desperdicio",
                        b.usa_desperdicio
                    )
                ),
                "]"
            ) AS departamentos
        FROM
            products a
        JOIN products_tiempos_de_produccion b ON
            b.id_product = a._id
        LEFT JOIN departamentos c ON
            c._id = b.id_departamento
        GROUP BY
            a._id
        ORDER BY a.product ASC
        ';
    $products = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // PARSEAR RESULTADOS
    $key = 0;
    foreach ($products as $product) {
      $data[$key]['id_product'] = intval($product['id_product']);
      $data[$key]['product'] = $product['product'];
      $data[$key]['total_tiempo'] = $product['total_tiempo'];
      $data[$key]['tiempo_por_departamentos'] = json_decode($product['departamentos']);

      $key++;
    }

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** NUEVO TIEMPO DE PRODUCCION */
  $app->post('/tiempos-de-produccion', function (Request $request, Response $response) {
    $miTiempo = $request->getParsedBody();
    $localConnection = new LocalDB();

    $id_product = intval($miTiempo['id_product']);  // Convertir a entero para seguridad
    $departamento = intval($miTiempo['id_departamento']);  // Convertir a entero para seguridad
    $tiempo = intval($miTiempo['tiempo']);  // Convertir a entero para seguridad
    $usa_desperdicio = isset($miTiempo['usa_desperdicio']) ? intval($miTiempo['usa_desperdicio']) : 0;

    /** VERIFICAR EXISTENCIA DEL REGISTRO */
    $sql = "SELECT _id FROM products_tiempos_de_produccion WHERE id_product = $id_product AND id_departamento = $departamento";
    $object['response_verify'] = $localConnection->goQuery($sql);

    if (empty($object['response_verify'])) {
      // No existe, insertar nuevo registro
      $sql = "INSERT INTO products_tiempos_de_produccion (id_product, id_departamento, tiempo, usa_desperdicio) VALUES ($id_product, $departamento, $tiempo, $usa_desperdicio);";
    } else {
      // Existe, actualizar registro
      $sql = "UPDATE products_tiempos_de_produccion SET tiempo = $tiempo, usa_desperdicio = $usa_desperdicio WHERE id_product = $id_product AND id_departamento = $departamento;";
    }

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** ACTUALIZAR FLAG DE DESPERDICIO DEDICADO */
  $app->post('/tiempos-de-produccion/usa-desperdicio', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $localConnection = new LocalDB();

    $id_product = intval($body['id_product']);
    $id_departamento = intval($body['id_departamento']);
    $usa_desperdicio = isset($body['usa_desperdicio']) ? intval($body['usa_desperdicio']) : 0;

    /** VERIFICAR EXISTENCIA DEL REGISTRO */
    $sql = "SELECT _id FROM products_tiempos_de_produccion WHERE id_product = $id_product AND id_departamento = $id_departamento";
    $verify = $localConnection->goQuery($sql);

    if (empty($verify)) {
      $sql = "INSERT INTO products_tiempos_de_produccion (id_product, id_departamento, tiempo, usa_desperdicio) VALUES ($id_product, $id_departamento, 0, $usa_desperdicio);";
    } else {
      $sql = "UPDATE products_tiempos_de_produccion SET usa_desperdicio = $usa_desperdicio WHERE id_product = $id_product AND id_departamento = $id_departamento;";
    }

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** CATALOG DE INSUMOS PARA CLASIFICACIÓN DE PROIDUCTOS */
  $app->post('/insumos-productos', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Preparar los parámetros
    // Aseguramos que id_talla (recibido como id_size) sea null si no se envía o está vacío
    $id_talla = isset($miInsumo['id_size']) && !empty($miInsumo['id_size']) && $miInsumo['id_size'] !== 'null' ? intval($miInsumo['id_size']) : null;
    $id_product = intval($miInsumo['id_product']);
    $id_departamento = intval($miInsumo['departamento']);
    $id_catalogo = intval($miInsumo['insumo']);

    // Verificar si ya existe un registro con la misma combinación
    $sql_check = 'SELECT _id FROM product_insumos_asignados 
                  WHERE id_product = ? 
                    AND id_departamento = ? 
                    AND id_catalogo_insumos_productos = ? 
                    AND (id_talla = ? OR (id_talla IS NULL AND ? IS NULL))';
    $check_params = [$id_product, $id_departamento, $id_catalogo, $id_talla, $id_talla];
    $existing = $localConnection->goQuery($sql_check, $check_params);

    if (!empty($existing)) {
      // Ya existe, devolver error
      $localConnection->disconnect();
      $object['error'] = true;
      $object['message'] = 'Ya existe un insumo asignado con esta combinación de producto, departamento, catálogo y talla.';
      $object['existing_id'] = $existing[0]['_id'];
      $response->getBody()->write(json_encode($object));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(409); // Conflict
    }

    // No existe, proceder con la inserción
    $sql = 'INSERT INTO product_insumos_asignados 
                    (id_product, id_departamento, id_catalogo_insumos_productos, cantidad, unidad, id_talla) 
                VALUES (?, ?, ?, ?, ?, ?)';

    $params = [
      $id_product,
      $id_departamento,
      $id_catalogo,
      $miInsumo['cantidad'],
      $miInsumo['unidad'],
      $id_talla
    ];

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql, $params);
    $object['error'] = false;
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });


}; // Fin de la función que envuelve las rutas
