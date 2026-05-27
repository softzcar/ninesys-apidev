<?php
require_once __DIR__ . '/../src/LocalDB.php';

$db = new LocalDB();
$sql = "SELECT _id, id_orden, detalle, detalle_emisor, aprobada, moment FROM reposiciones WHERE id_orden = 5176 ORDER BY _id DESC LIMIT 5";
$res = $db->goQuery($sql);
print_r($res);
$db->disconnect();
