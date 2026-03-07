<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


    // =================================================================
    // REPORTE DE COSTOS DE PRODUCCIÓN
    // =================================================================
    $app->get('/reportes/costos-produccion/{inicio}/{fin}', function (Request $request, Response $response, array $args) {
        $inicio = $args['inicio'] ?? null;
        $fin = $args['fin'] ?? null;
        $id_empresa = ID_EMPRESA;

        $finalResponse = [];

        try {
            // --- 1. Consulta del Reporte Principal (Base de datos de la empresa) ---
            $db = new LocalDB();
            $whereConditions = [];
            $params = [];

            if ($inicio && $fin) {
                $whereConditions[] = 'DATE(a.moment) BETWEEN ? AND ?';
                $params[] = $inicio;
                $params[] = $fin;
            }

            // Filtrar solo órdenes terminadas o entregadas (con datos completos de producción)
            $whereConditions[] = "a.status IN ('terminada', 'entregada')";

            $sqlSalarios = "SELECT id_usuario id_empleado, salario_monto, salario_periodo, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_empresa = $id_empresa";
            $salariosData = $db->goQuery($sqlSalarios);
            $finalResponse['salarios_data'] = $salariosData;


            $sqlReporte = "SELECT
            a._id AS id_orden,
            (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = a.responsable) vendedor,
            COALESCE(prod.total_productos, 0) AS total_productos,
            a.pago_total,
            COALESCE(ins.costos_de_insumos, 0) AS costos_de_insumos,
            COALESCE(mano_de_obra.costo_mano_de_obra, 0) AS costo_mano_de_obra,
            (COALESCE(ins.costos_de_insumos, 0) + COALESCE(mano_de_obra.costo_mano_de_obra, 0)) AS costo_total,
            (a.pago_total - (COALESCE(ins.costos_de_insumos, 0) + COALESCE(mano_de_obra.costo_mano_de_obra, 0))) AS ganancia,
            
            COALESCE(tiempo.empleados_asignados, '') AS empleados_asignados, -- AÑADIDO: Mostrar los IDs de los empleados
            
            COALESCE(tiempo.tiempo_de_produccion, 0) AS tiempo_de_produccion,
            COALESCE(repo.total_reposiciones, 0) AS reposiciones
        FROM
            ordenes a
        LEFT JOIN (
            SELECT id_orden, SUM(cantidad) AS total_productos
            FROM ordenes_productos
            GROUP BY id_orden
        ) prod ON a._id = prod.id_orden
        LEFT JOIN (
            SELECT c.id_orden, SUM((c.valor_inicial - c.valor_final) * (d.costo / d.cantidad_inicial)) AS costos_de_insumos, GROUP_CONCAT(d.sku) AS skus
            FROM inventario_movimientos c JOIN inventario d ON c.id_insumo = d._id
            GROUP BY c.id_orden
        ) ins ON a._id = ins.id_orden
        LEFT JOIN (
            SELECT id_orden, SUM(monto_pago) AS costo_mano_de_obra
            FROM pagos
            GROUP BY id_orden
        ) mano_de_obra ON a._id = mano_de_obra.id_orden
        LEFT JOIN (
            -- MODIFICADO: Subconsulta 'tiempo' para incluir los empleados
            SELECT
                ldea.id_orden,
                GROUP_CONCAT(DISTINCT ldea.id_empleado) AS empleados_asignados, -- AÑADIDO: Agrupar los IDs de los empleados en una cadena
                api_empresas.CalcularHorasLaborales(
                    MIN(ldea.fecha_inicio),
                    MAX(ldea.fecha_terminado),
                    (SELECT horario_laboral FROM api_empresas.empresas LIMIT 1)
                ) AS tiempo_de_produccion
            FROM
                lotes_detalles_empleados_asignados ldea
            WHERE ldea.fecha_inicio IS NOT NULL AND ldea.fecha_terminado IS NOT NULL
            GROUP BY ldea.id_orden
        ) tiempo ON a._id = tiempo.id_orden

        LEFT JOIN (
            SELECT id_orden, COUNT(_id) AS total_reposiciones
            FROM reposiciones
            GROUP BY id_orden
        ) repo ON a._id = repo.id_orden
      ";

            if (!empty($whereConditions)) {
                $sqlReporte .= ' WHERE ' . implode(' AND ', $whereConditions);
            }

            $finalResponse['sqlReporte'] = $sqlReporte;
            $finalResponse['whereConditions'] = $whereConditions;
            $finalResponse['params'] = $params;

            // $reporteData = $db->goQuery($sqlReporte, $params);
            $reporteData = $db->goQuery($sqlReporte, $params);
            if (isset($reporteData['status']) && $reporteData['status'] === 'error') {
                throw new Exception('Error en la consulta del reporte: ' . $reporteData['message']);
            }
            $finalResponse['reporte_data'] = $reporteData;


            // --- Consulta de Tintas Consumidas ---
            $sqlTintas = <<<SQL
      -- Usamos tres CTEs: uno para encontrar el costo por ml de recargas, otro para fallback desde inventario, y otro para calcular el costo total.
      WITH
      -- CTE 1: Encuentra el costo por ml de la última recarga para cada tanque.
      last_ink_refill_cost AS (
          SELECT
              tr.id_catalogo_impresora,
              tr.color,
              CASE
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0
              END AS ink_cost_per_ml,
              ROW_NUMBER() OVER (PARTITION BY tr.id_catalogo_impresora, tr.color ORDER BY tr.fecha_recarga DESC) as rn
          FROM
              tintas_recargas tr
          JOIN
              inventario inv ON tr.id_insumo = inv._id
      ),
      -- CTE 2 (NUEVO): Fallback - Obtiene el costo por ml desde inventario usando tinta_filtro
      fallback_ink_cost AS (
          SELECT
              tf.color AS color_code,
              CASE
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0
              END AS ink_cost_per_ml
          FROM tinta_filtro tf
          JOIN inventario inv ON tf.id_inventario = inv._id
      ),
      -- CTE 3: Usa los CTEs anteriores para calcular el costo total de la tinta para cada orden.
      costos_por_orden AS (
          SELECT
              tin.id_orden,
              ROUND(
                  (COALESCE(tin.c, 0) * COALESCE(lic_c.ink_cost_per_ml, fic_c.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.m, 0) * COALESCE(lic_m.ink_cost_per_ml, fic_m.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.y, 0) * COALESCE(lic_y.ink_cost_per_ml, fic_y.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.k, 0) * COALESCE(lic_k.ink_cost_per_ml, fic_k.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.w, 0) * COALESCE(lic_w.ink_cost_per_ml, fic_w.ink_cost_per_ml, 0))
              , 2) AS total_tinta_costo
          FROM
              tintas tin
          -- Primero intentamos con tintas_recargas
          LEFT JOIN last_ink_refill_cost lic_c ON lic_c.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_c.color = 'C' AND lic_c.rn = 1
          LEFT JOIN last_ink_refill_cost lic_m ON lic_m.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_m.color = 'M' AND lic_m.rn = 1
          LEFT JOIN last_ink_refill_cost lic_y ON lic_y.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_y.color = 'Y' AND lic_y.rn = 1
          LEFT JOIN last_ink_refill_cost lic_k ON lic_k.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_k.color = 'K' AND lic_k.rn = 1
          LEFT JOIN last_ink_refill_cost lic_w ON lic_w.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_w.color = 'W' AND lic_w.rn = 1
          -- Fallback: usamos inventario directamente si no hay recargas
          LEFT JOIN fallback_ink_cost fic_c ON fic_c.color_code = 'C'
          LEFT JOIN fallback_ink_cost fic_m ON fic_m.color_code = 'M'
          LEFT JOIN fallback_ink_cost fic_y ON fic_y.color_code = 'Y'
          LEFT JOIN fallback_ink_cost fic_k ON fic_k.color_code = 'K'
          LEFT JOIN fallback_ink_cost fic_w ON fic_w.color_code = 'W'
          GROUP BY tin.id_orden
      )

      -- Consulta Final: Unimos tu consulta original con nuestros costos calculados.
      SELECT
          imo.id_orden,
          imo.c AS cyan,
          imo.m AS magenta,
          imo.y AS yellow,
          imo.k AS black,
          (COALESCE(imo.c, 0) + COALESCE(imo.m, 0) + COALESCE(imo.y, 0) + COALESCE(imo.k, 0) + COALESCE(imo.w, 0)) AS total_tinta_consumo_ml,
          cpo.total_tinta_costo
      FROM
          tintas imo
      LEFT JOIN
          costos_por_orden cpo ON imo.id_orden = cpo.id_orden
      WHERE DATE(imo.moment) BETWEEN ? AND ?
      ORDER BY
          imo.id_orden ASC
      SQL;

            $tintasData = $db->goQuery($sqlTintas, [$inicio, $fin]);
            $finalResponse['tintas_consumidas'] = $tintasData;

            // --- Consulta Resumida de Tintas (solo totales por orden) ---
            $sqlTintasResumen = <<<SQL
      -- Usamos tres CTEs: uno para recargas, otro para fallback desde inventario, y otro para calcular el costo total.
      WITH
      -- CTE 1: Encuentra el costo por ml de la última recarga para cada tanque.
      last_ink_refill_cost AS (
          SELECT
              tr.id_catalogo_impresora,
              tr.color,
              CASE
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0
              END AS ink_cost_per_ml,
              ROW_NUMBER() OVER (PARTITION BY tr.id_catalogo_impresora, tr.color ORDER BY tr.fecha_recarga DESC) as rn
          FROM
              tintas_recargas tr
          JOIN
              inventario inv ON tr.id_insumo = inv._id
      ),
      -- CTE 2 (NUEVO): Fallback - Obtiene el costo por ml desde inventario usando tinta_filtro
      fallback_ink_cost AS (
          SELECT
              tf.color AS color_code,
              CASE
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0
              END AS ink_cost_per_ml
          FROM tinta_filtro tf
          JOIN inventario inv ON tf.id_inventario = inv._id
      ),
      -- CTE 3: Usa los CTEs anteriores para calcular el costo total de la tinta para cada orden.
      costos_por_orden AS (
          SELECT
              tin.id_orden,
              ROUND(
                  (COALESCE(tin.c, 0) * COALESCE(lic_c.ink_cost_per_ml, fic_c.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.m, 0) * COALESCE(lic_m.ink_cost_per_ml, fic_m.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.y, 0) * COALESCE(lic_y.ink_cost_per_ml, fic_y.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.k, 0) * COALESCE(lic_k.ink_cost_per_ml, fic_k.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.w, 0) * COALESCE(lic_w.ink_cost_per_ml, fic_w.ink_cost_per_ml, 0))
              , 2) AS total_tinta_costo
          FROM
              tintas tin
          LEFT JOIN last_ink_refill_cost lic_c ON lic_c.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_c.color = 'C' AND lic_c.rn = 1
          LEFT JOIN last_ink_refill_cost lic_m ON lic_m.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_m.color = 'M' AND lic_m.rn = 1
          LEFT JOIN last_ink_refill_cost lic_y ON lic_y.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_y.color = 'Y' AND lic_y.rn = 1
          LEFT JOIN last_ink_refill_cost lic_k ON lic_k.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_k.color = 'K' AND lic_k.rn = 1
          LEFT JOIN last_ink_refill_cost lic_w ON lic_w.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_w.color = 'W' AND lic_w.rn = 1
          -- Fallback desde inventario
          LEFT JOIN fallback_ink_cost fic_c ON fic_c.color_code = 'C'
          LEFT JOIN fallback_ink_cost fic_m ON fic_m.color_code = 'M'
          LEFT JOIN fallback_ink_cost fic_y ON fic_y.color_code = 'Y'
          LEFT JOIN fallback_ink_cost fic_k ON fic_k.color_code = 'K'
          LEFT JOIN fallback_ink_cost fic_w ON fic_w.color_code = 'W'
          GROUP BY tin.id_orden
      )

      -- Consulta Final Resumida: Solo totales por orden
      SELECT
          imo.id_orden,
          (COALESCE(imo.c, 0) + COALESCE(imo.m, 0) + COALESCE(imo.y, 0) + COALESCE(imo.k, 0) + COALESCE(imo.w, 0)) AS total_tinta_consumo_ml,
          cpo.total_tinta_costo
      FROM
          tintas imo
      LEFT JOIN
          costos_por_orden cpo ON imo.id_orden = cpo.id_orden
      WHERE DATE(imo.moment) BETWEEN ? AND ?
      ORDER BY
          imo.id_orden ASC
      SQL;

            $tintasResumenData = $db->goQuery($sqlTintasResumen, [$inicio, $fin]);
            $finalResponse['tintas_resumen'] = $tintasResumenData;

            // --- Consulta de Insumos Consumidos Resumen ---
            $sqlInsumosResumen = "
      SELECT
        a.id_orden,
          ((a.valor_inicial - a.valor_final - b.desperdicio) / ((a.valor_inicial - a.valor_final) / 100)) eficiencia
      FROM inventario_movimientos a
      JOIN rendimiento b ON a.id_insumo = b.id_orden
      WHERE DATE(a.fecha) BETWEEN ? AND ?
      GROUP BY a.id_orden
      ORDER BY a.id_orden ASC;
      ";

            $insumosResumenData = $db->goQuery($sqlInsumosResumen, [$inicio, $fin]);
            $finalResponse['insumos_resumen'] = $insumosResumenData;

            // --- Consulta de Insumos Consumidos Detalles ---
            $sqlInsumosResumenDetalles = "
      SELECT
            a._id id_inventario_movimientos,
            a.id_orden,
          a.id_producto,
            d.product producto,
            c.insumo,
            ((a.valor_inicial - a.valor_final - b.desperdicio) / ((a.valor_inicial - a.valor_final) / 100)) eficiencia
        FROM
          inventario_movimientos a
            LEFT JOIN rendimiento b ON a.id_orden = b.id_orden 
            JOIN inventario c ON a.id_insumo = c._id 
            JOIN products d ON a.id_producto = d._id
        WHERE DATE(a.fecha) BETWEEN ? AND ?
        ORDER BY a.id_orden ASC;
      ";

            $insumosResumenDataDetalles = $db->goQuery($sqlInsumosResumenDetalles, [$inicio, $fin]);
            $finalResponse['insumos_detalles'] = $insumosResumenDataDetalles;

            // Consulta de tareas de empleados (debe ir ANTES del disconnect)
            $sqlHorarioEmpleados = "SELECT
            a.id_orden,
            a.id_empleado,
            a.fecha_inicio,
            a.fecha_terminado,
            TIME_TO_SEC(TIMEDIFF(a.fecha_terminado, a.fecha_inicio)) / 60 AS minutos_transcurridos
        FROM
            lotes_detalles_empleados_asignados a
        WHERE
            a.fecha_inicio IS NOT NULL AND a.fecha_terminado IS NOT NULL;
      ";
            $horarioEmpleadosResult = $db->goQuery($sqlHorarioEmpleados);
            $finalResponse['tareas_data'] = $horarioEmpleadosResult;

            $db->disconnect();

            // --- 2. Consulta de Gastos Fijos (Base de datos de empresas) ---
            $dbEmpresas = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $sqlGastos = "SELECT SUM(
                        CASE periodicidad
                            WHEN 'mensual' THEN monto / 4.33
                            WHEN 'trimestral' THEN monto / 13
                            WHEN 'semestral' THEN monto / 26
                            WHEN 'anual' THEN monto / 52 
                            ELSE 0
                        END
                    ) AS total_gastos_semanales 
                    FROM empresas_gastos
                    WHERE id_empresa = ? AND estatus = 'activo'";

            $gastosResult = $dbEmpresas->goQuery($sqlGastos, [$id_empresa]);

            $sqlHorario = "SELECT
          eu.id_usuario,    
          eu.nombre,    
          eu.salario_tipo, 
            eu.salario_monto / 
            (
                (
                    (JSON_VALUE(e.horario_laboral, '$.horaFinManana') - JSON_VALUE(e.horario_laboral, '$.horaInicioManana')) + 
                    (JSON_VALUE(e.horario_laboral, '$.horaFinTarde') - JSON_VALUE(e.horario_laboral, '$.horaInicioTarde'))
                ) * JSON_LENGTH(e.horario_laboral, '$.diasLaborales') * (52 / 12)
            ) AS costo_por_hora
        FROM
            empresas_usuarios eu
        LEFT JOIN
            empresas e ON eu.id_empresa = e.id_empresa -- <-- CORREGIDO: Usando 'id_empresa' para el JOIN
        WHERE
            (eu.salario_tipo LIKE 'Salario' OR eu.salario_tipo LIKE 'Salario más Comisión') AND 
            eu.id_empresa =  $id_empresa
      ";

            // $horarioResult = $dbEmpresas->goQuery($sqlHorario, [$id_empresa]);
            $horarioResult = $dbEmpresas->goQuery($sqlHorario);
            $finalResponse['costo_hora_empleado'] = $horarioResult;


            if (isset($gastosResult['status']) && $gastosResult['status'] === 'error') {
                throw new Exception('Error en la consulta de gastos: ' . $gastosResult['message']);
            }
            $totalGastosSemanales = !empty($gastosResult) ? (float) $gastosResult[0]['total_gastos_semanales'] : 0;
            $dbEmpresas->disconnect();

            // --- 3. Cálculos combinados ---
            $totalProductosPeriodo = 0;
            foreach ($reporteData as $row) {
                $totalProductosPeriodo += $row['total_productos'];
            }

            $costoOperativoPorProducto = 0;
            if ($totalProductosPeriodo > 0) {
                $costoOperativoPorProducto = $totalGastosSemanales / $totalProductosPeriodo;
            }

            $finalResponse['costos_operativos'] = [
                'total_gastos_semanales' => $totalGastosSemanales,
                'total_productos_periodo' => $totalProductosPeriodo,
                'costo_operativo_por_producto' => $costoOperativoPorProducto
            ];

            // --- 4. Enviar respuesta ---
            $response->getBody()->write(json_encode($finalResponse, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error en la consulta del reporte: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // REPORTE DE INSUMOS CONSUMIDOS POR ORDEN
    // =================================================================
    $app->get('/reportes/insumos-consumidos-por-orden/{id_orden}', function (Request $request, Response $response, array $args) {
        $id_orden = $args['id_orden'];

        $sql = "SELECT
        a._id id_inventario_movimientos,
        a.id_orden,
        a.id_producto,
        c.product producto,
        a.id_insumo,
        b.insumo,
        (a.valor_inicial - a.valor_final) cantidad_consumida,
        ((a.valor_inicial - a.valor_final) * (b.costo / b.cantidad_inicial)) insumo_total_costo,
        b.unidad,
        a.fecha
    FROM
        inventario_movimientos a
    JOIN inventario b ON a.id_insumo = b._id
    JOIN products c ON a.id_producto = c._id
    WHERE
        a.id_orden = ?
    ORDER BY a.fecha DESC";

        try {
            $db = new LocalDB();
            $result = $db->goQuery($sql, [$id_orden]);
            $db->disconnect();

            $response->getBody()->write(json_encode(['insumos' => $result], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al obtener insumos consumidos: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // REPORTE DE MANO DE OBRA POR ORDEN
    // =================================================================
    $app->get('/reportes/mano-obra-por-orden/{id_orden}', function (Request $request, Response $response, array $args) {
        $id_orden = $args['id_orden'];

        $sql = 'SELECT
                    p.id_empleado,
                    eu.nombre AS nombre_empleado,
                    p.detalle AS departamento,
                    p.cantidad,
                    p.monto_pago
                FROM
                    pagos p
                JOIN
                    api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario
                WHERE
                    p.id_orden = ?
                ORDER BY
                    eu.nombre, p.detalle';
        $db = new LocalDB();
        $data = $db->goQuery($sql, [$id_orden]);
        $db->disconnect();
        $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    // =================================================================
    // REPORTE DE GASTOS DE FABRICACIÓN DE PRODUCTOS
    // =================================================================
    $app->get('/reportes/gastos-de-fabricacion/{fecha}', function (Request $request, Response $response, array $args) {
        $id_orden = $args['id_orden'];

        $sql = 'SELECT
                    p.id_empleado,
                    eu.nombre AS nombre_empleado,
                    p.detalle AS departamento,
                    p.cantidad,
                    p.monto_pago
                FROM
                    pagos p
                JOIN
                    api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario
                WHERE
                    p.id_orden = ?
                ORDER BY
                    eu.nombre, p.detalle';
        $db = new LocalDB();
        $data = $db->goQuery($sql, [$id_orden]);
        $db->disconnect();
        $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    // =================================================================
    // REPORTE DE MATERIAL CONSUMIDO POR ORDEN
    // =================================================================
    $app->get('/reportes/material-consumido-por-orden/{id_orden}', function (Request $request, Response $response, array $args) {
        $id_orden = $args['id_orden'];

        $sql = "SELECT
                    r.id_orden,
                    i.insumo AS material,
                    i.sku,
                    r.metros AS cantidad_consumida,
                    i.unidad,
                    r.desperdicio,
                    CASE
                        WHEN r.id_empleado_corte IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_corte)
                        WHEN r.id_empleado_impresion IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_impresion)
                        WHEN r.id_empleado_estampado IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_estampado)
                        ELSE 'No Asignado'
                    END AS empleado,
                    CASE
                        WHEN r.id_empleado_corte IS NOT NULL THEN 'Corte'
                        WHEN r.id_empleado_impresion IS NOT NULL THEN 'Impresión'
                        WHEN r.id_empleado_estampado IS NOT NULL THEN 'Estampado'
                        ELSE 'No Asignado'
                    END AS departamento
                FROM rendimiento r
                JOIN inventario i ON r.id_insumo = i._id
                WHERE r.id_orden = ?";
        $db = new LocalDB();
        $data = $db->goQuery($sql, [$id_orden]);
        $db->disconnect();
        $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
    
    // =================================================================
    // REPORTE SEMANAL DETALLADO (Para Reporte de Eficiencia)
    // =================================================================
    $app->get('/reportes/semanal-detallado', function (Request $request, Response $response) {
        $params = $request->getQueryParams();
        $inicio = $params['inicio'] ?? null;
        $fin = $params['fin'] ?? null;

        if (!$inicio || !$fin) {
            $response->getBody()->write(json_encode(['error' => 'Faltan parámetros de fecha']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = new LocalDB();
        try {
            // 1. Obtener Órdenes
            $sqlOrders = "SELECT 
                    a._id AS orden,
                    a._id AS _id,
                    CONCAT(COALESCE(cus.first_name, ''), ' ', COALESCE(cus.last_name, '')) AS cliente_nombre,
                    a.fecha_inicio AS inicio,
                    a.fecha_entrega AS entrega,
                    (SELECT CONCAT(o.fecha_entrega, ' 08:30:00') FROM ordenes o WHERE o._id = a._id) AS fecha_entrega_orden,
                    a.status AS status,
                    a.moment,
                    (
                        SELECT SUM(op.cantidad) 
                        FROM ordenes_productos op 
                        JOIN products p ON op.id_woo = p._id 
                        WHERE op.id_orden = a._id AND (p.fisico = 1 OR p.fisico IS NULL)
                    ) AS total_unidades
                FROM ordenes a
                LEFT JOIN customers cus ON cus._id = a.id_wp
                WHERE a.status IN ('terminada', 'entregada')
                AND DATE(a.moment) BETWEEN ? AND ?
                ORDER BY a._id DESC";
            
            $orders = $db->goQuery($sqlOrders, [$inicio, $fin]);
            
            if (empty($orders)) {
                $db->disconnect();
                $response->getBody()->write(json_encode(['items' => []]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            $orderIds = array_column($orders, 'orden');
            $idsString = implode(',', $orderIds);

            // 2. Obtener Tareas para el Semáforo
            $sqlTasks = "SELECT
                    ldea.id_orden AS _id,
                    ldea.id_orden,
                    ldea.id_departamento,
                    dep.departamento AS nombre_departamento,
                    dep.orden_proceso AS orden_proceso_departamento,
                    MIN(ldea.fecha_inicio) AS fecha_inicio,
                    MAX(ldea.fecha_terminado) AS fecha_terminado,
                    DATE_FORMAT(MIN(ldea.fecha_inicio), '%d/%m/%Y %h:%i %p') AS fecha_inicio_formateada,
                    DATE_FORMAT(MAX(ldea.fecha_terminado), '%d/%m/%Y %h:%i %p') AS fecha_terminado_formateada,
                    DATE_FORMAT(MIN(ldea.fecha_inicio), '%d/%m/%Y %h:%i %p') AS fecha_estimada_inicio_formateada,
                    DATE_FORMAT(MAX(ldea.fecha_terminado), '%d/%m/%Y %h:%i %p') AS fecha_estimada_fin_formateada,
                    'terminado' AS status,
                    COALESCE(COUNT(DISTINCT ldea.id_empleado), 1) as cant_empleados,
                    (
                        SELECT COALESCE(SUM(ptp.tiempo * op_sub.cantidad), 0)
                        FROM products_tiempos_de_produccion ptp
                        JOIN ordenes_productos op_sub ON op_sub.id_woo = ptp.id_product
                        WHERE op_sub.id_orden = ldea.id_orden AND ptp.id_departamento = ldea.id_departamento
                    ) AS tiempo_total_orden_depto
                FROM
                    lotes_detalles_empleados_asignados ldea
                JOIN departamentos dep ON dep._id = ldea.id_departamento
                WHERE ldea.id_orden IN ($idsString)
                GROUP BY ldea.id_orden, ldea.id_departamento
                ORDER BY ldea.id_orden, dep.orden_proceso ASC";
            
            $tasks = $db->goQuery($sqlTasks);
            
            // 3. Obtener Eficiencia de Material (Consolidado por Orden)
            $sqlMat = "SELECT 
                    op.id_orden,
                    SUM(op.cantidad * pia.cantidad) AS meta,
                    COALESCE((
                        SELECT SUM(ABS(im_sub.valor_final - im_sub.valor_inicial) * COALESCE(inv_sub.rendimiento, 1))
                        FROM inventario_movimientos im_sub
                        LEFT JOIN inventario inv_sub ON inv_sub._id = im_sub.id_insumo
                        WHERE im_sub.id_orden = op.id_orden
                    ), 0) AS `real`
                FROM ordenes_productos op
                JOIN product_insumos_asignados pia ON pia.id_product = op.id_woo AND pia.id_talla = op.id_size
                WHERE op.id_orden IN ($idsString)
                GROUP BY op.id_orden";
            $matEff = $db->goQuery($sqlMat);

            // 4. Obtener Eficiencia de Tiempo (Consolidado por Orden)
            $sqlTime = "SELECT 
                    o._id AS id_orden,
                    (
                        SELECT COALESCE(SUM(ptp.tiempo * op_sub.cantidad), 0)
                        FROM products_tiempos_de_produccion ptp
                        JOIN ordenes_productos op_sub ON op_sub.id_woo = ptp.id_product
                        WHERE op_sub.id_orden = o._id
                    ) AS projected,
                    COALESCE((
                        SELECT SUM(TIMESTAMPDIFF(SECOND, ldea.fecha_inicio, ldea.fecha_terminado))
                        FROM lotes_detalles_empleados_asignados ldea
                        WHERE ldea.id_orden = o._id AND ldea.fecha_inicio IS NOT NULL AND ldea.fecha_terminado IS NOT NULL
                    ), 0) AS `real`
                FROM ordenes o
                WHERE o._id IN ($idsString)";
            $timeEff = $db->goQuery($sqlTime);
            
            error_log("DEBUG REPORTS: orders count: " . count($orders));
            error_log("DEBUG REPORTS: tasks count: " . count($tasks));
            error_log("DEBUG REPORTS: matEff count: " . count($matEff));
            error_log("DEBUG REPORTS: timeEff count: " . count($timeEff));

            $db->disconnect();
            
            // Agrupar tareas por ID de orden para un mapeo eficiente
            $tasksGrouped = [];
            if (is_array($tasks)) {
                foreach ($tasks as $t) {
                    $id = (string)$t['id_orden'];
                    if (!isset($tasksGrouped[$id])) {
                        $tasksGrouped[$id] = [];
                    }
                    $tasksGrouped[$id][] = $t;
                }
            }

            // Helper para formatear segundos
            $formatSeconds = function($s) {
                if ($s <= 0) return '0s';
                $h = floor($s / 3600);
                $m = floor(($s % 3600) / 60);
                $sec = $s % 60;
                if ($h > 0) return $h . "h " . (int)$m . "m";
                if ($m > 0) return $m . "m " . (int)$sec . "s";
                return (int)$sec . "s";
            };

            $finalItems = [];
            foreach ($orders as $order) {
                $currentId = (string)$order['_id'];
                
                // Tareas
                $orderTasks = isset($tasksGrouped[$currentId]) ? $tasksGrouped[$currentId] : [];
                foreach ($orderTasks as &$t) {
                    $t['tiempo_total_orden_depto_formateado'] = $formatSeconds($t['tiempo_total_orden_depto']);
                    if ($t['fecha_inicio'] && $t['fecha_terminado']) {
                        $diff = strtotime($t['fecha_terminado']) - strtotime($t['fecha_inicio']);
                        $t['tiempo_real_empleado_formateado'] = $formatSeconds($diff);
                    } else {
                        $t['tiempo_real_empleado_formateado'] = null;
                    }
                }
                
                $order['id_orden'] = $currentId;
                $order['tareas'] = $orderTasks;
                unset($t);

                // Eficiencia Material
                $matMatch = array_filter($matEff, function($me) use ($currentId) { return (string)$me['id_orden'] === $currentId; });
                if (!empty($matMatch)) {
                    $mVal = array_values($matMatch)[0];
                    $order['eficiencia_material'] = ($mVal['real'] > 0) ? round(($mVal['meta'] / $mVal['real']) * 100, 2) : 100;
                    if ($mVal['meta'] == 0 && $mVal['real'] == 0) $order['eficiencia_material'] = 'N/A';
                } else {
                    $order['eficiencia_material'] = 'N/A';
                }

                // Eficiencia Tiempo
                $timeMatch = array_filter($timeEff, function($te) use ($currentId) { return (string)$te['id_orden'] === $currentId; });
                if (!empty($timeMatch)) {
                    $tVal = array_values($timeMatch)[0];
                    $order['eficiencia_tiempo'] = ($tVal['real'] > 0) ? round(($tVal['projected'] / $tVal['real']) * 100, 2) : 100;
                } else {
                    $order['eficiencia_tiempo'] = 'N/A';
                }

                $finalItems[] = $order;
            }

            $response->getBody()->write(json_encode(['items' => $finalItems]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $db->disconnect();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

}; // Fin de la función que envuelve las rutas
