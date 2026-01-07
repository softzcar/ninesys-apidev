<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // BUSCAR ORDENES QUE NO TIENEN NINGUN EMPLEADO ASIGNADO
    $app->get('/ordenes-sin-asignar/{id_vendedor}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        //  Verificar existencia de la orden
        $sql = "SELECT
            a._id id_orden,
            cliente_nombre
        FROM
            ordenes a
        LEFT JOIN
            lotes_detalles_empleados_asignados b ON b.id_orden = a._id
        WHERE
            b.id_orden IS NULL 
            AND (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') 
            AND a.responsable = {$args['id_vendedor']}
    ";

        $resp = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
};
