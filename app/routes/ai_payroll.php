<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas de Nómina y Comisiones por Empleado para el Asistente de IA
 *
 * Permite consultar:
 * - Comisiones y pagos pendientes acumulados por liquidar a los empleados.
 * - Historial de comisiones pagadas por trabajador o resumen general de la nómina pendiente.
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
            error_log('[ai_payroll] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

    // =========================================================================
    // GET /internal/nomina/{id_empresa}/comisiones
    // =========================================================================
    $app->get('/internal/nomina/{id_empresa}/comisiones', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa) {
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
        $estado = strtolower(trim((string) ($params['estado'] ?? 'pendientes')));
        if (!in_array($estado, ['pendientes', 'pagadas', 'todas'])) {
            $estado = 'pendientes';
        }

        $desde = !empty($params['desde']) ? trim((string) $params['desde']) : null;
        $hasta = !empty($params['hasta']) ? trim((string) $params['hasta']) : null;
        $limit = filter_var($params['limit'] ?? 20, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;

        $isPg = DB_DRIVER === 'pgsql';

        try {
            $db = new LocalDB();

            // CASO A: Consulta de un empleado específico
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
                        'success' => false,
                        'message' => "No se encontró ningún empleado que coincida con '{$empleadoParam}'.",
                    ], 404);
                }

                $targetId = (int) $userRow[0]['id_usuario'];
                $targetNombre = $userRow[0]['nombre'];
                $targetDepto = $userRow[0]['departamento'];

                $whereParts = ["p.id_empleado = {$targetId}"];

                if ($estado === 'pendientes') {
                    $whereParts[] = "p.fecha_pago IS NULL";
                } elseif ($estado === 'pagadas') {
                    $whereParts[] = "p.fecha_pago IS NOT NULL";
                }

                $fechaCol = $estado === 'pagadas' ? 'p.fecha_pago' : 'p.moment';
                $fechaColExpr = $isPg ? "{$fechaCol}::date" : "DATE({$fechaCol})";
                if ($desde && $hasta) {
                    $whereParts[] = "{$fechaColExpr} BETWEEN '{$desde}' AND '{$hasta}'";
                }

                $whereSql = implode(' AND ', $whereParts);

                // 1. Totales y desglose por concepto
                $sqlConceptos = "SELECT 
                    p.detalle,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(p.monto_pago), 0) as monto
                FROM pagos p
                WHERE {$whereSql}
                GROUP BY p.detalle
                ORDER BY monto DESC";
                $concRows = $db->goQuery($sqlConceptos);

                $desglose = [];
                $granTotal = 0;
                $granTareas = 0;

                if (!empty($concRows) && !isset($concRows['status'])) {
                    foreach ($concRows as $cr) {
                        $m = round((float) $cr['monto'], 2);
                        $cnt = (int) $cr['cantidad'];
                        $granTotal += $m;
                        $granTareas += $cnt;
                        $desglose[] = [
                            'concepto' => $cr['detalle'] ?: 'Varios',
                            'cantidad' => $cnt,
                            'monto'    => $m,
                        ];
                    }
                }

                // 2. Detalle de las tareas más recientes
                $sqlDetalle = "SELECT 
                    p._id as id_pago,
                    p.id_orden,
                    p.detalle,
                    p.monto_pago,
                    p.comision,
                    p.moment as fecha_tarea,
                    p.fecha_pago,
                    p.estatus
                FROM pagos p
                WHERE {$whereSql}
                ORDER BY p._id DESC
                LIMIT {$limit}";
                $detRows = $db->goQuery($sqlDetalle);
                $db->disconnect();

                $tareas = [];
                if (!empty($detRows) && !isset($detRows['status'])) {
                    foreach ($detRows as $tr) {
                        $tareas[] = [
                            'id_pago'     => (int) $tr['id_pago'],
                            'id_orden'    => $tr['id_orden'] !== null ? (int) $tr['id_orden'] : null,
                            'concepto'    => $tr['detalle'],
                            'monto_pago'  => round((float) $tr['monto_pago'], 2),
                            'comision'    => round((float) $tr['comision'], 2),
                            'fecha_tarea' => $tr['fecha_tarea'],
                            'fecha_pago'  => $tr['fecha_pago'],
                            'estatus'     => $tr['estatus'],
                        ];
                    }
                }

                return $respondJson([
                    'success'               => true,
                    'id_empresa'            => $idEmpresa,
                    'empleado'              => [
                        'id_usuario'   => $targetId,
                        'nombre'       => $targetNombre,
                        'departamento' => $targetDepto,
                    ],
                    'estado_filtro'         => $estado,
                    'monto_total'           => round($granTotal, 2),
                    'total_tareas'          => $granTareas,
                    'desglose_por_concepto' => $desglose,
                    'tareas'                => $tareas,
                ], 200);
            }

            // CASO B: Resumen de toda la nómina/personal de la empresa
            $whereParts = ["1=1"];
            if ($estado === 'pendientes') {
                $whereParts[] = "p.fecha_pago IS NULL";
            } elseif ($estado === 'pagadas') {
                $whereParts[] = "p.fecha_pago IS NOT NULL";
            }

            $fechaCol = $estado === 'pagadas' ? 'p.fecha_pago' : 'p.moment';
            $fechaColExpr = $isPg ? "{$fechaCol}::date" : "DATE({$fechaCol})";
            if ($desde && $hasta) {
                $whereParts[] = "{$fechaColExpr} BETWEEN '{$desde}' AND '{$hasta}'";
            }

            $whereSql = implode(' AND ', $whereParts);

            $sqlNomina = "SELECT 
                p.id_empleado,
                COALESCE(u.nombre, 'Sin nombre') as nombre,
                COALESCE(u.departamento, 'N/A') as departamento,
                COUNT(*) as total_tareas,
                COALESCE(SUM(p.monto_pago), 0) as monto_total
            FROM pagos p
            JOIN api_empresas.empresas_usuarios u ON u.id_usuario = p.id_empleado
            WHERE {$whereSql}
            GROUP BY p.id_empleado, u.nombre, u.departamento
            ORDER BY monto_total DESC";

            $rows = $db->goQuery($sqlNomina);
            $db->disconnect();

            $personal = [];
            $granTotalMonto = 0;
            $granTotalTareas = 0;

            if (!empty($rows) && !isset($rows['status'])) {
                foreach ($rows as $r) {
                    $m = round((float) $r['monto_total'], 2);
                    $cnt = (int) $r['total_tareas'];
                    $granTotalMonto += $m;
                    $granTotalTareas += $cnt;
                    $personal[] = [
                        'id_empleado'  => (int) $r['id_empleado'],
                        'nombre'       => $r['nombre'],
                        'departamento' => $r['departamento'],
                        'total_tareas' => $cnt,
                        'monto_total'  => $m,
                    ];
                }
            }

            return $respondJson([
                'success'           => true,
                'id_empresa'        => $idEmpresa,
                'estado_filtro'     => $estado,
                'gran_total_monto'  => round($granTotalMonto, 2),
                'gran_total_tareas' => $granTotalTareas,
                'personal'          => $personal,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_payroll][comisiones] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar comisiones: ' . $e->getMessage()], 500);
        }
    });

};
