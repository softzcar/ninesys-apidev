<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/app_loader.php';

$db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
$db->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '163';
$id_empresa = (int)$auth_header;

$empresa = $db->goQuery("SELECT id_empresa, db_host, db_user, db_password, db_name FROM empresas WHERE id_empresa = $id_empresa");

if (isset($empresa['status']) && $empresa['status'] === 'error') {
    die("Error conectando a api_empresas: " . $empresa['message']);
}

$emp = $empresa[0];
echo "Verificando índices para la empresa ID: " . $emp['id_empresa'] . " (" . $emp['db_name'] . ")...\n";
$local_dns = 'mysql:host=' . $emp['db_host'] . ';dbname=' . $emp['db_name'];
$db->switchDatabase($local_dns, $emp['db_user'], $emp['db_password']);

$tables = ['lotes_detalles_empleados_asignados', 'ordenes', 'ordenes_productos', 'products_tiempos_de_produccion'];

foreach ($tables as $table) {
    echo "Tabla: $table\n";
    $indexes = $db->goQuery("SHOW INDEX FROM `$table` ");
    if (isset($indexes['status']) && $indexes['status'] === 'error') {
        echo "- Error: " . $indexes['message'] . "\n";
    } else {
        foreach ($indexes as $idx) {
            echo "- " . $idx['Key_name'] . " (" . $idx['Column_name'] . ")\n";
        }
    }
    echo "----------------------------------------\n";
}
