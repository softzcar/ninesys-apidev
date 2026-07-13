<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** Asignacion */
  // Obtener datos para la asignaciond e empelados
  $app->get('/asignacion/ordenes', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'inicio';
    $object['fields'][2]['label'] = 'Inicio';

    $object['fields'][3]['key'] = 'entrega';
    $object['fields'][3]['label'] = 'Entrega';

    $object['fields'][4]['key'] = 'status';
    $object['fields'][4]['label'] = 'Estatus';

    $object['fields'][4]['key'] = 'asignar';
    $object['fields'][4]['label'] = 'Asignar';

    $sql = "SELECT a._id orden, a._id asignar, a.cliente_nombre cliente, a.fecha_inicio inicio, a.fecha_entrega entrega, a.status estatus, b.terminado FROM ordenes a JOIN disenos b ON a._id = b.id_orden WHERE (a.status = 'activa' OR a.status = 'terminada' OR a.status = 'En espera' OR status = 'pausada') AND b.terminado = 1 OR b.tipo = 'no' ORDER BY a._id DESC";

    $object['items'] = $localConnection->goQuery($sql);
    $object['data'] = $object['items'];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener empleados de la asignados de la orden
  $app->get('/asignacion/empleados/{orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT dep_responsable responsable,dep_diseno diseno, dep_jefe_diseno, dep_corte corte,dep_impresion impresion,dep_estampado estampado,dep_confeccion confeccion,dep_revision revision FROM ordenes WHERE _id = ' . $args['orden'];
    $object['sql'] = $sql;
    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR ASIGNACION
  $app->post('/asignacion/{orden}/{departamento}/{empleado}', function (Request $request, Response $response, $args) {
    // ACTUALIZAR DATOS DE LA ORDEN
    $localConnection = new LocalDB();
    $departamento = 'dep_' . $args['departamento'];

    // Atomicidad FK: UPDATE ordenes + cambios en pagos en una transacción
    $localConnection->beginTransaction();

    if ($args['empleado'] === 'none') {
      $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = NULL WHERE _id = ' . $args['orden'];
      $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
    } else {
      // BUSCAR COMISION DEL EMPLEADO PARA LA ORDEN
      $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = ' . $args['empleado'] . ' WHERE _id = ' . $args['orden'];
      if (DB_DRIVER === 'pgsql') {
        $sql_comision = 'SELECT comision FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $args['empleado'];
      } else {
        $sql_comision = 'SELECT  comision FROM empleados WHERE _id = ' . $args['empleado'];
      }
      $dataEmpleado = $localConnection->goQuery($sql_comision);

      $comision = $dataEmpleado[0]['comision'];
      // PREPARAR FECHAS
      $myDate = new CustomTime();
      $now = $myDate->today();

      // GUARDAR DATOS DEL PAGO
      $values = "'" . $now . "',";
      $values .= $args['empleado'] . ',';
      $values .= $args['orden'] . ',';
      $values .= "'" . $args['departamento'] . "',";
      $values .= "'0000-00-00',";
      $values .= '0,';
      $values .= $comision . ',';
      $values .= '0';

      $object['sql_pagos'] = $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
      $object['sql_pagos'] .= $sql_pagos = 'INSERT INTO pagos (moment, id_empleado, id_orden, departamento, fecha_terminado, dolar,  comision, pago) VALUES (' . $values . ')';
    }

    $dataEmpleado = $localConnection->goQuery($sql_ordenes);
    $dataEmpleado = $localConnection->goQuery($sql_pagos);

    $localConnection->commit();
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ELIMINAR ASIGNACION
  $app->post('/asignacion/elimianr/{orden}/{departamento}', function (Request $request, Response $response, $args) {
    // ACTUALIZAR DATOS DE LA ORDEN
    $localConnection = new LocalDB();
    // ELIMINAR DATOS DEL PAGO
    $sql = 'DELETE FROM PAGOS WHERE id_orden = ' . $request['id_orden'] . ' AND departamento = ' . $args['departamento'];
    $object['dataEmpleado'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

}; // Fin de la función que envuelve las rutas
