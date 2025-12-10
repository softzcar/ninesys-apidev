<?php
require 'app/config.php';
require 'app/model/LocalDB.php';

// Simulate IdEmpresaMiddleware logic
$id_empresa = 152; // Derived from user's previous context (api_emp_152)

$dsn = 'mysql:host=localhost;dbname=api_empresas';
$user = 'api_adminemp';
$password = 'rkyaFy!dAs8L5Lq8';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = 'SELECT db_host, db_user, db_password, nombre, db_name FROM empresas WHERE id_empresa = :id_empresa';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_empresa' => $id_empresa]);

    $connectionDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($connectionDetails) {
        define('LOCAL_DNS', 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name']);
        define('LOCAL_USER', $connectionDetails['db_user']);
        define('LOCAL_PASS', $connectionDetails['db_password']);
        echo "Connected to " . $connectionDetails['db_name'] . "\n";
    } else {
        die("Empresa $id_empresa not found.\n");
    }

} catch (PDOException $e) {
    die("Connection to api_empresas failed: " . $e->getMessage() . "\n");
}

$id_orden = 3153;

$sql = "SELECT 
    op.name AS producto,
    op.cantidad,
    ld._id AS id_lotes_detalles,
    ldea._id AS id_asignacion,
    ldea.fecha_inicio,
    ldea.fecha_terminado
FROM 
    ordenes_productos op
LEFT JOIN 
    lotes_detalles ld ON ld.id_ordenes_productos = op._id
LEFT JOIN 
    lotes_detalles_empleados_asignados ldea ON ldea.id_lotes_detalles = ld._id
WHERE 
    op.id_orden = $id_orden";

$localDB = new LocalDB();
$results = $localDB->goQuery($sql);

print_r($results);
