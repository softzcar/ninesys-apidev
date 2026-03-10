<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** RETIROS */

  // REPORTE GENERAL DE PAGOS Y ABONOS
  $app->get('/reporte-de-pagos[/{inicio}/{fin}/{id_vendedor}]', function (Request $request, Response $response, array $args) {
    /** FONDO */
    $localConnection = new LocalDB();
    $localConnection->goQuery('SET group_concat_max_len = 1000000;');
    $inicio = isset($args['inicio']) ? $args['inicio'] : null;
    $fin = isset($args['fin']) ? $args['fin'] : null;
    $vendedor = isset($args['id_vendedor']) ? $args['id_vendedor'] : null;

    /* if (isset($args["id_vendedor"])) {
            $vendedor = $args["id_vendedor"];
        } else {
            $object["vendedor"] = $args["id_vendedor"];
            $vendedor = null;
        } */

    if (!is_null($vendedor)) {
      if ($vendedor == '0') {
        $searchVendedor = '';
      } else {
        $searchVendedor = ' AND ord.responsable = ' . $vendedor . ' ';
      }
    } else {
      $searchVendedor = '';
    }

    $object['searchVendedor'] = $searchVendedor;

    if (is_null($inicio) || is_null($fin)) {
      $sql = "SELECT
    met._id,
    ord._id orden,
    ord.responsable id_empleado,
    emp.nombre empleado,
    met.metodo_pago,
    met.monto,
    met.detalle,
    met.tasa,
    met.moneda,
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
    DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
    DATE_FORMAT(met.moment, '%h:%i %p') AS hora
    FROM
        metodos_de_pago met
    JOIN ordenes ord ON met.id_orden = ord._id 
    JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
    WHERE
    (ord.status = 'activa' OR ord.status = 'En espera' OR ord.status = 'terminada' OR ord.status = 'pausada' OR ord.status = 'entregada')
        -- met.moment >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        -- AND MONTH(met.moment) = MONTH(CURDATE()) -- Comentar esta línea
        {$searchVendedor}
    ORDER BY
        met.id_orden DESC, met.moment ASC;";
      /* $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                YEAR(met.moment) = YEAR(CURDATE())
                AND MONTH(met.moment) = MONTH(CURDATE())
                " . $searchVendedor . '
            ORDER BY
                met.id_orden DESC, met.moment ASC;
            '; */
    } else {
      $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
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
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                DATE(met.moment) BETWEEN '" . $inicio . "' AND '" . $fin . "' 
                " . $searchVendedor . '
                ORDER BY
                met.id_orden DESC, met.moment ASC;';
    }

    // $object['sql_pagos'] = $sql;

    $object['pagos'] = $localConnection->goQuery($sql);

    $pagos = $object['pagos'];

    foreach ($pagos as &$pago) {
      if (isset($pago['product_categories'])) {
        $pago['product_categories'] = json_decode($pago['product_categories']);
      }
    }
    $object['pagos'] = $pagos;

    // Buscar todos los empleados que sean vendedres o administradores
    $sqlv = "SELECT
        id_usuario _id,
        nombre
    FROM
        api_empresas.empresas_usuarios a 
    JOIN api_empresas.empresas_usuarios_departamentos b ON a.id_usuario = b.id_empleado
    WHERE
        (b.id_departamento = 7 OR b.id_departamento = 8)  AND a.id_empresa = " . ID_EMPRESA;
    $object['vendedores'] = $localConnection->goQuery($sqlv);
    $object['SQL'] = $sqlv;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Datos para efectuar el cietre de caja
  $app->get('/cierre-de-caja/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_vendedor = $args['id_vendedor'];
    $object = ['data' => []];

    // 1. Get the latest cash fund for the vendor using a prepared statement
    $sql_fondo = 'SELECT dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
    $fondo = $localConnection->goQuery($sql_fondo, [$id_vendedor]);

    $fondo_dolares = !empty($fondo) ? (float) $fondo[0]['dolares'] : 0;
    $fondo_pesos = !empty($fondo) ? (float) $fondo[0]['pesos'] : 0;
    $fondo_bolivares = !empty($fondo) ? (float) $fondo[0]['bolivares'] : 0;

    $object['data']['fondo'] = [['dolares' => $fondo_dolares, 'pesos' => $fondo_pesos, 'bolivares' => $fondo_bolivares]];

    // 2. Get the sum of open cash transactions for each currency using a prepared statement
    $sql_caja = 'SELECT 
                        moneda, 
                        (SUM(monto)) as total_monto                        
                     FROM caja 
                     WHERE id_empleado = ? AND id_caja_cierres IS NULL 
                     GROUP BY moneda';
    $caja_entries = $localConnection->goQuery($sql_caja, [$id_vendedor]);

    $caja_dolares = 0;
    $caja_pesos = 0;
    $caja_bolivares = 0;

    foreach ($caja_entries as $entry) {
      switch ($entry['moneda']) {
        case 'Dólares':
          $caja_dolares = (float) $entry['total_monto'];
          break;
        case 'Pesos':
          $caja_pesos = (float) $entry['total_monto'];
          break;
        case 'Bolívares':
          $caja_bolivares = (float) $entry['total_monto'];
          break;
      }
    }

    // 3. Calculate total cash on hand and structure the response
    $total_dolares = $fondo_dolares + $caja_dolares;
    $total_pesos = $fondo_pesos + $caja_pesos;
    $total_bolivares = $fondo_bolivares + $caja_bolivares;

    $object['data']['dolares'] = [['moneda' => 'Dólares', 'monto' => $total_dolares]];
    $object['data']['pesos'] = [['moneda' => 'Pesos', 'monto' => $total_pesos]];
    $object['data']['bolivares'] = [['moneda' => 'Bolívares', 'monto' => $total_bolivares]];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar Cierre de caja
  $app->post('/cierre-de-caja-vendedor', function (Request $request, Response $response, $args) {
    $datosCierre = $request->getParsedBody();
    $localConnection = new LocalDB();

    // $object['response_DB'] = $localConnection;

    // Guardamos el cierre
    $sql = ' INSERT INTO caja_cierres (dolares, pesos, bolivares, id_empleado) VALUES (' . $datosCierre['cierreDolaresEfectivo'] . ', ' . $datosCierre['cierrePesosEfectivo'] . ', ' . $datosCierre['cierreBolivaresEfectivo'] . ', ' . $datosCierre['id_empleado'] . ');';
    $responseCierreCaja = $localConnection->goQuery($sql);

    // Identificamos el ID del INSERT
    $insertID = $responseCierreCaja['insert_id'];

    // Insertamos caja_fondos
    $sql = "INSERT INTO caja_fondos (id_empleado, dolares, id_caja_cierres, pesos, bolivares) VALUES ({$datosCierre['id_empleado']}, {$datosCierre['fondoDolares']}, $insertID, {$datosCierre['fondoPesos']}, {$datosCierre['fondoBolivares']})";
    $object['response_insert_caja_fondos'] = $localConnection->goQuery($sql);

    // Actualizamos caja para los registros cerrados
    $sql = "UPDATE caja SET id_caja_cierres = $insertID WHERE id_empleado = {$datosCierre['id_empleado']}";
    $object['response_update_caja'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode(str_replace("\r", '', $object)));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Reporte de caja
  $app->get('/reporte-de-caja/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $id_vendedor = (int)$args['id_vendedor'];
    $inicio = $args['inicio'];
    $fin = $args['fin'];

    if ($inicio === $fin) {
      $whereBase = "moment LIKE '$inicio%'";
    } else {
      $whereBase = "moment BETWEEN '$inicio' AND '$fin'";
    }

    // Filtro de vendedor
    $filterUserCaja = $id_vendedor === 0 ? "" : " AND id_empleado = $id_vendedor";
    $filterUserOrdenes = $id_vendedor === 0 ? "" : " AND o.responsable = $id_vendedor";
    $filterUserRetiros = $id_vendedor === 0 ? "" : " AND a.id_empleado = $id_vendedor";

    /** EFECTIVO (Dólares, Pesos, Bolívares) */
    $tiposMoneda = ['Dólares', 'Pesos', 'Bolívares'];
    foreach ($tiposMoneda as $moneda) {
      $monedaKey = strtolower(str_replace('ó', 'o', $moneda));
      $sql = "SELECT 
                SUM(monto) monto, 
                '$moneda' moneda, 
                tasa, 
                SUM(monto / tasa) dolares
              FROM caja 
              WHERE $whereBase $filterUserCaja AND moneda = '$moneda'
              GROUP BY tasa";
      $object['data']['efectivo'][$monedaKey] = $localConnection->goQuery($sql);
    }

    /** MONEDA DIGITAL */
    $metodosDigitales = ['Zelle', 'Pagomovil', 'Punto', 'Transferencia'];
    foreach ($metodosDigitales as $metodo) {
      $metodoKey = strtolower($metodo);
      $sql = "SELECT 
                SUM(a.monto) monto, 
                a.tasa, 
                SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
                a.moneda, 
                '$metodo' metodo_pago
              FROM metodos_de_pago AS a 
              JOIN ordenes AS o ON a.id_orden = o._id
              WHERE a.metodo_pago = '$metodo' AND a.$whereBase $filterUserOrdenes
              GROUP BY a.tasa, a.moneda";
      $object['data']['digital'][$metodoKey] = $localConnection->goQuery($sql);
    }

    /** RETIROS */
    $sql = "SELECT 
              SUM(a.monto) monto, 
              a.moneda, 
              a.tasa, 
              SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
              'Retiros' metodo_pago
            FROM retiros AS a 
            WHERE DATE(a.moment) BETWEEN '$inicio' AND '$fin' $filterUserRetiros
            GROUP BY a.tasa, a.moneda";

    $object['data']['retiros'] = $localConnection->goQuery($sql);

    // Obtener lista de vendedores para el select del frontend
    $sqlv = "SELECT
                id_usuario _id,
                nombre
            FROM
                api_empresas.empresas_usuarios a 
            JOIN api_empresas.empresas_usuarios_departamentos b ON a.id_usuario = b.id_empleado
            WHERE
                (b.id_departamento = 7 OR b.id_departamento = 8) AND a.id_empresa = " . ID_EMPRESA . " 
            ORDER BY nombre ASC";
    $object['vendedores'] = $localConnection->goQuery($sqlv);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar nuevo retiro
  $app->post('/retiro', function (Request $request, Response $response) {
    try {
      $arr = $request->getParsedBody();
      $localConnection = new LocalDB();
      $id_empleado = intval($arr['id_empleado']);
      $detalle = $arr['detalle'] ?? '';
      $tasa_peso = floatval($arr['tasa_peso'] ?? 1);
      $tasa_bolivar = floatval($arr['tasa_dolar'] ?? 1);

      // 1. Obtener saldos actuales para validar
      // (Reutilizamos la lógica del GET /retiros pero simplificada para validación)
      
      // Fondo
      $sqlFondo = 'SELECT dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
      $fondoRes = $localConnection->goQuery($sqlFondo, [$id_empleado]);
      $fondo = !empty($fondoRes) ? $fondoRes[0] : ['dolares' => 0, 'pesos' => 0, 'bolivares' => 0];

      $saldos = [];
      $monedas = ['Dólares', 'Pesos', 'Bolívares'];
      foreach ($monedas as $moneda) {
        $extraFondo = 0;
        if ($moneda === 'Pesos') $extraFondo = $fondo['pesos'];
        if ($moneda === 'Bolívares') $extraFondo = $fondo['bolivares'];

        $sqlSaldo = "SELECT (IFNULL(SUM(c.monto), 0) + $extraFondo - IFNULL(SUM(a.monto), 0)) AS saldo 
                     FROM caja c 
                     LEFT JOIN retiros a ON c.id_empleado = a.id_empleado AND a.moneda = ? 
                     WHERE c.moneda = ? AND c.id_caja_cierres IS NULL AND c.id_empleado = ?";
        $res = $localConnection->goQuery($sqlSaldo, [$moneda, $moneda, $id_empleado]);
        $saldos[$moneda] = !empty($res) ? floatval($res[0]['saldo']) : 0;
      }

      // 2. Validar montos solicitados
      $solicitado = [
        'Dólares' => floatval($arr['montoDolaresEfectivo'] ?? 0),
        'Pesos' => floatval($arr['montoPesosEfectivo'] ?? 0),
        'Bolívares' => floatval($arr['montoBolivaresEfectivo'] ?? 0)
      ];

      foreach ($solicitado as $moneda => $monto) {
        if ($monto > 0 && $monto > $saldos[$moneda]) {
           $localConnection->disconnect();
           $object['statusCode'] = 400;
           $object['status'] = 'error';
           $object['message'] = "Saldo insuficiente en $moneda. Disponible: " . number_format($saldos[$moneda], 2);
           $response->getBody()->write(json_encode($object));
           return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
      }

      // 3. Procesar inserciones (Sentencias Preparadas)
      $localConnection->beginTransaction();
      
      $insertSql = "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES (?, ?, ?, ?, ?, ?)";
      
      // Mapeo de campos del form a moneda/metodo
      $operaciones = [
        ['montoDolaresEfectivo', 'Dólares', 'Efectivo', 1],
        ['montoDolaresZelle', 'Dólares', 'Zelle', 1],
        ['montoDolaresPanama', 'Dólares', 'Panamá', 1],
        ['montoPesosEfectivo', 'Pesos', 'Efectivo', $tasa_peso],
        ['montoPesosTransferencia', 'Pesos', 'Transferencia', $tasa_peso],
        ['montoBolivaresEfectivo', 'Bolívares', 'Efectivo', $tasa_bolivar],
        ['montoBolivaresPunto', 'Bolívares', 'Punto', $tasa_bolivar],
        ['montoBolivaresPagomovil', 'Bolívares', 'Pagomovil', $tasa_bolivar],
        ['montoBolivaresTransferencia', 'Bolívares', 'Transferencia', $tasa_bolivar]
      ];

      foreach ($operaciones as $op) {
        $monto = floatval($arr[$op[0]] ?? 0);
        if ($monto > 0) {
          $localConnection->goQuery($insertSql, [$id_empleado, $op[1], $op[2], $monto, $detalle, $op[3]]);
        }
      }

      $localConnection->commit();
      $localConnection->disconnect();

      $object['statusCode'] = 200;
      $object['status'] = 'success';
      $object['message'] = 'Retiro registrado correctamente';
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      if (isset($localConnection)) {
        $localConnection->rollback();
        $localConnection->disconnect();
      }
      $object['statusCode'] = 500;
      $object['status'] = 'error';
      $object['message'] = $e->getMessage();
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // Guardar nuevo abono
  $app->post('/otro-abono', function (Request $request, Response $response) {
    $arr = $request->getParsedBody();
    $localConnection = new LocalDB();

    // ========== VALIDACIONES ==========
    $totalAbono = floatval($arr['abono'] ?? 0);
    if ($totalAbono <= 0) {
      return ApiResponse::validationError($response, 'El monto del abono debe ser mayor a cero');
    }

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      // Validar si se ha enviado un id_orden y asignarlo. De lo contrario, usar 0.
      $id_orden_abono = isset($arr['id_orden']) ? intval($arr['id_orden']) : 0;

      $sql = '';

      // Si es un abono a una orden específica, registrarlo también en la tabla `abonos`
      if ($id_orden_abono > 0) {
        $now = date('Y-m-d H:i:s');
        $abono = floatval($arr['abono'] ?? 0);
        $descuento = 0;
        $id_empleado = isset($arr['id_empleado']) ? intval($arr['id_empleado']) : 0;

        $sqlAbono = "INSERT INTO abonos (moment, id_orden, abono, descuento, id_empleado) VALUES (?, ?, ?, ?, ?)";
        $localConnection->goQuery($sqlAbono, [$now, $id_orden_abono, $abono, $descuento, $id_empleado]);
      }

      // GUARDAR METODOS DE PAGO UTILIZADOS
      $metodosRegistrados = 0;

      if (floatval($arr['montoDolaresEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Dólares', 'Efectivo', ?, ?, '1')";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresEfectivo'], $arr['detalle'] ?? '']);

        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES (?, 'Dólares', 1, 'abono', ?)";
        $localConnection->goQuery($sql, [$arr['montoDolaresEfectivo'], $arr['id_empleado'] ?? 0]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoDolaresZelle'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Dólares', 'Zelle', ?, ?, '1')";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresZelle'], $arr['detalle'] ?? '']);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoDolaresPanama'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Dólares', 'Panamá', ?, ?, '1')";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresPanama'], $arr['detalle'] ?? '']);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoPesosEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Pesos', 'Efectivo', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoPesosEfectivo'], $arr['detalle'] ?? '', $arr['tasa_peso'] ?? 1]);

        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES (?, 'Pesos', ?, 'abono', ?)";
        $localConnection->goQuery($sql, [$arr['montoPesosEfectivo'], $arr['tasa_peso'] ?? 1, $arr['id_empleado'] ?? 0]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoPesosTransferencia'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Pesos', 'Transferencia', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoPesosTransferencia'], $arr['detalle'] ?? '', $arr['tasa_peso'] ?? 1]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoBolivaresEfectivo'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Bolívares', 'Efectivo', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresEfectivo'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1]);

        $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES (?, 'Bolívares', ?, 'abono', ?)";
        $localConnection->goQuery($sql, [$arr['montoBolivaresEfectivo'], $arr['tasa_dolar'] ?? 1, $arr['id_empleado'] ?? 0]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoBolivaresPunto'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Bolívares', 'Punto', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresPunto'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoBolivaresPagomovil'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Bolívares', 'Pagomovil', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresPagomovil'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1]);
        $metodosRegistrados++;
      }

      if (floatval($arr['montoBolivaresTransferencia'] ?? 0) > 0) {
        $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES (?, ?, 'Bolívares', 'Transferencia', ?, ?, ?)";
        $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresTransferencia'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1]);
        $metodosRegistrados++;
      }

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Abono registrado correctamente', [
        'id_orden' => $id_orden_abono,
        'monto_abono' => $totalAbono,
        'metodos_registrados' => $metodosRegistrados
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /otro-abono: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al registrar el abono: ' . $e->getMessage(), $e);
    }
  });

  // Obteber Retiros
  $app->get('/retiros/{inicio}/{fin}/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // Obtener retiros
    $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario WHERE DATE(a.moment) BETWEEN '" . $args['inicio'] . "' AND '" . $args['fin'] . "' ORDER BY a.moment DESC";

    $pbject['sql']['data_retiros'] = $sql;
    $object['data']['retiros'] = $localConnection->goQuery($sql);

    /** FONDO */
    $sql = 'SELECT dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
    $fondo = $localConnection->goQuery($sql, [$args['id_empleado']]);
    // $pbject['sql']['data_fondo'] = $sql;
    $object['data']['fondo'] = $fondo;

    if (empty($fondo)) {
      $fondo[0]['dolares'] = 0;
      $fondo[0]['pesos'] = 0;
      $fondo[0]['bolivares'] = 0;
    }
     $id_emp = $args['id_empleado'];

    // DÓLARES EN CAJA
    $montoDolaresCaja = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM caja WHERE moneda = 'Dólares' AND id_caja_cierres IS NULL AND id_empleado = ?", [$id_emp])[0]['total']);
    $montoDolaresRetiros = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM retiros WHERE moneda = 'Dólares' AND cierre_caja = 0 AND id_empleado = ?", [$id_emp])[0]['total']);
    $saldoDolares = floatval($montoDolaresCaja + ($fondo[0]['dolares'] ?? 0) - $montoDolaresRetiros);

    $object['data']['caja'] = [[
        'monto' => $saldoDolares,
        'moneda' => 'Dólares',
        'tasa' => 1,
        'dolares' => '$' . number_format($saldoDolares, 2)
    ]];

    // PESOS EN CAJA
    $montoPesosCaja = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM caja WHERE moneda = 'Pesos' AND id_caja_cierres IS NULL AND id_empleado = ?", [$id_emp])[0]['total']);
    $montoPesosRetiros = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM retiros WHERE moneda = 'Pesos' AND cierre_caja = 0 AND id_empleado = ?", [$id_emp])[0]['total']);
    $saldoPesos = floatval($montoPesosCaja + ($fondo[0]['pesos'] ?? 0) - $montoPesosRetiros);
    
    $resTasaP = $localConnection->goQuery("SELECT tasa FROM caja WHERE moneda = 'Pesos' AND id_empleado = ? ORDER BY _id DESC LIMIT 1", [$id_emp]);
    $tasaP = floatval(!empty($resTasaP) ? $resTasaP[0]['tasa'] : 1);
    if($tasaP <= 0) $tasaP = 1;

    array_push($object['data']['caja'], [
        'monto' => $saldoPesos,
        'moneda' => 'Pesos',
        'tasa' => $tasaP,
        'dolares' => '$' . number_format($saldoPesos / $tasaP, 2)
    ]);

    // BOLIVARES EN CAJA
    $montoBolivaresCaja = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM caja WHERE moneda = 'Bolívares' AND id_caja_cierres IS NULL AND id_empleado = ?", [$id_emp])[0]['total']);
    $montoBolivaresRetiros = floatval($localConnection->goQuery("SELECT IFNULL(SUM(monto), 0) as total FROM retiros WHERE moneda = 'Bolívares' AND cierre_caja = 0 AND id_empleado = ?", [$id_emp])[0]['total']);
    $saldoBolivares = floatval($montoBolivaresCaja + ($fondo[0]['bolivares'] ?? 0) - $montoBolivaresRetiros);

    $resTasaB = $localConnection->goQuery("SELECT tasa FROM caja WHERE moneda = 'Bolívares' AND id_empleado = ? ORDER BY _id DESC LIMIT 1", [$id_emp]);
    $tasaB = floatval(!empty($resTasaB) ? $resTasaB[0]['tasa'] : 1);
    if($tasaB <= 0) $tasaB = 1;

    array_push($object['data']['caja'], [
        'monto' => $saldoBolivares,
        'moneda' => 'Bolívares',
        'tasa' => $tasaB,
        'dolares' => '$' . number_format($saldoBolivares/ $tasaB, 2)
    ]);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Pagos Ordenes
  $app->get('/pagos-ordenes/{fecha}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT _id, moment, monto, moneda, metodo_pago, id_orden, tasa FROM metodos_de_pago WHERE moment LIKE '" . $args['fecha'] . "%'";
    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN RETIROS */

}; // Fin de la función que envuelve las rutas
