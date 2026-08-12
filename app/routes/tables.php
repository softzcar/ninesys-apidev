<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** * TABLAS */
  // REPORTE SEMANAL DE ORDENES
  $app->get('/ordenes-reporte-semanal/{fecha}', function (Request $request, Response $response, array $args) {
    $fechaSegundos = strtotime($args['fecha']);
    $week = date('W', $fechaSegundos);
    $object['week'] = $week;
    $localConnection = new LocalDB();
    $weekCond = DB_DRIVER === 'pgsql'
        ? "EXTRACT(WEEK FROM a.moment) = $week"
        : "WEEK(a.moment) = $week";
    $weekCondSimple = DB_DRIVER === 'pgsql'
        ? "EXTRACT(WEEK FROM moment) = $week"
        : "WEEK(moment) = $week";

    $sql = "SELECT
        a._id orden,
        a.cliente_nombre cliente,
        a.pago_total total,
        a.pago_abono abono,
        a.pago_descuento descuento,
        b.nombre empleado,
        (a.pago_total - a.pago_descuento) - a.pago_abono + a.pago_nota_credito AS total_pendiente
        FROM ordenes a
        JOIN api_empresas.empresas_usuarios b ON a.responsable = b.id_usuario
        WHERE $weekCond";
    $object['items'] = $localConnection->goQuery($sql);

    $sql = "SELECT SUM(pago_abono) total_semana FROM ordenes WHERE $weekCondSimple";
    $object['total_week'] = $localConnection->goQuery($sql);

    if (is_null($object['total_week'][0]['total_semana'])) {
      $object['total_week'][0]['total_semana'] = '0';
    }

    $sql = "SELECT (SUM(pago_total) - SUM(pago_descuento)) - SUM(pago_abono) + SUM(pago_nota_credito) total_credito
        FROM ordenes WHERE $weekCondSimple";
    $object['total_credito'] = $localConnection->goQuery($sql);

    if (is_null($object['total_credito'][0]['total_credito'])) {
      $object['total_credito'][0]['total_credito'] = '0';
    }

    $sql = "SELECT SUM(pago_descuento) total_descuentos FROM ordenes WHERE $weekCondSimple";
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

    $sql = 'SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleadodo, b.nombre AS empleado
          FROM ordenes_tmp a
          JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario';

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
    // "form" se guarda como texto; las funciones JSON_* de MySQL no existen en PostgreSQL
    // (que usa ->/->> sobre json/jsonb), por eso esta consulta necesita rama por motor.
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
                SELECT a._id,
                       a.form,
                       a.tipo,
                       b.id_usuario AS id_empleado,
                       b.nombre AS empleado,
                       (a.form::jsonb)->>'obs' as observaciones,
                       (a.form::jsonb)->'productos' as productos_json
                FROM ordenes_tmp a
                JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
                UNION ALL
                SELECT p._id,
                       CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form,
                       'Presupuesto Finalizado' as tipo,
                       p.responsable as id_empleado,
                       u.nombre as empleado,
                       p.observaciones,
                       (SELECT jsonb_agg(jsonb_build_object('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                        FROM presupuestos_productos pp
                        LEFT JOIN sizes s ON pp.id_size = s._id
                        LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                        WHERE pp.id_orden = p._id) as productos_json
                FROM presupuestos p
                JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
                WHERE p.status != 'Convertido'
              ) as combined
              ORDER BY _id DESC LIMIT 100";
    } else {
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
                       (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                        FROM presupuestos_productos pp
                        LEFT JOIN sizes s ON pp.id_size = s._id
                        LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                        WHERE pp.id_orden = p._id) as productos_json
                FROM presupuestos p
                JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
                WHERE p.status != 'Convertido'
              ) as combined
              ORDER BY _id DESC LIMIT 100";
    }

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
              'corte' => $p['corte'] ?? '',
              'atributo' => $p['atributo'] ?? $attrText
            ];
          }, $prods);
        } else {
          // Ya es un presupuesto finalizado, asegurar que productos_json sea array
          $item['productos_json'] = $prods;
        }

        // DECODE FORM field for both types
        if (isset($item['form']) && is_string($item['form'])) {
          $item['form'] = json_decode($item['form'], true);
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
    $idOrden = (int) $args['id_orden'];
    $idEmpleado = (int) $args['id_empleado'];
    $idDepartamento = (int) $args['id_departamento'];

    $sql = "SELECT
        obs.observaciones AS observaciones_ordenes,
            (SELECT borrador FROM ordenes_borrador_empleado WHERE id_orden = ? AND id_empleado = ? AND id_departamento = ?) observaciones_empleado
        FROM
            ordenes a
        LEFT JOIN ordenes_observaciones obs ON a._id = obs.id_orden
        WHERE
            a._id = ?";

    $object = $localConnection->goQuery($sql, [$idOrden, $idEmpleado, $idDepartamento, $idOrden]);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ordenes/notas-por-empleado/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_orden = (int) $args['id_orden'];

    $sql = "SELECT
                loa.id_empleado,
                emp.nombre AS empleado,
                dep.departamento,
                obe.borrador AS nota,
                CASE WHEN obe.borrador IS NOT NULL AND obe.borrador != '' THEN 1 ELSE 0 END AS tiene_nota
            FROM
                lotes_detalles_empleados_asignados loa
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = loa.id_empleado
            JOIN departamentos dep ON dep._id = loa.id_departamento
            LEFT JOIN ordenes_borrador_empleado obe ON obe.id_orden = loa.id_orden
                AND obe.id_empleado = loa.id_empleado
                AND obe.id_departamento = loa.id_departamento
            WHERE
                loa.id_orden = ?
            ORDER BY dep.orden_proceso ASC, emp.nombre ASC";

    $object = $localConnection->goQuery($sql, [$id_orden]);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });


  $app->get('/ordenes/borrador/reporte-semanal/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $yearweekCond = DB_DRIVER === 'pgsql'
        ? "EXTRACT(WEEK FROM a.moment) = EXTRACT(WEEK FROM CURRENT_DATE) AND EXTRACT(YEAR FROM a.moment) = EXTRACT(YEAR FROM CURRENT_DATE)"
        : "YEARWEEK(a.moment, 1) = YEARWEEK(CURDATE(), 1)";

    $sql = "SELECT
                b._id id_orden,
                b.cliente_nombre,    
                a._id id_ordenes_borador_empleado,
                a.borrador,
                a.moment
            FROM
                ordenes_borrador_empleado a
            LEFT JOIN ordenes b ON b._id = a.id_orden
            WHERE a.id_empleado = ? AND a.id_departamento = ?
              AND $yearweekCond
        ";

    $object = $localConnection->goQuery($sql, [(int) $args['id_empleado'], (int) $args['id_departamento']]);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR BORRADOR DEL EMPLEADO
  $app->post('/ordenes/borrador', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $idOrden = (int) $data['id_orden'];
    $idEmpleado = (int) $data['id_empleado'];
    $idDepartamento = (int) $data['id_departamento'];
    $borrador = $data['borrador'] ?? '';

    // Verificar si ya existe un registro para la orden
    $sql = 'SELECT _id FROM ordenes_borrador_empleado WHERE id_orden = ? AND id_empleado = ? AND id_departamento = ?';
    $resp = $localConnection->goQuery($sql, [$idOrden, $idEmpleado, $idDepartamento]);

    if (empty($resp)) {
      $sql = 'INSERT INTO ordenes_borrador_empleado (id_orden, id_empleado, id_departamento, borrador) VALUES (?, ?, ?, ?)';
      $resp = $localConnection->goQuery($sql, [$idOrden, $idEmpleado, $idDepartamento, $borrador]);
    } else {
      $sql = 'UPDATE ordenes_borrador_empleado SET id_departamento = ?, borrador = ? WHERE id_orden = ? AND id_empleado = ?';
      $resp = $localConnection->goQuery($sql, [$idDepartamento, $borrador, $idOrden, $idEmpleado]);
    }
    $localConnection->disconnect();
    $object['resp'] = 'OK';

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ELIMINAR ORDENES GUARDADAS
  $app->post('/ordenes/guardadas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $data = $request->getParsedBody();
    $id = $data['id'];
    $tipo = $data['tipo'] ?? 'Borrador'; // 'Presupuesto Finalizado' o cualquier otra cosa (Borrador)

    // Atomicidad: borrado de presupuesto + sus productos en una transacción
    $localConnection->beginTransaction();

    if ($tipo === 'Presupuesto Finalizado') {
      // Eliminar presupuesto finalizado y sus productos
      $sql1 = 'DELETE FROM presupuestos_productos WHERE id_orden = ' . $id;
      $sql2 = 'DELETE FROM presupuestos WHERE _id = ' . $id;
      $localConnection->goQuery($sql1);
      $localConnection->goQuery($sql2);
      $object['sql_delete'] = "$sql1; $sql2";
    } else {
      // Eliminar borrador
      $sql = 'DELETE FROM ordenes_tmp WHERE _id =  ' . $id;
      $localConnection->goQuery($sql);
      $object['sql_delete'] = $sql;
    }

    $object['response_delete'] = "OK";

    $localConnection->commit();

    // Recargar la lista
    if (DB_DRIVER === 'pgsql') {
      // Equivalentes Postgres: form::jsonb ->> / -> en vez de JSON_UNQUOTE(JSON_EXTRACT());
      // jsonb_agg(jsonb_build_object()) en vez de JSON_ARRAYAGG(JSON_OBJECT()).
      $sqlLoad = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
                SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleado, b.nombre AS empleado,
                       (a.form::jsonb ->> 'obs') as observaciones,
                       (a.form::jsonb -> 'productos') as productos_json
                FROM ordenes_tmp a
                JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
                UNION ALL
                SELECT p._id,
                       CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form,
                       'Presupuesto Finalizado' as tipo,
                       p.responsable as id_empleado,
                       u.nombre as empleado,
                       p.observaciones,
                       (SELECT jsonb_agg(jsonb_build_object('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                        FROM presupuestos_productos pp
                        LEFT JOIN sizes s ON pp.id_size = s._id
                        LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                        WHERE pp.id_orden = p._id) as productos_json
                FROM presupuestos p
                JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
                WHERE p.status != 'Convertido'
              ) as combined
              ORDER BY _id DESC LIMIT 100";
    } else {
      $sqlLoad = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
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
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                      FROM presupuestos_productos pp
                      LEFT JOIN sizes s ON pp.id_size = s._id
                      LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";
    }

    $results = $localConnection->goQuery($sqlLoad);
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
                    'corte' => $p['corte'] ?? '',
                    'atributo' => $p['atributo'] ?? $attrText
                ];
            }, $prods);
        } else {
            $item['productos_json'] = $prods;
        }

        // DECODE FORM field
        if (isset($item['form']) && is_string($item['form'])) {
            $item['form'] = json_decode($item['form'], true);
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

    $sql = 'INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES (?, ?, ?)';
    $object['sql_insert'] = $sql;
    $localConnection->goQuery($sql, [$data['form'], $data['id_empleado'], $data['tipo']]);

    if (DB_DRIVER === 'pgsql') {
      // form es TEXT; se castea a json para usar los operadores ->/->>. JSON_ARRAYAGG/JSON_OBJECT
      // (MySQL-only) se traducen a json_agg/json_build_object.
      $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
                SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleado, b.nombre AS empleado,
                       (a.form::json->>'obs') as observaciones,
                       (a.form::json->'productos') as productos_json
                FROM ordenes_tmp a
                JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
                UNION ALL
                SELECT p._id,
                       CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form,
                       'Presupuesto Finalizado' as tipo,
                       p.responsable as id_empleado,
                       u.nombre as empleado,
                       p.observaciones,
                       (SELECT json_agg(json_build_object('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                        FROM presupuestos_productos pp
                        LEFT JOIN sizes s ON pp.id_size = s._id
                        LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                        WHERE pp.id_orden = p._id) as productos_json
                FROM presupuestos p
                JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
                WHERE p.status != 'Convertido'
              ) as combined
              ORDER BY _id DESC LIMIT 100";
    } else {
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
                       (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', pp.tela, 'corte', pp.corte, 'atributo', pa.attribute_name))
                        FROM presupuestos_productos pp
                        LEFT JOIN sizes s ON pp.id_size = s._id
                        LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                        WHERE pp.id_orden = p._id) as productos_json
                FROM presupuestos p
                JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
                WHERE p.status != 'Convertido'
              ) as combined
              ORDER BY _id DESC LIMIT 100";
    }

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
                    'corte' => $p['corte'] ?? '',
                    'atributo' => $p['atributo'] ?? $attrText
                ];
            }, $prods);
        } else {
            $item['productos_json'] = $prods;
        }

        // DECODE FORM field
        if (isset($item['form']) && is_string($item['form'])) {
            $item['form'] = json_decode($item['form'], true);
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

    $sql = 'SELECT departamento FROM empresas_usuarios WHERE id_usuario = ?';
    $departamento = $localConnection->goQuery($sql, [(int) $args['id_empleado']])[0]['departamento'];

    $localConnection = new LocalDB();

    // Sub-select de categorías compatible con ambos motores
    if (DB_DRIVER === 'pgsql') {
      $catSubSelect = "(
          SELECT json_agg(json_build_object('category_name', c.nombre, 'category_total', (op.cantidad * op.precio_unitario)))
          FROM ordenes_productos op
          JOIN products p ON op.id_woo = p._id
          JOIN categories c ON c._id::text = ANY(string_to_array(p.category_ids, ','))
          WHERE op.id_orden = ord._id
      ) AS product_categories";
      $saldo = "(ord.pago_total - COALESCE((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0))";
    } else {
      $catSubSelect = "(
          SELECT CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('category_name', c.nombre, 'category_total', (op.cantidad * op.precio_unitario))), ']')
          FROM ordenes_productos op
          JOIN products p ON op.id_woo = p._id
          JOIN categories c ON FIND_IN_SET(c._id, p.category_ids)
          WHERE op.id_orden = ord._id
      ) AS product_categories";
      $saldo = "(ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0))";
    }

    $baseFields = "ord.responsable, ord._id orden, ord._id id_father, ord._id acc,
                ord.responsable id_vendedor, emp.nombre vendedor,
                ord.cliente_nombre, cus.phone, cus.email,
                ord.pago_total total, ord.fecha_inicio, ord.fecha_entrega,
                $catSubSelect,
                (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
                $saldo AS saldo_pendiente,
                ord.status estatus";
    $baseJoins = "FROM ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable";
    $saldoFilter = "$saldo > 0 OR ($saldo = 0 AND ord.status != 'entregada') OR $saldo < 0";

    $sqlParams = [];
    if (strpos($departamento, 'Admin') !== false) {
      $sql = "SELECT $baseFields $baseJoins
            WHERE ord.status != 'cancelada' AND ($saldoFilter)
            ORDER BY ord._id DESC";
    } else {
      $sql = "SELECT $baseFields $baseJoins
            WHERE ord.responsable = ?
                AND ord.status != 'cancelada'
                AND ($saldoFilter)
            ORDER BY ord._id DESC";
      $sqlParams[] = (int) $args['id_empleado'];
    }

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

    $items = $localConnection->goQuery($sql, $sqlParams);
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

  // OPCIONES DE FILTRO PARA "TODAS LAS ORDENES" -- catálogo completo, desacoplado de la
  // paginación (las opciones de un filtro no pueden depender de qué página esté cargada).
  $app->get('/table/ordenes-todas/opciones', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $categorias = $localConnection->goQuery('SELECT nombre FROM categories WHERE eliminado = 0 ORDER BY nombre');
    $estadosOrden = $localConnection->goQuery('SELECT DISTINCT status FROM ordenes WHERE status IS NOT NULL ORDER BY status');

    // Misma consulta ya usada en /reporte-de-pagos (finance.php) para el select de vendedores.
    $sqlVendedores = "SELECT DISTINCT
        id_usuario _id,
        nombre
    FROM
        api_empresas.empresas_usuarios a
    JOIN api_empresas.empresas_usuarios_departamentos b ON a.id_usuario = b.id_empleado
    WHERE
        b.id_departamento IN (SELECT _id FROM departamentos WHERE departamento IN ('Comercialización', 'Comecialización', 'Administración'))
        AND a.id_empresa = " . ID_EMPRESA . "
    ORDER BY nombre";
    $vendedores = $localConnection->goQuery($sqlVendedores);

    $localConnection->disconnect();

    $object = [
      'categorias' => array_column($categorias, 'nombre'),
      'estados_orden' => array_column($estadosOrden, 'status'),
      'vendedores' => $vendedores,
    ];

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODAS LAS ORDENES
  $app->get('/table/ordenes-todas', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $fecha_inicio = $params['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $params['fecha_fin'] ?? date('Y-m-d');

    $localConnection = new LocalDB();

    // product_categories se calculaba antes con una subconsulta correlacionada (una vez POR
    // fila, patrón N+1) -- confirmado con EXPLAIN ANALYZE que eso multiplicaba el tiempo de
    // esta consulta por 5.5x (68ms -> 376ms con 3041 órdenes en Desarrollo), dominado por un
    // Seq Scan sobre categories ejecutado dentro del loop. Ahora se calcula en un segundo query
    // por lote (una sola pasada) después de tener los ids de esta página, ver más abajo.
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
    (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
    (SELECT SUM(monto / COALESCE(NULLIF(tasa, 0), 1)) FROM metodos_de_pago WHERE id_orden = ord._id) AS total_abonado_base,
    ord.status estatus
FROM
    ordenes ord
JOIN customers cus ON ord.id_wp = cus._id
LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
WHERE
    (ord.fecha_inicio >= ? AND ord.fecha_inicio <= ?) OR
    (ord.fecha_entrega >= ? AND ord.fecha_entrega <= ?) OR
    (ord.fecha_inicio <= ? AND ord.fecha_entrega >= ?)
ORDER BY ord._id DESC";

    $sqlParams = [$fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin];
    $items = $localConnection->goQuery($sql, $sqlParams);

    // Categorías de producto por orden, en un solo query por lote (no por fila).
    $categoriesByOrder = [];
    $ids = array_column($items, 'orden');
    if (!empty($ids)) {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      if (DB_DRIVER === 'pgsql') {
        $sqlCats = "SELECT op.id_orden, c.nombre AS category_name, SUM(op.cantidad * op.precio_unitario) AS category_total
            FROM ordenes_productos op
            JOIN products p ON op.id_woo = p._id
            JOIN categories c ON c._id::text = ANY(string_to_array(p.category_ids, ','))
            WHERE op.id_orden IN ($placeholders)
            GROUP BY op.id_orden, c.nombre";
      } else {
        $sqlCats = "SELECT op.id_orden, c.nombre AS category_name, SUM(op.cantidad * op.precio_unitario) AS category_total
            FROM ordenes_productos op
            JOIN products p ON op.id_woo = p._id
            JOIN categories c ON FIND_IN_SET(c._id, p.category_ids)
            WHERE op.id_orden IN ($placeholders)
            GROUP BY op.id_orden, c.nombre";
      }
      $catRows = $localConnection->goQuery($sqlCats, $ids);
      foreach ($catRows as $catRow) {
        $categoriesByOrder[(int) $catRow['id_orden']][] = [
          'category_name' => $catRow['category_name'],
          'category_total' => $catRow['category_total'],
        ];
      }
    }

    foreach ($items as &$item) {
      // Mismo contrato que antes: null cuando la orden no tiene categorías, no un array vacío.
      $item['product_categories'] = $categoriesByOrder[(int) $item['orden']] ?? null;
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

  // ORDENES CON DEUDAA
  $app->get('/table/ordenes-con-deuda', function (Request $request, Response $response, array $args) {
    $fechaFormat = DB_DRIVER === 'pgsql'
        ? "TO_CHAR(a.moment, 'DD/MM/YYYY')"
        : "DATE_FORMAT(a.moment, '%d/%m/%Y')";

    // Subconsulta correlacionada: SUM() mezclado con d.pago_total (no agregado) sin GROUP BY.
    // MySQL lo tolera (d.pago_total es constante por grupo via el JOIN 1:1); PostgreSQL exige
    // que sea funcionalmente dependiente o este envuelto en un agregado.
    $pagoTotalExpr = DB_DRIVER === 'pgsql' ? 'MAX(d.pago_total)' : 'd.pago_total';
    $sql = "SELECT
        a._id orden,
        a.responsable,
        a._id id_father,
        a._id acc,
        a.cliente_nombre cliente,
        a.fecha_inicio,
        a.fecha_entrega,
        'TRAER DESDE EL ENDPOINT DEDICADO' obs,
        a.status estatus,
        a.pago_total AS monto,
        $fechaFormat AS fecha,
        (
         SELECT
         $pagoTotalExpr - SUM(c.abono) - SUM(c.descuento) + SUM(c.nota_credito) AS total_deuda
         FROM abonos c
         JOIN ordenes d ON c.id_orden = d._id
         WHERE c.id_orden = a._id
         ) AS total_deuda
        FROM ordenes AS a
        WHERE a.status != 'cancelada'
        AND (
         SELECT
         $pagoTotalExpr - SUM(c.abono) - SUM(c.descuento) + SUM(c.nota_credito) AS total_deuda
         FROM abonos c
         JOIN ordenes d ON c.id_orden = d._id
         WHERE c.id_orden = a._id) > 0
        ORDER BY _id DESC";
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
