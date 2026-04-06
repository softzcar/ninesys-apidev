<?php
require_once __DIR__ . '/app/config.php';
// Check database connection logic in reports.php
// It uses $this->dbEmpresa($id_empresa)
// I will try to replicate it.

$id_empresa = 174; // Assume this from credentials or subagent
$companyDB = "api_emp_" . $id_empresa;

$conn = new mysqli("localhost", "root", "root", $companyDB);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$orderId = 4481;

// Test the BATCH SQL I wrote
$sql = "SELECT p.id_orden, SUM(
    p.monto_pago 
    - COALESCE((SELECT SUM(monto) FROM pagos_salarios WHERE id_pago = p._id), 0)
    + CASE 
        WHEN (SELECT COUNT(*) FROM pagos_salarios WHERE id_pago = p._id) = 0 
        THEN (COALESCE((SELECT SUM(monto) FROM pagos_abonos WHERE id_pago = p._id), 0) - COALESCE((SELECT SUM(monto) FROM pagos_descuentos WHERE id_pago = p._id), 0))
        ELSE 0 
      END
) as total FROM pagos p WHERE p.id_orden = $orderId GROUP BY p.id_orden";

$res = $conn->query($sql);
if (!$res) {
    echo "SQL ERROR: " . $conn->error . "\n";
} else {
    $row = $res->fetch_assoc();
    echo "BATCH SQL RESULT FOR 4481: " . ($row['total'] ?? 'NULL') . "\n";
}

$conn->close();
