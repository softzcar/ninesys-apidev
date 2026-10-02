<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas de Inventario, Telas y Consumibles para el Asistente de IA
 *
 * Permite consultar:
 * - Existencias y stock de telas, rollos/papel DTF, tintas e insumos generales.
 * - Alertas de materiales agotados o con bajo stock.
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
            error_log('[ai_inventory] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

    // =========================================================================
    // GET /internal/inventario/{id_empresa}/stock
    // =========================================================================
    $app->get('/internal/inventario/{id_empresa}/stock', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa) {
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
        $tipo = strtolower(trim((string) ($params['tipo'] ?? '')));
        $buscar = trim((string) ($params['buscar'] ?? ''));
        $depto = trim((string) ($params['departamento'] ?? ''));
        $soloBajoStock = !empty($params['solo_bajo_stock']) && $params['solo_bajo_stock'] !== '0' && $params['solo_bajo_stock'] !== 'false';
        $umbral = filter_var($params['umbral'] ?? 5, FILTER_VALIDATE_FLOAT);
        if ($umbral === false || $umbral < 0) $umbral = 5.0;

        $limit = filter_var($params['limit'] ?? 50, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) $limit = 50;
        if ($limit > 100) $limit = 100;

        try {
            $db = new LocalDB();

            // 1. Resumen global de stock por tipo de insumo
            $resumenRows = $db->goQuery("SELECT 
                tipo_insumo, 
                COUNT(*) as total_items, 
                COALESCE(SUM(cantidad), 0) as stock_total
            FROM inventario
            WHERE eliminado = 0
            GROUP BY tipo_insumo
            ORDER BY total_items DESC");

            $resumenPorTipo = [];
            if (!empty($resumenRows) && !isset($resumenRows['status'])) {
                foreach ($resumenRows as $rr) {
                    $resumenPorTipo[$rr['tipo_insumo']] = [
                        'total_items' => (int) $rr['total_items'],
                        'stock_total' => round((float) $rr['stock_total'], 2),
                    ];
                }
            }

            // 2. Consulta filtrada de ítems
            $whereParts = ["eliminado = 0"];
            $queryParams = [];

            if ($tipo !== '' && !in_array($tipo, ['todos', 'todas', 'all', '*'])) {
                $whereParts[] = "tipo_insumo = ?";
                $queryParams[] = $tipo;
            }

            if ($buscar !== '') {
                $whereParts[] = "(insumo ILIKE ? OR sku ILIKE ? OR color ILIKE ?)";
                $queryParams[] = "%{$buscar}%";
                $queryParams[] = "%{$buscar}%";
                $queryParams[] = "%{$buscar}%";
            }

            if ($depto !== '') {
                $whereParts[] = "departamento ILIKE ?";
                $queryParams[] = "%{$depto}%";
            }

            $whereSql = implode(' AND ', $whereParts);
            $havingSql = $soloBajoStock ? "HAVING SUM(cantidad) <= {$umbral}" : "";

            $sql = "SELECT 
                sku,
                insumo,
                tipo_insumo,
                unidad,
                COALESCE(departamento, 'General') as departamento,
                COALESCE(SUM(cantidad), 0) as stock_total,
                COUNT(*) as items_lotes,
                MIN(cantidad) as stock_minimo_lote,
                MAX(cantidad) as stock_maximo_lote
            FROM inventario
            WHERE {$whereSql}
            GROUP BY sku, insumo, tipo_insumo, unidad, departamento
            {$havingSql}
            ORDER BY stock_total " . ($soloBajoStock ? "ASC" : "DESC") . "
            LIMIT {$limit}";

            $rows = $db->goQuery($sql, $queryParams);
            $db->disconnect();

            $items = [];
            if (!empty($rows) && !isset($rows['status'])) {
                foreach ($rows as $r) {
                    $st = round((float) $r['stock_total'], 2);
                    $items[] = [
                        'sku'           => $r['sku'] ?: 'SIN-SKU',
                        'insumo'        => $r['insumo'] ?: 'Sin nombre',
                        'tipo_insumo'   => $r['tipo_insumo'],
                        'unidad'        => $r['unidad'] ?: 'Und',
                        'departamento'  => $r['departamento'],
                        'stock_total'   => $st,
                        'rollos_lotes'  => (int) $r['items_lotes'],
                        'alerta_bajo'   => $st <= $umbral,
                        'agotado'       => $st <= 0,
                    ];
                }
            }

            return $respondJson([
                'success'           => true,
                'id_empresa'        => $idEmpresa,
                'resumen_por_tipo'  => $resumenPorTipo,
                'filtros'           => [
                    'tipo'            => $tipo ?: 'todos',
                    'buscar'          => $buscar ?: null,
                    'solo_bajo_stock' => $soloBajoStock,
                    'umbral'          => $umbral,
                ],
                'total_resultados'  => count($items),
                'items'             => $items,
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_inventory][stock] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al consultar inventario: ' . $e->getMessage()], 500);
        }
    });

};
