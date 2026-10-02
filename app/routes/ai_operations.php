<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas de Operaciones de Taller y Control de Producción para el Asistente de IA
 *
 * Permite consultar:
 * - Detalle de órdenes retrasadas del semáforo de entregas (días de retraso, estación/departamento actual, cliente).
 * - Carga de trabajo de diseñadores y propuestas de bocetos esperando respuesta del cliente.
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
            error_log('[ai_operations] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

    // =========================================================================
    // 1. GET /internal/taller/{id_empresa}/ordenes-retrasadas
    // =========================================================================
    $app->get('/internal/taller/{id_empresa}/ordenes-retrasadas', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa) {
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
        $depto = trim((string) ($params['departamento'] ?? ''));
        $limit = filter_var($params['limit'] ?? 20, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;

        $isPg = DB_DRIVER === 'pgsql';
        $fechaEntregaExpr = $isPg ? 'o.fecha_entrega::date' : 'DATE(o.fecha_entrega)';
        $curDateExpr = $isPg ? 'CURRENT_DATE' : 'CURDATE()';
        $diasDiffExpr = $isPg ? "({$curDateExpr} - {$fechaEntregaExpr})" : "DATEDIFF({$curDateExpr}, {$fechaEntregaExpr})";

        try {
            $db = new LocalDB();

            // 1. Conteo total y desglose por departamento donde están retrasadas
            $sqlConteo = "SELECT 
                COALESCE(l.paso, 'Sin departamento') as departamento,
                COUNT(*) as cantidad
            FROM ordenes o
            LEFT JOIN lotes l ON l.id_orden = o._id
            WHERE o.status = 'activa'
              AND {$fechaEntregaExpr} < {$curDateExpr}
            GROUP BY COALESCE(l.paso, 'Sin departamento')
            ORDER BY cantidad DESC";
            $deptoRows = $db->goQuery($sqlConteo);

            $resumenDeptos = [];
            $totalRetrasadas = 0;
            if (!empty($deptoRows) && !isset($deptoRows['status'])) {
                foreach ($deptoRows as $dr) {
                    $cnt = (int) $dr['cantidad'];
                    $totalRetrasadas += $cnt;
                    $resumenDeptos[$dr['departamento']] = $cnt;
                }
            }

            // 2. Detalle ordenado por mayor cantidad de días de retraso
            $whereExtra = "";
            $queryParams = [];
            if ($depto !== '') {
                $whereExtra = " AND l.paso ILIKE ? ";
                $queryParams[] = "%{$depto}%";
            }

            $sqlOrdenes = "SELECT 
                o._id as id_orden,
                COALESCE(o.cliente_nombre, 'Sin cliente') as cliente,
                {$fechaEntregaExpr} as fecha_entrega,
                {$diasDiffExpr} as dias_retraso,
                COALESCE(l.paso, 'Sin departamento') as departamento_actual,
                o.pago_total,
                o.pago_abono,
                COALESCE((o.pago_total - o.pago_abono - o.pago_descuento + o.pago_nota_credito), 0) as saldo_pendiente,
                (SELECT string_agg(DISTINCT p.product, ', ')
                 FROM ordenes_productos op
                 JOIN products p ON p._id = op.id_woo
                 WHERE op.id_orden = o._id
                ) as productos
            FROM ordenes o
            LEFT JOIN lotes l ON l.id_orden = o._id
            WHERE o.status = 'activa'
              AND {$fechaEntregaExpr} < {$curDateExpr}
              {$whereExtra}
            ORDER BY dias_retraso DESC, o._id ASC
            LIMIT {$limit}";

            $ordRows = $db->goQuery($sqlOrdenes, $queryParams);
            $db->disconnect();

            $ordenes = [];
            if (!empty($ordRows) && !isset($ordRows['status'])) {
                foreach ($ordRows as $r) {
                    $ordenes[] = [
                        'id_orden'            => (int) $r['id_orden'],
                        'cliente'             => $r['cliente'],
                        'fecha_entrega'       => $r['fecha_entrega'],
                        'dias_retraso'        => (int) $r['dias_retraso'],
                        'departamento_actual' => $r['departamento_actual'],
                        'pago_total'          => round((float) $r['pago_total'], 2),
                        'pago_abono'          => round((float) $r['pago_abono'], 2),
                        'saldo_pendiente'     => round((float) $r['saldo_pendiente'], 2),
                        'productos'           => $r['productos'] ?: 'Sin producto especificado',
                    ];
                }
            }

            return $respondJson([
                'success'                  => true,
                'id_empresa'               => $idEmpresa,
                'total_retrasadas'         => $totalRetrasadas,
                'resumen_por_departamento' => $resumenDeptos,
                'total_mostradas'          => count($ordenes),
                'ordenes'                  => $ordenes,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_operations][ordenes-retrasadas] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar órdenes retrasadas: ' . $e->getMessage()], 500);
        }
    });

    // =========================================================================
    // 2. GET /internal/taller/{id_empresa}/disenadores-carga
    // =========================================================================
    $app->get('/internal/taller/{id_empresa}/disenadores-carga', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa) {
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

        try {
            $db = new LocalDB();

            // 1. Carga por diseñador
            $sqlDisenadores = "SELECT 
                d.id_empleado,
                COALESCE(u.nombre, 'Sin asignar') as disenador,
                COUNT(*) FILTER (WHERE d.terminado = 0) as disenos_activos,
                COUNT(*) FILTER (WHERE d.terminado = 1) as disenos_terminados,
                COUNT(DISTINCT r._id) FILTER (WHERE r.estatus = 'Esperando Respuesta') as propuestas_esperando_cliente
            FROM disenos d
            LEFT JOIN api_empresas.empresas_usuarios u ON u.id_usuario = d.id_empleado
            LEFT JOIN revisiones r ON r.id_diseno = d._id
            GROUP BY d.id_empleado, u.nombre
            ORDER BY disenos_activos DESC";
            $disRows = $db->goQuery($sqlDisenadores);

            $disenadores = [];
            $totalActivos = 0;
            $totalEsperando = 0;

            if (!empty($disRows) && !isset($disRows['status'])) {
                foreach ($disRows as $dr) {
                    $act = (int) $dr['disenos_activos'];
                    $term = (int) $dr['disenos_terminados'];
                    $esp = (int) $dr['propuestas_esperando_cliente'];
                    $totalActivos += $act;
                    $totalEsperando += $esp;

                    $disenadores[] = [
                        'id_empleado'                  => $dr['id_empleado'] !== null ? (int) $dr['id_empleado'] : null,
                        'disenador'                    => $dr['disenador'],
                        'disenos_activos'              => $act,
                        'disenos_terminados'           => $term,
                        'propuestas_esperando_cliente' => $esp,
                    ];
                }
            }

            // 2. Propuestas específicas en espera de respuesta
            $sqlPropuestas = "SELECT 
                r._id as id_revision,
                r.id_orden,
                r.id_diseno,
                r.url_image,
                r.moment as fecha_propuesta,
                COALESCE(u.nombre, 'Sin asignar') as disenador,
                COALESCE(o.cliente_nombre, 'Sin cliente') as cliente
            FROM revisiones r
            JOIN disenos d ON d._id = r.id_diseno
            JOIN ordenes o ON o._id = r.id_orden
            LEFT JOIN api_empresas.empresas_usuarios u ON u.id_usuario = d.id_empleado
            WHERE r.estatus = 'Esperando Respuesta'
              AND r.url_image IS NOT NULL AND r.url_image <> ''
            ORDER BY r.moment DESC
            LIMIT 15";
            $propRows = $db->goQuery($sqlPropuestas);
            $db->disconnect();

            $propuestasEnEspera = [];
            if (!empty($propRows) && !isset($propRows['status'])) {
                foreach ($propRows as $pr) {
                    $propuestasEnEspera[] = [
                        'id_revision'     => (int) $pr['id_revision'],
                        'id_orden'        => (int) $pr['id_orden'],
                        'cliente'         => $pr['cliente'],
                        'disenador'       => $pr['disenador'],
                        'fecha_propuesta' => $pr['fecha_propuesta'],
                        'url_image'       => $pr['url_image'],
                    ];
                }
            }

            return $respondJson([
                'success'                       => true,
                'id_empresa'                    => $idEmpresa,
                'total_disenos_activos'         => $totalActivos,
                'total_esperando_cliente'       => $totalEsperando,
                'disenadores'                   => $disenadores,
                'propuestas_recientes_en_espera'=> $propuestasEnEspera,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_operations][disenadores-carga] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar carga de diseñadores: ' . $e->getMessage()], 500);
        }
    });

};
