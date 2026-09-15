<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // BUSCAR ORDENES QUE NO TIENEN NINGUN EMPLEADO ASIGNADO
    $app->get('/ordenes-sin-asignar/{id_vendedor}', function (Request $request, Response $response, array $args) {
        if ($errorResponse = perteneceAAlgunModulo($request, $response, [1, 2, 5])) {
            return $errorResponse;
        }

        $localConnection = new LocalDB();

        // IDOR: el id_vendedor de la URL no se valida contra la sesión real -- un no-admin
        // podía pasar cualquier id_vendedor y ver las órdenes sin asignar de otro vendedor.
        // Si no es admin, se ignora el segmento de la URL y se usa el id de la sesión.
        $idVendedor = ((int) (defined('ACCESO_TOKEN') ? ACCESO_TOKEN : 0) === 1)
            ? (int) $args['id_vendedor']
            : (int) ID_USUARIO_TOKEN;

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
            AND a.responsable = ?
    ";

        $resp = $localConnection->goQuery($sql, [$idVendedor]);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
};
