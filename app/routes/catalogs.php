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

  /** * CATALOGO TINTAS (Con migración auto-ejecutable y adaptativa al nuevo modelo) */
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

      // 3. Crear catalogo_colores_tintas si no existe
      $localConnection->goQuery("CREATE TABLE IF NOT EXISTS `catalogo_colores_tintas` (
        `_id` int(11) NOT NULL AUTO_INCREMENT,
        `codigo` varchar(16) NOT NULL,
        `nombre` varchar(64) NOT NULL,
        `color_hex` varchar(7) DEFAULT '#808080',
        `moment` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`_id`),
        UNIQUE KEY `uk_codigo_color` (`codigo`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");

      // Poblar colores por defecto si está vacío
      $countColores = $localConnection->goQuery("SELECT COUNT(*) as total FROM `catalogo_colores_tintas`");
      if (empty($countColores) || intval($countColores[0]['total'] ?? 0) === 0) {
        $localConnection->goQuery("INSERT INTO `catalogo_colores_tintas` (`_id`, `codigo`, `nombre`, `color_hex`) VALUES
          (1, 'C', 'Cyan', '#00FFFF'),
          (2, 'M', 'Magenta', '#FF00FF'),
          (3, 'Y', 'Yellow', '#FFFF00'),
          (4, 'K', 'Black', '#343A40'),
          (5, 'W', 'White', '#FFFFFF');");
      }

      // 4. Crear impresoras_colores si no existe
      $localConnection->goQuery("CREATE TABLE IF NOT EXISTS `impresoras_colores` (
        `id_catalogo_impresora` int(11) NOT NULL,
        `id_color_tinta` int(11) NOT NULL,
        PRIMARY KEY (`id_catalogo_impresora`, `id_color_tinta`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");

      // Poblar impresoras_colores de forma predictiva según la configuración actual
      $countImpCol = $localConnection->goQuery("SELECT COUNT(*) as total FROM `impresoras_colores`");
      if (empty($countImpCol) || intval($countImpCol[0]['total'] ?? 0) === 0) {
        $impresorasExistentes = $localConnection->goQuery("SELECT _id, tipo_tecnologia FROM `catalogo_impresoras`");
        if (is_array($impresorasExistentes)) {
          foreach ($impresorasExistentes as $imp) {
            $impId = intval($imp['_id']);
            $tech = strtoupper(trim($imp['tipo_tecnologia'] ?? ''));
            if ($tech === 'CMYK') {
              $localConnection->goQuery("INSERT IGNORE INTO `impresoras_colores` (id_catalogo_impresora, id_color_tinta) VALUES ($impId, 1), ($impId, 2), ($impId, 3), ($impId, 4)");
            } else if ($tech === 'CMYKW' || empty($tech)) {
              $localConnection->goQuery("INSERT IGNORE INTO `impresoras_colores` (id_catalogo_impresora, id_color_tinta) VALUES ($impId, 1), ($impId, 2), ($impId, 3), ($impId, 4), ($impId, 5)");
            }
          }
        }
      }

      // 5. Agregar id_catalogo_tintas a catalogo_impresoras si no existe
      $columnsImp = $localConnection->goQuery("SHOW COLUMNS FROM `catalogo_impresoras` LIKE 'id_catalogo_tintas'");
      if (empty($columnsImp)) {
        $localConnection->goQuery("ALTER TABLE `catalogo_impresoras` ADD COLUMN `id_catalogo_tintas` int(11) DEFAULT NULL;");
        $localConnection->goQuery("ALTER TABLE `catalogo_impresoras` ADD CONSTRAINT `fk_impresoras_cat_tintas` FOREIGN KEY (`id_catalogo_tintas`) REFERENCES `catalogo_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;");
        // Mapear tecnología por defecto a las existentes (Sublimación = 1 para impresora 1, DTF = 2 para impresora 2, fallback a 1)
        $localConnection->goQuery("UPDATE `catalogo_impresoras` SET `id_catalogo_tintas` = 1 WHERE `_id` = 1;");
        $localConnection->goQuery("UPDATE `catalogo_impresoras` SET `id_catalogo_tintas` = 2 WHERE `_id` = 2;");
        $localConnection->goQuery("UPDATE `catalogo_impresoras` SET `id_catalogo_tintas` = 1 WHERE `id_catalogo_tintas` IS NULL;");
      }

      // 6. Agregar columnas id_color_tinta e id_catalogo_tintas a inventario si no existen
      $columnsInvColor = $localConnection->goQuery("SHOW COLUMNS FROM `inventario` LIKE 'id_color_tinta'");
      if (empty($columnsInvColor)) {
        $localConnection->goQuery("ALTER TABLE `inventario` ADD COLUMN `id_color_tinta` int(11) DEFAULT NULL;");
        $localConnection->goQuery("ALTER TABLE `inventario` ADD COLUMN `id_catalogo_tintas` int(11) DEFAULT NULL;");
        $localConnection->goQuery("ALTER TABLE `inventario` ADD CONSTRAINT `fk_inventario_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;");
        $localConnection->goQuery("ALTER TABLE `inventario` ADD CONSTRAINT `fk_inventario_catalogo_tintas` FOREIGN KEY (`id_catalogo_tintas`) REFERENCES `catalogo_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;");

        // Si la tabla tinta_filtro existe, migrar datos
        $tablesFilter = $localConnection->goQuery("SHOW TABLES LIKE 'tinta_filtro'");
        if (!empty($tablesFilter)) {
          $localConnection->goQuery("UPDATE `inventario` inv 
                                     JOIN `tinta_filtro` tf ON inv._id = tf.id_inventario 
                                     JOIN `catalogo_colores_tintas` cct ON tf.color = cct.codigo
                                     SET inv.id_color_tinta = cct._id, inv.id_catalogo_tintas = tf.id_catalogo_tintas;");
          $localConnection->goQuery("DROP TABLE `tinta_filtro`;");
        }
      }

      // 7. Modificar tintas_recargas si usa campo color y no id_color_tinta
      $columnsRecargasNew = $localConnection->goQuery("SHOW COLUMNS FROM `tintas_recargas` LIKE 'id_color_tinta'");
      if (empty($columnsRecargasNew)) {
        $localConnection->goQuery("ALTER TABLE `tintas_recargas` ADD COLUMN `id_color_tinta` int(11) DEFAULT NULL;");
        $localConnection->goQuery("ALTER TABLE `tintas_recargas` ADD CONSTRAINT `fk_recargas_color` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE;");
        
        $columnsRecargasOld = $localConnection->goQuery("SHOW COLUMNS FROM `tintas_recargas` LIKE 'color'");
        if (!empty($columnsRecargasOld)) {
          $localConnection->goQuery("UPDATE `tintas_recargas` tr 
                                     JOIN `catalogo_colores_tintas` cct ON tr.color = cct.codigo 
                                     SET tr.id_color_tinta = cct._id;");
          $localConnection->goQuery("ALTER TABLE `tintas_recargas` DROP COLUMN `color`;");
        }
      }

      // 8. Modificar tintas si tiene columnas estáticas c, m, y, k, w
      $columnsTintasOld = $localConnection->goQuery("SHOW COLUMNS FROM `tintas` LIKE 'c'");
      if (!empty($columnsTintasOld)) {
        // Crear tabla temporal
        $localConnection->goQuery("CREATE TABLE IF NOT EXISTS `tintas_nueva` (
          `_id` int(11) NOT NULL AUTO_INCREMENT,
          `id_catalogo_impresoras` int(11) DEFAULT NULL,
          `id_orden` int(11) DEFAULT NULL,
          `id_empleado` int(11) DEFAULT NULL,
          `id_color_tinta` int(11) NOT NULL,
          `cantidad` decimal(7, 2) NOT NULL DEFAULT 0.00,
          `moment` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`_id`),
          CONSTRAINT `fk_tintas_impresoras_n` FOREIGN KEY (`id_catalogo_impresoras`) REFERENCES `catalogo_impresoras` (`_id`) ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT `fk_tintas_ordenes_n` FOREIGN KEY (`id_orden`) REFERENCES `ordenes` (`_id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_tintas_colores_n` FOREIGN KEY (`id_color_tinta`) REFERENCES `catalogo_colores_tintas` (`_id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");

        // Migrar histórico
        $localConnection->goQuery("INSERT INTO `tintas_nueva` (id_catalogo_impresoras, id_orden, id_empleado, id_color_tinta, cantidad, moment)
                                   SELECT id_catalogo_impresoras, id_orden, id_empleado, 1, c, moment FROM `tintas` WHERE c IS NOT NULL AND c > 0
                                   UNION ALL
                                   SELECT id_catalogo_impresoras, id_orden, id_empleado, 2, m, moment FROM `tintas` WHERE m IS NOT NULL AND m > 0
                                   UNION ALL
                                   SELECT id_catalogo_impresoras, id_orden, id_empleado, 3, y, moment FROM `tintas` WHERE y IS NOT NULL AND y > 0
                                   UNION ALL
                                   SELECT id_catalogo_impresoras, id_orden, id_empleado, 4, k, moment FROM `tintas` WHERE k IS NOT NULL AND k > 0
                                   UNION ALL
                                   SELECT id_catalogo_impresoras, id_orden, id_empleado, 5, w, moment FROM `tintas` WHERE w IS NOT NULL AND w > 0;");

        // Renombrar vieja y poner la nueva
        $localConnection->goQuery("RENAME TABLE `tintas` TO `tintas_old`, `tintas_nueva` TO `tintas`;");
      }
    } catch (\Exception $e) {
      error_log("Error en migración auto-ejecutable y adaptativa del catálogo de tintas y colores: " . $e->getMessage());
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

  /** * CATALOGO COLORES TINTAS */
  $app->get('/catalogo-colores-tintas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_colores_tintas ORDER BY nombre';
    $object['data'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-colores-tintas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $codigo = $data['codigo'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $color_hex = $data['color_hex'] ?? '#808080';

    $sql = "INSERT INTO catalogo_colores_tintas (codigo, nombre, color_hex) VALUES (?, ?, ?)";
    $result = $localConnection->goQuery($sql, [$codigo, $nombre, $color_hex]);
    $lastId = $localConnection->getLastID();

    $sqlNew = "SELECT * FROM catalogo_colores_tintas WHERE _id = ?";
    $newItem = $localConnection->goQuery($sqlNew, [$lastId]);

    $object['response'] = $result;
    $object['data'] = $newItem[0] ?? null;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-colores-tintas/{_id}', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    
    $codigo = $data['codigo'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $color_hex = $data['color_hex'] ?? '#808080';

    $sql = 'UPDATE catalogo_colores_tintas SET codigo = ?, nombre = ?, color_hex = ? WHERE _id = ?';
    $localConnection->goQuery($sql, [$codigo, $nombre, $color_hex, $args['_id']]);

    $sqlAll = 'SELECT * FROM catalogo_colores_tintas ORDER BY nombre';
    $object['data'] = $localConnection->goQuery($sqlAll);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/catalogo-colores-tintas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $body = $request->getParsedBody();
    $id = isset($body['id']) ? intval($body['id']) : 0;

    $sql = "DELETE FROM catalogo_colores_tintas WHERE _id = ?";
    $object['response'] = $localConnection->goQuery($sql, [$id]);
    
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
