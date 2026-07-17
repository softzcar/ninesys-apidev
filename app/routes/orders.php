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

    // Atomicidad FK: la edición puede tocar ordenes_productos y ordenes en pasos separados
    $localConnection->beginTransaction();

    $params = [];
    switch ($data['accion']) {
      case 'editar-cantidad':
        $sql = 'UPDATE ordenes_productos SET cantidad = ? WHERE _id = ?';
        $resp = $localConnection->goQuery($sql, [$data['cantidad'], $data['id']]);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ?';

        $resp = $localConnection->goQuery($sql, [$data['id_orden']]);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        $sql = 'UPDATE ordenes SET pago_total = ? WHERE _id = ?';
        $params = [$nuevototal, $data['id_orden']];
        break;

      case 'editar-talla':
        // Guardar nuevos datos
        $sql = 'UPDATE ordenes_productos SET precio_unitario = ?, talla = ? WHERE _id = ?';
        $resp = $localConnection->goQuery($sql, [$data['precio'], $data['cantidad'], $data['id']]);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ?';

        $resp = $localConnection->goQuery($sql, [$data['id_orden']]);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        // Guardar nuevo pago_total de la orden
        $sql = 'UPDATE ordenes SET pago_total = ? WHERE _id = ?';
        $params = [$nuevototal, $data['id_orden']];
        break;

      case 'editar-corte':
        $sql = 'UPDATE ordenes_productos SET corte = ? WHERE _id = ?';
        $params = [$data['cantidad'], $data['id']];
        break;

      case 'eliminar-producto':
        $sql = 'DELETE FROM ordenes_productos WHERE _id = ?';
        $params = [$data['id']];
        break;

      case 'editar-tela':
        // Guardar cambios
        $sql = 'UPDATE ordenes_productos SET tela = ? WHERE _id = ?';
        $params = [$data['cantidad'], $data['id']];
        break;

      case 'nuevo-producto':
        $campos = '(moment, id_orden, id_woo, precio_woo, name, cantidad, talla, corte, tela, precio_unitario)';

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $sql = 'INSERT INTO ordenes_productos ' . $campos . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params = [
          $now,
          $data['id_orden'],
          $data['id_woo'],
          $data['precio_woo'],
          $data['name'],
          $data['cantidad'],
          $data['talla'],
          $data['corte'],
          $data['tela'],
          $data['precio_unitario'],
        ];
        break;

      default:
        // code...
        break;
    }

    $resp = $localConnection->goQuery($sql, $params);

    $localConnection->commit();
    $localConnection->disconnect();

    $object['response'] = $resp;

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ============================================================
  // PREVIA CANCELACIÓN - Obtener tareas pendientes antes de cancelar
  // ============================================================
  $app->post('/orden/previa-cancelacion', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $idOrden = isset($body['id']) ? intval($body['id']) : 0;

    if ($idOrden <= 0) {
      return ApiResponse::validationError($response, 'ID de orden inválido');
    }

    $localConnection = new LocalDB();

    // Consultar tareas pendientes (fecha_terminado IS NULL)
    $sqlPendientes = "
        SELECT 
            ldea._id as id_asignacion,
            dep.departamento,
            eu.nombre as empleado,
            CASE 
                WHEN ldea.fecha_inicio IS NULL THEN 'Por iniciar'
                ELSE 'En curso'
            END as status_tarea
        FROM lotes_detalles_empleados_asignados ldea
        JOIN departamentos dep ON ldea.id_departamento = dep._id
        JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario
        WHERE ldea.id_orden = $idOrden
          AND ldea.fecha_terminado IS NULL
        ORDER BY dep.orden_proceso ASC
    ";
    $tareasPendientes = $localConnection->goQuery($sqlPendientes);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode([
      'success' => true,
      'puede_cancelar' => true,
      'tareas_pendientes' => $tareasPendientes ?: []
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // Actualizar estado de la orden
  $app->post('/orden/actualizar-estado', function (Request $request, Response $response, $args) {
    $order = $request->getParsedBody();
    $localConnection = new LocalDB();

    // 1. OBTENER ESTADO ACTUAL DE LA ORDEN
    $sqlActual = "SELECT status FROM ordenes WHERE _id = " . intval($order['id']);
    $resActual = $localConnection->goQuery($sqlActual);
    $estadoActual = !empty($resActual) ? $resActual[0]['status'] : null;

    if (!$estadoActual) {
      $localConnection->disconnect();
      return ApiResponse::validationError($response, 'Orden no encontrada');
    }

    // Atomicidad FK: borrado de asignaciones + UPDATE ordenes + auditoría en una transacción.
    // Las validaciones que retornan temprano hacen disconnect() => la transacción abierta se revierte sola.
    $localConnection->beginTransaction();

    // 2. VALIDACIÓN "ENTREGADA" (Solo si el estatus actual es "terminada")
    if ($order['estado'] === 'entregada' && $estadoActual !== 'terminada') {
      $localConnection->disconnect();
      return ApiResponse::validationError($response, 'La orden debe estar en estatus "Terminada" para poder marcarla como "Entregada"');
    }

    // 3. VALIDACIÓN "TERMINADA" (Revisar tareas de empleados asignados)
    if ($order['estado'] === 'terminada') {
      $sqlPendientes = "
          SELECT 
              dep.departamento,
              eu.nombre as empleado,
              CASE 
                  WHEN ldea.fecha_inicio IS NULL THEN 'Por iniciar'
                  ELSE 'En curso'
              END as status_tarea
          FROM lotes_detalles_empleados_asignados ldea
          JOIN departamentos dep ON ldea.id_departamento = dep._id
          JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario
          WHERE ldea.id_orden = " . intval($order['id']) . "
            AND ldea.fecha_terminado IS NULL
      ";
      $tareasPendientes = $localConnection->goQuery($sqlPendientes);

      if (!empty($tareasPendientes)) {
        $localConnection->disconnect();
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'Existen tareas asignadas inconclusas. Se deben terminar primero o eliminar la asignación.',
          'tareas_pendientes' => $tareasPendientes
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }
    }

    // 4. LÓGICA "CANCELADA" (Eliminar asignaciones pendientes antes de cancelar)
    $eliminadas = 0;
    if ($order['estado'] === 'cancelada') {
      // 4a. Obtener IDs de asignaciones pendientes para eliminar sus pausas
      $sqlIdsPendientes = "SELECT _id FROM lotes_detalles_empleados_asignados 
                           WHERE id_orden = " . intval($order['id']) . " 
                           AND fecha_terminado IS NULL";
      $idsPendientes = $localConnection->goQuery($sqlIdsPendientes);

      if (!empty($idsPendientes) && is_array($idsPendientes)) {
        $idsArray = array_column($idsPendientes, '_id');
        if (!empty($idsArray)) {
          $idsStr = implode(',', $idsArray);

          // 4b. Eliminar pausas de las asignaciones que serán eliminadas
          $sqlDeletePausas = "DELETE FROM lotes_detalles_empleados_asignados_pausas
                              WHERE id_lotes_detalles_empleados_asignados IN ($idsStr)";
          $localConnection->goQuery($sqlDeletePausas);
        }
      }

      // 4c. Eliminar asignaciones pendientes (fecha_terminado IS NULL)
      $sqlDeleteAsignaciones = "DELETE FROM lotes_detalles_empleados_asignados 
                                WHERE id_orden = " . intval($order['id']) . " 
                                AND fecha_terminado IS NULL";
      $localConnection->goQuery($sqlDeleteAsignaciones);
      $eliminadas = count($idsPendientes ?: []);
    }

    // 5. VALIDACIÓN "CANCELADA → TERMINADA" (Bloquear cambio ilógico)
    if ($estadoActual === 'cancelada' && $order['estado'] === 'terminada') {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'No se puede marcar como terminada una orden cancelada. Si desea completar esta orden, reactívela primero.'
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // 6. VALIDACIÓN "REACTIVAR CANCELADA" (Advertir si no hay asignaciones)
    $forzarReactivacion = isset($order['forzar_reactivacion']) ? intval($order['forzar_reactivacion']) : 0;

    if ($estadoActual === 'cancelada' && $order['estado'] !== 'cancelada' && !$forzarReactivacion) {
      // Contar asignaciones activas (cualquier asignación, terminada o no)
      $sqlAsignaciones = "SELECT COUNT(*) as total 
                          FROM lotes_detalles_empleados_asignados 
                          WHERE id_orden = " . intval($order['id']);
      $resAsignaciones = $localConnection->goQuery($sqlAsignaciones);
      $totalAsignaciones = !empty($resAsignaciones) ? intval($resAsignaciones[0]['total']) : 0;

      if ($totalAsignaciones === 0) {
        $localConnection->disconnect();
        $response->getBody()->write(json_encode([
          'success' => false,
          'advertencia_reactivacion' => true,
          'message' => 'Esta orden fue cancelada y no tiene personal asignado. Deberá reasignar empleados manualmente.'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }
    }

    // 7. VALIDACIÓN DE MOTIVO para Administración (Cancelada, Terminada manual, Reactivada o reversión de Entregada)
    // Pedir motivo si se cambia a cancelada/terminada O si se está saliendo de esos estados o de 'entregada'
    // EXCEPCIÓN: terminada -> entregada es el flujo normal y NO requiere motivo manual
    $esFlujoNormalTerminadaAEntrega = ($estadoActual === 'terminada' && $order['estado'] === 'entregada');

    if (!$esFlujoNormalTerminadaAEntrega && ($order['estado'] === 'cancelada' || $order['estado'] === 'terminada' || $estadoActual === 'cancelada' || $estadoActual === 'terminada' || $estadoActual === 'entregada')) {
      // Verificar que se envió el motivo si hay cambio de estado
      if ($order['estado'] !== $estadoActual && (!isset($order['motivo']) || trim($order['motivo']) === '')) {
        $localConnection->disconnect();
        $response->getBody()->write(json_encode([
          'success' => false,
          'requiere_motivo' => true,
          'message' => 'Debe proporcionar un motivo para esta acción'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }
    }

    // 8. SI PASA LAS VALIDACIONES, ACTUALIZAR
    $sql = "UPDATE ordenes SET status = '" . $order['estado'] . "' WHERE _id = " . intval($order['id']);
    $data = $localConnection->goQuery($sql);

    // 9. REGISTRAR EN AUDITORÍA (si es cancelada, terminada manual, reactivada o reversión de entregada)
    // No registrar auditoría manual si es el flujo normal terminada -> entregada
    if (!$esFlujoNormalTerminadaAEntrega && ($order['estado'] === 'cancelada' || $order['estado'] === 'terminada' || $estadoActual === 'cancelada' || $estadoActual === 'terminada' || $estadoActual === 'entregada') && $order['estado'] !== $estadoActual) {
      $motivoEscaped = addslashes(trim($order['motivo']));
      $idAdmin = isset($order['id_admin']) ? intval($order['id_admin']) : 0;
      $nombreAdmin = isset($order['nombre_admin']) ? addslashes($order['nombre_admin']) : 'Desconocido';

      // Determinar la acción para la auditoría
      $accion = $order['estado'];
      if ($estadoActual === 'cancelada' || $estadoActual === 'terminada') {
        $accion = 'reactivada';
      } elseif ($estadoActual === 'entregada') {
        $accion = 'reversión de entrega';
      }

      $sqlAudit = "INSERT INTO ordenes_auditoria (id_orden, accion, id_admin, nombre_admin, motivo) 
                   VALUES (" . intval($order['id']) . ", 
                           '$accion', 
                           $idAdmin, 
                           '$nombreAdmin', 
                           '$motivoEscaped')";
      $localConnection->goQuery($sqlAudit);
    }

    // Confirmar los cambios en BD antes de la sincronización externa con WooCommerce
    $localConnection->commit();

    // Generar el regstro en Woocomemrce si la orden está terminada
    if ($order['estado'] === 'terminada' || $order['estado'] === 'entregada') {
      $sql = 'SELECT id_wp_order FROM ordenes WHERE _id = ' . intval($order['id']);
      $data = $localConnection->goQuery($sql);

      if (!empty($data) && isset($data[0]['id_wp_order']) && !is_null($data[0]['id_wp_order'])) {
        $woo = new WooMe();

        if ($order['estado'] === 'terminada') {
          // UPDATE PRODUCTS QUANTITY
          // Buscar cantidades de productos en ninesys
          $sql = 'SELECT id_woo, cantidad FROM ordenes_productos WHERE id_orden = ' . intval($order['id']);
          $productos = $localConnection->goQuery($sql);

          foreach ($productos as $key => $producto) {
            // Buscar existencia de productos en WC
            $tmpProd = $woo->getProductById($producto['id_woo']);

            // Sumar cantidades de ambas fuentes
            $tmpCantidad = (isset($tmpProd->stock_quantity) ? $tmpProd->stock_quantity : 0) + $producto['cantidad'];

            $woo->updateProductQuantity($producto['id_woo'], $tmpCantidad);
          }
        }

        if ($order['estado'] === 'entregada') {
          $woo->updateOrderStatus(intval($data[0]['id_wp_order']), 'completed');
        }
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['status' => 'success', 'data' => $data]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN PPARA EL ABONO
  $app->get('/ordenes/abono/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = "SELECT 
              b._id as id_orden, 
              COALESCE((SELECT SUM(abono) FROM abonos WHERE id_orden = b._id), 0) abono, 
              COALESCE((SELECT SUM(descuento) FROM abonos WHERE id_orden = b._id), 0) descuento, 
              COALESCE((SELECT SUM(nota_credito) FROM abonos WHERE id_orden = b._id), 0) nota_credito, 
              b.pago_total as total, 
              COALESCE((SELECT SUM(abono) + SUM(descuento) FROM abonos WHERE id_orden = b._id), 0) total_abono_descuento
            FROM ordenes b 
            WHERE b._id = " . intval($args['id']);
    $datosAbono = $localConnection->goQuery($sql);

    $object['sql'] = $sql;
    $object['data'] = $datosAbono[0];

    $localConnection->disconnect();

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
    $object = [];

    // ========== VALIDACIÓN DE DATOS DE ENTRADA ==========
    if (empty($datosAbono['id']) || !is_numeric($datosAbono['id'])) {
      return ApiResponse::validationError($response, 'Error: ID de orden inválido');
    }

    $abono_val = round(floatval($datosAbono['abono'] ?? 0), 2);
    $descuento_val = floatval($datosAbono['descuento'] ?? 0);
    $nota_credito_val = floatval($datosAbono['notaCredito'] ?? 0);

    if ($abono_val <= 0 && $descuento_val <= 0 && $nota_credito_val <= 0) {
      return ApiResponse::validationError($response, 'Error: Debe especificar un monto de abono, descuento o nota de crédito mayor a 0');
    }

    if (empty($datosAbono['empleado']) || !is_numeric($datosAbono['empleado'])) {
      return ApiResponse::validationError($response, 'Error: ID de empleado inválido');
    }

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
      $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . intval($datosAbono['id']);
      $resultVendedor = $localConnection->goQuery($sql);

      if (empty($resultVendedor) || !isset($resultVendedor[0]['responsable'])) {
        throw new Exception('Orden no encontrada');
      }
      $id_vendedor = $resultVendedor[0]['responsable'];

      // BUSCAR COMISION DEL VENDEDOR
      $sql = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . intval($id_vendedor);
      $respComision = $localConnection->goQuery($sql);

      if (empty($respComision)) {
        throw new Exception('No se encontró información del vendedor');
      }
      $respComision = $respComision[0];

      if ($respComision['comision_tipo'] === 'porcentaje') {
        $comision = floatval($respComision['comision_porcentaje']);
      } else {
        $comisionFloat = floatval($respComision['comision']);
        $comision = number_format($comisionFloat, 2, '.', '');
      }

      // PREPARAR FECHAS
      $myDate = new CustomTime();
      $now = $myDate->today();

      $detalleAbono = isset($datosAbono['tipoAbono']) ? $datosAbono['tipoAbono'] : '';

      // VERIFICAR SI YA EXISTE UN ABONO IDÉNTICO RECIENTE (últimos 10 segundos)
      // Esto previene duplicados por doble clic o reintentos rápidos
      $momentoRecienteExpr = DB_DRIVER === 'pgsql' ? "NOW() - INTERVAL '10 seconds'" : 'DATE_SUB(NOW(), INTERVAL 10 SECOND)';
      $sql_check_duplicate = "SELECT COUNT(*) as count FROM abonos
        WHERE id_orden = " . intval($datosAbono['id']) . "
        AND id_empleado = " . intval($datosAbono['empleado']) . "
        AND ABS(abono - " . $abono_val . ") < 0.01
        AND ABS(descuento - " . $descuento_val . ") < 0.01
        AND moment > $momentoRecienteExpr";
      $check_result = $localConnection->goQuery($sql_check_duplicate);

      if (!empty($check_result) && $check_result[0]['count'] > 0) {
        throw new Exception('Ya existe un abono idéntico registrado hace menos de 10 segundos. Evite hacer doble clic en el botón.');
      }

      // INSERT EN ABONOS (operación principal)
      $detalleNotaCredito = isset($datosAbono['notaCreditoDetalle']) ? $datosAbono['notaCreditoDetalle'] : '';
      // Si hay nota de crédito, usar su detalle; si no, usar el detalle del abono
      $detalleFinal = $nota_credito_val > 0 ? $detalleNotaCredito : $detalleAbono;

      $values = "'" . $now . "',";
      $values .= "'" . intval($datosAbono['id']) . "',";
      $values .= "'" . $abono_val . "',";
      $values .= "'" . $descuento_val . "',";
      $values .= "'" . $nota_credito_val . "',";
      $values .= "'" . intval($datosAbono['empleado']) . "',";
      $values .= "'" . addslashes($detalleFinal) . "'";

      $sql = 'INSERT INTO abonos(moment, id_orden, abono, descuento, nota_credito, id_empleado, detalle) VALUES (' . $values . ')';
      $resultAbono = $localConnection->goQuery($sql);

      if (isset($resultAbono['status']) && $resultAbono['status'] === 'error') {
        throw new Exception('Error al insertar abono: ' . ($resultAbono['message'] ?? 'desconocido'));
      }



      // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
      $tasa_peso = isset($datosAbono['tasa_peso']) && is_numeric($datosAbono['tasa_peso']) ? floatval($datosAbono['tasa_peso']) : 1;
      $tasa_dolar = isset($datosAbono['tasa_dolar']) && is_numeric($datosAbono['tasa_dolar']) ? floatval($datosAbono['tasa_dolar']) : 1;
      $responsable = isset($datosAbono['responsable']) ? intval($datosAbono['responsable']) : intval($datosAbono['empleado']);
      $detalleAbonoOrden = addslashes('Abono a Orden #' . intval($datosAbono['id']));

      if (intval($datosAbono['montoDolaresEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . intval($datosAbono['id']) . "', 'Dólares', 'Efectivo', '" . floatval($datosAbono['montoDolaresEfectivo']) . "', '1')";
        $localConnection->goQuery($sql);
        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . floatval($datosAbono['montoDolaresEfectivo']) . "', 'Dólares', 1, '" . addslashes($datosAbono['tipoAbono']) . "', '" . $responsable . "', '" . $detalleAbonoOrden . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoDolaresZelle'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . addslashes($datosAbono['detalleZelle'] ?? '') . "', '" . intval($datosAbono['id']) . "', 'Dólares', 'Zelle', '" . floatval($datosAbono['montoDolaresZelle']) . "', '1')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoDolaresPanama'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (detalle, tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['detallePanama'] ?? '') . "', '" . addslashes($datosAbono['tipoAbono']) . "', '" . intval($datosAbono['id']) . "', 'Dólares', 'Panamá', '" . floatval($datosAbono['montoDolaresPanama']) . "', '1')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoPesosEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . intval($datosAbono['id']) . "', 'Pesos', 'Efectivo', '" . floatval($datosAbono['montoPesosEfectivo']) . "', '" . $tasa_peso . "')";
        $localConnection->goQuery($sql);
        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . floatval($datosAbono['montoPesosEfectivo']) . "', 'Pesos', '" . $tasa_peso . "', '" . addslashes($datosAbono['tipoAbono']) . "', '" . $responsable . "', '" . $detalleAbonoOrden . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoPesosTransferencia'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . addslashes($datosAbono['detallePesosTransferencia'] ?? '') . "', '" . intval($datosAbono['id']) . "', 'Pesos', 'Transferencia', '" . floatval($datosAbono['montoPesosTransferencia']) . "', '" . $tasa_peso . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoBolivaresEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . intval($datosAbono['id']) . "', 'Bolívares', 'Efectivo', '" . floatval($datosAbono['montoBolivaresEfectivo']) . "', '" . $tasa_dolar . "')";
        $localConnection->goQuery($sql);
        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . floatval($datosAbono['montoBolivaresEfectivo']) . "', 'Bolívares', '" . $tasa_dolar . "', '" . addslashes($datosAbono['tipoAbono']) . "', '" . $responsable . "', '" . $detalleAbonoOrden . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoBolivaresPunto'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . intval($datosAbono['id']) . "', 'Bolívares', 'Punto', '" . floatval($datosAbono['montoBolivaresPunto']) . "', '" . $tasa_dolar . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoBolivaresPagomovil'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . addslashes($datosAbono['detallePagomovil'] ?? '') . "', '" . intval($datosAbono['id']) . "', 'Bolívares', 'Pagomovil', '" . floatval($datosAbono['montoBolivaresPagomovil']) . "', '" . $tasa_dolar . "')";
        $localConnection->goQuery($sql);
      }

      if (intval($datosAbono['montoBolivaresTransferencia'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . addslashes($datosAbono['tipoAbono']) . "', '" . addslashes($datosAbono['detalleBolivaresTransferencia'] ?? '') . "', '" . intval($datosAbono['id']) . "', 'Bolívares', 'Transferencia', '" . floatval($datosAbono['montoBolivaresTransferencia']) . "', '" . $tasa_dolar . "')";
        $localConnection->goQuery($sql);
      }

      // ACTUALIZAR TOTALES EN ORDENES
      if ($abono_val > 0 || $descuento_val > 0 || $nota_credito_val > 0) {
        $sql_update_totales = "UPDATE ordenes SET 
              pago_abono = pago_abono + {$abono_val},
              pago_descuento = pago_descuento + {$descuento_val},
              pago_nota_credito = pago_nota_credito + {$nota_credito_val}
              WHERE _id = " . intval($datosAbono['id']);
        $resultUpdate = $localConnection->goQuery($sql_update_totales);

        if (isset($resultUpdate['status']) && $resultUpdate['status'] === 'error') {
          throw new Exception('Error al actualizar totales de la orden');
        }
      }

      // OBTENER ULTIMO DE LA TABLA metodos_de_pago
      $sql_max_id = 'SELECT MAX(_id) last_id FROM metodos_de_pago';
      $last_id_pago = $localConnection->goQuery($sql_max_id)[0]['last_id'];

      // GUARDAR PAGO DEL VENDEDOR
      $comision_vendedor = number_format((floatval($datosAbono['abono']) * floatval($comision) / 100), 2, '.', '');
      $sql = "INSERT INTO pagos (detalle, estatus, monto_pago, id_empleado, id_orden, id_metodos_de_pago) VALUES ('Comercialización', 'aprobado', " . $comision_vendedor . ', ' . intval($id_vendedor) . ', ' . intval($datosAbono['id']) . ', ' . intval($last_id_pago) . ')';
      $localConnection->goQuery($sql);

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      // Preparar mensaje de éxito
      if ($nota_credito_val > 0) {
        $tipoOperacion = 'Nota de crédito';
        $montoOperacion = $nota_credito_val;
      } elseif ($abono_val > 0) {
        $tipoOperacion = 'Abono';
        $montoOperacion = $abono_val;
      } else {
        $tipoOperacion = 'Descuento';
        $montoOperacion = $descuento_val;
      }
      $mensaje = $tipoOperacion . ' de $' . number_format($montoOperacion, 2) . ' registrado correctamente para la orden #' . $datosAbono['id'];

      return ApiResponse::success($response, $mensaje, [
        'id_orden' => intval($datosAbono['id']),
        'abono' => $abono_val,
        'descuento' => $descuento_val,
        'nota_credito' => $nota_credito_val
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN EN CASO DE ERROR ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /orden/abono: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al registrar el abono. Por favor intente nuevamente.', $e);
    }
  });

  // GUARDAR OBSERVACIONES DESDE EDITAR EN COMERCIALIZACION
  $app->post('/orden/edit/obs', function (Request $request, Response $response, $args) {
    $datosObs = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Las observaciones se guardan en la tabla dedicada ordenes_observaciones
    // (patron upsert: una fila por orden), ya que la columna ordenes.observaciones ya no existe.
    $sql_check = 'SELECT _id FROM ordenes_observaciones WHERE id_orden = ?';
    $existente = $localConnection->goQuery($sql_check, [$datosObs['id']]);

    if (empty($existente)) {
      $sql = 'INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)';
      $data = $localConnection->goQuery($sql, [$datosObs['id'], $datosObs['obs']]);
    } else {
      $sql = 'UPDATE ordenes_observaciones SET observaciones = ? WHERE id_orden = ?';
      $data = $localConnection->goQuery($sql, [$datosObs['obs'], $datosObs['id']]);
    }

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

    $object['fields'][3]['key'] = 'nota_credito';
    $object['fields'][3]['label'] = 'Nota de Crédito';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][4]['key'] = 'detalle';
    $object['fields'][4]['label'] = 'Detalle';
    $object['fields'][4]['sortable'] = false;
    //  Obtener historial completo (Pagos, NC y Descuentos)
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT 
                CONCAT('P-', met._id::text) as _id, 
                ord._id as orden, 
                ord.responsable as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                met.metodo_pago as metodo_pago, 
                met.monto as monto, 
                met.detalle as detalle, 
                met.tasa as tasa, 
                met.moneda as moneda, 
                TO_CHAR(met.moment, 'DD/MM/YYYY') AS fecha, 
                TO_CHAR(met.moment, 'HH12:MI AM') AS hora,
                met.monto as abono, 0 as descuento, 0 as nota_credito,
                met.moment
            FROM metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE met.id_orden = " . intval($args['id']) . "
            
            UNION ALL
            
            SELECT 
                CONCAT('NC-', ab._id::text) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Nota de Crédito' as metodo_pago, 
                ab.nota_credito as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                TO_CHAR(ab.moment, 'DD/MM/YYYY') AS fecha, 
                TO_CHAR(ab.moment, 'HH12:MI AM') AS hora,
                0 as abono, 0 as descuento, ab.nota_credito as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = " . intval($args['id']) . " AND ab.nota_credito > 0
            
            UNION ALL
            
            SELECT 
                CONCAT('D-', ab._id::text) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Descuento' as metodo_pago, 
                ab.descuento as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                TO_CHAR(ab.moment, 'DD/MM/YYYY') AS fecha, 
                TO_CHAR(ab.moment, 'HH12:MI AM') AS hora,
                0 as abono, ab.descuento as descuento, 0 as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = " . intval($args['id']) . " AND ab.descuento > 0
            
            ORDER BY moment DESC";
    } else {
      $sql = "SELECT 
                CONCAT('P-', met._id) as _id, 
                ord._id as orden, 
                ord.responsable as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                met.metodo_pago as metodo_pago, 
                met.monto as monto, 
                met.detalle as detalle, 
                met.tasa as tasa, 
                met.moneda as moneda, 
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora,
                met.monto as abono, 0 as descuento, 0 as nota_credito,
                met.moment
            FROM metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE met.id_orden = " . intval($args['id']) . "
            
            UNION ALL
            
            SELECT 
                CONCAT('NC-', ab._id) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Nota de Crédito' as metodo_pago, 
                ab.nota_credito as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                DATE_FORMAT(ab.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(ab.moment, '%h:%i %p') AS hora,
                0 as abono, 0 as descuento, ab.nota_credito as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = " . intval($args['id']) . " AND ab.nota_credito > 0
            
            UNION ALL
            
            SELECT 
                CONCAT('D-', ab._id) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Descuento' as metodo_pago, 
                ab.descuento as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                DATE_FORMAT(ab.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(ab.moment, '%h:%i %p') AS hora,
                0 as abono, ab.descuento as descuento, 0 as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = " . intval($args['id']) . " AND ab.descuento > 0
            
            ORDER BY moment DESC";
    }

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

    // Obtener datos salariales del empleado
    $sqlEmpleado = "SELECT salario_tipo, salario_monto, salario_periodo, comision, comision_tipo 
                    FROM api_empresas.empresas_usuarios 
                    WHERE id_usuario = {$args['id_empleado']}";
    $datosEmpleado = $localConnection->goQuery($sqlEmpleado);
    $object['datos_empleado'] = !empty($datosEmpleado) ? $datosEmpleado[0] : null;

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
            JOIN pagos b ON b.id_orden = a.id_orden AND b.detalle = '{$object['departamento']}'
            JOIN ordenes c ON c._id = a.id_orden AND c.status NOT LIKE 'entregada' AND c.status NOT LIKE 'cancelada' AND c.status NOT LIKE 'terminada' 
            WHERE b.id_empleado = {$args['id_empleado']} AND b.fecha_pago IS NULL
        ";
    $object['ordenes_terminadas'] = $localConnection->goQuery($sql);

    $sql = "SELECT
            a._id AS id_revision,
            a.id_orden,
            p.product AS producto,
            'Diseño' AS departamento,
            0 AS monto_pago,
            'pendiente' AS progreso
        FROM
            revisiones a
        LEFT JOIN products p ON p._id = a.id_product
        JOIN ordenes c ON c._id = a.id_orden
        WHERE 
            a.id_empleado = {$args['id_empleado']}
            AND a.estatus != 'Aprobado'
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

    $sql = 'SELECT departamento, tipo FROM departamentos WHERE _id = ' . $args['id_departamento'];
    $departamento = $localConnection->goQuery($sql);
    $object['departamento'] = $departamento[0]['departamento'];
    $departamentoTipo = $departamento[0]['tipo'] ?? null;

    // Obtener datos salariales del empleado
    $sqlEmpleado = "SELECT salario_tipo, salario_monto, salario_periodo, comision, comision_tipo 
                    FROM api_empresas.empresas_usuarios 
                    WHERE id_usuario = {$args['id_empleado']}";
    $datosEmpleado = $localConnection->goQuery($sqlEmpleado);
    $object['datos_empleado'] = !empty($datosEmpleado) ? $datosEmpleado[0] : null;

    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                string_agg(DISTINCT e.product, ', ') as product,
                (SELECT cliente_nombre FROM ordenes WHERE _id = a.id_orden) cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = a.id_orden) total_productos,
                (SELECT comision_tipo FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL LIMIT 1) AS comision_tipo,
                (SELECT SUM(monto_pago) FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL) AS monto_pago,
                eu.salario_tipo,
                eu.salario_monto,
                eu.salario_periodo,
                EXTRACT(EPOCH FROM (a.fecha_terminado::timestamp - a.fecha_inicio::timestamp))::int AS tiempo_empleado,
                SUM(c.tiempo * b.cantidad) AS tiempo_estimado_de_produccion,
                (EXTRACT(EPOCH FROM (a.fecha_terminado::timestamp - a.fecha_inicio::timestamp))::int - SUM(c.tiempo * b.cantidad)) rendimiento,
                SUM(b.cantidad) unidades,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                string_agg(DISTINCT b.id_woo::text, ',') id_producto,
                'EficienciaInsumos' eficiencia_insumos,
                string_agg(DISTINCT b.talla, ', ') as talla
            FROM
                lotes_detalles_empleados_asignados a
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            LEFT JOIN api_empresas.empresas_usuarios eu ON a.id_empleado = eu.id_usuario
            WHERE a.id_empleado = {$args['id_empleado']} 
              AND a.id_departamento = {$args['id_departamento']} 
              AND a.fecha_terminado IS NOT NULL 
              AND EXISTS (SELECT 1 FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL)
            GROUP BY a._id, a.id_orden, a.fecha_inicio, a.fecha_terminado, a.progreso, eu.salario_tipo, eu.salario_monto, eu.salario_periodo
            ORDER BY a.id_orden ASC
        ";
    } else {
      $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                GROUP_CONCAT(DISTINCT e.product SEPARATOR ', ') as product,
                (SELECT cliente_nombre FROM ordenes WHERE _id = a.id_orden) cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = a.id_orden) total_productos,
                (SELECT comision_tipo FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL LIMIT 1) AS comision_tipo,
                (SELECT SUM(monto_pago) FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL) AS monto_pago,
                eu.salario_tipo,
                eu.salario_monto,
                eu.salario_periodo,
                TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) AS tiempo_empleado,
                SUM(c.tiempo * b.cantidad) AS tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) - SUM(c.tiempo * b.cantidad)) rendimiento,
                SUM(b.cantidad) unidades,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                GROUP_CONCAT(DISTINCT b.id_woo) id_producto,
                'EficienciaInsumos' eficiencia_insumos,
                GROUP_CONCAT(DISTINCT b.talla SEPARATOR ', ') as talla
            FROM
                lotes_detalles_empleados_asignados a
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            LEFT JOIN api_empresas.empresas_usuarios eu ON a.id_empleado = eu.id_usuario
            WHERE a.id_empleado = {$args['id_empleado']} 
              AND a.id_departamento = {$args['id_departamento']} 
              AND a.fecha_terminado IS NOT NULL 
              AND EXISTS (SELECT 1 FROM pagos WHERE id_lotes_detalles = a._id AND fecha_pago IS NULL)
            GROUP BY a.id_orden
            ORDER BY a.id_orden ASC
        ";
    }
    $object['sql_terminadas'] = $sql;

    $pagos = $localConnection->goQuery($sql);
    $object['ordenes_terminadas'] = $pagos;

    // ORDENES PENDIENTES  ((SUM(c.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_variable,
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                ord.cliente_nombre cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                d.comision_tipo,
                EXTRACT(EPOCH FROM (a.fecha_terminado::timestamp - a.fecha_inicio::timestamp))::int AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (EXTRACT(EPOCH FROM (a.fecha_terminado::timestamp - a.fecha_inicio::timestamp))::int - c.tiempo) rendimiento,
                b.cantidad unidades,
                SUM(b.cantidad) total_productos,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                b.id_woo id_producto,
                e.product,
                b.talla,
                ofo.orden_fila,
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
            GROUP BY a._id, a.id_orden, ord.cliente_nombre, a.fecha_inicio, a.fecha_terminado, a.progreso, d.comision_tipo, c.tiempo, b.cantidad, b.id_woo, e.product, b.talla, ofo.orden_fila, e.comision, d.comision
            ORDER BY ofo.orden_fila ASC, a.id_orden DESC, a.progreso ASC
        ";
    } else {
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
    }
    $pendientes = $localConnection->goQuery($sql);
    $object['sql_pendientes'] = $sql;
    $object['ordenes_pendientes'] = $pendientes;

    // ORDENES PARA CALCULO DE TIEMPO
    if (DB_DRIVER === 'pgsql') {
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
            MAX(TO_CHAR(NULLIF(d.fecha_entrega, '')::date, 'DD-MM-YYYY')) AS fecha_entrega,
            MAX(y._id) AS lotes_detalles_empleados_asignados,
            y.id_departamento,
            (SELECT MIN(dep.orden_proceso) FROM lotes_detalles_empleados_asignados ldea JOIN departamentos dep ON ldea.id_departamento = dep._id WHERE ldea.id_orden = y.id_orden) AS orden_proceso_min,
            (SELECT orden_proceso FROM departamentos WHERE _id = 4) AS orden_proceso_departamento,            
            (SELECT orden_proceso FROM departamentos WHERE _id = MAX(c.id_departamento_actual)) AS orden_proceso,
            MAX(c.id_departamento_actual) AS id_departamento_actual,
            y.id_orden AS orden,
            string_agg(DISTINCT a.name, ', ') AS producto,
            y.id_empleado,
            MAX(x.detalle) AS detalle_reposicion,
            string_agg(DISTINCT (SELECT nombre FROM sizes WHERE _id = a.id_size), ', ') AS talla,
            string_agg(DISTINCT a.corte, ', ') AS corte,
            string_agg(DISTINCT a.tela, ', ') AS tela,
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
        WHERE  
            (y.id_empleado = 20)
            AND (y.id_departamento = 4)
        GROUP BY
            y.id_orden, y.id_empleado, y.id_departamento
        ORDER BY
            orden_fila ASC,
            y.id_orden DESC;
        ";
    } else {
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
        -- ========================================================================
        GROUP BY
            y.id_orden, y.id_empleado, y.id_departamento
        ORDER BY
            orden_fila ASC,
            y.id_orden DESC;
        ";
    }

    $ordenes = $localConnection->goQuery($sql);
    $object['sql_ordenes'] = $sql;
    $object['ordenes'] = $ordenes;

    // EFICIENCIA DE INSUMOS
    if (DB_DRIVER === 'pgsql') {
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
                    ldea.id_orden,
                    ldea.id_empleado,
                    ldea.id_departamento,
                    string_agg(DISTINCT p.product, ', ') AS nombre_producto,
                    string_agg(DISTINCT s.nombre, ', ') AS talla,
                    cip.nombre AS nombre_insumo,
                    pia.id_catalogo_insumos_productos,
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
                    ldea.id_empleado = {$args['id_empleado']}
                    AND ldea.id_departamento = {$args['id_departamento']}
                    AND pia.id_catalogo_insumos_productos IS NOT NULL
                GROUP BY
                    ldea.id_orden, ldea.id_empleado, ldea.id_departamento,
                    cip.nombre, pia.id_catalogo_insumos_productos,
                    eu.nombre, dep.departamento, ldea.fecha_inicio
            ) AS est
        LEFT JOIN
            (
                SELECT
                    im.id_orden,
                    im.id_departamento,
                    im.id_empleado,
                    im.id_catalogo_insumos_prodcutos,
                    SUM((im.valor_inicial - im.valor_final) * COALESCE(inv.rendimiento, 1)) AS consumo_real_total
                FROM
                    inventario_movimientos im
                LEFT JOIN inventario inv ON im.id_insumo = inv._id
                WHERE
                    im.id_empleado = {$args['id_empleado']}
                    AND im.id_departamento = {$args['id_departamento']}
                GROUP BY
                    im.id_orden, im.id_departamento, im.id_empleado, im.id_catalogo_insumos_prodcutos
            ) AS consumo_r ON est.id_orden = consumo_r.id_orden
                          AND est.id_departamento = consumo_r.id_departamento
                          AND est.id_empleado = consumo_r.id_empleado
                          AND est.id_catalogo_insumos_productos = consumo_r.id_catalogo_insumos_prodcutos;
        ";
    } else {
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
                    GROUP_CONCAT(DISTINCT p.product SEPARATOR ', ') AS nombre_producto,
                    GROUP_CONCAT(DISTINCT s.nombre SEPARATOR ', ') AS talla,
                    cip.nombre AS nombre_insumo,
                    pia.id_catalogo_insumos_productos,
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
                    ldea.id_empleado = {$args['id_empleado']}
                    AND ldea.id_departamento = {$args['id_departamento']}
                    AND pia.id_catalogo_insumos_productos IS NOT NULL
                GROUP BY
                    ldea.id_orden, ldea.id_empleado, ldea.id_departamento,
                    cip.nombre, pia.id_catalogo_insumos_productos,
                    eu.nombre, dep.departamento, ldea.fecha_inicio
            ) AS est
        LEFT JOIN
            (
                SELECT
                    im.id_orden,
                    im.id_departamento,
                    im.id_empleado,
                    im.id_catalogo_insumos_prodcutos,
                    SUM((im.valor_inicial - im.valor_final) * COALESCE(inv.rendimiento, 1)) AS consumo_real_total
                FROM
                    inventario_movimientos im
                LEFT JOIN inventario inv ON im.id_insumo = inv._id
                WHERE
                    im.id_empleado = {$args['id_empleado']}
                    AND im.id_departamento = {$args['id_departamento']}
                GROUP BY
                    im.id_orden, im.id_departamento, im.id_empleado, im.id_catalogo_insumos_prodcutos
            ) AS consumo_r ON est.id_orden = consumo_r.id_orden
                          AND est.id_departamento = consumo_r.id_departamento
                          AND est.id_empleado = consumo_r.id_empleado
                          AND est.id_catalogo_insumos_productos = consumo_r.id_catalogo_insumos_prodcutos;
        ";
    }
    $reficiencia_insumos = $localConnection->goQuery($sql);
    $object['sql_eficiencia_insumos'] = $sql;
    $object['eficiencia_insumos'] = $reficiencia_insumos;

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

    // NOTA: $departamento es el array crudo de goQuery(), no un string -- estas
    // condiciones nunca eran ciertas para NINGÚN departamento antes de este fix
    // (bug preexistente, no introducido por la migración a Postgres). Se usa
    // $object['departamento'] (el nombre ya extraído) y se agrega el chequeo de
    // $departamentoTipo para reconocer cualquier departamento con
    // comportamiento "corte", no solo el que se llama literalmente "Corte".
    if ($departamentoTipo === 'corte' || $object['departamento'] === 'Corte' || $object['departamento'] === 'Estampado' || $object['departamento'] === 'Limpieza' || $object['departamento'] === 'Revisión' || $object['departamento'] === 'Impresión') {
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

    if ($object['departamento'] === 'Comercialización' || $object['departamento'] === 'Administración') {
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

    if ($object['departamento'] === 'Diseño') {
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

  // DASHBOARD COMERCIALIZACIÓN
  $app->get('/comercializacion/dashboard/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_empleado = (int) $args['id_empleado'];
    $id_departamento = (int) $args['id_departamento']; // Disponible para lógica futura de validación
    $object = [];

    // Helper para respuesta JSON segura
    $sendJson = function ($data, $status = 200) use ($response) {
      $payload = json_encode($data);
      $response->getBody()->write($payload);
      return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    };

    // 1. Resumen de Órdenes
    $queries = [
      'creadas' => (DB_DRIVER === 'pgsql' ? "SELECT COUNT(*) as total FROM ordenes WHERE EXTRACT(WEEK FROM fecha_creacion) = EXTRACT(WEEK FROM NOW()) AND EXTRACT(YEAR FROM fecha_creacion) = EXTRACT(YEAR FROM NOW()) AND responsable = $id_empleado" : "SELECT COUNT(*) as total FROM ordenes WHERE YEARWEEK(fecha_creacion, 1) = YEARWEEK(NOW(), 1) AND responsable = $id_empleado"),
      'terminadas' => "SELECT COUNT(*) as total FROM ordenes WHERE status = 'terminada' AND responsable = $id_empleado",
      'entregadas' => "SELECT COUNT(*) as total FROM ordenes WHERE status = 'entregada' AND responsable = $id_empleado"
    ];

    $resumen = [];
    foreach ($queries as $key => $sql) {
      $res = $localConnection->goQuery($sql);
      if (isset($res['status']) && $res['status'] === 'error') {
        return $sendJson(['error' => "Error SQL $key", 'details' => $res], 500);
      }
      $resumen[$key] = (int) $res[0]['total'];
    }
    $object['resumen_ordenes'] = $resumen;

    // 2. Estado de Órdenes (Donut)
    $sqlEstados = "SELECT status, COUNT(*) as cantidad 
                   FROM ordenes 
                   WHERE status IN ('en espera', 'activa', 'pausada', 'terminada') 
                   AND responsable = $id_empleado 
                   GROUP BY status";
    $estadosData = $localConnection->goQuery($sqlEstados);
    if (isset($estadosData['status']) && $estadosData['status'] === 'error') {
      return $sendJson(['error' => 'Error SQL Estados', 'details' => $estadosData], 500);
    }

    $estados = ['en_espera' => 0, 'activa' => 0, 'pausada' => 0, 'terminada' => 0];
    foreach ($estadosData as $row) {
      $statusKey = str_replace(' ', '_', strtolower($row['status']));
      if (isset($estados[$statusKey])) {
        $estados[$statusKey] = (int) $row['cantidad'];
      }
    }
    $object['estado_ordenes'] = $estados;

    // 3. Pagos de la Semana
    if (DB_DRIVER === 'pgsql') {
      $sqlPagos = "SELECT EXTRACT(DOW FROM a.moment)::int as dia_num, SUM(a.abono) as total 
                 FROM abonos a
                 JOIN ordenes o ON a.id_orden = o._id
                 WHERE EXTRACT(WEEK FROM a.moment) = EXTRACT(WEEK FROM NOW()) AND EXTRACT(YEAR FROM a.moment) = EXTRACT(YEAR FROM NOW())
                 AND o.responsable = $id_empleado
                 GROUP BY dia_num 
                 ORDER BY dia_num ASC";
    } else {
      $sqlPagos = "SELECT DATE_FORMAT(a.moment, '%w') as dia_num, SUM(a.abono) as total 
                 FROM abonos a
                 JOIN ordenes o ON a.id_orden = o._id
                 WHERE YEARWEEK(a.moment, 1) = YEARWEEK(NOW(), 1)
                 AND o.responsable = $id_empleado
                 GROUP BY dia_num 
                 ORDER BY dia_num ASC";
    }
    $pagosData = $localConnection->goQuery($sqlPagos);
    if (isset($pagosData['status']) && $pagosData['status'] === 'error') {
      return $sendJson(['error' => 'Error SQL Pagos', 'details' => $pagosData], 500);
    }

    $pagosSemana = array_fill(0, 7, 0);
    foreach ($pagosData as $row) {
      $pagosSemana[(int) $row['dia_num']] = (float) $row['total'];
    }
    // Reordenar: Lunes(1) a Domingo(0)
    $object['pagos_semana'] = [
      $pagosSemana[1],
      $pagosSemana[2],
      $pagosSemana[3],
      $pagosSemana[4],
      $pagosSemana[5],
      $pagosSemana[6],
      $pagosSemana[0]
    ];

    // 4. Progreso de Órdenes (Detallado)
    $sqlProgreso = "SELECT o._id as id_orden, o.cliente_nombre, d.departamento, d.orden_proceso, o.fecha_inicio
                         FROM ordenes o 
                         JOIN lotes l ON o._id = l.id_orden 
                         JOIN departamentos d ON l.id_departamento_actual = d._id 
                         WHERE o.status IN ('activa', 'en espera') 
                         AND o.responsable = $id_empleado
                         ORDER BY d.orden_proceso DESC, o._id DESC";

    $progresoData = $localConnection->goQuery($sqlProgreso);
    if (isset($progresoData['status']) && $progresoData['status'] === 'error') {
      return $sendJson(['error' => 'Error SQL Progreso', 'details' => $progresoData], 500);
    }

    $ordenesProgreso = [];
    foreach ($progresoData as $row) {
      // Calcular porcentaje basado en orden_proceso (asumiendo max 6 pasos aprox, o solo usar el paso raw)
      // Ajustar esto según la lógica de negocio real. Por ahora pasamos el paso crudo.
      $ordenesProgreso[] = [
        'id_orden' => (int) $row['id_orden'],
        'cliente' => $row['cliente_nombre'],
        'departamento' => $row['departamento'],
        'paso' => (int) $row['orden_proceso'],
        'fecha_inicio' => $row['fecha_inicio']
      ];
    }

    $object['ordenes_progreso'] = $ordenesProgreso;

    // 5. Finanzas (Cuentas por Cobrar)
    // Se consideran ordenes activas, en espera, terminadas y pausadas (cartera activa)
    $sqlFinanzas = "SELECT 
                      SUM(o.pago_total - o.pago_descuento) as total_vendido_neto,
                      SUM(COALESCE((SELECT SUM(ab.abono) FROM abonos ab WHERE ab.id_orden = o._id), 0)) as total_cobrado
                    FROM ordenes o
                    WHERE o.responsable = $id_empleado
                    AND o.status IN ('activa', 'En espera', 'terminada', 'pausada')";

    $finanzasData = $localConnection->goQuery($sqlFinanzas);

    $totalVendido = 0;
    $totalCobrado = 0;

    if (!isset($finanzasData['status']) || $finanzasData['status'] !== 'error') {
      if (!empty($finanzasData)) {
        $totalVendido = (float) ($finanzasData[0]['total_vendido_neto'] ?? 0);
        $totalCobrado = (float) ($finanzasData[0]['total_cobrado'] ?? 0);
      }
    }

    $object['finanzas'] = [
      'total_vendido' => $totalVendido,
      'total_cobrado' => $totalCobrado,
      'saldo_pendiente' => $totalVendido - $totalCobrado
    ];

    // Mantener estructura anterior vacía por compatibilidad si es necesario, o eliminarla.
    // Se elimina ordenes_por_departamento ya que se reemplaza por esta visualización.
    $object['ordenes_por_departamento'] = [
      'labels' => [],
      'valores' => []
    ];

    return $sendJson($object);
  });
  // This block was part of the old code and is now outside the new route definition.
  // It seems to be a remnant or an error in the original structure.
  // Based on the instruction, the entire old route should be replaced.
  // The new route ends with `return $sendJson($object);` and `});`.
  // The lines below were part of the old route's execution flow after the initial `return $response->withHeader('Content-Type', 'application/json');`
  // but before the `localConnection = new LocalDB();` for the next section.
  // Since the new route has its own `return`, these lines are now orphaned.
  // I will remove them as they are part of the replaced logic.
  /*
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json');

    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'id_orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'moment';
    $object['fields'][1]['label'] = 'Fecha y hora';

    $object['fields'][2]['key'] = 'abono';
    $object['fields'][2]['label'] = 'Abono';

    $object['fields'][3]['key'] = 'descuento';
    $object['fields'][3]['label'] = 'Descuento';

    if (DB_DRIVER === 'pgsql') {
      $sql = 'SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a WHERE EXTRACT(WEEK FROM a.moment) = EXTRACT(WEEK FROM NOW()) AND EXTRACT(YEAR FROM a.moment) = EXTRACT(YEAR FROM NOW()) ORDER BY a.id_orden ASC';
    } else {
      $sql = 'SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a  WHERE YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC';
    }
    $datosAbono = $localConnection->goQuery($sql);
    $object['items'] = $datosAbono;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  */

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
      $sql = 'SELECT _id, name, id_woo cod, cantidad, talla, corte, precio_unitario precio FROM ordenes_productos WHERE id_orden = ' . $id;
      $object['productos'] = $localConnection->goQuery($sql);
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // NOTA: GET /ordenes-sin-asignar/{id_vendedor} vive en su propio archivo dedicado
  // ordenes-sin-asignacion.php (registrado por separado en app/routes.php). Esta copia
  // duplicada quedó huérfana aquí tras una modularización anterior; no se restaura para
  // evitar el conflicto de enrutamiento "Cannot register two routes matching..." con la
  // versión canónica.





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

  // CREAR NUEVO PRESUPUESTO
  $app->post('/presupuesto/nuevo', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id'] ?? 'null');
    $arr['nombre'] = json_decode($newJson['nombre'] ?? 'null');
    $arr['vinculada'] = json_decode($newJson['vinculada'] ?? '0');
    $arr['apellido'] = json_decode($newJson['apellido'] ?? 'null');
    $arr['cedula'] = json_decode($newJson['cedula'] ?? 'null');
    $arr['telefono'] = json_decode($newJson['telefono'] ?? 'null');
    $arr['email'] = json_decode($newJson['email'] ?? 'null');
    $arr['direccion'] = json_decode($newJson['direccion'] ?? 'null');
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega'] ?? 'null');
    $arr['misProductos'] = json_decode($newJson['productos'] ?? '[]', true);
    $arr['obs'] = json_decode($newJson['obs'] ?? 'null');
    $arr['total'] = json_decode($newJson['total'] ?? '0');
    $arr['abono'] = json_decode($newJson['abono'] ?? '0');
    $arr['descuento'] = json_decode($newJson['descuento'] ?? '0');
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle'] ?? 'null');
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico'] ?? 'false');
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas'] ?? 'false');
    $arr['responsable'] = json_decode($newJson['responsable'] ?? 'null');
    $arr['sales_commission'] = json_decode($newJson['sales_commission'] ?? 'true');

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo'] ?? '0');
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle'] ?? 'null');
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle'] ?? '0');
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle'] ?? 'null');
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama'] ?? '0');
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle'] ?? 'null');
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo'] ?? '0');
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle'] ?? 'null');
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia'] ?? '0');
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle'] ?? 'null');
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo'] ?? '0');
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle'] ?? 'null');
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto'] ?? '0');
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle'] ?? 'null');
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil'] ?? '0');
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle'] ?? 'null');
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia'] ?? '0');
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle'] ?? 'null');
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar'] ?? '1');
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso'] ?? '1');

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
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

    // Validar id_wp
    $id_wp_val = $arr['id_wp'];
    $id_wp_param = (!empty($id_wp_val) && is_numeric($id_wp_val)) ? $id_wp_val : null;

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO presupuestos (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $localConnection->beginTransaction();
    try {
    $insertPresupResult = $localConnection->goQuery($sql, [
      $newJson['responsable'],
      $now,
      $arr['descuento'],
      $arr['abono'],
      $id_wp_param,
      $arr['cedula'],
      $newJson['obs'] ?? '',
      $newJson['total'],
      $cliente,
      date('Y-m-d'),
      $newJson['fechaEntrega'],
      date('Y-m-d'),
      'En espera',
    ]);
    $object['nuevo_presupuesto_response'] = json_encode($insertPresupResult);

    // ID del presupuesto recién insertado (evita race condition de SELECT MAX)
    $last_id = intval($insertPresupResult['insert_id']);
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
    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        $cat_name = 'Uncatagorized';
        $id_categoria = (isset($decodedObj['categoria']) && !empty($decodedObj['categoria'])) ? intval($decodedObj['categoria']) : 0;

        // Nuevos campos: id_products_attributes, id_size, id_tela
        $id_attr = null;
        if (!empty($decodedObj['atributos_seleccionados']) && is_array($decodedObj['atributos_seleccionados'])) {
          $id_attr = $decodedObj['atributos_seleccionados'][0]['value'] ?? null;
        }

        $id_size = (isset($decodedObj['talla']) && is_numeric($decodedObj['talla'])) ? $decodedObj['talla'] : null;
        $id_tela = (isset($decodedObj['tela']) && is_numeric($decodedObj['tela'])) ? $decodedObj['tela'] : null;

        $sql2 = 'INSERT INTO presupuestos_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela, id_products_attributes, id_size, id_tela) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $object['sql_presupuestos_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2, [
          $now,
          $decodedObj['precio'],
          $decodedObj['precioWoo'] ?? $decodedObj['precio'],
          $decodedObj['producto'],
          $last_id,
          $decodedObj['cod'],
          $decodedObj['cantidad'],
          $id_categoria,
          $cat_name,
          $decodedObj['talla'] ?? '',
          $decodedObj['corte'] ?? '',
          $decodedObj['tela'] ?? '',
          $id_attr,
          $id_size,
          $id_tela,
        ]);
      }
    }

    $localConnection->commit();
    } catch (\Throwable $e) {
      $localConnection->rollback();
      throw $e;
    }

    // enviar email - obtener formato
    // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    // $object["resultBuscar"] = $resultBuscar["object"];
    // $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
    // $object["sendMail"] = $result;


    // Agregar indicadores de éxito para el frontend
    $object['orden_creada'] = true;
    $object['response'] = [
      'status' => 'success',
      'message' => 'Presupuesto creado exitosamente',
      'id_presupuesto' => $last_id
    ];

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER LISTA DE PRESUPUESTOS FINALIZADOS (guardados en tabla presupuestos)
  $app->get('/presupuestos/lista', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT p._id, p.cliente_nombre, p.cliente_cedula, p.pago_total, 
                   p.fecha_creacion, p.fecha_entrega, p.status, p.observaciones, p.responsable,
                   u.nombre as empleado_nombre
            FROM presupuestos p
            LEFT JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
            WHERE p.status != 'Convertido'
            ORDER BY p._id DESC";

    $result = $localConnection->goQuery($sql);

    $response->getBody()->write(json_encode($result));
    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER UN PRESUPUESTO FINALIZADO ESPECÍFICO CON SUS PRODUCTOS
  $app->get('/presupuesto/detalle/{id}', function (Request $request, Response $response, $args) {
    $id_presupuesto = intval($args['id']);
    $localConnection = new LocalDB();
    $object = [];

    // Obtener datos del presupuesto
    $sql = "SELECT * FROM presupuestos WHERE _id = {$id_presupuesto}";
    $presupuesto = $localConnection->goQuery($sql);

    if (empty($presupuesto)) {
      $object['error'] = 'Presupuesto no encontrado';
      $response->getBody()->write(json_encode($object));
      $localConnection->disconnect();
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
    }

    $presupuesto = $presupuesto[0];

    // Obtener productos del presupuesto
    $sql_productos = "SELECT pp.*, 
                             s.nombre as talla_nombre,
                             ct.tela as tela_nombre,
                             pa.attribute_name as atributo_nombre
                      FROM presupuestos_productos pp
                      LEFT JOIN sizes s ON pp.id_size = s._id
                      LEFT JOIN catalogo_telas ct ON pp.id_tela = ct._id
                      LEFT JOIN products_attributes pa ON pp.id_products_attributes = pa._id
                      WHERE pp.id_orden = {$id_presupuesto}";
    $productos = $localConnection->goQuery($sql_productos);

    $presupuesto['productos'] = $productos;
    $object = $presupuesto;

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

    $sql = 'UPDATE ordenes_fila_orden SET orden_fila = ? WHERE id_orden = ?';
    $localConnection->goQuery($sql, [$data['orden_fila'], $data['id_orden']]);

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
    if (!isset($newJson['id_orden_edit']) || empty($newJson['id_orden_edit']) || !is_numeric($newJson['id_orden_edit'])) {
      $object['response']['status'] = 'error';
      $object['response']['message'] = 'No se proporcionó un ID de orden válido para editar.';
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_orden_a_editar = intval($newJson['id_orden_edit']);

    // Identificar si es una orden o un presupuesto
    $table_principal = 'ordenes';
    $table_productos = 'ordenes_productos';
    $is_presupuesto = false;

    $sql_check = "SELECT pago_abono FROM ordenes WHERE _id = {$id_orden_a_editar}";
    $res_check = $localConnection->goQuery($sql_check);

    if (empty($res_check)) {
      $sql_check_pres = "SELECT pago_abono FROM presupuestos WHERE _id = {$id_orden_a_editar}";
      $res_check = $localConnection->goQuery($sql_check_pres);
      if (!empty($res_check)) {
        $table_principal = 'presupuestos';
        $table_productos = 'presupuestos_productos';
        $is_presupuesto = true;
      }
    }

    if (empty($res_check)) {
      $object['response']['status'] = 'error';
      $object['response']['message'] = "No se encontró la orden o presupuesto con ID {$id_orden_a_editar}.";
      $response->getBody()->write(json_encode($object));
      $localConnection->disconnect();
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    // Atomicidad FK: toda la edición (productos, lotes_detalles, abonos, pagos, métodos de pago, caja, observaciones)
    // se aplica en una transacción; una violación de integridad propaga la excepción y disconnect() la revierte.
    $localConnection->beginTransaction();

    // 2. PROCESAR DATOS DEL PAYLOAD (similar al endpoint original)
    $arr = [];
    $arr['id_wp'] = $newJson['id'] ?? null;
    $arr['fechaEntrega'] = $newJson['fechaEntrega'] ?? date('Y-m-d');
    $arr['obs'] = isset($newJson['obs']) && $newJson['obs'] !== null ? addslashes($newJson['obs']) : '';
    $arr['total'] = floatval($newJson['total'] ?? 0);
    $arr['abono'] = floatval($newJson['abono'] ?? 0);
    $arr['descuento'] = floatval($newJson['descuento'] ?? 0);
    $arr['descuentoDetalle'] = $newJson['descuentoDetalle'] ?? '';
    $arr['responsable'] = intval($newJson['responsable'] ?? 0);
    $arr['sales_commission'] = (($newJson['sales_commission'] ?? 'false') === 'true');
    $arr['tasa_dolar'] = floatval($newJson['tasa_dolar'] ?? 1);
    $arr['tasa_peso'] = floatval($newJson['tasa_peso'] ?? 1);
    $nuevos_productos = json_decode($newJson['productos'] ?? '[]', true);

    // 3. MANEJO DE PRODUCTOS (INSERT, UPDATE, DELETE)
    // 3.1. Obtener productos actuales de la base de datos para comparar
    $sql_productos_actuales = "SELECT _id, _id cod, cantidad, precio_unitario, talla, tela, corte, id_products_attributes FROM {$table_productos} WHERE id_orden = {$id_orden_a_editar}";
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
        $sql_delete_prod = "DELETE FROM {$table_productos} WHERE _id = {$id_actual}";
        $localConnection->goQuery($sql_delete_prod);

        if (!$is_presupuesto) {
          // También borrar sus detalles de lote (solo para órdenes)
          $sql_delete_lote = "DELETE FROM lotes_detalles WHERE id_ordenes_productos = {$id_actual}";
          $localConnection->goQuery($sql_delete_lote);
        }
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
          $sql_update_prod = "UPDATE {$table_productos} SET
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
        $values .= floatval($decodedObj['precio'] ?? 0) . ',';
        $values .= "'" . floatval($decodedObj['precio'] ?? 0) . "',";  // precio_woo
        $values .= "'" . addslashes($decodedObj['producto'] ?? '') . "',";
        $values .= $id_orden_a_editar . ',';
        $values .= intval($decodedObj['cod'] ?? 0) . ',';
        $values .= floatval($decodedObj['cantidad'] ?? 0) . ',';
        $values .= intval($decodedObj['categoria'] ?? 0) . ',';
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

        $sql2 = "INSERT INTO {$table_productos} (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (" . $values . ', ' . $id_products_attributes . ')';

        $res_insert = $localConnection->goQuery($sql2);
        $object['sql_insert_new_product'] = $sql2;

        /* $response->getBody()->write(json_encode($res_insert));
        $localConnection->disconnect();

        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200); */

        // $last_id_ordenes_productos = $res_insert['insert_id'];

        // Lógica para insertar en lotes_detalles para el nuevo producto (solo para órdenes)
        // ... (copiar la lógica de lotes_detalles del endpoint original) ...
      }
    }

    // 4. ACTUALIZAR LA ORDEN PRINCIPAL
    // Primero, obtener el abono actual para sumarle el nuevo
    $abono_historico = floatval($res_check[0]['pago_abono'] ?? 0);
    $nuevo_abono_total = $abono_historico + $arr['abono'];

    // Se elimina el campo `observaciones` de la actualización principal
    $sql_update_orden = "UPDATE {$table_principal} SET
        pago_total = " . $arr['total'] . ',
        pago_abono = ' . $nuevo_abono_total . ',
        pago_descuento = pago_descuento + ' . $arr['descuento'] . ",
        fecha_entrega = '" . $arr['fechaEntrega'] . "'
        WHERE _id = {$id_orden_a_editar}";
    $localConnection->goQuery($sql_update_orden);
    $object['sql_update_orden'] = $sql_update_orden;

    // Lógica para insertar o actualizar las observaciones
    if ($is_presupuesto) {
      $sql_obs = "UPDATE presupuestos SET observaciones = '{$arr['obs']}' WHERE _id = {$id_orden_a_editar}";
    } else {
      // NUEVO: Lógica para insertar o actualizar las observaciones en la tabla dedicada para órdenes
      $sql_check_obs = "SELECT _id FROM ordenes_observaciones WHERE id_orden = {$id_orden_a_editar}";
      $obs_existente = $localConnection->goQuery($sql_check_obs);

      if (empty($obs_existente)) {
        $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$id_orden_a_editar}, '{$arr['obs']}')";
      } else {
        $sql_obs = "UPDATE ordenes_observaciones SET observaciones = '{$arr['obs']}' WHERE id_orden = {$id_orden_a_editar}";
      }
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
        $sql_comision = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $arr['responsable'];
        $respComision = $localConnection->goQuery($sql_comision)[0];
        $comisionTipo = $respComision['comision_tipo'];

        if ($comisionTipo === 'porcentaje') {
          $comision = floatval($respComision['comision_porcentaje']);
        } else {
          $comisionFloat = floatval($respComision['comision']);
          $comision = number_format($comisionFloat, 2);
        }

        $pago_vendedor = floatval($arr['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);

        $sql_pago = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ",
       '" . $respComision['comision_tipo'] . "', '" . $id_orden_a_editar . "',  '" . $arr['responsable'] . "', '" . $pago_vendedor . "', 'Abono a orden', 'aprobado')";
        $object['sql_pago_response'] = $localConnection->goQuery($sql_pago);

        $object['sql_pago'] = $sql_pago;
        $object['pago_a_vendedor_por_abono'] = 'SI hubo comisión por el nuevo abono.';
      }
    }

    // 6. GUARDAR NUEVOS METODOS DE PAGO
    $object['metodos_pago_response'] = [];

    if (floatval($newJson['montoDolaresEfectivo'] ?? 0) > 0) {
      $monto = floatval($newJson['montoDolaresEfectivo']);
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Dólares', 'Efectivo', '{$monto}', '1', '');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
      $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('{$monto}', 'Dólares', 1, 'Abono a Orden', '" . $arr['responsable'] . "', 'Abono a Orden #{$id_orden_a_editar}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoDolaresZelle'] ?? 0) > 0) {
      $monto = floatval($newJson['montoDolaresZelle']);
      $detalle = addslashes($newJson['montoDolaresZelleDetalle'] ?? '');
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Dólares', 'Zelle', '{$monto}', '1', '{$detalle}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoDolaresPanama'] ?? 0) > 0) {
      $monto = floatval($newJson['montoDolaresPanama']);
      $detalle = addslashes($newJson['montoDolaresPanamaDetalle'] ?? '');
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Dólares', 'Panamá', '{$monto}', '1', '{$detalle}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoPesosEfectivo'] ?? 0) > 0) {
      $monto = floatval($newJson['montoPesosEfectivo']);
      $tasa = floatval($newJson['tasa_peso'] ?? 1);
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Pesos', 'Efectivo', '{$monto}', '{$tasa}', '');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
      $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('{$monto}', 'Pesos', '{$tasa}', 'Abono a Orden', '" . $arr['responsable'] . "', 'Abono a Orden #{$id_orden_a_editar}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoPesosTransferencia'] ?? 0) > 0) {
      $monto = floatval($newJson['montoPesosTransferencia']);
      $tasa = floatval($newJson['tasa_peso'] ?? 1);
      $detalle = addslashes($newJson['montoPesosTransferenciaDetalle'] ?? '');
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Pesos', 'Transferencia', '{$monto}', '{$tasa}', '{$detalle}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoBolivaresEfectivo'] ?? 0) > 0) {
      $monto = floatval($newJson['montoBolivaresEfectivo']);
      $tasa = floatval($newJson['tasa_dolar'] ?? 1);
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Bolívares', 'Efectivo', '{$monto}', '{$tasa}', '');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
      $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('{$monto}', 'Bolívares', '{$tasa}', 'Abono a Orden', '" . $arr['responsable'] . "', 'Abono a Orden #{$id_orden_a_editar}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoBolivaresPunto'] ?? 0) > 0) {
      $monto = floatval($newJson['montoBolivaresPunto']);
      $tasa = floatval($newJson['tasa_dolar'] ?? 1);
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Bolívares', 'Punto', '{$monto}', '{$tasa}', '');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoBolivaresPagomovil'] ?? 0) > 0) {
      $monto = floatval($newJson['montoBolivaresPagomovil']);
      $tasa = floatval($newJson['tasa_dolar'] ?? 1);
      $detalle = addslashes($newJson['montoBolivaresPagomovilDetalle'] ?? '');
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Bolívares', 'Pagomovil', '{$monto}', '{$tasa}', '{$detalle}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    if (floatval($newJson['montoBolivaresTransferencia'] ?? 0) > 0) {
      $monto = floatval($newJson['montoBolivaresTransferencia']);
      $tasa = floatval($newJson['tasa_dolar'] ?? 1);
      $detalle = addslashes($newJson['montoBolivaresTransferenciaDetalle'] ?? '');
      $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Bolívares', 'Transferencia', '{$monto}', '{$tasa}', '{$detalle}');";
      $object['metodos_pago_response'][] = $localConnection->goQuery($sql_metodos_pago);
    }

    $object['response']['status'] = 'success';
    $object['response']['message'] = 'La orden número ' . $id_orden_a_editar . ' ha sido actualizada correctamente.';

    $response->getBody()->write(json_encode($object));
    $localConnection->commit();
    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE CUSTOM CON VALIDACIONES
  $app->post('/ordenes/nueva/custom', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();

    // ========== VALIDACIONES TEMPRANAS (antes de procesar datos) ==========
    $productosRaw = $newJson['productos'] ?? '';
    $misProductos = json_decode($productosRaw, true);

    if (empty($misProductos) || !is_array($misProductos) || count($misProductos) === 0) {
      return ApiResponse::validationError($response, 'Debe agregar al menos un producto a la orden');
    }

    if (empty($newJson['responsable']) || !is_numeric($newJson['responsable'])) {
      return ApiResponse::validationError($response, 'Debe seleccionar un responsable para la orden');
    }

    if (empty($newJson['fechaEntrega'])) {
      return ApiResponse::validationError($response, 'Debe seleccionar una fecha de entrega');
    }

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
    $arr['montoDolaresEfectivoDetalle'] = $newJson['montoDolaresEfectivoDetalle'] ?? '';
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = $newJson['montoDolaresZelleDetalle'] ?? '';
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = $newJson['montoDolaresPanamaDetalle'] ?? '';
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = $newJson['montoPesosEfectivoDetalle'] ?? '';
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = $newJson['montoPesosTransferenciaDetalle'] ?? '';
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = $newJson['montoBolivaresEfectivoDetalle'] ?? '';
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = $newJson['montoBolivaresPuntoDetalle'] ?? '';
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = $newJson['montoBolivaresPagomovilDetalle'] ?? '';
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = $newJson['montoBolivaresTransferenciaDetalle'] ?? '';
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

    // NOTA: Las validaciones de entrada se movieron al inicio del endpoint

    /* Crear orden en ninesys */
    // Corregir valores NaN o vacíos
    $abono_value = (is_numeric($arr['abono']) && $arr['abono'] !== '') ? floatval($arr['abono']) : 0;
    $descuento_value = (is_numeric($arr['descuento']) && $arr['descuento'] !== '') ? floatval($arr['descuento']) : 0;

    // Validar id_wp
    $id_wp_val = $arr['id_wp'];
    $id_wp_sql = "NULL";
    if (!empty($id_wp_val) && is_numeric($id_wp_val)) {
      $id_wp_sql = "'" . $id_wp_val . "'";
    } else {
      // NUEVO: Si no hay id_wp, crear o actualizar el cliente automáticamente
      error_log("id_wp vacío detectado, creando/actualizando cliente automáticamente");

      $cliente_nombre = addslashes($arr['nombre'] ?? '');
      $cliente_apellido = addslashes($arr['apellido'] ?? '');
      $cliente_cedula = addslashes($arr['cedula'] ?? '');
      $cliente_telefono = addslashes($arr['telefono'] ?? '');
      $cliente_email = addslashes($arr['email'] ?? '');
      $cliente_direccion = addslashes($arr['direccion'] ?? 'none');

      // Generar email si está vacío
      if (empty($cliente_email) || $cliente_email === 'none') {
        $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $cliente_email = strtolower(substr($cliente_nombre, 0, 1)) . $randomString . '@email.com';
      }

      // Buscar si ya existe un cliente con esa cédula o teléfono
      $sqlCheckCustomer = "SELECT _id FROM customers WHERE ";
      $conditions = [];

      if (!empty($cliente_cedula) && $cliente_cedula !== 'none') {
        $conditions[] = "cedula = '$cliente_cedula'";
      }

      if (!empty($cliente_telefono) && $cliente_telefono !== 'none') {
        $digits = preg_replace('/\D/', '', $cliente_telefono);
        if (strlen($digits) >= 7) {
          $last10 = substr($digits, -10);
          $conditions[] = "REGEXP_REPLACE(phone, '[^0-9]', '') LIKE '%" . $last10 . "'";
        } else {
          $conditions[] = "phone = '$cliente_telefono'";
        }
      }

      if (!empty($conditions)) {
        $sqlCheckCustomer .= '(' . implode(' OR ', $conditions) . ') LIMIT 1';
        $existingCustomer = $localConnection->goQuery($sqlCheckCustomer);

        if (!empty($existingCustomer)) {
          // Cliente ya existe (activo o eliminado), ACTUALIZAR sus datos.
          // eliminado = 0 → auto-reactivar: si estaba con soft delete y hace una
          // nueva orden, se reutiliza su registro (no se duplica el teléfono).
          $customer_id = $existingCustomer[0]['_id'];
          $sqlUpdateCustomer = "UPDATE customers SET
                                  eliminado = 0,
                                  first_name = '$cliente_nombre',
                                  last_name = '$cliente_apellido',
                                  cedula = '$cliente_cedula',
                                  phone = '$cliente_telefono',
                                  email = '$cliente_email',
                                  address = '$cliente_direccion'
                                WHERE _id = $customer_id";
          $localConnection->goQuery($sqlUpdateCustomer);
          $id_wp_sql = "'" . $customer_id . "'";
          error_log("Cliente existente actualizado con ID: $customer_id");
        } else {
          // Cliente no existe, CREAR nuevo
          $sqlCreateCustomer = "INSERT INTO customers (first_name, last_name, cedula, phone, email, address) 
                                VALUES ('$cliente_nombre', '$cliente_apellido', '$cliente_cedula', '$cliente_telefono', '$cliente_email', '$cliente_direccion')";
          $createResult = $localConnection->goQuery($sqlCreateCustomer);

          if (isset($createResult['insert_id'])) {
            $id_wp_sql = "'" . $createResult['insert_id'] . "'";
            error_log("Nuevo cliente creado con ID: " . $createResult['insert_id']);
          } else {
            error_log("Error al crear cliente: id_wp quedará NULL");
          }
        }
      } else {
        // No hay cédula ni teléfono para buscar, crear cliente nuevo directamente
        $sqlCreateCustomer = "INSERT INTO customers (first_name, last_name, cedula, phone, email, address) 
                              VALUES ('$cliente_nombre', '$cliente_apellido', '$cliente_cedula', '$cliente_telefono', '$cliente_email', '$cliente_direccion')";
        $createResult = $localConnection->goQuery($sqlCreateCustomer);

        if (isset($createResult['insert_id'])) {
          $id_wp_sql = "'" . $createResult['insert_id'] . "'";
          error_log("Nuevo cliente creado con ID: " . $createResult['insert_id']);
        } else {
          error_log("Error al crear cliente: id_wp quedará NULL");
        }
      }
    }


    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . intval($newJson['responsable']) . ", '" . $now . "', " . $descuento_value . ', ' . $abono_value . ",  " . $id_wp_sql . ", '" . addslashes($arr['cedula'] ?? '') . "', " . floatval($newJson['total']) . ",'" . addslashes($cliente ?? '') . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";
      $nueva_oreden_response = $localConnection->goQuery($sql);
      $object['nueva_oreden_sql'] = $sql;

      // $object['nueva_oreden_response'] = $nueva_oreden_response['message'];

      if (isset($nueva_oreden_response['status']) && $nueva_oreden_response['status'] === 'error') {
        throw new Exception('Error al crear la orden: ' . ($nueva_oreden_response['message'] ?? 'desconocido'));
      }

      if (!isset($nueva_oreden_response['insert_id'])) {
        throw new Exception('No se pudo obtener el ID de la orden creada');
      }

      $object['orden_creada'] = true;
      // Obtener id de la orden creada
      $last = $nueva_oreden_response['insert_id'];
      $last_id = intval($last);

      // NUEVO: Guardar las observaciones en la tabla dedicada
      if (!empty($newJson['obs'])) {
        $sql_obs = 'INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)';
        $object['sql_observaciones'] = $sql_obs;
        $localConnection->goQuery($sql_obs, [$last_id, $newJson['obs']]);
      }

      // Crear registro en la fila de producción
      $lastOrdenFila = $localConnection->goQuery('SELECT MAX(orden_fila) AS max FROM ordenes_fila_orden;');
      $lastOrdenFila = $lastOrdenFila[0]['max'] + 1;

      $sql = "INSERT INTO ordenes_fila_orden(id_orden, orden_fila)  VALUES ($last_id, $lastOrdenFila)";
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
      $comisionPagoId = null;
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
        $comisionPagoResult = $localConnection->goQuery($sql);
        $comisionPagoId = $comisionPagoResult['insert_id'] ?? null;
        $object['resultado_abono'] = json_encode($comisionPagoResult);
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

          $precio_item = (isset($decodedObj['precio']) && !empty($decodedObj['precio'])) ? $decodedObj['precio'] : 0;
          $values = "'" . $now . "',";
          $values .= $precio_item . ',';
          $values .= "'" . $precio_item . "',";
          $values .= "'" . $decodedObj['producto'] . "',";
          $values .= $last_id . ',';
          $values .= $decodedObj['cod'] . ',';
          $values .= $decodedObj['cantidad'] . ',';
          $id_categoria = (isset($decodedObj['categoria']) && !empty($decodedObj['categoria'])) ? intval($decodedObj['categoria']) : 0;
          // El frontend no siempre envía 'categoria' (ej. al importar un presupuesto).
          // Si no llegó, se resuelve desde la categoría real del producto en vez de
          // insertar 0, que viola la FK ord_prod_ibfk_5 (no existe categoría _id=0).
          if ($id_categoria <= 0 && !empty($decodedObj['cod'])) {
            $prodCat = $localConnection->goQuery('SELECT category_ids FROM products WHERE _id = ' . intval($decodedObj['cod']));
            $firstCatId = !empty($prodCat[0]['category_ids']) ? intval(explode(',', $prodCat[0]['category_ids'])[0]) : 0;
            if ($firstCatId > 0) {
              $catRow = $localConnection->goQuery('SELECT nombre FROM categories WHERE _id = ' . $firstCatId);
              if (!empty($catRow[0]['nombre'])) {
                $id_categoria = $firstCatId;
                $cat_name = $catRow[0]['nombre'];
              }
            }
          }
          $values .= $id_categoria . ',';
          $values .= "'" . addslashes($cat_name) . "',";
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
                $sql_lote_detalles = "INSERT INTO lotes_detalles (moment, id_orden, id_ordenes_productos, id_woo, id_departamento, departamento) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', {$departamento['_id']}, '{$departamento['departamento']}');";
                $object['lotes_detalles_sql'][$i][] = $sql_lote_detalles;
                $object['lote_detalles_response'][$i][] = $localConnection->goQuery($sql_lote_detalles);
              }
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

      // INICIO: Lógica para actualizar stock en /nueva/custom

      $stock_additions = [];
      $stock_deductions = [];

      foreach ($misProductos as $producto) {
        $product_id = intval($producto['cod']);
        $quantity = intval($producto['cantidad']);

        if ($guardar_stock) {
          // Órdenes internas: generar e incrementar stock
          if (isset($stock_additions[$product_id])) {
            $stock_additions[$product_id] += $quantity;
          } else {
            $stock_additions[$product_id] = $quantity;
          }
        } else {
          // Órdenes normales: deducir stock solo para productos físicos
          $sql_fisico = "SELECT fisico FROM products WHERE _id = {$product_id}";
          $res_f = $localConnection->goQuery($sql_fisico);
          if (!empty($res_f) && !isset($res_f['status']) && intval($res_f[0]['fisico']) === 1) {
            if (isset($stock_deductions[$product_id])) {
              $stock_deductions[$product_id] += $quantity;
            } else {
              $stock_deductions[$product_id] = $quantity;
            }
          }
        }
      }

      // Aumentos por orden interna
      foreach ($stock_additions as $product_id => $total_quantity) {
        $sql_stock_update = "UPDATE products SET stock_quantity = stock_quantity + {$total_quantity} WHERE _id = {$product_id};";
        $object['custom_stock_update_sql'][] = $sql_stock_update;
        $object['custom_stock_update_response'][] = $localConnection->goQuery($sql_stock_update);
      }

      // Descuentos por venta estándar
      foreach ($stock_deductions as $product_id => $total_quantity) {
        $sql_stock_update = "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - {$total_quantity}) WHERE _id = {$product_id};";
        $object['custom_stock_update_sql'][] = $sql_stock_update;
        $object['custom_stock_update_response'][] = $localConnection->goQuery($sql_stock_update);
      }
      // FIN: Lógica para actualizar stock en /nueva/custom

      // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
      $object['sql_metodos_pago'] = [];
      $object['metodos_pago'] = [];

      if (floatval($arr['montoDolaresEfectivo']) > 0) {  // Usar floatval para comparar con 0
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', '');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
        $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'Orden Nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoDolaresZelle']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', '" . addslashes($arr['montoDolaresZelleDetalle'] ?? '') . "');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoDolaresPanama']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', '" . addslashes($arr['montoDolaresPanamaDetalle'] ?? '') . "');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoPesosEfectivo']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', '');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
        $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'Orden Nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoPesosTransferencia']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', '" . addslashes($arr['montoPesosTransferenciaDetalle'] ?? '') . "');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoBolivaresEfectivo']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', '');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
        $sql_metodos_pago = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'Orden Nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoBolivaresPunto']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', '');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoBolivaresPagomovil']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', '" . addslashes($arr['montoBolivaresPagomovilDetalle'] ?? '') . "');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      if (floatval($arr['montoBolivaresTransferencia']) > 0) {
        $sql_metodos_pago = "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', '" . addslashes($arr['montoBolivaresTransferenciaDetalle'] ?? '') . "');";
        $object['sql_metodos_pago'][] = $sql_metodos_pago;
        $object['metodos_pago'][] = $localConnection->goQuery($sql_metodos_pago);
      }

      // Vincular la comisión del vendedor con el método de pago recién registrado.
      // El pago de comisión se inserta ANTES de conocer los métodos de pago usados,
      // así que se enlaza aquí (mismo patrón ya usado en /orden/abono más arriba).
      if (!empty($comisionPagoId)) {
        $sqlMaxMetodo = "SELECT MAX(_id) AS last_id FROM metodos_de_pago WHERE id_orden = " . intval($last_id);
        $resMaxMetodo = $localConnection->goQuery($sqlMaxMetodo);
        $idMetodoPagoComision = !empty($resMaxMetodo[0]['last_id']) ? intval($resMaxMetodo[0]['last_id']) : null;
        if ($idMetodoPagoComision) {
          $localConnection->goQuery('UPDATE pagos SET id_metodos_de_pago = ' . $idMetodoPagoComision . ' WHERE _id = ' . intval($comisionPagoId));
        }
      }

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();

      // WhatsApp se envía DESPUÉS del commit (no es crítico si falla)
      if ($sendWhatsApp) {
        try {
          // Usar el teléfono que viene del formulario directamente
          $clientPhone = $arr['telefono'] ?? null;

          // Fallback: si no viene del formulario, intentar obtener de customers
          if (empty($clientPhone)) {
            $infoSql = 'SELECT b.phone FROM ordenes a LEFT JOIN customers b ON b._id = a.id_wp WHERE a._id = ' . $last_id;
            $contactInfo = $localConnection->goQuery($infoSql)[0] ?? [];
            $clientPhone = $contactInfo['phone'] ?? null;
          }

          if (empty($clientPhone)) {
            $object['ws_response'] = 'Envío de WhatsApp omitido: No se encontró un número de teléfono para el cliente.';
          } else {
            $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');  // Asegúrate que esta función esté definida
            $payload = $resultBuscar['object'];
            $payload['phone_client'] = $clientPhone;
            $payload['template'] = 'welcome';

            $msgApi = new WhatsAppAPIClient(WS_API_URL);
            $ws_result = $msgApi->sendMessage(ID_EMPRESA, $payload);

            $object['ws_response'] = $ws_result;
            $object['ws_payload_sent'] = $payload;
            $object['ws_http_code'] = isset($ws_result['code']) ? $ws_result['code'] : (isset($ws_result['success']) && $ws_result['success'] ? 200 : 500);
          }
        } catch (\Throwable $wse) {
          error_log('Error no crítico al enviar WhatsApp en /ordenes/nueva/custom: ' . $wse->getMessage());
          $object['ws_response'] = [
            'success' => false,
            'status' => 'failed',
            'message' => 'Error al procesar el envío de WhatsApp: ' . $wse->getMessage()
          ];
          $object['ws_http_code'] = 500;
        }
      } else {
        $object['ws_response'] = 'Envío de WhatsApp omitido por el usuario.';
      }

      $localConnection->disconnect();

      // Respuesta estandarizada de éxito
      return ApiResponse::success($response, 'La orden número ' . $last_id . ' ha sido creada correctamente', [
        'orden_creada' => true,
        'id_orden' => $last_id,
        'response' => ['status' => 'success', 'message' => 'La orden número ' . $last_id . ' ha sido creada correctamente']
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN EN CASO DE ERROR ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /ordenes/nueva/custom: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al crear la orden: ' . $e->getMessage(), $e);
    }
  });

  // CREAR NUEVA ORDEN ANTES DE SPORT
  // CREAR NUEVA ORDEN ANTES DE SPORT
  $app->post('/ordenes/nueva/sport', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();

    // ========== VALIDACIONES TEMPRANAS ==========
    $productosRaw = $newJson['productos'] ?? '';
    // Intentar decodificar si es string, si ya es array usarlo directo (por si el middleware ya lo procesó, aunque getParsedBody suele traer array si es JSON)
    // El código original usaba json_decode($newJson['productos'], true), asumiendo que viene como JSON string dentro del post body.
    $misProductos = json_decode($newJson['productos'], true);

    if (empty($misProductos) || !is_array($misProductos) || count($misProductos) === 0) {
      return ApiResponse::validationError($response, 'Debe agregar al menos un producto a la orden');
    }

    if (empty($newJson['responsable']) || !is_numeric($newJson['responsable'])) {
      return ApiResponse::validationError($response, 'Debe seleccionar un responsable para la orden');
    }

    if (empty($newJson['fechaEntrega'])) {
      return ApiResponse::validationError($response, 'Debe seleccionar una fecha de entrega');
    }

    $localConnection = new LocalDB();

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      $count = count($misProductos);

      $arr = [];
      // Manteniendo la lógica de decodificación original detallada
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
      $arr['misProductos'] = $misProductos;
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

      // Validar id_wp para evitar error SQL de entero vacío
      $id_wp_param = (is_numeric($arr['id_wp']) && $arr['id_wp'] > 0) ? $arr['id_wp'] : null;

      $sql = "INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status, tipo ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'entregada', 'sport')";

      $nueva_oreden_response = $localConnection->goQuery($sql, [
        $newJson['responsable'],
        $now,
        $arr['descuento'],
        $arr['abono'],
        $id_wp_param,
        $arr['cedula'],
        $newJson['total'],
        $cliente,
        date('Y-m-d'),
        $newJson['fechaEntrega'],
        date('Y-m-d'),
      ]);
      $object['nueva_oreden_sql'] = $sql;

      if (isset($nueva_oreden_response['status']) && $nueva_oreden_response['status'] === 'error') {
        throw new Exception("Error al crear la orden en BD: " . ($nueva_oreden_response['message'] ?? 'Desconocido'));
      }

      $last_id = isset($nueva_oreden_response['insert_id']) ? $nueva_oreden_response['insert_id'] : null;
      if (!$last_id) {
        throw new Exception("No se pudo obtener el ID de la orden creada.");
      }

      $object['orden_creada'] = true;

      if (!empty($newJson['obs'])) {
        $sql_obs = 'INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)';
        $object['sql_observaciones'] = $sql_obs;
        $localConnection->goQuery($sql_obs, [$last_id, $newJson['obs']]);
      }

      // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
      for ($i = 0; $i <= $count; $i++) {
        if (isset($misProductos[$i])) {
          $decodedObj = $misProductos[$i];
          $cat_name = 'Uncatagorized';

          $params2 = [$now, $decodedObj['precio'], $decodedObj['precio'], $decodedObj['producto'], $last_id, $decodedObj['cod'], $decodedObj['cantidad']];
          $id_categoria = (isset($decodedObj['categoria']) && !empty($decodedObj['categoria'])) ? intval($decodedObj['categoria']) : 0;
          $params2[] = $id_categoria;
          $params2[] = $cat_name;
          $values_prefix = '?, ?, ?, ?, ?, ?, ?, ?, ?, ';

          if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
            $id_talla = intval($decodedObj['talla']);
            $values = $values_prefix . '?, (SELECT nombre FROM sizes WHERE _id = ?),';
            $params2[] = $id_talla;
            $params2[] = $id_talla;
          } else {
            $values = $values_prefix . 'NULL, NULL,';
          }

          if (isset($decodedObj['corte'])) {
            $values .= '?,';
            $params2[] = $decodedObj['corte'];
          } else {
            $values .= '?,';
            $params2[] = '';
          }

          if (isset($decodedObj['tela']) && !is_null($decodedObj['tela']) && $decodedObj['tela'] !== '') {
            $id_tela_prod = intval($decodedObj['tela']);
            $values .= '?, (SELECT tela FROM catalogo_telas WHERE _id = ?)';
            $params2[] = $id_tela_prod;
            $params2[] = $id_tela_prod;
          } else {
            $values .= "NULL, ?";
            $params2[] = '';
          }

          $id_products_attributes = null;
          if (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo']) && $decodedObj['atributo'] !== '') {
            $id_products_attributes = intval($decodedObj['atributo']);
          }
          $params2[] = $id_products_attributes;

          $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ?)';
          $object['sql_ordenes_productos'] = $sql2;
          $producto_detalle_response = $localConnection->goQuery($sql2, $params2);
          $object['producto_detalle'][] = $producto_detalle_response;

          // Verificar error en inserción de producto
          if (isset($producto_detalle_response['status']) && $producto_detalle_response['status'] === 'error') {
            throw new Exception("Error al guardar producto: " . ($producto_detalle_response['message'] ?? 'Desconocido'));
          }

          if (isset($producto_detalle_response['insert_id'])) {
            $last_id_ordenes_productos = $producto_detalle_response['insert_id'];

            // Procesar atributos_seleccionados
            if (isset($decodedObj['atributos_seleccionados']) && is_array($decodedObj['atributos_seleccionados'])) {
              $product_id_for_attributes_table = intval($decodedObj['cod']);
              $object['response_data'] = $decodedObj['atributos_seleccionados'];

              foreach ($decodedObj['atributos_seleccionados'] as $attribute_data) {
                $object['response_flag'][] = true;
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

                  $sql_attr = 'INSERT INTO products_attributes_values (id_orden, id_product, id_product_attribute, attribute_value, attribute_price) VALUES (?, ?, ?, ?, ?)';
                  $params_attr = [
                    $last_id,
                    $product_id_for_attributes_table,
                    $id_product_attribute,
                    $attribute_value_text,
                    $attribute_price_value
                  ];
                  $object['response_Atrinutos'][] = $localConnection->goQuery($sql_attr, $params_attr);
                }
              }
            } else {
              $object['response_flag'][] = false;
            }
          }
        }
      }

      // STOCK UPDATE (Sólo productos físicos)
      $stock_deductions = [];
      foreach ($misProductos as $producto) {
        $product_id = intval($producto['cod']);
        $quantity = intval($producto['cantidad']);

        $sql_fisico = 'SELECT fisico FROM products WHERE _id = ?';
        $res_f = $localConnection->goQuery($sql_fisico, [$product_id]);
        if (!empty($res_f) && !isset($res_f['status']) && intval($res_f[0]['fisico']) === 1) {
          if (isset($stock_deductions[$product_id])) {
            $stock_deductions[$product_id] += $quantity;
          } else {
            $stock_deductions[$product_id] = $quantity;
          }
        }
      }

      $stock_update_responses = [];
      foreach ($stock_deductions as $product_id => $total_quantity) {
        $stock_update_responses[] = $localConnection->goQuery(
          'UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE _id = ?',
          [$total_quantity, $product_id]
        );
      }

      if (!empty($stock_update_responses)) {
        $object['stock_update_response'] = $stock_update_responses;
      }

      // COMISIÓN VENDEDORES
      if (floatval($newJson['abono']) > 0 && !(isset($newJson['guardar_stock']) && filter_var($newJson['guardar_stock'], FILTER_VALIDATE_BOOLEAN))) {
        $sql_comision = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ?';
        $respComisionArr = $localConnection->goQuery($sql_comision, [$newJson['responsable']]);

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

          $sql_pago = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES (?, ?, ?, ?, ?, ?, 'Comercialización', 'aprobado')";
          $object['resultado_abono'] = $localConnection->goQuery($sql_pago, [$now, $comision, $comisionTipo, $last_id, $newJson['responsable'], $pago_vendedor]);
          $object['pago_a_vendedor'] = 'SI hubo comisión, cliente normal';
        } else {
          $object['pago_a_vendedor'] = 'NO se encontró información de comisión para el vendedor.';
        }
      }

      // CONFIRMAR TRANSACCIÓN
      $localConnection->commit();

      $response->getBody()->write(json_encode($object));
      $localConnection->disconnect();

      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    } catch (\Throwable $e) {
      // EN CASO DE ERROR, REVERTIR TRANSACCIÓN
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /ordenes/nueva/sport: ' . $e->getMessage());
      return ApiResponse::serverError($response, 'Error al crear la orden Sport: ' . $e->getMessage(), $e);
    }
  });

  // FIN CREAR NUEVA ORDEN

  // CAMBIAR ESTATUS DE LA REVISIÓN
  $app->post('/comercializacion/revisiones-estatus/{estatus}/{id_revision}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();

    // Atomicidad FK: revisiones + disenos + ordenes + pago del diseñador en una transacción
    $localConnection->beginTransaction();

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
      $miRevision = $localConnection->goQuery($sql);
      $object['sql_revision'] = $sql;
      $sql = "UPDATE ordenes SET status = 'activa' WHERE _id = " . $args['id_orden'] . ';';
      $miRevision = $localConnection->goQuery($sql);
      $object['sql_orden'] = $sql;

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

    $localConnection->commit();
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

    $sql = 'UPDATE revisiones SET detalles = ? WHERE _id = ?';
    $object['revisiones'] = $localConnection->goQuery($sql, [htmlspecialchars($data['detalles']), $args['id_revision']]);

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

    $sql = 'UPDATE ordenes_fila_reposiciones SET orden_fila = ? WHERE id_reposicion = ?';
    $object['sql_update_fila_reposicion'] = $sql;
    $object['response'] = $localConnection->goQuery($sql, [intval($data['orden_fila']), intval($data['id_reposicion'])]);

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

  /**
   * POST /ordenes/materiales-lote
   * Obtiene los materiales estimados para un conjunto de órdenes de un lote
   */
  $app->post('/ordenes/materiales-lote', function (Request $request, Response $response) {
    $json_body = $request->getBody()->getContents();
    $data = json_decode($json_body, true);
    $id_ordenes = $data['id_ordenes'] ?? null;

    if (empty($id_ordenes) || !is_array($id_ordenes)) {
      $response->getBody()->write(json_encode(['error' => 'Se requiere un array de id_ordenes.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    try {
      // Crear lista de IDs para la consulta SQL
      $ids_string = implode(',', array_map('intval', $id_ordenes));

      // Consulta para obtener los materiales estimados de todas las órdenes
      $sql = "SELECT
                cip.nombre as catalogo,
                pia.cantidad as cantidad_estimada_de_consumo,
                op.cantidad as unidades,
                pia.unidad as unidad_de_medida,
                op.id_orden
              FROM
                product_insumos_asignados pia
              JOIN
                ordenes_productos op ON op.id_woo = pia.id_product
              LEFT JOIN
                catalogo_insumos_productos cip ON cip._id = pia.id_catalogo_insumos_productos
              WHERE
                op.id_orden IN ({$ids_string})
                AND pia.id_departamento = (SELECT _id FROM departamentos WHERE departamento = 'Impresión' LIMIT 1)";

      $result = $localConnection->goQuery($sql);

      $response->getBody()->write(json_encode($result));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (\Throwable $e) {
      error_log('Error al obtener materiales del lote: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  /**
   * CREAR ORDEN SIMPLIFICADA (desde Chat IA)
   * 
   * Permite crear órdenes básicas sin procesar pagos.
   * Solo usuarios de Administración o Comercialización pueden usar este endpoint.
   * 
   * Payload:
   * {
   *   "cliente_nombre": "Nombre del cliente",
   *   "productos": [
   *     {"nombre": "franela", "cantidad": 3, "talla": "S Dama", "tela": "dryfit"}
   *   ],
   *   "observaciones": "Texto libre",
   *   "responsable_id": 3
   * }
   */
  $app->post('/ordenes/nueva/simple', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $object = [];

    try {
      $data = $request->getParsedBody();

      // Debug: Si data es null, intentar parsear el body manualmente
      if ($data === null) {
        $rawBody = (string) $request->getBody();
        $data = json_decode($rawBody, true);
        error_log('/ordenes/nueva/simple - Raw body: ' . $rawBody);
      }



      $id_empresa = defined('ID_EMPRESA') ? ID_EMPRESA : 163;

      // Validar campos requeridos
      if (empty($data['cliente_nombre'])) {
        return ApiResponse::validationError($response, 'Debe especificar el nombre del cliente');
      }
      if (empty($data['productos']) || !is_array($data['productos'])) {
        return ApiResponse::validationError($response, 'Debe especificar al menos un producto');
      }
      if (empty($data['responsable_id'])) {
        return ApiResponse::validationError($response, 'Debe especificar el ID del responsable');
      }

      $responsable_id = intval($data['responsable_id']);

      // ==========================================================
      // VALIDAR PERMISOS: Solo Administración o Comercialización
      // ==========================================================
      $departamentos_autorizados = ['Administración', 'Comercialización'];

      $sql_dept = "SELECT departamento FROM api_empresas.empresas_usuarios 
                   WHERE id_usuario = ? AND id_empresa = ?";
      $user_result = $localConnection->goQuery($sql_dept, [$responsable_id, $id_empresa]);

      if (empty($user_result)) {
        return ApiResponse::error($response, 'Usuario no encontrado', 404);
      }

      $user_dept = $user_result[0]['departamento'];

      if (!in_array($user_dept, $departamentos_autorizados)) {
        return ApiResponse::error(
          $response,
          "No estás autorizado para crear órdenes. Tu departamento actual es: {$user_dept}. " .
          "Solo personal de Administración o Comercialización puede crear órdenes.",
          403
        );
      }

      // ==========================================================
      // BUSCAR CLIENTE
      // ==========================================================
      $cliente_nombre = trim($data['cliente_nombre']);
      $sql_cliente = "SELECT _id, first_name, last_name, phone, cedula, email
                      FROM customers
                      WHERE eliminado = 0 AND CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?
                      LIMIT 5";
      $clientes = $localConnection->goQuery($sql_cliente, ["%{$cliente_nombre}%"]);

      if (empty($clientes)) {
        return ApiResponse::error(
          $response,
          "No se encontró ningún cliente con el nombre '{$cliente_nombre}'. " .
          "Verifica el nombre o crea el cliente primero.",
          404
        );
      }

      // Si hay múltiples coincidencias, usar el primero pero avisar
      $cliente = $clientes[0];
      $cliente['nombre'] = trim($cliente['first_name'] . ' ' . ($cliente['last_name'] ?? ''));
      $cliente['telefono'] = $cliente['phone'] ?? null;
      $multiples_clientes = count($clientes) > 1;

      // ==========================================================
      // CALCULAR FECHA DE ENTREGA AUTOMÁTICA
      // ==========================================================
      // Calcular días de producción basado en órdenes activas
      $sql_ordenes_activas = "SELECT COUNT(*) as total FROM ordenes 
                              WHERE status IN ('En espera', 'En proceso')";
      $ordenes_activas = $localConnection->goQuery($sql_ordenes_activas);
      $total_ordenes = $ordenes_activas[0]['total'] ?? 0;

      // Estimación: 2 días base + 0.5 días por cada orden activa (máximo 14 días)
      $dias_estimados = min(2 + ceil($total_ordenes * 0.5), 14);
      $fecha_entrega = date('Y-m-d', strtotime("+{$dias_estimados} days"));

      // ==========================================================
      // PROCESAR PRODUCTOS
      // ==========================================================
      $productos_procesados = [];
      $total_orden = 0;
      $errores_productos = [];

      foreach ($data['productos'] as $index => $prod) {
        $producto_info = [
          'index' => $index + 1,
          'nombre_buscado' => $prod['nombre'] ?? '',
          'cantidad' => intval($prod['cantidad'] ?? 1),
          'corte' => $prod['corte'] ?? ''
        ];

        // ============================================================
        // Si vienen campos directos del modal de confirmación, usarlos
        // ============================================================
        if (!empty($prod['producto_id'])) {
          // Campos directos del modal
          $producto_info['producto_id'] = intval($prod['producto_id']);
          $producto_info['producto_nombre'] = $prod['producto_nombre'] ?? $prod['nombre'];
          $producto_info['categoria'] = $prod['categoria'] ?? 'Sin categoría';
          $producto_info['precio'] = floatval($prod['precio'] ?? 0);
          $producto_info['talla_id'] = $prod['talla_id'] ?? null;
          $producto_info['talla_nombre'] = $prod['talla_nombre'] ?? $prod['talla'] ?? null;
          $producto_info['tela_id'] = $prod['tela_id'] ?? null;
          $producto_info['tela_nombre'] = $prod['tela_nombre'] ?? $prod['tela'] ?? null;
        } else {
          // ============================================================
          // Buscar producto por nombre (flujo antiguo sin modal)
          // ============================================================
          $sql_prod = "SELECT _id, product, category_ids, price FROM products 
                       WHERE product LIKE ? LIMIT 1";
          $producto_result = $localConnection->goQuery($sql_prod, ["%{$prod['nombre']}%"]);

          if (empty($producto_result) || isset($producto_result['status']) || !isset($producto_result[0])) {
            $errores_productos[] = "Producto '{$prod['nombre']}' no encontrado";
            continue;
          }

          $producto_info['producto_id'] = $producto_result[0]['_id'] ?? null;
          $producto_info['producto_nombre'] = $producto_result[0]['product'] ?? 'Sin nombre';
          $producto_info['categoria'] = $producto_result[0]['category_ids'] ?? 'Sin categoría';
          $producto_info['precio'] = floatval($producto_result[0]['price'] ?? 0);

          if ($producto_info['producto_id'] === null) {
            $errores_productos[] = "Producto '{$prod['nombre']}' sin ID válido";
            continue;
          }

          // Buscar precio del producto (usar el primero disponible)
          $sql_precio = "SELECT price FROM products_prices 
                         WHERE id_product = ? ORDER BY _id ASC LIMIT 1";
          $precio_result = $localConnection->goQuery($sql_precio, [$producto_info['producto_id']]);
          if (!empty($precio_result) && !isset($precio_result['status']) && isset($precio_result[0]['price'])) {
            $producto_info['precio'] = floatval($precio_result[0]['price']);
          }

          // Buscar talla (si se especificó)
          if (!empty($prod['talla'])) {
            $sql_talla = "SELECT _id, nombre FROM sizes WHERE nombre LIKE ? LIMIT 1";
            $talla_result = $localConnection->goQuery($sql_talla, ["%{$prod['talla']}%"]);
            if (!empty($talla_result) && !isset($talla_result['status'])) {
              $producto_info['talla_id'] = $talla_result[0]['_id'];
              $producto_info['talla_nombre'] = $talla_result[0]['nombre'];
            }
          }

          // Buscar tela (si se especificó)
          if (!empty($prod['tela'])) {
            $sql_tela = "SELECT _id, tela FROM catalogo_telas WHERE tela LIKE ? LIMIT 1";
            $tela_result = $localConnection->goQuery($sql_tela, ["%{$prod['tela']}%"]);
            if (!empty($tela_result) && !isset($tela_result['status'])) {
              $producto_info['tela_id'] = $tela_result[0]['_id'];
              $producto_info['tela_nombre'] = $tela_result[0]['tela'];
            }
          }
        }

        $producto_info['subtotal'] = $producto_info['precio'] * $producto_info['cantidad'];
        $total_orden += $producto_info['subtotal'];
        $productos_procesados[] = $producto_info;
      }

      if (empty($productos_procesados)) {
        return ApiResponse::error(
          $response,
          "No se pudo procesar ningún producto. Errores: " . implode(', ', $errores_productos),
          400
        );
      }

      // ==========================================================
      // VALIDACIÓN CRÍTICA: Verificar que TODOS los productos tengan producto_id válido
      // ==========================================================
      $productos_invalidos = [];
      foreach ($productos_procesados as $prod) {
        if (empty($prod['producto_id']) || !is_numeric($prod['producto_id'])) {
          $productos_invalidos[] = $prod['producto_nombre'] ?? $prod['nombre_buscado'] ?? 'Desconocido';
        }
      }

      if (!empty($productos_invalidos)) {
        return ApiResponse::error(
          $response,
          "No se puede crear la orden. Los siguientes productos no tienen un ID válido: " .
          implode(', ', $productos_invalidos) . ". " .
          "Esto indica que no fueron encontrados en la base de datos durante la prevalidación.",
          400
        );
      }

      // ==========================================================
      // CREAR LA ORDEN
      // ==========================================================
      $myDate = new CustomTime();
      $now = $myDate->today();
      $observaciones = $data['observaciones'] ?? '';
      $cliente_nombre_completo = $cliente['nombre'];

      $localConnection->beginTransaction();
      $sql_orden = "INSERT INTO ordenes 
                    (responsable, moment, pago_descuento, pago_abono, pago_total, 
                     id_wp, cliente_nombre, cliente_cedula, fecha_inicio, fecha_entrega, 
                     fecha_creacion, status) 
                    VALUES (?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, 'En espera')";

      $orden_result = $localConnection->goQuery($sql_orden, [
        $responsable_id,
        $now,
        $total_orden,
        $cliente['_id'],  // id_wp = ID del cliente
        $cliente_nombre_completo,
        $cliente['cedula'] ?? '',
        date('Y-m-d'),
        $fecha_entrega,
        date('Y-m-d')
      ]);

      if (!isset($orden_result['insert_id'])) {
        throw new Exception('Error al crear la orden');
      }

      $orden_id = $orden_result['insert_id'];
      $object['orden_id'] = $orden_id;

      // Guardar observaciones si existen
      if (!empty($observaciones)) {
        $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)";
        $localConnection->goQuery($sql_obs, [$orden_id, $observaciones]);
      }

      // Crear registro en fila de producción
      $lastOrdenFila = $localConnection->goQuery('SELECT MAX(orden_fila) AS max FROM ordenes_fila_orden');
      $nuevaFila = ($lastOrdenFila[0]['max'] ?? 0) + 1;
      $localConnection->goQuery(
        "INSERT INTO ordenes_fila_orden (id_orden, orden_fila) VALUES (?, ?)",
        [$orden_id, $nuevaFila]
      );

      // Crear abono inicial (con monto 0)
      $localConnection->goQuery(
        "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES (?, ?, ?, 0, 0)",
        [$now, $orden_id, $responsable_id]
      );

      // ==========================================================
      // CREAR PRODUCTOS DE LA ORDEN
      // ==========================================================
      foreach ($productos_procesados as $prod) {
        $sql_producto = "INSERT INTO ordenes_productos
                         (moment, precio_unitario, precio_woo, name, id_orden, id_woo,
                          cantidad, id_category, category_name, id_size, talla,
                          corte, id_tela, tela)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // DEBUG: Log valores antes de INSERT
        error_log('/ordenes/nueva/simple - Insertando producto: ' . json_encode([
          'producto_nombre' => $prod['producto_nombre'],
          'producto_id (id_woo)' => $prod['producto_id'],
          'precio' => $prod['precio'],
          'cantidad' => $prod['cantidad']
        ]));

        $prod_result = $localConnection->goQuery($sql_producto, [
          $now,
          $prod['precio'],
          $prod['precio'],
          $prod['producto_nombre'],
          $orden_id,
          $prod['producto_id'],
          $prod['cantidad'],
          null,
          $prod['categoria'] ?? 'Sin categoría',
          $prod['talla_id'] ?? null,
          $prod['talla_nombre'] ?? null,
          $prod['corte'] ?? '',
          $prod['tela_id'] ?? null,
          $prod['tela_nombre'] ?? null
        ]);

        $id_ordenes_productos = $prod_result['insert_id'] ?? null;

        // Crear lotes_detalles para cada departamento de producción
        if ($id_ordenes_productos) {
          $sql_deptos = "SELECT _id, departamento FROM departamentos 
                         WHERE asignar_numero_de_paso = 1 ORDER BY _id ASC";
          $departamentos = $localConnection->goQuery($sql_deptos);

          foreach ($departamentos as $depto) {
            $sql_lote = "INSERT INTO lotes_detalles 
                         (moment, id_orden, id_ordenes_productos, id_woo, id_departamento, departamento) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $localConnection->goQuery($sql_lote, [
              $now,
              $orden_id,
              $id_ordenes_productos,
              $prod['producto_id'],
              $depto['_id'],
              $depto['departamento']
            ]);
          }
        }
      }

      // Crear lote
      $sql_lote_main = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) 
                        VALUES (?, ?, ?, ?, 'producción')";
      $localConnection->goQuery($sql_lote_main, [$now, date('Y-m-d'), $orden_id, $orden_id]);

      // ==========================================================
      // PREPARAR RESPUESTA
      // ==========================================================
      $object['success'] = true;
      $object['orden_id'] = $orden_id;
      $object['total'] = number_format($total_orden, 2);
      $object['fecha_entrega'] = $fecha_entrega;
      $object['cliente'] = [
        'id' => $cliente['_id'],
        'nombre' => $cliente['nombre'],
        'telefono' => $cliente['telefono'] ?? null
      ];
      $object['productos_creados'] = count($productos_procesados);
      $object['productos'] = array_map(function ($p) {
        return [
          'nombre' => $p['producto_nombre'],
          'cantidad' => $p['cantidad'],
          'talla' => $p['talla_nombre'] ?? 'N/A',
          'tela' => $p['tela_nombre'] ?? 'N/A',
          'precio' => $p['precio'],
          'subtotal' => $p['subtotal']
        ];
      }, $productos_procesados);

      if (!empty($errores_productos)) {
        $object['advertencias'] = $errores_productos;
      }
      if ($multiples_clientes) {
        $object['nota'] = "Se encontraron múltiples clientes. Se usó: " . $cliente['nombre'];
      }

      $localConnection->commit();
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (\Throwable $e) {
      $localConnection->rollback();
      error_log('Error en /ordenes/nueva/simple: ' . $e->getMessage());
      return ApiResponse::error($response, 'Error al crear la orden: ' . $e->getMessage(), 500);
    } finally {
      $localConnection->disconnect();
    }
  });

  /**
   * PREVALIDAR ORDEN (desde Chat IA)
   * 
   * Valida los datos de una orden sin crearla.
   * Retorna cliente, productos con precios disponibles, tallas y telas.
   * El frontend muestra estos datos para que el usuario seleccione opciones.
   * 
   * Payload:
   * {
   *   "cliente_nombre": "Nombre del cliente",
   *   "productos": [
   *     {"nombre": "franela", "cantidad": 3, "talla": "S", "tela": "dryfit"}
   *   ],
   *   "responsable_id": 3
   * }
   */
  // VALIDAR STOCK DE PRODUCTOS FÍSICOS
  $app->post('/ordenes/validar-stock', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $object = [];

    try {
      $data = $request->getParsedBody();
      if ($data === null) {
        $rawBody = (string) $request->getBody();
        $data = json_decode($rawBody, true);
      }

      $productos = $data['productos'] ?? [];
      if (empty($productos) || !is_array($productos)) {
        return ApiResponse::validationError($response, 'Debe especificar al menos un producto para validar');
      }

      $errores = [];

      foreach ($productos as $prod) {
        $id_producto = intval($prod['id'] ?? 0);
        $cantidad_pedida = intval($prod['cantidad'] ?? 0);
        $nombre_producto = $prod['nombre'] ?? 'Producto Desconocido';

        if ($id_producto > 0 && $cantidad_pedida > 0) {
          $sql = "SELECT stock_quantity, fisico FROM products WHERE _id = ?";
          $res = $localConnection->goQuery($sql, [$id_producto]);

          if (!empty($res) && !isset($res['status'])) {
            $is_fisico = intval($res[0]['fisico'] ?? 0);
            $stock_actual = intval($res[0]['stock_quantity'] ?? 0);

            if ($is_fisico === 1 && $cantidad_pedida > $stock_actual) {
              $errores[] = "{$nombre_producto}: solicitados {$cantidad_pedida}, disponibles {$stock_actual}.";
            }
          }
        }
      }

      if (!empty($errores)) {
        return ApiResponse::error($response, 'Stock insuficiente para algunos productos', 400, ['errores' => $errores]);
      }

      $object['success'] = true;
      $object['mensaje'] = 'Stock disponible para todos los productos físicos';
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (\Throwable $e) {
      error_log('Error en /ordenes/validar-stock: ' . $e->getMessage());
      return ApiResponse::error($response, 'Error al validar stock: ' . $e->getMessage(), 500);
    } finally {
      $localConnection->disconnect();
    }
  });

  $app->post('/ordenes/prevalidar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $object = [];

    try {
      $data = $request->getParsedBody();

      // Debug: Si data es null, intentar parsear el body manualmente
      if ($data === null) {
        $rawBody = (string) $request->getBody();
        $data = json_decode($rawBody, true);
      }

      $id_empresa = defined('ID_EMPRESA') ? ID_EMPRESA : 163;

      // Validar campos requeridos
      if (empty($data['cliente_nombre'])) {
        return ApiResponse::validationError($response, 'Debe especificar el nombre del cliente');
      }
      if (empty($data['productos']) || !is_array($data['productos'])) {
        return ApiResponse::validationError($response, 'Debe especificar al menos un producto');
      }
      if (empty($data['responsable_id'])) {
        return ApiResponse::validationError($response, 'Debe especificar el ID del responsable');
      }

      $responsable_id = intval($data['responsable_id']);

      // Validar permisos
      $departamentos_autorizados = ['Administración', 'Comercialización'];
      $sql_dept = "SELECT departamento FROM api_empresas.empresas_usuarios 
                   WHERE id_usuario = ? AND id_empresa = ?";
      $user_result = $localConnection->goQuery($sql_dept, [$responsable_id, $id_empresa]);

      if (empty($user_result) || isset($user_result['status'])) {
        return ApiResponse::error($response, 'Usuario no encontrado', 404);
      }

      $user_dept = $user_result[0]['departamento'];

      if (!in_array($user_dept, $departamentos_autorizados)) {
        return ApiResponse::error(
          $response,
          "No estás autorizado para crear órdenes. Tu departamento actual es: {$user_dept}. " .
          "Solo personal de Administración o Comercialización puede crear órdenes.",
          403
        );
      }

      // ==========================================================
      // BUSCAR CLIENTE
      // ==========================================================
      $cliente_nombre = trim($data['cliente_nombre']);
      $sql_cliente = "SELECT _id, first_name, last_name, phone, cedula, email, address
                      FROM customers
                      WHERE eliminado = 0 AND CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?
                      LIMIT 5";
      $clientes = $localConnection->goQuery($sql_cliente, ["%{$cliente_nombre}%"]);

      if (empty($clientes) || isset($clientes['status'])) {
        return ApiResponse::error(
          $response,
          "No se encontró ningún cliente con el nombre '{$cliente_nombre}'.",
          404
        );
      }

      // Formatear clientes encontrados
      $clientes_formateados = [];
      foreach ($clientes as $c) {
        $clientes_formateados[] = [
          'id' => $c['_id'],
          'nombre' => trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')),
          'telefono' => $c['phone'] ?? null,
          'cedula' => $c['cedula'] ?? null,
          'email' => $c['email'] ?? null,
          'direccion' => $c['address'] ?? null
        ];
      }

      // ==========================================================
      // OBTENER TELAS DISPONIBLES
      // ==========================================================
      $sql_telas = "SELECT _id, tela FROM catalogo_telas WHERE eliminado = 0 ORDER BY tela ASC";
      $telas_result = $localConnection->goQuery($sql_telas);
      $telas_disponibles = [];
      if (!empty($telas_result) && !isset($telas_result['status'])) {
        foreach ($telas_result as $t) {
          $telas_disponibles[] = ['id' => $t['_id'], 'nombre' => $t['tela']];
        }
      }

      // ==========================================================
      // OBTENER TALLAS DISPONIBLES
      // ==========================================================
      $sql_tallas = "SELECT _id, nombre FROM sizes ORDER BY _id ASC";
      $tallas_result = $localConnection->goQuery($sql_tallas);
      $tallas_disponibles = [];
      if (!empty($tallas_result) && !isset($tallas_result['status'])) {
        foreach ($tallas_result as $t) {
          $tallas_disponibles[] = ['id' => $t['_id'], 'nombre' => $t['nombre']];
        }
      }

      // ==========================================================
      // CORTES DISPONIBLES (fijos)
      // ==========================================================
      $cortes_disponibles = [
        ['id' => 'V', 'nombre' => 'Cuello V'],
        ['id' => 'redondo', 'nombre' => 'Cuello Redondo'],
        ['id' => '', 'nombre' => 'Sin especificar']
      ];

      // ==========================================================
      // PROCESAR PRODUCTOS
      // ==========================================================
      $productos_validados = [];
      $errores_productos = [];

      foreach ($data['productos'] as $index => $prod) {
        $producto_item = [
          'index' => $index,
          'nombre_buscado' => $prod['nombre'] ?? '',
          'cantidad' => intval($prod['cantidad'] ?? 1),
          'talla_buscada' => $prod['talla'] ?? null,
          'tela_buscada' => $prod['tela'] ?? null,
          'encontrado' => false
        ];

        // Buscar producto
        $sql_prod = "SELECT _id, product, category_ids, price FROM products 
                     WHERE product LIKE ? LIMIT 5";
        $producto_result = $localConnection->goQuery($sql_prod, ["%{$prod['nombre']}%"]);

        if (empty($producto_result) || isset($producto_result['status']) || !isset($producto_result[0])) {
          $errores_productos[] = "Producto '{$prod['nombre']}' no encontrado";
          $productos_validados[] = $producto_item;
          continue;
        }

        // Si encontramos múltiples productos, los listamos
        $productos_encontrados = [];
        foreach ($producto_result as $pr) {
          // Obtener precios del producto
          $sql_precios = "SELECT _id, price, descripcion FROM products_prices 
                          WHERE id_product = ? ORDER BY _id ASC";
          $precios = $localConnection->goQuery($sql_precios, [$pr['_id']]);

          $precios_lista = [];
          if (!empty($precios) && !isset($precios['status'])) {
            foreach ($precios as $p) {
              $precios_lista[] = [
                'id' => $p['_id'],
                'precio' => floatval($p['price']),
                'descripcion' => $p['descripcion']
              ];
            }
          }

          // Si no hay precios en products_prices, usar el precio base
          if (empty($precios_lista) && !empty($pr['price'])) {
            $precios_lista[] = [
              'id' => null,
              'precio' => floatval($pr['price']),
              'descripcion' => 'Precio base'
            ];
          }

          $productos_encontrados[] = [
            'id' => $pr['_id'],
            'nombre' => $pr['product'],
            'categoria' => $pr['category_ids'],
            'precios' => $precios_lista
          ];
        }

        $producto_item['encontrado'] = true;
        $producto_item['opciones'] = $productos_encontrados;

        // Buscar talla si se especificó
        if (!empty($prod['talla'])) {
          $sql_talla = "SELECT _id, nombre FROM sizes WHERE nombre LIKE ? LIMIT 1";
          $talla_result = $localConnection->goQuery($sql_talla, ["%{$prod['talla']}%"]);
          if (!empty($talla_result) && !isset($talla_result['status'])) {
            $producto_item['talla_encontrada'] = [
              'id' => $talla_result[0]['_id'],
              'nombre' => $talla_result[0]['nombre']
            ];
          }
        }

        // Buscar tela si se especificó
        if (!empty($prod['tela'])) {
          $sql_tela = "SELECT _id, tela FROM catalogo_telas WHERE tela LIKE ? LIMIT 1";
          $tela_result = $localConnection->goQuery($sql_tela, ["%{$prod['tela']}%"]);
          if (!empty($tela_result) && !isset($tela_result['status'])) {
            $producto_item['tela_encontrada'] = [
              'id' => $tela_result[0]['_id'],
              'nombre' => $tela_result[0]['tela']
            ];
          }
        }

        $productos_validados[] = $producto_item;
      }

      // ==========================================================
      // CALCULAR FECHA DE ENTREGA ESTIMADA
      // ==========================================================
      $sql_ordenes_activas = "SELECT COUNT(*) as total FROM ordenes 
                              WHERE status IN ('En espera', 'En proceso')";
      $ordenes_activas = $localConnection->goQuery($sql_ordenes_activas);
      $total_ordenes = $ordenes_activas[0]['total'] ?? 0;
      $dias_estimados = min(2 + ceil($total_ordenes * 0.5), 14);
      $fecha_entrega = date('Y-m-d', strtotime("+{$dias_estimados} days"));

      // ==========================================================
      // PREPARAR RESPUESTA
      // ==========================================================
      $object['success'] = true;
      $object['validacion'] = 'pendiente_confirmacion';
      $object['clientes'] = $clientes_formateados;
      $object['cliente_seleccionado'] = $clientes_formateados[0] ?? null;
      $object['productos'] = $productos_validados;
      $object['fecha_entrega_estimada'] = $fecha_entrega;
      $object['opciones'] = [
        'tallas' => $tallas_disponibles,
        'telas' => $telas_disponibles,
        'cortes' => $cortes_disponibles
      ];

      if (!empty($errores_productos)) {
        $object['advertencias'] = $errores_productos;
      }

      $object['mensaje'] = 'Por favor selecciona el precio para cada producto y confirma para crear la orden.';

      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (\Throwable $e) {
      error_log('Error en /ordenes/prevalidar: ' . $e->getMessage());
      return ApiResponse::error($response, 'Error al prevalidar la orden: ' . $e->getMessage(), 500);
    } finally {
      $localConnection->disconnect();
    }
  });

  /**
   * CONTEXTO PARA IA - Proporciona datos de BD para enriquecer prompts de Gemini
   * 
   * POST /ordenes/contexto-ia
   * 
   * Busca información relevante en la BD para que Gemini pueda tomar decisiones
   * inteligentes durante la creación conversacional de órdenes.
   */
  $app->post('/ordenes/contexto-ia', function (Request $request, Response $response) {
    $empresaId = $request->getHeader('Authorization')[0] ?? null;
    $localConnection = new LocalDB($empresaId);

    try {
      $body = json_decode($request->getBody()->getContents(), true);

      $buscarClientes = $body['buscar_clientes'] ?? null;
      $buscarProductos = $body['buscar_productos'] ?? null;
      $incluirTallas = $body['incluir_tallas'] ?? false;
      $incluirTelas = $body['incluir_telas'] ?? false;

      $resultado = [
        'clientes' => [],
        'productos' => [],
        'tallas' => [],
        'telas' => []
      ];

      // Buscar clientes si se proporciona nombre
      if (!empty($buscarClientes)) {
        $nombre = trim($buscarClientes);
        $sql = "SELECT _id as id, 
                       CONCAT(first_name, ' ', COALESCE(last_name, '')) as nombre_completo,
                       phone as telefono
                FROM customers
                WHERE eliminado = 0 AND (first_name LIKE ?
                   OR last_name LIKE ?
                   OR CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?)
                ORDER BY first_name ASC
                LIMIT 10";
        $clientes = $localConnection->goQuery($sql, ["%{$nombre}%", "%{$nombre}%", "%{$nombre}%"]);

        if (!empty($clientes) && !isset($clientes['status'])) {
          $resultado['clientes'] = $clientes;
        }
      }

      // Buscar productos si se proporciona nombre
      if (!empty($buscarProductos)) {
        $nombre = trim($buscarProductos);
        // Normalizar plurales
        $nombreSingular = preg_replace('/(es|s)$/i', '', $nombre);

        $sql = "SELECT p._id as id, p.product as nombre, 
                       COALESCE(pp.price, p.price) as precio
                FROM products p
                LEFT JOIN products_prices pp ON pp.id_product = p._id
                WHERE p.product LIKE ? OR p.product LIKE ?
                GROUP BY p._id
                ORDER BY p.product ASC
                LIMIT 15";
        $productos = $localConnection->goQuery($sql, ["%{$nombre}%", "%{$nombreSingular}%"]);

        if (!empty($productos) && !isset($productos['status'])) {
          $resultado['productos'] = $productos;
        }
      }

      // Obtener tallas disponibles
      if ($incluirTallas) {
        $sql = "SELECT _id as id, nombre FROM sizes ORDER BY _id ASC LIMIT 20";
        $tallas = $localConnection->goQuery($sql, []);

        if (!empty($tallas) && !isset($tallas['status'])) {
          $resultado['tallas'] = $tallas;
        }
      }

      // Obtener telas del catálogo
      if ($incluirTelas) {
        $sql = "SELECT _id as id, tela as nombre FROM catalogo_telas WHERE eliminado = 0 ORDER BY tela ASC LIMIT 20";
        $telas = $localConnection->goQuery($sql, []);

        if (!empty($telas) && !isset($telas['status'])) {
          $resultado['telas'] = $telas;
        }
      }

      // Obtener atributos del catálogo (siempre incluir)
      $sql = "SELECT _id as id, attribute_name as nombre, precio FROM products_attributes ORDER BY attribute_name ASC LIMIT 20";
      $atributos = $localConnection->goQuery($sql, []);
      if (!empty($atributos) && !isset($atributos['status'])) {
        $resultado['atributos'] = $atributos;
      }

      $response->getBody()->write(json_encode([
        'success' => true,
        'contexto' => $resultado
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (\Throwable $e) {
      error_log('Error en /ordenes/contexto-ia: ' . $e->getMessage());
      return ApiResponse::error($response, 'Error obteniendo contexto: ' . $e->getMessage(), 500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // ==========================================================
  // FUNCIONES AUXILIARES PARA VALIDAR PASOS
  // ==========================================================

  /**
   * Validar paso: Cliente
   */
  function validarPasoCliente($db, $datos, $contexto)
  {
    $nombre = trim($datos['nombre'] ?? '');
    $seleccion_id = $datos['seleccion_id'] ?? null;

    // Si viene un ID de selección, el usuario eligió de una lista
    if ($seleccion_id) {
      $sql = "SELECT _id, first_name, last_name, phone, cedula, email, address FROM customers WHERE _id = ?";
      $cliente = $db->goQuery($sql, [$seleccion_id]);

      if (!empty($cliente) && !isset($cliente['status'])) {
        $c = $cliente[0];
        return [
          'ok' => true,
          'cliente' => [
            'id' => $c['_id'],
            'nombre' => trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')),
            'telefono' => $c['phone'] ?? null,
            'cedula' => $c['cedula'] ?? null,
            'email' => $c['email'] ?? null,
            'direccion' => $c['address'] ?? null
          ],
          'siguiente_paso' => 'productos',
          'mensaje' => '✅ Cliente seleccionado. ¿Qué productos deseas agregar al pedido?'
        ];
      }
      return ['ok' => false, 'mensaje' => 'Cliente no encontrado con ese ID'];
    }

    // Buscar cliente por nombre
    if (empty($nombre)) {
      return [
        'ok' => false,
        'paso_requerido' => 'cliente',
        'mensaje' => '¿Cuál es el nombre del cliente para este pedido?'
      ];
    }

    // Buscar cliente por nombre - buscar en first_name y last_name por separado
    // ya que el nombre puede estar en cualquiera de los dos campos
    $sql = "SELECT _id, first_name, last_name, phone, cedula, email, address
            FROM customers
            WHERE eliminado = 0 AND (first_name LIKE ?
               OR last_name LIKE ?
               OR CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?)
            ORDER BY first_name ASC
            LIMIT 10";
    $clientes = $db->goQuery($sql, ["%{$nombre}%", "%{$nombre}%", "%{$nombre}%"]);

    if (empty($clientes) || isset($clientes['status'])) {
      return [
        'ok' => false,
        'mensaje' => "❌ No encontré ningún cliente con el nombre '{$nombre}'. Por favor, verifica el nombre o créalo primero."
      ];
    }

    // Formatear clientes
    $clientes_formateados = [];
    foreach ($clientes as $c) {
      $clientes_formateados[] = [
        'id' => $c['_id'],
        'nombre' => trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')),
        'telefono' => $c['phone'] ?? null,
        'cedula' => $c['cedula'] ?? null
      ];
    }

    if (count($clientes_formateados) === 1) {
      // Solo un resultado, lo seleccionamos automáticamente
      return [
        'ok' => true,
        'cliente' => $clientes_formateados[0],
        'siguiente_paso' => 'productos',
        'mensaje' => "✅ Cliente encontrado: {$clientes_formateados[0]['nombre']}. ¿Qué productos deseas agregar?"
      ];
    }

    // Múltiples resultados, pedir selección
    return [
      'ok' => false,
      'paso_pendiente' => 'seleccionar_cliente',
      'opciones' => $clientes_formateados,
      'mensaje' => "Encontré varios clientes. ¿Cuál es el correcto?\n" . implode("\n", array_map(function ($c, $i) {
        $tel = $c['telefono'] ? " (Tel: {$c['telefono']})" : '';
        return ($i + 1) . ". {$c['nombre']}{$tel}";
      }, $clientes_formateados, array_keys($clientes_formateados)))
    ];
  }

  /**
   * Validar paso: Productos
   */
  function validarPasoProductos($db, $datos, $contexto)
  {
    $items = $datos['items'] ?? [];

    if (empty($items)) {
      return [
        'ok' => false,
        'paso_requerido' => 'productos',
        'mensaje' => 'Por favor, indica los productos y cantidades que deseas agregar al pedido.'
      ];
    }

    $productos_validados = [];
    $productos_no_encontrados = [];
    $productos_multiples = [];

    foreach ($items as $idx => $item) {
      $nombre = trim($item['nombre'] ?? '');
      $cantidad = intval($item['cantidad'] ?? 1);
      $producto_id = $item['producto_id'] ?? null;

      // Si ya viene con ID (selección previa)
      if ($producto_id) {
        $sql = "SELECT _id, product, price FROM products WHERE _id = ?";
        $prod = $db->goQuery($sql, [$producto_id]);
        if (!empty($prod) && !isset($prod['status'])) {
          // Obtener precios del producto
          $sql_precios = "SELECT _id, price, descripcion FROM products_prices WHERE id_product = ? ORDER BY _id ASC";
          $precios = $db->goQuery($sql_precios, [$producto_id]);
          $precio_base = !empty($precios) && !isset($precios['status']) ? floatval($precios[0]['price']) : floatval($prod[0]['price']);

          $productos_validados[] = [
            'index' => $idx,
            'producto_id' => $prod[0]['_id'],
            'nombre' => $prod[0]['product'],
            'cantidad' => $cantidad,
            'precio' => $precio_base,
            'talla_id' => $item['talla_id'] ?? null,
            'talla_nombre' => $item['talla_nombre'] ?? null,
            'tela_id' => $item['tela_id'] ?? null,
            'tela_nombre' => $item['tela_nombre'] ?? null
          ];
          continue;
        }
      }

      // Buscar producto por nombre - normalizar quitando plurales comunes
      $nombreNormalizado = $nombre;
      // Quitar 's' o 'es' del final si es plural (español)
      if (preg_match('/(es|s)$/i', $nombreNormalizado) && strlen($nombreNormalizado) > 3) {
        $nombreSingular = preg_replace('/(es|s)$/i', '', $nombreNormalizado);
      } else {
        $nombreSingular = $nombreNormalizado;
      }

      // Buscar con el nombre original y con el singular
      $sql = "SELECT _id, product, price FROM products 
              WHERE product LIKE ? OR product LIKE ? 
              ORDER BY product ASC LIMIT 10";
      $productos = $db->goQuery($sql, ["%{$nombre}%", "%{$nombreSingular}%"]);

      if (empty($productos) || isset($productos['status'])) {
        $productos_no_encontrados[] = $nombre;
        continue;
      }

      // Siempre mostrar opciones para que el usuario elija (incluso si es 1 solo producto)
      // Esto permite al usuario ver qué productos hay disponibles que coinciden
      $productos_multiples[$idx] = [
        'buscado' => $nombre,
        'cantidad' => $cantidad,
        'opciones' => array_map(function ($p) {
          return ['id' => $p['_id'], 'nombre' => $p['product']];
        }, $productos)
      ];
    }

    // Si hay productos no encontrados
    if (!empty($productos_no_encontrados)) {
      return [
        'ok' => false,
        'productos_validados' => $productos_validados,
        'no_encontrados' => $productos_no_encontrados,
        'mensaje' => "❌ No encontré estos productos: " . implode(', ', $productos_no_encontrados) . ". Verifica los nombres."
      ];
    }

    // Si hay múltiples coincidencias
    if (!empty($productos_multiples)) {
      $msg = "Encontré varios resultados para algunos productos:\n";
      foreach ($productos_multiples as $idx => $pm) {
        $msg .= "\n**{$pm['buscado']}:**\n";
        foreach ($pm['opciones'] as $i => $op) {
          $msg .= ($i + 1) . ". {$op['nombre']}\n";
        }
      }
      return [
        'ok' => false,
        'paso_pendiente' => 'seleccionar_productos',
        'productos_validados' => $productos_validados,
        'productos_pendientes' => $productos_multiples,
        'mensaje' => $msg
      ];
    }

    // Todos los productos validados
    return [
      'ok' => true,
      'productos' => $productos_validados,
      'siguiente_paso' => 'tallas',
      'mensaje' => "✅ Productos agregados:\n" . implode("\n", array_map(function ($p) {
        return "• {$p['cantidad']}x {$p['nombre']}";
      }, $productos_validados))
    ];
  }

  /**
   * Validar paso: Tallas
   */
  function validarPasoTallas($db, $datos, $contexto)
  {
    $asignaciones = $datos['asignaciones'] ?? [];
    $productos = $contexto['productos'] ?? [];

    // Obtener tallas disponibles
    $sql_tallas = "SELECT _id, nombre FROM sizes ORDER BY _id ASC";
    $tallas_result = $db->goQuery($sql_tallas);
    $tallas_disponibles = [];
    if (!empty($tallas_result) && !isset($tallas_result['status'])) {
      foreach ($tallas_result as $t) {
        $tallas_disponibles[] = ['id' => $t['_id'], 'nombre' => $t['nombre']];
      }
    }

    // Si no hay asignaciones y hay productos sin talla
    $productos_sin_talla = array_filter($productos, function ($p) {
      return empty($p['talla_id']);
    });

    if (!empty($productos_sin_talla) && empty($asignaciones)) {
      $tallas_nombres = array_column($tallas_disponibles, 'nombre');
      return [
        'ok' => false,
        'paso_requerido' => 'tallas',
        'productos_pendientes' => array_values($productos_sin_talla),
        'tallas_disponibles' => $tallas_disponibles,
        'mensaje' => "Ahora necesito las tallas para cada producto. Opciones disponibles:\n" .
          implode(', ', $tallas_nombres) . "\n\n¿Qué tallas asigno a cada producto?"
      ];
    }

    // Procesar asignaciones
    $productos_actualizados = $productos;
    foreach ($asignaciones as $asig) {
      $idx = $asig['producto_idx'] ?? null;
      $talla_nombre = $asig['talla'] ?? null;
      $talla_id = $asig['talla_id'] ?? null;

      if ($idx === null)
        continue;

      // Buscar talla por nombre si no viene ID
      if (!$talla_id && $talla_nombre) {
        $sql = "SELECT _id, nombre FROM sizes WHERE nombre LIKE ? LIMIT 1";
        $talla = $db->goQuery($sql, ["%{$talla_nombre}%"]);
        if (!empty($talla) && !isset($talla['status'])) {
          $talla_id = $talla[0]['_id'];
          $talla_nombre = $talla[0]['nombre'];
        }
      }

      if (isset($productos_actualizados[$idx])) {
        $productos_actualizados[$idx]['talla_id'] = $talla_id;
        $productos_actualizados[$idx]['talla_nombre'] = $talla_nombre;
      }
    }

    // Verificar que todos tienen talla
    $faltantes = [];
    foreach ($productos_actualizados as $idx => $p) {
      if (empty($p['talla_id'])) {
        $faltantes[] = $p['nombre'];
      }
    }

    if (!empty($faltantes)) {
      return [
        'ok' => false,
        'productos' => $productos_actualizados,
        'tallas_disponibles' => $tallas_disponibles,
        'mensaje' => "⚠️ Aún faltan tallas para: " . implode(', ', $faltantes)
      ];
    }

    return [
      'ok' => true,
      'productos' => $productos_actualizados,
      'siguiente_paso' => 'telas',
      'mensaje' => "✅ Tallas asignadas. Ahora indica el tipo de tela para cada producto."
    ];
  }

  /**
   * Validar paso: Telas
   */
  function validarPasoTelas($db, $datos, $contexto)
  {
    $asignaciones = $datos['asignaciones'] ?? [];
    $productos = $contexto['productos'] ?? [];

    // Obtener telas disponibles
    $sql_telas = "SELECT _id, tela FROM catalogo_telas WHERE eliminado = 0 ORDER BY tela ASC";
    $telas_result = $db->goQuery($sql_telas);
    $telas_disponibles = [];
    if (!empty($telas_result) && !isset($telas_result['status'])) {
      foreach ($telas_result as $t) {
        $telas_disponibles[] = ['id' => $t['_id'], 'nombre' => $t['tela']];
      }
    }

    // Si no hay asignaciones y hay productos sin tela
    $productos_sin_tela = array_filter($productos, function ($p) {
      return empty($p['tela_id']);
    });

    if (!empty($productos_sin_tela) && empty($asignaciones)) {
      $telas_nombres = array_column($telas_disponibles, 'nombre');
      return [
        'ok' => false,
        'paso_requerido' => 'telas',
        'productos_pendientes' => array_values($productos_sin_tela),
        'telas_disponibles' => $telas_disponibles,
        'mensaje' => "Ahora indica el tipo de tela. Opciones disponibles:\n" .
          implode(', ', $telas_nombres) . "\n\n¿Qué tipo de tela para cada producto?"
      ];
    }

    // Procesar asignaciones
    $productos_actualizados = $productos;
    foreach ($asignaciones as $asig) {
      $idx = $asig['producto_idx'] ?? null;
      $tela_nombre = $asig['tela'] ?? null;
      $tela_id = $asig['tela_id'] ?? null;
      $aplicar_a_todos = $asig['aplicar_a_todos'] ?? false;

      // Buscar tela por nombre si no viene ID
      if (!$tela_id && $tela_nombre) {
        $sql = "SELECT _id, tela FROM catalogo_telas WHERE tela LIKE ? LIMIT 1";
        $tela = $db->goQuery($sql, ["%{$tela_nombre}%"]);
        if (!empty($tela) && !isset($tela['status'])) {
          $tela_id = $tela[0]['_id'];
          $tela_nombre = $tela[0]['tela'];
        }
      }

      if ($aplicar_a_todos) {
        // Aplicar a todos los productos
        foreach ($productos_actualizados as &$p) {
          $p['tela_id'] = $tela_id;
          $p['tela_nombre'] = $tela_nombre;
        }
      } else if ($idx !== null && isset($productos_actualizados[$idx])) {
        $productos_actualizados[$idx]['tela_id'] = $tela_id;
        $productos_actualizados[$idx]['tela_nombre'] = $tela_nombre;
      }
    }

    // Verificar que todos tienen tela
    $faltantes = [];
    foreach ($productos_actualizados as $idx => $p) {
      if (empty($p['tela_id'])) {
        $faltantes[] = $p['nombre'];
      }
    }

    if (!empty($faltantes)) {
      return [
        'ok' => false,
        'productos' => $productos_actualizados,
        'telas_disponibles' => $telas_disponibles,
        'mensaje' => "⚠️ Aún faltan telas para: " . implode(', ', $faltantes)
      ];
    }

    // Calcular fecha de entrega
    $sql_ordenes = "SELECT COUNT(*) as total FROM ordenes WHERE status IN ('En espera', 'En proceso')";
    $ordenes = $db->goQuery($sql_ordenes);
    $total = $ordenes[0]['total'] ?? 0;
    $dias = min(2 + ceil($total * 0.5), 14);
    $fecha_entrega = date('Y-m-d', strtotime("+{$dias} days"));

    // Calcular total
    $total_orden = 0;
    foreach ($productos_actualizados as $p) {
      $total_orden += ($p['precio'] ?? 0) * ($p['cantidad'] ?? 1);
    }

    return [
      'ok' => true,
      'productos' => $productos_actualizados,
      'fecha_entrega' => $fecha_entrega,
      'total' => $total_orden,
      'siguiente_paso' => 'confirmar',
      'mensaje' => "✅ Todo listo. Fecha de entrega estimada: {$fecha_entrega}. Total: $" . number_format($total_orden, 2) . "\n\n¿Confirmas el pedido?"
    ];
  }

  /**
   * Confirmar y crear orden
   */
  function confirmarOrden($db, $contexto, $responsable_id)
  {
    $cliente = $contexto['cliente'] ?? null;
    $productos = $contexto['productos'] ?? [];
    $fecha_entrega = $contexto['fecha_entrega'] ?? date('Y-m-d', strtotime('+7 days'));

    if (!$cliente || empty($productos)) {
      return [
        'ok' => false,
        'mensaje' => '❌ Faltan datos. Necesito cliente y productos para crear la orden.'
      ];
    }

    // Calcular totales
    $total_prendas = 0;
    $total_monto = 0;
    foreach ($productos as $p) {
      $total_prendas += $p['cantidad'] ?? 1;
      $total_monto += ($p['precio'] ?? 0) * ($p['cantidad'] ?? 1);
    }

    // ==========================================================
    // CREAR ORDEN
    // ==========================================================
    $sql_orden = "INSERT INTO ordenes (
      id_wp, nombre, apellido, telefono, correo_electronico,
      fecha_entrega, total_prendas, total, status, forma_pago,
      total_dolar, abono_dolar, restante_dolar,
      id_responsable, created_at
    ) VALUES (
      ?, ?, '', ?, ?,
      ?, ?, ?, 'En espera', 'Por definir',
      ?, 0, ?,
      ?, NOW()
    )";

    $cliente_nombre_parts = explode(' ', $cliente['nombre'], 2);
    $first_name = $cliente_nombre_parts[0] ?? '';
    $last_name = $cliente_nombre_parts[1] ?? '';

    $db->goQuery($sql_orden, [
      $cliente['id'],
      $first_name,
      $cliente['telefono'] ?? '',
      $cliente['email'] ?? '',
      $fecha_entrega,
      $total_prendas,
      $total_monto,
      $total_monto,
      $total_monto,
      $responsable_id
    ]);

    $orden_id = $db->getLastID();

    if (!$orden_id) {
      return ['ok' => false, 'mensaje' => '❌ Error al crear la orden. Intenta de nuevo.'];
    }

    // ==========================================================
    // CREAR PRODUCTOS DE LA ORDEN
    // ==========================================================
    foreach ($productos as $p) {
      $sql_prod = "INSERT INTO ordenes_productos (
        id_order, id_product, product, qty, price, talla, id_talla, tela, id_tela, corte
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $db->goQuery($sql_prod, [
        $orden_id,
        $p['producto_id'],
        $p['nombre'],
        $p['cantidad'],
        $p['precio'] ?? 0,
        $p['talla_nombre'] ?? '',
        $p['talla_id'],
        $p['tela_nombre'] ?? '',
        $p['tela_id'],
        ''
      ]);
    }

    // ==========================================================
    // CREAR LOTES DE PRODUCCIÓN
    // ==========================================================
    foreach ($productos as $p) {
      for ($i = 0; $i < ($p['cantidad'] ?? 1); $i++) {
        $sql_lote = "INSERT INTO lotes (
          id_orden, producto, cantidad, talla, tela, status, created_at
        ) VALUES (?, ?, 1, ?, ?, 'pendiente', NOW())";

        $db->goQuery($sql_lote, [
          $orden_id,
          $p['nombre'],
          $p['talla_nombre'] ?? '',
          $p['tela_nombre'] ?? ''
        ]);
      }
    }

    return [
      'ok' => true,
      'orden_id' => $orden_id,
      'cliente' => $cliente,
      'total' => $total_monto,
      'fecha_entrega' => $fecha_entrega,
      'total_prendas' => $total_prendas,
      'mensaje' => "✅ **¡Orden #{$orden_id} creada exitosamente!**\n\n" .
        "• Cliente: {$cliente['nombre']}\n" .
        "• Prendas: {$total_prendas}\n" .
        "• Total: $" . number_format($total_monto, 2) . "\n" .
        "• Entrega: {$fecha_entrega}"
    ];
  }

  /** FIN ORDENES */

  // CONVERTIR PRESUPUESTO A ORDEN
  $app->post('/presupuesto/{id}/convertir-a-orden', function (Request $request, Response $response, $args) {
  $id_presupuesto = intval($args['id']);
  $data = $request->getParsedBody();
  $localConnection = new LocalDB();
  $object = [];

  // VALIDAR QUE EL PRESUPUESTO EXISTE Y NO ESTÁ CONVERTIDO
  $sql = 'SELECT * FROM presupuestos WHERE _id = ?';
  $presupuesto = $localConnection->goQuery($sql, [$id_presupuesto]);

  if (empty($presupuesto)) {
    $object['error'] = 'Presupuesto no encontrado';
    $response->getBody()->write(json_encode($object));
    $localConnection->disconnect();
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(404);
  }

  $presupuesto = $presupuesto[0];

  if ($presupuesto['status'] === 'Convertido') {
    $object['error'] = 'Este presupuesto ya fue convertido a orden';
    $response->getBody()->write(json_encode($object));
    $localConnection->disconnect();
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(400);
  }

  // OBTENER PRODUCTOS DEL PRESUPUESTO
  $sql_productos = 'SELECT * FROM presupuestos_productos WHERE id_orden = ?';
  $productos = $localConnection->goQuery($sql_productos, [$id_presupuesto]);

  // PREPARAR DATOS PARA LA ORDEN
  $myDate = new CustomTime();
  $now = $myDate->today();
  $token = substr(md5(microtime()), 1, 15);

  // CALCULAR TOTAL Y ABONO
  $total = isset($data['total']) ? floatval($data['total']) : floatval($presupuesto['pago_total']);
  $descuento = isset($data['descuento']) ? floatval($data['descuento']) : 0;

  // SUMAR TODOS LOS MONTOS DE PAGO PARA EL ABONO
  $abono = 0;
  $abono += isset($data['montoDolaresEfectivo']) ? floatval($data['montoDolaresEfectivo']) : 0;
  $abono += isset($data['montoDolaresZelle']) ? floatval($data['montoDolaresZelle']) : 0;
  $abono += isset($data['montoDolaresPanama']) ? floatval($data['montoDolaresPanama']) : 0;
  $abono += isset($data['montoPesosEfectivo']) ? floatval($data['montoPesosEfectivo']) : 0;
  $abono += isset($data['montoPesosPago']) ? floatval($data['montoPesosPago']) : 0;
  $abono += isset($data['montoBolivaresEfectivo']) ? floatval($data['montoBolivaresEfectivo']) : 0;
  $abono += isset($data['montoBolivaresPago']) ? floatval($data['montoBolivaresPago']) : 0;

  // CREAR ORDEN EN TABLA ORDENES
  $localConnection->beginTransaction();
  try {
  $sql_orden = 'INSERT INTO ordenes (
id_wp,
status,
tipo,
responsable,
cliente_nombre,
cliente_cedula,
fecha_inicio,
fecha_entrega,
fecha_creacion,
token,
pago_descuento,
pago_total,
pago_abono,
pago_comision,
moment
) VALUES (?, \'activa\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendiente\', ?)';

  $object['sql_orden'] = $sql_orden;
  $localConnection->goQuery($sql_orden, [
    intval($presupuesto['id_wp']),
    $presupuesto['tipo'],
    intval($presupuesto['responsable']),
    $presupuesto['cliente_nombre'],
    $presupuesto['cliente_cedula'],
    $presupuesto['fecha_inicio'],
    $presupuesto['fecha_entrega'],
    $now,
    $token,
    $descuento,
    $total,
    $abono,
    $now,
  ]);
  $id_orden = $localConnection->getLastID();
  $object['id_orden'] = $id_orden;

  // REGISTRAR ABONO Y DESCUENTO INICIAL EN TABLA ABONOS
  if (floatval($abono) > 0 || floatval($descuento) > 0) {
    $sql_abono = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento, detalle) VALUES (?, ?, ?, ?, ?, 'Abono inicial por presupuesto')";
    $localConnection->goQuery($sql_abono, [$now, $id_orden, intval($presupuesto['responsable']), floatval($abono), floatval($descuento)]);
  }

  // INSERTAR OBSERVACIONES SI EXISTEN
  if (!empty($presupuesto['observaciones'])) {
    $sql_obs = 'INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES (?, ?)';
    $localConnection->goQuery($sql_obs, [$id_orden, $presupuesto['observaciones']]);
  }

  // COPIAR PRODUCTOS A ORDENES_PRODUCTOS
  foreach ($productos as $producto) {
    $sql_producto = 'INSERT INTO ordenes_productos (
id_orden, id_woo, id_category, category_name, name, cantidad,
talla, corte, tela, precio_unitario, precio_woo,
id_products_attributes, id_size, id_tela, moment
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $localConnection->goQuery($sql_producto, [
      $id_orden,
      intval($producto['id_woo']),
      intval($producto['id_category']),
      $producto['category_name'],
      $producto['name'],
      floatval($producto['cantidad']),
      $producto['talla'],
      $producto['corte'],
      $producto['tela'],
      floatval($producto['precio_unitario']),
      floatval($producto['precio_woo']),
      $producto['id_products_attributes'] ?? null,
      $producto['id_size'] ?? null,
      $producto['id_tela'] ?? null,
      $now,
    ]);
  }

  // REGISTRAR MÉTODOS DE PAGO
  $tasa_dolar = isset($data['tasa_dolar']) ? floatval($data['tasa_dolar']) : 1;
  $tasa_peso = isset($data['tasa_peso']) ? floatval($data['tasa_peso']) : 1;

  $sql_metodo_pago = 'INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, detalle, tipo_de_pago, monto, tasa, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

  // Dólares Efectivo
  if (isset($data['montoDolaresEfectivo']) && $data['montoDolaresEfectivo'] > 0) {
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Dolares', 'Efectivo', '', 'Orden nueva', floatval($data['montoDolaresEfectivo']), $tasa_dolar, $now]);
  }

  // Dólares Zelle
  if (isset($data['montoDolaresZelle']) && $data['montoDolaresZelle'] > 0) {
    $detalle = $data['montoDolaresZelleDetalle'] ?? '';
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Dolares', 'Zelle', $detalle, 'Orden nueva', floatval($data['montoDolaresZelle']), $tasa_dolar, $now]);
  }

  // Dólares Banesco Panamá
  if (isset($data['montoDolaresPanama']) && $data['montoDolaresPanama'] > 0) {
    $detalle = $data['montoDolaresPanamaDetalle'] ?? '';
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Dolares', 'Banesco Panama', $detalle, 'Orden nueva', floatval($data['montoDolaresPanama']), $tasa_dolar, $now]);
  }

  // Pesos
  if (isset($data['montoPesosEfectivo']) && $data['montoPesosEfectivo'] > 0) {
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Pesos', 'Efectivo', '', 'Orden nueva', floatval($data['montoPesosEfectivo']), $tasa_peso, $now]);
  }

  if (isset($data['montoPesosPago']) && $data['montoPesosPago'] > 0) {
    $detalle = $data['montoPesosPagoDetalle'] ?? '';
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Pesos', 'Pago movil', $detalle, 'Orden nueva', floatval($data['montoPesosPago']), $tasa_peso, $now]);
  }

  // Bolívares (si aplica)
  if (isset($data['montoBolivaresEfectivo']) && $data['montoBolivaresEfectivo'] > 0) {
    $localConnection->goQuery($sql_metodo_pago, [$id_orden, 'Bolivares', 'Efectivo', '', 'Orden nueva', floatval($data['montoBolivaresEfectivo']), 1, $now]);
  }

  // CREAR LOTE DE PRODUCCIÓN
  $lote_code = 'L-' . str_pad($id_orden, 6, '0', STR_PAD_LEFT);
  $sql_lote = 'INSERT INTO lotes (lote, fecha, id_orden, id_departamento_actual, piezas_actuales, paso, moment) VALUES (?, ?, ?, NULL, NULL, ?, ?)';
  $localConnection->goQuery($sql_lote, [$lote_code, $now, $id_orden, 'pendiente', $now]);

  // MARCAR PRESUPUESTO COMO CONVERTIDO
  $sql_update = 'UPDATE presupuestos SET status = ? WHERE _id = ?';
  $localConnection->goQuery($sql_update, ['Convertido', $id_presupuesto]);

  $object['success'] = true;
  $object['message'] = 'Presupuesto convertido exitosamente a orden';

  $localConnection->commit();
  $response->getBody()->write(json_encode($object));
  $localConnection->disconnect();

  return $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus(200);

  } catch (\Throwable $e) {
    $localConnection->rollback();
    $localConnection->disconnect();
    $object['error'] = 'Error al convertir presupuesto: ' . $e->getMessage();
    $response->getBody()->write(json_encode($object));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
  }
  });
}; // Fin de la función que envuelve las rutas