<?php
require_once __DIR__ . '/app/config.php';
// Replicating the connection logic to ensure same environment
// In reports.php: $dbEmpresas = $this->dbEmpresa($id_empresa);

$id_empresa = 163; // User mentioned 163
$companyDB = "api_emp_" . $id_empresa;

$conn = new mysqli("127.0.0.1", "dev_user", "dev_pass", $companyDB);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$orderId = 4481;

// THE CORRECT SQL (Batch version of the modal logic)
$sql = "SELECT p.id_orden, SUM(
    p.monto_pago 
    - COALESCE((SELECT SUM(monto) FROM pagos_salarios WHERE id_pago = p._id), 0)
    + COALESCE((SELECT SUM(monto) FROM pagos_abonos WHERE id_pago = p._id), 0)
    - COALESCE((SELECT SUM(monto) FROM pagos_descuentos WHERE id_pago = p._id), 0)
) as total FROM pagos p WHERE p.id_orden = $orderId GROUP BY p.id_orden";

$res = $conn->query($sql);
if (!$res) {
    echo "SQL ERROR: " . $conn->error . "\n";
} else {
    $row = $res->fetch_assoc();
    echo "CORRECTED BATCH SQL TOTAL: " . ($row['total'] ?? 0) . "\n";
}

$conn->close();
