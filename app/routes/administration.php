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

        try {
            // =====================================================================
            // GRÁFICO 1: RESUMEN SEMANAL DE ÓRDENES (GLOBAL)
            // =====================================================================
            // Últimas órdenes agrupadas por día
            $sqlResumenSemanal = "SELECT 
                DATE_FORMAT(fecha_creacion, '%W') as dia,
                DATE(fecha_creacion) as fecha,
                COUNT(*) as total_ordenes
            FROM ordenes
            WHERE fecha_creacion IS NOT NULL
            GROUP BY DATE(fecha_creacion)
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
            $sqlVentasVsSaldo = "SELECT 
                (SELECT COALESCE(SUM(pago_total), 0) 
                 FROM ordenes 
                 WHERE YEAR(fecha_creacion) = YEAR(CURDATE()) 
                   AND MONTH(fecha_creacion) = MONTH(CURDATE())
                ) as ventas_mes,
                (SELECT COALESCE(SUM(pago_abono), 0) 
                 FROM ordenes 
                 WHERE YEAR(fecha_creacion) = YEAR(CURDATE()) 
                   AND MONTH(fecha_creacion) = MONTH(CURDATE())
                ) as cobrado_mes,
                (SELECT COALESCE(SUM(pago_total - pago_abono), 0) 
                 FROM ordenes 
                 WHERE YEAR(fecha_creacion) = YEAR(CURDATE()) 
                   AND MONTH(fecha_creacion) = MONTH(CURDATE())
                   AND (pago_total - pago_abono) > 0
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
                
                (SELECT COUNT(*) 
                 FROM disenos 
                 WHERE terminado = 1
                ) as terminados,
                
                (SELECT COUNT(DISTINCT id_diseno) 
                 FROM revisiones 
                 WHERE estatus = 'Aprobado'
                ) as aprobados";

            $estadoDisenosResult = $localConnection->goQuery($sqlEstadoDisenos);

            $finalResponse['estado_disenos'] = [
                'asignados' => 0,
                'terminados' => 0,
                'aprobados' => 0
            ];

            if (!empty($estadoDisenosResult) && !isset($estadoDisenosResult['status'])) {
                $row = $estadoDisenosResult[0];
                $finalResponse['estado_disenos'] = [
                    'asignados' => (int) $row['asignados'],
                    'terminados' => (int) $row['terminados'],
                    'aprobados' => (int) $row['aprobados']
                ];
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
