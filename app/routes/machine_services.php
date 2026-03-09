<?php
/**
 * Rutas para la gestión de Servicios Técnicos de Máquinas (Mantenimientos, Reparaciones, etc.)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // GET /servicios-maquinas - Listado de servicios con filtros
    $app->get('/servicios-maquinas', function (Request $request, Response $response) {
        $queryParams = $request->getQueryParams();
        $search = $queryParams['search'] ?? '';
        $id_maquina = $queryParams['id_maquina'] ?? '';
        $estado = $queryParams['estado'] ?? '';
        $maquina_tipo = $queryParams['maquina_tipo'] ?? '';

        $localConnection = new LocalDB();
        
        $sql = "SELECT s.*, ci.codigo_interno as maquina_nombre 
                FROM servicios_maquinas s
                LEFT JOIN catalogo_impresoras ci ON s.id_maquina = ci._id AND s.maquina_tipo = 'impresora'
                WHERE 1=1";
        $params = [];

        if (!empty($id_maquina)) {
            $sql .= " AND s.id_maquina = ?";
            $params[] = $id_maquina;
        }

        if (!empty($search)) {
            $sql .= " AND (s.tipo_servicio LIKE ? OR s.descripcion LIKE ? OR s.tecnico LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($estado)) {
            $sql .= " AND s.estado = ?";
            $params[] = $estado;
        }

        if (!empty($maquina_tipo)) {
            $sql .= " AND s.maquina_tipo = ?";
            $params[] = $maquina_tipo;
        }

        $sql .= " ORDER BY s.moment DESC";

        $servicios = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($servicios ?: [], JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    // POST /servicios-maquinas - Registrar servicio
    $app->post('/servicios-maquinas', function (Request $request, Response $response) {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        $localConnection = new LocalDB();

        $sql = "INSERT INTO servicios_maquinas (id_maquina, maquina_tipo, tipo_servicio, descripcion, tecnico, costo, fecha_servicio, proxima_fecha, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['id_maquina'],
            $data['maquina_tipo'] ?? 'impresora',
            $data['tipo_servicio'],
            $data['descripcion'] ?? null,
            $data['tecnico'] ?? null,
            $data['costo'] ?? 0,
            $data['fecha_servicio'] ?? date('Y-m-d H:i:s'),
            $data['proxima_fecha'] ?? null,
            $data['estado'] ?? 'completado'
        ];

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false, 'id' => $result]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // PUT /servicios-maquinas/{id} - Editar servicio
    $app->put('/servicios-maquinas/{id}', function (Request $request, Response $response, array $args) {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        $localConnection = new LocalDB();

        $sql = "UPDATE servicios_maquinas SET tipo_servicio = ?, descripcion = ?, tecnico = ?, costo = ?, fecha_servicio = ?, proxima_fecha = ?, estado = ? WHERE _id = ?";
        $params = [
            $data['tipo_servicio'],
            $data['descripcion'] ?? null,
            $data['tecnico'] ?? null,
            $data['costo'] ?? 0,
            $data['fecha_servicio'],
            $data['proxima_fecha'] ?? null,
            $data['estado'],
            $args['id']
        ];

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // DELETE /servicios-maquinas/{id} - Eliminar
    $app->delete('/servicios-maquinas/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = "DELETE FROM servicios_maquinas WHERE _id = ?";
        $result = $localConnection->goQuery($sql, [$args['id']]);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
