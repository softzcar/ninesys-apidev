<?php
require_once 'app/model/LocalDB.php';

// Database Connection Logic (Copied from debug_order_1.php)
$id_empresa = 151;
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

echo "\n=== 1. CHECKING CATALOGO INSUMOS ===\n";
$sql = "SELECT _id, nombre FROM catalogo_insumos_productos LIMIT 5";
$catalogo = $localConnection->goQuery($sql);
print_r($catalogo);

echo "\n=== 2. CHECKING INVENTARIO (LINKAGE) ===\n";
$sql = "SELECT _id, insumo, costo, cantidad_inicial, id_catalogo FROM inventario LIMIT 10";
$inventario = $localConnection->goQuery($sql);
print_r($inventario);

echo "\n=== 3. CHECKING INVENTARIO MOVIMIENTOS (ORDER 1) ===\n";
$sql = "SELECT * FROM inventario_movimientos WHERE id_orden = 1";
$movimientos = $localConnection->goQuery($sql);
print_r($movimientos);

$localConnection->disconnect();
?>