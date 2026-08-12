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

    $queryParams = $request->getQueryParams();
    $fecha_inicio = $queryParams['fecha_inicio'] ?? null;
    $fecha_fin = $queryParams['fecha_fin'] ?? null;
    $ignorarFecha = !$fecha_inicio || !$fecha_fin
      || (isset($queryParams['ignorar_fecha']) && $queryParams['ignorar_fecha'] !== '0' && $queryParams['ignorar_fecha'] !== '');
    $search = trim($queryParams['search'] ?? '');
    $idVendedor = isset($queryParams['id_vendedor']) ? (int) $queryParams['id_vendedor'] : 0;
    $categoria = trim($queryParams['categoria'] ?? 'todas');
    $estadoOrden = trim($queryParams['estado_orden'] ?? 'todas');
    $cursor = (isset($queryParams['cursor']) && $queryParams['cursor'] !== '') ? (int) $queryParams['cursor'] : null;
    $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 25;

    if (DB_DRIVER === 'pgsql') {
      $saldo = "(ord.pago_total - COALESCE((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0))";
    } else {
      $saldo = "(ord.pago_total - IFNULL((SELECT SUM(abono) + SUM(descuento) - SUM(nota_credito) FROM abonos WHERE id_orden = ord._id), 0))";
    }

    $baseFields = "ord.responsable, ord._id orden, ord._id id_father, ord._id acc,
                ord.responsable id_vendedor, emp.nombre vendedor,
                ord.cliente_nombre, cus.phone, cus.email,
                ord.pago_total total, ord.fecha_inicio, ord.fecha_entrega,
                (SELECT SUM(descuento) FROM abonos WHERE id_orden = ord._id) AS descuento_total,
                $saldo AS saldo_pendiente,
                ord.status estatus";
    $baseJoins = "FROM ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable";
    $saldoFilter = "$saldo > 0 OR ($saldo = 0 AND ord.status != 'entregada') OR $saldo < 0";

    // Filtros base (regla de negocio, siempre aplican, nunca se ignoran por búsqueda/filtros).
    $whereClauses = ["ord.status != 'cancelada'", "($saldoFilter)"];
    $whereParams = [];
    if (strpos($departamento, 'Admin') === false) {
      $whereClauses[] = 'ord.responsable = ?';
      $whereParams[] = (int) $args['id_empleado'];
    }

    // Filtros opcionales (mismo patrón que /table/ordenes-todas): fecha se ignora si no se
    // dio rango o si hay una búsqueda/filtro activo, para que buscar/filtrar nunca dependa
    // de si la orden está dentro del rango cargado.
    if (!$ignorarFecha) {
      $whereClauses[] = '((ord.fecha_inicio >= ? AND ord.fecha_inicio <= ?) OR (ord.fecha_entrega >= ? AND ord.fecha_entrega <= ?) OR (ord.fecha_inicio <= ? AND ord.fecha_entrega >= ?))';
      array_push($whereParams, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
    }

    if ($idVendedor > 0) {
      $whereClauses[] = 'ord.responsable = ?';
      $whereParams[] = $idVendedor;
    }

    if ($estadoOrden !== '' && $estadoOrden !== 'todas') {
      $whereClauses[] = 'LOWER(ord.status) = LOWER(?)';
      $whereParams[] = $estadoOrden;
    }

    if ($categoria !== '' && $categoria !== 'todas') {
      if (DB_DRIVER === 'pgsql') {
        $whereClauses[] = "EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON op.id_woo = p._id JOIN categories c ON c._id::text = ANY(string_to_array(p.category_ids, ',')) WHERE op.id_orden = ord._id AND LOWER(c.nombre) = LOWER(?))";
      } else {
        $whereClauses[] = "EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON op.id_woo = p._id JOIN categories c ON FIND_IN_SET(c._id, p.category_ids) WHERE op.id_orden = ord._id AND LOWER(c.nombre) = LOWER(?))";
      }
      $whereParams[] = $categoria;
    }

    if ($search !== '') {
      $likeOp = DB_DRIVER === 'pgsql' ? 'ILIKE' : 'LIKE';
      $castOrden = DB_DRIVER === 'pgsql' ? 'CAST(ord._id AS TEXT)' : 'CAST(ord._id AS CHAR)';
      $whereClauses[] = "($castOrden $likeOp ? OR ord.cliente_nombre $likeOp ?)";
      $searchLike = '%' . $search . '%';
      $whereParams[] = $searchLike;
      $whereParams[] = $searchLike;
    }

    $whereSqlBase = 'WHERE ' . implode(' AND ', $whereClauses);

    $totalCount = null;
    if ($cursor === null) {
      $countResult = $localConnection->goQuery("SELECT COUNT(*) AS total FROM ordenes ord $whereSqlBase", $whereParams);
      $totalCount = (int) ($countResult[0]['total'] ?? 0);
    }

    $pageWhereClauses = $whereClauses;
    $pageWhereParams = $whereParams;
    if ($cursor !== null) {
      $pageWhereClauses[] = 'ord._id < ?';
      $pageWhereParams[] = $cursor;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $pageWhereClauses);

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

    // product_categories se calculaba antes con una subconsulta correlacionada por fila
    // (mismo patrón N+1 ya corregido en /table/ordenes-todas) -- ahora se calcula en un
    // solo query por lote, después de tener los ids de esta página.
    $sql = "SELECT $baseFields $baseJoins
        $whereSql
        ORDER BY ord._id DESC
        LIMIT ?";
    $sqlParams = array_merge($pageWhereParams, [$limit + 1]);
    $items = $localConnection->goQuery($sql, $sqlParams);

    $nextCursor = null;
    if (count($items) > $limit) {
      $items = array_slice($items, 0, $limit);
      $lastItem = end($items);
      $nextCursor = (int) $lastItem['orden'];
    }

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
      $item['product_categories'] = $categoriesByOrder[(int) $item['orden']] ?? null;
    }
    $object['items'] = $items;
    $object['next_cursor'] = $nextCursor;
    if ($totalCount !== null) {
      $object['total_count'] = $totalCount;
    }
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

    // Deduplicado case-insensitive en PHP -- datos reales confirmados con nombres/estados
    // duplicados por casing distinto ('cancelada'/'Cancelada') o filas realmente repetidas
    // (2 categorías "Banderines" con _id distinto, mismo nombre exacto) que de otro modo
    // aparecen 2 veces en el dropdown de filtro con el mismo value, mostrando ambas
    // "seleccionadas" a la vez al elegir cualquiera.
    $categoriasRaw = $localConnection->goQuery('SELECT nombre FROM categories WHERE eliminado = 0');
    $categoriasSeen = [];
    foreach ($categoriasRaw as $row) {
      $nombre = trim($row['nombre'] ?? '');
      if ($nombre === '') continue;
      $key = mb_strtolower($nombre);
      if (!isset($categoriasSeen[$key])) {
        $categoriasSeen[$key] = $nombre;
      }
    }
    $categoriasNombres = array_values($categoriasSeen);
    sort($categoriasNombres);

    $estadosOrdenRaw = $localConnection->goQuery('SELECT DISTINCT status FROM ordenes WHERE status IS NOT NULL');
    $estadosSeen = [];
    foreach ($estadosOrdenRaw as $row) {
      $status = trim($row['status'] ?? '');
      if ($status === '') continue;
      $key = mb_strtolower($status);
      if (!isset($estadosSeen[$key])) {
        $estadosSeen[$key] = mb_convert_case($key, MB_CASE_TITLE);
      }
    }
    $estadosOrdenNombres = array_values($estadosSeen);
    sort($estadosOrdenNombres);

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
      'categorias' => $categoriasNombres,
      'estados_orden' => $estadosOrdenNombres,
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
    // ignorar_fecha=1 se usa cuando hay una búsqueda activa (search) -- así la búsqueda
    // encuentra órdenes de CUALQUIER fecha, no solo las del rango seleccionado (bug real
    // reportado: la página se llama "Todas las Órdenes" pero el buscador solo encontraba
    // lo que ya estaba cargado dentro del rango).
    $ignorarFecha = isset($params['ignorar_fecha']) && $params['ignorar_fecha'] !== '0' && $params['ignorar_fecha'] !== '';
    $search = trim($params['search'] ?? '');
    $idVendedor = isset($params['id_vendedor']) ? (int) $params['id_vendedor'] : 0;
    $categoria = trim($params['categoria'] ?? 'todas');
    $estadoOrden = trim($params['estado_orden'] ?? 'todas');
    $cursor = (isset($params['cursor']) && $params['cursor'] !== '') ? (int) $params['cursor'] : null;
    $limit = isset($params['limit']) ? min(100, max(1, (int) $params['limit'])) : 25;

    $localConnection = new LocalDB();

    // Filtros base (sin el cursor -- el cursor se agrega aparte porque el conteo total
    // de la primera página se calcula SIN él, ver más abajo).
    $whereClauses = [];
    $whereParams = [];

    if (!$ignorarFecha) {
      $whereClauses[] = '((ord.fecha_inicio >= ? AND ord.fecha_inicio <= ?) OR (ord.fecha_entrega >= ? AND ord.fecha_entrega <= ?) OR (ord.fecha_inicio <= ? AND ord.fecha_entrega >= ?))';
      array_push($whereParams, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
    }

    if ($idVendedor > 0) {
      $whereClauses[] = 'ord.responsable = ?';
      $whereParams[] = $idVendedor;
    }

    if ($estadoOrden !== '' && $estadoOrden !== 'todas') {
      // Insensible a mayúsculas -- datos reales confirmados con 'cancelada'/'Cancelada'
      // coexistiendo en la misma tabla (mismo patrón ya conocido en BadgeEstatusOrden.vue).
      $whereClauses[] = 'LOWER(ord.status) = LOWER(?)';
      $whereParams[] = $estadoOrden;
    }

    if ($categoria !== '' && $categoria !== 'todas') {
      if (DB_DRIVER === 'pgsql') {
        $whereClauses[] = "EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON op.id_woo = p._id JOIN categories c ON c._id::text = ANY(string_to_array(p.category_ids, ',')) WHERE op.id_orden = ord._id AND LOWER(c.nombre) = LOWER(?))";
      } else {
        $whereClauses[] = "EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON op.id_woo = p._id JOIN categories c ON FIND_IN_SET(c._id, p.category_ids) WHERE op.id_orden = ord._id AND LOWER(c.nombre) = LOWER(?))";
      }
      $whereParams[] = $categoria;
    }

    if ($search !== '') {
      $likeOp = DB_DRIVER === 'pgsql' ? 'ILIKE' : 'LIKE';
      $castOrden = DB_DRIVER === 'pgsql' ? 'CAST(ord._id AS TEXT)' : 'CAST(ord._id AS CHAR)';
      $whereClauses[] = "($castOrden $likeOp ? OR ord.cliente_nombre $likeOp ?)";
      $searchLike = '%' . $search . '%';
      $whereParams[] = $searchLike;
      $whereParams[] = $searchLike;
    }

    $whereSqlBase = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

    // total_count solo se calcula en la primera página de cada combinación de filtros
    // (cursor ausente) -- no tiene sentido repetir un COUNT en cada "cargar más".
    $totalCount = null;
    if ($cursor === null) {
      $countResult = $localConnection->goQuery("SELECT COUNT(*) AS total FROM ordenes ord $whereSqlBase", $whereParams);
      $totalCount = (int) ($countResult[0]['total'] ?? 0);
    }

    $pageWhereClauses = $whereClauses;
    $pageWhereParams = $whereParams;
    if ($cursor !== null) {
      $pageWhereClauses[] = 'ord._id < ?';
      $pageWhereParams[] = $cursor;
    }
    $whereSql = $pageWhereClauses ? ('WHERE ' . implode(' AND ', $pageWhereClauses)) : '';

    // product_categories se calculaba antes con una subconsulta correlacionada (una vez POR
    // fila, patrón N+1) -- confirmado con EXPLAIN ANALYZE que eso multiplicaba el tiempo de
    // esta consulta por 5.5x (68ms -> 376ms con 3041 órdenes en Desarrollo), dominado por un
    // Seq Scan sobre categories ejecutado dentro del loop. Ahora se calcula en un segundo query
    // por lote (una sola pasada) después de tener los ids de esta página, ver más abajo.
    // Se pide limit+1 filas para saber si hay una página siguiente sin un COUNT aparte.
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
$whereSql
ORDER BY ord._id DESC
LIMIT ?";

    $sqlParams = array_merge($pageWhereParams, [$limit + 1]);
    $items = $localConnection->goQuery($sql, $sqlParams);

    $nextCursor = null;
    if (count($items) > $limit) {
      $items = array_slice($items, 0, $limit);
      $lastItem = end($items);
      $nextCursor = (int) $lastItem['orden'];
    }

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
    $object['next_cursor'] = $nextCursor;
    if ($totalCount !== null) {
      $object['total_count'] = $totalCount;
    }
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
