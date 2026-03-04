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

  // PROCESAR LOTE MASIVO DE PAGOS
  $app->post('/pagos/procesar-lote-pagos', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    // Fallback if getParsedBody is empty (sometimes Content-Type issues cause this)
    if (empty($data)) {
      $body = $request->getBody()->getContents();
      $data = json_decode($body, true);
    }

    error_log('DEBUG procesar-lote-pagos DATA: ' . print_r($data, true));

    $localConnection = new LocalDB();

    $myDate = new CustomTime();
    $now = $myDate->today();

    // Sometimes the frontend might send JSON incorrectly or nested differently
    // If $data contains 'pagos' as a JSON string instead of an array, decode it
    if (isset($data['pagos']) && is_string($data['pagos'])) {
      $pagosLote = json_decode($data['pagos'], true);
    } else {
      $pagosLote = $data['pagos'] ?? [];
    }

    error_log('DEBUG procesar-lote-pagos pagosLote: ' . print_r($pagosLote, true));

    if (!is_array($pagosLote) || count($pagosLote) === 0) {
      error_log('DEBUG procesar-lote-pagos FALLO VALIDACION: no hay pagos o no es array. ' . (is_array($pagosLote) ? 'Es array vacío' : 'No es array'));
      return ApiResponse::validationError($response, 'No se proporcionaron pagos válidos para procesar');
    }

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      $totalMontoPagadoLote = 0;
      $totalPagosProcesadosLote = 0;

      foreach ($pagosLote as $pagoData) {
        $idPagosStr = $pagoData['id_pagos'] ?? '';
        $idEmpleado = $pagoData['id_empleado'] ?? null;
        $bonos = $pagoData['bonos'] ?? [];
        $descuentos = $pagoData['descuentos'] ?? [];
        $salario = floatval($pagoData['monto_salario'] ?? 0);
        $comision = floatval($pagoData['monto_comision'] ?? 0);

        // Sanitizar y convertir los IDs de pago a un array de enteros.
        $listaDeIdPagos = array_map('intval', explode(',', $idPagosStr));
        // Filtrar valores vacíos o cero
        $listaDeIdPagos = array_filter($listaDeIdPagos, function ($id) {
          return $id > 0;
        });

        $idsParaPago = $listaDeIdPagos;

        // Si no hay pagos operativos (cantidadDePagos == 0) pero hay valores a procesar, creamos un ancla virtual en la tabla pagos
        if (count($idsParaPago) === 0 && ($salario > 0 || count($bonos) > 0 || count($descuentos) > 0) && $idEmpleado) {
          $fechaActual = $pagoData['fecha_pago'] ?? $now;
          // El campo detalle en la tabla pagos es VARCHAR(16). 'Pago Nomina' tiene 11 chars.
          $detallePago = 'Pago Nomina';

          $sqlVirtual = "INSERT INTO pagos (id_empleado, detalle, monto_pago, comision, comision_tipo, cantidad, fecha_pago, estatus) VALUES (?, ?, 0, 0, 'fija', 1, ?, 'aprobado')";
          $result = $localConnection->goQuery($sqlVirtual, [$idEmpleado, $detallePago, $fechaActual]);

          if (isset($result['insert_id'])) {
            $idsParaPago = [$result['insert_id']];
          }
        }

        $cantidadDePagos = count($idsParaPago);

        if ($cantidadDePagos > 0) {

          $totalBonosAplicados = 0;
          $totalDescuentosAplicados = 0;

          // Procesar bonos
          if (is_array($bonos) && count($bonos) > 0) {
            foreach ($bonos as $bono) {
              $monto = floatval($bono['monto'] ?? 0);
              $descripcion = $bono['descripcion'] ?? 'Bono global';
              if ($monto > 0) {
                $totalBonosAplicados += $monto;
                foreach ($idsParaPago as $idPago) {
                  $montoPorPago = $monto / $cantidadDePagos;
                  $sql = "INSERT INTO pagos_abonos (id_pago, monto, descripcion) VALUES (?, ?, ?)";
                  $localConnection->goQuery($sql, [$idPago, $montoPorPago, $descripcion]);
                }
              }
            }
          }

          // Procesar descuentos
          if (is_array($descuentos) && count($descuentos) > 0) {
            foreach ($descuentos as $descuento) {
              $monto = floatval($descuento['monto'] ?? 0);
              $descripcion = $descuento['descripcion'] ?? 'Descuento global';
              if ($monto > 0) {
                $totalDescuentosAplicados += $monto;
                foreach ($idsParaPago as $idPago) {
                  $montoPorPago = $monto / $cantidadDePagos;
                  $sql = "INSERT INTO pagos_descuentos (id_pago, monto, descripcion) VALUES (?, ?, ?)";
                  $localConnection->goQuery($sql, [$idPago, $montoPorPago, $descripcion]);
                }
              }
            }
          }

          // Procesar salario si existe
          if ($salario > 0 && $idEmpleado) {
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

            $salarioPorPago = $salario / $cantidadDePagos;
            foreach ($idsParaPago as $idPago) {
              $sql = "INSERT INTO pagos_salarios (id_pago, tipo_salario, numero_semana, monto) VALUES (?, ?, ?, ?)";
              $localConnection->goQuery($sql, [$idPago, $periodo, $numeroPeriodo, $salarioPorPago]);
            }
          }

          // Calcular el monto total del pago y el monto por cada registro de pago
          $montoTotalPago = ($salario + $comision + $totalBonosAplicados) - $totalDescuentosAplicados;
          $montoPorRegistroDePago = $montoTotalPago / $cantidadDePagos;

          $totalMontoPagadoLote += $montoTotalPago;
          $totalPagosProcesadosLote += $cantidadDePagos;

          // Crear placeholders (?) para la cláusula IN
          $placeholders = implode(',', array_fill(0, count($idsParaPago), '?'));

          // Actualizar pagos con fecha_pago y el monto_pago DIVIDIDO
          $fechaActual = $pagoData['fecha_pago'] ?? $now;
          $sql = "UPDATE pagos SET fecha_pago = ?, monto_pago = ? WHERE _id IN ({$placeholders})";
          $params = array_merge([$fechaActual, $montoPorRegistroDePago], $idsParaPago);
          $localConnection->goQuery($sql, $params);
        }
      }

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Lote de pagos procesado correctamente', [
        'cantidad_registros_procesados' => $totalPagosProcesadosLote,
        'monto_total_pagado' => $totalMontoPagadoLote
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /pagos/procesar-lote-pagos: ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al procesar el lote de pagos: ' . $e->getMessage(), $e);
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

    $id_empresa = intval(str_replace('api_emp_', '', LOCAL_DB));

    // --- 1. Consulta para empleados con COMISIÓN FIJA ---
    $sql_fija = "SELECT DISTINCT
                    a._id AS id_pago,
                    a.id_reposicion,
                    'N/A' as cod,
                    b._id AS id_lotes_detalles,
                    b.id_orden AS orden,
                    b.id_orden AS id_orden, 
                    'Pago Fijo Global' AS producto,
                    'N/A' as talla,
                    c.id_usuario AS id_empleado,
                    c.nombre,
                    a.monto_pago AS pago,
                    c.comision,
                    a.comision AS comision,
                    a.comision_tipo,
                    c.salario_tipo,
                    c.salario_monto,
                    c.salario_periodo,
                    a.detalle AS departamento,
                    o.pago_total AS monto_orden,
                    DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                    DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                    DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                    a.fecha_pago,
                    TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                    a.cantidad as cantidad
                FROM
                    pagos a
                LEFT JOIN
                    lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                LEFT JOIN
                    ordenes o ON a.id_orden = o._id
                JOIN
                    api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
                WHERE
                    a.fecha_pago IS NULL
                    AND a.comision_tipo = 'fija'
                    AND a.id_lotes_detalles IS NOT NULL";

    $pagos_fijos = $localConnection->goQuery($sql_fija);
    if (!is_array($pagos_fijos) || (isset($pagos_fijos['status']) && $pagos_fijos['status'] === 'error')) {
      $pagos_fijos = [];
    }

    // --- 2. Consulta para empleados con COMISIÓN VARIABLE (desglosada por producto) ---
    $sql_variable = "SELECT DISTINCT
                        a._id AS id_pago,
                        a.id_reposicion,
                        'N/A' AS cod,
                        b._id AS id_lotes_detalles,
                        b.id_orden AS orden,
                        'Lote Multi-Producto' AS producto,
                        'N/A' AS talla,
                        c.id_usuario AS id_empleado,
                        c.nombre,
                        a.monto_pago AS pago,
                        a.comision AS comision,
                        a.comision_tipo,
                        c.salario_tipo,
                        c.salario_monto,
                        c.salario_periodo,
                        a.detalle AS departamento,
                        o.pago_total AS monto_orden,
                        DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                        DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                        DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                        a.fecha_pago,
                        TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                        a.cantidad as cantidad
                    FROM
                        pagos a
                    LEFT JOIN
                        lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                    LEFT JOIN
                        ordenes o ON a.id_orden = o._id
                    JOIN
                        api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
                    WHERE
                        a.fecha_pago IS NULL
                        AND a.comision_tipo = 'variable'
                        AND a.id_lotes_detalles IS NOT NULL";

    $pagos_variables = $localConnection->goQuery($sql_variable);
    if (!is_array($pagos_variables) || (isset($pagos_variables['status']) && $pagos_variables['status'] === 'error')) {
      $pagos_variables = [];
    }

    // --- 3. Consulta para empleados con COMISIÓN PORCENTAJE ---
    $sql_porcentaje = "SELECT DISTINCT
                        a._id AS id_pago,
                        a.id_reposicion,
                        'N/A' AS cod,
                        b._id AS id_lotes_detalles,
                        b.id_orden AS orden,
                        'Lote Multi-Producto' AS producto,
                        'N/A' AS talla,
                        c.id_usuario AS id_empleado,
                        c.nombre,
                        a.monto_pago AS pago,
                        a.comision AS comision,
                        a.comision_tipo,
                        c.salario_tipo,
                        c.salario_monto,
                        c.salario_periodo,
                        a.detalle AS departamento,
                        o.pago_total AS monto_orden,
                        DATE_FORMAT(b.fecha_terminado, '%a') AS dia,
                        DATE_FORMAT(b.fecha_terminado, '%v') AS semana,
                        DATE_FORMAT(b.fecha_terminado, '%d/%m/%y') AS fecha,
                        a.fecha_pago,
                        TIMEDIFF(b.fecha_terminado, b.fecha_inicio) AS tiempo_transcurrido,
                        0 AS precio_producto,
                        a.cantidad as cantidad
                    FROM
                        pagos a
                    LEFT JOIN
                        lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
                    LEFT JOIN
                        ordenes o ON a.id_orden = o._id
                    JOIN
                        api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
                    WHERE
                        a.fecha_pago IS NULL
                        AND a.comision_tipo = 'porcentaje'
                        AND a.id_lotes_detalles IS NOT NULL";

    $pagos_porcentaje = $localConnection->goQuery($sql_porcentaje);
    if (!is_array($pagos_porcentaje) || (isset($pagos_porcentaje['status']) && $pagos_porcentaje['status'] === 'error')) {
      $pagos_porcentaje = [];
    }

    // --- 4. Consulta para ESTRICTAMENTE ADMINISTRATIVOS O SIN TAREAS PENDIENTES ---
    $sql_administrativo = "SELECT DISTINCT
                        0 AS id_pago,
                        0 AS id_reposicion,
                        'N/A' as cod,
                        0 AS id_lotes_detalles,
                        0 AS orden,
                        0 AS id_orden, 
                        'Sin tareas operacionales' AS producto,
                        'N/A' as talla,
                        eu.id_usuario AS id_empleado,
                        eu.nombre,
                        0 AS pago,
                        0 as comision,
                        'fija' AS comision_tipo,
                        eu.salario_tipo,
                        eu.salario_monto,
                        eu.salario_periodo,
                        eu.departamento AS departamento,
                        0 AS monto_orden,
                        '' AS dia,
                        '' AS semana,
                        '' AS fecha,
                        NULL AS fecha_pago,
                        '' AS tiempo_transcurrido,
                        0 as cantidad,
                        (SELECT numero_semana FROM " . LOCAL_DB . ".pagos_salarios ps JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                        (SELECT YEAR(pa.moment) FROM " . LOCAL_DB . ".pagos_salarios ps JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado
                    FROM
                        api_empresas.empresas_usuarios eu
                    WHERE
                        eu.id_empresa = {$id_empresa}
                        AND eu.salario_monto > 0
                        AND eu.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM pagos p 
                            WHERE p.id_empleado = eu.id_usuario 
                            AND p.fecha_pago IS NULL
                            AND p.id_orden > 0
                        )
                        -- Ocultar si ya se pagó el salario de este periodo (semana/quincena/mes) 
                        -- (Calculamos el periodo actual igual que en el proceso de inserción)
                        AND (
                            SELECT COUNT(*) FROM " . LOCAL_DB . ".pagos_salarios ps 
                            JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id 
                            WHERE pa.id_empleado = eu.id_usuario 
                            AND ps.numero_semana = (
                                CASE 
                                    WHEN eu.salario_periodo = 'quincenal' THEN (YEAR(NOW()) * 24 + (MONTH(NOW())-1) * 2 + IF(DAY(NOW())<=15, 1, 2))
                                    WHEN eu.salario_periodo = 'mensual' THEN (YEAR(NOW()) * 12 + (MONTH(NOW())-1))
                                    ELSE WEEK(NOW()) 
                                END
                            )
                        ) = 0";

    $pagos_administrativos = $localConnection->goQuery($sql_administrativo);
    if (!is_array($pagos_administrativos) || (isset($pagos_administrativos['status']) && $pagos_administrativos['status'] === 'error')) {
      $pagos_administrativos = [];
    }

    // --- 5. Unir y ordenar los resultados ---
    $todos_los_pagos = array_merge($pagos_fijos, $pagos_variables, $pagos_porcentaje, $pagos_administrativos);

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
    $object['sql_debug']['administrativos'] = $sql_administrativo;

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

  // =============================================
  // REPORTE DETALLADO DE PAGO POR EMPLEADO
  // Params: ?pendiente=1 (planilla actual) ó ?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD (histórico)
  // =============================================
  $app->get('/pagos/reporte-empleado/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $params = $request->getQueryParams();
    $idEmpleado = intval($args['id_empleado']);
    $pendiente = isset($params['pendiente']) && $params['pendiente'] == '1';
    $fechaInicio = $params['fecha_inicio'] ?? null;
    $fechaFin = $params['fecha_fin'] ?? null;

    // Condición de fecha para filtrar
    if ($pendiente) {
      // Planilla actual: mostrar pagos AÚN NO PROCESADOS (fecha_pago IS NULL)
      $whereFecha = "AND a.fecha_pago IS NULL";
      $whereFechaP = "AND p.fecha_pago IS NULL";
    } elseif ($fechaInicio && $fechaFin) {
      $whereFecha = "AND DATE(a.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      $whereFechaP = "AND DATE(p.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
    } else {
      // Sin filtro: mostrar la última fecha de pago procesada
      $sqlUltimaFecha = "SELECT MAX(fecha_pago) AS ultima_fecha FROM pagos WHERE id_empleado = {$idEmpleado}";
      $resUltimaFecha = $localConnection->goQuery($sqlUltimaFecha);
      $ultimaFecha = (is_array($resUltimaFecha) && !empty($resUltimaFecha[0]['ultima_fecha']))
        ? $resUltimaFecha[0]['ultima_fecha']
        : date('Y-m-d');
      $whereFecha = "AND DATE(a.fecha_pago) = DATE('{$ultimaFecha}')";
      $whereFechaP = "AND DATE(p.fecha_pago) = DATE('{$ultimaFecha}')";
    }

    // 1. Info de la empresa
    $sqlConfig = "SELECT nombre_empresa, direccion, telefonos, email FROM config LIMIT 1";
    $configData = $localConnection->goQuery($sqlConfig);
    $empresa = (is_array($configData) && isset($configData[0]))
      ? $configData[0]
      : ['nombre_empresa' => 'Empresa', 'direccion' => '', 'telefonos' => '', 'email' => ''];

    // 2. Info del empleado
    $sqlEmpleado = "SELECT id_usuario, nombre, departamento, salario_tipo, salario_monto, salario_periodo FROM api_empresas.empresas_usuarios WHERE id_usuario = {$idEmpleado}";
    $empleadoData = $localConnection->goQuery($sqlEmpleado);
    $empleado = (is_array($empleadoData) && isset($empleadoData[0]))
      ? $empleadoData[0]
      : ['nombre' => 'Empleado', 'departamento' => ''];

    // 3. Pagos detallados
    // La tabla 'pagos' ya contiene: detalle (departamento), cantidad y monto_pago (= cantidad × comisión).
    // Obtenemos el nombre del producto con un subquery a ordenes_productos.
    // No usamos JOINs con lotes_detalles porque generan filas duplicadas.
    $sqlPagos = "SELECT
        a._id AS id_pago,
        a.id_orden,
        a.detalle AS departamento_pago,
        a.cantidad,
        a.monto_pago,
        a.comision,
        a.comision_tipo,
        a.fecha_pago,
        COALESCE(
          (SELECT op.name FROM ordenes_productos op WHERE op.id_orden = a.id_orden LIMIT 1),
          a.detalle, 'N/A'
        ) AS producto,
        dep.orden_proceso,
        dep.departamento AS nombre_departamento
      FROM pagos a
      LEFT JOIN departamentos dep ON dep.departamento = a.detalle
      WHERE a.id_empleado = {$idEmpleado}
      {$whereFecha}
      ORDER BY a.id_orden ASC, dep.orden_proceso ASC, a._id ASC";

    $pagosDetalle = $localConnection->goQuery($sqlPagos);
    if (!is_array($pagosDetalle))
      $pagosDetalle = [];

    // 4. Salario del periodo
    $sqlSalario = "SELECT SUM(ps.monto) AS total_salario
      FROM pagos_salarios ps
      JOIN pagos p ON ps.id_pago = p._id
      WHERE p.id_empleado = {$idEmpleado} {$whereFechaP}";
    $salarioRes = $localConnection->goQuery($sqlSalario);
    $totalSalario = (is_array($salarioRes) && isset($salarioRes[0]['total_salario']))
      ? floatval($salarioRes[0]['total_salario'])
      : 0.0;

    // 5. Bonos del periodo
    $sqlBonos = "SELECT pa.descripcion, SUM(pa.monto) AS monto
      FROM pagos_abonos pa
      JOIN pagos p ON pa.id_pago = p._id
      WHERE p.id_empleado = {$idEmpleado} {$whereFechaP}
      GROUP BY pa.descripcion";
    $bonosRes = $localConnection->goQuery($sqlBonos);
    if (!is_array($bonosRes))
      $bonosRes = [];

    // 6. Descuentos del periodo
    $sqlDescuentos = "SELECT pd.descripcion, SUM(pd.monto) AS monto
      FROM pagos_descuentos pd
      JOIN pagos p ON pd.id_pago = p._id
      WHERE p.id_empleado = {$idEmpleado} {$whereFechaP}
      GROUP BY pd.descripcion";
    $descuentosRes = $localConnection->goQuery($sqlDescuentos);
    if (!is_array($descuentosRes))
      $descuentosRes = [];

    // 7. Calcular totales
    $totalComision = array_reduce($pagosDetalle, function ($carry, $item) {
      return $carry + floatval($item['monto_pago'] ?? 0);
    }, 0.0);
    $totalBonos = array_reduce($bonosRes, function ($c, $i) {
      return $c + floatval($i['monto'] ?? 0);
    }, 0.0);
    $totalDescuentos = array_reduce($descuentosRes, function ($c, $i) {
      return $c + floatval($i['monto'] ?? 0);
    }, 0.0);
    $totalPagado = $totalSalario + $totalComision + $totalBonos - $totalDescuentos;
    $totalPiezas = array_reduce($pagosDetalle, function ($carry, $item) {
      return $carry + intval($item['cantidad'] ?? 0);
    }, 0);

    $localConnection->disconnect();

    $result = [
      'empresa' => $empresa,
      'empleado' => $empleado,
      'pagos' => $pagosDetalle,
      'salario' => $totalSalario,
      'bonos' => $bonosRes,
      'descuentos' => $descuentosRes,
      'totales' => [
        'salario' => round($totalSalario, 2),
        'comision' => round($totalComision, 2),
        'bonos' => round($totalBonos, 2),
        'descuentos' => round($totalDescuentos, 2),
        'total' => round($totalPagado, 2),
        'piezas' => $totalPiezas,
      ],
    ];

    $response->getBody()->write(json_encode(['success' => true, 'data' => $result], JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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
            NULL as cod,
            b._id id_lotes_detalles,
            a.id_orden orden,
            NULL as id_woo,
            \'Pago Multi-Producto\' as producto,
            a.cantidad cantidad,
            \'N/A\' as talla,
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
            TIMEDIFF(b.fecha_terminado, b.fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            LEFT JOIN lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
            WHERE WEEK(a.fecha_pago, 1) = ' . $args['semana'] . ' AND a.fecha_pago IS NOT NULL
            ORDER BY
            c.nombre ASC,
            a.id_orden ASC,
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
            a.id_orden orden,
            NULL as id_woo,
            \'Pago Multi-Producto\' as producto,
            \'N/A\' as talla,
            c.id_usuario id_empleado,
            c.nombre,
            c.comision,
            b.id_departamento,
            a.detalle as departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            a.cantidad cantidad,
            a.monto_pago pago,
            a.fecha_pago,
            a.cantidad,
            TIMEDIFF(b.fecha_terminado, b.fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            LEFT JOIN lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
            WHERE ' . $whereEmpleados . ' AND a.fecha_pago IS NULL 
            ORDER BY
            c.nombre ASC,
            a.id_orden ASC,
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
    a.id_orden orden,
    NULL as id_woo,
    \'Pago Multi-Producto\' as producto,
    \'N/A\' as talla,
    c._id id_empleado,
    c.nombre,
    c.comision,
    c.departamento,
    DATE_FORMAT(b.fecha_terminado, "%a") dia,
    DATE_FORMAT(b.fecha_terminado, "%v") semana,
    DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
    a.cantidad cantidad,
    a.monto_pago pago,
    a.fecha_pago,
    a.cantidad,
    TIMEDIFF(b.fecha_terminado, b.fecha_inicio) tiempo_transcurrido
    FROM
    pagos a
    LEFT JOIN lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
    JOIN empleados c ON a.id_empleado = c._id
    WHERE ' . $whereEmpleados . ' AND a.fecha_pago IS NULL 
    ORDER BY
    c.nombre ASC,
    a.id_orden ASC,
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
