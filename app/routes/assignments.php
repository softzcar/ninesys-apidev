<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** Asignacion */
  // Obtener datos para la asignaciond e empelados
  $app->get('/asignacion/ordenes', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'inicio';
    $object['fields'][2]['label'] = 'Inicio';

    $object['fields'][3]['key'] = 'entrega';
    $object['fields'][3]['label'] = 'Entrega';

    $object['fields'][4]['key'] = 'status';
    $object['fields'][4]['label'] = 'Estatus';

    $object['fields'][4]['key'] = 'asignar';
    $object['fields'][4]['label'] = 'Asignar';

    $sql = "SELECT a._id orden, a._id asignar, a.cliente_nombre cliente, a.fecha_inicio inicio, a.fecha_entrega entrega, a.status estatus, b.terminado FROM ordenes a JOIN disenos b ON a._id = b.id_orden WHERE (a.status = 'activa' OR a.status = 'terminada' OR a.status = 'En espera' OR status = 'pausada') AND b.terminado = 1 OR b.tipo = 'no' ORDER BY a._id DESC";

    $object['items'] = $localConnection->goQuery($sql);
    $object['data'] = $object['items'];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

}; // Fin de la función que envuelve las rutas
