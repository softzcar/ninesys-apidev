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
    $listaDeIdPagos = array_values($listaDeIdPagos);

    $salario  = floatval($data['salario']  ?? 0);
    $comision = floatval($data['comision'] ?? 0);
    $bonosRaw = $data['bonos'] ?? '0';
    $hayConceptos = $salario > 0 || $comision > 0 || ($bonosRaw !== '0' && $bonosRaw !== '');

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      // Si no hay órdenes pendientes pero sí hay conceptos (salario/bono), crear registro ancla
      if (count($listaDeIdPagos) === 0 && $hayConceptos) {
        $idEmpleadoVirtual = $data['id_empleado'] ?? null;
        if (!$idEmpleadoVirtual) {
          $localConnection->rollback();
          return ApiResponse::validationError($response, 'Se requiere id_empleado para pagos sin órdenes pendientes');
        }
        $sqlVirtual = "INSERT INTO pagos (id_empleado, detalle, monto_pago, comision, comision_tipo, cantidad, fecha_pago, estatus) VALUES (?, 'Pago Nomina', 0, 0, 'fija', 1, ?, 'aprobado')";
        $resVirtual = $localConnection->goQuery($sqlVirtual, [$idEmpleadoVirtual, $now]);
        if (isset($resVirtual['insert_id'])) {
          $listaDeIdPagos = [$resVirtual['insert_id']];
        }
      }

      // ========== VALIDACIÓN FINAL ==========
      if (count($listaDeIdPagos) === 0) {
        $localConnection->rollback();
        return ApiResponse::validationError($response, 'No se proporcionaron IDs de pago válidos');
      }

      $cantidadDePagos = count($listaDeIdPagos);

      // Procesar bonos, descuentos, salario y comisión
      $totalBonos = 0;
      $totalDescuentos = 0;

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
      $salarioMontoReal = $salario;
      if ($salario > 0) {
        $idEmpleado = $data['id_empleado'] ?? null;

        // Si no viene el id_empleado, lo buscamos en el primer pago de la lista
        if (!$idEmpleado && count($listaDeIdPagos) > 0) {
          $sqlEmp = "SELECT id_empleado FROM pagos WHERE _id = ?";
          $resEmp = $localConnection->goQuery($sqlEmp, [$listaDeIdPagos[0]]);
          if (!empty($resEmp) && isset($resEmp[0]['id_empleado'])) {
            $idEmpleado = $resEmp[0]['id_empleado'];
          }
        }

        if ($idEmpleado) {
          // Buscar frecuencia de salario
          $sql = "SELECT salario_periodo FROM api_empresas.empresas_usuarios WHERE id_usuario = ?";
          $resultUsuario = $localConnection->goQuery($sql, [$idEmpleado]);
          $periodo = isset($resultUsuario[0]['salario_periodo']) ? $resultUsuario[0]['salario_periodo'] : 'semanal';
          
          // El frontend ya envía monto_salario prorateado (periodo × semanas pendientes).
          // No dividir aquí: $salarioMontoReal = $salario (asignado arriba).

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
          $salarioPorPago = $salarioMontoReal / $cantidadDePagos;
          foreach ($listaDeIdPagos as $idPago) {
            $sql = "INSERT INTO pagos_salarios (id_pago, tipo_salario, numero_semana, monto) VALUES (?, ?, ?, ?)";
            $localConnection->goQuery($sql, [$idPago, $periodo, $numeroPeriodo, $salarioPorPago]);
          }
        }
      }

      // monto_pago ya tiene la comisión correcta desde el registro original (manufacturing/orders).
      // Solo marcar como pagado; salario/bonos/descuentos van en sus tablas separadas.
      $montoTotalPago = ($salarioMontoReal + $comision + $totalBonos) - $totalDescuentos;

      // Crear placeholders (?) para la cláusula IN
      $placeholders = implode(',', array_fill(0, count($listaDeIdPagos), '?'));

      $sql = "UPDATE pagos SET fecha_pago = ? WHERE _id IN ({$placeholders})";
      $params = array_merge([$now], $listaDeIdPagos);
      $localConnection->goQuery($sql, $params);

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Pago procesado correctamente', [
        'cantidad_pagos' => $cantidadDePagos,
        'monto_total_pagado' => $montoTotalPago,
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
        $salarioMontoReal = $salario;
        if ($salario > 0 && $idEmpleado) {
          // Buscar frecuencia de salario
            $sql = "SELECT salario_periodo FROM api_empresas.empresas_usuarios WHERE id_usuario = ?";
            $resultUsuario = $localConnection->goQuery($sql, [$idEmpleado]);
            $periodo = isset($resultUsuario[0]['salario_periodo']) ? $resultUsuario[0]['salario_periodo'] : 'semanal';

            // El frontend ya envía monto_salario prorateado (periodo × semanas pendientes).
            // No dividir aquí: $salarioMontoReal = $salario (asignado arriba).

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
            } else {
              // Alinear con WEEK(NOW(), 1) de MySQL
              $numeroPeriodo = intval(date('W')); 
            }

            $salarioPorPago = $salarioMontoReal / $cantidadDePagos;
            foreach ($idsParaPago as $idPago) {
              $sql = "INSERT INTO pagos_salarios (id_pago, tipo_salario, numero_semana, monto) VALUES (?, ?, ?, ?)";
              $localConnection->goQuery($sql, [$idPago, $periodo, $numeroPeriodo, $salarioPorPago]);
            }
          }

          // Calcular el monto total del pago y el monto por cada registro de pago
          $montoTotalPago = ($salarioMontoReal + $comision + $totalBonosAplicados) - $totalDescuentosAplicados;
          $montoPorRegistroDePago = $montoTotalPago / $cantidadDePagos;

          $totalMontoPagadoLote += $montoTotalPago;
          $totalPagosProcesadosLote += $cantidadDePagos;

          // Crear placeholders (?) para la cláusula IN
          $placeholders = implode(',', array_fill(0, count($idsParaPago), '?'));

          // monto_pago ya tiene la comisión correcta desde el registro original (manufacturing/orders).
          // Solo marcar como pagado; salario/bonos/descuentos van en sus tablas separadas.
          $fechaActual = $pagoData['fecha_pago'] ?? $now;
          $sql = "UPDATE pagos SET fecha_pago = ? WHERE _id IN ({$placeholders})";
          $params = array_merge([$fechaActual], $idsParaPago);
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

    $queryParams = $request->getQueryParams();
    $fechaInicio = $queryParams['fecha_inicio'] ?? null;
    $fechaFin = $queryParams['fecha_fin'] ?? null;

    $whereFecha = "";
    if (DB_DRIVER === 'pgsql') {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (p.moment::date BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    } else {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (DATE(p.moment) BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    }

    // DISEÑADORES
    if (DB_DRIVER === 'pgsql') {
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
                  (SELECT numero_semana FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = p.id_empleado ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                  (SELECT EXTRACT(YEAR FROM pa.moment) FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = p.id_empleado ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                  (SELECT CASE WHEN p.id_reposicion > 0 THEN (SELECT unidades FROM reposiciones WHERE _id = p.id_reposicion) ELSE (SELECT COALESCE(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = p.id_orden) END) as cantidad_productos,
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
                  p.id_orden = r.id_orden AND p.id_empleado = r.id_empleado AND r.estatus = \'Aprobado\'
              WHERE p.fecha_pago IS NULL AND p.detalle IN (\'Diseño\', \'ajuste\', \'personalización\')' . $whereFecha . '
              GROUP BY p._id, r._id, r.url_image, r.id_empleado, r.id_product
          ';
    } else {
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
              WHERE p.fecha_pago IS NULL AND p.detalle IN ("Diseño", "ajuste", "personalización")' . $whereFecha . '
              GROUP BY p._id
          ';
    }
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

    $queryParams = $request->getQueryParams();
    $fechaInicio = $queryParams['fecha_inicio'] ?? null;
    $fechaFin = $queryParams['fecha_fin'] ?? null;

    $whereFecha = "";
    if (DB_DRIVER === 'pgsql') {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (COALESCE(b.fecha_terminado, a.moment)::date BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    } else {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (DATE(COALESCE(b.fecha_terminado, a.moment)) BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    }

    $id_empresa = intval(str_replace('api_emp_', '', LOCAL_DB));

    if (DB_DRIVER === 'pgsql') {
      // --- 1. Consulta para empleados con COMISIÓN FIJA (PostgreSQL) ---
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
                      COALESCE(TO_CHAR(b.fecha_terminado, 'Dy'), TO_CHAR(a.moment, 'Dy')) AS dia,
                      COALESCE(TO_CHAR(b.fecha_terminado, 'IW'), TO_CHAR(a.moment, 'IW')) AS semana,
                      COALESCE(TO_CHAR(b.fecha_terminado, 'DD/MM/YY'), TO_CHAR(a.moment, 'DD/MM/YY')) AS fecha,
                      a.fecha_pago,
                      COALESCE(to_char(b.fecha_terminado - b.fecha_inicio, 'HH24:MI:SS'), '') AS tiempo_transcurrido,
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
                      AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

      // --- 2. Consulta para empleados con COMISIÓN VARIABLE (PostgreSQL) ---
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
                          COALESCE(TO_CHAR(b.fecha_terminado, 'Dy'), TO_CHAR(a.moment, 'Dy')) AS dia,
                          COALESCE(TO_CHAR(b.fecha_terminado, 'IW'), TO_CHAR(a.moment, 'IW')) AS semana,
                          COALESCE(TO_CHAR(b.fecha_terminado, 'DD/MM/YY'), TO_CHAR(a.moment, 'DD/MM/YY')) AS fecha,
                          a.fecha_pago,
                          COALESCE(to_char(b.fecha_terminado - b.fecha_inicio, 'HH24:MI:SS'), '') AS tiempo_transcurrido,
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
                          AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

      // --- 3. Consulta para empleados con COMISIÓN PORCENTAJE (PostgreSQL) ---
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
                          COALESCE(TO_CHAR(b.fecha_terminado, 'Dy'), TO_CHAR(a.moment, 'Dy')) AS dia,
                          COALESCE(TO_CHAR(b.fecha_terminado, 'IW'), TO_CHAR(a.moment, 'IW')) AS semana,
                          COALESCE(TO_CHAR(b.fecha_terminado, 'DD/MM/YY'), TO_CHAR(a.moment, 'DD/MM/YY')) AS fecha,
                          a.fecha_pago,
                          COALESCE(to_char(b.fecha_terminado - b.fecha_inicio, 'HH24:MI:SS'), '') AS tiempo_transcurrido,
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
                          AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

      // --- 4. Consulta para ESTRICTAMENTE ADMINISTRATIVOS (PostgreSQL) ---
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
                          (SELECT numero_semana FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                          (SELECT EXTRACT(YEAR FROM pa.moment) FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                          (SELECT TO_CHAR(pa.fecha_pago, 'DD/MM/YYYY') FROM pagos pa LEFT JOIN pagos_salarios ps ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario AND pa.fecha_pago IS NOT NULL AND (EXTRACT(WEEK FROM pa.fecha_pago) = EXTRACT(WEEK FROM CURRENT_DATE) OR ps.numero_semana = EXTRACT(WEEK FROM CURRENT_DATE)) AND EXTRACT(YEAR FROM pa.fecha_pago) = EXTRACT(YEAR FROM CURRENT_DATE) ORDER BY pa.fecha_pago DESC LIMIT 1) ultima_fecha_pago
                      FROM
                          api_empresas.empresas_usuarios eu
                      WHERE
                          eu.id_empresa = {$id_empresa}
                          AND eu.salario_monto > 0
                          AND eu.activo = 1";
    } else {
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
                      COALESCE(DATE_FORMAT(b.fecha_terminado, '%a'), DATE_FORMAT(a.moment, '%a')) AS dia,
                      COALESCE(DATE_FORMAT(b.fecha_terminado, '%v'), DATE_FORMAT(a.moment, '%v')) AS semana,
                      COALESCE(DATE_FORMAT(b.fecha_terminado, '%d/%m/%y'), DATE_FORMAT(a.moment, '%d/%m/%y')) AS fecha,
                      a.fecha_pago,
                      COALESCE(TIMEDIFF(b.fecha_terminado, b.fecha_inicio), '') AS tiempo_transcurrido,
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
                      AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

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
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%a'), DATE_FORMAT(a.moment, '%a')) AS dia,
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%v'), DATE_FORMAT(a.moment, '%v')) AS semana,
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%d/%m/%y'), DATE_FORMAT(a.moment, '%d/%m/%y')) AS fecha,
                          a.fecha_pago,
                          COALESCE(TIMEDIFF(b.fecha_terminado, b.fecha_inicio), '') AS tiempo_transcurrido,
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
                          AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

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
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%a'), DATE_FORMAT(a.moment, '%a')) AS dia,
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%v'), DATE_FORMAT(a.moment, '%v')) AS semana,
                          COALESCE(DATE_FORMAT(b.fecha_terminado, '%d/%m/%y'), DATE_FORMAT(a.moment, '%d/%m/%y')) AS fecha,
                          a.fecha_pago,
                          COALESCE(TIMEDIFF(b.fecha_terminado, b.fecha_inicio), '') AS tiempo_transcurrido,
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
                          AND (a.id_lotes_detalles IS NOT NULL OR (a.detalle NOT IN ('Comercialización', 'Diseño') AND a.id_orden > 0)) {$whereFecha}";

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
                          (SELECT YEAR(pa.moment) FROM " . LOCAL_DB . ".pagos_salarios ps JOIN " . LOCAL_DB . ".pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                          (SELECT DATE_FORMAT(pa.fecha_pago, '%d/%m/%Y') FROM " . LOCAL_DB . ".pagos pa LEFT JOIN " . LOCAL_DB . ".pagos_salarios ps ON ps.id_pago = pa._id WHERE pa.id_empleado = eu.id_usuario AND pa.fecha_pago IS NOT NULL AND (WEEK(pa.fecha_pago, 1) = WEEK(NOW(), 1) OR ps.numero_semana = WEEK(NOW(), 1)) AND YEAR(pa.fecha_pago) = YEAR(NOW()) ORDER BY pa.fecha_pago DESC LIMIT 1) ultima_fecha_pago
                      FROM
                          api_empresas.empresas_usuarios eu
                      WHERE
                          eu.id_empresa = {$id_empresa}
                          AND eu.salario_monto > 0
                          AND eu.activo = 1";
    }

    $pagos_fijos = $localConnection->goQuery($sql_fija);
    if (!is_array($pagos_fijos) || (isset($pagos_fijos['status']) && $pagos_fijos['status'] === 'error')) {
      $pagos_fijos = [];
    }

    $pagos_variables = $localConnection->goQuery($sql_variable);
    if (!is_array($pagos_variables) || (isset($pagos_variables['status']) && $pagos_variables['status'] === 'error')) {
      $pagos_variables = [];
    }

    $pagos_porcentaje = $localConnection->goQuery($sql_porcentaje);
    if (!is_array($pagos_porcentaje) || (isset($pagos_porcentaje['status']) && $pagos_porcentaje['status'] === 'error')) {
      $pagos_porcentaje = [];
    }

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

    $queryParams = $request->getQueryParams();
    $fechaInicio = $queryParams['fecha_inicio'] ?? null;
    $fechaFin = $queryParams['fecha_fin'] ?? null;

    $whereFecha = "";
    if (DB_DRIVER === 'pgsql') {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (a.moment::date BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    } else {
      if (!empty($fechaInicio) && !empty($fechaFin)) {
        $fechaInicioSan = preg_replace('/[^0-9\-]/', '', $fechaInicio);
        $fechaFinSan = preg_replace('/[^0-9\-]/', '', $fechaFin);
        $whereFecha = " AND (DATE(a.moment) BETWEEN '{$fechaInicioSan}' AND '{$fechaFinSan}')";
      }
    }

    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT
                  a._id AS id_pago,
                  a.id_orden,
                  a.id_empleado,
                  a.detalle AS departamento,
                  a.cantidad,
                  a.monto_pago AS pago,
                  a.comision,
                  a.comision_tipo,
                  c.salario_tipo,
                  c.nombre,
                  d.pago_abono monto_abonado,
                  e.monto monto_abonado_abono,
                  d.status,
                  e.tipo_de_pago,
                  a.fecha_pago,
                  (SELECT numero_semana FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = a.id_empleado ORDER by pa.moment DESC LIMIT 1) ultima_semana_pagada,
                  (SELECT EXTRACT(YEAR FROM pa.moment) FROM pagos_salarios ps JOIN pagos pa ON ps.id_pago = pa._id WHERE pa.id_empleado = a.id_empleado ORDER by pa.moment DESC LIMIT 1) ultimo_anio_pagado,
                  TO_CHAR(a.moment, 'DD/MM/YYYY') fecha_de_pago,
                  (SELECT CASE WHEN a.id_reposicion > 0 THEN (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion) ELSE (SELECT COALESCE(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden) END) as cantidad_productos
              FROM
                  pagos a
              JOIN api_empresas.empresas_usuarios c
              ON
                  a.id_empleado = c.id_usuario
              JOIN ordenes d ON
                  a.id_orden = d._id
              LEFT JOIN metodos_de_pago e ON
                  e._id = a.id_metodos_de_pago
              WHERE
                  a.fecha_pago IS NULL AND d.status != 'cancelada' AND a.detalle = 'Comercialización' {$whereFecha}
              GROUP BY
                  a._id, c.salario_tipo, c.nombre, d.pago_abono, e.monto, d.status, e.tipo_de_pago, d._id
              ORDER BY
                  d._id ASC,
                  a._id DESC";
    } else {
      $sql = "SELECT
                  a._id AS id_pago,
                  a.id_orden,
                  a.id_empleado,
                  a.detalle AS departamento,
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
                  DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
                  (SELECT IF(a.id_reposicion > 0, (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion), (SELECT IFNULL(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden))) as cantidad_productos
              FROM
                  pagos a
              JOIN api_empresas.empresas_usuarios c
              ON
                  a.id_empleado = c.id_usuario
              JOIN ordenes d ON
                  a.id_orden = d._id
              LEFT JOIN metodos_de_pago e ON
                  e._id = a.id_metodos_de_pago
              WHERE
                  a.fecha_pago IS NULL AND d.status != 'cancelada' AND a.detalle = 'Comercialización' {$whereFecha}
              GROUP BY
                  a._id
              ORDER BY
                  d._id ASC,
                  a._id DESC";
    }

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

    if (DB_DRIVER === 'pgsql') {
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
                  d.cliente_nombre,
                  d.pago_abono monto_abonado,
                  e.monto monto_abonado_abono,
                  d.status,
                  e.tipo_de_pago,
                  e.metodo_pago,
                  e.moneda,
                  a.fecha_pago,
                  TO_CHAR(a.moment, 'DD/MM/YYYY') fecha_de_pago,
                  a.moment AS moment_raw,
                  (SELECT CASE WHEN a.id_reposicion > 0 THEN (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion) ELSE (SELECT COALESCE(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden) END) as cantidad_productos
              FROM
                  pagos a
              JOIN api_empresas.empresas_usuarios c
              ON
                  a.id_empleado = c.id_usuario
              JOIN ordenes d ON
                  a.id_orden = d._id
              LEFT JOIN metodos_de_pago e ON
                  e._id = a.id_metodos_de_pago
              WHERE
                  a.id_empleado = {$args['id_vendedoor']} AND
                  a.fecha_pago IS NULL
              GROUP BY
                  a._id, c.nombre, d.cliente_nombre, d.pago_abono, e.monto, d.status, e.tipo_de_pago, e.metodo_pago, e.moneda, d._id
              ORDER BY
                  d._id ASC,
                  a._id DESC";
    } else {
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
                  d.cliente_nombre,
                  d.pago_abono monto_abonado,
                  e.monto monto_abonado_abono,
                  d.status,
                  e.tipo_de_pago,
                  e.metodo_pago,
                  e.moneda,
                  a.fecha_pago,
                  DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
                  a.moment AS moment_raw,
                  (SELECT IF(a.id_reposicion > 0, (SELECT unidades FROM reposiciones WHERE _id = a.id_reposicion), (SELECT IFNULL(SUM(cantidad), 0) FROM ordenes_productos op WHERE op.id_orden = a.id_orden))) as cantidad_productos
              FROM
                  pagos a
              JOIN api_empresas.empresas_usuarios c
              ON
                  a.id_empleado = c.id_usuario
              JOIN ordenes d ON
                  a.id_orden = d._id
              LEFT JOIN metodos_de_pago e ON
                  e._id = a.id_metodos_de_pago
              WHERE
                  a.id_empleado = {$args['id_vendedoor']} AND
                  a.fecha_pago IS NULL
              GROUP BY
                  a._id
              ORDER BY
                  d._id ASC,
                  a._id DESC";
    }

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
      if (DB_DRIVER === 'pgsql') {
        $whereFecha = "AND a.fecha_pago::date BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
        $whereFechaP = "AND p.fecha_pago::date BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      } else {
        $whereFecha = "AND DATE(a.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
        $whereFechaP = "AND DATE(p.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      }
    } else {
      // Sin filtro: mostrar la última fecha de pago procesada
      $sqlUltimaFecha = "SELECT MAX(fecha_pago) AS ultima_fecha FROM pagos WHERE id_empleado = {$idEmpleado}";
      $resUltimaFecha = $localConnection->goQuery($sqlUltimaFecha);
      $ultimaFecha = (is_array($resUltimaFecha) && !empty($resUltimaFecha[0]['ultima_fecha']))
        ? $resUltimaFecha[0]['ultima_fecha']
        : date('Y-m-d');
      if (DB_DRIVER === 'pgsql') {
        $whereFecha = "AND a.fecha_pago::date = '{$ultimaFecha}'::date";
        $whereFechaP = "AND p.fecha_pago::date = '{$ultimaFecha}'::date";
      } else {
        $whereFecha = "AND DATE(a.fecha_pago) = DATE('{$ultimaFecha}')";
        $whereFechaP = "AND DATE(p.fecha_pago) = DATE('{$ultimaFecha}')";
      }
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
          (SELECT opx.name FROM lotes_detalles_empleados_asignados ldea JOIN lotes_detalles ld ON ldea.id_lotes_detalles = ld._id JOIN ordenes_productos opx ON ld.id_ordenes_productos = opx._id WHERE ldea._id = a.id_lotes_detalles LIMIT 1),
          (SELECT opy.name FROM ordenes_productos opy JOIN products p ON opy.id_woo = p._id WHERE opy.id_orden = a.id_orden AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL) LIMIT 1),
          (SELECT opz.name FROM ordenes_productos opz WHERE opz.id_orden = a.id_orden LIMIT 1),
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
    if ($pendiente) {
      $bonosRes = [];
    } else {
      $sqlBonos = "SELECT pa.descripcion, SUM(pa.monto) AS monto
        FROM pagos_abonos pa
        JOIN pagos p ON pa.id_pago = p._id
        WHERE p.id_empleado = {$idEmpleado} {$whereFechaP}
        GROUP BY pa.descripcion";
      $bonosRes = $localConnection->goQuery($sqlBonos);
      if (!is_array($bonosRes))
        $bonosRes = [];
    }

    // 6. Descuentos del periodo
    if ($pendiente) {
      $descuentosRes = [];
    } else {
      $sqlDescuentos = "SELECT pd.descripcion, SUM(pd.monto) AS monto
        FROM pagos_descuentos pd
        JOIN pagos p ON pd.id_pago = p._id
        WHERE p.id_empleado = {$idEmpleado} {$whereFechaP}
        GROUP BY pd.descripcion";
      $descuentosRes = $localConnection->goQuery($sqlDescuentos);
      if (!is_array($descuentosRes))
        $descuentosRes = [];
    }

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
    $localConnection = new LocalDB();
    $queryParams = $request->getQueryParams();

    // Preferir filtro por rango de fechas (consistente con reporte-empleado).
    // Fallback a numero_semana para compatibilidad con llamadas sin fecha.
    $fechaInicio = isset($queryParams['fecha_inicio']) ? $queryParams['fecha_inicio'] : null;
    $fechaFin    = isset($queryParams['fecha_fin'])    ? $queryParams['fecha_fin']    : null;

    if (DB_DRIVER === 'pgsql') {
      if ($fechaInicio && $fechaFin) {
        $whereFechaPago  = "a.fecha_pago::date BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
        $whereFechaPagoP = "p.fecha_pago::date BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      } else {
        $semana = intval($args['semana']);
        $anioActual = intval(date('Y'));
        $whereFechaPago  = "ps.numero_semana = {$semana} AND EXTRACT(YEAR FROM a.fecha_pago) = {$anioActual}";
        $whereFechaPagoP = "ps.numero_semana = {$semana} AND EXTRACT(YEAR FROM p.fecha_pago) = {$anioActual}";
      }
    } else {
      if ($fechaInicio && $fechaFin) {
        $whereFechaPago  = "DATE(a.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
        $whereFechaPagoP = "DATE(p.fecha_pago) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      } else {
        $semana = intval($args['semana']);
        $anioActual = intval(date('Y'));
        $whereFechaPago  = "ps.numero_semana = {$semana} AND YEAR(a.fecha_pago) = {$anioActual}";
        $whereFechaPagoP = "ps.numero_semana = {$semana} AND YEAR(p.fecha_pago) = {$anioActual}";
      }
    }

    // PAGOS VENDEDORES
    if (DB_DRIVER === 'pgsql') {
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
          TO_CHAR(a.moment, 'DD/MM/YYYY') fecha_de_pago
          FROM
          pagos a
          JOIN
          api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
          JOIN
          ordenes d ON a.id_orden = d._id
          LEFT JOIN
          metodos_de_pago e ON e._id = a.id_metodos_de_pago
          LEFT JOIN
          pagos_salarios ps ON ps.id_pago = a._id
          WHERE {$whereFechaPago} AND a.fecha_pago IS NOT NULL AND a.detalle = 'Comercialización'
          GROUP BY
          a._id, c.nombre, d.pago_abono, e.monto, d.status, e.tipo_de_pago, d._id
          ORDER BY
          d._id ASC, a._id DESC;
          ";
    } else {
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
          DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago
          FROM
          pagos a
          JOIN
          api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
          JOIN
          ordenes d ON a.id_orden = d._id
          LEFT JOIN
          metodos_de_pago e ON e._id = a.id_metodos_de_pago
          LEFT JOIN
          pagos_salarios ps ON ps.id_pago = a._id
          WHERE {$whereFechaPago} AND a.fecha_pago IS NOT NULL AND a.detalle = 'Comercialización'
          GROUP BY
          a._id
          ORDER BY
          d._id ASC, a._id DESC;
          ";
    }
    $object['sql_pagos_vendedores'] = $sql;
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    // CONSULTAS ADICIONALES PARA DETALLES DE RECIBO (Salarios, Bonos, Descuentos)

    // 1. Salarios
    if (DB_DRIVER === 'pgsql') {
      $sqlSalarios = "SELECT
          SUM(ps.monto) as monto,
          ps.tipo_salario,
          p.id_empleado
          FROM pagos_salarios ps
          JOIN pagos p ON ps.id_pago = p._id
          WHERE {$whereFechaPagoP} AND p.fecha_pago IS NOT NULL
          GROUP BY p.id_empleado, ps.tipo_salario";
    } else {
      $sqlSalarios = "SELECT
          SUM(ps.monto) as monto,
          ps.tipo_salario,
          p.id_empleado
          FROM pagos_salarios ps
          JOIN pagos p ON ps.id_pago = p._id
          WHERE {$whereFechaPagoP} AND p.fecha_pago IS NOT NULL
          GROUP BY p.id_empleado";
    }

    $salariosData = $localConnection->goQuery($sqlSalarios);
    $object['data']['salarios_detalles'] = $salariosData ?: [];

    // 2. Bonos (Abonos extra)
    $sqlBonos = "SELECT
        pa.monto,
        pa.descripcion,
        p.id_empleado
        FROM pagos_abonos pa
        JOIN pagos p ON pa.id_pago = p._id
        LEFT JOIN pagos_salarios ps ON ps.id_pago = p._id
        WHERE {$whereFechaPagoP} AND p.fecha_pago IS NOT NULL";

    $bonosData = $localConnection->goQuery($sqlBonos);
    $object['data']['bonos_detalles'] = $bonosData ?: [];

    // 3. Descuentos
    $sqlDescuentos = "SELECT
        pd.monto,
        pd.descripcion,
        p.id_empleado
        FROM pagos_descuentos pd
        JOIN pagos p ON pd.id_pago = p._id
        LEFT JOIN pagos_salarios ps ON ps.id_pago = p._id
        WHERE {$whereFechaPagoP} AND p.fecha_pago IS NOT NULL";

    $descuentosData = $localConnection->goQuery($sqlDescuentos);
    $object['data']['descuentos_detalles'] = $descuentosData ?: [];


    // PAGOS EMPLEADOS
    if (DB_DRIVER === 'pgsql') {
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
              TO_CHAR(b.fecha_terminado, \'Dy\') dia,
              TO_CHAR(b.fecha_terminado, \'IW\') semana,
              TO_CHAR(b.fecha_terminado, \'DD/MM/YY\') fecha,
              a.fecha_pago,
              to_char(b.fecha_terminado - b.fecha_inicio, \'HH24:MI:SS\') tiempo_transcurrido
              FROM
              pagos a
              LEFT JOIN lotes_detalles_empleados_asignados b ON a.id_lotes_detalles = b._id
              JOIN api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
              LEFT JOIN pagos_salarios ps ON ps.id_pago = a._id
              WHERE ' . $whereFechaPago . ' AND a.fecha_pago IS NOT NULL
              AND a.detalle NOT IN (\'Comercialización\', \'Diseño\', \'ajuste\', \'personalización\')
              ORDER BY
              c.nombre ASC,
              a.id_orden ASC,
              a._id ASC;';
    } else {
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
              LEFT JOIN pagos_salarios ps ON ps.id_pago = a._id
              WHERE ' . $whereFechaPago . ' AND a.fecha_pago IS NOT NULL
              AND a.detalle NOT IN (\'Comercialización\', \'Diseño\', \'ajuste\', \'personalización\')
              ORDER BY
              c.nombre ASC,
              a.id_orden ASC,
              a._id ASC;';
    }
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    $object['sql_pagos_empleados'] = $sql;

    // DISENADORES
    if (DB_DRIVER === 'pgsql') {
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
          LEFT JOIN pagos_salarios ps ON ps.id_pago = p._id
          WHERE ' . $whereFechaPagoP . ' AND p.fecha_pago IS NOT NULL
          GROUP BY p._id, p.id_orden, r._id, p.monto_pago, p.id_empleado, p.fecha_pago, r.id_product
          ';
    } else {
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
          LEFT JOIN pagos_salarios ps ON ps.id_pago = p._id
          WHERE ' . $whereFechaPagoP . ' AND p.fecha_pago IS NOT NULL
          GROUP BY p._id
          ';
    }
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

    $paramsWhere = [];
    $paramsWhereEmpleados = [];

    if (isset($data['numero_semana'])) {
      if (DB_DRIVER === 'pgsql') {
        $where = 'EXTRACT(WEEK FROM e.moment) = ? AND e.fecha_pago IS NULL';
      } else {
        $where = 'WEEK(e.moment, 1) = ? AND e.fecha_pago IS NULL';
      }
      $paramsWhere[] = $data['numero_semana'];
      $whereEmpleados = DB_DRIVER === 'pgsql' ? 'b.fecha_terminado::text LIKE ? AND a.fecha_pago IS NULL ' : 'b.fecha_terminado LIKE ? AND a.fecha_pago IS NULL ';
      $paramsWhereEmpleados[] = $data['fecha_inicio'] . '%';
    }

    if ($data['fecha_inicio'] === $data['fecha_fin']) {
      $where = DB_DRIVER === 'pgsql' ? 'e.moment::text LIKE ? AND e.fecha_pago IS NULL' : 'e.moment LIKE ? AND e.fecha_pago IS NULL';
      $paramsWhere = [$data['fecha_inicio'] . '%'];
      $whereEmpleados = DB_DRIVER === 'pgsql' ? 'b.fecha_terminado::text LIKE ? AND a.fecha_pago IS NULL ' : 'b.fecha_terminado LIKE ? AND a.fecha_pago IS NULL ';
      $paramsWhereEmpleados = [$data['fecha_inicio'] . '%'];
    } else {
      if (DB_DRIVER === 'pgsql') {
        $where = "(e.moment::date BETWEEN ? AND ?) ";
        $paramsWhere = [$data['fecha_inicio'], $data['fecha_fin']];
        $whereEmpleados = "b.fecha_inicio >= ? AND (b.fecha_terminado - INTERVAL '1 day')::date <= ? ";
        $paramsWhereEmpleados = [$data['fecha_inicio'], $data['fecha_fin']];
      } else {
        $where = '(DATE(e.moment) BETWEEN ? AND ?) ';
        $paramsWhere = [$data['fecha_inicio'], $data['fecha_fin']];
        $whereEmpleados = 'b.fecha_inicio >= ? AND DATE_ADD(b.fecha_terminado, INTERVAL -1 DAY) <= ? ';
        $paramsWhereEmpleados = [$data['fecha_inicio'], $data['fecha_fin']];
      }
    }

    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, TO_CHAR(a.moment, 'DD/MM/YYYY') fecha_de_pago FROM pagos a JOIN api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    } else {
      $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    }
    $object['data']['vendedores'] = $localConnection->goQuery($sql, $paramsWhere);
    // FIN BUSCAR PAGOS DE VENDEDORES

    // OBTENER PAGOS DE EMPLEADOS
    if (DB_DRIVER === 'pgsql') {
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
              TO_CHAR(b.fecha_terminado, \'Dy\') dia,
              TO_CHAR(b.fecha_terminado, \'IW\') semana,
              TO_CHAR(b.fecha_terminado, \'DD/MM/YY\') fecha,
              a.cantidad cantidad,
              a.monto_pago pago,
              a.fecha_pago,
              to_char(b.fecha_terminado - b.fecha_inicio, \'HH24:MI:SS\') tiempo_transcurrido
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
    } else {
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
    }

    $object['sql']['empleados'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql, $paramsWhereEmpleados);
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
    $object['data']['diseno'] = $localConnection->goQuery($sql, $paramsWhere);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ?';
      $tmpResp = $localConnection->goQuery($sqlTMP, [$value['id_orden']]);
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
