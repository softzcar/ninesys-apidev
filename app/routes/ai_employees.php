<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas de Empleados y Ventas por Empleado para el Asistente de IA
 *
 * Permite al agente consultar:
 * - Lista de empleados, nombres, estatus y departamentos asignados.
 * - Desempeño y volumen de ventas/órdenes por empleado o ranking de vendedores.
 */
return function (App $app) {

    $makeRespondJson = function (Response $response) {
        return function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };
    };

    $validarEmpresa = function (int $idEmpresa): bool {
        try {
            $central = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $central->goQuery('SELECT id_empresa FROM empresas WHERE id_empresa = ? AND activo = 1', [$idEmpresa]);
            $central->disconnect();
            return !empty($rows) && !isset($rows['status']);
        } catch (\Throwable $e) {
            error_log('[ai_employees] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

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
        ];
    };

    // =========================================================================
    // 1. GET /internal/empleados/{id_empresa}/list
    // =========================================================================
    $app->get('/internal/empleados/{id_empresa}/list', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa) {
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
        $buscar = trim((string) ($params['buscar'] ?? ''));
        $depto = trim((string) ($params['departamento'] ?? ''));
        $soloActivos = isset($params['solo_activos']) ? (int) $params['solo_activos'] : 1;

        try {
            $db = new LocalDB();

            $whereParts = ["eue.id_empresa = ?"];
            $queryParams = [$idEmpresa];

            if ($soloActivos === 1) {
                $whereParts[] = "eue.activo = 1";
            }

            if ($buscar !== '') {
                $whereParts[] = "(u.nombre ILIKE ? OR u.email ILIKE ?)";
                $queryParams[] = "%{$buscar}%";
                $queryParams[] = "%{$buscar}%";
            }

            if ($depto !== '') {
                $whereParts[] = "(u.departamento ILIKE ? OR d.departamento ILIKE ?)";
                $queryParams[] = "%{$depto}%";
                $queryParams[] = "%{$depto}%";
            }

            $whereSql = implode(' AND ', $whereParts);

            $sql = "SELECT
                u.id_usuario,
                u.nombre,
                u.email,
                u.telefono,
                u.departamento as departamento_principal,
                u.acceso,
                eue.activo as empresa_activo,
                COALESCE(
                    json_agg(DISTINCT d.departamento) FILTER (WHERE d.departamento IS NOT NULL),
                    '[]'::json
                ) as departamentos_asignados,
                COALESCE(
                    json_agg(DISTINCT d._id) FILTER (WHERE d._id IS NOT NULL),
                    '[]'::json
                ) as departamentos_ids
            FROM api_empresas.empresas_usuarios u
            JOIN api_empresas.empresas_usuarios_empresas eue 
                ON eue.id_usuario = u.id_usuario
            LEFT JOIN api_empresas.empresas_usuarios_departamentos eud 
                ON eud.id_empleado = u.id_usuario AND eud.id_empresa = ?
            LEFT JOIN departamentos d 
                ON d._id = eud.id_departamento AND d.eliminado = 0
            WHERE {$whereSql}
            GROUP BY u.id_usuario, u.nombre, u.email, u.telefono, u.departamento, u.acceso, eue.activo
            ORDER BY u.nombre ASC";

            // Pasamos $idEmpresa para el join de eud más los params del WHERE
            $allParams = array_merge([$idEmpresa], $queryParams);
            $rows = $db->goQuery($sql, $allParams);
            $db->disconnect();

            $empleados = [];
            if (!empty($rows) && !isset($rows['status'])) {
                foreach ($rows as $r) {
                    $depsAsig = json_decode($r['departamentos_asignados'] ?? '[]', true) ?: [];
                    // IDs (fijos en Ninesys): imprime filtra por su lista blanca de departamentos con estos, no por nombre.
                    $depsIds = array_map('intval', json_decode($r['departamentos_ids'] ?? '[]', true) ?: []);
                    $empleados[] = [
                        'id_usuario'              => (int) $r['id_usuario'],
                        'nombre'                  => $r['nombre'] ?: 'Sin nombre',
                        'email'                   => $r['email'] ?: '',
                        'telefono'                => $r['telefono'] ?: '',
                        'departamento_principal'  => $r['departamento_principal'] ?: 'Sin departamento',
                        'departamentos_asignados' => $depsAsig,
                        'departamentos_ids'       => $depsIds,
                        'activo'                  => (int) ($r['empresa_activo'] ?? 0) === 1,
                        'acceso_sistema'          => (int) ($r['acceso'] ?? 0) === 1,
                        'status'                  => ((int) ($r['empresa_activo'] ?? 0) === 1) ? 'activo' : 'inactivo',
                    ];
                }
            }

            return $respondJson([
                'success'   => true,
                'total'     => count($empleados),
                'empleados' => $empleados,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_employees][list] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al listar empleados: ' . $e->getMessage()], 500);
        }
    });

    // =========================================================================
    // 2. GET /internal/empleados/{id_empresa}/ventas
    // =========================================================================
    $app->get('/internal/empleados/{id_empresa}/ventas', function (Request $request, Response $response, array $args) use ($makeRespondJson, $resolverRangoFechas, $validarEmpresa) {
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
        $empleadoParam = trim((string) ($params['empleado'] ?? ''));
        $periodo = trim((string) ($params['periodo'] ?? 'mes_actual'));
        $inicioCustom = !empty($params['inicio']) ? trim((string) $params['inicio']) : null;
        $finCustom = !empty($params['fin']) ? trim((string) $params['fin']) : null;

        $rango = $resolverRangoFechas($periodo, $inicioCustom, $finCustom);

        $isPg = DB_DRIVER === 'pgsql';
        $fechaExpr = $isPg ? 'o.fecha_creacion::date' : 'DATE(o.fecha_creacion)';

        try {
            $db = new LocalDB();

            // CASO A: Se especificó un empleado concreto (buscar por ID o nombre)
            if ($empleadoParam !== '') {
                $idUsuario = filter_var($empleadoParam, FILTER_VALIDATE_INT);
                $whereUser = $idUsuario !== false
                    ? "u.id_usuario = {$idUsuario}"
                    : "u.nombre ILIKE '%" . addslashes($empleadoParam) . "%' OR u.email ILIKE '%" . addslashes($empleadoParam) . "%'";

                $userRow = $db->goQuery("
                    SELECT u.id_usuario, u.nombre, u.departamento
                    FROM api_empresas.empresas_usuarios u
                    JOIN api_empresas.empresas_usuarios_empresas eue ON eue.id_usuario = u.id_usuario AND eue.id_empresa = {$idEmpresa}
                    WHERE {$whereUser}
                    LIMIT 1
                ");

                if (empty($userRow) || isset($userRow['status'])) {
                    $db->disconnect();
                    return $respondJson([
                        'success'  => false,
                        'message'  => "No se encontró ningún empleado que coincida con '{$empleadoParam}' en esta empresa.",
                        'rango'    => $rango,
                    ], 404);
                }

                $targetId = (int) $userRow[0]['id_usuario'];
                $targetNombre = $userRow[0]['nombre'];
                $targetDepto = $userRow[0]['departamento'];

                // Métricas financieras del vendedor
                $sqlVendedor = "SELECT 
                    COUNT(*) as total_ordenes,
                    COALESCE(SUM(o.pago_total), 0) as total_ventas,
                    COALESCE(SUM(o.pago_abono), 0) as total_cobrado,
                    COALESCE(SUM(CASE WHEN (o.pago_total - o.pago_abono - o.pago_descuento + o.pago_nota_credito) > 0 
                                     THEN (o.pago_total - o.pago_abono - o.pago_descuento + o.pago_nota_credito) 
                                     ELSE 0 END), 0) as saldo_por_cobrar,
                    COALESCE(SUM(o.pago_descuento), 0) as total_descuentos
                FROM ordenes o
                WHERE o.responsable = ?
                  AND {$fechaExpr} BETWEEN ? AND ?
                  AND LOWER(o.status) <> 'cancelada'";

                $vendRes = $db->goQuery($sqlVendedor, [$targetId, $rango['inicio'], $rango['fin']]);
                $vRow = (!empty($vendRes) && !isset($vendRes['status'])) ? $vendRes[0] : [];

                $totOrd = (int) ($vRow['total_ordenes'] ?? 0);
                $totVen = round((float) ($vRow['total_ventas'] ?? 0), 2);
                $totCob = round((float) ($vRow['total_cobrado'] ?? 0), 2);
                $totSal = round((float) ($vRow['saldo_por_cobrar'] ?? 0), 2);
                $totDes = round((float) ($vRow['total_descuentos'] ?? 0), 2);
                $ticket = $totOrd > 0 ? round($totVen / $totOrd, 2) : 0;
                $pctCob = $totVen > 0 ? round(($totCob / $totVen) * 100, 1) : 0;

                // Desglose por estado de orden
                $sqlStatus = "SELECT status, COUNT(*) as cantidad
                    FROM ordenes o
                    WHERE o.responsable = ?
                      AND {$fechaExpr} BETWEEN ? AND ?
                      AND LOWER(o.status) <> 'cancelada'
                    GROUP BY status";
                $stRows = $db->goQuery($sqlStatus, [$targetId, $rango['inicio'], $rango['fin']]);
                $porEstado = [];
                if (!empty($stRows) && !isset($stRows['status'])) {
                    foreach ($stRows as $st) {
                        $porEstado[$st['status']] = (int) $st['cantidad'];
                    }
                }

                // Últimas 5 órdenes vendidas
                $sqlUltimas = "SELECT 
                    o._id as id_orden,
                    COALESCE(o.cliente_nombre, 'Sin cliente') as cliente,
                    o.pago_total,
                    o.status,
                    {$fechaExpr} as fecha
                FROM ordenes o
                WHERE o.responsable = ?
                  AND {$fechaExpr} BETWEEN ? AND ?
                  AND LOWER(o.status) <> 'cancelada'
                ORDER BY o._id DESC
                LIMIT 5";
                $ultRows = $db->goQuery($sqlUltimas, [$targetId, $rango['inicio'], $rango['fin']]);
                $ultimasOrdenes = [];
                if (!empty($ultRows) && !isset($ultRows['status'])) {
                    foreach ($ultRows as $uo) {
                        $ultimasOrdenes[] = [
                            'id_orden' => (int) $uo['id_orden'],
                            'cliente'  => $uo['cliente'],
                            'monto'    => round((float) $uo['pago_total'], 2),
                            'status'   => $uo['status'],
                            'fecha'    => $uo['fecha'],
                        ];
                    }
                }

                $db->disconnect();

                return $respondJson([
                    'success'  => true,
                    'rango'    => $rango,
                    'empleado' => [
                        'id_usuario'   => $targetId,
                        'nombre'       => $targetNombre,
                        'departamento' => $targetDepto,
                    ],
                    'metricas' => [
                        'total_ordenes'      => $totOrd,
                        'total_ventas'       => $totVen,
                        'total_cobrado'      => $totCob,
                        'saldo_por_cobrar'   => $totSal,
                        'total_descuentos'   => $totDes,
                        'ticket_promedio'    => $ticket,
                        'porcentaje_cobrado' => $pctCob,
                    ],
                    'por_estado'       => $porEstado,
                    'ultimas_ordenes'  => $ultimasOrdenes,
                ], 200);
            }

            // CASO B: No se especificó empleado -> Devolver ranking global de vendedores
            $sqlRanking = "SELECT 
                o.responsable as id_usuario,
                COALESCE(u.nombre, 'Sin vendedor') as nombre,
                COALESCE(u.departamento, 'N/A') as departamento,
                COUNT(*) as total_ordenes,
                COALESCE(SUM(o.pago_total), 0) as total_ventas,
                COALESCE(SUM(o.pago_abono), 0) as total_cobrado,
                COALESCE(SUM(CASE WHEN (o.pago_total - o.pago_abono - o.pago_descuento + o.pago_nota_credito) > 0 
                                 THEN (o.pago_total - o.pago_abono - o.pago_descuento + o.pago_nota_credito) 
                                 ELSE 0 END), 0) as saldo_por_cobrar
            FROM ordenes o
            LEFT JOIN api_empresas.empresas_usuarios u ON u.id_usuario = o.responsable
            WHERE {$fechaExpr} BETWEEN ? AND ?
              AND LOWER(o.status) <> 'cancelada'
            GROUP BY o.responsable, u.nombre, u.departamento
            ORDER BY total_ordenes DESC";

            $rows = $db->goQuery($sqlRanking, [$rango['inicio'], $rango['fin']]);
            $db->disconnect();

            $ranking = [];
            $granTotalOrdenes = 0;
            $granTotalVentas = 0;
            $granTotalCobrado = 0;

            if (!empty($rows) && !isset($rows['status'])) {
                foreach ($rows as $r) {
                    $totOrd = (int) $r['total_ordenes'];
                    $totVen = round((float) $r['total_ventas'], 2);
                    $totCob = round((float) $r['total_cobrado'], 2);
                    $totSal = round((float) $r['saldo_por_cobrar'], 2);
                    $ticket = $totOrd > 0 ? round($totVen / $totOrd, 2) : 0;

                    $granTotalOrdenes += $totOrd;
                    $granTotalVentas += $totVen;
                    $granTotalCobrado += $totCob;

                    $ranking[] = [
                        'id_usuario'       => $r['id_usuario'] !== null ? (int) $r['id_usuario'] : null,
                        'nombre'           => $r['nombre'],
                        'departamento'     => $r['departamento'],
                        'total_ordenes'    => $totOrd,
                        'total_ventas'     => $totVen,
                        'total_cobrado'    => $totCob,
                        'saldo_por_cobrar' => $totSal,
                        'ticket_promedio'  => $ticket,
                    ];
                }

                foreach ($ranking as $i => $item) {
                    $ranking[$i]['posicion'] = $i + 1;
                    $ranking[$i]['porcentaje_ventas'] = $granTotalVentas > 0
                        ? round(($item['total_ventas'] / $granTotalVentas) * 100, 1)
                        : 0;
                }
            }

            return $respondJson([
                'success' => true,
                'rango'   => $rango,
                'totales' => [
                    'total_ordenes' => $granTotalOrdenes,
                    'total_ventas'  => round($granTotalVentas, 2),
                    'total_cobrado' => round($granTotalCobrado, 2),
                ],
                'ranking' => $ranking,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_employees][ventas] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar ventas por empleado: ' . $e->getMessage()], 500);
        }
    });

};
