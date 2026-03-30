<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/app_loader.php';


$db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

// Conectar a la base de datos central de empresas
$db->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$id_empresa_filter = '';
if(!empty($auth_header)) {
    $id_empresa_filter = " WHERE id_empresa = " . (int)$auth_header;
}

$empresas = $db->goQuery("SELECT id_empresa, db_host, db_user, db_password, db_name FROM empresas" . $id_empresa_filter);

if (isset($empresas['status']) && $empresas['status'] === 'error') {
    die("Error conectando a api_empresas: " . $empresas['message']);
}

foreach ($empresas as $emp) {
    if(!isset($emp['db_name'])) continue;

    echo "Actualizando índices para la empresa ID: " . $emp['id_empresa'] . " (" . $emp['db_name'] . ")...\n";
    $local_dns = 'mysql:host=' . $emp['db_host'] . ';dbname=' . $emp['db_name'];
    
    // Cambiar a la DB de la empresa específica
    $db->switchDatabase($local_dns, $emp['db_user'], $emp['db_password']);

    // Ignorar errores de foreign key checks mientras alteramos
    $db->goQuery("SET FOREIGN_KEY_CHECKS = 0;");

    $queries = [
        "ALTER TABLE `lotes_detalles_empleados_asignados` ADD INDEX `idx_orden_depto` (`id_orden`, `id_departamento`)",
        "ALTER TABLE `ordenes` ADD INDEX `idx_status` (`status`)",
        "ALTER TABLE `ordenes_productos` ADD INDEX `idx_orden_woo` (`id_orden`, `id_woo`)",
        "ALTER TABLE `products_tiempos_de_produccion` ADD INDEX `idx_prod_depto` (`id_product`, `id_departamento`)"
    ];

    foreach($queries as $q) {
        try {
            // goQuery returna error en un array si falla
            $res = $db->goQuery($q);
            if(isset($res['status']) && $res['status'] === 'error') {
                echo "- OMITIDO (posiblemente ya existe): " . $res['message'] . "\n";
            } else {
                echo "- OK: Índice creado correctamente.\n";
            }
        } catch(Exception $e) {
            echo "- ERR: " . $e->getMessage() . "\n";
        }
    }
    
    $db->goQuery("SET FOREIGN_KEY_CHECKS = 1;");
    echo "----------------------------------------\n";
}

echo "Proceso finalizado.\n";
