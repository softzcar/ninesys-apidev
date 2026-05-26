<?php
require_once __DIR__ . '/../src/LocalDB.php';

header('Content-Type: application/json');

$db = new LocalDB();
$sql = "SELECT _id, id_orden, detalle, detalle_emisor, aprobada, moment FROM reposiciones ORDER BY _id DESC LIMIT 10";
$res = $db->goQuery($sql);
echo json_encode($res, JSON_PRETTY_PRINT);
$db->disconnect();
