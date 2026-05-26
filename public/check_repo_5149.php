<?php
require __DIR__ . '/../vendor/autoload.php';
require '../app/config.php';
require '../app/app_loader.php';

header('Content-Type: application/json');

$db = new LocalDB();

$output = [];

// 1. Buscar empleados Maru
$sqlEmp = "SELECT _id, nombre, departamento FROM empleados WHERE nombre LIKE '%Maru%' OR nombre LIKE '%maru%'";
$output['empleados'] = $db->goQuery($sqlEmp);

// 2. Buscar reposiciones para la orden 5149
$sqlRepo = "SELECT * FROM reposiciones WHERE id_orden = 5149 ORDER BY _id DESC";
$output['reposiciones'] = $db->goQuery($sqlRepo);

echo json_encode($output, JSON_PRETTY_PRINT);
$db->disconnect();
