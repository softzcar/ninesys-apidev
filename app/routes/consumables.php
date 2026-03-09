<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // GET /consumibles - Listado de consumibles con filtros
    $app->get('/consumibles', function (Request $request, Response $response) {
        $queryParams = $request->getQueryParams();
        $search = $queryParams['search'] ?? '';
        $id = $queryParams['id'] ?? '';
        $estado = $queryParams['estado'] ?? '';
        $maquina_tipo = $queryParams['maquina_tipo'] ?? '';

        $localConnection = new LocalDB();
        
        $sql = "SELECT c.*, ci.codigo_interno as maquina_nombre 
                FROM consumibles c
                LEFT JOIN catalogo_impresoras ci ON c.id_maquina = ci._id AND c.maquina_tipo = 'impresora'
                WHERE 1=1";
        $params = [];

        if (!empty($id)) {
            $sql .= " AND c._id = ?";
            $params[] = $id;
        }

        if (!empty($search)) {
            $sql .= " AND (c.nombre LIKE ? OR c.serial_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($estado)) {
            $sql .= " AND c.estado = ?";
            $params[] = $estado;
        }

        if (!empty($maquina_tipo)) {
            $sql .= " AND c.maquina_tipo = ?";
            $params[] = $maquina_tipo;
        }

        $sql .= " ORDER BY c.moment DESC";

        $consumibles = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($consumibles ?: [], JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    // POST /consumibles - Crear consumible
    $app->post('/consumibles', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "INSERT INTO consumibles (nombre, categoria, serial_id, maquina_tipo, notas, estado) 
                VALUES (?, ?, ?, ?, ?, 'disponible')";
        $params = [
            $data['nombre'],
            $data['categoria'] ?? null,
            $data['serial_id'] ?? null,
            $data['maquina_tipo'] ?? 'impresora',
            $data['notas'] ?? null
        ];

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false, 'id' => $result]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // PUT /consumibles/{id} - Editar consumible
    $app->put('/consumibles/{id}', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE consumibles SET nombre = ?, categoria = ?, serial_id = ?, notas = ? WHERE _id = ?";
        $params = [
            $data['nombre'],
            $data['categoria'] ?? null,
            $data['serial_id'] ?? null,
            $data['notas'] ?? null,
            $args['id']
        ];

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // PATCH /consumibles/{id}/instalar - Instalar en máquina
    $app->patch('/consumibles/{id}/instalar', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE consumibles SET id_maquina = ?, maquina_tipo = ?, estado = 'instalado', fecha_inicio = NOW() WHERE _id = ?";
        $params = [
            $data['id_maquina'],
            $data['maquina_tipo'] ?? 'impresora',
            $args['id']
        ];

        $result = $localConnection->goQuery($sql, $params);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // PATCH /consumibles/{id}/finalizar - Finalizar vida útil
    $app->patch('/consumibles/{id}/finalizar', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "UPDATE consumibles SET estado = 'agotado', fecha_fin = NOW() WHERE _id = ?";
        $result = $localConnection->goQuery($sql, [$args['id']]);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // DELETE /consumibles/{id} - Eliminar
    $app->delete('/consumibles/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = "DELETE FROM consumibles WHERE _id = ?";
        $result = $localConnection->goQuery($sql, [$args['id']]);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(['success' => $result !== false]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
