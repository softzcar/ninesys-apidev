<?php
// test_sql_fix.php

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

$id_empleado = 499; // Empleado prueba
$id_orden = 10;
$id_departamento = 1; // Impresión

$sql = "SELECT
               a._id AS id_lotes_detalles,
               a.procentaje_comision,
               b.comision AS comision_fija,
               SUM(c.cantidad) AS total_productos_empleado,
               SUM(c.cantidad) * b.comision AS total_comision_fija
           FROM
               lotes_detalles_empleados_asignados a
           JOIN
               api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
           JOIN
               ordenes_productos c ON c.id_orden = a.id_orden
           JOIN
               products p ON c.id_woo = p._id
           WHERE
               a.id_empleado = $id_empleado
               AND a.id_orden = $id_orden
               AND a.id_departamento = $id_departamento
               AND (p.fisico = 1 OR p.fisico IS NULL)
               AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
           GROUP BY
               a._id,
               a.procentaje_comision,
               b.comision,
               b.comision_tipo
           ;";

echo "Executing SQL:\n$sql\n\n";

try {
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($result);
} catch (Exception $e) {
    echo "SQL Error: " . $e->getMessage() . "\n";
}
