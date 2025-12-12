<?php
// test_debug.php

define('DB_HOST', 'localhost');
define('DB_USER', 'api_user_160');
define('DB_PASS', '0126e26ef574f5f9f8225253');
define('DB_NAME', 'api_emp_160');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$id_lotes_detalles = 17; // ID from Order 5 failure
$unidades_input = 7;

echo "--- Debugging Fix Logic ---\n";
echo "ID Assign: $id_lotes_detalles\n";
echo "Unidades Input: $unidades_input\n";

// 1. Get Order
$sql_orden_id = 'SELECT id_orden FROM lotes_detalles_empleados_asignados WHERE _id = ' . $id_lotes_detalles;
$stmt = $pdo->query($sql_orden_id);
$resOrden = $stmt->fetch(PDO::FETCH_ASSOC);
$idOrdenActual = $resOrden['id_orden'] ?? 0;

echo "Orden Found: $idOrdenActual\n";

if ($idOrdenActual > 0) {
    // 2. Calculate Clean Units
    $sql_clean_units = "SELECT SUM(op.cantidad) as total 
                        FROM ordenes_productos op
                        JOIN products p ON op.id_woo = p._id
                        WHERE op.id_orden = $idOrdenActual
                        AND (p.fisico = 1 OR p.fisico IS NULL)
                        AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";

    echo "SQL Clean: $sql_clean_units\n";

    $stmt = $pdo->query($sql_clean_units);
    $resClean = $stmt->fetch(PDO::FETCH_ASSOC);
    $cleanUnits = floatval($resClean['total'] ?? 0);

    echo "Clean Units Calculated: $cleanUnits\n";

    if ($cleanUnits > 0 && $cleanUnits < floatval($unidades_input)) {
        echo "FIX TRIGGERED: New Unidades = $cleanUnits\n";
    } else {
        echo "FIX NOT TRIGGERED\n";
        echo "Condition: $cleanUnits < " . floatval($unidades_input) . "\n";
    }
} else {
    echo "Orden not found or invalid.\n";
}
