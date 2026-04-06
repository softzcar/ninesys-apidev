<?php
require_once __DIR__ . '/app/lib/LocalDB.php';
$db = new LocalDB();

$orderId = 4481;

// 1. Check parent report query for this order
$sql = "SELECT p.id_orden, SUM(p.monto_pago - COALESCE((SELECT SUM(monto) FROM pagos_salarios WHERE id_pago = p._id), 0)) as total 
        FROM pagos p 
        WHERE p.id_orden = $orderId 
        GROUP BY p.id_orden";

$res = $db->goQuery($sql);
echo "PARENT PAGOS TOTAL RAW:\n";
print_r($res);
$row = $res[0] ?? null;
echo "PARENT PAGOS TOTAL: " . ($row['total'] ?? 0) . "\n";

// 2. Check child modal query for this order
$sqlChild = "SELECT p._id, p.detalle AS departamento, p.cantidad, p.monto_pago, 
            (SELECT COALESCE(SUM(monto), 0) FROM pagos_abonos WHERE id_pago = p._id) AS total_bonos,
            (SELECT COALESCE(SUM(monto), 0) FROM pagos_descuentos WHERE id_pago = p._id) AS total_descuentos,
            (SELECT COALESCE(SUM(monto), 0) FROM pagos_salarios WHERE id_pago = p._id) AS total_salario_pagado 
            FROM pagos p 
            WHERE p.id_orden = $orderId";

$resChild = $db->goQuery($sqlChild);
echo "CHILD MODAL PAYMENTS:\n";
$childTotal = 0;
if ($resChild) {
    foreach ($resChild as $rowC) {
        $salarioPagado = (float)$rowC['total_salario_pagado'];
        $montoPago = (float)$rowC['monto_pago'];
        $bonos = (float)$rowC['total_bonos'];
        $descuentos = (float)$rowC['total_descuentos'];
        
        $comisionPura = $salarioPagado > 0 
            ? ($montoPago - $bonos + $descuentos - $salarioPagado)
            : $montoPago;
            
        $subtotal = $comisionPura + $bonos - $descuentos;
        $childTotal += $subtotal;
        
        echo "- Detalle: {$rowC['departamento']}, ID: {$rowC['_id']}, Monto: {$montoPago}, Salario: {$salarioPagado}, Subtotal: {$subtotal}\n";
    }
}
echo "CHILD CALCULATED TOTAL: " . $childTotal . "\n";

// 3. Check employees
$sqlEmp = "SELECT id_orden, GROUP_CONCAT(DISTINCT id_empleado) as empleados FROM lotes_detalles_empleados_asignados WHERE id_orden = $orderId GROUP BY id_orden";
$resEmp = $db->goQuery($sqlEmp);
echo "EMPLOYEES: " . ($resEmp[0]['empleados'] ?? "NONE") . "\n";

$db->disconnect();
