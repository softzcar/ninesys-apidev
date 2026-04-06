<?php
require_once __DIR__ . '/app/model/LocalDB.php';
$db = new LocalDB();
$sql = "SELECT id_empleado, id_orden FROM lotes_detalles_empleados_asignados WHERE fecha_inicio IS NOT NULL AND fecha_terminado IS NULL LIMIT 5";
$res = $db->goQuery($sql);
echo json_encode($res);
?>
