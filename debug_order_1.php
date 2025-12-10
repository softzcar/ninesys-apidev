<?php
require_once 'app/model/LocalDB.php';

// Database Connection Logic (Copied from debug_query_3153.php)
$id_empresa = 151; // Assuming 151 based on previous context
$dsn = 'mysql:host=localhost;dbname=api_empresas';
$user = 'api_adminemp';
$password = 'rkyaFy!dAs8L5Lq8';

try {
    $pdo = new PDO($dsn, $user, $password);
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

$localConnection = new LocalDB();
$id_orden = 1;
$id_empleado = 479;

echo "=== DEBUG INFO FOR ORDER $id_orden AND EMPLOYEE $id_empleado ===\n\n";

// 1. Check Lotes Detalles (Departments assigned to the order)
$sql = "SELECT ld._id, ld.id_departamento, d.nombre as depto_nombre, ld.fecha_inicio, ld.fecha_terminado 
        FROM lotes_detalles ld
        JOIN ordenes_productos op ON op._id = ld.id_ordenes_productos
        JOIN departamentos d ON d._id = ld.id_departamento
        WHERE op.id_orden = $id_orden";
$lotes = $localConnection->goQuery($sql);
echo "1. Lotes Detalles (Departments):\n";
print_r($lotes);
echo "\n";

// 2. Check Assignments for Employee 479
$sql = "SELECT * FROM lotes_detalles_empleados_asignados 
        WHERE id_orden = $id_orden AND id_empleado = $id_empleado";
$assignments = $localConnection->goQuery($sql);
echo "2. Assignments for Employee $id_empleado:\n";
print_r($assignments);
echo "\n";

// 3. Check Projected Time Standards
$sql = "SELECT ptp.id_departamento, ptp.tiempo 
        FROM products_tiempos_de_produccion ptp
        JOIN ordenes_productos op ON op.id_woo = ptp.id_product
        WHERE op.id_orden = $id_orden";
$standards = $localConnection->goQuery($sql);
echo "3. Time Standards (Per Unit):\n";
print_r($standards);
echo "\n";

// 4. Check Employee's Department (if table exists)
// Trying to guess table name for employees/users
$sql = "SELECT * FROM empleados WHERE _id = $id_empleado";
// Note: If table is different, this might fail. Let's try 'usuarios' or similar if this fails.
// Based on previous context, 'empleados' seems likely or maybe 'users'.
// Let's try a generic query or check tables first? 
// I'll assume 'empleados' for now based on 'id_empleado'.
try {
    $employee = $localConnection->goQuery($sql);
    echo "4. Employee Info:\n";
    print_r($employee);
} catch (Exception $e) {
    echo "4. Employee Info: Could not fetch (Table might be different)\n";
}

$localConnection->disconnect();
?>