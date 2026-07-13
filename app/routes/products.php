<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** PRODUCTOS */

  // Obtener todos los productos

  $app->get('/customer/create/nine', function (Request $request, Response $response) {
    $woo = new WooMe();
    // $response->getBody()->write($woo->createCustomerNeneteen());
    $response->getBody()->write($woo->updateCustomerNine(36));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products', function (Request $request, Response $response) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getAllProducts());

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/products-attributes', function (Request $request, Response $response) {
    $miAtributo = $request->getParsedBody();

    // Crear estructura de valores para insertar nuevo atributo de producto
    $values = '(';
    $values .= "'" . $miAtributo['nombre'] . "',";
    $values .= "'" . $miAtributo['precio'] . "')";

    $sql = 'INSERT INTO products_attributes (attribute_name, precio) VALUES ' . $values . ';';
    $sql .= 'SELECT * FROM products_attributes ORDER BY products_attributes';

    $localConnection = new LocalDB();
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products-attributes', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT precio, attribute_name as name, _id FROM products_attributes ORDER BY name ASC';
    $attributes = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $object['data'] = $attributes;

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Editar un atributo de producto existente
  $app->post('/products-attributes/editar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'UPDATE products_attributes SET attribute_name = ?, precio = ? WHERE _id = ?';
    $result = $localConnection->goQuery($sql, [$data['name'], $data['precio'], $data['id']]);
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Atributo de producto actualizado exitosamente.',
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar un atributo de producto
  $app->post('/products-attributes/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'DELETE FROM products_attributes WHERE _id = ?';
    $result = $localConnection->goQuery($sql, [$data['id']]);
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Atributo de producto eliminado exitosamente.',
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products/categories/{id_category}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getCategoryById($args['id_category']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener todos los productos asignados a una orden
  $app->get('/productos-asignados/{orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = ' . $args['orden'] . " AND category_name != 'Diseños'";

    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener prodcuto por ID
  $app->get('/products/{id}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $product = $woo->getProductById($args['id']);
    $response->getBody()->write(json_encode($product));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo producto
  $app->post('/products/{name}/{sku}/{price}/{stock_quantity}/{categories}/{sizes}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $response->getBody()->write($woo->createProduct($args['name'], $args['sku'], $args['price'], $args['stock_quantity'], $args['categories'], $args['sizes']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo producto lite
  // $app->post('/products/lite/{name}/{price}/{categories}', function (Request $request, Response $response, array $args) {
  $app->post('/products/lite', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $producto_fisico = $data['producto_fisico'] ?? 0;
    $es_diseno = $data['es_diseno'] ?? 0;
    $stock_quantity = $data['stock_quantity'] ?? 0;
    $responseProd = $woo->createProductLite($data['product'], $data['prices'], $data['category'], $data['sku'], $producto_fisico, $es_diseno, $stock_quantity);

    // $response->getBody()->write($responseProd);
    $response->getBody()->write(json_encode($responseProd));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
    // $response->getBody()->write(json_encode($woo->getAllProducts()));
  });

  // Editar producto
  $app->post('/editar-producto', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $producto_fisico = $data['producto_fisico'] ?? 0;
    $es_diseno = $data['es_diseno'] ?? 0;
    $stock_quantity = $data['stock_quantity'] ?? 0;

    $result = $woo->updateProductLite($data['id'], $data['product'], $data['sku'], $data['category'], $producto_fisico, $es_diseno, $stock_quantity);

    $response->getBody()->write(json_encode([$result]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Editar precios de producto
  $app->post('/editar-producto-precios', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Atomicidad: alta/edición de precios (products_prices) en una transacción
    $localConnection->beginTransaction();

    // ACTUALIZAR O INSERTAR PRECIOS
    $prices = json_decode($data['prices'], true);
    $debug_prices = [];

    foreach ($prices as $price) {
      $price_id = $price['id'] ?? null;
      $price_value = $price['price'] ?? 0;
      $price_desc = $price['description'] ?? '';

      if (!empty($price_id)) {
        // Es un precio existente, hacer UPDATE
        $sql_price = 'UPDATE products_prices SET price = ?, descripcion = ? WHERE _id = ?';
        $params = [$price_value, $price_desc, $price_id];
        $localConnection->goQuery($sql_price, $params);
        $debug_prices[] = ['action' => 'update', 'sql' => $sql_price, 'params' => $params];
      } else {
        // Es un precio nuevo, hacer INSERT
        $sql_price = 'INSERT INTO products_prices (id_product, price, descripcion) VALUES (?, ?, ?)';
        $params = [$data['product_id'], $price_value, $price_desc];
        $localConnection->goQuery($sql_price, $params);
        $debug_prices[] = ['action' => 'insert', 'sql' => $sql_price, 'params' => $params];
      }
    }

    // OBTENER PRECIOS ACTUALIZADOS
    $sql_get_prices = 'SELECT _id as id, price, descripcion as description FROM products_prices WHERE id_product = ?';
    $updated_prices = $localConnection->goQuery($sql_get_prices, [$data['product_id']]);

    $localConnection->commit();
    $localConnection->disconnect();

    $object['prices'] = $updated_prices;
    $response->getBody()->write(json_encode([$object]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar un precio de producto
  $app->delete('/products-prices/{id}', function (Request $request, Response $response, array $args) {
    $id_price = $args['id'];
    $localConnection = new LocalDB();

    try {
      // 1. Obtener el id_product antes de eliminar el precio
      $sql_get_product_id = 'SELECT id_product FROM products_prices WHERE _id = ?';
      $product_id_result = $localConnection->goQuery($sql_get_product_id, [$id_price]);

      if (empty($product_id_result)) {
        $response->getBody()->write(json_encode(['error' => 'Precio no encontrado.']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(404);
      }
      $id_product = $product_id_result[0]['id_product'];

      // 2. Eliminar el precio
      $sql_delete = 'DELETE FROM products_prices WHERE _id = ?';
      $localConnection->goQuery($sql_delete, [$id_price]);

      // 3. Obtener la lista actualizada de precios para el producto
      $sql_get_prices = 'SELECT _id as id, price, descripcion as description FROM products_prices WHERE id_product = ?';
      $updated_prices = $localConnection->goQuery($sql_get_prices, [$id_product]);

      // 4. Formatear la respuesta
      $object['prices'] = $updated_prices;
      $response->getBody()->write(json_encode([$object]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    } catch (Exception $e) {
      error_log('Error al eliminar precio: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al eliminar el precio.']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // Actualizar Stock
  $app->put('/products/stock/{id}/{stock_quantity}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();

    $response->getBody()->write($woo->updateProductStock($args['id'], $args['stock_quantity']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar Producto (Usamos el metodo `options` porque noo acepta metodo `delete`  da ERROR 405)
  $app->delete('/products/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'UPDATE products SET eliminado = 1 WHERE _id = ' . $args['id'];
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object), JSON_NUMERIC_CHECK);

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN PROIDUCTOS */

  /** MANEJO DE ATRIBUTOS PARA PRODUCTOS */

  // ASIGNAR COMISION A PRODUCTO
  $app->post('/product-set-comision-producto', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $tmpConnection = new LocalDB();

    // Atomicidad FK: comisión del producto + recálculo retroactivo de pagos en una transacción
    $tmpConnection->beginTransaction();

    $sql = "SELECT _id FROM products_comisiones WHERE id_product = {$data['id_product']} AND id_departamento = {$data['id_departamento']}";
    $object['response_verify'] = $tmpConnection->goQuery($sql);

    if (empty($object['response_verify'])) {
      $sql = 'INSERT INTO products_comisiones (comision, id_product, id_departamento) VALUES (' . $data['comision'] . ', ' . $data['id_product'] . ", '" . $data['id_departamento'] . "');";
    } else {
      $sql = "UPDATE products_comisiones SET comision = {$data['comision']} WHERE id_product = {$data['id_product']} AND id_departamento = {$data['id_departamento']}";
    }

    $object['sql'] = $sql;

    $object['response'] = $tmpConnection->goQuery($sql);

    // Recálculo retroactivo en pagos (para lotes no pagados)
    $comisionVal = floatval($data['comision']);
    $idProductVal = intval($data['id_product']);
    $idDeptoVal = intval($data['id_departamento']);

    $sqlUpdatePagos = "UPDATE pagos p
        JOIN lotes_detalles_empleados_asignados a ON p.id_lotes_detalles = a._id
        SET p.monto_pago = (
              SELECT SUM(
                  IFNULL(pc.comision, 0) *
                  ( IF(a.id_departamento = 3,
                      COALESCE(NULLIF((SELECT SUM(ic.cantidad) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = op._id), 0), op.cantidad),
                      op.cantidad
                    ) ) *
                  (IF(a.procentaje_comision > 0, a.procentaje_comision, 100) / 100)
              )
              FROM ordenes_productos op
              JOIN products p_woo ON op.id_woo = p_woo._id
              LEFT JOIN products_comisiones pc ON pc.id_product = op.id_woo AND pc.id_departamento = a.id_departamento
              WHERE op.id_orden = a.id_orden
                AND (p_woo.fisico = 1 OR p_woo.fisico IS NULL)
                AND (p_woo.es_diseno = 0 OR p_woo.es_diseno IS NULL)
          ),
          p.comision = (
              SELECT MAX(IFNULL(pc2.comision, 0))
              FROM ordenes_productos op2
              JOIN products p_woo2 ON op2.id_woo = p_woo2._id
              LEFT JOIN products_comisiones pc2 ON pc2.id_product = op2.id_woo AND pc2.id_departamento = a.id_departamento
              WHERE op2.id_orden = a.id_orden
                AND (p_woo2.fisico = 1 OR p_woo2.fisico IS NULL)
                AND (p_woo2.es_diseno = 0 OR p_woo2.es_diseno IS NULL)
          ),
          p.cantidad = (
              SELECT SUM(
                  ( IF(a.id_departamento = 3,
                      COALESCE(NULLIF((SELECT SUM(ic.cantidad) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = op3._id), 0), op3.cantidad),
                      op3.cantidad
                    ) ) *
                  (IF(a.procentaje_comision > 0, a.procentaje_comision, 100) / 100)
              )
              FROM ordenes_productos op3
              JOIN products p_woo3 ON op3.id_woo = p_woo3._id
              WHERE op3.id_orden = a.id_orden
                AND (p_woo3.fisico = 1 OR p_woo3.fisico IS NULL)
                AND (p_woo3.es_diseno = 0 OR p_woo3.es_diseno IS NULL)
          )
        WHERE p.fecha_pago IS NULL
          AND p.comision_tipo = 'variable'
          AND a.id_departamento = {$idDeptoVal}
          AND p.detalle != 'Corte-Excedente'
          AND EXISTS (
              SELECT 1 FROM ordenes_productos op_ex
              WHERE op_ex.id_orden = a.id_orden AND op_ex.id_woo = {$idProductVal}
          )
          AND (
              SELECT eu.salario_tipo
              FROM api_empresas.empresas_usuarios eu
              WHERE eu.id_usuario = a.id_empleado
          ) != 'Salario'";

    $tmpConnection->goQuery($sqlUpdatePagos);

    $tmpConnection->commit();
    $tmpConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ASIGNAR COMISIONES EN LOTE A PRODUCTOS
  $app->post('/product-set-comisiones-batch', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $tmpConnection = new LocalDB();
    $results = [];  // Para almacenar los resultados de cada operación

    // --- CAMBIO AQUÍ: Decodificar la cadena JSON de 'comisiones' ---
    $comisionesJsonString = $data['comisiones'] ?? null;

    if (empty($comisionesJsonString)) {
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No se recibió la cadena JSON de comisiones.']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);  // Bad Request
    }

    $batchData = json_decode($comisionesJsonString, true);

    if (!is_array($batchData)) {
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'La cadena JSON de comisiones no es un array válido.']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);  // Bad Request
    }
    // --- FIN CAMBIO ---

    foreach ($batchData as $commissionData) {  // Ahora iteramos sobre $batchData
      $id_product = $commissionData['id_product'];
      $id_departamento = $commissionData['id_departamento'];
      $comision = $commissionData['comision'];

      $operationResult = [
        'id_product' => $id_product,
        'id_departamento' => $id_departamento,
        'status' => 'success',
        'message' => ''
      ];

      try {
        // Atomicidad FK por iteración (preserva el éxito parcial del batch)
        $tmpConnection->beginTransaction();
        // Verificar si ya existe una comisión para este producto y departamento
        $sqlCheck = "SELECT _id FROM products_comisiones WHERE id_product = {$id_product} AND id_departamento = {$id_departamento}";
        $existingRecord = $tmpConnection->goQuery($sqlCheck);

        $sql = '';
        if (empty($existingRecord)) {
          // Si no existe, insertar
          $sql = "INSERT INTO products_comisiones (comision, id_product, id_departamento) VALUES ({$comision}, {$id_product}, {$id_departamento});";
        } else {
          // Si existe, actualizar
          $sql = "UPDATE products_comisiones SET comision = {$comision} WHERE id_product = {$id_product} AND id_departamento = {$id_departamento}";
        }

        $queryResult = $tmpConnection->goQuery($sql);
        $operationResult['sql'] = $sql;
        $operationResult['response'] = $queryResult;

        // Recálculo retroactivo en pagos (para lotes no pagados)
        $comisionVal = floatval($comision);
        $idProductVal = intval($id_product);
        $idDeptoVal = intval($id_departamento);

        $sqlUpdatePagos = "UPDATE pagos p
        JOIN lotes_detalles_empleados_asignados a ON p.id_lotes_detalles = a._id
        SET p.monto_pago = (
              SELECT SUM(
                  IFNULL(pc.comision, 0) *
                  ( IF(a.id_departamento = 3,
                      COALESCE(NULLIF((SELECT SUM(ic.cantidad) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = op._id), 0), op.cantidad),
                      op.cantidad
                    ) ) *
                  (IF(a.procentaje_comision > 0, a.procentaje_comision, 100) / 100)
              )
              FROM ordenes_productos op
              JOIN products p_woo ON op.id_woo = p_woo._id
              LEFT JOIN products_comisiones pc ON pc.id_product = op.id_woo AND pc.id_departamento = a.id_departamento
              WHERE op.id_orden = a.id_orden
                AND (p_woo.fisico = 1 OR p_woo.fisico IS NULL)
                AND (p_woo.es_diseno = 0 OR p_woo.es_diseno IS NULL)
          ),
          p.comision = (
              SELECT MAX(IFNULL(pc2.comision, 0))
              FROM ordenes_productos op2
              JOIN products p_woo2 ON op2.id_woo = p_woo2._id
              LEFT JOIN products_comisiones pc2 ON pc2.id_product = op2.id_woo AND pc2.id_departamento = a.id_departamento
              WHERE op2.id_orden = a.id_orden
                AND (p_woo2.fisico = 1 OR p_woo2.fisico IS NULL)
                AND (p_woo2.es_diseno = 0 OR p_woo2.es_diseno IS NULL)
          ),
          p.cantidad = (
              SELECT SUM(
                  ( IF(a.id_departamento = 3,
                      COALESCE(NULLIF((SELECT SUM(ic.cantidad) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = op3._id), 0), op3.cantidad),
                      op3.cantidad
                    ) ) *
                  (IF(a.procentaje_comision > 0, a.procentaje_comision, 100) / 100)
              )
              FROM ordenes_productos op3
              JOIN products p_woo3 ON op3.id_woo = p_woo3._id
              WHERE op3.id_orden = a.id_orden
                AND (p_woo3.fisico = 1 OR p_woo3.fisico IS NULL)
                AND (p_woo3.es_diseno = 0 OR p_woo3.es_diseno IS NULL)
          )
        WHERE p.fecha_pago IS NULL
          AND p.comision_tipo = 'variable'
          AND a.id_departamento = {$idDeptoVal}
          AND p.detalle != 'Corte-Excedente'
          AND EXISTS (
              SELECT 1 FROM ordenes_productos op_ex
              WHERE op_ex.id_orden = a.id_orden AND op_ex.id_woo = {$idProductVal}
          )
          AND (
              SELECT eu.salario_tipo
              FROM api_empresas.empresas_usuarios eu
              WHERE eu.id_usuario = a.id_empleado
          ) != 'Salario'";

        $tmpConnection->goQuery($sqlUpdatePagos);

        $tmpConnection->commit();
      } catch (Exception $e) {
        if ($tmpConnection->inTransaction()) {
          $tmpConnection->rollback();
        }
        $operationResult['status'] = 'error';
        $operationResult['message'] = $e->getMessage();
      }
      $results[] = $operationResult;
    }

    $tmpConnection->disconnect();

    $response->getBody()->write(json_encode(['status' => 'success', 'results' => $results]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ASIGNAR COMISION A PRODUCTO Y EMPLEADO
  $app->get('/product-set-comision/{id}/{comision}', function (Request $request, Response $response, array $args) {
    $tmpConnection = new LocalDB();
    /* $woo = new WooMe();
    $res = $woo->updateProductComision($args['id'], $args['comision']);
    $object['res'] = $res; */

    // PostgreSQL no permite multiples comandos en un solo prepared statement (a diferencia de
    // MySQL, que lo tolera); se separan en dos llamadas.
    $sqlUpdate = 'UPDATE products SET comision = ' . floatval($args['comision']) . ' WHERE _id = ' . $args['id'] . ';';
    $tmpConnection->goQuery($sqlUpdate);
    $sql = 'SELECT * FROM products WHERE _id = ' . $args['id'];
    $object['res'] = $tmpConnection->goQuery($sql);

    $sql1 = 'SELECT _id id_lotes_detalles, unidades_solicitadas, id_empleado FROM lotes_detalles WHERE id_woo = ' . $args['id'] . " AND departamento = 'Costura' AND fecha_terminado IS NOT NULL";
    $resp = $tmpConnection->goQuery($sql1);

    if (!empty($resp)) {
      foreach ($resp as $row) {
        // Obtener la cantidad desde la tabla pagos en lugar de unidades_solicitadas
        $sql_cantidad = 'SELECT cantidad FROM pagos WHERE id_empleado = ' . $row['id_empleado'] . ' AND fecha_pago IS NULL AND id_lotes_detalles = ' . $row['id_lotes_detalles'];
        $cantidad_resp = $tmpConnection->goQuery($sql_cantidad);

        if (!empty($cantidad_resp)) {
          $cantidad = intval($cantidad_resp[0]['cantidad']);
          $nuevo_pago = floatval($args['comision']) * $cantidad;
          $sql2 = 'UPDATE pagos SET monto_pago = ' . $nuevo_pago . ' WHERE id_empleado = ' . $row['id_empleado'] . ' AND fecha_pago IS NULL AND id_lotes_detalles = ' . $row['id_lotes_detalles'];
          $tmpConnection->goQuery($sql2);
        }
      }
    }

    $tmpConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENR LOS PARODUCTOS APRA LE MANEJO DE ATRIBUTOS DE COMSIONES
  $app->get('/atributos/comisiones', function (Request $request, Response $response) {
    $woo = new WooMe();
    // $object['data'] = json_decode($woo->getProductsAttr());
    $object['data'] = json_decode($woo->getAllProducts());
    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PRUEBA DE CONEXION DIRECTA A LA BASE DE DATOS `ninetengreen` de Wordpress
  $app->get('/wp/products', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllProducts());

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/wp/products/{id_product}', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllProducts());

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN MANEJO DE ATRIBUTOS PARA PRODUCTOS */

  /** * CLIENTES */

  // ELIMINAR UN CLIENTE
  $app->post('/customers/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER TODOS LOS CLIENTES
  $app->get('/customers', function (Request $request, Response $response) {
    $queryParams = $request->getQueryParams();
    $id_vendedor = isset($queryParams['id_vendedor']) ? intval($queryParams['id_vendedor']) : null;

    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllCustomesrs($id_vendedor));
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OPTMIZAR CLIENTES
  $app->get('/customers/optimize/{key}/{acc}', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER TODAS LAS ORDENES ASOCIADAS A UN CLIENTE
  $app->get('/customers/orders/{id_customer}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $object['data'] = $woo->getCustomerOrders($args['id_customer']);
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/wp/customers', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllCustomesrs());
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/customers/orders-count/{customer_email}/{customer_id}', function (Request $request, Response $response, array $args) {
    // Buscar la cantidad de ordenes en woocommerce
    $woo = new WooMe();
    $object['ordenes_wc'] = $woo->getOrdersCount($args['customer_email']);

    // Buscar si teiene ordenes activas en el sistema de prodsucción
    $localConnection = new LocalDB();
    $sql = "SELECT COUNT(a._id) total_ordenes FROM ordenes a WHERE (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') AND a.id_wp =  " . $args['customer_id'];
    $tmpRes = $localConnection->goQuery($sql);
    $object['ordenes_ns'] = (is_array($tmpRes) && isset($tmpRes[0]['total_ordenes'])) ? intval($tmpRes[0]['total_ordenes']) : 0;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener Cliente por ID
  $app->get('/customers/{id}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    // $customer = $woo->getCustomerById($args["id"]);
    $customer = $woo->getCustomerByIdWP($args['id']);

    $response->getBody()->write(json_encode($customer));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo cliente
  // Buscar un cliente ACTIVO por teléfono (para autocompletar formularios de
  // orden/presupuesto/CRUD). Busca por los últimos 10 dígitos. Devuelve {data: {...}|null}.
  $app->get('/customers/por-telefono/{phone}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $customer = $woo->getCustomerByPhone($args['phone']);
    $response->getBody()->write(json_encode(['data' => $customer]));
    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/customers/{first_name}/{last_name}/{cedula}/{phone}/{email}/{address}', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    if (empty($data)) {
      $bodyStream = $request->getBody();
      $bodyStream->rewind();
      $raw_body = $bodyStream->getContents();
      parse_str($raw_body, $data);
    }

    $idCatalogoPais = isset($data['id_catalogo_pais']) && $data['id_catalogo_pais'] !== '' ? intval($data['id_catalogo_pais']) : null;
    $idCatalogoEstado = isset($data['id_catalogo_estado']) && $data['id_catalogo_estado'] !== '' ? intval($data['id_catalogo_estado']) : null;
    $idCatalogoCiudad = isset($data['id_catalogo_ciudad']) && $data['id_catalogo_ciudad'] !== '' ? intval($data['id_catalogo_ciudad']) : null;
    $recibir = isset($data['recibir_notificaciones']) ? $data['recibir_notificaciones'] : null;

    $woo = new WooMe();
    // Este endpoint lo usa el flujo de creación de orden/presupuesto (nueva.vue /
    // presupuesto.vue). reactivar=true: si el teléfono coincide con un cliente
    // eliminado (soft delete), se reactiva automáticamente y se reutiliza (evita
    // duplicar el teléfono y no interrumpe la creación de la orden con una pregunta).
    $resJson = $woo->createCustomer(
      $args['first_name'],
      $args['last_name'],
      $args['cedula'],
      $args['phone'],
      $args['email'],
      $args['address'],
      $recibir,
      $idCatalogoPais,
      $idCatalogoEstado,
      $idCatalogoCiudad,
      true
    );
    $resObj = json_decode($resJson, true);
    $status = (isset($resObj['status']) && $resObj['status'] === 'error') ? 400 : 200;
    $response->getBody()->write($resJson);

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status);
  });

  // Actualizar Cliente
  $app->put('/customers/{id}/{first_name}/{last_name}/{cedula}/{phone}/{email}/{billing_address}', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $raw_body_debug = "";
    if (empty($data)) {
      $bodyStream = $request->getBody();
      $bodyStream->rewind();
      $raw_body_debug = $bodyStream->getContents();
      parse_str($raw_body_debug, $data);
    }
    
    $idCatalogoPais = isset($data['id_catalogo_pais']) && $data['id_catalogo_pais'] !== '' ? intval($data['id_catalogo_pais']) : null;
    $idCatalogoEstado = isset($data['id_catalogo_estado']) && $data['id_catalogo_estado'] !== '' ? intval($data['id_catalogo_estado']) : null;
    $idCatalogoCiudad = isset($data['id_catalogo_ciudad']) && $data['id_catalogo_ciudad'] !== '' ? intval($data['id_catalogo_ciudad']) : null;
    $recibir = isset($data['recibir_notificaciones']) ? $data['recibir_notificaciones'] : null;

    error_log("DEBUG PUT CUSTOMER - raw_body: " . $raw_body_debug);
    error_log("DEBUG PUT CUSTOMER - parsed data: " . json_encode($data));
    error_log("DEBUG PUT CUSTOMER - values: " . json_encode([
      'id' => $args['id'],
      'id_catalogo_pais' => $idCatalogoPais,
      'id_catalogo_estado' => $idCatalogoEstado,
      'id_catalogo_ciudad' => $idCatalogoCiudad
    ]));

    $woo = new WooMe();
    $resJson = $woo->updateCustomer(
      $args['id'], 
      $args['first_name'], 
      $args['last_name'], 
      $args['cedula'], 
      $args['phone'], 
      $args['email'], 
      $args['billing_address'],
      $recibir,
      $idCatalogoPais,
      $idCatalogoEstado,
      $idCatalogoCiudad
    );
    $resObj = json_decode($resJson, true);
    $status = (isset($resObj['status']) && $resObj['status'] === 'error') ? 400 : 200;
    $response->getBody()->write($resJson);

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status);
  });

  // Actualizar Cliente desde Admin
  $app->post('/customers/edit1', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $woo = new WooMe();
    $recibir = isset($data['recibir_notificaciones']) ? $data['recibir_notificaciones'] : null;
    $idCatalogoPais = isset($data['id_catalogo_pais']) && $data['id_catalogo_pais'] !== '' ? intval($data['id_catalogo_pais']) : null;
    $idCatalogoEstado = isset($data['id_catalogo_estado']) && $data['id_catalogo_estado'] !== '' ? intval($data['id_catalogo_estado']) : null;
    $idCatalogoCiudad = isset($data['id_catalogo_ciudad']) && $data['id_catalogo_ciudad'] !== '' ? intval($data['id_catalogo_ciudad']) : null;
    
    $resJson = $woo->updateCustomer($data['id'], $data['first_name'], $data['last_name'], $data['cedula'], $data['phone'], $data['email'], $data['address'], $recibir, $idCatalogoPais, $idCatalogoEstado, $idCatalogoCiudad);
    $resObj = json_decode($resJson, true);
    $status = (isset($resObj['status']) && $resObj['status'] === 'error') ? 400 : 200;
    $response->getBody()->write($resJson);

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status);
  });

  // Nuevo cliente desde Admin
  $app->post('/customers/nuevo', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $woo = new WooMe();
    $recibir = isset($data['recibir_notificaciones']) ? $data['recibir_notificaciones'] : null;
    $idCatalogoPais = isset($data['id_catalogo_pais']) && $data['id_catalogo_pais'] !== '' ? intval($data['id_catalogo_pais']) : null;
    $idCatalogoEstado = isset($data['id_catalogo_estado']) && $data['id_catalogo_estado'] !== '' ? intval($data['id_catalogo_estado']) : null;
    $idCatalogoCiudad = isset($data['id_catalogo_ciudad']) && $data['id_catalogo_ciudad'] !== '' ? intval($data['id_catalogo_ciudad']) : null;
    // El flujo de orden/presupuesto envía reactivar=1 para revivir automáticamente
    // un cliente eliminado (soft delete) con el mismo teléfono, en vez de recibir
    // 'phone_deleted' y preguntar. La creación suelta de cliente NO lo envía.
    $reactivar = isset($data['reactivar']) && ($data['reactivar'] === '1' || $data['reactivar'] === 1 || $data['reactivar'] === true || $data['reactivar'] === 'true');

    $resJson = $woo->createCustomer(
      $data['first_name'],
      $data['last_name'],
      $data['cedula'],
      $data['phone'],
      $data['email'],
      $data['address'],
      $recibir,
      $idCatalogoPais,
      $idCatalogoEstado,
      $idCatalogoCiudad,
      $reactivar
    );
    $resObj = json_decode($resJson, true);
    $status = (isset($resObj['status']) && $resObj['status'] === 'error') ? 400 : 200;
    $response->getBody()->write($resJson);

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status);
  });

  // Reactivar un cliente marcado como eliminado (soft delete). El frontend lo llama
  // tras confirmar en la creación suelta de cliente (respuesta 'phone_deleted').
  $app->post('/customers/reactivar', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $woo = new WooMe();

    if (empty($data['id'])) {
      $response->getBody()->write(json_encode(['status' => 'error', 'msg' => 'Falta el id del cliente a reactivar.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $recibir = isset($data['recibir_notificaciones']) ? $data['recibir_notificaciones'] : null;
    $idCatalogoPais = isset($data['id_catalogo_pais']) && $data['id_catalogo_pais'] !== '' ? intval($data['id_catalogo_pais']) : null;
    $idCatalogoEstado = isset($data['id_catalogo_estado']) && $data['id_catalogo_estado'] !== '' ? intval($data['id_catalogo_estado']) : null;
    $idCatalogoCiudad = isset($data['id_catalogo_ciudad']) && $data['id_catalogo_ciudad'] !== '' ? intval($data['id_catalogo_ciudad']) : null;

    $resJson = $woo->reactivateCustomer(
      $data['id'],
      $data['first_name'] ?? '',
      $data['last_name'] ?? '',
      $data['cedula'] ?? '',
      $data['phone'] ?? '',
      $data['email'] ?? '',
      $data['address'] ?? '',
      $recibir,
      $idCatalogoPais,
      $idCatalogoEstado,
      $idCatalogoCiudad
    );
    $response->getBody()->write($resJson);
    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** *  CATEGORIAS */
  $app->get('/categories', function (Request $request, Response $response) {
    $woo = new WooMe();

    $response->getBody()->write($woo->getAllCategories());
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** *  DEPARTAMETOS */
  $app->get('/departamentos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT _id, departamento, id_modulo, enviar_mensaje, orden_proceso, asignar_numero_de_paso, tipo FROM departamentos WHERE eliminado = 0 ORDER BY orden_proceso ASC';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Departamentos por empleado
  $app->get('/departamentos-empleado/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT a.id_departamento, b.departamento, b.orden_proceso, b.tipo from api_empresas.empresas_usuarios_departamentos a JOIN departamentos b On b._id = a.id_departamento AND b.eliminado = 0 WHERE a.id_empleado = {$args['id_empleado']}";
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* NUEVO DEPARTAMENTO */
  $app->post('/departamentos/nuevo', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Buscar último número de orden_proceso
    $sql = 'SELECT COUNT(*) + 1 total_departamentos FROM departamentos;';
    $resTotal = $localConnection->goQuery($sql);
    $totalDepartamentos = intval($resTotal[0]['total_departamentos']);

    $tipo = isset($data['tipo']) ? $data['tipo'] : 'general';

    $sql = "INSERT INTO departamentos (orden_proceso, enviar_mensaje, id_modulo, asignar_numero_de_paso, departamento, tipo) VALUES ({$totalDepartamentos}, {$data['enviar_mensaje']}, {$data['modulo']}, {$data['asignar_paso']}, '{$data['departamento']}', '{$tipo}')";
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR NOMBRE DEL DEPARTAMENTO */
  $app->post('/departamentos/editar', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $tipo = isset($data['tipo']) ? $data['tipo'] : 'general';

    $sql = "UPDATE departamentos SET enviar_mensaje = {$data['enviar_mensaje']}, asignar_numero_de_paso = {$data['asignar_paso']}, id_modulo = {$data['modulo']}, departamento = '" . $data['departamento'] . "', tipo = '{$tipo}' WHERE _id = " . $data['id_departamento'];
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR NOMBRE ENVIO DE MENSAJE */
  $app->post('/departamentos/editar/mensaje', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE departamentos SET enviar_mensaje = {$data['enviar_mensaje']} WHERE _id = " . $data['id_departamento'];
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ELIMINAR DEPARTAMENTO */
  $app->delete('/departamentos/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "UPDATE departamentos SET eliminado = 1 WHERE _id = {$args['id_departamento']}";
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR ORDEN DE PROCESO DE DEPARTAMENTO */
  $app->post('/departamentos/orden-paso', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $nuevoOrden = intval($data['orden_proceso']);

    // ACTUALIZAR ORDEN DE DEPARTAMENTOS SIGUIENTES SUMANDO 1 AL ORDEN DEL DEPARTAMENTO
    // $sql = 'UPDATE departamentos SET orden_proceso = (orden_proceso + 1) WHERE orden_proceso > ' . $data['orden_proceso_cur'] . ';';
    $sql = 'UPDATE departamentos SET orden_proceso = ' . $data['orden_proceso'] . ' WHERE _id = ' . $data['id_departamento'] . ';';
    $sql .= 'SELECT _id, departamento, orden_proceso FROM departamentos WHERE _id = ' . $data['id_departamento'] . ' ORDER BY orden_proceso ASC';
    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN CATEGORIAS */

  /** * ATRIBUTOS */
  $app->get('/attributes', function (Request $request, Response $response) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getAllAttributes());

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN ATRINUTOS */

  /** * TALLAS */
  $app->get('/sizes', function (Request $request, Response $response) {
    $woo = new WooMe();
    $sizes = json_decode($woo->getSizes());
    $object['data'] = $sizes;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TALLAS */


  // Crear una nueva talla
  // Crear una nueva talla
  $app->post('/sizes', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'INSERT INTO sizes (nombre, variation_percentage) VALUES (?, ?)';
    $variation = isset($data['variation_percentage']) ? $data['variation_percentage'] : 0;
    $result = $localConnection->goQuery($sql, [$data['name'], $variation]);
    $newId = $result['insert_id'] ?? null;
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Talla creada exitosamente.',
      'id' => $newId,
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(201);
  });

  // Editar una talla existente
  $app->post('/sizes/editar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'UPDATE sizes SET nombre = ?, variation_percentage = ? WHERE _id = ?';
    $variation = isset($data['variation_percentage']) ? $data['variation_percentage'] : 0;
    $result = $localConnection->goQuery($sql, [$data['name'], $variation, $data['id']]);
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Talla actualizada exitosamente.',
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar una talla
  $app->post('/sizes/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'DELETE FROM sizes WHERE _id = ?';
    $result = $localConnection->goQuery($sql, [$data['id']]);
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Talla eliminada exitosamente.',
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar una categoría

  // Crear una nueva categoría
  $app->post('/categories', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'INSERT INTO categories (nombre) VALUES (?)';
    $result = $localConnection->goQuery($sql, [$data['name']]);
    $newId = $result['insert_id'] ?? null;
    $localConnection->disconnect();

    $responseData = [
      'message' => 'Categoría creada exitosamente.',
      'id' => $newId,
      'rowCount' => $result['row_count'] ?? 0
    ];

    $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(201);
  });




  /** LOTES */

  // Obtener departamento asignado al empleado
  $app->get('/empleado/asignado/{departamento}/{orden}/{item_id}', function (Request $request, Response $response, array $args) {
    // Verificar la asignacion
    $localConnection = new LocalDB();
    $sql = "SELECT id_empleado FROM lotes_detalles  WHERE id_orden = '" . $args['orden'] . "' AND id_ordenes_productos = '" . $args['item_id'] . "' AND departamento = '" . $args['departamento'] . "'";
    $object['id_empleado'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/lotes/en-proceso', function (Request $request, Response $response, array $args) {
    // BUSCAR ORDENES EN CURSO - Modificado para usar lotes_detalles_empleados_asignados
    $localConnection = new LocalDB();
    $sql = "SELECT 
            a._id AS orden, 
            a._id AS vinculada, 
            a.cliente_nombre AS cliente, 
            b.prioridad, 
            -- Paso calculado desde lotes_detalles_empleados_asignados
            CASE 
                WHEN prog_info.total_departamentos = 0 OR prog_info.total_departamentos IS NULL THEN 'Por asignar'
                WHEN prog_info.paso_actual IS NULL THEN 'Terminado'
                ELSE prog_info.paso_actual
            END AS paso,
            a.fecha_inicio AS inicio, 
            a.fecha_entrega AS entrega, 
            'TRAER DEL ENDPOINT DEDICADO' AS detalles, 
            a._id AS acciones, 
            a.status AS estatus,
            -- Datos de progreso
            COALESCE(prog_info.departamentos_terminados, 0) AS progreso_paso_valor,
            COALESCE(prog_info.total_departamentos, 0) AS progreso_total_pasos,
            COALESCE(
                ROUND(
                    (prog_info.departamentos_terminados * 100) / NULLIF(prog_info.total_departamentos, 0)
                ), 0
            ) AS progreso_porcentaje
        FROM ordenes a 
        JOIN lotes b ON a._id = b.id_orden
        LEFT JOIN (
            SELECT
                ldea.id_orden,
                COUNT(DISTINCT ldea.id_departamento) AS total_departamentos,
                COUNT(DISTINCT CASE WHEN ldea.fecha_terminado IS NOT NULL THEN ldea.id_departamento END) AS departamentos_terminados,
                (
                    SELECT dep.departamento
                    FROM lotes_detalles_empleados_asignados ldea2
                    JOIN departamentos dep ON dep._id = ldea2.id_departamento
                    WHERE ldea2.id_orden = ldea.id_orden
                      AND ldea2.fecha_terminado IS NULL
                    ORDER BY dep.orden_proceso ASC
                    LIMIT 1
                ) AS paso_actual
            FROM
                lotes_detalles_empleados_asignados ldea
            GROUP BY
                ldea.id_orden
        ) AS prog_info ON a._id = prog_info.id_orden
        WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' 
        ORDER BY a._id DESC";
    $object['items'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'prioridad';
    $object['fields'][2]['label'] = 'Prioridad';

    $object['fields'][2]['key'] = 'paso';
    $object['fields'][2]['label'] = 'Progreso';

    $object['fields'][3]['key'] = 'inicio';
    $object['fields'][3]['label'] = 'Inicio';

    $object['fields'][4]['key'] = 'entrega';
    $object['fields'][4]['label'] = 'Entrega';

    $object['fields'][5]['key'] = 'vinculada';
    $object['fields'][5]['label'] = 'Vinculada';

    $object['fields'][6]['key'] = 'estatus';
    $object['fields'][6]['label'] = 'Estatus';

    $object['fields'][7]['key'] = 'detalles';
    $object['fields'][7]['label'] = 'Detalles';

    $object['fields'][8]['key'] = 'acciones';
    $object['fields'][8]['label'] = 'Acciones';

    $go = $object;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($go));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // VERIFICAR CANTIDAD ASIGNADA EN LOTES
  $app->get('/lotes/verificar-cantidad-asignada/{id_ordenes_productos}/{departamento}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // -> VERIFICAR EXISTENCIA DEL REGISTRO
    $sql = "SELECT _id FROM lotes_detalles WHERE id_ordenes_productos  = '" . $args['id_ordenes_productos'] . "' AND departamento = '" . $args['departamento'] . "' AND id_orden = " . $args['id_orden'];
    $object['data'] = $localConnection->goQuery($sql);

    // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE ???

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER DATOS PARA REPOSICIONES EN EL MODULO DE EMPLEADOS
  $app->get('/empleados/reposicion/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // ITEM
    $sql = "SELECT DISTINCT
            a._id AS orden,
            a._id AS vinculada,    
            a.cliente_nombre AS cliente,
            b.prioridad,
            b.paso,
            d.detalle detalle_supervisor,
            d.detalle_emisor,
            a.fecha_inicio AS inicio,
            a.fecha_entrega AS entrega,
            obs.observaciones AS detalles,
            a._id AS acciones,
            a.status AS estatus,
            c._id AS id_diseno,
            d.unidades,
            d.id_ordenes_productos,
            COALESCE(e.nombre, 'Sin asignar') AS disenador
            FROM
            ordenes a
            JOIN lotes b ON a._id = b.id_orden
            LEFT JOIN ordenes_observaciones obs ON obs.id_orden = a._id
            LEFT JOIN disenos c ON a._id = c.id_orden
            LEFT JOIN reposiciones d ON d.id_orden = a._id
            LEFT JOIN api_empresas.empresas_usuarios e ON e.id_usuario = (CASE WHEN c.id_empleado = 0 THEN 0 ELSE c.id_empleado END)
            WHERE a._id = " . $args['id_orden'] . " AND 
            (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera')
            -- AND (SELECT COUNT(_id) FROM lotes_detalles WHERE id_orden = a._id) > 0
            ORDER BY a._id DESC";
    $object['item']['data'] = $localConnection->goQuery($sql)[0];
    // DETALLES DE REPOSICIÓN
    $sql = 'SELECT
            r._id id_reposicion,
            r.id_orden,
            r.id_empleado,
            r.id_ordenes_productos,
            o.name producto,
            r.unidades,
            r.detalle detalle_supervisor,
            r.detalle_emisor detalle_empleado
        FROM
            reposiciones r
        LEFT JOIN ordenes_productos o ON r.id_ordenes_productos = o._id
        WHERE r.terminada = 0 AND r.id_orden = ' . $args['id_orden'];

    $object['item']['detalles_reposicion'] = $localConnection->goQuery($sql);

    // REPOSICION ORDENES PRODUCTOS
    $sql = 'SELECT
                b._id,
                b.id_orden,
                b._id item,
                b.id_woo cod,
                b.name producto,
                b.cantidad,
                b.talla,
                b.tela,
                b.corte,
                b.precio_unitario precio,
                b.precio_woo precioWoo
            FROM
                ordenes_productos b
            JOIN ordenes a ON
                a._id = b.id_orden
            JOIN products p ON p._id = b.id_woo
            WHERE a._id = ' . $args['id_orden'] . " AND 
                (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND p.fisico = 1
        ";
    $object['reposicion_ordenes_productos'] = $localConnection->goQuery($sql);

    /* // BUSCAR PASO ACTUAL EN EL LOTE
    $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
    $tmpPaso = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    if (!empty($tmpPaso)) {
      $object["paso"] = $tmpPaso[0]["paso"];
    } else {
      $object["paso"] = null;
    } */

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/empleados/reasignar', function (Request $request, Response $response, $args) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Atomicidad FK: reasignación (lotes_detalles + LDEA + lotes) en una transacción
    $localConnection->beginTransaction();

    // BUSCAR NOMBRE DEL DEPARTAMENTO
    $sql = "SELECT departamento FROM departamentos WHERE _id = {$miEmpleado['id_departamento']}";
    $respDep = $localConnection->goQuery($sql);
    $nombreDepartamento = $respDep[0]['departamento'];

    // CALCULAR CANTIDAD DE UNIDADES SOLICITADAS
    $sql = "SELECT SUM(cantidad) total_cantidad FROM ordenes_productos WHERE id_orden = {$miEmpleado['id_orden']}";
    $total_cantidad = $localConnection->goQuery($sql)[0]['total_cantidad'] ?? 0;
    // Si la orden no tiene productos, SUM() devuelve NULL. MySQL coerciona '' a 0 en una
    // columna entera; PostgreSQL lo rechaza ("invalid input syntax for type integer").
    if ($total_cantidad === null) {
      $total_cantidad = 0;
    }

    $values = "id_departamento ='" . $miEmpleado['id_departamento'] . "',";
    $values .= "departamento ='" . $nombreDepartamento . "',";
    $values .= "unidades_solicitadas ='$total_cantidad'";

    // ACTUALIZAR REGISTRO EN lotes_detalles
    $sql = 'UPDATE lotes_detalles SET ' . $values . " WHERE id_departamento = '" . $miEmpleado['id_departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'];
    $resultados = $localConnection->goQuery($sql);

    // VERIFICAR REGISTRO EN lotes_detalles_empleados_asigandos
    $sql2 = "SELECT _id cantida_registros FROM lotes_detalles_empleados_asignados WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']}";
    $resultados = $localConnection->goQuery($sql2);
    $object['sql_verificar'] = $sql;
    $object['result_verificar'] = $resultados;

    if (empty($resultados)) {
      $sql = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_departamento, id_empleado, procentaje_comision) VALUES ({$miEmpleado['id_orden']}, {$miEmpleado['id_departamento']}, {$miEmpleado['id_empleado']}, {$miEmpleado['porcentaje']})";
    } else {
      $sql = "UPDATE lotes_detalles_empleados_asignados SET id_empleado = {$miEmpleado['id_empleado']}, procentaje_comision = {$miEmpleado['porcentaje']}, id_departamento = {$miEmpleado['id_departamento']} WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']}";
    }
    $afectarEmpleado = $localConnection->goQuery($sql);
    $object['sql'] = $sql;
    $object['result'] = $afectarEmpleado;

    // ACTUALIZAR id_departamento_actual EN LOTES si es necesario
    // Esto permite que el botón "Iniciar Todo" funcione correctamente
    $sql_check = "SELECT id_departamento_actual FROM lotes WHERE id_orden = {$miEmpleado['id_orden']}";
    $current_dept = $localConnection->goQuery($sql_check);

    if (!empty($current_dept)) {
      $current_dept_id = $current_dept[0]['id_departamento_actual'];

      // Si id_departamento_actual es NULL o 0, actualizarlo con el departamento asignado
      if ($current_dept_id === null || $current_dept_id === 0 || $current_dept_id === '0') {
        $sql_update_lote = "UPDATE lotes SET id_departamento_actual = {$miEmpleado['id_departamento']} WHERE id_orden = {$miEmpleado['id_orden']}";
        $localConnection->goQuery($sql_update_lote);
        $object['lote_actualizado'] = true;
      } else {
        // Verificar si el nuevo departamento tiene menor orden_proceso (primera etapa del flujo)
        $sql_orden = "SELECT 
            (SELECT orden_proceso FROM departamentos WHERE _id = {$miEmpleado['id_departamento']}) AS nuevo_orden,
            (SELECT orden_proceso FROM departamentos WHERE _id = {$current_dept_id}) AS actual_orden";
        $ordenes = $localConnection->goQuery($sql_orden);

        if (!empty($ordenes) && $ordenes[0]['nuevo_orden'] < $ordenes[0]['actual_orden']) {
          $sql_update_lote = "UPDATE lotes SET id_departamento_actual = {$miEmpleado['id_departamento']} WHERE id_orden = {$miEmpleado['id_orden']}";
          $localConnection->goQuery($sql_update_lote);
          $object['lote_actualizado'] = true;
        }
      }
    }

    $localConnection->commit();
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/empleados/reasignar-masiva', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    $id_ordenes = isset($data['id_ordenes']) ? json_decode($data['id_ordenes'], true) : [];
    $asignaciones = isset($data['asignaciones']) ? json_decode($data['asignaciones'], true) : [];

    if (empty($id_ordenes) || empty($asignaciones)) {
      $response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos: id_ordenes y asignaciones son obligatorios.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    $object = ['success' => true, 'results' => []];

    try {
      $localConnection->beginTransaction();

      // Mapeo para cachear información de departamentos y evitar múltiples queries redundantes
      $deptCache = [];

      foreach ($id_ordenes as $id_orden) {
        $id_orden = intval($id_orden);

        // 1. CALCULAR CANTIDAD DE UNIDADES SOLICITADAS (una vez por orden)
        $sqlCant = "SELECT SUM(cantidad) total_cantidad FROM ordenes_productos WHERE id_orden = {$id_orden}";
        $total_cantidad_res = $localConnection->goQuery($sqlCant);
        $total_cantidad = $total_cantidad_res[0]['total_cantidad'] ?? 0;

        foreach ($asignaciones as $asig) {
          $id_departamento = intval($asig['id_departamento']);
          $empleados = $asig['empleados'];

          if (!isset($deptCache[$id_departamento])) {
            $sqlDep = "SELECT departamento, orden_proceso FROM departamentos WHERE _id = {$id_departamento}";
            $respDep = $localConnection->goQuery($sqlDep);
            if (empty($respDep)) {
              throw new Exception("Departamento no encontrado: {$id_departamento}");
            }
            $deptCache[$id_departamento] = [
              'nombre' => $respDep[0]['departamento'],
              'orden' => $respDep[0]['orden_proceso']
            ];
          }

          $nombreDepartamento = $deptCache[$id_departamento]['nombre'];
          $nuevo_orden_proceso = $deptCache[$id_departamento]['orden'];

          // 2. ACTUALIZAR O INSERTAR REGISTRO EN lotes_detalles
          $sqlCheckDetail = "SELECT _id FROM lotes_detalles WHERE id_departamento = '{$id_departamento}' AND id_orden = {$id_orden}";
          $detailExists = $localConnection->goQuery($sqlCheckDetail);

          if (empty($detailExists)) {
            $sqlInsDetail = "INSERT INTO lotes_detalles (id_orden, id_departamento, departamento, unidades_solicitadas, estatus) 
                             VALUES ({$id_orden}, '{$id_departamento}', '{$nombreDepartamento}', '{$total_cantidad}', 'por iniciar')";
            $localConnection->goQuery($sqlInsDetail);
          } else {
            $sqlUpLote = "UPDATE lotes_detalles SET departamento = '{$nombreDepartamento}', unidades_solicitadas = '{$total_cantidad}' 
                          WHERE id_departamento = '{$id_departamento}' AND id_orden = {$id_orden}";
            $localConnection->goQuery($sqlUpLote);
          }

          // 3. ASIGNAR EMPLEADOS
          $sqlDel = "DELETE FROM lotes_detalles_empleados_asignados WHERE id_orden = {$id_orden} AND id_departamento = {$id_departamento}";
          $localConnection->goQuery($sqlDel);

          foreach ($empleados as $emp) {
            $id_emp = intval($emp['id_empleado']);
            $porcentaje = floatval($emp['porcentaje']);

            $sqlIns = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_departamento, id_empleado, procentaje_comision) 
                       VALUES ({$id_orden}, {$id_departamento}, {$id_emp}, {$porcentaje})";
            $localConnection->goQuery($sqlIns);
          }

          // 4. ACTUALIZAR id_departamento_actual EN LOTES (si corresponde)
          $sql_check = "SELECT id_departamento_actual FROM lotes WHERE id_orden = {$id_orden}";
          $current_dept_res = $localConnection->goQuery($sql_check);

          if (!empty($current_dept_res)) {
            $current_dept_id = $current_dept_res[0]['id_departamento_actual'];

            if ($current_dept_id === null || $current_dept_id === 0 || $current_dept_id === '0') {
              $sql_update_lote = "UPDATE lotes SET id_departamento_actual = {$id_departamento}, paso = '{$nombreDepartamento}' WHERE id_orden = {$id_orden}";
              $localConnection->goQuery($sql_update_lote);
            } else {
              if (!isset($deptCache[$current_dept_id])) {
                $sql_orden_comp = "SELECT departamento, orden_proceso FROM departamentos WHERE _id = {$current_dept_id}";
                $actual_orden_res = $localConnection->goQuery($sql_orden_comp);
                if (!empty($actual_orden_res)) {
                  $deptCache[$current_dept_id] = [
                    'nombre' => $actual_orden_res[0]['departamento'],
                    'orden' => $actual_orden_res[0]['orden_proceso']
                  ];
                }
              }

              if (isset($deptCache[$current_dept_id])) {
                $actual_orden = $deptCache[$current_dept_id]['orden'];
                if ($nuevo_orden_proceso <= $actual_orden) {
                  $sql_update_lote = "UPDATE lotes SET id_departamento_actual = {$id_departamento}, paso = '{$nombreDepartamento}' WHERE id_orden = {$id_orden}";
                  $localConnection->goQuery($sql_update_lote);
                }
              }
            }
          }
        }
        $object['results'][] = ['id_orden' => $id_orden, 'status' => 'ok'];
      }

      $localConnection->commit();
    } catch (Exception $e) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $object['success'] = false;
      $object['error'] = $e->getMessage();
    } finally {
      $localConnection->disconnect();
    }

    $response->getBody()->write(json_encode($object));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($object['success'] ? 200 : 500);
  });

}; // Fin de la función que envuelve las rutas

