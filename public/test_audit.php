<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../app/model/LocalDB.php';

$localConnection = new LocalDB();
$id = 12;

echo json_encode([
    'test' => 'endpoint de prueba',
    'orden_id' => $id,
    'query' => "SELECT accion, id_admin, nombre_admin, motivo, fecha FROM ordenes_auditoria WHERE id_orden = $id ORDER BY fecha DESC LIMIT 1",
    'result' => $localConnection->goQuery("SELECT accion, id_admin, nombre_admin, motivo, fecha FROM ordenes_auditoria WHERE id_orden = $id ORDER BY fecha DESC LIMIT 1")
], JSON_PRETTY_PRINT);

$localConnection->disconnect();
