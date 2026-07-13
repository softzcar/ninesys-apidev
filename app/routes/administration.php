<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * RUTAS DE ADMINISTRACIÓN
 * Endpoints para el dashboard de administración con datos globales de la empresa
 */

return function ($app) {
    // =====================================================================
    // DASHBOARD DE ADMINISTRACIÓN - ESTADÍSTICAS GLOBALES
    // =====================================================================
    $app->get('/administracion/dashboard-stats', function (Request $request, Response $response) {
        // Conectar a la base de datos de la empresa (configurado por IdEmpresaMiddleware)
        $localConnection = new LocalDB();

        $finalResponse = [];
        $isPg = DB_DRIVER === 'pgsql';

        try {
            // =====================================================================
            // GRÁFICO 1: RESUMEN SEMANAL DE ÓRDENES (GLOBAL)
            // =====================================================================
            // Últimas órdenes agrupadas por día
            $diaExpr = $isPg ? "TO_CHAR(fecha_creacion, 'FMDay')" : "DATE_FORMAT(fecha_creacion, '%W')";
            $fechaCreacionExpr = $isPg ? 'fecha_creacion::date' : 'DATE(fecha_creacion)';
            $sqlResumenSemanal = "SELECT
                $diaExpr as dia,
                $fechaCreacionExpr as fecha,
                COUNT(*) as total_ordenes
            FROM ordenes
            WHERE fecha_creacion IS NOT NULL
            GROUP BY $fechaCreacionExpr
            ORDER BY fecha DESC
            LIMIT 7";

            $resumenSemanalResult = $localConnection->goQuery($sqlResumenSemanal);

            // Formatear respuesta (invertir para mostrar más antiguo primero)
            $finalResponse['resumen_semanal'] = [];
            if (!empty($resumenSemanalResult) && !isset($resumenSemanalResult['status'])) {
                $reversed = array_reverse($resumenSemanalResult);
                foreach ($reversed as $row) {
                    $finalResponse['resumen_semanal'][] = [
                        'dia' => $row['dia'],
                        'fecha' => $row['fecha'],
                        'total_ordenes' => (int) $row['total_ordenes']
                    ];
                }
            }

            // =====================================================================
            // GRÁFICO 2: ESTADO DE ÓRDENES (GLOBAL)
            // =====================================================================
            // Conteo de todas las órdenes por status
            $sqlEstadoOrdenes = "SELECT 
                status,
                COUNT(*) as cantidad
            FROM ordenes
            WHERE status IN ('En espera', 'pausada', 'activa', 'terminada')
            GROUP BY status";

            $estadoOrdenesResult = $localConnection->goQuery($sqlEstadoOrdenes);

            // Estructurar respuesta
            $finalResponse['estado_ordenes'] = [
                'en_espera' => 0,
                'pausadas' => 0,
                'activas' => 0,
                'terminadas' => 0
            ];

            if (!empty($estadoOrdenesResult) && !isset($estadoOrdenesResult['status'])) {
                foreach ($estadoOrdenesResult as $row) {
                    if ($row['status'] === 'En espera') {
                        $finalResponse['estado_ordenes']['en_espera'] = (int) $row['cantidad'];
                    } elseif ($row['status'] === 'pausada') {
                        $finalResponse['estado_ordenes']['pausadas'] = (int) $row['cantidad'];
                    } elseif ($row['status'] === 'activa') {
                        $finalResponse['estado_ordenes']['activas'] = (int) $row['cantidad'];
                    } elseif ($row['status'] === 'terminada') {
                        $finalResponse['estado_ordenes']['terminadas'] = (int) $row['cantidad'];
                    }
                }
            }

            // =====================================================================
            // GRÁFICO 3: PROGRESO DE ÓRDENES ACTIVAS (GLOBAL)
            // =====================================================================
            // Progreso basado en asignaciones con fecha_terminado (tareas completadas)
            $sqlProgresoActivas = "SELECT 
                o._id,
                o._id as numero_orden,
                COALESCE(o.cliente_nombre, 'Sin nombre') as cliente_nombre,
                (SELECT COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_orden = o._id AND fecha_terminado IS NOT NULL) as tareas_terminadas,
                (SELECT COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_orden = o._id) as tareas_totales,
                COALESCE(
                    ROUND(
                        (SELECT COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_orden = o._id AND fecha_terminado IS NOT NULL) * 100.0 /
                        NULLIF((SELECT COUNT(*) FROM lotes_detalles_empleados_asignados WHERE id_orden = o._id), 0),
                        1
                    ),
                    0
                ) as porcentaje_completado
            FROM ordenes o
            WHERE o.status = 'activa'
            ORDER BY porcentaje_completado ASC
            LIMIT 10";

            $progresoActivasResult = $localConnection->goQuery($sqlProgresoActivas);

            $finalResponse['progreso_activas'] = [];
            if (!empty($progresoActivasResult) && !isset($progresoActivasResult['status'])) {
                foreach ($progresoActivasResult as $row) {
                    $finalResponse['progreso_activas'][] = [
                        'id_orden' => (int) $row['_id'],
                        'cliente_nombre' => $row['cliente_nombre'],
                        'numero_orden' => $row['numero_orden'],
                        'tareas_terminadas' => (int) $row['tareas_terminadas'],
                        'tareas_totales' => (int) $row['tareas_totales'],
                        'porcentaje' => round((float) $row['porcentaje_completado'], 1)
                    ];
                }
            }

            // =====================================================================
            // GRÁFICO 4: VENTAS DEL MES VS SALDO POR COBRAR (GLOBAL)
            // =====================================================================
            $mesActualCond = $isPg
                ? 'EXTRACT(YEAR FROM fecha_creacion) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM fecha_creacion) = EXTRACT(MONTH FROM CURRENT_DATE)'
                : 'YEAR(fecha_creacion) = YEAR(CURDATE()) AND MONTH(fecha_creacion) = MONTH(CURDATE())';
            $sqlVentasVsSaldo = "SELECT
                (SELECT COALESCE(SUM(pago_total), 0)
                 FROM ordenes
                 WHERE $mesActualCond
                ) as ventas_mes,
                (SELECT COALESCE(SUM(pago_abono), 0)
                 FROM ordenes
                 WHERE $mesActualCond
                ) as cobrado_mes,
                (SELECT COALESCE(SUM(pago_total - pago_abono - pago_descuento + pago_nota_credito), 0)
                 FROM ordenes
                 WHERE $mesActualCond
                   AND (pago_total - pago_abono - pago_descuento + pago_nota_credito) > 0
                ) as saldo_por_cobrar";

            $ventasVsSaldoResult = $localConnection->goQuery($sqlVentasVsSaldo);

            $finalResponse['ventas_vs_saldo'] = [
                'ventas_mes' => 0,
                'cobrado_mes' => 0,
                'saldo_por_cobrar' => 0
            ];

            if (!empty($ventasVsSaldoResult) && !isset($ventasVsSaldoResult['status'])) {
                $row = $ventasVsSaldoResult[0];
                $finalResponse['ventas_vs_saldo'] = [
                    'ventas_mes' => round((float) $row['ventas_mes'], 2),
                    'cobrado_mes' => round((float) $row['cobrado_mes'], 2),
                    'saldo_por_cobrar' => round((float) $row['saldo_por_cobrar'], 2)
                ];
            }

            // =====================================================================
            // GRÁFICO 5: ESTADO DE DISEÑOS (GLOBAL)
            // =====================================================================
            $sqlEstadoDisenos = "SELECT 
                (SELECT COUNT(*) 
                 FROM disenos 
                 WHERE id_empleado IS NOT NULL AND id_empleado > 0
                ) as asignados,
                
                (SELECT COUNT(DISTINCT r.id_diseno) 
                 FROM revisiones r
                 WHERE r.url_image IS NOT NULL
                ) as propuestas_enviadas,
                
                (SELECT COUNT(DISTINCT d._id)
                 FROM disenos d
                 INNER JOIN pagos p ON p.id_orden = d.id_orden AND p.id_empleado = d.id_empleado
                 WHERE p.id_orden IS NOT NULL
                ) as aprobados_pagados";

            $estadoDisenosResult = $localConnection->goQuery($sqlEstadoDisenos);

            $finalResponse['estado_disenos'] = [
                'asignados' => 0,
                'propuestas_enviadas' => 0,
                'aprobados_pagados' => 0
            ];

            if (!empty($estadoDisenosResult) && !isset($estadoDisenosResult['status'])) {
                $row = $estadoDisenosResult[0];
                $finalResponse['estado_disenos'] = [
                    'asignados' => (int) $row['asignados'],
                    'propuestas_enviadas' => (int) $row['propuestas_enviadas'],
                    'aprobados_pagados' => (int) $row['aprobados_pagados']
                ];
            }

            // =====================================================================
            // GRÁFICO 6: TIEMPOS DE ENTREGA (SEMÁFORO)
            // =====================================================================
            $fechaEntregaExpr = $isPg ? 'fecha_entrega::date' : 'DATE(fecha_entrega)';
            $curDateExpr = $isPg ? 'CURRENT_DATE' : 'CURDATE()';
            $sqlSemaforo = "SELECT
                SUM(CASE WHEN status = 'En espera' THEN 1 ELSE 0 END) as por_iniciar,
                SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr < $curDateExpr THEN 1 ELSE 0 END) as retrasado,
                SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr = $curDateExpr THEN 1 ELSE 0 END) as en_el_dia,
                SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr > $curDateExpr THEN 1 ELSE 0 END) as a_tiempo,
                SUM(CASE WHEN status = 'pausada' THEN 1 ELSE 0 END) as pausadas
              FROM ordenes
              WHERE status IN ('En espera', 'activa', 'pausada')";

            $semaforoResult = $localConnection->goQuery($sqlSemaforo);

            $finalResponse['tiempos_entrega'] = [
                'por_iniciar' => 0,
                'retrasado' => 0,
                'en_el_dia' => 0,
                'a_tiempo' => 0,
                'pausadas' => 0
            ];

            if (!empty($semaforoResult) && isset($semaforoResult[0])) {
                $finalResponse['tiempos_entrega']['por_iniciar'] = (int) ($semaforoResult[0]['por_iniciar'] ?? 0);
                $finalResponse['tiempos_entrega']['retrasado'] = (int) ($semaforoResult[0]['retrasado'] ?? 0);
                $finalResponse['tiempos_entrega']['en_el_dia'] = (int) ($semaforoResult[0]['en_el_dia'] ?? 0);
                $finalResponse['tiempos_entrega']['a_tiempo'] = (int) ($semaforoResult[0]['a_tiempo'] ?? 0);
                $finalResponse['tiempos_entrega']['pausadas'] = (int) ($semaforoResult[0]['pausadas'] ?? 0);
            }

            // =====================================================================
            // GRÁFICO 7: ÓRDENES POR DEPARTAMENTO
            // =====================================================================
            $sqlDepartamentos = "SELECT 
                l.paso as departamento,
                COUNT(DISTINCT l.id_orden) as cantidad
              FROM lotes l
              JOIN ordenes o ON o._id = l.id_orden
              WHERE o.status IN ('En espera', 'pausada', 'activa')
                AND l.paso IS NOT NULL
                AND l.paso != ''
                AND l.paso != 'Terminado'
                AND l.paso != 'Por asignar'
              GROUP BY l.paso
              ORDER BY cantidad DESC";

            $departamentosResult = $localConnection->goQuery($sqlDepartamentos);

            $finalResponse['ordenes_por_departamento'] = [];
            if (!empty($departamentosResult)) {
                foreach ($departamentosResult as $row) {
                    $finalResponse['ordenes_por_departamento'][] = [
                        'departamento' => $row['departamento'],
                        'cantidad' => (int) $row['cantidad']
                    ];
                }
            }

            $response->getBody()->write(json_encode($finalResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Error al obtener estadísticas del dashboard de administración',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
};
