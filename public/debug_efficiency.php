<?php
require __DIR__ . '/../vendor/autoload.php';

$host = 'localhost';
$db = 'api_emp_163';
$user = 'api_user_163';
$pass = 'c45ff25ef00ce4ebb0fca422';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$id_empleado = 588;
$idsStr = '3713,3754,3726,3764,3801,3766,3767,3770,3783,3813,3685,3709,3727,3750,3753,3765,3768,3786,3787,3791,3796,3806,3811,3818,3819,3828,3833,3597,3591,3646,3751,3598,3762,3769,3755,3680';

// Consulta idéntica a manufacturing.php (con el fix de p.fisico=1)
// Pero seleccionando datos para agrupar en PHP
$sql = "
WITH TiemposCalculados AS (
    SELECT 
        sub_ldea.id_orden,
        SUM(
            CASE 
                WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, sub_ldea.fecha_terminado)
                ELSE 0 
            END
        ) AS tiempo_terminado
    FROM lotes_detalles_empleados_asignados sub_ldea
    WHERE sub_ldea.id_orden IN ($idsStr)
    AND sub_ldea.id_empleado = $id_empleado
    GROUP BY sub_ldea.id_orden
)
SELECT 
    o._id AS id_orden,
    op.name AS producto,
    
    COALESCE(tc.tiempo_terminado, 0) / (SELECT GREATEST(COUNT(*), 1) FROM ordenes_productos op_count JOIN products p_count ON p_count._id = op_count.id_woo AND p_count.fisico = 1 WHERE op_count.id_orden = o._id) AS totalRealTerminadas
    
FROM ordenes o
JOIN ordenes_productos op ON op.id_orden = o._id
JOIN products p ON p._id = op.id_woo AND p.fisico = 1
LEFT JOIN TiemposCalculados tc ON tc.id_orden = o._id
WHERE o._id IN ($idsStr)
ORDER BY o._id;
";

try {
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    echo "Total rows returned: " . count($results) . "\n";

    $stats = [];
    $grandTotal = 0;

    foreach ($results as $row) {
        $oid = $row['id_orden'];
        $val = floatval($row['totalRealTerminadas']);

        if (!isset($stats[$oid])) {
            $stats[$oid] = ['count' => 0, 'sum' => 0];
        }
        $stats[$oid]['count']++;
        $stats[$oid]['sum'] += $val;
        $grandTotal += $val;
    }

    echo "---------------------------------\n";
    echo sprintf("%-10s | %-10s | %-15s\n", "Orden", "Rows", "Sum (Seconds)");
    echo "---------------------------------\n";

    foreach ($stats as $oid => $data) {
        if ($data['sum'] > 0) {
            echo sprintf("%-10s | %-10d | %-15.2f\n", $oid, $data['count'], $data['sum']);
        }
    }

    echo "---------------------------------\n";
    echo "GRAND TOTAL (Seconds): " . number_format($grandTotal, 2) . "\n";
    echo "GRAND TOTAL (Hours): " . number_format($grandTotal / 3600, 2) . "\n";

} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
}
