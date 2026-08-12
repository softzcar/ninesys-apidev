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
    if (DB_DRIVER !== 'pgsql') {
      $localConnection->goQuery('SET group_concat_max_len = 1000000;');
    }
    $inicio = isset($args['inicio']) ? $args['inicio'] : null;
    $fin = isset($args['fin']) ? $args['fin'] : null;
    $vendedor = isset($args['id_vendedor']) ? $args['id_vendedor'] : null;

    /* if (isset($args["id_vendedor"])) {
            $vendedor = $args["id_vendedor"];
        } else {
            $object["vendedor"] = $args["id_vendedor"];
            $vendedor = null;
        } */

    $searchVendedor = '';
    $paramsVendedor = [];
    if (!is_null($vendedor) && $vendedor != '0') {
      $searchVendedor = ' AND ord.responsable = ?';
      $paramsVendedor[] = (int) $vendedor;
    }

    if (is_null($inicio) || is_null($fin)) {
    if (DB_DRIVER === 'pgsql') {
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
            SUM(op.cantidad * op.precio_unitario)
        FROM
            ordenes_productos op
        WHERE
            op.id_orden = ord._id
    ) AS total_orden,
    (SELECT COALESCE(SUM(descuento), 0) FROM abonos WHERE id_orden = ord._id) AS total_descuento,
    (SELECT COALESCE(SUM(nota_credito), 0) FROM abonos WHERE id_orden = ord._id) AS total_nota_credito,
    (SELECT COALESCE(SUM(abono), 0) FROM abonos WHERE id_orden = ord._id) AS total_abonos_base,
    (
        SELECT
            COALESCE(json_agg(
                json_build_object(
                    'category_name', t_cat.category_name,
                    'category_total', t_cat.category_total
                )
            )::text, '[]')
        FROM (
            SELECT 
                op_in.id_orden,
                c_in.nombre as category_name,
                SUM(op_in.cantidad * op_in.precio_unitario) as category_total
            FROM 
                ordenes_productos op_in
            JOIN products p_in ON op_in.id_woo = p_in._id
            JOIN categories c_in ON (c_in._id::text = ANY(string_to_array(p_in.category_ids, ',')))
            GROUP BY 
                op_in.id_orden, c_in.nombre
        ) t_cat
        WHERE t_cat.id_orden = ord._id
    ) AS product_categories,
    TO_CHAR(met.moment, 'DD/MM/YYYY') AS fecha,
    TO_CHAR(met.moment, 'HH12:MI AM') AS hora
    FROM
        metodos_de_pago met
    JOIN ordenes ord ON met.id_orden = ord._id 
    JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
    WHERE
    (ord.status = 'activa' OR ord.status = 'En espera' OR ord.status = 'terminada' OR ord.status = 'pausada' OR ord.status = 'entregada')
        {$searchVendedor}
    ORDER BY
        met.id_orden DESC, met.moment ASC;";
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
            SUM(op.cantidad * op.precio_unitario)
        FROM
            ordenes_productos op
        WHERE
            op.id_orden = ord._id
    ) AS total_orden,
    (SELECT COALESCE(SUM(descuento), 0) FROM abonos WHERE id_orden = ord._id) AS total_descuento,
    (SELECT COALESCE(SUM(nota_credito), 0) FROM abonos WHERE id_orden = ord._id) AS total_nota_credito,
    (SELECT COALESCE(SUM(abono), 0) FROM abonos WHERE id_orden = ord._id) AS total_abonos_base,
    (
        SELECT
            CONCAT(
                '[',
                COALESCE(GROUP_CONCAT(
                    JSON_OBJECT(
                        'category_name', t_cat.category_name,
                        'category_total', t_cat.category_total
                    )
                ), ''),
                ']'
            )
        FROM (
            SELECT 
                op_in.id_orden,
                c_in.nombre as category_name,
                SUM(op_in.cantidad * op_in.precio_unitario) as category_total
            FROM 
                ordenes_productos op_in
            JOIN products p_in ON op_in.id_woo = p_in._id
            JOIN categories c_in ON FIND_IN_SET(c_in._id, p_in.category_ids)
            GROUP BY 
                op_in.id_orden, c_in.nombre
        ) t_cat
        WHERE t_cat.id_orden = ord._id
    ) AS product_categories,
    DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
    DATE_FORMAT(met.moment, '%h:%i %p') AS hora
    FROM
        metodos_de_pago met
    JOIN ordenes ord ON met.id_orden = ord._id 
    JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
    WHERE
    (ord.status = 'activa' OR ord.status = 'En espera' OR ord.status = 'terminada' OR ord.status = 'pausada' OR ord.status = 'entregada')
        {$searchVendedor}
    ORDER BY
        met.id_orden DESC, met.moment ASC;";
    }
      $params = $paramsVendedor;
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
    if (DB_DRIVER === 'pgsql') {
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
                        SUM(op.cantidad * op.precio_unitario)
                    FROM
                        ordenes_productos op
                    WHERE
                        op.id_orden = ord._id
                ) AS total_orden,
                (SELECT COALESCE(SUM(descuento), 0) FROM abonos WHERE id_orden = ord._id) AS total_descuento,
                (SELECT COALESCE(SUM(nota_credito), 0) FROM abonos WHERE id_orden = ord._id) AS total_nota_credito,
                (SELECT COALESCE(SUM(abono), 0) FROM abonos WHERE id_orden = ord._id) AS total_abonos_base,
                (
                    SELECT
                        COALESCE(json_agg(
                            json_build_object(
                                'category_name', t_cat.category_name,
                                'category_total', t_cat.category_total
                            )
                        )::text, '[]')
                    FROM (
                        SELECT 
                            op_in.id_orden,
                            c_in.nombre as category_name,
                            SUM(op_in.cantidad * op_in.precio_unitario) as category_total
                        FROM 
                            ordenes_productos op_in
                        JOIN products p_in ON op_in.id_woo = p_in._id
                        JOIN categories c_in ON (c_in._id::text = ANY(string_to_array(p_in.category_ids, ',')))
                        GROUP BY 
                            op_in.id_orden, c_in.nombre
                    ) t_cat
                    WHERE t_cat.id_orden = ord._id
                ) AS product_categories,
                TO_CHAR(met.moment, 'DD/MM/YYYY') AS fecha,
                TO_CHAR(met.moment, 'HH12:MI AM') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                met.moment::date BETWEEN ? AND ?
                " . $searchVendedor . '
                ORDER BY
                met.id_orden DESC, met.moment ASC;';
      $params = array_merge([$inicio, $fin], $paramsVendedor);
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
                        SUM(op.cantidad * op.precio_unitario)
                    FROM
                        ordenes_productos op
                    WHERE
                        op.id_orden = ord._id
                ) AS total_orden,
                (SELECT COALESCE(SUM(descuento), 0) FROM abonos WHERE id_orden = ord._id) AS total_descuento,
                (SELECT COALESCE(SUM(nota_credito), 0) FROM abonos WHERE id_orden = ord._id) AS total_nota_credito,
                (SELECT COALESCE(SUM(abono), 0) FROM abonos WHERE id_orden = ord._id) AS total_abonos_base,
                (
                    SELECT
                        CONCAT(
                            '[',
                            COALESCE(GROUP_CONCAT(
                                JSON_OBJECT(
                                    'category_name', t_cat.category_name,
                                    'category_total', t_cat.category_total
                                )
                            ), ''),
                            ']'
                        )
                    FROM (
                        SELECT 
                            op_in.id_orden,
                            c_in.nombre as category_name,
                            SUM(op_in.cantidad * op_in.precio_unitario) as category_total
                        FROM 
                            ordenes_productos op_in
                        JOIN products p_in ON op_in.id_woo = p_in._id
                        JOIN categories c_in ON FIND_IN_SET(c_in._id, p_in.category_ids)
                        GROUP BY 
                            op_in.id_orden, c_in.nombre
                    ) t_cat
                    WHERE t_cat.id_orden = ord._id
                ) AS product_categories,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                DATE(met.moment) BETWEEN ? AND ?
                " . $searchVendedor . '
                ORDER BY
                met.id_orden DESC, met.moment ASC;';
      $params = array_merge([$inicio, $fin], $paramsVendedor);
    }
  }

    // $object['sql_pagos'] = $sql;

    $object['pagos'] = $localConnection->goQuery($sql, $params);

    $pagos = $object['pagos'];

    foreach ($pagos as &$pago) {
      if (isset($pago['product_categories'])) {
        $pago['product_categories'] = json_decode($pago['product_categories']);
      }
    }
    $object['pagos'] = $pagos;

    // Buscar todos los empleados que sean vendedres o administradores -- JOIN contra
    // empresas_usuarios_empresas (no a.id_empresa/a.activo directo) porque una identidad
    // puede estar asignada a más de una empresa; a.id_empresa hoy es solo "la empresa
    // activa en el último login", no "pertenece a esta empresa" (ver /login, 2026-08-12).
    // También se quitó el leak de SQL completo en la respuesta.
    $sqlv = "SELECT DISTINCT
        a.id_usuario _id,
        a.nombre
    FROM
        api_empresas.empresas_usuarios a
    JOIN api_empresas.empresas_usuarios_empresas eue ON eue.id_usuario = a.id_usuario AND eue.id_empresa = " . ID_EMPRESA . " AND eue.activo = 1
    JOIN api_empresas.empresas_usuarios_departamentos b ON a.id_usuario = b.id_empleado AND b.id_empresa = " . ID_EMPRESA . "
    WHERE
        b.id_departamento IN (SELECT _id FROM departamentos WHERE departamento IN ('Comercialización', 'Comecialización', 'Administración'))";
    $object['vendedores'] = $localConnection->goQuery($sqlv);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Datos para efectuar el cietre de caja
  $app->get('/cierre-de-caja/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_vendedor = (int) $args['id_vendedor'];
    $object = ['data' => []];

    // Fase 8: dolares/pesos/bolivares son columnas fijas del último fondo
    // dejado (enfoque híbrido) -- se combinan con la tabla hija
    // caja_fondos_extra para cualquier moneda fuera de esas 3.
    $columnaPorCodigo = ['USD' => 'dolares', 'COP' => 'pesos', 'VES' => 'bolivares'];

    $sql_fondo = 'SELECT _id, dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
    $fondo = $localConnection->goQuery($sql_fondo, [$id_vendedor]);

    $fondoPorCodigo = [];
    if (!empty($fondo)) {
      $fondoPorCodigo['USD'] = (float) $fondo[0]['dolares'];
      $fondoPorCodigo['COP'] = (float) $fondo[0]['pesos'];
      $fondoPorCodigo['VES'] = (float) $fondo[0]['bolivares'];

      $fondoExtra = $localConnection->goQuery(
        'SELECT cm.codigo, SUM(cfe.monto) AS monto
         FROM caja_fondos_extra cfe
         JOIN catalogo_monedas cm ON cm._id = cfe.id_moneda
         WHERE cfe.id_caja_fondos = ?
         GROUP BY cm.codigo',
        [$fondo[0]['_id']]
      );
      foreach ($fondoExtra as $row) {
        $fondoPorCodigo[$row['codigo']] = (float) $row['monto'];
      }
    }

    // Caja abierta (sin cerrar) por CADA moneda activa real del catálogo --
    // ya no limitado a un switch de 3 strings hardcodeados.
    $monedasActivas = $localConnection->goQuery(
      'SELECT _id, codigo, nombre, simbolo FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC, nombre'
    );

    $object['data']['porMoneda'] = [];
    foreach ($monedasActivas as $moneda) {
      $sql_caja = 'SELECT COALESCE(SUM(monto), 0) AS total FROM caja WHERE moneda = ? AND id_empleado = ? AND id_caja_cierres IS NULL';
      $res = $localConnection->goQuery($sql_caja, [$moneda['nombre'], $id_vendedor]);
      $cajaMonto = !empty($res) ? (float) $res[0]['total'] : 0;

      // Antes no se restaban los retiros ya hechos -- "disponible para
      // cerrar" mostraba el bruto de caja, no lo que realmente queda
      // (hallazgo real: tras retirar, Retiros mostraba el saldo correcto
      // pero Cierre de Caja seguía mostrando el monto de antes del retiro,
      // 2026-08-03). Mismo cálculo que ya usa GET /retiros/.../{id_empleado}.
      $sql_retiros = 'SELECT COALESCE(SUM(monto), 0) AS total FROM retiros WHERE moneda = ? AND id_empleado = ? AND cierre_caja = 0';
      $resRetiros = $localConnection->goQuery($sql_retiros, [$moneda['nombre'], $id_vendedor]);
      $retirosMonto = !empty($resRetiros) ? (float) $resRetiros[0]['total'] : 0;

      $fondoMonto = $fondoPorCodigo[$moneda['codigo']] ?? 0;

      $object['data']['porMoneda'][] = [
        'id_moneda' => (int) $moneda['_id'],
        'codigo' => $moneda['codigo'],
        'nombre' => $moneda['nombre'],
        'simbolo' => $moneda['simbolo'],
        'fondo' => $fondoMonto,
        'caja' => $cajaMonto,
        'retiros' => $retirosMonto,
        'total' => $fondoMonto + $cajaMonto - $retirosMonto,
      ];
    }

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
    $object = [];

    // Atomicidad FK: cierre (caja_cierres + caja_fondos + UPDATE caja) en una transacción
    $localConnection->beginTransaction();

    try {
      // Fase 8 (rediseño 100% dinámico): dolares/pesos/bolivares quedan
      // congeladas en 0 para todo cierre nuevo -- son puramente históricas,
      // se conservan solo para que los cierres de antes de hoy sigan siendo
      // legibles. TODA moneda nueva (incluyendo USD/VES/COP) se escribe en
      // caja_cierres_extra/caja_fondos_extra por id_moneda real, sin ningún
      // mapeo de código a columna. Corrige el enfoque híbrido anterior,
      // señalado explícitamente por el usuario como un vestigio hardcodeado.
      $cierresGenericos = decodificarArrayJson($datosCierre, 'cierres');
      $fondosGenericos = decodificarArrayJson($datosCierre, 'fondos');

      $cierresExtra = [];
      foreach ($cierresGenericos as $item) {
        $monto = floatval($item['monto'] ?? 0);
        if ($monto <= 0) {
          continue;
        }
        $moneda = resolverMonedaPorId($localConnection, $item['id_moneda'] ?? 0);
        if (!$moneda) {
          throw new Exception('Moneda inválida o eliminada (id_moneda=' . ($item['id_moneda'] ?? '?') . ')');
        }
        $cierresExtra[] = ['id_moneda' => $moneda['id_moneda'], 'monto' => $monto];
      }

      $sql = 'INSERT INTO caja_cierres (dolares, pesos, bolivares, id_empleado) VALUES (0, 0, 0, ?)';
      $responseCierreCaja = $localConnection->goQuery($sql, [$datosCierre['id_empleado']]);
      $insertID = $responseCierreCaja['insert_id'];

      foreach ($cierresExtra as $extra) {
        insertarCierreCajaExtra($localConnection, $insertID, $extra['id_moneda'], $extra['monto']);
      }

      $fondosExtra = [];
      foreach ($fondosGenericos as $item) {
        $monto = floatval($item['monto'] ?? 0);
        if ($monto <= 0) {
          continue;
        }
        $moneda = resolverMonedaPorId($localConnection, $item['id_moneda'] ?? 0);
        if (!$moneda) {
          throw new Exception('Moneda inválida o eliminada (id_moneda=' . ($item['id_moneda'] ?? '?') . ')');
        }
        $fondosExtra[] = ['id_moneda' => $moneda['id_moneda'], 'monto' => $monto];
      }

      $sql = 'INSERT INTO caja_fondos (id_empleado, dolares, id_caja_cierres, pesos, bolivares) VALUES (?, 0, ?, 0, 0)';
      $fondoInsert = $localConnection->goQuery($sql, [$datosCierre['id_empleado'], $insertID]);
      $idCajaFondos = $fondoInsert['insert_id'];

      foreach ($fondosExtra as $extra) {
        insertarFondoCajaExtra($localConnection, $idCajaFondos, $extra['id_moneda'], $extra['monto']);
      }

      // Actualizamos caja para los registros cerrados
      $sql = 'UPDATE caja SET id_caja_cierres = ? WHERE id_empleado = ? AND id_caja_cierres IS NULL';
      $localConnection->goQuery($sql, [$insertID, $datosCierre['id_empleado']]);

      // Marcamos como cerrados los retiros pendientes (mismo criterio que caja arriba),
      // y les atribuimos el id_caja_cierres real -- sin esto, GET /balance-de-cierres
      // no tiene forma de restarlos del "teórico" y siempre muestra un Diff falso del
      // tamaño exacto de lo retirado (hallazgo real, 2026-08-03).
      $sql = 'UPDATE retiros SET cierre_caja = 1, id_caja_cierres = ? WHERE id_empleado = ? AND cierre_caja = 0';
      $localConnection->goQuery($sql, [$insertID, $datosCierre['id_empleado']]);

      $localConnection->commit();
      $localConnection->disconnect();

      $object['success'] = true;
      $object['id_caja_cierres'] = $insertID;
      $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    } catch (\Throwable $e) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      error_log('Error en /cierre-de-caja-vendedor: ' . $e->getMessage());
      return ApiResponse::serverError($response, 'Error al registrar el cierre de caja. Por favor intente nuevamente.', $e);
    }
  });

  // Reporte de caja
  $app->get('/reporte-de-caja/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $id_vendedor = (int)$args['id_vendedor'];
    $inicio = $args['inicio'];
    $fin = $args['fin'];

    if ($inicio === $fin) {
      // "moment" es timestamp; LIKE requiere cast a texto en PostgreSQL (no existe el
      // operador LIKE para timestamp), MySQL lo tolera con coercion implicita.
      $whereBase = DB_DRIVER === 'pgsql' ? "moment::text LIKE ?" : "moment LIKE ?";
      $paramsFecha = [$inicio . '%'];
    } else {
      $whereBase = "moment BETWEEN ? AND ?";
      $paramsFecha = [$inicio, $fin];
    }

    // Filtro de vendedor
    $filterUserCaja = '';
    $filterUserOrdenes = '';
    $filterUserRetiros = '';
    $paramsUser = [];
    if ($id_vendedor !== 0) {
      $filterUserCaja = ' AND id_empleado = ?';
      $filterUserOrdenes = ' AND o.responsable = ?';
      $filterUserRetiros = ' AND a.id_empleado = ?';
      $paramsUser = [$id_vendedor];
    }

    // Fase 8 (rediseño 100% dinámico): ni las monedas del efectivo ni los
    // métodos digitales están hardcodeados -- ambos se leen del catálogo
    // real de la empresa, así que una moneda o método nuevo agregado vía el
    // Gestor de Monedas aparece automáticamente en este reporte sin tocar
    // código. Sustituye los arrays fijos `$tiposMoneda`/`$metodosDigitales`.

    /** EFECTIVO: una entrada por cada moneda activa real */
    $monedasActivas = $localConnection->goQuery(
      'SELECT _id, codigo, nombre, simbolo FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC, nombre'
    );

    $object['data']['efectivo'] = [];
    foreach ($monedasActivas as $moneda) {
      $sql = "SELECT
                SUM(monto) monto,
                moneda,
                tasa,
                SUM(monto / tasa) monto_base
              FROM caja
              WHERE $whereBase $filterUserCaja AND id_moneda = ?
              GROUP BY moneda, tasa";
      $object['data']['efectivo'][] = [
        'id_moneda' => (int) $moneda['_id'],
        'codigo' => $moneda['codigo'],
        'nombre' => $moneda['nombre'],
        'simbolo' => $moneda['simbolo'],
        'items' => $localConnection->goQuery($sql, array_merge($paramsFecha, $paramsUser, [$moneda['_id']])),
      ];
    }

    /** DIGITAL: una entrada por cada método no-efectivo activo real (puede
     * cubrir varias monedas a la vez, ej. "Transferencia" existe tanto para
     * COP como para VES -- se agrupa por nombre, no por id de catálogo). */
    $metodosDigitales = $localConnection->goQuery(
      'SELECT DISTINCT nombre FROM catalogo_metodos_pago WHERE es_efectivo = 0 AND eliminado = 0 ORDER BY nombre'
    );

    $object['data']['digital'] = [];
    foreach ($metodosDigitales as $metodo) {
      $sql = "SELECT
                SUM(a.monto) monto,
                a.tasa,
                SUM(ROUND(a.monto / a.tasa, 2)) AS monto_base,
                a.moneda
              FROM metodos_de_pago AS a
              LEFT JOIN ordenes AS o ON a.id_orden = o._id
              JOIN catalogo_metodos_pago AS cmp ON cmp._id = a.id_metodo_pago
              WHERE cmp.nombre = ? AND cmp.es_efectivo = 0 AND a.$whereBase $filterUserOrdenes
              GROUP BY a.tasa, a.moneda";
      $object['data']['digital'][] = [
        'nombre' => $metodo['nombre'],
        'items' => $localConnection->goQuery($sql, array_merge([$metodo['nombre']], $paramsFecha, $paramsUser)),
      ];
    }

    /** RETIROS (ya era dinámico -- no filtra por moneda/método hardcodeado) */
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT
                SUM(a.monto) monto,
                a.moneda,
                a.tasa,
                SUM(ROUND(a.monto / a.tasa, 2)) AS monto_base,
                'Retiros' metodo_pago
              FROM retiros AS a
              WHERE a.moment::date BETWEEN ? AND ? $filterUserRetiros
              GROUP BY a.tasa, a.moneda";
    } else {
      $sql = "SELECT
                SUM(a.monto) monto,
                a.moneda,
                a.tasa,
                SUM(ROUND(a.monto / a.tasa, 2)) AS monto_base,
                'Retiros' metodo_pago
              FROM retiros AS a
              WHERE DATE(a.moment) BETWEEN ? AND ? $filterUserRetiros
              GROUP BY a.tasa, a.moneda";
    }

    $object['data']['retiros'] = $localConnection->goQuery($sql, array_merge([$inicio, $fin], $paramsUser));

    // Moneda base real de la empresa, para que el frontend etiquete "Total
    // {codigo}"/el símbolo de los totales generales sin asumir "$"/USD.
    $monedaBaseRow = $localConnection->goQuery(
      'SELECT codigo, nombre, simbolo FROM catalogo_monedas WHERE es_base = 1 AND eliminado = 0 LIMIT 1'
    );
    $object['monedaBase'] = !empty($monedaBaseRow) ? $monedaBaseRow[0] : null;

    // Obtener lista de vendedores para el select del frontend -- mismo criterio ya
    // corregido arriba (JOIN contra empresas_usuarios_empresas, no a.id_empresa directo).
    $sqlv = "SELECT DISTINCT
                a.id_usuario _id,
                a.nombre
            FROM
                api_empresas.empresas_usuarios a
            JOIN api_empresas.empresas_usuarios_empresas eue ON eue.id_usuario = a.id_usuario AND eue.id_empresa = " . ID_EMPRESA . " AND eue.activo = 1
            JOIN api_empresas.empresas_usuarios_departamentos b ON a.id_usuario = b.id_empleado AND b.id_empresa = " . ID_EMPRESA . "
            WHERE
                b.id_departamento IN (SELECT _id FROM departamentos WHERE departamento IN ('Comercialización', 'Comecialización', 'Administración'))
            ORDER BY a.nombre ASC";
    $object['vendedores'] = $localConnection->goQuery($sqlv);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Reporte granular de transacciones de caja (movimientos de efectivo uno por uno)
  $app->get('/reporte-de-caja-transacciones/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $id_vendedor = (int)$args['id_vendedor'];
    $inicio = $args['inicio'];
    $fin = $args['fin'];

    $whereFecha = DB_DRIVER === 'pgsql' ? "c.moment::date BETWEEN ? AND ?" : "DATE(c.moment) BETWEEN ? AND ?";
    $params = [$inicio, $fin];

    $filterVendedor = '';
    if ($id_vendedor !== 0) {
      $filterVendedor = ' AND c.id_empleado = ?';
      $params[] = $id_vendedor;
    }

    $sql = "SELECT
              c._id,
              c.moment,
              c.monto,
              c.moneda,
              c.tasa,
              c.tipo,
              c.detalle,
              c.id_empleado,
              emp.nombre AS empleado
            FROM caja c
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = c.id_empleado
            WHERE $whereFecha $filterVendedor
            ORDER BY c.moment DESC";

    $object['data']['transacciones'] = $localConnection->goQuery($sql, $params);

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

      // 1. Obtener saldos actuales para validar, para TODAS las monedas activas
      // de la empresa (no solo las 3 originales) -- necesario para que el
      // camino genérico de abajo pueda validar cualquier moneda futura.
      // (Reutilizamos la lógica del GET /retiros pero simplificada para validación)

      // Fondo: columnas fijas (dolares/pesos/bolivares, puramente históricas
      // desde la corrección del 2026-07-31 -- congeladas en 0 para cualquier
      // fondo nuevo) combinadas con caja_fondos_extra, donde vive el fondo
      // real de TODA moneda desde esa corrección. Mismo patrón ya usado en
      // GET /retiros y GET /cierre-de-caja.
      $sqlFondo = 'SELECT _id, dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
      $fondoRes = $localConnection->goQuery($sqlFondo, [$id_empleado]);
      $fondo = !empty($fondoRes) ? $fondoRes[0] : ['dolares' => 0, 'pesos' => 0, 'bolivares' => 0];
      $extraFondoPorCodigo = ['USD' => (float) $fondo['dolares'], 'COP' => (float) $fondo['pesos'], 'VES' => (float) $fondo['bolivares']];

      if (!empty($fondoRes)) {
        $fondoExtra = $localConnection->goQuery(
          'SELECT cm.codigo, SUM(cfe.monto) AS monto
           FROM caja_fondos_extra cfe
           JOIN catalogo_monedas cm ON cm._id = cfe.id_moneda
           WHERE cfe.id_caja_fondos = ?
           GROUP BY cm.codigo',
          [$fondoRes[0]['_id']]
        );
        foreach ($fondoExtra as $row) {
          $extraFondoPorCodigo[$row['codigo']] = (float) $row['monto'];
        }
      }

      $monedasActivas = $localConnection->goQuery('SELECT codigo, nombre FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0');

      $saldos = [];
      foreach ($monedasActivas as $monedaActiva) {
        $nombreMoneda = $monedaActiva['nombre'];
        $extraFondo = $extraFondoPorCodigo[$monedaActiva['codigo']] ?? 0;

        // Subconsultas independientes (NO un JOIN entre caja y retiros: al no
        // existir relación 1:1 entre ambas tablas, el JOIN generaba un producto
        // cartesiano que multiplicaba ambas sumas -- ej. 175 filas de caja x 2
        // de retiros duplicaban el total varias veces, dando saldos negativos
        // falsos). También se filtra cierre_caja = 0 para no restar retiros que
        // ya quedaron contabilizados en un cierre de caja anterior.
        $sqlSaldo = "SELECT (
                       (SELECT COALESCE(SUM(monto), 0) FROM caja WHERE moneda = ? AND id_caja_cierres IS NULL AND id_empleado = ?)
                       + $extraFondo
                       - (SELECT COALESCE(SUM(monto), 0) FROM retiros WHERE moneda = ? AND cierre_caja = 0 AND id_empleado = ?)
                     ) AS saldo";
        $res = $localConnection->goQuery($sqlSaldo, [$nombreMoneda, $id_empleado, $nombreMoneda, $id_empleado]);
        $saldos[$nombreMoneda] = !empty($res) ? floatval($res[0]['saldo']) : 0;
      }

      $pagosGenericos = decodificarPagosGenericos($arr);

      if (!empty($pagosGenericos)) {
        // CAMINO NUEVO (Fase 7): valida saldo solo para métodos es_efectivo=1
        // (mismo criterio que el camino legado, generalizado por catálogo).
        foreach ($pagosGenericos as $pago) {
          $monto = floatval($pago['monto'] ?? 0);
          if ($monto <= 0) {
            continue;
          }
          $metodo = resolverMetodoPagoPorId($localConnection, $pago['id_metodo_pago'] ?? 0);
          if (!$metodo) {
            $localConnection->disconnect();
            $object['statusCode'] = 400;
            $object['status'] = 'error';
            $object['message'] = 'Método de pago inválido o eliminado (id_metodo_pago=' . ($pago['id_metodo_pago'] ?? '?') . ')';
            $response->getBody()->write(json_encode($object));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
          }
          if ((int) $metodo['es_efectivo'] === 1) {
            $saldoDisponible = $saldos[$metodo['moneda_nombre']] ?? 0;
            if ($monto > $saldoDisponible) {
              $localConnection->disconnect();
              $object['statusCode'] = 400;
              $object['status'] = 'error';
              $object['message'] = "Saldo insuficiente en {$metodo['moneda_nombre']}. Disponible: " . number_format($saldoDisponible, 2);
              $response->getBody()->write(json_encode($object));
              return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
          }
        }
      } else {
        // CAMINO LEGADO: solo valida las 3 monedas/métodos originales (todos
        // "Efectivo", que es lo único que este endpoint permitía retirar) --
        // sin cambios de comportamiento.
        $solicitado = [
          'Dólares' => floatval($arr['montoDolaresEfectivo'] ?? 0),
          'Pesos' => floatval($arr['montoPesosEfectivo'] ?? 0),
          'Bolívares' => floatval($arr['montoBolivaresEfectivo'] ?? 0)
        ];

        foreach ($solicitado as $moneda => $monto) {
          if ($monto > 0 && $monto > ($saldos[$moneda] ?? 0)) {
             $localConnection->disconnect();
             $object['statusCode'] = 400;
             $object['status'] = 'error';
             $object['message'] = "Saldo insuficiente en $moneda. Disponible: " . number_format($saldos[$moneda] ?? 0, 2);
             $response->getBody()->write(json_encode($object));
             return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
          }
        }
      }

      // 3. Procesar inserciones (Sentencias Preparadas)
      $localConnection->beginTransaction();

      if (!empty($pagosGenericos)) {
        // CAMINO NUEVO (Fase 7): dirigido por catalogo_metodos_pago real.
        foreach ($pagosGenericos as $pago) {
          $monto = floatval($pago['monto'] ?? 0);
          if ($monto <= 0) {
            continue;
          }
          $metodo = resolverMetodoPagoPorId($localConnection, $pago['id_metodo_pago'] ?? 0);
          if (!$metodo) {
            throw new Exception('Método de pago inválido o eliminado (id_metodo_pago=' . ($pago['id_metodo_pago'] ?? '?') . ')');
          }
          $tasa = floatval($pago['tasa'] ?? 1);
          // '??' no cae al detalle general cuando el componente dinámico envía
          // detalle: "" (string vacío, no null/ausente) -- caso real de
          // retiros/index.vue, donde el "Detalle del retiro" es un campo
          // general obligatorio, sin equivalente por método de pago.
          $detallePago = !empty($pago['detalle']) ? $pago['detalle'] : $detalle;
          insertarRetiroGenerico($localConnection, $id_empleado, $metodo, $monto, $detallePago, $tasa);
        }
      } else {
        // CAMINO LEGADO: formularios aún no migrados al componente dinámico
        // (Fase 7, en curso) -- sin cambios de comportamiento.
        $insertSql = "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

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
            list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, $op[1], $op[2]);
            $localConnection->goQuery($insertSql, [$id_empleado, $op[1], $op[2], $monto, $detalle, $op[3], $idMoneda, $idMetodo]);
          }
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
      $pagosGenericos = decodificarPagosGenericos($arr);

      if (!empty($pagosGenericos)) {
        // CAMINO NUEVO (Fase 7): dirigido por catalogo_metodos_pago real.
        foreach ($pagosGenericos as $pago) {
          $monto = floatval($pago['monto'] ?? 0);
          if ($monto <= 0) {
            continue;
          }

          $metodo = resolverMetodoPagoPorId($localConnection, $pago['id_metodo_pago'] ?? 0);
          if (!$metodo) {
            throw new Exception('Método de pago inválido o eliminado (id_metodo_pago=' . ($pago['id_metodo_pago'] ?? '?') . ')');
          }

          // '??' no cae al detalle general cuando el componente dinámico envía
          // detalle: "" (string vacío, no null/ausente) -- mismo hallazgo que en /retiro.
          $detalle = !empty($pago['detalle']) ? $pago['detalle'] : ($arr['detalle'] ?? '');
          $tasa = floatval($pago['tasa'] ?? 1);
          $tipoAbono = $arr['tipoAbono'] ?? '';

          insertarMetodoPagoGenerico($localConnection, $id_orden_abono, $tipoAbono, $metodo, $monto, $detalle, $tasa);

          if ((int) $metodo['es_efectivo'] === 1) {
            insertarCajaGenerico($localConnection, $monto, $metodo, $tasa, 'Otro Abono', $arr['id_empleado'] ?? 0, $detalle);
          }
          $metodosRegistrados++;
        }
      } else {
        // CAMINO LEGADO: formularios aún no migrados al componente dinámico
        // (Fase 7, en curso) -- sin cambios de comportamiento.
        if (floatval($arr['montoDolaresEfectivo'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Dólares', 'Efectivo');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Dólares', 'Efectivo', ?, ?, '1', ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresEfectivo'], $arr['detalle'] ?? '', $idMoneda, $idMetodo]);

          $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle, id_moneda) VALUES (?, 'Dólares', 1, 'Otro Abono', ?, ?, ?)";
          $localConnection->goQuery($sql, [$arr['montoDolaresEfectivo'], $arr['id_empleado'] ?? 0, $arr['detalle'] ?? '', $idMoneda]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoDolaresZelle'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Dólares', 'Zelle');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Dólares', 'Zelle', ?, ?, '1', ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresZelle'], $arr['detalle'] ?? '', $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoDolaresPanama'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Dólares', 'Panamá');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Dólares', 'Panamá', ?, ?, '1', ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoDolaresPanama'], $arr['detalle'] ?? '', $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoPesosEfectivo'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Pesos', 'Efectivo');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Pesos', 'Efectivo', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoPesosEfectivo'], $arr['detalle'] ?? '', $arr['tasa_peso'] ?? 1, $idMoneda, $idMetodo]);

          $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle, id_moneda) VALUES (?, 'Pesos', ?, 'Otro Abono', ?, ?, ?)";
          $localConnection->goQuery($sql, [$arr['montoPesosEfectivo'], $arr['tasa_peso'] ?? 1, $arr['id_empleado'] ?? 0, $arr['detalle'] ?? '', $idMoneda]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoPesosTransferencia'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Pesos', 'Transferencia');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Pesos', 'Transferencia', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoPesosTransferencia'], $arr['detalle'] ?? '', $arr['tasa_peso'] ?? 1, $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoBolivaresEfectivo'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Bolívares', 'Efectivo');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Bolívares', 'Efectivo', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresEfectivo'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1, $idMoneda, $idMetodo]);

          $sql = "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle, id_moneda) VALUES (?, 'Bolívares', ?, 'Otro Abono', ?, ?, ?)";
          $localConnection->goQuery($sql, [$arr['montoBolivaresEfectivo'], $arr['tasa_dolar'] ?? 1, $arr['id_empleado'] ?? 0, $arr['detalle'] ?? '', $idMoneda]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoBolivaresPunto'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Bolívares', 'Punto');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Bolívares', 'Punto', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresPunto'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1, $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoBolivaresPagomovil'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Bolívares', 'Pagomovil');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Bolívares', 'Pagomovil', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresPagomovil'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1, $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }

        if (floatval($arr['montoBolivaresTransferencia'] ?? 0) > 0) {
          list($idMoneda, $idMetodo) = resolverIdsMonedaMetodo($localConnection, 'Bolívares', 'Transferencia');
          $sql = "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa, id_moneda, id_metodo_pago) VALUES (?, ?, 'Bolívares', 'Transferencia', ?, ?, ?, ?, ?)";
          $localConnection->goQuery($sql, [$id_orden_abono, $arr['tipoAbono'] ?? '', $arr['montoBolivaresTransferencia'], $arr['detalle'] ?? '', $arr['tasa_dolar'] ?? 1, $idMoneda, $idMetodo]);
          $metodosRegistrados++;
        }
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
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario WHERE a.moment::date BETWEEN ? AND ? ORDER BY a.moment DESC";
    } else {
      $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario WHERE DATE(a.moment) BETWEEN ? AND ? ORDER BY a.moment DESC";
    }

    $object['data']['retiros'] = $localConnection->goQuery($sql, [$args['inicio'], $args['fin']]);

    $id_emp = (int) $args['id_empleado'];

    // FONDO: columnas fijas + tabla hija (Fase 8, enfoque híbrido, mismo
    // mapeo por código ISO ya usado en /retiro y /cierre-de-caja).
    $columnaPorCodigo = ['USD' => 'dolares', 'COP' => 'pesos', 'VES' => 'bolivares'];
    $sql = 'SELECT _id, dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
    $fondoRow = $localConnection->goQuery($sql, [$id_emp]);
    $object['data']['fondo'] = $fondoRow;

    $fondoPorCodigo = ['USD' => 0, 'COP' => 0, 'VES' => 0];
    if (!empty($fondoRow)) {
      $fondoPorCodigo['USD'] = floatval($fondoRow[0]['dolares']);
      $fondoPorCodigo['COP'] = floatval($fondoRow[0]['pesos']);
      $fondoPorCodigo['VES'] = floatval($fondoRow[0]['bolivares']);

      $fondoExtra = $localConnection->goQuery(
        'SELECT cm.codigo, SUM(cfe.monto) AS monto
         FROM caja_fondos_extra cfe
         JOIN catalogo_monedas cm ON cm._id = cfe.id_moneda
         WHERE cfe.id_caja_fondos = ?
         GROUP BY cm.codigo',
        [$fondoRow[0]['_id']]
      );
      foreach ($fondoExtra as $row) {
        $fondoPorCodigo[$row['codigo']] = floatval($row['monto']);
      }
    }

    // SALDO EN CAJA para CADA moneda activa real -- ya no 3 bloques
    // hardcodeados a Dólares/Pesos/Bolívares.
    $monedasActivas = $localConnection->goQuery('SELECT codigo, nombre FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC, nombre');
    $object['data']['caja'] = [];
    foreach ($monedasActivas as $moneda) {
      $montoCaja = floatval($localConnection->goQuery("SELECT COALESCE(SUM(monto), 0) as total FROM caja WHERE moneda = ? AND id_caja_cierres IS NULL AND id_empleado = ?", [$moneda['nombre'], $id_emp])[0]['total']);
      $montoRetiros = floatval($localConnection->goQuery("SELECT COALESCE(SUM(monto), 0) as total FROM retiros WHERE moneda = ? AND cierre_caja = 0 AND id_empleado = ?", [$moneda['nombre'], $id_emp])[0]['total']);
      $extraFondo = $fondoPorCodigo[$moneda['codigo']] ?? 0;
      $saldo = floatval($montoCaja + $extraFondo - $montoRetiros);

      $resTasa = $localConnection->goQuery("SELECT tasa FROM caja WHERE moneda = ? AND id_empleado = ? ORDER BY _id DESC LIMIT 1", [$moneda['nombre'], $id_emp]);
      $tasa = floatval(!empty($resTasa) ? $resTasa[0]['tasa'] : 1);
      if ($tasa <= 0) {
        $tasa = 1;
      }

      $object['data']['caja'][] = [
        'monto' => $saldo,
        'moneda' => $moneda['nombre'],
        'tasa' => $tasa,
        'dolares' => '$' . number_format($saldo / $tasa, 2),
      ];
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Pagos Ordenes
  $app->get('/pagos-ordenes/{fecha}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $momentLikeExpr = DB_DRIVER === 'pgsql' ? 'moment::text' : 'moment';
    $sql = "SELECT _id, moment, monto, moneda, metodo_pago, id_orden, tasa FROM metodos_de_pago WHERE $momentLikeExpr LIKE ?";
    $object['data'] = $localConnection->goQuery($sql, [$args['fecha'] . '%']);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Balance de Cierres de Caja
  $app->get('/balance-de-cierres/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $inicio = $args['inicio'];
    $fin = $args['fin'];
    $id_vendedor = (int) $args['id_vendedor'];

    $filterVendedor = '';
    $paramsVendedor = [];
    if ($id_vendedor !== 0) {
      $filterVendedor = ' AND c.id_empleado = ?';
      $paramsVendedor[] = $id_vendedor;
    }

    // Fase 8 (rediseño 100% dinámico): dolares/pesos/bolivares son puramente
    // históricas -- solo cierres de antes de esta migración las usan. Se
    // interpretan por código ISO real únicamente para decodificar ese
    // historial (nunca para decidir dónde escribir). Toda moneda, legada o
    // nueva, se combina con caja_cierres_extra/caja_fondos_extra por
    // id_moneda real, y el "recaudado" se agrupa por caja.id_moneda en vez
    // de comparar contra el texto de caja.moneda.
    $columnaPorCodigo = ['USD' => 'dolares', 'COP' => 'pesos', 'VES' => 'bolivares'];

    $monedasActivas = $localConnection->goQuery(
      'SELECT _id, codigo, nombre, simbolo, es_base FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC, nombre'
    );

    $monedaBase = null;
    foreach ($monedasActivas as $moneda) {
      if ((int) $moneda['es_base'] === 1) {
        $monedaBase = $moneda;
        break;
      }
    }

    $dateExpr = DB_DRIVER === 'pgsql' ? 'c.moment::date' : 'DATE(c.moment)';

    $sql = "SELECT
              c._id,
              c.moment AS fecha_cierre,
              u.nombre AS vendedor,
              c.dolares, c.pesos, c.bolivares,
              f._id AS id_caja_fondos,
              f.dolares AS f_dolares, f.pesos AS f_pesos, f.bolivares AS f_bolivares,
              (SELECT f_ant._id FROM caja_fondos f_ant WHERE f_ant.id_empleado = c.id_empleado AND f_ant.id_caja_cierres < c._id ORDER BY f_ant._id DESC LIMIT 1) AS id_fondo_anterior,
              (SELECT f_ant.dolares FROM caja_fondos f_ant WHERE f_ant.id_empleado = c.id_empleado AND f_ant.id_caja_cierres < c._id ORDER BY f_ant._id DESC LIMIT 1) AS fa_dolares,
              (SELECT f_ant.pesos FROM caja_fondos f_ant WHERE f_ant.id_empleado = c.id_empleado AND f_ant.id_caja_cierres < c._id ORDER BY f_ant._id DESC LIMIT 1) AS fa_pesos,
              (SELECT f_ant.bolivares FROM caja_fondos f_ant WHERE f_ant.id_empleado = c.id_empleado AND f_ant.id_caja_cierres < c._id ORDER BY f_ant._id DESC LIMIT 1) AS fa_bolivares
            FROM caja_cierres c
            JOIN caja_fondos f ON c._id = f.id_caja_cierres
            JOIN api_empresas.empresas_usuarios u ON c.id_empleado = u.id_usuario
            WHERE $dateExpr BETWEEN ? AND ? $filterVendedor
            ORDER BY c.moment DESC";

    $cierres = $localConnection->goQuery($sql, array_merge([$inicio, $fin], $paramsVendedor));

    $data = [];

    if (!empty($cierres)) {
      $cierreIds = array_map('intval', array_column($cierres, '_id'));
      $fondoIds = array_map('intval', array_column($cierres, 'id_caja_fondos'));
      $fondoAnteriorIds = array_map('intval', array_filter(array_column($cierres, 'id_fondo_anterior')));
      $todosFondoIds = array_values(array_unique(array_merge($fondoIds, $fondoAnteriorIds)));

      $cierresExtraMap = mapaSumaPorClaveYMoneda(
        $localConnection,
        'SELECT id_caja_cierres AS clave, id_moneda, SUM(monto) AS monto FROM caja_cierres_extra WHERE id_caja_cierres IN (' . marcadoresPosicionales(count($cierreIds)) . ') GROUP BY id_caja_cierres, id_moneda',
        $cierreIds
      );

      $fondosExtraMap = mapaSumaPorClaveYMoneda(
        $localConnection,
        'SELECT id_caja_fondos AS clave, id_moneda, SUM(monto) AS monto FROM caja_fondos_extra WHERE id_caja_fondos IN (' . marcadoresPosicionales(count($todosFondoIds)) . ') GROUP BY id_caja_fondos, id_moneda',
        $todosFondoIds
      );

      $recaudadoMap = mapaSumaPorClaveYMoneda(
        $localConnection,
        'SELECT id_caja_cierres AS clave, id_moneda, SUM(monto) AS monto FROM caja WHERE id_caja_cierres IN (' . marcadoresPosicionales(count($cierreIds)) . ') AND id_moneda IS NOT NULL GROUP BY id_caja_cierres, id_moneda',
        $cierreIds
      );

      // Retiros atribuidos a este cierre -- sin esto, "teórico" no reflejaba
      // el dinero que salió de caja antes de cerrar, y el Diff siempre daba
      // un falso descuadre del tamaño exacto de lo retirado (hallazgo real,
      // 2026-08-03). Cierres anteriores a este fix no tendrán retiros
      // atribuidos (id_caja_cierres quedó NULL en su momento) -- fuera de
      // alcance, dato histórico irreversible.
      $retirosMap = mapaSumaPorClaveYMoneda(
        $localConnection,
        'SELECT id_caja_cierres AS clave, id_moneda, SUM(monto) AS monto FROM retiros WHERE id_caja_cierres IN (' . marcadoresPosicionales(count($cierreIds)) . ') AND id_moneda IS NOT NULL GROUP BY id_caja_cierres, id_moneda',
        $cierreIds
      );

      $tasaCierreMap = mapaUltimaTasaPorCierre($localConnection, $cierreIds);

      // Historial de tasas por moneda no-base, para cuando un cierre no tuvo
      // ningún movimiento de caja en esa moneda durante el turno.
      $historialTasas = [];
      foreach ($monedasActivas as $moneda) {
        if ((int) $moneda['es_base'] === 1) {
          continue;
        }
        $historialTasas[(int) $moneda['_id']] = $localConnection->goQuery(
          'SELECT moment, tasa FROM caja WHERE id_moneda = ? ORDER BY moment ASC',
          [$moneda['_id']]
        );
      }

      foreach ($cierres as $c) {
        $cierreId = (int) $c['_id'];
        $fondoId = (int) $c['id_caja_fondos'];
        $fondoAnteriorId = $c['id_fondo_anterior'] !== null ? (int) $c['id_fondo_anterior'] : null;

        $porMoneda = [];
        $totalTeoricoBase = 0.0;
        $totalRealBase = 0.0;

        foreach ($monedasActivas as $moneda) {
          $idMoneda = (int) $moneda['_id'];
          $esBase = (int) $moneda['es_base'] === 1;
          $columna = $columnaPorCodigo[$moneda['codigo']] ?? null;

          $cierreValor = ($columna ? (float) $c[$columna] : 0) + ($cierresExtraMap[$cierreId][$idMoneda] ?? 0);
          $fondoNuevoValor = ($columna ? (float) $c['f_' . $columna] : 0) + ($fondosExtraMap[$fondoId][$idMoneda] ?? 0);
          $fondoAnteriorValor = 0.0;
          if ($fondoAnteriorId !== null) {
            $fondoAnteriorValor = ($columna ? (float) $c['fa_' . $columna] : 0) + ($fondosExtraMap[$fondoAnteriorId][$idMoneda] ?? 0);
          }
          $recaudadoValor = $recaudadoMap[$cierreId][$idMoneda] ?? 0;
          $retiradoValor = $retirosMap[$cierreId][$idMoneda] ?? 0;

          if ($esBase) {
            $tasa = 1.0;
          } else {
            $tasa = $tasaCierreMap[$cierreId][$idMoneda] ?? tasaHistoricaEnMomento($historialTasas[$idMoneda] ?? [], $c['fecha_cierre']);
            if ($tasa <= 0) {
              $tasa = 1.0;
            }
          }

          $teorico = $fondoAnteriorValor + $recaudadoValor - $retiradoValor;
          $real = $cierreValor + $fondoNuevoValor;

          $totalTeoricoBase += $esBase ? $teorico : $teorico / $tasa;
          $totalRealBase += $esBase ? $real : $real / $tasa;

          $porMoneda[] = [
            'id_moneda' => $idMoneda,
            'codigo' => $moneda['codigo'],
            'nombre' => $moneda['nombre'],
            'simbolo' => $moneda['simbolo'],
            'cierre' => $cierreValor,
            'fondo_nuevo' => $fondoNuevoValor,
            'fondo_anterior' => $fondoAnteriorValor,
            'recaudado' => $recaudadoValor,
            'retirado' => $retiradoValor,
            'tasa' => $tasa,
            'diferencia' => $real - $teorico,
          ];
        }

        $data[] = [
          '_id' => $cierreId,
          'fecha_cierre' => $c['fecha_cierre'],
          'vendedor' => $c['vendedor'],
          'total_teorico_base' => $totalTeoricoBase,
          'total_real_base' => $totalRealBase,
          'diferencia_base' => $totalRealBase - $totalTeoricoBase,
          'porMoneda' => $porMoneda,
        ];
      }
    }

    $localConnection->disconnect();

    $object = [
      'monedaBase' => $monedaBase,
      'monedas' => $monedasActivas,
      'data' => $data,
    ];
    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN RETIROS */

}; // Fin de la función que envuelve las rutas
