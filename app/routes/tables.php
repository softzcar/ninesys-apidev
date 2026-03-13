<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** * TABLAS */
  // REPORTE DE PRODUCCIÓN SEMANAL
  $app->get('/ordenes-reporte-semanal-produccion/{fecha}', function (Request $request, Response $response, array $args) {
    $fechaSegundos = strtotime($args['fecha']);
    $week = date('W', $fechaSegundos);
    $object['week'] = $week;
    $localConnection = new LocalDB();

    // ORDENES DE PRODUCIDAS EN LA SEMANA
    $sql = "SELECT
        a._id id_orden,
        a.cliente_nombre cliente,    
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') AS fecha_inicio,
        DATE_FORMAT(a.fecha_entrega, '%d/%m/%Y') AS fecha_entrega,
        a.status estatus
        FROM
        ordenes a
        WHERE
        WEEK(a.moment) = " . $week;
    $object['items'] = $localConnection->goQuery($sql);

    // PROPDUCTOS ASOICIADOS A LAS ORDENES DE LA SEMANA
    $sql = 'SELECT
        a._id id_ordenes_productos,
        a.id_orden,
        a.id_woo,
        a.name,
        a.cantidad,
        a.talla,
        a.corte,
        a.tela
        FROM
        ordenes_productos a
        WHERE
        a.id_woo != 11 AND 
        a.id_woo != 12 AND 
        a.id_woo != 13 AND 
        a.id_woo != 14 AND 
        a.id_woo != 15 AND 
        a.id_woo != 16 AND 
        a.id_woo != 112 AND 
        a.id_woo != 113 AND 
        a.id_woo != 168 AND 
        a.id_woo != 169 AND 
        WEEK(a.moment) = ' . $week . ' 
        ORDER BY a.name ASC, a.corte ASC, a.talla ASC, a.tela ASC, a.id_orden ASC;';
    $object['items_productos'] = $localConnection->goQuery($sql);

    // INSERTAR PRODUCTOS EN items

    foreach ($object['items'] as $key => $orden) {
      foreach ($object['items_productos'] as $producto) {
        if (!isset($object['items'][$key]['productos'])) {
          $object['items'][$key]['productos'] = [];
        }

        if ($producto['id_orden'] === $orden['id_orden']) {
          $object['items'][$key]['productos'][] = $producto;
        }
      }
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE SEMANAL DE ORDENES
  $app->get('/ordenes-reporte-semanal/{fecha}', function (Request $request, Response $response, array $args) {
    $fechaSegundos = strtotime($args['fecha']);
    $week = date('W', $fechaSegundos);
    $object['week'] = $week;
    $localConnection = new LocalDB();

    $sql = 'SELECT
        a._id orden,
        a.cliente_nombre cliente,
        a.pago_total total,
        a.pago_abono abono,
        a.pago_descuento descuento,
        b.nombre empleado,
        (a.pago_total - a.pago_descuento) - a.pago_abono + a.pago_nota_credito AS total_pendiente
        FROM
        ordenes a
        JOIN empleados b ON a.responsable = b._id
        WHERE
        WEEK(a.moment) = ' . $week;
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT
        SUM(pago_abono) total_semana
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_week'] = $localConnection->goQuery($sql);

    if (is_null($object['total_week'][0]['total_semana'])) {
      $object['total_week'][0]['total_semana'] = '0';
    }

    $sql = 'SELECT
        (SUM(pago_total) - SUM(pago_descuento)) - SUM(pago_abono) + SUM(pago_nota_credito) total_credito
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_credito'] = $localConnection->goQuery($sql);

    if (is_null($object['total_credito'][0]['total_credito'])) {
      $object['total_credito'][0]['total_credito'] = '0';
    }

    $sql = 'SELECT
        SUM(pago_descuento) total_descuentos
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_descuentos'] = $localConnection->goQuery($sql);

    if (is_null($object['total_descuentos'][0]['total_descuentos'])) {
      $object['total_descuentos'][0]['total_descuentos'] = '0';
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER PRESUPUESTOS GUARDADOS
  $app->get('/presupuestos/guardados', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a._id, a.form, a.tipo, b._id AS id_empleadodo, b.nombre AS empleado 
          FROM ordenes_tmp a 
          JOIN empleados b ON a.id_empleado = b._id';

    $object['items'] = $localConnection->goQuery($sql);

    foreach ($object['items'] as $key => $item) {
      $item[$key]['form'] = json_decode($item['form']);
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER ORDENES GUARDADAS (Borradores + Presupuestos Finalizados con observaciones y productos)
  $app->get('/ordenes/guardadas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    // Consultamos borradores y presupuestos finalizados en una sola query
    // Para los borradores (ordenes_tmp), las observaciones están dentro del JSON 'form'
    // Para los presupuestos finalizados, están en la columna 'observaciones'
    $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
              SELECT a._id, 
                     a.form, 
                     a.tipo, 
                     b.id_usuario AS id_empleado, 
                     b.nombre AS empleado,
                     JSON_UNQUOTE(JSON_EXTRACT(a.form, '$.obs')) as observaciones,
                     JSON_EXTRACT(a.form, '$.productos') as productos_json
              FROM ordenes_tmp a 
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              UNION ALL
              SELECT p._id, 
                     CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form, 
                     'Presupuesto Finalizado' as tipo, 
                     p.responsable as id_empleado, 
                     u.nombre as empleado,
                     p.observaciones,
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'atributo', pa.attribute_name)) 
                      FROM presupuestos_productos pp 
                      LEFT JOIN sizes s ON pp.id_size = s._id 
                      LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";

    $results = $localConnection->goQuery($sql);

      foreach ($results as &$item) {
        $prodsJson = $item['productos_json'] ?: '[]';
        $prods = (is_string($prodsJson)) ? json_decode($prodsJson, true) : $prodsJson;
        $prods = $prods ?: [];

        if ($item['tipo'] !== 'Presupuesto Finalizado') {
          // Es un borrador (ordenes_tmp). Mapear llaves para consistencia
          $item['productos_json'] = array_map(function($p) {
            $attrText = '';
            if (!empty($p['atributos_seleccionados']) && is_array($p['atributos_seleccionados'])) {
              $attrText = $p['atributos_seleccionados'][0]['text'] ?? '';
            }
            return [
              'name' => $p['name'] ?? ($p['producto'] ?? 'S/N'),
              'cantidad' => $p['cantidad'] ?? 0,
              'talla' => $p['talla'] ?? 'N/A',
              'tela' => $p['tela'] ?? 'N/A',
              'atributo' => $p['atributo'] ?? $attrText
            ];
          }, $prods);
        } else {
          // Ya es un presupuesto finalizado, asegurar que productos_json sea array
          $item['productos_json'] = $prods;
        }
      }
      $object['items'] = $results;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ordenes/observaciones/{id_orden}/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // $sql = "SELECT a.observaciones observaciones_orden, b.borrador observaciones_empleado FROM ordenes a JOIN ordenes_borrador_empleado b ON b.id_orden = a._id WHERE a._id = {$args['id_orden']} AND b.id_empleado = {$args['id_empleado']} AND b.id_departamento = {$args['id_departamento']}";

    $sql = "SELECT
        obs.observaciones AS observaciones_ordenes,
            (SELECT borrador FROM ordenes_borrador_empleado WHERE id_orden = {$args['id_orden']} AND id_empleado = {$args['id_empleado']} AND id_departamento = {$args['id_departamento']}) observaciones_empleado
        FROM
            ordenes a
        LEFT JOIN ordenes_observaciones obs ON a._id = obs.id_orden
        WHERE
            a._id = {$args['id_orden']}";

    $object = $localConnection->goQuery($sql);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ordenes/borrador/reporte-semanal/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                b._id id_orden,
                b.cliente_nombre,    
                a._id id_ordenes_borador_empleado,
                a.borrador,
                a.moment
            FROM
                ordenes_borrador_empleado a
            LEFT JOIN ordenes b ON b._id = a.id_orden
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']}
              AND YEARWEEK(a.moment, 1) = YEARWEEK(CURDATE(), 1)
        ";

    $object = $localConnection->goQuery($sql);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR BORRADOR DEL EMPLEADO
  $app->post('/ordenes/borrador', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Verificar si ya existe un registro para la orden
    $sql = 'SELECT _id FROM ordenes_borrador_empleado WHERE id_orden = ' . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'] . ' AND id_departamento = ' . $data['id_departamento'];
    $resp = $localConnection->goQuery($sql);

    if (empty($resp)) {
      $sql = 'INSERT INTO ordenes_borrador_empleado (`id_orden`, `id_empleado`, `id_departamento`, `borrador`) VALUES (' . $data['id_orden'] . ', ' . $data['id_empleado'] . ", '{$data['id_departamento']}', '" . addslashes($data['borrador'] ?? '') . "');";
    } else {
      $sql = 'UPDATE ordenes_borrador_empleado SET id_departamento = ' . $data['id_departamento'] . ', id_orden = ' . $data['id_orden'] . ', id_empleado = ' . $data['id_empleado'] . ", borrador = '" . addslashes($data['borrador'] ?? '') . "' WHERE id_orden = " . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'];
    }
    $object['sql'] = $sql;
    $resp = $localConnection->goQuery($sql);
    $object['resp'] = $localConnection->disconnect($sql);

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ELIMINAR ORDENES GUARDADAS
  $app->post('/ordenes/guardadas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $data = $request->getParsedBody();
    $sql = 'DELETE FROM ordenes_tmp WHERE _id =  ' . $data['id'];
    $object['response_delete'] = json_encode($localConnection->goQuery($sql));
    $object['sql_delete'] = $sql;

    $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
              SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleado, b.nombre AS empleado,
                     JSON_UNQUOTE(JSON_EXTRACT(a.form, '$.obs')) as observaciones,
                     JSON_EXTRACT(a.form, '$.productos') as productos_json
              FROM ordenes_tmp a 
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              UNION ALL
              SELECT p._id, 
                     CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form, 
                     'Presupuesto Finalizado' as tipo, 
                     p.responsable as id_empleado, 
                     u.nombre as empleado,
                     p.observaciones,
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'atributo', pa.attribute_name)) 
                      FROM presupuestos_productos pp 
                      LEFT JOIN sizes s ON pp.id_size = s._id 
                      LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";

    $results = $localConnection->goQuery($sql);
    foreach ($results as &$item) {
        $prodsJson = $item['productos_json'] ?: '[]';
        $prods = (is_string($prodsJson)) ? json_decode($prodsJson, true) : $prodsJson;
        $prods = $prods ?: [];

        if ($item['tipo'] !== 'Presupuesto Finalizado') {
            $item['productos_json'] = array_map(function($p) {
                $attrText = '';
                if (!empty($p['atributos_seleccionados']) && is_array($p['atributos_seleccionados'])) {
                    $attrText = $p['atributos_seleccionados'][0]['text'] ?? '';
                }
                return [
                    'name' => $p['name'] ?? ($p['producto'] ?? 'S/N'),
                    'cantidad' => $p['cantidad'] ?? 0,
                    'talla' => $p['talla'] ?? 'N/A',
                    'tela' => $p['tela'] ?? 'N/A',
                    'atributo' => $p['atributo'] ?? $attrText
                ];
            }, $prods);
        } else {
            $item['productos_json'] = $prods;
        }
    }
    $object['items'] = $results;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR ORDEN PARA REPTMARLA LUEGO
  $app->post('/orden/guardar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $localConnection = new LocalDB();

    $sql = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES ('" . $data['form'] . "', " . $data['id_empleado'] . ", '" . $data['tipo'] . "')";
    $object['sql_insert'] = $sql;
    $localConnection->goQuery($sql);

    $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
              SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleado, b.nombre AS empleado,
                     JSON_UNQUOTE(JSON_EXTRACT(a.form, '$.obs')) as observaciones,
                     JSON_EXTRACT(a.form, '$.productos') as productos_json
              FROM ordenes_tmp a 
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              UNION ALL
              SELECT p._id, 
                     CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form, 
                     'Presupuesto Finalizado' as tipo, 
                     p.responsable as id_empleado, 
                     u.nombre as empleado,
                     p.observaciones,
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'atributo', pa.attribute_name)) 
                      FROM presupuestos_productos pp 
                      LEFT JOIN sizes s ON pp.id_size = s._id 
                      LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";

    $results = $localConnection->goQuery($sql);
    foreach ($results as &$item) {
        $prodsJson = $item['productos_json'] ?: '[]';
        $prods = (is_string($prodsJson)) ? json_decode($prodsJson, true) : $prodsJson;
        $prods = $prods ?: [];

        if ($item['tipo'] !== 'Presupuesto Finalizado') {
            $item['productos_json'] = array_map(function($p) {
                $attrText = '';
                if (!empty($p['atributos_seleccionados']) && is_array($p['atributos_seleccionados'])) {
                    $attrText = $p['atributos_seleccionados'][0]['text'] ?? '';
                }
                return [
                    'name' => $p['name'] ?? ($p['producto'] ?? 'S/N'),
                    'cantidad' => $p['cantidad'] ?? 0,
                    'talla' => $p['talla'] ?? 'N/A',
                    'tela' => $p['tela'] ?? 'N/A',
                    'atributo' => $p['atributo'] ?? $attrText
                ];
            }, $prods);
        } else {
            $item['productos_json'] = $prods;
        }
    }
    $object['items'] = $results;
    $object['sql'] = $sql;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES ACTIVAS
  $app->get('/table/ordenes-activas/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    $sql = 'SELECT departamento FROM  empresas_usuarios  WHERE id_usuario = ' . $args['id_empleado'];
    $departamento = $localConnection->goQuery($sql)[0]['departamento'];

    $localConnection = new LocalDB();

    if (strpos($departamento, 'Admin') !== false) {
      $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.responsable id_vendedor,
                emp.nombre vendedor,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.pago_total total,
                ord.fecha_inicio,
                ord.fecha_entrega,
                (
                    SELECT
                        CONCAT(
                            '[',
                            GROUP_CONCAT(
                                DISTINCT JSON_OBJECT(
                                    'category_name',
                                    c.nombre,
                                    'category_total',
                                    (op.cantidad * op.precio_unitario)
                                )
                            ),
                            ']'
                        )
                    FROM
                        ordenes_productos op
                    JOIN products p ON op.id_woo = p._id
                    JOIN categories c ON FIND_IN_SET(c._id, p.category_ids)
                    WHERE
                        op.id_orden = ord._id
                ) AS product_categories,
                (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
                (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) AS saldo_pendiente,
                ord.status estatus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                ord.status != 'cancelada'
                AND (
                    (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) > 0
                    OR 
                    (
                        (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) = 0
                        AND ord.status != 'entregada'
                    )
                    OR
                    (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) < 0
                )

            ORDER BY
                ord._id
            DESC;";
    } else {
      $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.responsable id_vendedor,
                emp.nombre vendedor,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.pago_total total,
                ord.fecha_inicio,
                ord.fecha_entrega,
                (
                    SELECT
                        CONCAT(
                            '[',
                            GROUP_CONCAT(
                                DISTINCT JSON_OBJECT(
                                    'category_name',
                                    c.nombre,
                                    'category_total',
                                    (op.cantidad * op.precio_unitario)
                                )
                            ),
                            ']'
                        )
                    FROM
                        ordenes_productos op
                    JOIN products p ON op.id_woo = p._id
                    JOIN categories c ON FIND_IN_SET(c._id, p.category_ids)
                    WHERE
                        op.id_orden = ord._id
                ) AS product_categories,
                (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
                (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) AS saldo_pendiente,
                ord.status estatus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                ord.responsable = '{$args['id_empleado']}'
                AND ord.status != 'cancelada'
                AND (
                    (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) > 0
                    OR 
                    (
                        (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) = 0
                        AND ord.status != 'entregada'
                    )
                    OR
                    (ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0)) < 0
                )

            ORDER BY
                ord._id
            DESC;";
    }

    $object['sql'] = $sql;

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'estatus';
    $object['fields'][1]['label'] = 'Estatus';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'fecha_inicio';
    $object['fields'][2]['label'] = 'Inicio';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'fecha_entrega';
    $object['fields'][3]['label'] = 'Entrega';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][4]['key'] = 'cliente_nombre';
    $object['fields'][4]['label'] = 'Cliente';
    $object['fields'][4]['sortable'] = true;

    $object['fields'][5]['key'] = 'id_father';
    $object['fields'][5]['label'] = 'Vinculadas';
    $object['fields'][5]['sortable'] = false;

    $object['fields'][6]['key'] = 'acc';
    $object['fields'][6]['label'] = 'Acciones';
    $object['fields'][6]['sortable'] = false;

    $items = $localConnection->goQuery($sql);
    foreach ($items as &$item) {
      if (isset($item['product_categories'])) {
        $item['product_categories'] = json_decode($item['product_categories']);
      }
    }
    $object['items'] = $items;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODAS LAS ORDENES
  $app->get('/table/ordenes-todas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT
    ord.responsable,
    ord._id orden,
    ord._id id_father,
    ord._id acc,
    ord.responsable id_vendedor,
    emp.nombre vendedor,
    ord.cliente_nombre,
    cus.phone,
    cus.email,
    ord.fecha_inicio,
    ord.fecha_entrega,
    ord.pago_total AS total,
    (
        SELECT
            CONCAT(
                '[',
                GROUP_CONCAT(
                    DISTINCT JSON_OBJECT(
                        'category_name',
                        c.nombre,
                        'category_total',
                        (op.cantidad * op.precio_unitario)
                    )
                ),
                ']'
            )
        FROM
            ordenes_productos op
        JOIN products p ON op.id_woo = p._id
        JOIN categories c ON FIND_IN_SET(c._id, p.category_ids)
        WHERE
            op.id_orden = ord._id
    ) AS product_categories,
    (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
    ord.status estatus
FROM
    ordenes ord
JOIN customers cus ON ord.id_wp = cus._id
LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
ORDER BY
    ord._id
DESC;";

    $items = $localConnection->goQuery($sql);
    foreach ($items as &$item) {
      if (isset($item['product_categories'])) {
        $item['product_categories'] = json_decode($item['product_categories']);
      }
    }
    $object['items'] = $items;
    $localConnection->disconnect();

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'estatus';
    $object['fields'][1]['label'] = 'Estatus';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'fecha_inicio';
    $object['fields'][2]['label'] = 'Inicio';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'fecha_entrega';
    $object['fields'][3]['label'] = 'Entrega';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][4]['key'] = 'cliente_nombre';
    $object['fields'][4]['label'] = 'Cliente';
    $object['fields'][4]['sortable'] = true;

    $object['fields'][5]['key'] = 'id_father';
    $object['fields'][5]['label'] = 'Vinculadas';
    $object['fields'][5]['sortable'] = false;

    $object['fields'][6]['key'] = 'acc';
    $object['fields'][6]['label'] = 'Acciones';
    $object['fields'][6]['sortable'] = false;

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODAS LAS ORDENES OLD
  /* $app->get('/table/ordenes-todas_OLD', function (Request $request, Response $response, array $args) {
      $sql = "SELECT
      a._id AS orden,
      DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
      a.cliente_nombre AS cliente,
      a.pago_total AS monto,
      a.pago_abono abono,
      (SELECT cus.phone FROM customers cus WHERE cus._id = a.id_wp) phone,
      (
       SELECT
       d.pago_total - SUM(c.abono) - SUM(c.descuento) + SUM(c.nota_credito) AS total_deuda
       FROM
       abonos c
       JOIN ordenes d ON
       c.id_orden = d._id
       WHERE
       c.id_orden = a._id
       ) AS monto_pendiente,
      a.status estatus
      FROM
      ordenes AS a
      WHERE a.status != 'cancelada'
      ORDER BY a._id DESC;";

      $localConnection = new LocalDB();
      $items = $localConnection->goQuery($sql);

      $object['items'] = $items;

      // Cabeceras de la tabla
      $object['fields'][0]['key'] = 'orden';
      $object['fields'][0]['label'] = 'Orden';
      $object['fields'][0]['sortable'] = true;

      $object['fields'][1]['key'] = 'fecha';
      $object['fields'][1]['label'] = 'Fecha';
      $object['fields'][1]['sortable'] = true;

      $object['fields'][2]['key'] = 'cliente';
      $object['fields'][2]['label'] = 'Cliente';
      $object['fields'][2]['sortable'] = true;

      $object['fields'][3]['key'] = 'monto';
      $object['fields'][3]['label'] = 'Monto';
      $object['fields'][3]['sortable'] = true;

      $object['fields'][3]['key'] = 'acc';
      $object['fields'][3]['label'] = 'Acciones';
      $object['fields'][3]['sortable'] = false;

      // $object['items'] = $localConnection->goQuery($sql);
      $localConnection->disconnect();

      $response->getBody()->write(json_encode($object));
      return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
  }); */

  // ORDENES CON DEUDAA
  $app->get('/table/ordenes-con-deuda', function (Request $request, Response $response, array $args) {
    $sql = "SELECT
        _id AS orden,
        DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
        cliente_nombre AS cliente,
        pago_total AS monto
        FROM
        ordenes       
        ORDER BY _id DESC;";

    $sql = "SELECT
        a._id orden,
        a.responsable,
        a._id orden,
        a._id id_father,
        a._id acc,
        a.cliente_nombre cliente,
        a.fecha_inicio,
        a.fecha_entrega,
        'TRAER DESDE EL `ENDPOINT` DEDICADO' obs,
        a.status estatus,
        a.pago_total AS monto,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) + SUM(c.nota_credito) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id
         ) AS total_deuda 
        FROM
        ordenes AS a
        WHERE
        a.status!= 'cancelada' AND 
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) + SUM(c.nota_credito) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id) > 0
        ORDER BY
        _id
        DESC
        ";
    $localConnection = new LocalDB();
    $items = $localConnection->goQuery($sql);

    $object['items'] = $items;

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'fecha';
    $object['fields'][1]['label'] = 'Fecha';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'cliente';
    $object['fields'][2]['label'] = 'Cliente';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'monto';
    $object['fields'][3]['label'] = 'Monto';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][3]['key'] = 'acc';
    $object['fields'][3]['label'] = 'Acciones';
    $object['fields'][3]['sortable'] = false;

    // $object['items'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TABLAS */

}; // Fin de la función que envuelve las rutas
