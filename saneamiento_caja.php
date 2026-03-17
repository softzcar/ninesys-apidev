<?php
// Inclusion con nombres correctos (Case Sensitive)
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/lib/config.php';
require_once __DIR__ . '/app/model/LocalDB.php';
require_once __DIR__ . '/app/model/CustomTime.php';

// Iniciamos conexion base (api_empresas)
$localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

// Buscamos el ID de la empresa Nineteen Custom
$sqlEmp = "SELECT id_empresa, nombre FROM empresas WHERE nombre LIKE '%Nineteen%' LIMIT 1";
$resEmp = $localConnection->goQuery($sqlEmp);

if (empty($resEmp)) {
    die("Error: No se encontró la empresa Nineteen en api_empresas.\n");
}

$id_empresa = $resEmp[0]['id_empresa'];
echo "Empresa encontrada: " . $resEmp[0]['nombre'] . " (ID: $id_empresa)\n";

// Obtenemos sus detalles de conexión
$details = $localConnection->getConnectionDetails($id_empresa);
if (!$details) {
    die("Error: No se pudieron obtener los detalles de conexión para la empresa $id_empresa.\n");
}

$dsn = "mysql:host=" . $details['db_host'] . ";dbname=" . $details['db_name'];
echo "Conectando a base de datos: " . $details['db_name'] . " en " . $details['db_host'] . "\n";

// Cambiamos a la base de datos de la empresa
$localConnection->switchDatabase($dsn, $details['db_user'], $details['db_password']);

// 1. Conteo previo
$sqlPre = "SELECT moneda, SUM(monto) as monto_acumulado, COUNT(*) as total_registros
           FROM caja 
           WHERE id_caja_cierres IS NULL 
             AND moment < '2026-03-16 08:00:00'
           GROUP BY moneda";
$resPre = $localConnection->goQuery($sqlPre);

echo "\n--- CONTEO PREVIO ---\n";
print_r($resPre);

// 2. Ejecutar saneamiento
echo "\n--- EJECUTANDO SANEAMIENTO ---\n";
$sqlUpd = "UPDATE caja 
           SET id_caja_cierres = -1 
           WHERE id_caja_cierres IS NULL 
             AND moment < '2026-03-16 08:00:00'";
$resUpd = $localConnection->goQuery($sqlUpd);
print_r($resUpd);

// 3. Verificación final
$sqlPost = "SELECT COUNT(*) as pendientes 
            FROM caja 
            WHERE id_caja_cierres IS NULL 
              AND moment < '2026-03-16 08:00:00'";
$resPost = $localConnection->goQuery($sqlPost);

echo "\n--- VERIFICACIÓN FINAL ---\n";
print_r($resPost);

$localConnection->disconnect();
?>
