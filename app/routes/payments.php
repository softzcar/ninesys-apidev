<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** PAGOS */
  // Terminar planilla de pago
  $app->post('/pagos/terminar-planilla', function (Request $request, Response $response, $args) {
    $localConnection = new LocalDB();
    $myDate = new CustomTime();
    $now = $myDate->today();

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      $sql = "UPDATE pagos SET fecha_pago = ? WHERE fecha_pago IS NULL";
      $result = $localConnection->goQuery($sql, [$now]);

      // Verificar cuántos registros fueron actualizados
      $sqlCount = "SELECT COUNT(*) as total FROM pagos WHERE fecha_pago = ?";
      $countResult = $localConnection->goQuery($sqlCount, [$now]);
      $totalActualizados = isset($countResult[0]['total']) ? intval($countResult[0]['total']) : 0;

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Planilla terminada correctamente', [
        'fecha_pago' => $now,
        'registros_actualizados' => $totalActualizados
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /pagos/terminar-planilla: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al terminar la planilla: ' . $e->getMessage(), $e);
    }
  });

  // REALIZAR PAGO A EMPLEADOS
  $app->post('/pagos/pagar-a-empleados', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $myDate = new CustomTime();
    $now = $myDate->today();

    // Sanitizar y convertir los IDs de pago a un array de enteros.
    $listaDeIdPagos = array_map('intval', explode(',', $data['id_pagos'] ?? ''));
    // Filtrar valores vacíos o cero
    $listaDeIdPagos = array_filter($listaDeIdPagos, function ($id) {
      return $id > 0;
    });
    $cantidadDePagos = count($listaDeIdPagos);

    // ========== VALIDACIONES ==========
    if ($cantidadDePagos === 0) {
      return ApiResponse::validationError($response, 'No se proporcionaron IDs de pago válidos');
    }

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      // Procesar bonos, descuentos, salario y comisión
      $totalBonos = 0;
      $totalDescuentos = 0;
      $salario = floatval($data['salario'] ?? 0);
      $comision = floatval($data['comision'] ?? 0);

      // Procesar bonos
      if (isset($data['bonos']) && $data['bonos'] !== '0') {
        $bonosArray = json_decode($data['bonos'], true);
        if (is_array($bonosArray)) {
          foreach ($bonosArray as $bono) {
            $monto = floatval($bono['monto'] ?? 0);
            $descripcion = $bono['descripcion'] ?? '';
            $totalBonos += $monto;

            // Dividir el monto del bono entre la cantidad de pagos
            $montoPorPago = $monto / $cantidadDePagos;

            foreach ($listaDeIdPagos as $idPago) {
              $sql = "INSERT INTO pagos_abonos (id_pago, monto, descripcion) VALUES (?, ?, ?)";
              $localConnection->goQuery($sql, [$idPago, $montoPorPago, $descripcion]);
            }
          }
        }
      }

      // Procesar descuentos
      if (isset($data['descuentos']) && $data['descuentos'] !== '0') {
        $descuentosArray = json_decode($data['descuentos'], true);
        if (is_array($descuentosArray)) {
          foreach ($descuentosArray as $descuento) {
            $monto = floatval($descuento['monto'] ?? 0);
            $descripcion = $descuento['descripcion'] ?? '';
            $totalDescuentos += $monto;

            // Dividir el monto del descuento entre la cantidad de pagos
            $montoPorPago = $monto / $cantidadDePagos;

            foreach ($listaDeIdPagos as $idPago) {
              $sql = "INSERT INTO pagos_descuentos (id_pago, monto, descripcion) VALUES (?, ?, ?)";
              $localConnection->goQuery($sql, [$idPago, $montoPorPago, $descripcion]);
            }
          }
        }
      }

      // Procesar salario si existe
      if ($salario > 0) {
        $idEmpleado = $data['id_empleado'] ?? null;

        // Si no viene el id_empleado, lo buscamos en el primer pago de la lista
        if (!$idEmpleado && count($listaDeIdPagos) > 0) {
          $sqlEmp = "SELECT id_empleado FROM pagos WHERE _id = ?";
          $resEmp = $localConnection->goQuery($sqlEmp, [$listaDeIdPagos[0]]);
          if (isset($resEmp[0]['id_empleado'])) {
            $idEmpleado = $resEmp[0]['id_empleado'];
          }
        }

        if ($idEmpleado) {
          // Buscar frecuencia de salario
          $sql = "SELECT salario_periodo FROM api_empresas.empresas_usuarios WHERE id_usuario = ?";
          $resultUsuario = $localConnection->goQuery($sql, [$idEmpleado]);
          $periodo = isset($resultUsuario[0]['salario_periodo']) ? $resultUsuario[0]['salario_periodo'] : 'semanal';

          // Calcular el índice del periodo según la frecuencia para el histórico
          $numeroPeriodo = intval(date('W')); // Default: semana
          if ($periodo === 'quincenal') {
            $dia = intval(date('d'));
            $mes = intval(date('n')) - 1; // 0-11
            $anio = intval(date('Y'));
            $periodoMes = $dia <= 15 ? 1 : 2;
            $numeroPeriodo = $anio * 24 + $mes * 2 + $periodoMes;
          } elseif ($periodo === 'mensual') {
            $mes = intval(date('n')) - 1; // 0-11
            $anio = intval(date('Y'));
            $numeroPeriodo = $anio * 12 + $mes;
          }

          // Dividir el salario entre la cantidad de pagos
          $salarioPorPago = $salario / $cantidadDePagos;
          foreach ($listaDeIdPagos as $idPago) {
            $sql = "INSERT INTO pagos_salarios (id_pago, tipo_salario, numero_semana, monto) VALUES (?, ?, ?, ?)";
            $localConnection->goQuery($sql, [$idPago, $periodo, $numeroPeriodo, $salarioPorPago]);
          }
        }
      }

      // Calcular el monto total del pago y el monto por cada registro de pago
      $montoTotalPago = ($salario + $comision + $totalBonos) - $totalDescuentos;
      $montoPorRegistroDePago = $montoTotalPago / $cantidadDePagos;

      // Crear placeholders (?) para la cláusula IN
      $placeholders = implode(',', array_fill(0, count($listaDeIdPagos), '?'));

      // Actualizar pagos con fecha_pago y el monto_pago DIVIDIDO
      $sql = "UPDATE pagos SET fecha_pago = ?, monto_pago = ? WHERE _id IN ({$placeholders})";
      $params = array_merge([$now, $montoPorRegistroDePago], $listaDeIdPagos);
      $localConnection->goQuery($sql, $params);

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Pago procesado correctamente', [
        'cantidad_pagos' => $cantidadDePagos,
        'monto_total_pagado' => $montoTotalPago,
        'monto_por_registro' => $montoPorRegistroDePago
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /pagos/pagar-a-empleados: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al procesar el pago: ' . $e->getMessage(), $e);
    }
  });

  // Lista de pagos semanales
  $app->get('/pagos/semana/disenadores', function (Request $request, Response $response, array $args) {
    // OBTENER PAGOS DE DISEÑADORES
    $localConnection = new LocalDB();

    // DISEÑADORES
    $sql = 'SELECT
                p._id id_pago,
                p.id_orden,
                r._id id_revision,
                p.monto_pago pago,
                p.comision,
                p.comision_tipo,
                r.url_image,
                p.id_empleado,
                p.fecha_pago,
                (SELECT numero_semana FROM ' . LOCAL_DB . '.pagos_salarios ps JOIN ' . LOCAL_DB . '.pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = p.id_empleado ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                (SELECT YEAR(pa.moment) FROM ' . LOCAL_DB . '.pagos_salarios ps JOIN ' . LOCAL_DB . '.pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = p.id_empleado ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                (SELECT IF(p.id_reposicion > 0, (SELECT unidades FROM reposiciones WHERE _id = p.id_reposicion), (SELECT IFNULL(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = p.id_orden))) as cantidad_productos,
                (
                SELECT
                    departamento
                FROM
                    api_empresas.empresas_usuarios
                WHERE
                    id_usuario = r.id_empleado
            ) departamento,
                (
                SELECT
                    salario_tipo
                FROM
                    api_empresas.empresas_usuarios
                WHERE
                    id_usuario = r.id_empleado
            ) salario_tipo,
            (
                SELECT
                    nombre
                FROM
                    api_empresas.empresas_usuarios
                WHERE
                    id_usuario = r.id_empleado
            ) nombre,
            (
                SELECT
                    product producto
                FROM
                    products
                WHERE
                    _id = r.id_product
            ) producto
            FROM
                pagos p
            JOIN revisiones r ON
                p.id_orden = r.id_orden AND p.id_empleado = r.id_empleado AND r.estatus = "Aprobado"
            WHERE p.fecha_pago IS NULL AND p.detalle IN ("Diseño", "ajuste", "personalización")
            GROUP BY p._id
        ';
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);

      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }
    /* $app->get('/pagos/semana/disenadores', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        $localConnection = new LocalDB();

        // DISEÑADORES
        $sql = "SELECT
            ord._id id_orden,
            dis._id id_diseno,
            dis.id_product,
            rev._id id_revision,
            dis.id_empleado id_disenador,
            (
            SELECT
                nombre
            FROM
                api_empresas.empresas_usuarios
            WHERE
                id_usuario = dis.id_empleado
        ) disenador,
        ord.id_wp id_cliente,
        ord.cliente_nombre,
        dis.tipo tipo_diseno,
        rev.detalles,
        rev.estatus,
        rev.revision
        FROM
            ordenes ord
        RIGHT JOIN disenos dis ON
            dis.id_orden = ord._id
        LEFT JOIN revisiones rev ON
            rev.id_orden = ord._id AND rev.id_empleado = dis.id_empleado
        WHERE
            dis.id_product IS NOT NULL AND rev.estatus = 'Esperando Respuesta'
        ORDER BY ord._id ASC, ord.cliente_nombre ASC
        ";
        $object['data']['diseno'] = $localConnection->goQuery($sql);

        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);

            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        } */

    // TODO REPROGRAMAR PAGOS POR TAREASADICIONALES, PRIMERO DEBEN SER APROBADAS PARA SU PAGO
    /* $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
        foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
            $existe = false;
            foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $trabajos_adicionales_nuevos[] = $trabajo_adicional;
            }
        }
        $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
        $object['data']['trabajos_adicionales'] = [];
    } */

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/semana/empleados', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // --- 1. Consulta para empleados con COMISIÓN FIJA ---
    $sql_fija = "SELECT DISTINCT
                    a._id AS id_pago,
                    a.id_reposicion,
                    'N/A' as cod,
                    b._id AS id_lotes_detalles,
                    b.id_orden AS orden,
                    b.id_orden AS id_orden, 
                    'Pago Fijo Global' AS producto,
                    a.cantidad,
                    'N/A' as talla,
                    c.id_usuario AS id_empleado,
                    c.nombre,
                    a.monto_pago AS pago,
                    c.comision,
                    a.comision AS comision,
                    a.comision_tipo,
                    c.salario_tipo,
                    a.detalle AS departamento,
                    o.pago_total AS monto_orden,
                    DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                    DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                    DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                    a.fecha_pago,
                    TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                    (SELECT IF(a.id_reposicion > 0,
                        (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion),
                        IF(a.id_departamento = 3,
                            IFNULL((SELECT SUM(ic.cantidad) FROM inventario_corte ic JOIN ordenes_productos op ON op._id = ic.id_ordenes_productos WHERE ic.id_orden = b.id_orden), 0),
                            (SELECT IFNULL(SUM(op.cantidad), 0) FROM ordenes_productos op JOIN products pp ON pp._id = op.id_woo WHERE op.id_orden = b.id_orden AND (pp.fisico = 1 OR pp.fisico IS NULL) AND (pp.es_diseno = 0 OR pp.es_diseno IS NULL))
                        )
                    ) * (IFNULL(b.procentaje_comision, 100) / 100)) as cantidad_productos
                FROM
                    pagos a
                JOIN
                    lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                LEFT JOIN
                    ordenes o ON b.id_orden = o._id
                JOIN
                    api_empresas.empresas_usuarios c ON b.id_empleado = c.id_usuario
                LEFT JOIN 
                    lotes_detalles ld ON b.id_lotes_detalles = ld._id
                LEFT JOIN 
                    products p ON ld.id_woo = p._id
                WHERE
                    a.fecha_pago IS NULL
                    AND c.comision_tipo = 'fija'
                    AND (p.fisico = 1 OR p.fisico IS NULL)
                    AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";

    $pagos_fijos = $localConnection->goQuery($sql_fija);
    if (!is_array($pagos_fijos) || (isset($pagos_fijos['status']) && $pagos_fijos['status'] === 'error')) {
      $pagos_fijos = [];
    }

    // --- 2. Consulta para empleados con COMISIÓN VARIABLE (desglosada por producto) ---
    $sql_variable = "SELECT DISTINCT
                        a._id AS id_pago,
                        a.id_reposicion,
                        d.id_woo AS cod,
                        b._id AS id_lotes_detalles,
                        b.id_orden AS orden,
                        d.name AS producto,
                        d.cantidad,
                        d.talla,
                        c.id_usuario AS id_empleado,
                        c.nombre,
                        a.monto_pago AS pago,
                        a.comision AS comision,
                        a.comision_tipo,
                        c.salario_tipo,
                        a.detalle AS departamento,
                        o.pago_total AS monto_orden,
                        DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                        DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                        DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                        a.fecha_pago,
                        TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                        (SELECT IF(a.id_reposicion > 0,
                            (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion),
                            IF(a.id_departamento = 3,
                                IFNULL((SELECT SUM(ic.cantidad) FROM inventario_corte ic JOIN ordenes_productos op ON op._id = ic.id_ordenes_productos WHERE ic.id_orden = b.id_orden), 0),
                                (SELECT IFNULL(SUM(op.cantidad), 0) FROM ordenes_productos op JOIN products pp ON pp._id = op.id_woo WHERE op.id_orden = b.id_orden AND (pp.fisico = 1 OR pp.fisico IS NULL) AND (pp.es_diseno = 0 OR pp.es_diseno IS NULL))
                            )
                        ) * (IFNULL(b.procentaje_comision, 100) / 100)) as cantidad_productos
                    FROM
                        pagos a
                    JOIN
                        lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                    LEFT JOIN
                        ordenes o ON b.id_orden = o._id
                    JOIN
                        api_empresas.empresas_usuarios c ON b.id_empleado = c.id_usuario
                    LEFT JOIN 
                        lotes_detalles ld ON b.id_lotes_detalles = ld._id
                    LEFT JOIN
                        ordenes_productos d ON ld.id_ordenes_productos = d._id
                    LEFT JOIN 
                        products p ON d.id_woo = p._id
                    WHERE
                        a.fecha_pago IS NULL
                        AND c.comision_tipo = 'variable'
                        AND (p.fisico = 1 OR p.fisico IS NULL)
                        AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";

    $pagos_variables = $localConnection->goQuery($sql_variable);
    if (!is_array($pagos_variables) || (isset($pagos_variables['status']) && $pagos_variables['status'] === 'error')) {
      $pagos_variables = [];
    }

    // --- 3. Consulta para empleados con COMISIÓN PORCENTAJE ---
    $sql_porcentaje = "SELECT DISTINCT
                        a._id AS id_pago,
                        a.id_reposicion,
                        d.id_woo AS cod,
                        b._id AS id_lotes_detalles,
                        b.id_orden AS orden,
                        d.name AS producto,
                        d.cantidad,
                        d.talla,
                        c.id_usuario AS id_empleado,
                        c.nombre,
                        a.monto_pago AS pago,
                        a.comision AS comision,
                        a.comision_tipo,
                        c.salario_tipo,
                        a.detalle AS departamento,
                        o.pago_total AS monto_orden,
                        DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                        DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                        DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                        a.fecha_pago,
                        TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                        d.precio_unitario AS precio_producto,
                        (SELECT IF(a.id_reposicion > 0,
                            (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion),
                            IF(a.id_departamento = 3,
                                IFNULL((SELECT SUM(ic.cantidad) FROM inventario_corte ic JOIN ordenes_productos op ON op._id = ic.id_ordenes_productos WHERE ic.id_orden = b.id_orden), 0),
                                (SELECT IFNULL(SUM(op.cantidad), 0) FROM ordenes_productos op JOIN products pp ON pp._id = op.id_woo WHERE op.id_orden = b.id_orden AND (pp.fisico = 1 OR pp.fisico IS NULL) AND (pp.es_diseno = 0 OR pp.es_diseno IS NULL))
                            )
                        ) * (IFNULL(b.procentaje_comision, 100) / 100)) as cantidad_productos
                    FROM
                        pagos a
                    JOIN
                        lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                    LEFT JOIN
                        ordenes o ON b.id_orden = o._id
                    JOIN
                        api_empresas.empresas_usuarios c ON b.id_empleado = c.id_usuario
                    LEFT JOIN 
                        lotes_detalles ld ON b.id_lotes_detalles = ld._id
                    LEFT JOIN
                        ordenes_productos d ON ld.id_ordenes_productos = d._id
                    LEFT JOIN 
                        products p ON d.id_woo = p._id
                    WHERE
                        a.fecha_pago IS NULL
                        AND c.comision_tipo = 'porcentaje'
                        AND (p.fisico = 1 OR p.fisico IS NULL)
                        AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";

    $pagos_porcentaje = $localConnection->goQuery($sql_porcentaje);
    if (!is_array($pagos_porcentaje) || (isset($pagos_porcentaje['status']) && $pagos_porcentaje['status'] === 'error')) {
      $pagos_porcentaje = [];
    }

    // --- 4. Unir y ordenar los resultados ---
    $todos_los_pagos = array_merge($pagos_fijos, $pagos_variables, $pagos_porcentaje);

    // Opcional: re-ordenar el array combinado por nombre y luego por orden
    usort($todos_los_pagos, function ($a, $b) {
      if ($a['nombre'] == $b['nombre']) {
        return $a['orden'] <=> $b['orden'];
      }
      return $a['nombre'] <=> $b['nombre'];
    });

    $object['data']['empleados'] = $todos_los_pagos;
    $object['sql_debug']['fija'] = $sql_fija;
    $object['sql_debug']['variable'] = $sql_variable;
    $object['sql_debug']['porcentaje'] = $sql_porcentaje;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/semana/vendedores', function (Request $request, Response $response, array $args) {
    // OBTERER PAGOS DE VENDEDORES
    $localConnection = new LocalDB();

    $sql = "SELECT
                a._id AS id_pago,
                a.id_orden,
                a.id_empleado,
                a.detalle,
                a.cantidad,
                a.monto_pago AS pago,
                -- (a.comision * a.cantidad) pago,
                a.comision,
                a.comision_tipo,
                c.salario_tipo,
                c.nombre,
                d.pago_abono monto_abonado,
                e.monto monto_abonado_abono,
                d.status,
                e.tipo_de_pago,
                a.fecha_pago,
                (SELECT numero_semana FROM " . LOCAL_DB . ".pagos_salarios ps JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = a.id_empleado ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                (SELECT YEAR(pa.moment) FROM " . LOCAL_DB . ".pagos_salarios ps JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = a.id_empleado ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago,
                (SELECT IF(a.id_reposicion > 0, (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion), (SELECT IFNULL(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden))) as cantidad_productos
            FROM
                pagos a
            JOIN abonos b ON
                b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
            JOIN api_empresas.empresas_usuarios c
            ON
                a.id_empleado = c.id_usuario
            JOIN ordenes d ON
                a.id_orden = d._id
            LEFT JOIN metodos_de_pago e ON
                e._id = a.id_metodos_de_pago
            WHERE
                a.fecha_pago IS NULL AND d.status != 'cancelada'
            GROUP BY
                a._id
            ORDER BY
                d._id ASC,
                a._id
            DESC
    ;
        ";

    // $object['sql'] = $sql;
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/vendedor/{id_vendedoor}', function (Request $request, Response $response, array $args) {
    // OBTERER PAGOS DE VENDEDORES
    $localConnection = new LocalDB();

    $sql = "SELECT
                a._id AS id_pago,
                a.id_orden,
                a.id_empleado,
                a.detalle,
                a.cantidad,
                a.monto_pago AS pago,
                -- (a.comision * a.cantidad) pago,
                a.comision,
                a.comision_tipo,
                c.nombre,
                d.pago_abono monto_abonado,
                e.monto monto_abonado_abono,
                d.status,
                e.tipo_de_pago,
                a.fecha_pago,
                DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago,
                (SELECT IF(a.id_reposicion > 0, (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion), (SELECT IFNULL(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden))) as cantidad_productos
            FROM
                pagos a
            JOIN abonos b ON
                b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
            JOIN api_empresas.empresas_usuarios c
            ON
                a.id_empleado = c.id_usuario
            JOIN ordenes d ON
                a.id_orden = d._id
            LEFT JOIN metodos_de_pago e ON
                e._id = a.id_metodos_de_pago OR a.id_metodos_de_pago IS NULL
            WHERE
                a.id_empleado = {$args['id_vendedoor']} AND 
                a.fecha_pago IS NULL
            GROUP BY
                a._id
            ORDER BY
                d._id ASC,
                a._id
            DESC
    ;
        ";

    // $object['sql'] = $sql;
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/historico/{semana}', function (Request $request, Response $response, array $args) {
    // $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PAGOS VENDEDORES
    $sql = "SELECT
        a._id AS id_pago,
        a.id_orden,
        a.id_empleado,
        a.detalle,
        a.cantidad,
        a.monto_pago AS pago,
        a.comision,
        a.comision_tipo,
        c.nombre,
        d.pago_abono monto_abonado,
        e.monto monto_abonado_abono,
        d.status,
        e.tipo_de_pago,
        a.fecha_pago,
        DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago
        FROM
        pagos a
        JOIN
        abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
        JOIN
        api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
        JOIN
        ordenes d ON a.id_orden = d._id
        LEFT JOIN
        metodos_de_pago e ON e._id = a.id_metodos_de_pago
        WHERE WEEK(a.fecha_pago, 1) = {$args['semana']} AND a.fecha_pago IS NOT NULL
        GROUP BY
        a._id
        ORDER BY
        d._id ASC, a._id DESC;
        ";
    $object['sql_pagos_vendedores'] = $sql;
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    // CONSULTAS ADICIONALES PARA DETALLES DE RECIBO (Salarios, Bonos, Descuentos)

    // 1. Salarios
    $sqlSalarios = "SELECT 
        ps.monto, 
        ps.tipo_salario, 
        p.id_empleado 
        FROM pagos_salarios ps 
        JOIN pagos p ON ps.id_pago = p._id 
        WHERE WEEK(p.fecha_pago, 1) = {$args['semana']} AND p.fecha_pago IS NOT NULL
        GROUP BY p.id_empleado"; // Agrupamos por empleado porque el salario es por periodo, no por orden individual necesariamente para el recibo global

    $salariosData = $localConnection->goQuery($sqlSalarios);
    $object['data']['salarios_detalles'] = $salariosData ?: [];

    // 2. Bonos (Abonos extra)
    $sqlBonos = "SELECT 
        pa.monto, 
        pa.descripcion, 
        p.id_empleado 
        FROM pagos_abonos pa 
        JOIN pagos p ON pa.id_pago = p._id 
        WHERE WEEK(p.fecha_pago, 1) = {$args['semana']} AND p.fecha_pago IS NOT NULL";

    $bonosData = $localConnection->goQuery($sqlBonos);
    $object['data']['bonos_detalles'] = $bonosData ?: [];

    // 3. Descuentos
    $sqlDescuentos = "SELECT 
        pd.monto, 
        pd.descripcion, 
        p.id_empleado 
        FROM pagos_descuentos pd 
        JOIN pagos p ON pd.id_pago = p._id 
        WHERE WEEK(p.fecha_pago, 1) = {$args['semana']} AND p.fecha_pago IS NOT NULL";

    $descuentosData = $localConnection->goQuery($sqlDescuentos);
    $object['data']['descuentos_detalles'] = $descuentosData ?: [];


    // PAGOS ESPLEADOS
    $sql = 'SELECT
            a._id id_pago,
            b.id_woo cod,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            a.cantidad cantidad,
            d.talla,
            c.id_usuario id_empleado,
            c.nombre,
            a.monto_pago pago,
            a.comision,
            a.comision_tipo,
            c.departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            a.fecha_pago,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            JOIN lotes_detalles b ON
            a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON
            a.id_empleado = c.id_usuario
            JOIN ordenes_productos d ON
            b.id_ordenes_productos = d._id
            WHERE WEEK(a.fecha_pago, 1) = ' . $args['semana'] . ' AND a.fecha_pago IS NOT NULL
            ORDER BY
            c.nombre ASC,
            b.id_orden ASC,
            a._id ASC;';
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    $object['sql_pagos_empleados'] = $sql;

    // DISENADORES
    $sql = 'SELECT
            p.id_orden,
            r._id id_revision,
            p.monto_pago,
            p.id_empleado,
            p.fecha_pago,
            (SELECT departamento FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado) departamento,
            (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado) nombre,
            (SELECT product producto FROM products WHERE _id = r.id_product) producto

        FROM
            pagos p
        JOIN revisiones r ON p.id_orden = r.id_orden AND p.id_empleado = r.id_empleado
        WHERE WEEK(p.fecha_pago, 1) = ' . $args['semana'] . ' AND p.fecha_pago IS NOT NULL
        GROUP BY p._id
        ';
    $object['sql_disenos'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    /* if (!empty($object['data']['diseno'])) {
        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);

            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        }
    } else {
        $object['data']['trabajos_adicionales'] = [];
    } */

    $trabajos_adicionales_nuevos = [];
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Lista de pagos semanales con filtro de fechas
  $app->post('/pagos/semana', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    if (isset($data['numero_semana'])) {
      //
      $where = 'WEEK(e.moment) = ' . $data['numero_semana'] . "%' AND e.fecha_pago IS NULL";
      // $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
    } else {
    }

    if ($data['fecha_inicio'] === $data['fecha_fin']) {
      $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
      // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
    } else {
      $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
      $whereEmpleados = "b.fecha_inicio >= '" . $data['fecha_inicio'] . "' AND DATE_ADD(b.fecha_terminado, INTERVAL -1 DAY) <= '" . $data['fecha_fin'] . "' ";
    }

    $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    $object['data']['vendedores'] = $localConnection->goQuery($sql);
    // FIN BUSCAR PAGOS DE VENDEDORES

    // OBTENER PAGOS DE EMPLEADOS
    $sql = 'SELECT
            a._id id_pago,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            d.talla,
            c.id_usuario id_empleado,
            c.nombre,
            c.comision,
            b.id_departamento,
            b.departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            b.unidades_solicitadas cantidad,
            a.monto_pago pago,
            a.fecha_pago,
            a.cantidad,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            JOIN lotes_detalles b ON
            a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON
            b.id_empleado = c.id_usuario
            JOIN ordenes_productos d ON
            b.id_ordenes_productos = d._id
            WHERE ' . $whereEmpleados . ' AND a.fecha_pago IS NULL 
            ORDER BY
            c.nombre ASC,
            b.id_orden ASC,
            a._id ASC;
        ';

    $object['sql']['empleados'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    // FIN PAGOS EMPLEADOS

    // OBTENER INFORMACION DE DISEÑADORES
    $sql = "SELECT 
        e._id id_pago,
        e.id_orden, 
        e.id_empleado,
        e.detalle detalle_pago,
        a._id id_diseno, 
        b.nombre nombre, 
        b.departamento, 
        e.monto_pago pago,
        e.cantidad,
        c.name producto 
        FROM pagos e   
        JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
        JOIN api_empresas.empresas_usuarios b 
        ON b.id_usuario = e.id_empleado 
        JOIN ordenes_productos c 
        ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
        WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
    $object['sql']['diseno'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);
      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }

    $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
      foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
        $existe = false;
        foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
          if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
            $existe = true;
            break;
          }
        }
        if (!$existe) {
          $trabajos_adicionales_nuevos[] = $trabajo_adicional;
        }
      }
      $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
      $object['data']['trabajos_adicionales'] = [];
    }
    // FIN PAGOS DISEÑADORES

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/pagos/semana/OLD', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    if ($data['fecha_inicio'] === $data['fecha_fin']) {
      $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
      // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
    } else {
      $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
      $whereEmpleados = "b.fecha_terminado BETWEEN '" . $data['fecha_inicio'] . "%' AND '" . $data['fecha_fin'] . "' AND e.fecha_pago IS NULL ";

      // $where = "(DATE(e.moment) BETWEEN '" . $data["fecha_inicio"] . "' AND '" . $data["fecha_fin"] . "') ";
    }

    $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    $object['data']['vendedores'] = $localConnection->goQuery($sql);
    // FIN BUSCAR PAGOS DE VENDEDORES

    // OBTENER PAGOS DE EMPLEADOS
    $sql = 'SELECT
    a._id id_pago,
    b._id id_lotes_detalles,
    b.id_orden orden,
    b.id_woo id_woo,
    d.name producto,
    d.talla,
    c._id id_empleado,
    c.nombre,
    c.comision,
    c.departamento,
    DATE_FORMAT(b.fecha_terminado, "%a") dia,
    DATE_FORMAT(b.fecha_terminado, "%v") semana,
    DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
    b.unidades_solicitadas cantidad,
    a.monto_pago pago,
    a.fecha_pago,
    a.cantidad,
    TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
    FROM
    pagos a
    JOIN lotes_detalles b ON
    a.id_lotes_detalles = b._id
    JOIN empleados c ON
    b.id_empleado = c._id
    JOIN ordenes_productos d ON
    b.id_ordenes_productos = d._id
    WHERE ' . $whereEmpleados . ' AND e.fecha_pago IS NULL 
    ORDER BY
    c.nombre ASC,
    b.id_orden ASC,
    a._id ASC;
    ';

    $object['sql']['empleados'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    // FIN PAGOS EMPLEADOS

    // OBTENER INFORMACION DE DISEÑADORES
    $sql = "SELECT 
    e._id id_pago,
    e.id_orden, 
    e.id_empleado,
    e.detalle detalle_pago,
    a._id id_diseno, 
    b.nombre nombre, 
    b.departamento, 
    e.monto_pago pago,
    e.cantidad,
    c.name producto 
    FROM pagos e   
    JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
    JOIN empleados b 
    ON b._id = e.id_empleado 
    JOIN ordenes_productos c 
    ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
    WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
    $object['sql']['diseno'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);
      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }

    $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
      foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
        $existe = false;
        foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
          if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
            $existe = true;
            break;
          }
        }
        if (!$existe) {
          $trabajos_adicionales_nuevos[] = $trabajo_adicional;
        }
      }
      $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
      $object['data']['trabajos_adicionales'] = [];
    }
    // FIN PAGOS DISEÑADORES
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN PAGOS */

}; // Fin de la función que envuelve las rutas
