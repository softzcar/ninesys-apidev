<?php
require 'app/config.php';
require 'app/model/LocalDB.php';

$id_empresa = 152;

$dsn = 'mysql:host=localhost;dbname=api_empresas';
$user = 'api_adminemp';
$password = 'rkyaFy!dAs8L5Lq8';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT db_host, db_user, db_password, nombre, db_name FROM empresas WHERE id_empresa = :id');
    $stmt->execute(['id' => $id_empresa]);
    $conn = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conn) die("Empresa $id_empresa not found.\n");

    echo "DB: {$conn['db_name']}\n\n";

    $localDSN = 'mysql:host=' . $conn['db_host'] . ';dbname=' . $conn['db_name'];
    $db = new PDO($localDSN, $conn['db_user'], $conn['db_password'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Count tintas records
    $r = $db->query("SELECT COUNT(*) as cnt FROM tintas")->fetch(PDO::FETCH_ASSOC);
    echo "Total tintas rows: " . $r['cnt'] . "\n";

    // Last 5 orders with tintas
    $rows = $db->query("SELECT id_orden, id_catalogo_impresoras, c, m, y, k, w FROM tintas ORDER BY _id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nLast 5 tintas rows:\n";
    foreach ($rows as $row) { print_r($row); }

    // Check what columns tintas table has
    $cols = $db->query("SHOW COLUMNS FROM tintas")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTintas columns: ";
    echo implode(', ', array_column($cols, 'Field')) . "\n";

    // Sample from inventario_movimientos to see if tintas are tracked there too
    $r2 = $db->query("SELECT COUNT(*) as cnt FROM inventario_movimientos WHERE id_insumo IS NULL OR id_insumo = 0")->fetch(PDO::FETCH_ASSOC);
    echo "\ninventario_movimientos rows with no id_insumo: " . $r2['cnt'] . "\n";

    // Check inventario for any ink/tinta items
    $inks = $db->query("SELECT _id, sku, insumo, color FROM inventario WHERE LOWER(insumo) LIKE '%tinta%' OR LOWER(insumo) LIKE '%ink%' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nInk items in inventario:\n";
    foreach ($inks as $ink) { echo "  id={$ink['_id']} sku={$ink['sku']} nombre={$ink['insumo']} color={$ink['color']}\n"; }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
