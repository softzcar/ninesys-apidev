<?php
// Script de debug temporal para verificar consulta de auditoría
require_once __DIR__ . '/public/model/LocalDB.php';

$localConnection = new LocalDB();
$id = 12;

$sqlAuditoria = "SELECT accion, id_admin, nombre_admin, motivo, fecha 
                 FROM ordenes_auditoria 
                 WHERE id_orden = " . $id . " 
                 ORDER BY fecha DESC 
                 LIMIT 1";

echo "SQL: " . $sqlAuditoria . "\n\n";

$auditoria = $localConnection->goQuery($sqlAuditoria);

echo "Result Type: " . gettype($auditoria) . "\n";
echo "Is Empty: " . (empty($auditoria) ? 'YES' : 'NO') . "\n";
echo "Count: " . (is_array($auditoria) ? count($auditoria) : 'N/A') . "\n\n";
echo "Raw Result:\n";
var_dump($auditoria);

if (!empty($auditoria)) {
    echo "\n\nFirst Element:\n";
    var_dump($auditoria[0]);
}

$localConnection->disconnect();
