<?php
// Debug endpoint - eliminar después de probar
header('Content-Type: application/json');
require_once __DIR__ . '/../app/model/LocalDB.php';

$id = 12;
$localConnection = new LocalDB();

$sqlAuditoria = "SELECT accion, id_admin, nombre_admin, motivo, fecha 
                 FROM ordenes_auditoria 
                 WHERE id_orden = " . $id . " 
                 ORDER BY fecha DESC 
                 LIMIT 1";

$auditoria = $localConnection->goQuery($sqlAuditoria);

echo json_encode([
    'test' => 'debug_endpoint',
    'id_orden' => $id,
    'sql' => $sqlAuditoria,
    'auditoria_raw' => $auditoria,
    'auditoria_processed' => !empty($auditoria) ? $auditoria[0] : null,
    'empty_check' => empty($auditoria),
    'is_array' => is_array($auditoria),
    'count' => is_array($auditoria) ? count($auditoria) : 'N/A'
], JSON_PRETTY_PRINT);

$localConnection->disconnect();
