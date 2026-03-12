<?php
require_once __DIR__ . '/app/lib/LocalDB.php';

$db = new LocalDB();

echo "--- BORRADORES (ordenes_tmp) ---\n";
$sql_tmp = "SELECT _id, tipo, id_empleado FROM ordenes_tmp LIMIT 10";
$res_tmp = $db->goQuery($sql_tmp);
print_r($res_tmp);

echo "\n--- PRESUPUESTOS (presupuestos) ---\n";
$sql_pres = "SELECT _id, cliente_nombre, status FROM presupuestos LIMIT 10";
$res_pres = $db->goQuery($sql_pres);
print_r($res_pres);

$db->disconnect();
