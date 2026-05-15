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
        $queryParams = $request->getQueryParams();
        $debug = isset($queryParams['debug']) && (string)$queryParams['debug'] === '1';

        try {
            $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
            $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);

            $finalResponse = [];

            $whereConditions = [];
            $params = [];

            if ($inicio && $fin) {
                $whereConditions[] = 'DATE(a.moment) BETWEEN :inicio AND :fin';
                $params[':inicio'] = $inicio;
                $params[':fin'] = $fin;
            }

            // --- OPTIMIZACIÓN: Obtener horario laboral una vez para evitar subconsultas redundantes ---
            $horarioQuery = "SELECT horario_laboral FROM empresas WHERE id_empresa = ?";
            $horarioResult = $dbEmpresas->goQuery($horarioQuery, [$id_empresa]);
            
            $horario_laboral = (!empty($horarioResult) && isset($horarioResult[0]['horario_laboral'])) ? $horarioResult[0]['horario_laboral'] : null;
            // Sanitizar el JSON para usarlo como literal en SQL
            $horario_literal = $horario_laboral ? "'" . $horario_laboral . "'" : "'{}'";

            // Filtrar solo órdenes terminadas o entregadas
            $whereConditions[] = "a.status IN ('terminada', 'entregada')";

            // Obtener datos de salarios para el reporte
            $sqlSalarios = "SELECT id_usuario, id_usuario as id_empleado, nombre, salario_monto, salario_periodo, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_empresa = $id_empresa";
            $salariosData = $dbEmpresas->goQuery($sqlSalarios);
            $horarioObj = $horario_laboral ? json_decode($horario_laboral, true) : null;
            $horasDia = 0;
            $diasSemana = 0;
            if (is_array($horarioObj)) {
                $horasDia = (float)($horarioObj['horaFinManana'] ?? 0) - (float)($horarioObj['horaInicioManana'] ?? 0);
                $horasDia += (float)($horarioObj['horaFinTarde'] ?? 0) - (float)($horarioObj['horaInicioTarde'] ?? 0);
                $diasSemana = is_array($horarioObj['diasLaborales'] ?? null) ? count($horarioObj['diasLaborales']) : 0;
            }
            $horasSemana = max(0, $horasDia) * max(0, $diasSemana);
            if (is_array($salariosData) && !isset($salariosData['status'])) {
                foreach ($salariosData as &$emp) {
                    $monto = (float)($emp['salario_monto'] ?? 0);
                    $periodo = strtolower((string)($emp['salario_periodo'] ?? ''));
                    $factorSemanas = 0;
                    if ($periodo === 'semanal') $factorSemanas = 1;
                    else if ($periodo === 'quincenal') $factorSemanas = 2;
                    else if ($periodo === 'mensual') $factorSemanas = 4.33;
                    else if ($periodo === 'bimensual') $factorSemanas = 8.66;
                    else if ($periodo === 'trimestral') $factorSemanas = 13;
                    else if ($periodo === 'semestral') $factorSemanas = 26;
                    else if ($periodo === 'anual') $factorSemanas = 52;

                    $horasPeriodo = $horasSemana > 0 && $factorSemanas > 0 ? ($horasSemana * $factorSemanas) : 0;
                    $emp['costo_por_hora'] = $horasPeriodo > 0 ? round($monto / $horasPeriodo, 4) : 0;
                }
            }
            $finalResponse['costo_hora_empleado'] = $salariosData;

            $companyDB = LOCAL_DB;
            
            // --- 3. Obtener Costo de Tinta (api_empresas.tintas_recargas) ---
            $tintaSql = "SELECT precio_litro FROM api_empresas.tintas_recargas WHERE id_empresa = :id_empresa ORDER BY fecha DESC LIMIT 1";
            $tintaData = $dbEmpresas->goQuery($tintaSql, ['id_empresa' => $id_empresa]);
            $costo_tinta_litro = $tintaData[0]['precio_litro'] ?? 0;

            $whereClause = (isset($whereConditions) && !empty($whereConditions)) ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

            // 1. Obtener Órdenes (Consulta Base)
            $sqlReporte = "SELECT
                a._id AS id_orden,
                a.moment,
                a.cliente_nombre,
                a.status,
                a.pago_total,
                a.responsable as id_vendedor
            FROM
                $companyDB.ordenes a
            $whereClause
            ORDER BY a._id DESC
            ";

            $reporteData = $dbEmpresas->goQuery($sqlReporte, $params);
            if (isset($reporteData['status']) && $reporteData['status'] === 'error') {
                throw new Exception('Error en la consulta base del reporte: ' . $reporteData['message']);
            }

            if (empty($reporteData)) {
                $finalResponse['reporte_data'] = [];
            } else {
                $orderIds = array_column($reporteData, 'id_orden');
                $orderIdsStr = implode(',', $orderIds);

                // 2. Obtener Nombres de Vendedores
                $vendedoresSql = "SELECT id_usuario, nombre FROM api_empresas.empresas_usuarios WHERE id_empresa = $id_empresa";
                $vendedoresRaw = $dbEmpresas->goQuery($vendedoresSql);
                $vendedoresMap = [];
                foreach ($vendedoresRaw as $v) $vendedoresMap[$v['id_usuario']] = $v['nombre'];

                // 3. BATCH: Total Productos (Solo si hay órdenes)
                $productosMap = [];
                if (!empty($orderIds)) {
                    $productosSql = "SELECT id_orden, SUM(cantidad) as total FROM $companyDB.ordenes_productos WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden";
                    $productosRaw = $dbEmpresas->goQuery($productosSql);
                    if (is_array($productosRaw) && !isset($productosRaw['status'])) {
                        foreach ($productosRaw as $p) $productosMap[$p['id_orden']] = $p['total'];
                    }
                }

                // 4. BATCH: Costo de Insumos (Solo si hay órdenes)
                $insumosMap = [];
                if (!empty($orderIds)) {
                    $insumosSql = "SELECT c.id_orden, SUM(COALESCE(ABS(c.valor_inicial - c.valor_final), 0) * (COALESCE(d.costo, 0) / COALESCE(NULLIF(d.cantidad_inicial, 0), NULLIF(d.cantidad, 0), 1))) as total 
                                   FROM $companyDB.inventario_movimientos c JOIN $companyDB.inventario d ON c.id_insumo = d._id 
                                   WHERE c.id_orden IN ($orderIdsStr) GROUP BY c.id_orden";
                    $insumosRaw = $dbEmpresas->goQuery($insumosSql);
                    if (is_array($insumosRaw) && !isset($insumosRaw['status'])) {
                        foreach ($insumosRaw as $i) $insumosMap[$i['id_orden']] = $i['total'];
                    }
                }

                // 5. BATCH: Costo Mano de Obra (Pagos) (Solo si hay órdenes)
                $pagosMap = [];
                if (!empty($orderIds)) {
                    $pagosSql = "SELECT p.id_orden, SUM(p.monto_pago) as total FROM $companyDB.pagos p WHERE p.id_orden IN ($orderIdsStr) GROUP BY p.id_orden";
                    $pagosRaw = $dbEmpresas->goQuery($pagosSql);
                    if (is_array($pagosRaw) && !isset($pagosRaw['status'])) {
                        foreach ($pagosRaw as $p) $pagosMap[$p['id_orden']] = $p['total'];
                    }
                }

                // 6. BATCH: Reposiciones (Solo si hay órdenes)
                $reposMap = [];
                if (!empty($orderIds)) {
                    $reposSql = "SELECT id_orden, COUNT(_id) as total FROM $companyDB.reposiciones WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden";
                    $reposRaw = $dbEmpresas->goQuery($reposSql);
                    if (is_array($reposRaw) && !isset($reposRaw['status'])) {
                        foreach ($reposRaw as $r) $reposMap[$r['id_orden']] = $r['total'];
                    }
                }

                $debugMaps = [];
                if (false) {
                    $debugMaps = [
                        'movs' => [],
                        'pagos' => [],
                        'tintas' => [],
                        'tareas_total' => [],
                        'tareas_abiertas' => [],
                    ];

                    $dbgMovs = $dbEmpresas->goQuery("SELECT id_orden, COUNT(*) as cnt FROM $companyDB.inventario_movimientos WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden");
                    if (is_array($dbgMovs) && !isset($dbgMovs['status'])) foreach ($dbgMovs as $x) $debugMaps['movs'][$x['id_orden']] = (int)$x['cnt'];

                    $dbgPagos = $dbEmpresas->goQuery("SELECT id_orden, COUNT(*) as cnt FROM $companyDB.pagos WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden");
                    if (is_array($dbgPagos) && !isset($dbgPagos['status'])) foreach ($dbgPagos as $x) $debugMaps['pagos'][$x['id_orden']] = (int)$x['cnt'];

                    $dbgTintas = $dbEmpresas->goQuery("SELECT id_orden, COUNT(*) as cnt FROM $companyDB.tintas WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden");
                    if (is_array($dbgTintas) && !isset($dbgTintas['status'])) foreach ($dbgTintas as $x) $debugMaps['tintas'][$x['id_orden']] = (int)$x['cnt'];

                    $dbgTareas = $dbEmpresas->goQuery("SELECT id_orden, COUNT(*) as total, SUM(CASE WHEN fecha_terminado IS NULL THEN 1 ELSE 0 END) as abiertas FROM $companyDB.lotes_detalles_empleados_asignados WHERE id_orden IN ($orderIdsStr) GROUP BY id_orden");
                    if (is_array($dbgTareas) && !isset($dbgTareas['status'])) {
                        foreach ($dbgTareas as $x) {
                            $debugMaps['tareas_total'][$x['id_orden']] = (int)$x['total'];
                            $debugMaps['tareas_abiertas'][$x['id_orden']] = (int)$x['abiertas'];
                        }
                    }
                }

                // 7. BATCH: Empleados Asignados y Tiempo de Producción (Optimizado)
                $empAsigSql = "SELECT 
                        id_orden, 
                        GROUP_CONCAT(DISTINCT id_empleado) as empleados,
                        SUM(TIME_TO_SEC(TIMEDIFF(fecha_terminado, fecha_inicio)) / 3600) as tiempo_total
                    FROM $companyDB.lotes_detalles_empleados_asignados 
                    WHERE id_orden IN ($orderIdsStr) 
                    GROUP BY id_orden";
                $empAsigRaw = $dbEmpresas->goQuery($empAsigSql);
                $empAsigMap = [];
                $tiempoMap = [];
                if (!empty($empAsigRaw) && !isset($empAsigRaw['status'])) {
                    foreach ($empAsigRaw as $ea) {
                        $empAsigMap[$ea['id_orden']] = $ea['empleados'];
                        $tiempoMap[$ea['id_orden']] = (float)$ea['tiempo_total'];
                    }
                }

                // 8. BATCH: Tintas
                $tintasRaw = [];
                if (!empty($orderIds)) {
                    $tintasSql = "SELECT id_orden, moment, c, m, y, k, w, id_catalogo_impresoras FROM $companyDB.tintas WHERE id_orden IN ($orderIdsStr)";
                    $tintasRaw = $dbEmpresas->goQuery($tintasSql);
                }

                // Obtener costos de tintas (Recargas e Inventario)
                $recargasSql = "SELECT tr.id_catalogo_impresora, tr.color, (COALESCE(inv.costo, 0) / COALESCE(NULLIF(inv.cantidad_inicial, 0), NULLIF(inv.cantidad, 0), 1)) as cost_ml FROM $companyDB.tintas_recargas tr JOIN $companyDB.inventario inv ON tr.id_insumo = inv._id ORDER BY tr.fecha_recarga DESC";
                $recargasRaw = $dbEmpresas->goQuery($recargasSql);
                $costMap = [];
                if (is_array($recargasRaw) && !isset($recargasRaw['status'])) {
                    foreach ($recargasRaw as $r) {
                        $colKey = strtoupper(substr(trim($r['color'] ?? ''), 0, 1));
                        if ($colKey && !isset($costMap[$r['id_catalogo_impresora']][$colKey])) {
                            $costMap[$r['id_catalogo_impresora']][$colKey] = $r['cost_ml'];
                        }
                    }
                }

                // Fallback: tinta_filtro
                $fallbackRaw = $dbEmpresas->goQuery("SELECT tf.color, (COALESCE(inv.costo, 0) / COALESCE(NULLIF(inv.cantidad_inicial, 0), NULLIF(inv.cantidad, 0), 1)) as cost_ml FROM $companyDB.tinta_filtro tf JOIN $companyDB.inventario inv ON tf.id_inventario = inv._id");
                $fallbackMap = [];
                if (is_array($fallbackRaw) && !isset($fallbackRaw['status'])) {
                    foreach ($fallbackRaw as $fr) {
                        $colKey = strtoupper(substr(trim($fr['color'] ?? ''), 0, 1));
                        if ($colKey) $fallbackMap[$colKey] = $fr['cost_ml'];
                    }
                }

                $tintasDetalle = [];
                $tintasResumenMap = [];
                if (is_array($tintasRaw) && !isset($tintasRaw['status'])) {
                    foreach ($tintasRaw as $t) {
                    $id = $t['id_orden'];
                    $cost_total = 0;
                    $total_ml = 0;
                    foreach (['c', 'm', 'y', 'k', 'w'] as $col) {
                        $ml = (float)($t[$col] ?? 0);
                        $total_ml += $ml;
                        $colKey = strtoupper(substr($col, 0, 1));
                        $costMl = $costMap[$t['id_catalogo_impresoras']][$colKey] ?? ($fallbackMap[$colKey] ?? 0);
                        $cost_total += ($ml * $costMl);
                    }
                    $tintasDetalle[] = ['id_orden' => $id, 'total_tinta_consumo_ml' => round($total_ml, 2), 'total_tinta_costo' => round($cost_total, 2)];
                    if (!isset($tintasResumenMap[$id])) $tintasResumenMap[$id] = ['ml' => 0, 'cost' => 0];
                    $tintasResumenMap[$id]['ml'] += $total_ml;
                    $tintasResumenMap[$id]['cost'] += $cost_total;
                }
            }

                $tintasResumenFinal = [];
                foreach ($tintasResumenMap as $id => $data) {
                    $tintasResumenFinal[] = ['id_orden' => $id, 'total_tinta_consumo_ml' => round($data['ml'], 2), 'total_tinta_costo' => round($data['cost'], 2)];
                }

                // 9. Combinar Datos en reporteData
                foreach ($reporteData as &$row) {
                    $id = $row['id_orden'];
                    $row['vendedor'] = $vendedoresMap[$row['id_vendedor']] ?? 'Desconocido';
                    $row['total_productos'] = $productosMap[$id] ?? 0;
                    $row['costos_de_insumos'] = $insumosMap[$id] ?? 0;
                    $row['costo_mano_de_obra'] = $pagosMap[$id] ?? 0;
                    $row['tiempo_de_produccion'] = $tiempoMap[$id] ?? 0;
                    $row['empleados_asignados'] = $empAsigMap[$id] ?? '';
                    $row['reposiciones'] = $reposMap[$id] ?? 0;
                    if (false) {
                        $row['debug_movimientos_insumos'] = $debugMaps['movs'][$id] ?? 0;
                        $row['debug_pagos'] = $debugMaps['pagos'][$id] ?? 0;
                        $row['debug_tintas'] = $debugMaps['tintas'][$id] ?? 0;
                        $row['debug_tareas_total'] = $debugMaps['tareas_total'][$id] ?? 0;
                        $row['debug_tareas_abiertas'] = $debugMaps['tareas_abiertas'][$id] ?? 0;
                    }
                }

                $finalResponse['reporte_data'] = $reporteData;
                $finalResponse['tintas_consumidas'] = $tintasDetalle;
                $finalResponse['tintas_resumen'] = $tintasResumenFinal;
                if (false) {
                    $finalResponse['debug'] = [
                        'id_empresa' => $id_empresa,
                        'inicio' => $inicio,
                        'fin' => $fin,
                        'orders' => count($orderIds),
                    ];
                }

                // 10. BATCH: Eficiencia de Insumos (Unificado con Reporte Semanal: Meta vs Real)
                $sqlInsumosResumen = "SELECT 
                        op.id_orden,
                        SUM(op.cantidad * pia.cantidad) AS meta,
                        COALESCE((
                            SELECT SUM(ABS(im_sub.valor_final - im_sub.valor_inicial) * COALESCE(inv_sub.rendimiento, 1))
                            FROM $companyDB.inventario_movimientos im_sub
                            LEFT JOIN $companyDB.inventario inv_sub ON inv_sub._id = im_sub.id_insumo
                            WHERE im_sub.id_orden = op.id_orden
                        ), 0) AS `real`
                    FROM $companyDB.ordenes_productos op
                    LEFT JOIN $companyDB.product_insumos_asignados pia ON pia.id_product = op.id_woo AND pia.id_talla = op.id_size
                    WHERE op.id_orden IN ($orderIdsStr)
                    GROUP BY op.id_orden";
                
                $insumosResumenRaw = $dbEmpresas->goQuery($sqlInsumosResumen);
                $eficienciaMap = [];
                if (!empty($insumosResumenRaw) && !isset($insumosResumenRaw['status'])) {
                    foreach ($insumosResumenRaw as &$ir) {
                        $meta = (float)($ir['meta'] ?? 0);
                        $real = (float)($ir['real'] ?? 0);
                        if ($meta == 0) {
                            $ir['eficiencia'] = "Sin insumos asignados";
                        } else {
                            $ir['eficiencia'] = ($real > 0) ? round(($meta / $real) * 100, 2) : 100;
                        }
                        $eficienciaMap[$ir['id_orden']] = $ir['eficiencia'];
                    }
                }
                
                // Integrar eficiencia directamente en reporteData
                foreach ($reporteData as &$row) {
                    $row['eficiencia_insumos'] = $eficienciaMap[$row['id_orden']] ?? "Sin insumos asignados";
                }

                $finalResponse['insumos_resumen'] = $insumosResumenRaw;

                // Detalle para el modal (Eficiencia por Insumo)
                $sqlInsumosDetalles = "SELECT 
                        a._id id_inventario_movimientos, 
                        a.id_orden, 
                        a.id_producto, 
                        d.product producto, 
                        c.insumo,
                        (ABS(a.valor_inicial - a.valor_final) * COALESCE(c.rendimiento, 1)) as `real`,
                        COALESCE((
                            SELECT SUM(op_sub.cantidad * pia_sub.cantidad)
                            FROM $companyDB.ordenes_productos op_sub
                            JOIN $companyDB.product_insumos_asignados pia_sub ON pia_sub.id_product = op_sub.id_woo AND pia_sub.id_talla = op_sub.id_size
                            WHERE op_sub.id_orden = a.id_orden 
                              AND pia_sub.id_catalogo_insumos_productos = COALESCE(a.id_catalogo_insumos_prodcutos, c.id_catalogo)
                        ), 0) as meta
                    FROM $companyDB.inventario_movimientos a 
                    LEFT JOIN $companyDB.inventario c ON a.id_insumo = c._id 
                    LEFT JOIN $companyDB.products d ON a.id_producto = d._id 
                    WHERE a.id_orden IN ($orderIdsStr) 
                    ORDER BY a.id_orden ASC";
                
                $insDetRaw = $dbEmpresas->goQuery($sqlInsumosDetalles);
                if (!empty($insDetRaw) && !isset($insDetRaw['status'])) {
                    foreach ($insDetRaw as &$idr) {
                        $m = (float)($idr['meta'] ?? 0);
                        $r = (float)($idr['real'] ?? 0);
                        if ($m == 0) {
                            $idr['eficiencia'] = "Sin insumos asignados";
                        } else {
                            $idr['eficiencia'] = ($r > 0) ? round(($m / $r) * 100, 2) : 100;
                        }
                    }
                    $finalResponse['insumos_detalles'] = $insDetRaw;
                } else {
                    $finalResponse['insumos_detalles'] = [];
                }

                // 11. Tareas de Empleados
                $sqlTareas = "SELECT a.id_orden, a.id_empleado, a.fecha_inicio, a.fecha_terminado, TIME_TO_SEC(TIMEDIFF(a.fecha_terminado, a.fecha_inicio)) / 60 AS minutos_transcurridos FROM $companyDB.lotes_detalles_empleados_asignados a WHERE a.id_orden IN ($orderIdsStr) AND a.fecha_terminado IS NOT NULL";
                $finalResponse['tareas_data'] = $dbEmpresas->goQuery($sqlTareas);
            }

                                                // 12. Gastos (Reglas: Fijos siempre por plantilla, Variables/Adicionales por registro real)
            $fecha_inicio_dt = new DateTime($inicio);
            $fecha_fin_dt = new DateTime($fin);
            $intervalo = $fecha_inicio_dt->diff($fecha_fin_dt);
            $dias_periodo = $intervalo->days + 1;
            $factor_mensual = $dias_periodo / 30.44;

            // A. Plantillas Fijas
            $sqlPlantillasFijas = "SELECT moneda, SUM(monto) as total_mensual 
                                   FROM $companyDB.gastos 
                                   WHERE estatus = 'activo' AND tipo = 'fijo'
                                   GROUP BY moneda";
            $plantillasFijasRaw = $dbEmpresas->goQuery($sqlPlantillasFijas);

            // B. Registros Reales
            $sqlGastosReales = "SELECT tipo, moneda, SUM(monto) as total 
                                FROM $companyDB.gastos_registros 
                                WHERE fecha_de_gasto BETWEEN :inicio AND :fin 
                                GROUP BY tipo, moneda";
            $gastosRealesRaw = $dbEmpresas->goQuery($sqlGastosReales, [':inicio' => $inicio, ':fin' => $fin]);

            $gastosPorTipo = ['fijo' => [], 'variable' => [], 'adicional' => []];
            $fijosMap = [];
            $totalGastosFijosUSD = 0; // Para el resumen compatible

            if (is_array($plantillasFijasRaw) && !isset($plantillasFijasRaw['status'])) {
                foreach ($plantillasFijasRaw as $pf) {
                    $m = $pf['moneda'];
                    $monto = (float)$pf['total_mensual'] * $factor_mensual;
                    $fijosMap[$m] = $monto;
                }
            }

            if (is_array($gastosRealesRaw) && !isset($gastosRealesRaw['status'])) {
                foreach ($gastosRealesRaw as $gr) {
                    $tipo = strtolower($gr['tipo']);
                    $moneda = $gr['moneda'];
                    $monto = (float)$gr['total'];
                    if ($tipo === 'fijo') {
                        $fijosMap[$moneda] = max(($fijosMap[$moneda] ?? 0), $monto);
                    } else {
                        $gastosPorTipo[$tipo][] = ['moneda' => $moneda, 'total' => round($monto, 2)];
                    }
                }
            }

            foreach ($fijosMap as $moneda => $total) {
                $gastosPorTipo['fijo'][] = ['moneda' => $moneda, 'total' => round($total, 2)];
            }

            // Variables de compatibilidad
            $totalProductosPeriodo = !empty($reporteData) ? array_sum(array_column($reporteData, 'total_productos')) : 0;
            $totalGastosSemanales = 0; // Se mantiene por legacy si el frontend lo pide
            $costoOperativoPorProducto = 0;

            $finalResponse['costos_operativos'] = [
                'total_gastos_semanales' => $totalGastosSemanales,
                'total_productos_periodo' => $totalProductosPeriodo,
                'costo_operativo_por_producto' => $costoOperativoPorProducto,
                'gastos_por_tipo' => $gastosPorTipo
            ];

            if (false) {
                $count_total = $dbEmpresas->goQuery("SELECT COUNT(*) as total FROM $companyDB.gastos_registros");
                $count_period = $dbEmpresas->goQuery("SELECT COUNT(*) as total FROM $companyDB.gastos_registros WHERE fecha_de_gasto >= '2026-04-01'");
                $sample_gastos = $dbEmpresas->goQuery("SELECT * FROM $companyDB.gastos");
                $finalResponse['debug_gastos'] = [
                    'sql' => $sqlGastosReales,
                    'params' => [':inicio' => $inicio, ':fin' => $fin],
                    'raw_results' => $gastosRealesRaw,
                    'registros_desde_abril' => $count_period[0]['total'] ?? 0,
                    'registros_totales' => $count_total[0]['total'] ?? 0,
                    'plantillas_gastos' => $sample_gastos,
                    'company_db' => $companyDB
                ];
            }
            $response->getBody()->write(json_encode($finalResponse, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error en el reporte (Debug SQL Mode): ' . $e->getMessage()]));
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
        $id_empresa = ID_EMPRESA;

        $sqlPagos = 'SELECT
                        p._id AS id_pago,
                        p.id_empleado,
                        eu.nombre AS nombre_empleado,
                        p.detalle AS departamento,
                        p.cantidad,
                        p.monto_pago,
                        (SELECT COALESCE(SUM(monto), 0) FROM pagos_abonos WHERE id_pago = p._id) AS total_bonos,
                        (SELECT COALESCE(SUM(monto), 0) FROM pagos_descuentos WHERE id_pago = p._id) AS total_descuentos,
                        (SELECT COALESCE(SUM(monto), 0) FROM pagos_salarios WHERE id_pago = p._id) AS total_salario_pagado
                    FROM
                        pagos p
                    JOIN
                        api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario
                    WHERE
                        p.id_orden = ?
                    ORDER BY
                        eu.nombre, p.detalle';

        $db = new LocalDB();
        $pagosData = $db->goQuery($sqlPagos, [$id_orden]);

        // Load work schedule to compute costo_por_hora
        $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
        $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);
        $horarioResult = $dbEmpresas->goQuery("SELECT horario_laboral FROM empresas WHERE id_empresa = ?", [$id_empresa]);
        $horario_laboral = (!empty($horarioResult) && isset($horarioResult[0]['horario_laboral'])) ? $horarioResult[0]['horario_laboral'] : null;
        $horarioObj = $horario_laboral ? json_decode($horario_laboral, true) : null;
        $horasDia = 0;
        $diasSemana = 0;
        if (is_array($horarioObj)) {
            $horasDia = (float)($horarioObj['horaFinManana'] ?? 0) - (float)($horarioObj['horaInicioManana'] ?? 0);
            $horasDia += (float)($horarioObj['horaFinTarde'] ?? 0) - (float)($horarioObj['horaInicioTarde'] ?? 0);
            $diasSemana = is_array($horarioObj['diasLaborales'] ?? null) ? count($horarioObj['diasLaborales']) : 0;
        }
        $horasSemana = max(0, $horasDia) * max(0, $diasSemana);
        $dbEmpresas->disconnect();

        // Salary employees who worked on this order (from task records)
        $sqlSalarios = "SELECT
                            ldea.id_empleado,
                            eu.nombre AS nombre_empleado,
                            eu.salario_monto,
                            eu.salario_periodo,
                            SUM(TIMESTAMPDIFF(SECOND, ldea.fecha_inicio, COALESCE(ldea.fecha_terminado, NOW())) / 3600) AS horas_trabajadas
                        FROM lotes_detalles_empleados_asignados ldea
                        JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = ldea.id_empleado
                        WHERE ldea.id_orden = ?
                        AND ldea.fecha_inicio IS NOT NULL
                        AND eu.salario_tipo IN ('Salario', 'Salario más Comisión')
                        GROUP BY ldea.id_empleado, eu.nombre, eu.salario_monto, eu.salario_periodo";

        $salariosRaw = $db->goQuery($sqlSalarios, [$id_orden]);
        $db->disconnect();

        $salariosData = [];
        if (is_array($salariosRaw)) {
            foreach ($salariosRaw as $row) {
                $monto = (float)($row['salario_monto'] ?? 0);
                $periodo = strtolower((string)($row['salario_periodo'] ?? ''));
                $factorSemanas = 0;
                if ($periodo === 'semanal') $factorSemanas = 1;
                elseif ($periodo === 'quincenal') $factorSemanas = 2;
                elseif ($periodo === 'mensual') $factorSemanas = 4.33;
                elseif ($periodo === 'bimensual') $factorSemanas = 8.66;
                elseif ($periodo === 'trimestral') $factorSemanas = 13;
                elseif ($periodo === 'semestral') $factorSemanas = 26;
                elseif ($periodo === 'anual') $factorSemanas = 52;
                $horasPeriodo = $horasSemana > 0 && $factorSemanas > 0 ? ($horasSemana * $factorSemanas) : 0;
                $costoPorHora = $horasPeriodo > 0 ? round($monto / $horasPeriodo, 4) : 0;
                $horasTrabajadas = (float)($row['horas_trabajadas'] ?? 0);
                $salariosData[] = [
                    'id_empleado' => $row['id_empleado'],
                    'nombre_empleado' => $row['nombre_empleado'],
                    'horas_trabajadas' => round($horasTrabajadas, 2),
                    'costo_por_hora' => $costoPorHora,
                    'salario_proporcional' => round($costoPorHora * $horasTrabajadas, 2),
                ];
            }
        }

        $result = ['pagos' => $pagosData, 'salarios' => $salariosData];
        $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));
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
                    r.cantidad AS cantidad_consumida,
                    i.unidad,
                    r.desperdicio,
                    eu.nombre AS empleado,
                    d.departamento
                FROM rendimiento r
                JOIN inventario i ON r.id_insumo = i._id
                LEFT JOIN api_empresas.empresas_usuarios eu ON r.id_empleado = eu.id_usuario
                LEFT JOIN departamentos d ON r.id_departamento = d._id
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
                        WHERE op.id_orden = a._id 
                        AND (p.fisico = 1 OR p.fisico IS NULL)
                        AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
                    ) AS total_unidades
                FROM ordenes a
                LEFT JOIN customers cus ON cus._id = a.id_wp
                WHERE a.status IN ('terminada', 'entregada')
                AND a._id IN (
                    SELECT DISTINCT id_orden 
                    FROM lotes_detalles_empleados_asignados 
                    WHERE DATE(fecha_terminado) BETWEEN ? AND ?
                )
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

            // 5. Obtener Ranking de Productos Top 10
            $sqlTop = "SELECT 
                    p.product AS name,
                    SUM(op.cantidad) AS value
                FROM ordenes_productos op
                JOIN ordenes o ON o._id = op.id_orden
                JOIN products p ON p._id = op.id_woo
                WHERE o._id IN (
                    SELECT DISTINCT id_orden 
                    FROM lotes_detalles_empleados_asignados 
                    WHERE DATE(fecha_terminado) BETWEEN ? AND ?
                )
                AND (p.fisico = 1 OR p.fisico IS NULL)
                AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
                GROUP BY p._id
                ORDER BY value DESC
                LIMIT 10";
            $topProducts = $db->goQuery($sqlTop, [$inicio, $fin]);

            // 6. Obtener Resumen de Todos los Productos para el Modal (Alfabético)
            $sqlAll = "SELECT 
                    p.product AS name,
                    SUM(op.cantidad) AS value
                FROM ordenes_productos op
                JOIN products p ON p._id = op.id_woo
                WHERE op.id_orden IN (
                    SELECT DISTINCT id_orden 
                    FROM lotes_detalles_empleados_asignados 
                    WHERE DATE(fecha_terminado) BETWEEN ? AND ?
                )
                AND (p.fisico = 1 OR p.fisico IS NULL)
                AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
                GROUP BY p._id
                ORDER BY p.product ASC";
            $allProducts = $db->goQuery($sqlAll, [$inicio, $fin]);
            
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

            $response->getBody()->write(json_encode([
                'items' => $finalItems,
                'topProducts' => $topProducts ?: [],
                'allProducts' => $allProducts ?: []
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $db->disconnect();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // REPORTE GLOBAL DE EFICIENCIA POR EMPLEADOS (Para Gráfico)
    // =================================================================
    $app->get('/reportes/employee-efficiency-global', function (Request $request, Response $response) {
        $params = $request->getQueryParams();
        $inicio = $params['inicio'] ?? null;
        $fin = $params['fin'] ?? null;

        if (!$inicio || !$fin) {
            $response->getBody()->write(json_encode(['error' => 'Faltan parámetros de fecha']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = new LocalDB();
        try {
            $sqlDetalles = "
                SELECT 
                    ldea.id_empleado,
                    eu.nombre AS empleado_nombre,
                    ldea.id_orden,
                    ldea.id_departamento,
                    ldea.fecha_inicio,
                    ldea.fecha_terminado,
                    (
                        SELECT COALESCE(SUM(ptp_sub.tiempo * op.cantidad), 0)
                        FROM products_tiempos_de_produccion ptp_sub
                        JOIN ordenes_productos op ON ptp_sub.id_product = op.id_woo
                        WHERE ptp_sub.id_departamento = ldea.id_departamento 
                        AND op.id_orden = ldea.id_orden
                    ) AS projected_seconds
                FROM lotes_detalles_empleados_asignados ldea
                JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = ldea.id_empleado
                WHERE (DATE(ldea.fecha_inicio) BETWEEN ? AND ? OR DATE(ldea.fecha_terminado) BETWEEN ? AND ?)
                AND ldea.fecha_inicio IS NOT NULL
                ORDER BY eu.nombre, ldea.fecha_inicio DESC
            ";
            
            $tareas = $db->goQuery($sqlDetalles, [$inicio, $fin, $inicio, $fin]);
            $db->disconnect();
            
            $response->getBody()->write(json_encode(['tareas' => $tareas], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            if(isset($db)) $db->disconnect();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

}; // Fin de la función que envuelve las rutas
