<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas optimizadas para el Asistente de IA (Dashboard de Administración & Analítica)
 *
 * Expone estadísticas agregadas, métricas financieras, comparativas entre períodos y
 * rankings de productos para que el chat de IA pueda responder y comparar datos
 * del Dashboard de Administración (/administracion).
 *
 * Requiere cabeceras:
 *   - X-Internal-Token: {INTERNAL_TOKEN}
 *   - Authorization: {id_empresa}
 */
return function (App $app) {

    // Función auxiliar para responder JSON consistente
    $makeRespondJson = function (Response $response) {
        return function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };
    };

    // Traducción de días al español
    $diasEsp = [
        'monday'    => 'Lunes',
        'tuesday'   => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday'  => 'Jueves',
        'friday'    => 'Viernes',
        'saturday'  => 'Sábado',
        'sunday'    => 'Domingo',
    ];

    /**
     * Resuelve rangos de fechas comunes a partir de un identificador de período.
     */
    $resolverRangoFechas = function (string $periodo, ?string $inicioCustom = null, ?string $finCustom = null): array {
        $tz = new \DateTimeZone('America/Caracas');
        $hoy = new \DateTimeImmutable('today', $tz);

        switch (strtolower(trim($periodo))) {
            case 'hoy':
                $inicio = $hoy;
                $fin = $hoy;
                $desc = 'Hoy (' . $hoy->format('d/m/Y') . ')';
                break;

            case 'ayer':
                $inicio = $hoy->modify('-1 day');
                $fin = $inicio;
                $desc = 'Ayer (' . $inicio->format('d/m/Y') . ')';
                break;

            case 'semana_actual':
                $lunes = $hoy->modify('this week monday');
                $domingo = $hoy->modify('this week sunday');
                $inicio = $lunes;
                $fin = $domingo;
                $desc = 'Semana actual (' . $lunes->format('d/m') . ' al ' . $domingo->format('d/m/Y') . ')';
                break;

            case 'semana_anterior':
                $lunes = $hoy->modify('last week monday');
                $domingo = $hoy->modify('last week sunday');
                $inicio = $lunes;
                $fin = $domingo;
                $desc = 'Semana anterior (' . $lunes->format('d/m') . ' al ' . $domingo->format('d/m/Y') . ')';
                break;

            case 'mes_actual':
                $inicio = $hoy->modify('first day of this month');
                $fin = $hoy->modify('last day of this month');
                $desc = 'Mes actual (' . $hoy->format('m/Y') . ')';
                break;

            case 'mes_anterior':
                $inicio = $hoy->modify('first day of last month');
                $fin = $hoy->modify('last day of last month');
                $desc = 'Mes anterior (' . $inicio->format('m/Y') . ')';
                break;

            case 'ano_actual':
                $inicio = $hoy->setDate((int) $hoy->format('Y'), 1, 1);
                $fin = $hoy->setDate((int) $hoy->format('Y'), 12, 31);
                $desc = 'Año actual (' . $hoy->format('Y') . ')';
                break;

            case 'ano_anterior':
                $anoAnt = (int) $hoy->format('Y') - 1;
                $inicio = $hoy->setDate($anoAnt, 1, 1);
                $fin = $hoy->setDate($anoAnt, 12, 31);
                $desc = 'Año anterior (' . $anoAnt . ')';
                break;

            case 'custom':
            default:
                if ($inicioCustom && $finCustom) {
                    try {
                        $inicio = new \DateTimeImmutable($inicioCustom, $tz);
                        $fin = new \DateTimeImmutable($finCustom, $tz);
                        $desc = 'Del ' . $inicio->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
                    } catch (\Throwable $e) {
                        $inicio = $hoy->modify('first day of this month');
                        $fin = $hoy->modify('last day of this month');
                        $desc = 'Mes actual (' . $hoy->format('m/Y') . ')';
                    }
                } else {
                    $inicio = $hoy->modify('first day of this month');
                    $fin = $hoy->modify('last day of this month');
                    $desc = 'Mes actual (' . $hoy->format('m/Y') . ')';
                }
                break;
        }

        return [
            'inicio'      => $inicio->format('Y-m-d'),
            'fin'         => $fin->format('Y-m-d'),
            'descripcion' => $desc,
            'dias'        => (int) $inicio->diff($fin)->format('%a') + 1,
        ];
    };

    /**
     * Resuelve el rango contra el que se va a comparar.
     */
    $resolverRangoComparacion = function (array $rangoBase, string $tipoComp, ?string $compInicioCustom = null, ?string $compFinCustom = null): array {
        $tz = new \DateTimeZone('America/Caracas');
        $baseInicio = new \DateTimeImmutable($rangoBase['inicio'], $tz);
        $baseFin = new \DateTimeImmutable($rangoBase['fin'], $tz);

        switch (strtolower(trim($tipoComp))) {
            case 'ano_anterior':
                $compInicio = $baseInicio->modify('-1 year');
                $compFin = $baseFin->modify('-1 year');
                $desc = 'Mismo período del año anterior (' . $compInicio->format('d/m/Y') . ' al ' . $compFin->format('d/m/Y') . ')';
                break;

            case 'mes_anterior':
                $compInicio = $baseInicio->modify('-1 month');
                $compFin = $baseFin->modify('-1 month');
                $desc = 'Mes anterior (' . $compInicio->format('d/m/Y') . ' al ' . $compFin->format('d/m/Y') . ')';
                break;

            case 'periodo_previo':
                $dias = (int) $baseInicio->diff($baseFin)->format('%a') + 1;
                $compFin = $baseInicio->modify('-1 day');
                $compInicio = $compFin->modify('-' . ($dias - 1) . ' days');
                $desc = 'Período previo inmediato (' . $compInicio->format('d/m/Y') . ' al ' . $compFin->format('d/m/Y') . ')';
                break;

            case 'custom':
                if ($compInicioCustom && $compFinCustom) {
                    try {
                        $compInicio = new \DateTimeImmutable($compInicioCustom, $tz);
                        $compFin = new \DateTimeImmutable($compFinCustom, $tz);
                        $desc = 'Del ' . $compInicio->format('d/m/Y') . ' al ' . $compFin->format('d/m/Y');
                    } catch (\Throwable $e) {
                        $compInicio = $baseInicio->modify('-1 month');
                        $compFin = $baseFin->modify('-1 month');
                        $desc = 'Mes anterior';
                    }
                } else {
                    $compInicio = $baseInicio->modify('-1 month');
                    $compFin = $baseFin->modify('-1 month');
                    $desc = 'Mes anterior';
                }
                break;

            default:
                $compInicio = $baseInicio->modify('-1 month');
                $compFin = $baseFin->modify('-1 month');
                $desc = 'Mes anterior';
                break;
        }

        return [
            'inicio'      => $compInicio->format('Y-m-d'),
            'fin'         => $compFin->format('Y-m-d'),
            'descripcion' => $desc,
        ];
    };

    /**
     * Valida la empresa contra la base de datos central.
     */
    $validarEmpresa = function (int $idEmpresa): bool {
        try {
            $central = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $central->goQuery('SELECT id_empresa FROM empresas WHERE id_empresa = ? AND activo = 1', [$idEmpresa]);
            $central->disconnect();
            return !empty($rows) && !isset($rows['status']);
        } catch (\Throwable $e) {
            error_log('[ai_dashboard] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

    // =========================================================================
    // 1. GET /internal/dashboard/{id_empresa}/summary
    // =========================================================================
    $app->get('/internal/dashboard/{id_empresa}/summary', function (Request $request, Response $response, array $args) use ($makeRespondJson, $diasEsp, $validarEmpresa) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = $makeRespondJson($response);

        $idEmpresa = filter_var($args['id_empresa'] ?? ($request->getHeader('Authorization')[0] ?? ''), FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_empresa inválido.'], 400);
        }

        if (!$validarEmpresa($idEmpresa)) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $isPg = DB_DRIVER === 'pgsql';

        try {
            $db = new LocalDB();

            // A. Tasas de cambio configuradas
            $tasas = [];
            try {
                $monedasRows = $db->goQuery('SELECT codigo, es_base, tasa_manual, tasa_manual_actualizado_en FROM catalogo_monedas WHERE activo = 1 AND eliminado = 0 ORDER BY es_base DESC');
                if (!empty($monedasRows) && !isset($monedasRows['status'])) {
                    foreach ($monedasRows as $m) {
                        $tasas[$m['codigo']] = [
                            'es_base'      => (int) $m['es_base'] === 1,
                            'tasa_manual'  => $m['tasa_manual'] !== null ? (float) $m['tasa_manual'] : null,
                            'actualizado'  => $m['tasa_manual_actualizado_en'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // catalogo_monedas no existe en empresas sin migrar
            }
            if (empty($tasas)) {
                $tasas['USD'] = ['es_base' => true, 'tasa_manual' => 1.0, 'actualizado' => null];
            }

            // B. Tiempos de Entrega (Semáforo operativo)
            $fechaEntregaExpr = $isPg ? 'fecha_entrega::date' : 'DATE(fecha_entrega)';
            $curDateExpr = $isPg ? 'CURRENT_DATE' : 'CURDATE()';
            $sqlSemaforo = "SELECT
                COALESCE(SUM(CASE WHEN status = 'En espera' THEN 1 ELSE 0 END), 0) as por_iniciar,
                COALESCE(SUM(CASE WHEN status = 'activa' AND {$fechaEntregaExpr} < {$curDateExpr} THEN 1 ELSE 0 END), 0) as retrasado,
                COALESCE(SUM(CASE WHEN status = 'activa' AND {$fechaEntregaExpr} = {$curDateExpr} THEN 1 ELSE 0 END), 0) as en_el_dia,
                COALESCE(SUM(CASE WHEN status = 'activa' AND {$fechaEntregaExpr} > {$curDateExpr} THEN 1 ELSE 0 END), 0) as a_tiempo,
                COALESCE(SUM(CASE WHEN status = 'pausada' THEN 1 ELSE 0 END), 0) as pausadas
            FROM ordenes
            WHERE status IN ('En espera', 'activa', 'pausada')";
            $semRes = $db->goQuery($sqlSemaforo);
            $semRow = (!empty($semRes) && !isset($semRes['status'])) ? $semRes[0] : [];

            $tiemposEntrega = [
                'por_iniciar' => (int) ($semRow['por_iniciar'] ?? 0),
                'retrasado'   => (int) ($semRow['retrasado'] ?? 0),
                'en_el_dia'   => (int) ($semRow['en_el_dia'] ?? 0),
                'a_tiempo'    => (int) ($semRow['a_tiempo'] ?? 0),
                'pausadas'    => (int) ($semRow['pausadas'] ?? 0),
            ];
            $tiemposEntrega['total_cola'] = array_sum($tiemposEntrega);

            // C. Estado de Órdenes
            $sqlEstados = "SELECT status, COUNT(*) as cantidad
                FROM ordenes
                WHERE status IN ('En espera', 'pausada', 'activa', 'terminada')
                GROUP BY status";
            $estRes = $db->goQuery($sqlEstados);
            $estadoOrdenes = [
                'en_espera'  => 0,
                'pausadas'   => 0,
                'activas'    => 0,
                'terminadas' => 0,
            ];
            if (!empty($estRes) && !isset($estRes['status'])) {
                foreach ($estRes as $row) {
                    $st = strtolower($row['status']);
                    if ($st === 'en espera') $estadoOrdenes['en_espera'] = (int) $row['cantidad'];
                    elseif ($st === 'pausada') $estadoOrdenes['pausadas'] = (int) $row['cantidad'];
                    elseif ($st === 'activa') $estadoOrdenes['activas'] = (int) $row['cantidad'];
                    elseif ($st === 'terminada') $estadoOrdenes['terminadas'] = (int) $row['cantidad'];
                }
            }
            $estadoOrdenes['total'] = array_sum($estadoOrdenes);

            // D. Órdenes por Departamento (Cuellos de botella)
            $sqlDeptos = "SELECT 
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
            $deptosRes = $db->goQuery($sqlDeptos);
            $ordenesPorDepto = [];
            if (!empty($deptosRes) && !isset($deptosRes['status'])) {
                foreach ($deptosRes as $row) {
                    $ordenesPorDepto[] = [
                        'departamento' => $row['departamento'],
                        'cantidad'     => (int) $row['cantidad'],
                    ];
                }
            }

            // E. Ventas y Cobros del Mes Actual
            $mesActualCond = $isPg
                ? 'EXTRACT(YEAR FROM fecha_creacion) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM fecha_creacion) = EXTRACT(MONTH FROM CURRENT_DATE)'
                : 'YEAR(fecha_creacion) = YEAR(CURDATE()) AND MONTH(fecha_creacion) = MONTH(CURDATE())';
            $sqlVentasMes = "SELECT
                COALESCE(SUM(pago_total), 0) as ventas,
                COALESCE(SUM(pago_abono), 0) as cobrado,
                COALESCE(SUM(CASE WHEN (pago_total - pago_abono - pago_descuento + pago_nota_credito) > 0 
                                 THEN (pago_total - pago_abono - pago_descuento + pago_nota_credito) 
                                 ELSE 0 END), 0) as saldo_por_cobrar,
                COUNT(*) as total_ordenes
            FROM ordenes
            WHERE {$mesActualCond}
              AND LOWER(status) <> 'cancelada'";
            $ventasRes = $db->goQuery($sqlVentasMes);
            $ventasRow = (!empty($ventasRes) && !isset($ventasRes['status'])) ? $ventasRes[0] : [];
            $ventasTotal = round((float) ($ventasRow['ventas'] ?? 0), 2);
            $cobradoTotal = round((float) ($ventasRow['cobrado'] ?? 0), 2);
            $saldoTotal = round((float) ($ventasRow['saldo_por_cobrar'] ?? 0), 2);
            $totalOrdenesMes = (int) ($ventasRow['total_ordenes'] ?? 0);
            $pctCobrado = $ventasTotal > 0 ? round(($cobradoTotal / $ventasTotal) * 100, 1) : 0;

            $ventasMesActual = [
                'ventas'             => $ventasTotal,
                'cobrado'            => $cobradoTotal,
                'saldo_por_cobrar'   => $saldoTotal,
                'porcentaje_cobrado' => $pctCobrado,
                'total_ordenes'      => $totalOrdenesMes,
            ];

            // F. Estado de Diseños
            $sqlDisenos = "SELECT 
                (SELECT COUNT(*) FROM disenos WHERE id_empleado IS NOT NULL AND id_empleado > 0) as asignados,
                (SELECT COUNT(DISTINCT r.id_diseno) FROM revisiones r WHERE r.url_image IS NOT NULL AND r.url_image <> '') as propuestas_enviadas,
                (SELECT COUNT(DISTINCT d._id) FROM disenos d
                 INNER JOIN pagos p ON p.id_orden = d.id_orden AND p.id_empleado = d.id_empleado
                 WHERE p.id_orden IS NOT NULL) as aprobados_pagados";
            $disRes = $db->goQuery($sqlDisenos);
            $disRow = (!empty($disRes) && !isset($disRes['status'])) ? $disRes[0] : [];
            $estadoDisenos = [
                'asignados'           => (int) ($disRow['asignados'] ?? 0),
                'propuestas_enviadas' => (int) ($disRow['propuestas_enviadas'] ?? 0),
                'aprobados_pagados'   => (int) ($disRow['aprobados_pagados'] ?? 0),
            ];

            // G. Resumen Semanal (Últimos 7 días con movimiento de órdenes)
            $diaExpr = $isPg ? "TO_CHAR(fecha_creacion, 'FMDay')" : "DATE_FORMAT(fecha_creacion, '%W')";
            $fechaExpr = $isPg ? 'fecha_creacion::date' : 'DATE(fecha_creacion)';
            $sqlResumenSemanal = "SELECT
                {$diaExpr} as dia,
                {$fechaExpr} as fecha,
                COUNT(*) as total_ordenes
            FROM ordenes
            WHERE fecha_creacion IS NOT NULL
              AND LOWER(status) <> 'cancelada'
            GROUP BY {$fechaExpr}, {$diaExpr}
            ORDER BY fecha DESC
            LIMIT 7";
            $semMovRes = $db->goQuery($sqlResumenSemanal);
            $resumenSemanal = [];
            if (!empty($semMovRes) && !isset($semMovRes['status'])) {
                $reversed = array_reverse($semMovRes);
                foreach ($reversed as $r) {
                    $rawDia = strtolower(trim((string) $r['dia']));
                    $diaEspName = $diasEsp[$rawDia] ?? ucfirst($rawDia);
                    $resumenSemanal[] = [
                        'dia'           => $diaEspName,
                        'fecha'         => $r['fecha'],
                        'total_ordenes' => (int) $r['total_ordenes'],
                    ];
                }
            }

            $db->disconnect();

            return $respondJson([
                'success'                  => true,
                'id_empresa'               => $idEmpresa,
                'tasas'                    => $tasas,
                'tiempos_entrega'          => $tiemposEntrega,
                'estado_ordenes'           => $estadoOrdenes,
                'ordenes_por_departamento' => $ordenesPorDepto,
                'ventas_mes_actual'        => $ventasMesActual,
                'estado_disenos'           => $estadoDisenos,
                'resumen_semanal'          => $resumenSemanal,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_dashboard][summary] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al obtener resumen del dashboard: ' . $e->getMessage()], 500);
        }
    });

    // =========================================================================
    // 2. GET /internal/dashboard/{id_empresa}/sales-comparison
    // =========================================================================
    $app->get('/internal/dashboard/{id_empresa}/sales-comparison', function (Request $request, Response $response, array $args) use ($makeRespondJson, $resolverRangoFechas, $resolverRangoComparacion, $validarEmpresa) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = $makeRespondJson($response);

        $idEmpresa = filter_var($args['id_empresa'] ?? ($request->getHeader('Authorization')[0] ?? ''), FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_empresa inválido.'], 400);
        }

        if (!$validarEmpresa($idEmpresa)) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $params = $request->getQueryParams();
        $periodo = trim((string) ($params['periodo'] ?? 'mes_actual'));
        $inicioCustom = !empty($params['inicio']) ? trim((string) $params['inicio']) : null;
        $finCustom = !empty($params['fin']) ? trim((string) $params['fin']) : null;

        $rangoBase = $resolverRangoFechas($periodo, $inicioCustom, $finCustom);

        $compararCon = !empty($params['comparar_con']) ? trim((string) $params['comparar_con']) : null;
        $compInicioCustom = !empty($params['comp_inicio']) ? trim((string) $params['comp_inicio']) : null;
        $compFinCustom = !empty($params['comp_fin']) ? trim((string) $params['comp_fin']) : null;

        $rangoComp = null;
        if ($compararCon !== null && strtolower($compararCon) !== 'ninguno') {
            $rangoComp = $resolverRangoComparacion($rangoBase, $compararCon, $compInicioCustom, $compFinCustom);
        }

        $isPg = DB_DRIVER === 'pgsql';
        $fechaCreacionExpr = $isPg ? 'fecha_creacion::date' : 'DATE(fecha_creacion)';

        try {
            $db = new LocalDB();

            $sqlQuery = "SELECT
                COALESCE(SUM(pago_total), 0) as ventas,
                COALESCE(SUM(pago_abono), 0) as cobrado,
                COALESCE(SUM(CASE WHEN (pago_total - pago_abono - pago_descuento + pago_nota_credito) > 0 
                                 THEN (pago_total - pago_abono - pago_descuento + pago_nota_credito) 
                                 ELSE 0 END), 0) as saldo_por_cobrar,
                COALESCE(SUM(pago_descuento), 0) as descuentos,
                COUNT(*) as total_ordenes
            FROM ordenes
            WHERE {$fechaCreacionExpr} BETWEEN ? AND ?
              AND LOWER(status) <> 'cancelada'";

            // Métricas período base
            $resBase = $db->goQuery($sqlQuery, [$rangoBase['inicio'], $rangoBase['fin']]);
            $rowBase = (!empty($resBase) && !isset($resBase['status'])) ? $resBase[0] : [];
            $vBase = round((float) ($rowBase['ventas'] ?? 0), 2);
            $cBase = round((float) ($rowBase['cobrado'] ?? 0), 2);
            $sBase = round((float) ($rowBase['saldo_por_cobrar'] ?? 0), 2);
            $dBase = round((float) ($rowBase['descuentos'] ?? 0), 2);
            $oBase = (int) ($rowBase['total_ordenes'] ?? 0);
            $ticketBase = $oBase > 0 ? round($vBase / $oBase, 2) : 0;
            $pctCobradoBase = $vBase > 0 ? round(($cBase / $vBase) * 100, 1) : 0;

            $payloadBase = [
                'rango'              => $rangoBase,
                'ventas'             => $vBase,
                'cobrado'            => $cBase,
                'saldo_por_cobrar'   => $sBase,
                'descuentos'         => $dBase,
                'total_ordenes'      => $oBase,
                'ticket_promedio'    => $ticketBase,
                'porcentaje_cobrado' => $pctCobradoBase,
            ];

            // Métricas período de comparación (si aplica)
            $payloadComp = null;
            $comparativa = null;

            if ($rangoComp !== null) {
                $resComp = $db->goQuery($sqlQuery, [$rangoComp['inicio'], $rangoComp['fin']]);
                $rowComp = (!empty($resComp) && !isset($resComp['status'])) ? $resComp[0] : [];
                $vComp = round((float) ($rowComp['ventas'] ?? 0), 2);
                $cComp = round((float) ($rowComp['cobrado'] ?? 0), 2);
                $sComp = round((float) ($rowComp['saldo_por_cobrar'] ?? 0), 2);
                $dComp = round((float) ($rowComp['descuentos'] ?? 0), 2);
                $oComp = (int) ($rowComp['total_ordenes'] ?? 0);
                $ticketComp = $oComp > 0 ? round($vComp / $oComp, 2) : 0;
                $pctCobradoComp = $vComp > 0 ? round(($cComp / $vComp) * 100, 1) : 0;

                $payloadComp = [
                    'rango'              => $rangoComp,
                    'ventas'             => $vComp,
                    'cobrado'            => $cComp,
                    'saldo_por_cobrar'   => $sComp,
                    'descuentos'         => $dComp,
                    'total_ordenes'      => $oComp,
                    'ticket_promedio'    => $ticketComp,
                    'porcentaje_cobrado' => $pctCobradoComp,
                ];

                $deltaVentas = round($vBase - $vComp, 2);
                $pctVarVentas = $vComp > 0 ? round(($deltaVentas / $vComp) * 100, 2) : null;

                $deltaCobrado = round($cBase - $cComp, 2);
                $pctVarCobrado = $cComp > 0 ? round(($deltaCobrado / $cComp) * 100, 2) : null;

                $deltaOrdenes = $oBase - $oComp;
                $pctVarOrdenes = $oComp > 0 ? round(($deltaOrdenes / $oComp) * 100, 2) : null;

                $deltaTicket = round($ticketBase - $ticketComp, 2);
                $pctVarTicket = $ticketComp > 0 ? round(($deltaTicket / $ticketComp) * 100, 2) : null;

                $comparativa = [
                    'diferencia_ventas'            => $deltaVentas,
                    'porcentaje_variacion_ventas'  => $pctVarVentas,
                    'diferencia_cobrado'           => $deltaCobrado,
                    'porcentaje_variacion_cobrado' => $pctVarCobrado,
                    'diferencia_ordenes'           => $deltaOrdenes,
                    'porcentaje_variacion_ordenes' => $pctVarOrdenes,
                    'diferencia_ticket'            => $deltaTicket,
                    'porcentaje_variacion_ticket'  => $pctVarTicket,
                ];
            }

            $db->disconnect();

            return $respondJson([
                'success'      => true,
                'id_empresa'   => $idEmpresa,
                'periodo'      => $payloadBase,
                'comparacion'  => $payloadComp,
                'variacion'    => $comparativa,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_dashboard][sales-comparison] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al calcular comparativa de ventas: ' . $e->getMessage()], 500);
        }
    });

    // =========================================================================
    // 3. GET /internal/dashboard/{id_empresa}/top-products
    // =========================================================================
    $app->get('/internal/dashboard/{id_empresa}/top-products', function (Request $request, Response $response, array $args) use ($makeRespondJson, $resolverRangoFechas, $validarEmpresa) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = $makeRespondJson($response);

        $idEmpresa = filter_var($args['id_empresa'] ?? ($request->getHeader('Authorization')[0] ?? ''), FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_empresa inválido.'], 400);
        }

        if (!$validarEmpresa($idEmpresa)) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $params = $request->getQueryParams();
        $periodo = trim((string) ($params['periodo'] ?? 'semana_actual'));
        $inicioCustom = !empty($params['inicio']) ? trim((string) $params['inicio']) : null;
        $finCustom = !empty($params['fin']) ? trim((string) $params['fin']) : null;

        $rango = $resolverRangoFechas($periodo, $inicioCustom, $finCustom);

        $criterio = strtolower(trim((string) ($params['criterio'] ?? 'producidos')));
        if (!in_array($criterio, ['producidos', 'pedidos'])) {
            $criterio = 'producidos';
        }

        $limit = filter_var($params['limit'] ?? 10, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 10;
        if ($limit > 50) $limit = 50;

        $isPg = DB_DRIVER === 'pgsql';

        try {
            $db = new LocalDB();

            if ($criterio === 'producidos') {
                // Criterio exacto del Dashboard (unidades finalizadas en lotes_detalles_empleados_asignados)
                $fechaTerminadoExpr = $isPg ? 'fecha_terminado::date' : 'DATE(fecha_terminado)';
                $sql = "SELECT
                    p._id as id_producto,
                    p.product AS nombre,
                    COALESCE(SUM(op.cantidad), 0) AS unidades
                FROM ordenes_productos op
                JOIN products p ON p._id = op.id_woo
                JOIN ordenes o ON o._id = op.id_orden
                WHERE o._id IN (
                    SELECT DISTINCT id_orden
                    FROM lotes_detalles_empleados_asignados
                    WHERE {$fechaTerminadoExpr} BETWEEN ? AND ?
                )
                AND (p.fisico = 1 OR p.fisico IS NULL)
                AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
                GROUP BY p._id, p.product
                ORDER BY unidades DESC
                LIMIT {$limit}";
            } else {
                // Criterio de órdenes pedidas/creadas en el rango
                $fechaCreacionExpr = $isPg ? 'o.fecha_creacion::date' : 'DATE(o.fecha_creacion)';
                $sql = "SELECT
                    p._id as id_producto,
                    p.product AS nombre,
                    COALESCE(SUM(op.cantidad), 0) AS unidades
                FROM ordenes_productos op
                JOIN products p ON p._id = op.id_woo
                JOIN ordenes o ON o._id = op.id_orden
                WHERE {$fechaCreacionExpr} BETWEEN ? AND ?
                  AND LOWER(o.status) <> 'cancelada'
                  AND (p.fisico = 1 OR p.fisico IS NULL)
                  AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
                GROUP BY p._id, p.product
                ORDER BY unidades DESC
                LIMIT {$limit}";
            }

            $rows = $db->goQuery($sql, [$rango['inicio'], $rango['fin']]);
            $db->disconnect();

            $ranking = [];
            $totalUnidades = 0;

            if (!empty($rows) && !isset($rows['status'])) {
                foreach ($rows as $r) {
                    $u = (int) $r['unidades'];
                    $totalUnidades += $u;
                    $ranking[] = [
                        'id_producto' => (int) $r['id_producto'],
                        'nombre'      => $r['nombre'] ?: 'Sin nombre',
                        'unidades'    => $u,
                    ];
                }

                // Calcular porcentajes
                foreach ($ranking as $i => $item) {
                    $ranking[$i]['posicion'] = $i + 1;
                    $ranking[$i]['porcentaje'] = $totalUnidades > 0 ? round(($item['unidades'] / $totalUnidades) * 100, 1) : 0;
                }
            }

            return $respondJson([
                'success'        => true,
                'id_empresa'     => $idEmpresa,
                'rango'          => $rango,
                'criterio'       => $criterio,
                'total_unidades' => $totalUnidades,
                'ranking'        => $ranking,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_dashboard][top-products] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al obtener top productos: ' . $e->getMessage()], 500);
        }
    });

};
