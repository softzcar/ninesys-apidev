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

            // DEBUG: Log para ver qué retorna la query
            error_log('[ADMIN DASHBOARD] resumenSemanalResult: ' . json_encode($resumenSemanalResult));

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
            // Top 10 órdenes activas con su porcentaje de completitud basado en lotes
            $sqlProgresoActivas = "SELECT 
                o._id,
                o._id as numero_orden,
                o.cliente_nombre,
                COALESCE(
                    ROUND(
                        (SELECT COUNT(*) FROM lotes WHERE id_orden = o._id AND estatus_lote = 'terminado') * 100.0 /
                        NULLIF((SELECT COUNT(*) FROM lotes WHERE id_orden = o._id), 0),
                        1
                    ), 0
                ) as porcentaje_completado
            FROM ordenes o
            WHERE o.status = 'activa'
            ORDER BY porcentaje_completado ASC, o.fecha_creacion DESC
            LIMIT 10";

            $progresoActivasResult = $localConnection->goQuery($sqlProgresoActivas);

            $finalResponse['progreso_activas'] = [];
            if (!empty($progresoActivasResult) && !isset($progresoActivasResult['status'])) {
                foreach ($progresoActivasResult as $row) {
                    $finalResponse['progreso_activas'][] = [
                        'id_orden' => (int) $row['_id'],
                        'nombre_cliente' => $row['nombre_cliente'],
                        'numero_orden' => $row['numero_orden'],
                        'porcentaje' => round((float) $row['porcentaje_completado'], 1)
                    ];
                }
            }

            // =====================================================================
            // GRÁFICO 4: VENTAS DEL MES VS SALDO POR COBRAR (GLOBAL)
            // =====================================================================
            // Ventas del mes actual vs saldo pendiente de cobro
            $sqlVentasSaldo = "SELECT 
                (SELECT COALESCE(SUM(total), 0) 
                 FROM ordenes 
                 WHERE MONTH(fecha_alta) = MONTH(NOW()) 
                 AND YEAR(fecha_alta) = YEAR(NOW())
                ) as ventas_mes,
                
                (SELECT COALESCE(SUM(o.total - COALESCE(p.total_pagado, 0)), 0)
                 FROM ordenes o
                 LEFT JOIN (
                     SELECT id_orden, SUM(monto_pago) as total_pagado
                     FROM pagos
                     WHERE estatus = 'aprobado'
                     GROUP BY id_orden
                 ) p ON p.id_orden = o._id
                 WHERE o.total > COALESCE(p.total_pagado, 0)
                ) as saldo_por_cobrar";

            $ventasSaldoResult = $localConnection->goQuery($sqlVentasSaldo);

            $finalResponse['ventas_vs_saldo'] = [
                'ventas_mes' => 0,
                'saldo_por_cobrar' => 0
            ];

            if (!empty($ventasSaldoResult) && !isset($ventasSaldoResult['status'])) {
                $finalResponse['ventas_vs_saldo'] = [
                    'ventas_mes' => round((float) $ventasSaldoResult[0]['ventas_mes'], 2),
                    'saldo_por_cobrar' => round((float) $ventasSaldoResult[0]['saldo_por_cobrar'], 2)
                ];
            }

            $response->getBody()->write(json_encode([
                ...$finalResponse,
                '_debug_resumen_raw' => $resumenSemanalResult,
                '_debug_progreso_raw' => $progresoActivasResult
            ]));
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
