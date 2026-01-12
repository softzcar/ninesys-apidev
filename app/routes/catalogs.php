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
                        b.tiempo
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

    /** VERIFICAR EXISTENCIA DEL REGISTRO */
    $sql = "SELECT _id FROM products_tiempos_de_produccion WHERE id_product = $id_product AND id_departamento = $departamento";
    $object['response_verify'] = $localConnection->goQuery($sql);

    if (empty($object['response_verify'])) {
      // No existe, insertar nuevo registro
      $sql = "INSERT INTO products_tiempos_de_produccion (id_product, id_departamento, tiempo) VALUES ($id_product, $departamento, $tiempo);";
    } else {
      // Existe, actualizar registro
      $sql = "UPDATE products_tiempos_de_produccion SET tiempo = $tiempo WHERE id_product = $id_product AND id_departamento = $departamento;";
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

    // Usar sentencias preparadas para prevenir inyección SQL
    $sql = 'INSERT INTO product_insumos_asignados 
                    (id_product, id_departamento, id_catalogo_insumos_productos, cantidad, unidad, id_talla) 
                VALUES (?, ?, ?, ?, ?, ?)';

    // Preparar los parámetros para la consulta
    // Aseguramos que id_talla (recibido como id_size) sea null si no se envía o está vacío
    $id_talla = isset($miInsumo['id_size']) && !empty($miInsumo['id_size']) && $miInsumo['id_size'] !== 'null' ? $miInsumo['id_size'] : null;

    $params = [
      $miInsumo['id_product'],
      $miInsumo['departamento'],
      $miInsumo['insumo'],
      $miInsumo['cantidad'],
      $miInsumo['unidad'],
      $id_talla
    ];

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql, $params);
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });


}; // Fin de la función que envuelve las rutas
