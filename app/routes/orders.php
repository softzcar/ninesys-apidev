<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** * ORDENES */

  // Editar orden -> Actualixar datos cambio de endpoint a _null previniendo acceso

  $app->post('/orden/editar', function (Request $request, Response $response) {
    /**
     * opciones de edición
     *   - editar-talla
     *   - editar-cantidad
     *   - editar-corte
     *   - editar-tela
     *   - nuevo-producto
     *   - eliminar-producto
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    switch ($data['accion']) {
      case 'editar-cantidad':
        $sql = 'UPDATE ordenes_productos SET cantidad = ' . $data['cantidad'] . ' WHERE _id = ' . $data['id'];
        $resp = $localConnection->goQuery($sql);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

        $resp = $localConnection->goQuery($sql);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
        break;

      case 'editar-talla':
        // Guardar nuevos datos
        $sql = "UPDATE ordenes_productos SET precio_unitario = '" . $data['precio'] . "', talla = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
        $resp = $localConnection->goQuery($sql);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

        $resp = $localConnection->goQuery($sql);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        // Guardar nuevo pago_total de la orden
        $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
        break;

      case 'editar-corte':
        $sql = "UPDATE ordenes_productos SET corte = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'];
        break;

      case 'eliminar-producto':
        $sql = 'DELETE FROM ordenes_productos WHERE _id = ' . $data['id'];
        break;

      case 'editar-tela':
        // Guardar cambios
        $sql = "UPDATE ordenes_productos SET tela = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
        break;

      case 'nuevo-producto':
        $campos = '(moment, id_orden, id_woo, precio_woo, name, cantidad, talla, corte, tela, precio_unitario)';

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $values = '(';
        $values .= "'" . $now . "',";
        $values .= '' . $data['id_orden'] . ',';
        $values .= '' . $data['id_woo'] . ',';
        $values .= '' . $data['precio_woo'] . ',';
        $values .= "'" . $data['name'] . "',";
        $values .= '' . $data['cantidad'] . ',';
        $values .= "'" . $data['talla'] . "',";
        $values .= "'" . $data['corte'] . "',";
        $values .= "'" . $data['tela'] . "',";
        $values .= '' . $data['precio_unitario'] . ')';

        $sql = 'INSERT INTO ordenes_productos ' . $campos . ' VALUES ' . $values;
        break;

      default:
        // code...
        break;
    }

    $resp = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['response'] = $resp;

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar estado de la orden
  $app->post('/orden/actualizar-estado', function (Request $request, Response $response, $args) {
    $order = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE ordenes SET status = '" . $order['estado'] . "' WHERE _id = " . $order['id'];
    $data = $localConnection->goQuery($sql);

    // Generar el regstro en Woocomemrce si la orden está terminada
    if ($order['estado'] === 'terminada' || $order['estado'] === 'entregada') {
      $sql = 'SELECT id_wp_order FROM ordenes WHERE _id = ' . $order['id'];
      $data = $localConnection->goQuery($sql);

      if (!is_null($data[0]['id_wp_order'])) {
        $woo = new WooMe();

        if ($order['estado'] === 'terminada') {
          // UPDATE PRODUCTS QUANTITY
          // Buscar cantidades de productos en ninesys
          $sql = 'SELECT id_woo, cantidad FROM `ordenes_productos` WHERE id_orden = ' . $order['id'];
          $productos = $localConnection->goQuery($sql);

          // $data['prod_ninesys'] = $productos;

          foreach ($productos as $key => $producto) {
            // Buscar existencia de productos en WC
            $tmpProd = $woo->getProductById($producto['id_woo']);

            // Sumar cantidades de ambas fuentes
            $tmpCantidad = $tmpProd->stock_quantity + $producto['cantidad'];

            $woo->updateProductQuantity($producto['id_woo'], $tmpCantidad);
          }
        }

        if ($order['estado'] === 'entregada') {
          $r = $woo->updateOrderStatus(intval($data[0]['id_wp_order']), 'completed');
        }
      } else {
        $r['wc'] = 'La orden no tiene ID de pedido de Woocomemrce';
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Buscar ordenes para asignación

  $app->get('/orden/asignacion/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id = $args['id'];

    $sql['detalle_empleados'] = 'SELECT `dep_responsable_detalles` responsable, `dep_diseno_detalles` diseno, `dep_corte_detalles` corte, `dep_impresion_detalles` impresion, `dep_estampado_detalles` estampado, `dep_confeccion_detalles` confeccion, `dep_revision_detalles` revision FROM `ordenes` WHERE `_id` = ' . $id;

    $sql['orden'] = " SELECT _id, status, cliente_nombre, cliente_cedula, lote_id lote, fecha_inicio, fecha_entrega FROM ordenes WHERE _id = '" . $id . "' ";
    $sql['orden_personas'] = "SELECT * FROM ordenes_personas WHERE id_order = '" . $id . "'";
    $sql['ordeen_personas_productos'] = "SELECT a._id, a.id_orden, a.idp, a.prodcuto, a.cantidad, a.talla, a.tela, a.detalles, b.nombre FROM ordenes_personas_productos a JOIN ordenes_personas b ON a.idp = b.idp WHERE id_orden = '" . $id . "'";
    $sql['orden_productos'] = "SELECT _id, id_woo, name FROM ordenes_productos WHERE id_orden = '" . $id . "'";
    $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['corte'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_corte = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['impresion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_impresion = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['estampado'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_estampado = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['confeccion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_confeccion = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['revision'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_revision = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['responsable'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_responsable = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_productos_cantidad'] = "SELECT a.cantidad, a.prodcuto,. a.idp FROM ordenes_personas_productos a WHERE  id_orden = '" . $id . "'";
    $sql['lotes_detalles'] = 'SELECT producto, unidades_solicitadas, unidades_restantes, departamento, id_orden FROM lotes_detalles WHERE id_orden = ' . $id;

    $object = $localConnection->goQuery($sql['orden'])[0];

    $object['detalle_empleados'] = $localConnection->goQuery($sql['detalle_empleados'])[0];

    $object['orden_productos_cantidad'] = $localConnection->goQuery($sql['orden_productos_cantidad']);

    $object['orden_personas'] = $localConnection->goQuery($sql['orden_personas']);

    $object['orden_personas_productos'] = $localConnection->goQuery($sql['ordeen_personas_productos']);

    $object['orden_productos'] = $localConnection->goQuery($sql['orden_productos']);

    // LOTES DETALLES
    $object['lotes_detalles'] = $localConnection->goQuery($sql['lotes_detalles']);

    // EMPLEADOS
    $object['empleados']['corte'] = $localConnection->goQuery($sql['orden_empleados']['corte']);
    if ($object['empleados']['corte'] == null) {
      $object['empleados']['corte'] = '';
    }

    $object['empleados']['impresion'] = $localConnection->goQuery($sql['orden_empleados']['impresion']);
    if ($object['empleados']['impresion'] == null) {
      $object['empleados']['impresion'] = '';
    }

    $object['empleados']['estampado'] = $localConnection->goQuery($sql['orden_empleados']['estampado']);
    if ($object['empleados']['estampado'] == null) {
      $object['empleados']['estampado'] = '';
    }

    $object['empleados']['confeccion'] = $localConnection->goQuery($sql['orden_empleados']['confeccion']);
    if ($object['empleados']['confeccion'] == null) {
      $object['empleados']['confeccion'] = '';
    }

    $object['empleados']['revision'] = $localConnection->goQuery($sql['orden_empleados']['revision']);
    if ($object['empleados']['revision'] == null) {
      $object['empleados']['revision'] = '';
    }

    $object['empleados']['responsable'] = $localConnection->goQuery($sql['orden_empleados']['responsable']);
    if ($object['empleados']['responsable'] == null) {
      $object['empleados']['responsable'] = '';
    }

    $object['empleados']['diseno'] = $localConnection->goQuery($sql['orden_empleados']['diseno']);
    if ($object['empleados']['diseno'] == null) {
      $object['empleados']['diseno'] = '';
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN PPARA EL ABONO
  $app->get('/ordenes/abono/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = 'SELECT a.id_orden, SUM(a.abono) abono, SUM(a.descuento) descuento, b.pago_total total, SUM(a.abono) + SUM(a.descuento) total_abono_descuento, a.detalle, a.moment  FROM abonos a JOIN ordenes b ON a.id_orden = b._id WHERE a.id_orden = ' . $args['id'];
    $datosAbono = $localConnection->goQuery($sql);

    $object['sql'] = $sql;
    $object['data'] = $datosAbono[0];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PASARELAS DE PAGO
  $app->get('/metodos-de-pago', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $object['data'] = $woo->getPG();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // VERIFICAR SI LA ORDEN SE PUEDE EDITAR DESDE COMERCIALIZACION
  $app->get('/ordenes/verificar-edición/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT paso  FROM lotes WHERE id_orden = ' . $args['id'];
    $datosAbono = $localConnection->goQuery($sql);
    $object = $datosAbono[0];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/orden/abono', function (Request $request, Response $response, $args) {
    $datosAbono = $request->getParsedBody();
    $localConnection = new LocalDB();

    // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
    $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $id_vendedor = $localConnection->goQuery($sql)[0]['responsable'];

    // BUSCAR COMISION DEL VENDEDOR
    $sql = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $id_vendedor;
    $respComision = $localConnection->goQuery($sql)[0];

    if ($respComision['comision_tipo'] === 'porcentaje') {
      $comision = floatval($respComision['comision_porcentaje']);
    } else {
      $comisionFloat = floatval($respComision['comision']);
      $comision = number_format($comisionFloat, 2);
    }
    $object['sql'] = $sql;
    $object['comision'] = $comision;

    /*  $localConnection->disconnect();

     $response->getBody()->write(json_encode($floatValue), JSON_NUMERIC_CHECK);
     return $response
         ->withHeader('Content-Type', 'application/json')
         ->withStatus(200); */

    // ******************************************

    // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
    $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $id_vendedor = $localConnection->goQuery($sql)[0]['responsable'];

    $sql = 'SELECT pago_abono FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $primerAbono = $localConnection->goQuery($sql);
    $totalAbono = floatval($datosAbono['abono']);

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    $detalleAbono = isset($datosAbono['tipoAbono']) ? $datosAbono['tipoAbono'] : '';

    $values = "'" . $now . "',";
    $values .= "'" . $datosAbono['id'] . "',";
    $values .= "'" . $totalAbono . "',";
    $values .= "'" . $datosAbono['descuento'] . "',";
    $values .= "'" . $datosAbono['empleado'] . "',";
    $values .= "'" . $detalleAbono . "'";

    $sql = 'INSERT INTO abonos(moment, id_orden, abono, descuento, id_empleado, detalle) VALUES (' . $values . ')';
    $data = $localConnection->goQuery($sql);

    // INSERTAR EN PAGOS_DESCUENTOS SI HAY DESCUENTO
    if (floatval($datosAbono['descuento']) > 0) {
      $sql_last_abono = "SELECT MAX(_id) as last_id FROM abonos WHERE id_orden = {$datosAbono['id']}";
      $res_last_abono = $localConnection->goQuery($sql_last_abono);
      $id_abono_creado = $res_last_abono[0]['last_id'];

      $detalleDescuento = isset($datosAbono['descuentoDetalle']) ? $datosAbono['descuentoDetalle'] : 'Descuento en abono';

      if ($id_abono_creado) {
        $sql_pagos_descuentos = "INSERT INTO pagos_descuentos (id_pago, monto, descripcion) VALUES ({$id_abono_creado}, {$datosAbono['descuento']}, '" . addslashes($detalleDescuento) . "')";
        $localConnection->goQuery($sql_pagos_descuentos);
        $object['sql_pagos_descuentos'] = $sql_pagos_descuentos;
      }
    }

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql_metodos_pago = '';
    if (intval($datosAbono['montoDolaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Efectivo', '" . $datosAbono['montoDolaresEfectivo'] . "', '1');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoDolaresEfectivo'] . "', 'Dólares', 1, '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoDolaresZelle']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleZelle'] . "',  '" . $datosAbono['id'] . "', 'Dólares', 'Zelle', '" . $datosAbono['montoDolaresZelle'] . "', '1');";
    }

    if (intval($datosAbono['montoDolaresPanama']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (detalle, tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePanama'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Panamá', '" . $datosAbono['montoDolaresPanama'] . "', '1');";
    }

    if (intval($datosAbono['montoPesosEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Efectivo', '" . $datosAbono['montoPesosEfectivo'] . "', '" . $datosAbono['tasa_peso'] . "');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoPesosEfectivo'] . "', 'Pesos', '" . $datosAbono['tasa_peso'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoPesosTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePesosTransferencia'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Transferencia', '" . $datosAbono['montoPesosTransferencia'] . "', '" . $datosAbono['tasa_peso'] . "');";
    }

    if (intval($datosAbono['montoBolivaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Efectivo', '" . $datosAbono['montoBolivaresEfectivo'] . "', '" . $datosAbono['tasa_dolar'] . "');";

      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $datosAbono['tasa_dolar'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoBolivaresPunto']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Punto', '" . $datosAbono['montoBolivaresPunto'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    if (intval($datosAbono['montoBolivaresPagomovil']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePagomovil'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Pagomovil', '" . $datosAbono['montoBolivaresPagomovil'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    if (intval($datosAbono['montoBolivaresTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleBolivaresTransferencia'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Transferencia', '" . $datosAbono['montoBolivaresTransferencia'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    if (!empty($sql_metodos_pago)) {
      $object['metodos_pago'] = $localConnection->goQuery($sql_metodos_pago);
    }

    // ACTUALIZAR TOTALES EN ORDENES
    $abono_val = floatval($datosAbono['abono']);
    $descuento_val = floatval($datosAbono['descuento']);

    if ($abono_val > 0 || $descuento_val > 0) {
      $sql_update_totales = "UPDATE ordenes SET 
            pago_abono = pago_abono + {$abono_val},
            pago_descuento = pago_descuento + {$descuento_val}
            WHERE _id = " . $datosAbono['id'];
      $localConnection->goQuery($sql_update_totales);
      $object['sql_update_totales'] = $sql_update_totales;
    }

    // OBTENER ULTIMO DE LA TABLA metodos_de_pago
    $sql_max_id = 'SELECT MAX(_id) last_id FROM metodos_de_pago';
    $last_id_pago = $localConnection->goQuery($sql_max_id)[0]['last_id'];

    // GUARDAR PAGO
    $comision_vendedor = number_format((floatval($datosAbono['abono']) * $comision / 100), 2);
    $sql = "INSERT INTO pagos (detalle, estatus, monto_pago, id_empleado, id_orden, id_metodos_de_pago) VALUES ('Comercialización', 'aprobado', " . $comision_vendedor . ', ' . $id_vendedor . ', ' . $datosAbono['id'] . ', ' . $last_id_pago . ')';

    $object['response_SET_pago'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR OBSERVACIONES DESDE EDITAR EN COMERCIALIZACION
  $app->post('/orden/edit/obs', function (Request $request, Response $response, $args) {
    $datosObs = $request->getParsedBody();
    $localConnection = new LocalDB();

    // $sql = "UPDATE ordenes SET observaciones = '" . $datosObs["obs"] . "'  WHERE _id = " . $datosObs["id"];
    $sql = "UPDATE ordenes SET observaciones = 'Editada sin concentimiento por " . $datosObs['empleado'] . "'  WHERE _id = " . $datosObs['id'];
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR DETALLES DEL ABONO
  $app->get('/ordenes/abono-detale/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'moment';
    $object['fields'][0]['label'] = 'Fecha y hora';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'abono';
    $object['fields'][1]['label'] = 'Abono';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'descuento';
    $object['fields'][2]['label'] = 'Descuento';
    $object['fields'][2]['sortable'] = true;
    //  Verificar existencia de la orden
    $sql = 'SELECT _id id_abono, abono, descuento, moment FROM abonos  WHERE id_orden = ' . $args['id'] . ' GROUP BY _id, id_orden';
    $datosAbono = $localConnection->goQuery($sql);
    $object['items'] = $datosAbono;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // REPORTE PAGOS DISEÑADORES
  $app->get('/reportes/resumen/disenadores/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT departamento FROM departamentos WHERE _id = ' . $args['id_departamento'];
    $departamento = $localConnection->goQuery($sql);
    $object['departamento'] = $departamento[0]['departamento'];

    $sql = "SELECT
                a._id id_revision,
                a.id_orden,
                (SELECT product FROM products WHERE _id = a.id_product) producto,
                b.monto_pago,
                '{$object['departamento']}' departamento,
                b.fecha_pago,
                'terminada' progreso
            FROM
                revisiones a
            JOIN pagos b ON b.id_orden = a.id_orden
            JOIN ordenes c ON c._id = a.id_orden AND c.status NOT LIKE 'entregada' AND c.status NOT LIKE 'cancelada' AND c.status NOT LIKE 'terminada' 
            WHERE b.id_empleado = {$args['id_empleado']} AND b.fecha_pago IS NOT NULL
        ";
    $object['ordenes_terminadas'] = $localConnection->goQuery($sql);

    $sql = "SELECT
            a._id AS id_revision,
            a.id_orden,
            p.product AS producto, -- Columna traída directamente del JOIN
            'Diseño' AS departamento
        FROM
            revisiones a
        -- Unir con productos para obtener el nombre de forma eficiente
        LEFT JOIN products p ON p._id = a.id_product
        -- Unir con pagos para poder filtrar por empleado
        JOIN pagos b ON b.id_orden = a.id_orden
        -- Unir con órdenes para filtrar por su estado
        JOIN ordenes c ON c._id = a.id_orden
        WHERE 
            -- Condición principal sobre el empleado
            b.id_empleado = {$args['id_empleado']}
            -- Condición sobre el estado de la orden, simplificada con NOT IN
            AND c.status NOT IN ('entregada', 'cancelada', 'terminada');
        ";
    $object['ordenes_pendientes'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE PAGOS DE EMPLEADOS
  $app->get('/reportes/resumen/empleados/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT departamento FROM departamentos WHERE _id = ' . $args['id_departamento'];
    $departamento = $localConnection->goQuery($sql);
    $object['departamento'] = $departamento[0]['departamento'];

    $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                e.product,
                (SELECT cliente_nombre FROM ordenes WHERE _id = a.id_orden) cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = a.id_orden) total_productos,
                d.comision_tipo,
                -- d.monto_pago,
                d.monto_pago,
                TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) - c.tiempo) rendimiento,
                b.cantidad unidades,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                b.id_woo id_producto,
                e.product,
                'EficienciaInsumos' eficiencia_insumos,
                b.talla
            FROM
                lotes_detalles_empleados_asignados a
            -- RIGHT JOIN ordenes ord ON ord._id = a.id_orden
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            JOIN pagos d ON d.id_lotes_detalles = a._id -- DIVIDIR ENTRE CANTIDAD DE EMPLEADOS ASIGNADOS
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']} ORDER BY a.id_orden ASC
            
        ";
    $object['sql_terminadas'] = $sql;

    $pagos = $localConnection->goQuery($sql);
    $object['ordenes_terminadas'] = $pagos;

    // ORDENES PENDIENTES  ((SUM(c.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_variable,
    $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                ord.cliente_nombre cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                d.comision_tipo,
                TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) - c.tiempo) rendimiento,
                b.cantidad unidades,
                SUM(b.cantidad) total_productos,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                b.id_woo id_producto,
                e.product,
                b.talla,
                ((SUM(b.cantidad) * e.comision)) AS total_comision_variable,
                ((SUM(b.cantidad) * d.comision)) AS total_comision_fija
                -- ((SUM(b.cantidad) * e.comision) * a.procentaje_comision / 100) AS total_comision_variable,
                -- ((SUM(b.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_fija
            FROM
                lotes_detalles_empleados_asignados a
            JOIN ordenes ord ON ord._id = a.id_orden
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            LEFT JOIN api_empresas.empresas_usuarios d ON d.id_usuario = a.id_empleado
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = ord._id
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']} AND a.progreso != 'terminada' AND (ord.status LIKE 'En espera' OR ord.status LIKE 'activa' OR ord.status LIKE 'pausada') AND e.fisico = 1
            GROUP BY a.id_orden ORDER BY ofo.orden_fila ASC, a.id_orden DESC, a.progreso ASC
        ";
    $pendientes = $localConnection->goQuery($sql);
    $object['sql_pendientes'] = $sql;
    $object['ordenes_pendientes'] = $pendientes;

    // ORDENES PARA CALCULO DE TIEMPO
    $sql = "SELECT 
            y.id_orden,
            MAX(ofo.orden_fila) AS orden_fila,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = y.id_orden AND id_empleado = y.id_empleado) AS extra,
            (SELECT COUNT(_id) FROM reposiciones WHERE id_departamento = 4 AND id_empleado = 20 AND terminada = 0 AND id_orden = y.id_orden) AS en_reposiciones,
            (SELECT COUNT(_id) FROM tintas WHERE id_orden = y.id_orden) AS en_tintas,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = y.id_orden AND id_empleado = 20) AS en_inv_mov,
            (SELECT valor_inicial FROM inventario_movimientos WHERE id_orden = y.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_inicial,
            (SELECT valor_final FROM inventario_movimientos WHERE id_orden = y.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_final,
            MAX(c.prioridad) AS prioridad,
            MAX(z.unidades_produccion) AS unidades_solicitadas,
            SUM(a.cantidad) AS unidades,
            SUM(a.cantidad) AS piezas_actuales,
            MAX(y.fecha_inicio) AS fecha_inicio,
            MAX(y.fecha_terminado) AS fecha_terminado,
            MAX(DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y')) AS fecha_entrega,
            MAX(y._id) AS lotes_detalles_empleados_asignados,
            y.id_departamento,
            (SELECT MIN(dep.orden_proceso) FROM lotes_detalles_empleados_asignados ldea JOIN departamentos dep ON ldea.id_departamento = dep._id WHERE ldea.id_orden = y.id_orden) AS orden_proceso_min,
            (SELECT orden_proceso FROM departamentos WHERE _id = 4) AS orden_proceso_departamento,            
            (SELECT orden_proceso FROM departamentos WHERE _id = MAX(c.id_departamento_actual)) AS orden_proceso,
            MAX(c.id_departamento_actual) AS id_departamento_actual,
            y.id_orden AS orden,
            GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS producto,
            y.id_empleado,
            MAX(x.detalle) AS detalle_reposicion,
            GROUP_CONCAT(DISTINCT (SELECT nombre FROM sizes WHERE _id = a.id_size) SEPARATOR ', ') AS talla,
            GROUP_CONCAT(DISTINCT a.corte SEPARATOR ', ') AS corte,
            GROUP_CONCAT(DISTINCT a.tela SEPARATOR ', ') AS tela,
            MAX(tp.tiempo) AS tiempo_produccion,
            MAX(y.procentaje_comision) AS procentaje_comision,
            MAX(c.paso) AS paso,
            MAX(d.status) AS status,
            MAX(d.fecha_entrega) AS fecha_enrega_raw,
            MAX(d.fecha_entrega) AS fecha_enrega_orden,
            MAX(y.progreso) AS progreso,
            NULL AS detalles_revision
        FROM
            lotes_detalles_empleados_asignados y
            JOIN ordenes_productos a ON y.id_orden = a.id_orden
            JOIN ordenes d ON a.id_orden = d._id
            JOIN products p ON p._id = a.id_woo
            JOIN products_tiempos_de_produccion tp ON tp.id_product = p._id AND tp.id_departamento = 4
            LEFT JOIN lotes c ON c.id_orden = y.id_orden
            LEFT JOIN lotes_historico_solicitadas z ON z.id_orden = a.id_orden
            LEFT JOIN reposiciones x ON x.id_orden = d._id AND x.id_empleado = y.id_empleado AND x.id_ordenes_productos = a._id
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = d._id
        -- ============================ WHERE CORREGIDO Y ALINEADO ============================
        WHERE  
            (y.id_empleado = 20)
            AND (y.id_departamento = 4)
            -- Se eliminan los filtros extra de 'status' y 'fisico' para que la lógica de filtrado sea idéntica a la de la consulta de 'ordenes pendientes'.
        -- ========================================================================
        GROUP BY
            y.id_orden, y.id_empleado, y.id_departamento
        ORDER BY
            orden_fila ASC,
            y.id_orden DESC;
        ";

    $ordenes = $localConnection->goQuery($sql);
    $object['sql_ordenes'] = $sql;
    $object['ordenes'] = $ordenes;

    // EFICIENCIA DE INSUMOS
    $sql = "SELECT
          est.id_orden,
          est.id_empleado,
          est.id_departamento,
          est.nombre_empleado,
          est.nombre_departamento,
          est.fecha_asignacion,
          est.nombre_producto,
          est.talla,
          est.nombre_insumo,
          est.cantidad_piezas,
          est.consumo_estimado_total,
          COALESCE(consumo_r.consumo_real_total, 0) AS consumo_real_total,
          (COALESCE(consumo_r.consumo_real_total, 0) - COALESCE(est.consumo_estimado_total, 0)) AS diferencia
      FROM
          (
              SELECT
                  op.id_orden,
                  ldea.id_empleado,
                  ldea.id_departamento,
                  p.product AS nombre_producto,
                  s.nombre AS talla,
                  cip.nombre AS nombre_insumo,
                  SUM(op.cantidad) AS cantidad_piezas,
                  SUM(op.cantidad * COALESCE(pia.cantidad, 0)) AS consumo_estimado_total,
                  eu.nombre AS nombre_empleado,
                  dep.departamento AS nombre_departamento,
                  ldea.fecha_inicio AS fecha_asignacion
              FROM
                  lotes_detalles_empleados_asignados ldea
              JOIN ordenes_productos op ON ldea.id_orden = op.id_orden
              JOIN products p ON op.id_woo = p._id
              LEFT JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario
              LEFT JOIN departamentos dep ON ldea.id_departamento = dep._id
              LEFT JOIN product_insumos_asignados pia ON op.id_woo = pia.id_product 
                                                      AND op.id_size = pia.id_talla
                                                      AND ldea.id_departamento = pia.id_departamento
              LEFT JOIN sizes s ON op.id_size = s._id
              LEFT JOIN catalogo_insumos_productos cip ON pia.id_catalogo_insumos_productos = cip._id
              WHERE
                  -- ldea.id_orden = 2008
                  ldea.id_empleado = {$args['id_empleado']}
                  AND ldea.id_departamento = {$args['id_departamento']}
              GROUP BY
                  ldea.id_orden, ldea.id_empleado, ldea.id_departamento,
                  p.product, s.nombre, cip.nombre,
                  eu.nombre, dep.departamento, ldea.fecha_inicio
          ) AS est
      LEFT JOIN
          (
              SELECT
                  id_orden,
                  id_departamento,
                  id_empleado,
                  -- Agrupamos todo el consumo real, sin importar el insumo específico
                  SUM(valor_inicial - valor_final) AS consumo_real_total
              FROM
                  inventario_movimientos
              WHERE
                  -- id_orden = 2008
                  id_empleado = {$args['id_empleado']}
                  AND id_departamento = {$args['id_departamento']}
              GROUP BY
                  -- Quitamos id_catalogo_insumos_prodcutos del GROUP BY
                  id_orden, id_departamento, id_empleado
          ) AS consumo_r ON est.id_orden = consumo_r.id_orden
                        AND est.id_departamento = consumo_r.id_departamento
                        AND est.id_empleado = consumo_r.id_empleado;
                        -- Se elimina la condición de 'id_catalogo_insumos_productos' del JOIN final
      ";
    $reficiencia_insumos = $localConnection->goQuery($sql);
    $object['sql_eficiencia_insumos'] = $sql;
    $object['eficiencia_inusmos'] = $reficiencia_insumos;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
    /* if ($departamento === 'Costura') {
    $sql = "SELECT
        a._id id_lote_detalles,
        a.id_orden,
        a.id_woo,
        a.id_orden detalle,
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        b.departamento,
        a.progreso,
        c.name producto,
        c.talla,
        c.corte,
        c.tela,
        c.cantidad,
        b.comision,
        d.fecha_pago,
        d.monto_pago,
        0 calculo_pago
        FROM
        lotes_detalles a
        LEFT JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
        LEFT JOIN pagos d ON d.id_lotes_detalles = a._id
        WHERE
        d.fecha_pago IS NULL AND
        d.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

        $ordenes = $localConnection->goQuery($sql);
        $object['ordenes'] = $ordenes;

        // Buscar comision de productos en woocommerce y recalcular `calculo_pago`
        $tmpOrdenes = null;
        foreach ($object['ordenes'] as $key => $orden) {
            $tmpOrdenes[$key] = $orden;
            $idwc = intval($orden['id_woo']);
            $woo = new WooMe();
            $producto = $woo->getProductById($idwc);

            if (isset($producto->attributes[0]->options)) {
                $comision = $producto->attributes[0]->options[0];
                $tmpOrdenes[$key]['comision'] = $producto->attributes[0]->options[0];
                $comision = floatval($comision) * intval($orden['cantidad']);
                $tmpOrdenes[$key]['calculo_pago'] = $comision;
            } else {
                $comision = 0;
            }
        }
        $object['ordenes'] = $tmpOrdenes;
        $object['ordenes_semana'] = $tmpOrdenes;
    }

    if ($departamento === 'Corte' || $departamento === 'Estampado' || $departamento === 'Limpieza' || $departamento === 'Revisión' || $departamento === 'Impresión') {
        // REporte todo lo no pagado
        $sql = "SELECT
            a._id id_lotes_detalles,
            a.id_orden,
            a.id_orden detalle,
            a.fecha_inicio fecha_inicio_ts,
            a.fecha_terminado fecha_terminado_ts,
            DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
            DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
            DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
            DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
            a.unidades_solicitadas cantidad,
            b.name producto,
            FORMAT(b.cantidad * c.comision, 2) AS calculo_pago
            FROM
            lotes_detalles a
            JOIN api_empresas.empresas_usuarios c ON
            a.id_empleado = c.id_usuario
            JOIN ordenes_productos b ON
            a.id_ordenes_productos = b._id
            JOIN pagos d ON
            d.id_lotes_detalles = a._id
            WHERE  d.fecha_pago IS NULL AND
            a.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

        $ordenes = $localConnection->goQuery($sql);
        $object['ordenes'] = $ordenes;

        $sql = "SELECT
            a._id id_lote_detalles,
            a.id_orden,
            a.id_orden detalle,
            a.fecha_inicio fecha_inicio_ts,
            a.fecha_terminado fecha_terminado_ts,
            DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
            DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
            DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
            DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
            b.departamento,
            a.progreso,
            c.name producto,
            c.talla,
            c.corte,
            c.tela,
            c.cantidad,
            b.comision,
            d.fecha_pago,
            d.monto_pago,
            FORMAT(c.cantidad * b.comision, 2) AS calculo_pago

            FROM
            pagos d
            LEFT JOIN lotes_detalles a ON d.id_lotes_detalles = a._id
            JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
            JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
            WHERE
            b.id_usuario = " . $args['id_empleado'] . ' AND  d.fecha_pago IS NULL ORDER BY a.id_orden ASC
        ';
        $ordenes = $localConnection->goQuery($sql);
        $object['ordenes_semana'] = $ordenes;
    }

    if ($departamento === 'Comercialización' || $departamento === 'Administración') {
        $sql = "SELECT
            a._id id_pagos,
            a.id_orden,
            DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
            b.tipo_de_pago,
            a.monto_pago monto_pago
            FROM pagos a
            LEFT JOIN metodos_de_pago b ON b._id = a.id_metodos_de_pago
            WHERE a.id_empleado = " . $args['id_empleado'] . ' AND a.fecha_pago IS NULL
        ';

        $ordenes = $localConnection->goQuery($sql);
        $object['ordenes_semana'] = $ordenes;
    }

    if ($departamento === 'Diseño') {
        $sql = "SELECT
            a._id id_pago,
            a.id_orden,
            a.cantidad,
            a.fecha_pago fecha_terminado,
            a.detalle producto,
            b.tipo tipo_arreglo,
            a.monto_pago calculo_pago,
            CASE
            WHEN b.tipo IS NOT NULL THEN b.tipo
            ELSE 'Diseño'
            END AS tipo
            FROM
            pagos a
            LEFT JOIN disenos_ajustes_y_personalizaciones b
            ON b.id_orden = a.id_orden
            WHERE
            a.fecha_pago IS NULL AND a.id_empleado = " . $args['id_empleado'] . '
            GROUP BY a._id
            ORDER BY
            a._id
            DESC;
        ';

        $ordenes = $localConnection->goQuery($sql);

        $object['ordenes'] = $ordenes;
        $object['ordenes_semana'] = $ordenes;
    } */
  });

  // REPORTE SEMANAL DE PAGOS Y ABONOS
  $app->get('/comercializacion/reportes/pagos-abonos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'id_orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'moment';
    $object['fields'][1]['label'] = 'Fecha y hora';

    $object['fields'][2]['key'] = 'abono';
    $object['fields'][2]['label'] = 'Abono';

    $object['fields'][3]['key'] = 'descuento';
    $object['fields'][3]['label'] = 'Descuento';

    $sql = 'SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a  WHERE YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC';
    $datosAbono = $localConnection->goQuery($sql);
    $object['items'] = $datosAbono;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN POR ID

  $app->get('/ordenes/reporte/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
    $resp = $localConnection->goQuery($sql);

    if (!$resp) {
      $object = $resp;
    } else {
      // Buscar datos del cliente en Woocommerce ...
      $sql = 'SELECT id_wp FROM ordenes WHERE _id  = ' . $id;
      $id_wp = $localConnection->goQuery($sql);
      $id_customer = $id_wp[0]['id_wp'];

      $woo = new WooMe();
      $object = array();

      // Buscar datos de la orden
      $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, 'TREAR DESDE EL `ENDPOINT` DEDICADO' observaciones, a.pago_total, a.pago_abono FROM ordenes a  WHERE _id =  " . $id;
      $object['orden'] = $localConnection->goQuery($sql);

      // Buscar datos del diseño
      // $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
      $sql = 'SELECT a._id id_diseno, a.tipo, a.id_orden, b.revision revision FROM disenos a JOIN revisiones b ON b.id_diseno = a._id WHERE a.id_orden =' . $id;
      $object['diseno'] = $localConnection->goQuery($sql);

      // Buscar datos del cliente
      $object['customer'][0] = $woo->getCustomerById($id_customer);

      // Buscar datos de productos
      $sql = 'SELECT _id, name, id_woo cod, cantidad, talla, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = ' . $id;
      $object['productos'] = $localConnection->goQuery($sql);
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // BUSCAR ORDENES QUE NO TIENEN NINGUN EMPLEADO ASIGNADO

  $app->get('/ordenes/sin-asignacion/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = "SELECT
            a._id id_orden,
            cliente_nombre
        FROM
            ordenes a
        LEFT JOIN
            lotes_detalles_empleados_asignados b ON b.id_orden = a._id
        WHERE
            b.id_orden IS NULL 
            AND (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') 
            AND a.responsable = {$args['id_vendedor']}
    ";

    $resp = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($resp));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN POR ID
  // Función para obtener la respuesta de /buscar
  function obtenerRespuestaBuscar($id, $email = null): array
  {
    $object = array();
    $localConnection = new LocalDB();

    // Verificar existencia de la orden
    $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
    $resp = $localConnection->goQuery($sql);

    if (!$resp) {
      $object = $resp;
    } else {
      // Buscar datos del cliente en Woocommerce
      $sql = 'SELECT id_wp FROM ordenes WHERE _id = ' . $id;
      $id_wp = $localConnection->goQuery($sql);
      $id_customer = $id_wp[0]['id_wp'];
      $id_customer = $id_wp[0]['id_wp'];

      $woo = new WooMe();
      $data = $woo->getCustomerByIdWP($id_customer);
      $customer = json_decode(json_encode($data), true);
      $object['customer']['data'] = $customer;

      $object['customer']['nombre'] = ($customer[0]['billing_first_name'] ?? '') . ' ' . ($customer[0]['billing_last_name'] ?? '');
      $object['customer']['direccion'] = $customer[0]['billing_address_1'] ?? '';
      $object['customer']['email'] = $customer[0]['billing_email'] ?? '';
      $object['customer']['cedula'] = $customer[0]['billing_postcode'] ?? '';
      $object['customer']['telefono'] = $customer[0]['billing_phone'] ?? '';

      // Buscar datos de la orden!
      // CONSULTA CORREGIDA: Se eliminaron las líneas comentadas que causaban el error.
      $sql_orden = 'SELECT
            a._id,
            a.status,
            a.cliente_nombre,
            c.nombre AS vendedor,
            b.cedula,
            a.fecha_inicio,
            a.fecha_entrega,
            a.pago_total
          FROM
            ordenes a
          JOIN customers b ON a.id_wp = b._id
          LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = a.responsable
          WHERE
            a._id = ' . $id;
      $object['orden'] = $localConnection->goQuery($sql_orden);

      // --- INICIO: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---
      $sql_abonos = 'SELECT SUM(abono) AS total_abonos, SUM(descuento) AS total_descuentos FROM abonos WHERE id_orden = ' . $id;
      $totales_abonos = $localConnection->goQuery($sql_abonos);

      if (isset($object['orden'][0])) {
        $object['orden'][0]['pago_abono'] = (float) ($totales_abonos[0]['total_abonos'] ?? 0);
        $object['orden'][0]['pago_descuento'] = (float) ($totales_abonos[0]['total_descuentos'] ?? 0);
      }
      // --- FIN: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---

      // Buscar datos del diseño
      $sql = 'SELECT tipo FROM disenos WHERE id_orden = ' . $id;
      $object['diseno'] = $localConnection->goQuery($sql);
      if (empty($object['diseno'])) {
        $object['diseno'][]['tipo'] = 'Ninguno';
      }

      // Buscar datos de productos
      $sql = 'SELECT
            op._id,
            op.name,
            pr.sku AS sku,
            pr._id AS cod,
            pr.fisico AS producto_fisico,
            op.id_woo,
            op.cantidad,
            op.id_size AS id_talla,
            s.nombre AS talla,
            op.id_tela,
            prices_json.prices, -- Aquí usamos el alias de la subconsulta derivada
            op.tela,
            op.id_tela,
            op.corte,
            op.precio_unitario AS precio,
            (SELECT attribute_name FROM products_attributes WHERE _id = op.id_products_attributes) atributo_nombre,
            op.id_products_attributes AS atributo -- Añadir el atributo del producto
          FROM
            ordenes_productos op
          LEFT JOIN
            products pr ON pr._id = op.id_woo
          LEFT JOIN
            sizes s ON s._id = op.id_size -- Unir directamente con sizes para la talla
          LEFT JOIN (
            -- Subconsulta derivada para agrupar los precios por producto
            SELECT
              pp.id_product AS product_id,
              CONCAT(
                "[",
                GROUP_CONCAT(
                  JSON_OBJECT(
                    "id",
                    pp._id,
                    "price",
                    pp.price,
                    "description",
                    pp.descripcion
                  )
                ),
                "]"
              ) AS prices
            FROM
              products_prices pp
            GROUP BY
              pp.id_product
          ) AS prices_json ON prices_json.product_id = pr._id -- Unir con la tabla de productos
          WHERE
            op.id_orden = ' . $id;

      $tmpProducts = $localConnection->goQuery($sql);

      // ATRIBUTOS DE PRODUCTOS
      $sqlAttr = "SELECT id_product, attribute_value, attribute_price FROM products_attributes_values WHERE id_orden = {$id}";
      $object['atributos_prodcutos'] = $localConnection->goQuery($sqlAttr);

      // PARSEAR PRODUCTOS
      $data = [];
      $key = 0;
      foreach ($tmpProducts as $product) {
        $data[$key]['_id'] = intval($product['_id']);
        $data[$key]['name'] = $product['name'];
        $data[$key]['cod'] = $product['cod'];
        $data[$key]['producto_fisico'] = $product['producto_fisico'];
        $data[$key]['id_woo'] = $product['id_woo'];
        $data[$key]['cantidad'] = $product['cantidad'];
        $data[$key]['id_tela'] = $product['id_tela'];
        $data[$key]['id_talla'] = $product['id_talla'];
        $data[$key]['talla'] = $product['talla'];
        $data[$key]['tela'] = $product['tela'];
        $data[$key]['corte'] = $product['corte'];
        $data[$key]['precio'] = $product['precio'];
        $data[$key]['atributo'] = $product['atributo'];
        $data[$key]['atributo_nombre'] = $product['atributo_nombre'];
        $data[$key]['prices'] = json_decode($product['prices']);
        $key++;
      }
      $object['productos'] = $data;
      $object['productos_count'] = count($object['productos']);
      $object['conterwoo'] = count($object['productos']);
    }

    $localConnection->disconnect();

    $contentType = 'application/json';
    return array('object' => $object, 'contentType' => $contentType);
  }

  $app->get('/buscar/{id}[/{email}]', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $email = isset($args['email']) ? $args['email'] : null;

    $result = obtenerRespuestaBuscar($id, $email);
    $response->getBody()->write(json_encode($result['object'], JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', $result['contentType'])
      ->withStatus(200);
  });

  /*$app->get('/buscar_old/{id}[/{email}]', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $id = $args["id"];
        $object = array();

//  Verificar existencia de la orden
        $sql = "SELECT _id FROM ordenes WHERE _id=" . $id;
        $resp = $localConnection->goQuery($sql);

        if (!$resp) {
            $object = $resp;
            } else {
// Buscar datos del cliente en Woocommerce ...
                $sql = "SELECT id_wp FROM ordenes WHERE _id  = " . $id;
                $id_wp = $localConnection->goQuery($sql);
                $id_customer = $id_wp[0]["id_wp"];

                $object["id_customer"] = $id_customer;

                $woo = new WooMe();
// Buscar datos del cliente
// $object["customer"][0] = $woo->getCustomerById($id_customer);
                $data = $woo->getCustomerById($id_customer);
                $customer = json_decode(json_encode($data), true);

                $object["customer"]["nombre"] = $customer["first_name"] . " " . $customer["last_name"];
                $object["customer"]["direccion"] = $customer["billing"]["address_1"];
                $object["customer"]["email"] = $customer["billing"]["email"];
                $object["customer"]["cedula"] = $customer["billing"]["postcode"];
                $object["customer"]["telefono"] = $customer["billing"]["phone"];

// Buscar datos de la orden
                $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono, a.pago_descuento FROM ordenes a  WHERE _id =  " . $id;
                $object["orden"] = $localConnection->goQuery($sql);

// Buscar datos del diseño
                $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
                $object['diseno'] = $localConnection->goQuery($sql);
                if (empty($object['diseno'])) {
                    $object['diseno'][]['tipo'] = "Ninguno";
                }

// Buscar datos de productos
                $sql = "SELECT _id, name, id_woo cod, cantidad, talla, tela, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = " . $id;
                $object['productos'] = $localConnection->goQuery($sql);

// Crear estructura del email de bienvenida:
                if (isset($args['email'])) {
                    $emailCliente = new EmailClienteBienvenida($object);
                    $email = $emailCliente->obtenerContenido();
                    $object = $email;
// $object = json_encode($email);
                    $contentType = 'text/html';
                    } else {
                        $object = json_encode($object);
                        $contentType = 'application/json';
                    }
                }

                $localConnection->disconnect();

                $response->getBody()->write(json_encode($object));
                return $response
                ->withHeader('Content-Type', $contentType)
                ->withStatus(200);
            });*/

  $app->get('/ruta2', function (Request $request, Response $response, array $args) {
    // Llamamos a la función que encapsula la lógica de /buscar
    $resultBuscar = obtenerRespuestaBuscar(303, 'true');

    // Modificamos la respuesta si es necesario
    /* $resultBuscar['object'] = json_decode($resultBuscar['object'], true);
        $resultBuscar['object']['modificado_en_ruta2'] = true;
        $resultBuscar['object'] = json_encode($resultBuscar['object']); */

    $response->getBody()->write($resultBuscar['object']);
    return $response
      ->withHeader('Content-Type', $resultBuscar['contentType'])
      ->withStatus(200);
  });

  // ORDENES ACTIVAS, TERMINADAS Y PAUSADAS
  $app->get('/comercializacion/ordenes/reporte', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // BUSCAR ORENES EN CURSO
    $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'activa' OR status = 'pausada' OR status = 'En espera' OR status = 'terminada'  ORDER BY _id DESC";
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES TERMNADAS Y NO ENTREGADAS
  $app->get('/comercializacion/ordenes/reporte/terminadas/{rango}', function (Request $request, Response $response, array $args) {
    $object['rango'] = $args['rango'];
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime($args['rango']);
    $now = $myDate->today();
    $before = $myDate->before();
    $object['moment-today'] = $now;
    $object['moment-before'] = $before;
    $momentInit = $now;
    $momentEnd = $before;

    // BUSCAR ORENES EN CURSO
    $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'terminada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES ENTREGADAS
  $app->get('/comercializacion/ordenes/reporte/entregadas/{rango}', function (Request $request, Response $response, array $args) {
    $object['rango'] = $args['rango'];
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime($args['rango']);
    $now = $myDate->today();
    $before = $myDate->before();
    $object['moment-today'] = $now;
    $object['moment-before'] = $before;
    $momentInit = $now;
    $momentEnd = $before;

    // BUSCAR ORENES EN CURSO
    // $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'entregada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
    $sql = 'SELECT _id, status, cliente_nombre, _id vinculada from ordenes ORDER BY _id ASC';

    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';

    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE VENTA AL DETAL EN LA TIENDA
  $app->post('/ordenes/nueva', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC; */
    $orderWC = 0;
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs'] ?? '') . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

    $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

    // Obtenr id de la orden creada
    $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
    $last_id = intval($last[0]['id']);
    $object['last_id'] = $last_id;

    // Guardar orden vinculada
    if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
      $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
      $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
    }

    // Crear abono inicial de la orden
    $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
    $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

    // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
    // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
    if (floatval($newJson['abono']) > 0) {
      // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
      $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
      $pago_vendedor = number_format($pago_vendedor, 2);
      $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
      $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
      $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
      /* if ($arg["sales_commission"] === true) {
                            $object['sales_commission_ISSET'][] = true;
                            } else {
                                $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                            } */
    }  /*  else {
$object['sales_commission_ISSET'][] = false;
} */

    // GUARDAR DATOS DE DISEÑO
    $sql_diseno = '';
    if ($newJson['diseno_grafico'] == 'true') {
      for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
        $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
      }
    }

    if ($newJson['diseno_modas'] == 'true') {
      for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
        $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
      }
    }

    $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));

    // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
    $sql = 'SELECT _id';

    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        /* $woo = new WooMe();
                $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                $tmp = json_decode($data_category);
                $cat_name = $tmp->name; */
        /* if ($tmp->statusCode === 500) {
                    $cat_name = "Uncatagorized";
                    } else {
                    } */

        $cat_name = 'Uncatagorized';

        $values = "'" . $now . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precioWoo'] . "',";
        $values .= "'" . $decodedObj['producto'] . "',";
        $values .= $last_id . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";
        // $values .= "'" . $tmp["->name"] . "',";

        if (isset($decodedObj['talla'])) {
          $values .= "'" . $decodedObj['talla'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['corte'])) {
          $values .= "'" . $decodedObj['corte'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['tela'])) {
          $values .= "'" . $decodedObj['tela'] . "'";
        } else {
          $values .= "''";
        }

        $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
        $object['sql_ordenes_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2);

        // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
        if ($misProductos[$i] != '') {
          $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
          $myOrder = $localConnection->goQuery($sql_order);
          $object['myOrder_sql'] = $sql_order;
          $object['myOrder'] = $myOrder;

          // Obtenr ultimo ID del producto creado
          $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
          $last_id_ordenes_productos = intval($last_prod[0]['id']);

          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
          $myWooId = intval($decodedObj['cod']);
          if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
            $sql_lote_detalles = '';
            // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
            // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
            $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
            $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
          }
        }
      }
    }

    // GUARDAR LOTE

    // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
    $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
    $resultVerify = $localConnection->goQuery($sql_verify);

    $guardarLote = true;
    if (!empty($resultVerify)) {
      // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
      if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
        $guardarLote = false;
      }
    }

    $object['guardar_en_lote'] = $guardarLote;

    if ($guardarLote) {
      $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
      $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
    }

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql_metodos_pago = '';

    if (intval($arr['montoDolaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresZelle']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresPanama']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPunto']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPagomovil']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);

    // enviar email - obtener formato
    $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    $object['resultBuscar'] = $resultBuscar['object'];

    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $args['id_orden']);
    $testResp = $msgApi->sendMessage(ID_EMPRESA, $last_id, 'welcome', $resultBuscar);
    /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
        $object["sendMail"] = $result; */

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVO PRESUPUESTO
  $app->post('/presupuesto/nuevo', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson); */
    // $object["create_product_WC"] = $orderWC;
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO presupuestos (id_wp_order, responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $orderWC . ', ' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs'] ?? '') . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

    $object['nuevo_presupuesto_response'] = json_encode($localConnection->goQuery($sql));

    // Obtenr id de la orden creada
    $last = $localConnection->goQuery('SELECT MAX(_id) id FROM presupuestos');
    $last_id = intval($last[0]['id']);
    $object['last_id'] = $last_id;

    // Guardar orden vinculada
    /* if ($arr["vinculada"] != 0 || $arr["vinculada"] != '0') {
            $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr["vinculada"] . ", " . $last_id . ")";
            $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
        } */

    // Crear abono inicial de la orden

    /*
     * $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson["abono"] . "', '" . $newJson['descuento'] . "');";
     *  $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));
     */
    // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
    // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono

    // GUARDAR DATOS DE DISEÑO
    /*  $sql_diseno = "";
         if ($newJson["diseno_grafico"] == "true") {
             for ($i = 0; $i < intval($newJson["diseno_grafico_cantidad"]); $i++) {
                 $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
             }
         }

         if ($newJson["diseno_modas"] == "true") {
             for ($i = 0; $i < intval($newJson["diseno_modas_cantidad"]); $i++) {
                 $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
             }
         }

         $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno)); */

    // GUARDAR PRODUCTOS ASOCIADOS AL PRESUPUESTO
    $sql = 'SELECT _id';

    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        $cat_name = 'Uncatagorized';

        $values = "'" . $now . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precioWoo'] . "',";
        $values .= "'" . $decodedObj['producto'] . "',";
        $values .= $last_id . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";
        // $values .= "'" . $tmp["->name"] . "',";

        if (isset($decodedObj['talla'])) {
          $values .= "'" . $decodedObj['talla'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['corte'])) {
          $values .= "'" . $decodedObj['corte'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['tela'])) {
          $values .= "'" . $decodedObj['tela'] . "'";
        } else {
          $values .= "''";
        }

        $sql2 = 'INSERT INTO presupuestos_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
        $object['sql_presupuestos_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2);
      }
    }

    // enviar email - obtener formato
    // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    // $object["resultBuscar"] = $resultBuscar["object"];
    // $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
    // $object["sendMail"] = $result;

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTUALIZR ORDEN DE LA FILA DE PRODUCCIÓN
  $app->post('/ordenes/actualizar-fila', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    $sql = 'UPDATE ordenes_fila_orden SET orden_fila = ' . $data['orden_fila'] . ' WHERE id_orden = ' . $data['id_orden'] . ';';
    $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM ordenes_fila_orden ORDER BY orden_fila ASC';
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // EDITAR UNA ORDEN EXISTENTE
  $app->post('/ordenes/nueva/custom/edit', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $localConnection = new LocalDB();
    $object = [];  // Objeto de respuesta

    // 1. OBTENER EL ID DE LA ORDEN A EDITAR. ¡ESTO ES CRÍTICO!
    // El frontend DEBE enviar 'id_orden_edit' en el payload.
    if (!isset($newJson['id_orden_edit']) || empty($newJson['id_orden_edit'])) {
      $object['response']['status'] = 'error';
      $object['response']['message'] = 'No se proporcionó el ID de la orden para editar.';
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_orden_a_editar = intval($newJson['id_orden_edit']);

    // 2. PROCESAR DATOS DEL PAYLOAD (similar al endpoint original)
    $arr = [];
    $arr['id_wp'] = $newJson['id'];
    $arr['fechaEntrega'] = $newJson['fechaEntrega'];
    $arr['obs'] = $newJson['obs'] !== null ? addslashes($newJson['obs']) : '';
    $arr['total'] = floatval($newJson['total']);  // Nuevo total recalculado en el frontend
    $arr['abono'] = floatval($newJson['abono']);  // ¡IMPORTANTE! Este debe ser SOLO el nuevo abono, no el total histórico.
    $arr['descuento'] = floatval($newJson['descuento']);  // Descuento total actualizado
    $arr['descuentoDetalle'] = $newJson['descuentoDetalle'];
    $arr['responsable'] = intval($newJson['responsable']);
    $arr['sales_commission'] = ($newJson['sales_commission'] === 'true');
    $arr['tasa_dolar'] = floatval($newJson['tasa_dolar']);
    $arr['tasa_peso'] = floatval($newJson['tasa_peso']);
    $nuevos_productos = json_decode($newJson['productos'], true);

    // 3. MANEJO DE PRODUCTOS (INSERT, UPDATE, DELETE)
    // 3.1. Obtener productos actuales de la base de datos para comparar
    $sql_productos_actuales = "SELECT _id, _id cod, cantidad, precio_unitario, talla, tela, corte, id_products_attributes FROM ordenes_productos WHERE id_orden = {$id_orden_a_editar}";
    $productos_actuales_db = $localConnection->goQuery($sql_productos_actuales);

    $map_productos_actuales = [];
    foreach ($productos_actuales_db as $p_actual) {
      // Usamos el ID del registro como clave para una búsqueda fácil
      $map_productos_actuales[$p_actual['_id']] = $p_actual;
    }

    // 3.2. Crear un mapa de los productos que llegan del frontend
    $map_productos_nuevos = [];
    foreach ($nuevos_productos as $p_nuevo) {
      // Si el producto tiene un '_id', es uno existente. Si no, es nuevo.
      if (isset($p_nuevo['_id']) && !empty($p_nuevo['_id'])) {
        $map_productos_nuevos[$p_nuevo['_id']] = $p_nuevo;
      } else {
        // Para los nuevos, los agregamos a un array separado para insertarlos luego.
        $productos_a_insertar[] = $p_nuevo;
      }
    }

    // 3.3. Identificar y ELIMINAR productos
    foreach ($map_productos_actuales as $id_actual => $p_actual) {
      if (!isset($map_productos_nuevos[$id_actual])) {
        // Este producto ya no está en la lista del frontend, hay que borrarlo.
        $sql_delete_prod = "DELETE FROM ordenes_productos WHERE _id = {$id_actual}";
        $localConnection->goQuery($sql_delete_prod);
        // También borrar sus detalles de lote
        $sql_delete_lote = "DELETE FROM lotes_detalles WHERE id_ordenes_productos = {$id_actual}";
        $localConnection->goQuery($sql_delete_lote);
      }
    }

    // 3.4. Identificar y ACTUALIZAR productos existentes
    foreach ($map_productos_nuevos as $id_nuevo => $p_nuevo) {
      if (isset($map_productos_actuales[$id_nuevo])) {
        $p_actual = $map_productos_actuales[$id_nuevo];
        // Comparamos si algo cambió para evitar updates innecesarios
        // Usamos el operador de fusión de null (??) para evitar errores de "Undefined index"
        if (
          ($p_actual['cantidad'] ?? null) != ($p_nuevo['cantidad'] ?? null) ||
          ($p_actual['precio_unitario'] ?? null) != ($p_nuevo['precio'] ?? null) ||
          ($p_actual['talla'] ?? null) != ($p_nuevo['talla'] ?? null) ||
          ($p_actual['tela'] ?? null) != ($p_nuevo['tela'] ?? null) ||
          ($p_actual['corte'] ?? null) != ($p_nuevo['corte'] ?? null) ||
          ($p_actual['id_products_attributes'] ?? null) != ($p_nuevo['atributo'] ?? null)
        ) {
          $sql_update_prod = "UPDATE ordenes_productos SET
                    cantidad = {$p_nuevo['cantidad']},
                    precio_unitario = {$p_nuevo['precio']},
                    corte = '{$p_nuevo['corte']}',
                    id_size = " . (isset($p_nuevo['talla']) && !is_null($p_nuevo['talla']) ? intval($p_nuevo['talla']) : 'NULL') . ',
                    talla = (SELECT nombre FROM sizes WHERE _id = ' . (isset($p_nuevo['talla']) && !is_null($p_nuevo['talla']) ? intval($p_nuevo['talla']) : 'NULL') . '),
                    id_tela = ' . (isset($p_nuevo['tela']) && !is_null($p_nuevo['tela']) ? intval($p_nuevo['tela']) : 'NULL') . ',
                    tela = (SELECT tela FROM catalogo_telas WHERE _id = ' . (isset($p_nuevo['tela']) && !is_null($p_nuevo['tela']) ? intval($p_nuevo['tela']) : 'NULL') . '),
                    id_products_attributes = ' . (isset($p_nuevo['atributo']) ? intval($p_nuevo['atributo']) : 'NULL') . "
                    WHERE _id = {$id_nuevo}";
          $localConnection->goQuery($sql_update_prod);
        }
      }
    }

    // 3.5. INSERTAR nuevos productos
    if (!empty($productos_a_insertar)) {
      foreach ($productos_a_insertar as $decodedObj) {
        // Reutilizamos la lógica de inserción del endpoint original
        $cat_name = 'Uncatagorized';  // Valor por defecto

        $values = "'" . date('Y-m-d H:i:s') . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precio'] . "',";  // precio_woo
        $values .= "'" . addslashes($decodedObj['producto'] ?? '') . "',";
        $values .= $id_orden_a_editar . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";

        // Talla
        if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
          $id_talla = intval($decodedObj['talla']);
          $values .= $id_talla . ',';
          $values .= "(SELECT nombre FROM sizes WHERE _id = {$id_talla}),";
        } else {
          $values .= 'NULL, NULL,';
        }

        // Corte
        $values .= (isset($decodedObj['corte']) ? "'" . $decodedObj['corte'] . "'," : "'',");

        // Tela
        if (isset($decodedObj['tela']) && !is_null($decodedObj['tela']) && $decodedObj['tela'] !== '') {
          $id_tela = intval($decodedObj['tela']);
          $values .= $id_tela . ',';
          $values .= "(SELECT tela FROM catalogo_telas WHERE _id = {$id_tela})";
        } else {
          $values .= "NULL, ''";
        }

        $id_products_attributes = (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo'])) ? intval($decodedObj['atributo']) : 'NULL';

        $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ' . $id_products_attributes . ')';

        $res_insert = $localConnection->goQuery($sql2);
        $object['sql_insert_new_product'] = $sql2;

        /* $response->getBody()->write(json_encode($res_insert));
        $localConnection->disconnect();

        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200); */

        // $last_id_ordenes_productos = $res_insert['insert_id'];

        // Lógica para insertar en lotes_detalles para el nuevo producto
        // ... (copiar la lógica de lotes_detalles del endpoint original) ...
      }
    }

    // 4. ACTUALIZAR LA ORDEN PRINCIPAL
    // Primero, obtener el abono actual para sumarle el nuevo
    $sql_abono_actual = "SELECT pago_abono FROM ordenes WHERE _id = {$id_orden_a_editar}";
    $res_abono = $localConnection->goQuery($sql_abono_actual);
    $abono_historico = $res_abono[0]['pago_abono'];
    $nuevo_abono_total = $abono_historico + $arr['abono'];

    // Se elimina el campo `observaciones` de la actualización principal
    $sql_update_orden = 'UPDATE ordenes SET
        pago_total = ' . $arr['total'] . ',
        pago_abono = ' . $nuevo_abono_total . ',
        pago_descuento = pago_descuento + ' . $arr['descuento'] . ",
        fecha_entrega = '" . $arr['fechaEntrega'] . "'
        WHERE _id = {$id_orden_a_editar}";
    $localConnection->goQuery($sql_update_orden);
    $object['sql_update_orden'] = $sql_update_orden;

    // NUEVO: Lógica para insertar o actualizar las observaciones en la tabla dedicada
    $sql_check_obs = "SELECT _id FROM ordenes_observaciones WHERE id_orden = {$id_orden_a_editar}";
    $obs_existente = $localConnection->goQuery($sql_check_obs);

    if (empty($obs_existente)) {
      $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$id_orden_a_editar}, '{$arr['obs']}')";
    } else {
      $sql_obs = "UPDATE ordenes_observaciones SET observaciones = '{$arr['obs']}' WHERE id_orden = {$id_orden_a_editar}";
    }
    $localConnection->goQuery($sql_obs);
    $object['sql_observaciones'] = $sql_obs;

    // DEFINIR FECHA ACTUAL
    $myDate = new CustomTime();
    $now = $myDate->today();

    // 5.1. REGISTRAR CAMBIOS EN DESCUENTO (Lógica simplificada: Incremental)
    $nuevo_descuento = floatval($arr['descuento']);

    if (abs($nuevo_descuento) > 0.001) {
      $detalle_descuento = isset($arr['descuentoDetalle']) ? $arr['descuentoDetalle'] : 'Ajuste de descuento en edición';

      // Insertar directamente el incremento
      $sql_insert_desc = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento, detalle) VALUES ('" . $now . "', '" . $id_orden_a_editar . "', '" . $arr['responsable'] . "', 0, '" . $nuevo_descuento . "', '" . addslashes($detalle_descuento) . "')";

      $localConnection->goQuery($sql_insert_desc);
      $object['sql_nuevo_descuento'] = $sql_insert_desc;

      // 5.2. REGISTRAR EN PAGOS_DESCUENTOS (Vinculado al abono)
      // Obtener el ID del abono recién insertado
      $sql_last_abono = "SELECT MAX(_id) as last_id FROM abonos WHERE id_orden = {$id_orden_a_editar}";
      $res_last_abono = $localConnection->goQuery($sql_last_abono);
      $id_abono_creado = $res_last_abono[0]['last_id'];

      if ($id_abono_creado) {
        $sql_pagos_descuentos = "INSERT INTO pagos_descuentos (id_pago, monto, descripcion) VALUES ({$id_abono_creado}, {$nuevo_descuento}, '" . addslashes($detalle_descuento) . "')";
        $localConnection->goQuery($sql_pagos_descuentos);
        $object['sql_pagos_descuentos'] = $sql_pagos_descuentos;
      }
    }

    // 5. REGISTRAR NUEVOS ABONOS Y COMISIONES (Solo sobre el nuevo pago)
    if (floatval($arr['abono']) > 0) {
      // Crear registro del nuevo abono
      $sql_abono = "INSERT INTO abonos (moment, id_orden, id_empleado, abono) VALUES ('" . $now . "', '" . $id_orden_a_editar . "',  '" . $arr['responsable'] . "', '"
        . $arr['abono'] . "');";
      $localConnection->goQuery($sql_abono);
      $object['sql_nuevo_abono'] = $sql_abono;

      // Calcular comisión SOLO sobre el nuevo abono
      if ($arr['sales_commission'] === true) {
        $sql_comision = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $arr['responsable'];
        $respComision = $localConnection->goQuery($sql_comision)[0];
        $comisionFloat = floatval($respComision['comision']);
        $comision = number_format($comisionFloat, 2);

        $pago_vendedor = floatval($arr['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);

        $sql_pago = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ",
       '" . $respComision['comision_tipo'] . "', '" . $id_orden_a_editar . "',  '" . $arr['responsable'] . "', '" . $pago_vendedor . "', 'Abono a orden', 'aprobado')";
        $object['sql_pago_response'] = $localConnection->goQuery($sql_pago);

        $object['sql_pago'] = $sql_pago;
        $object['pago_a_vendedor_por_abono'] = 'SI hubo comisión por el nuevo abono.';
      }
    }

    // 6. GUARDAR NUEVOS METODOS DE PAGO (La lógica original sirve, ya que solo inserta lo que recibe)
    $sql_metodos_pago = '';
    // ... Aquí va exactamente el mismo bloque de código que verifica cada 'monto...' y crea los INSERTs ...
    // Ejemplo:
    if (intval($newJson['montoDolaresEfectivo']) > 0) {
      $monto = intval($newJson['montoDolaresEfectivo']);
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Dólares', 'Efectivo',
       '{$monto}', '1', '');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('{$monto}', 'Dólares', 1, 'abono_orden', '" . $arr['responsable']
        . "', 'Abono a Orden #{$id_orden_a_editar}');";
    }
    // ... Repetir para Zelle, Pesos, Bolívares, etc.

    if ($sql_metodos_pago != '') {
      $object['metodos_pago_response'] = $localConnection->goQuery($sql_metodos_pago);
    }

    $object['response']['status'] = 'success';
    $object['response']['message'] = 'La orden número ' . $id_orden_a_editar . ' ha sido actualizada correctamente.';

    $response->getBody()->write(json_encode($object));
    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE CUSTOM CON VALIDACIONES
  $app->post('/ordenes/nueva/custom', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    if (is_null(json_decode($newJson['email']))) {
      $arr['email'] = json_decode($newJson['email']);
    } else {
      $arr['email'] = strtolower(json_decode($newJson['email']));
    }
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);
    $sendWhatsApp = filter_var($newJson['sendWhatsAppMessage'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $guardar_stock = filter_var($newJson['guardar_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC;*/
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* DEBuG */
    // $object['newJson'] = $newJson;

    /* Craer orden en nunesys */
    // Corregir valores NaN o vacíos
    $abono_value = (is_numeric($arr['abono']) && $arr['abono'] !== '') ? $arr['abono'] : 0;
    $descuento_value = (is_numeric($arr['descuento']) && $arr['descuento'] !== '') ? $arr['descuento'] : 0;

    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, `status` ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $descuento_value . ', ' . $abono_value . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', " . $newJson['total'] . ",'" . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";
    $nueva_oreden_response = $localConnection->goQuery($sql);
    $object['nueva_oreden_sql'] = $sql;

    // $object['nueva_oreden_response'] = $nueva_oreden_response['message'];

    if (isset($nueva_oreden_response['status'])) {
      if ($nueva_oreden_response['status'] === 'error') {
        $object['orden_creada'] = false;
        $object['response'] = $nueva_oreden_response;
        $object['response']['status'] = 'error';
      } else {
        $object['response']['status'] = 'success';
        $object['response']['message'] = 'Vrifique la creación de la orden';
      }
    } else {
      $object['orden_creada'] = true;
      // Obtenr id de la orden creada
      // $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
      $last = $nueva_oreden_response['insert_id'];
      $last_id = intval($last);

      // NUEVO: Guardar las observaciones en la tabla dedicada
      if (!empty($newJson['obs'])) {
        $observaciones = addslashes($newJson['obs'] ?? '');
        $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$last_id}, '{$observaciones}')";
        $object['sql_observaciones'] = $sql_obs;
        $localConnection->goQuery($sql_obs);
      }

      // Crear registro en la fila de producción
      $lastOrdenFila = $localConnection->goQuery('SELECT MAX(orden_fila) AS max FROM ordenes_fila_orden;');
      $lastOrdenFila = $lastOrdenFila[0]['max'] + 1;

      $sql = "INSERT INTO `ordenes_fila_orden`(`id_orden`, `orden_fila`)  VALUES ($last_id, $lastOrdenFila)";
      $object['sql_orden_fila'] = $sql;
      $response_last_fila = $localConnection->goQuery($sql);

      // Guardar orden vinculada
      if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
        $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
        $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
      }

      // Crear abono inicial de la orden
      $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
      $object['sql_abonos'] = $sql;
      $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

      // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
      // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
      if (floatval($newJson['abono']) > 0 && !$guardar_stock) {
        // $object['sales_commission_ISSET'][] = $arg["sales_commission"];

        // BUSCAR COMISION DEL VENDEDOR
        $sql = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $newJson['responsable'];
        $respComision = $localConnection->goQuery($sql)[0];

        $comisionTipo = $respComision['comision_tipo'];

        if ($comisionTipo === 'porcentaje') {
          $comision = floatval($respComision['comision_porcentaje']);
        } else {
          $comisionFloat = floatval($respComision['comision']);
          $comision = number_format($comisionFloat, 2);
        }

        $object['sql'] = $sql;
        $object['comision'] = $comision;

        $pago_vendedor = floatval($newJson['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);

        $sql = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ", '" . $comisionTipo . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
        $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
        $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
        /* if ($arg["sales_commission"] === true) {
                  $object['sales_commission_ISSET'][] = true;
                  } else {
                      $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                  } */
      }  /*  else {
   $object['sales_commission_ISSET'][] = false;
} */

      /* // GUARDAR DATOS DE DISEÑO
      $sql_diseno = '';
      if ($newJson['diseno_grafico'] == true) {
          for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
              $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
          }
      }

      if ($newJson['diseno_modas'] == 'true') {
          for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
              $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
          }
      }

      // AHORA LAS ORDENES PUEDEN PASAR SIN DISEñO Y SE PUEDE ASIGNAR POSERIORMETE A UN DISEÑADOR DE SER NECESARIO EN EL MODULO DE ADMINISRACION->ASIGNACION DE DISEÑOS
      if ($sql_diseno != '') {
          $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));
      } */

      // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
      $sql = 'SELECT _id';  // Esta línea parece no tener uso

      error_log('count productos: ' . $count);
      for ($i = 0; $i <= $count; $i++) {
        if (isset($misProductos[$i])) {
          error_log("Procesando producto $i: " . json_encode($misProductos[$i]));
          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          $decodedObj = $misProductos[$i];

          /* $woo = new WooMe();
                  $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                  $tmp = json_decode($data_category);
                  $cat_name = $tmp->name; */
          /* if ($tmp->statusCode === 500) {
                      $cat_name = "Uncatagorized";
                      } else {
                      } */
          /* $sqlc = 'SELECT `nombre` FROM `categories` WHERE  _id = ' . $decodedObj['categoria'];
           $cat_name_base = $localConnection->goQuery($sqlc);
           $object['CAT_sql'] = $sqlc;
           $object['CAT_response'] = $cat_name_base[0]['nombre'];

            if (empty($cat_name_base)) {
               $cat_name = 'Uncatagorized';
               } else {
                   $cat_name = $cat_name_base[0]['nombre'];
           } */
          $cat_name = 'Uncatagorized';

          $values = "'" . $now . "',";
          $values .= $decodedObj['precio'] . ',';
          $values .= "'" . $decodedObj['precio'] . "',";
          $values .= "'" . $decodedObj['producto'] . "',";
          $values .= $last_id . ',';
          $values .= $decodedObj['cod'] . ',';
          $values .= $decodedObj['cantidad'] . ',';
          $values .= $decodedObj['categoria'] . ',';
          $values .= "'" . $cat_name . "',";
          // $values .= "'" . $tmp["->name"] . "',";

          // --- INICIO: Corrección para guardar ID y Nombre de la Talla ---
          if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
            $id_talla = intval($decodedObj['talla']);
            $values .= $id_talla . ',';  // Para la columna id_size
            $resultTalla = $localConnection->goQuery("SELECT nombre FROM sizes WHERE _id = {$id_talla}");
            $nombreTalla = (isset($resultTalla[0]['nombre'])) ? $resultTalla[0]['nombre'] : '';
            $values .= "'" . addslashes($nombreTalla) . "',";  // Para la columna talla
          } else {
            $values .= 'NULL, NULL,';  // Para id_size y talla
          }
          // --- FIN: Corrección ---

          if (isset($decodedObj['corte'])) {
            $values .= "'" . addslashes($decodedObj['corte'] ?? '') . "',";
          } else {
            $values .= "'',";
          }

          if (isset($decodedObj['tela'])) {
            $id_tela = intval($decodedObj['tela']);
            $values .= $id_tela . ',';  // ID de la tela para la columna `id_tela`
            $values .= "'" . addslashes((string) $localConnection->goQuery('SELECT tela FROM catalogo_telas WHERE _id = ' . $id_tela)[0]['tela']) . "'";  // Nombre para la columna `tela`
          } else {
            $values .= "NULL, ''";  // Valores por defecto si no hay tela
          }

          // Manejar el nuevo atributo (SINGLE) si es que existe.
          // Según el payload, este campo 'atributo' no está llegando.
          // El que llega es 'atributos_seleccionados' (plural, array).
          $id_products_attributes_single = 'NULL';
          if (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo']) && $decodedObj['atributo'] !== '') {
            $id_products_attributes_single = intval($decodedObj['atributo']);
          }

          $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ' . $id_products_attributes_single . ')';
          error_log('SQL producto: ' . $sql2);
          $object['sql_ordenes_productos'] = $sql2;
          $producto_detalle_response = $localConnection->goQuery($sql2);
          error_log('Resultado INSERT: ' . json_encode($producto_detalle_response));
          $object['producto_detalle'][] = $producto_detalle_response;

          $last_id_ordenes_productos = null;
          if (isset($producto_detalle_response['insert_id'])) {
            $last_id_ordenes_productos = $producto_detalle_response['insert_id'];

            // === INICIO DE CORRECCIÓN: Procesar atributos_seleccionados ===
            if (isset($decodedObj['atributos_seleccionados']) && is_array($decodedObj['atributos_seleccionados'])) {
              // Aseguramos que 'cod' (id_woo del producto) esté disponible para id_product
              $product_id_for_attributes_table = intval($decodedObj['cod']);

              $sql_attr = null;
              foreach ($decodedObj['atributos_seleccionados'] as $attribute_data) {
                // Validar que todas las claves necesarias existan y tengan el tipo correcto
                if (
                  isset($attribute_data['value']) &&
                  is_numeric($attribute_data['value']) &&
                  isset($attribute_data['text']) &&
                  isset($attribute_data['precio']) &&
                  is_numeric($attribute_data['precio'])
                ) {
                  $id_product_attribute = intval($attribute_data['value']);
                  $attribute_value_text = $attribute_data['text'];
                  $attribute_price_value = floatval($attribute_data['precio']);

                  // Construct INSERT statement with correct column names and all required values
                  $sql_attr = 'INSERT INTO products_attributes_values (id_orden, id_product, id_product_attribute, attribute_value, attribute_price) VALUES (?, ?, ?, ?, ?)';

                  // Prepare parameters for the INSERT statement
                  $params_attr = [
                    $last_id,  // id_orden
                    $product_id_for_attributes_table,  // id_product (que es el id_woo del producto)
                    $id_product_attribute,  // id_product_attribute
                    $attribute_value_text,  // attribute_value
                    $attribute_price_value  // attribute_price
                  ];

                  // Execute the query
                  $localConnection->goQuery($sql_attr, $params_attr);
                }
              }
              // Para depuración, esto mostrará la última query de atributos ejecutada
              $object['sql_atributos_seleccionados'] = $sql_attr;
            }
            // === FIN DE CORRECCIÓN ===
          }

          // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
          if ($misProductos[$i] != '') {  // Esta condición ($misProductos[$i] != '') es redundante aquí porque ya se usó isset($misProductos[$i])
            $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
            $myOrder = $localConnection->goQuery($sql_order);
            $object['myOrder_sql'] = $sql_order;
            $object['myOrder'] = $myOrder;

            // Obtenr ultimo ID del producto creado - This is now available from $producto_detalle_response
            // $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
            // $last_id_ordenes_productos is available above if needed

            // PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();

            // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
            $myWooId = intval($decodedObj['cod']);

            // VERIFICAR SI ES UN PRODCTO FISICO
            $sqlpv = "SELECT fisico FROM products WHERE _id = {$myWooId} AND fisico = 1";
            $resultProductoFisico = $localConnection->goQuery($sqlpv);

            if (!empty($resultProductoFisico)) {
              // BUSCAR DEPARTAMETNOS DEL PROCESO DE PRODUCCIÓN
              $sqlpd = 'SELECT _id, departamento FROM departamentos WHERE asignar_numero_de_paso = 1 ORDER BY asignar_numero_de_paso ASC';
              $resultDepartamentos = $localConnection->goQuery($sqlpd);

              $sql_lote_detalles = '';

              foreach ($resultDepartamentos as $departamento) {
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `id_departamento`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', {$departamento['_id']}, '{$departamento['departamento']}');";
              }

              $object['lotes_detalles_sql'][$i] = $sql_lote_detalles;
              $object['lote_detalles_response'][$i] = $localConnection->goQuery($sql_lote_detalles);
            }
          }
        }
      }

      // GUARDAR LOTE

      // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
      $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
      $resultVerify = $localConnection->goQuery($sql_verify);

      $guardarLote = true;
      if (!empty($resultVerify)) {
        // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
        if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
          $guardarLote = false;
        }
      }

      $object['guardar_en_lote'] = $guardarLote;

      if ($guardarLote) {
        $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
        $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
      }

      // INICIO: Lógica condicional para guardar stock en /nueva/custom

      if ($guardar_stock) {
        $stock_updates = [];
        foreach ($misProductos as $producto) {
          $product_id = intval($producto['cod']);
          $quantity = intval($producto['cantidad']);
          if (isset($stock_updates[$product_id])) {
            $stock_updates[$product_id] += $quantity;
          } else {
            $stock_updates[$product_id] = $quantity;
          }
        }

        $sql_stock_update = '';
        foreach ($stock_updates as $product_id => $total_quantity) {
          $sql_stock_update .= "UPDATE products SET stock_quantity = stock_quantity + {$total_quantity} WHERE _id = {$product_id};";
        }

        if (!empty($sql_stock_update)) {
          $object['custom_stock_update_sql'] = $sql_stock_update;
          $object['custom_stock_update_response'] = $localConnection->goQuery($sql_stock_update);
        }
      }
      // FIN: Lógica condicional para guardar stock en /nueva/custom

      // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
      $sql_metodos_pago = '';

      if (floatval($arr['montoDolaresEfectivo']) > 0) {  // Usar floatval para comparar con 0
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (floatval($arr['montoDolaresZelle']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', ' " . addslashes($arr['montoDolaresZelleDetalle'] ?? '') . " ');";
      }

      if (floatval($arr['montoDolaresPanama']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', '" . addslashes($arr['montoDolaresPanamaDetalle'] ?? '') . "');";
      }

      if (floatval($arr['montoPesosEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (floatval($arr['montoPesosTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', '" . addslashes($arr['montoPesosTransferenciaDetalle'] ?? '') . "');";
      }

      if (floatval($arr['montoBolivaresEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (floatval($arr['montoBolivaresPunto']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', '');";
      }

      if (floatval($arr['montoBolivaresPagomovil']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', '" . addslashes($arr['montoBolivaresPagomovilDetalle'] ?? '') . "');";
      }

      if (floatval($arr['montoBolivaresTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', '" . addslashes($arr['montoBolivaresTransferenciaDetalle'] ?? '') . "');";
      }
      $object['sql_metodos_pago'] = $sql_metodos_pago;

      if ($sql_metodos_pago != '') {
        $object['metodos_pago'] = $localConnection->goQuery($sql_metodos_pago);  // Corregido: removí el [$i]
      }

      if ($sendWhatsApp) {
        $infoSql = 'SELECT b.phone FROM ordenes a LEFT JOIN customers b ON b._id = a.id_wp WHERE a._id = ' . $last_id;
        $contactInfo = $localConnection->goQuery($infoSql)[0] ?? [];
        $clientPhone = $contactInfo['phone'] ?? null;

        if (empty($clientPhone)) {
          $object['ws_response'] = 'Envío de WhatsApp omitido: No se encontró un número de teléfono para el cliente.';
        } else {
          // Asumiendo que 'obtenerRespuestaBuscar' y la API de WhatsApp son externas y funcionan
          $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');  // Asegúrate que esta función esté definida
          $payload = $resultBuscar['object'];
          $payload['phone_client'] = $clientPhone;
          $payload['template'] = 'welcome';

          $encoded_payload = json_encode($payload);
          $ws_url = 'https://ws.nineteengreen.com/send-message/' . ID_EMPRESA;  // Asegúrate que ID_EMPRESA esté definido

          $ch = curl_init($ws_url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_payload);
          curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($encoded_payload)
          ]);
          curl_setopt($ch, CURLOPT_TIMEOUT, 15);

          $ws_result = curl_exec($ch);
          $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          $curl_error = curl_error($ch);
          curl_close($ch);

          if ($ws_result === false) {
            $object['ws_response'] = ['error' => 'Error de cURL', 'details' => $curl_error];
          } else {
            $object['ws_response'] = json_decode($ws_result, true);
          }

          $object['ws_payload_sent'] = $payload;
          $object['ws_http_code'] = $http_code;
        }
      } else {
        $object['ws_response'] = 'Envío de WhatsApp omitido por el usuario.';
      }

      $object['response']['status'] = 'success';
      $object['response']['message'] = 'La orden número ' . $last_id . ' ha sido creada correctamente';
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));  // Usar JSON_NUMERIC_CHECK para manejar números
    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE SPORT
  $app->post('/ordenes/nueva/sport', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    if (is_null(json_decode($newJson['email']))) {
      $arr['email'] = json_decode($newJson['email']);
    } else {
      $arr['email'] = strtolower(json_decode($newJson['email']));
    }
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);
    $sendWhatsApp = filter_var($newJson['sendWhatsAppMessage'] ?? false);

    $arr['hoy'] = date('d/m/Y');
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    $myDate = new CustomTime();
    $now = $myDate->today();

    $orderWC = 0;

    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, `status`, tipo ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'entregada', 'sport')";
    $nueva_oreden_response = $localConnection->goQuery($sql);
    $object['nueva_oreden_sql'] = $sql;

    if (isset($nueva_oreden_response['status']) && $nueva_oreden_response['status'] === 'error') {
      $object['orden_creada'] = false;
      $object['response'] = $nueva_oreden_response;
      $object['response']['status'] = 'error';
    } else {
      $object['orden_creada'] = true;
      $last_id = $nueva_oreden_response['insert_id'];

      if (!empty($newJson['obs'])) {
        $observaciones = addslashes($newJson['obs'] ?? '');
        $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$last_id}, '{$observaciones}')";
        $object['sql_observaciones'] = $sql_obs;
        $localConnection->goQuery($sql_obs);
      }

      // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
      $sql = 'SELECT _id';

      for ($i = 0; $i <= $count; $i++) {
        if (isset($misProductos[$i])) {
          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          $decodedObj = $misProductos[$i];

          $cat_name = 'Uncatagorized';

          $values = "'" . $now . "',";
          $values .= $decodedObj['precio'] . ',';
          $values .= "'" . $decodedObj['precio'] . "',";
          $values .= "'" . $decodedObj['producto'] . "',";
          $values .= $last_id . ',';
          $values .= $decodedObj['cod'] . ',';
          $values .= $decodedObj['cantidad'] . ',';
          $values .= $decodedObj['categoria'] . ',';
          $values .= "'" . $cat_name . "',";

          if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
            $id_talla = intval($decodedObj['talla']);
            $values .= $id_talla . ',';
            $values .= "(SELECT nombre FROM sizes WHERE _id = {$id_talla}),";
          } else {
            $values .= 'NULL, NULL,';
          }

          if (isset($decodedObj['corte'])) {
            $values .= "'" . $decodedObj['corte'] . "',";
          } else {
            $values .= "'',";
          }

          if (isset($decodedObj['tela'])) {
            $values .= "'" . $decodedObj['tela'] . "',";
            $values .= '(SELECT tela FROM catalogo_telas WHERE _id = ' . intval($decodedObj['tela']) . ')';
          } else {
            $values .= "NULL, ''";
          }

          $id_products_attributes = 'NULL';
          if (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo']) && $decodedObj['atributo'] !== '') {
            $id_products_attributes = intval($decodedObj['atributo']);
          }

          $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ' . $id_products_attributes . ')';
          $object['sql_ordenes_productos'] = $sql2;
          $producto_detalle_response = $localConnection->goQuery($sql2);
          $object['producto_detalle'][] = $producto_detalle_response;

          if (isset($producto_detalle_response['insert_id'])) {
            $last_id_ordenes_productos = $producto_detalle_response['insert_id'];

            // === INICIO DE LA ÚNICA CORRECCIÓN: Procesar atributos_seleccionados ===
            if (isset($decodedObj['atributos_seleccionados']) && is_array($decodedObj['atributos_seleccionados'])) {
              // Obtener el id_product (que es id_woo en esta tabla)
              $product_id_for_attributes_table = intval($decodedObj['cod']);

              $object['response_data'] = $decodedObj['atributos_seleccionados'];
              foreach ($decodedObj['atributos_seleccionados'] as $attribute_data) {
                $object['response_flag'][] = true;
                // Validar que las claves necesarias existan y sean del tipo correcto
                if (
                  isset($attribute_data['value']) &&
                  is_numeric($attribute_data['value']) &&
                  isset($attribute_data['text']) &&
                  isset($attribute_data['precio']) &&
                  is_numeric($attribute_data['precio'])
                ) {
                  $id_product_attribute = intval($attribute_data['value']);
                  $attribute_value_text = $attribute_data['text'];  // No se aplica addslashes si goQuery usa prepared statements
                  $attribute_price_value = floatval($attribute_data['precio']);

                  // Construir la sentencia INSERT con los nombres de columna correctos y todos los valores
                  $sql_attr = 'INSERT INTO products_attributes_values (id_orden, id_product, id_product_attribute, attribute_value, attribute_price) VALUES (?, ?, ?, ?, ?)';

                  // Preparar los parámetros para la sentencia INSERT
                  // Asumo que goQuery() maneja parámetros de forma segura (prepared statements)
                  $params_attr = [
                    $last_id,  // id_orden
                    $product_id_for_attributes_table,  // id_product (id_woo del producto)
                    $id_product_attribute,  // id_product_attribute
                    $attribute_value_text,  // attribute_value (texto del atributo)
                    $attribute_price_value  // attribute_price (precio del atributo)
                  ];

                  // Ejecutar la consulta
                  $object['response_Atrinutos'][] = $localConnection->goQuery($sql_attr, $params_attr);
                }
              }
              // Para depuración, esto mostrará la última query de atributos ejecutada
              // El campo original era $object['myOrder_sql'], lo renombro para claridad
              // $object['sql_atributos_seleccionados'] = $sql_attr;
            } else {
              $object['response_flag'][] = false;
            }
            // === FIN DE LA ÚNICA CORRECCIÓN ===
          }
        }
      }

      $stock_updates = [];
      foreach ($misProductos as $producto) {
        $product_id = intval($producto['cod']);
        $quantity = intval($producto['cantidad']);
        if (isset($stock_updates[$product_id])) {
          $stock_updates[$product_id] += $quantity;
        } else {
          $stock_updates[$product_id] = $quantity;
        }
      }

      $sql_stock_update = '';
      foreach ($stock_updates as $product_id => $total_quantity) {
        // $sql_stock_update .= "UPDATE products SET stock_quantity = stock_quantity + {$total_quantity} WHERE _id = {$product_id};";
        $sql_stock_update .= "UPDATE products SET stock_quantity = stock_quantity - {$total_quantity} WHERE _id = {$product_id};";
      }

      if (!empty($sql_stock_update)) {
        $object['stock_update_sql'] = $sql_stock_update;
        $object['stock_update_response'] = $localConnection->goQuery($sql_stock_update);
      }

      // INICIO: Lógica de comisión para vendedores (copiada de /nueva/custom)
      // NUEVO: Debugging para guardar_stock
      error_log('DEBUG: guardar_stock raw value: ' . ($newJson['guardar_stock'] ?? 'NOT SET'));
      error_log('DEBUG: guardar_stock filter_var result: ' . (filter_var($newJson['guardar_stock'] ?? null, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE'));

      if (floatval($newJson['abono']) > 0 && !(isset($newJson['guardar_stock']) && filter_var($newJson['guardar_stock'], FILTER_VALIDATE_BOOLEAN))) {
        // BUSCAR COMISION DEL VENDEDOR
        $sql_comision = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $newJson['responsable'];
        $respComisionArr = $localConnection->goQuery($sql_comision);

        if (!empty($respComisionArr)) {
          $respComision = $respComisionArr[0];
          $comisionTipo = $respComision['comision_tipo'];

          if ($comisionTipo === 'porcentaje') {
            $comision = floatval($respComision['comision_porcentaje']);
          } else {
            $comisionFloat = floatval($respComision['comision']);
            $comision = number_format($comisionFloat, 2);
          }

          $pago_vendedor = floatval($newJson['abono']) * $comision / 100;
          $pago_vendedor = number_format($pago_vendedor, 2);

          $sql_pago = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ", '" . $comisionTipo . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
          $object['resultado_abono'] = $localConnection->goQuery($sql_pago);
          $object['pago_a_vendedor'] = 'SI hubo comisión, cliente normal';
        } else {
          $object['pago_a_vendedor'] = 'NO se encontró información de comisión para el vendedor.';
        }
      }
      // FIN: Lógica de comisión para vendedores

      $response->getBody()->write(json_encode($object));
      $localConnection->disconnect();

      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    }
  });

  // FIN CREAR NUEVA ORDEN

  // CAMBIAR ESTATUS DE LA REVISIÓN
  $app->post('/comercializacion/revisiones-estatus/{estatus}/{id_revision}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();

    $sql = "UPDATE revisiones SET estatus = '" . $args['estatus'] . "' WHERE _id = " . $args['id_revision'];
    $localConnection->goQuery($sql);

    // BUSCAR EL ID DE LA ORDEN EN `revisiones`
    // $sql = "SELECT id_orden FROM revisiones WHERE _id = " . $args["id_revision"];
    // $miRevision = $localConnection->goQuery($sql);
    // $miRevision = $args['id_revision'];

    // CON EL ID DE LA ORDEN BUSCAMOS EL ID DEL DISEÑADOR EN `disenos` `revisiones`
    $sql = 'SELECT id_orden, id_empleado FROM revisiones WHERE _id = ' . $args['id_revision'];
    $miDiseno = $localConnection->goQuery($sql);

    // VERIFICAR PAGO EXISTENTE
    $sqlPago = "SELECT count(_id) exist FROM pagos WHERE detalle = 'Diseño' AND id_orden = " . $miDiseno[0]['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
    $object['pago_exist'] = $localConnection->goQuery($sqlPago)[0];

    // ELIMINAR PAGO A DISEñADOR POR RECHAZO DE PROPUESTA
    if ($args['estatus'] === 'Rechazado') {
      $sql = "UPDATE revisiones SET estatus = 'Rechazado' WHERE _id = " . $args['id_revision'];
      $resultUpdateRevisiones = $localConnection->goQuery($sql);
    }

    // APROBAR PROPUESTA
    if ($args['estatus'] === 'Aprobado') {
      $estatusTerminado = 1;
      $sql = 'UPDATE disenos SET terminado = ' . $estatusTerminado . ' WHERE id_orden = ' . $miDiseno[0]['id_orden'] . ';';
      $sql .= "UPDATE ordenes SET status = 'activa' WHERE _id = " . $args['id_orden'] . ';';
      $miRevision = $localConnection->goQuery($sql);
      $object['sql_revision'] = $sql;

      // BUSCAR DATOS DE LA REVISON
      $sql = 'SELECT id_empleado, id_product FROM revisiones WHERE _id = ' . $args['id_revision'];
      $id_tmp = $localConnection->goQuery($sql);
      $id_disenador = $id_tmp[0]['id_empleado'];
      $id_product = $id_tmp[0]['id_product'];

      // BUSCAR DISEÑOS ASIGANDO PARA UBICAR EL MONTO DE LA COMISIÓN
      $sql = 'SELECT
                    pro._id id_porducto,
                    pro.product,
                    pro.comision
                FROM
                    disenos dis
                LEFT JOIN revisiones rev ON
                    rev.id_diseno = dis._id
                LEFT JOIN products pro ON
                    dis.id_product = pro._id
                WHERE
                    rev._id = ' . $args['id_revision'] . ' AND rev.id_orden = ' . $args['id_orden'] . ' AND dis.id_empleado = ' . $miDiseno[0]['id_empleado'] . '
            ';
      $comision_tmp = $localConnection->goQuery($sql);

      if (empty($comision_tmp)) {
        $object['comision_diseno'] = 0;
        $comision = 0;
      } else {
        $comision = $comision_tmp[0]['comision'];
        // Verificar si el pago existe
        /* $sql = "SELECT _id FROM pagos WHERE detalle = 'Diseño' AND id_empleado = " . $miDiseno[0]['id_empleado'] . ' AND id_orden = ' . $args['id_orden'];
        $object['sql_pago_exist'] = $sql;
        $miPago = $localConnection->goQuery($sql); */

        /*$object['id_woo'] = $idWoo[0]['id_woo'];

         // Buscar en WooMe la comision asociada a el producto $idWoo
        $woo = new WooMe();
        $woomeResponse = $woo->getProductById($idWoo[0]['id_woo']);

        // $object["woo-response"] = json_encode($woomeResponse);
        if (isset($woomeResponse->attributes[0]->options[0])) {
            $object['comision_diseno'] = json_encode($woomeResponse->attributes[0]->options[0]);
        } else {
            $object['comision_diseno'] = 0;
        }

        if (empty($woomeResponse->attributes)) {
            $comision = 0;
        } else {
            $comision = $woomeResponse->attributes[0]->options[0];
        } */

        $sql = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miDiseno[0]['id_empleado'];
        $respComision = $localConnection->goQuery($sql);
        $comision_tipo = $respComision[0]['comision_tipo'];

        if ($comision_tipo === 'variable') {
          // Buscar la comision en el producto
          $sql = 'SELECT comision FROM products WHERE _id = ' . $id_product;
          $respComisionProd = $localConnection->goQuery($sql);
          $comision = $respComisionProd[0]['comision'];
        } elseif ($comision_tipo === 'porcentaje') {
          // Para porcentaje: calcular basado en el precio del producto
          $sql_precio = 'SELECT precio_unitario FROM ordenes_productos WHERE id_orden = ' . $args['id_orden'] . ' LIMIT 1';
          $respPrecio = $localConnection->goQuery($sql_precio);
          if (!empty($respPrecio)) {
            $precioProducto = floatval($respPrecio[0]['precio_unitario']);
            $porcentaje = floatval($respComision[0]['comision_porcentaje']);
            $comision = $precioProducto * ($porcentaje / 100);
          } else {
            $comision = 0;
          }
        } else {
          // Preparar la comision para guardarla
          $comisionFloat = floatval($respComision[0]['comision']);
          $floatValue = floatval($comisionFloat);
          $comision = number_format($floatValue, 2);
        }

        // $comision_disenador = number_format(floatval($comision, 2));

        /* if (empty($miPago)) {
            $sqlPago = 'INSERT INTO pagos (cantidad, comision, comision_tipo, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, ' . $comision . ", '" . $comision_tipo . "',  " . $args['id_orden'] . ", 'aprobado' , " . $comision . ', ' . $miDiseno[0]['id_empleado'] . ", 'Diseño');";
            $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
        } else {
            // UPDATE pagos
            $sqlPago = 'UPDATE pagos SET monto_pago = ' . $comision . ' WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
            $object['sqlPago'] = $sqlPago;
            $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
        } */
        $sqlPago = 'INSERT INTO pagos (cantidad, comision, comision_tipo, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, ' . $comision . ", '" . $comision_tipo . "',  " . $args['id_orden'] . ", 'aprobado' , " . $comision . ', ' . $miDiseno[0]['id_empleado'] . ", 'Diseño');";
        $object['sql_pago'] = $sqlPago;
        $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR DETALLES DE LA REVISIÓN
  $app->post('/comercializacion/revisiones-detalles/{id_revision}', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    $sql = "UPDATE revisiones SET detalles = '" . htmlspecialchars($data['detalles']) . "' WHERE _id = " . $args['id_revision'];
    $object['revisiones'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REVISAR REVISIONES PENDIENTES
  $app->get('/comercializacion/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT acceso FROM  empresas_usuarios  WHERE id_usuario = ' . $args['id_empleado'];
    $miEmpleado = $localConnection->goQuery($sql);

    $localConnection = new localDB();

    if ($miEmpleado[0]['acceso']) {
      // Mostrar todos los registros de revisiones
      $sql = "SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
    } else {
      // Mostrar solo los registros del venededor
      $sql = 'SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id AND b.responsable = ' . $args['id_empleado'] . " WHERE b.responsable = '" . $args['id_empleado'] . "' AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
    }

    $object['revisiones'] = $localConnection->goQuery($sql);

    $object['total_revisiones'] = count($object['revisiones']);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTUALIZAR ORDEN DE LA FILA DE REPOSICIONES
  $app->post('/reposiciones/actualizar-fila', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    // Validar que los datos necesarios están presentes
    if (!isset($data['id_reposicion']) || !isset($data['orden_fila'])) {
      $response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos: id_reposicion u orden_fila.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'UPDATE ordenes_fila_reposiciones SET orden_fila = ' . intval($data['orden_fila']) . ' WHERE id_reposicion = ' . intval($data['id_reposicion']) . ';';
    $object['sql_update_fila_reposicion'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->get('/ordenes-observaciones/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // Se corrige la consulta para seleccionar el campo 'observaciones'
    $sql = "SELECT
            observaciones
        FROM
            ordenes_observaciones a
        WHERE
            a.id_orden = {$args['id_orden']}";

    $object = $localConnection->goQuery($sql);

    // Se añade JSON_UNESCAPED_UNICODE para asegurar que los caracteres especiales
    // dentro del HTML (como tildes o eñes) se envíen correctamente.
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN ORDENES */

}; // Fin de la función que envuelve las rutas
