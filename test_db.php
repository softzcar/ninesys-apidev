<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$db = new LocalDB();
$res = $db->goQuery("SELECT * FROM inventario_movimientos WHERE id_orden = 4519");
print_r($res);
