<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/lib/config.php';
require_once __DIR__ . '/app/model/LocalDB.php';
require_once __DIR__ . '/app/model/CustomTime.php';

$localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
$id_empresa = 163; // Nineteen Custom
$details = $localConnection->getConnectionDetails($id_empresa);
$dsn = "mysql:host=" . $details['db_host'] . ";dbname=" . $details['db_name'];
$localConnection->switchDatabase($dsn, $details['db_user'], $details['db_password']);

echo "--- TOTAL DE REGISTROS EN CAJA ---\n";
$sqlAll = "SELECT COUNT(*) as total FROM caja";
print_r($localConnection->goQuery($sqlAll));

echo "\n--- REGISTROS SIN CIERRE (id_caja_cierres IS NULL) ---\n";
$sqlNull = "SELECT moneda, SUM(monto) as monto, COUNT(*) as cantidad FROM caja WHERE id_caja_cierres IS NULL GROUP BY moneda";
print_r($localConnection->goQuery($sqlNull));

echo "\n--- ULTIMOS 5 REGISTROS ---\n";
$sqlLast = "SELECT _id, moment, monto, moneda, id_caja_cierres FROM caja ORDER BY _id DESC LIMIT 5";
print_r($localConnection->goQuery($sqlLast));

$localConnection->disconnect();
?>
