<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/app_loader.php';
require __DIR__ . '/../app/model/LocalDB.php';

$db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
$db->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '163';
$id_empresa = (int)$auth_header;

$empresa = $db->goQuery("SELECT id_empresa, db_host, db_user, db_password, db_name FROM empresas WHERE id_empresa = $id_empresa");
$emp = $empresa[0];
$local_dns = 'mysql:host=' . $emp['db_host'] . ';dbname=' . $emp['db_name'];
$db->switchDatabase($local_dns, $emp['db_user'], $emp['db_password']);

echo "Database Version: ";
$version = $db->goQuery("SELECT VERSION() as v");
echo $version[0]['v'] . "\n";

echo "EXPLAIN Query:\n";
// El query de manufacturing v6 (sin el $sql wrap)
$query = "
        WITH ActiveOrders AS (
            SELECT _id, status, fecha_entrega
            FROM ordenes
            WHERE status IN ('En espera', 'activa', 'pausada')
        ),
        OrderTotals AS (
            SELECT id_orden, SUM(cantidad) AS total_unidades
            FROM ordenes_productos
            WHERE id_orden IN (SELECT _id FROM ActiveOrders)
            GROUP BY id_orden
        ),
        AssignmentData AS (
            SELECT
                ldea.id_orden,
                ldea.id_departamento,
                COUNT(DISTINCT ldea.id_empleado) AS numero_de_empleados,
                MIN(ldea.fecha_inicio) AS fecha_inicio_agregada,
                CASE 
                    WHEN COUNT(ldea.id_empleado) = COUNT(ldea.fecha_terminado) THEN MAX(ldea.fecha_terminado) 
                    ELSE NULL 
                END AS fecha_terminado_agregada
            FROM
                lotes_detalles_empleados_asignados ldea
            JOIN ActiveOrders o ON o._id = ldea.id_orden
            GROUP BY
                ldea.id_orden,
                ldea.id_departamento
        ),
        ProjectedCoverage AS (
            SELECT DISTINCT a.id_orden, d.id_departamento
            FROM ordenes_productos a
            JOIN products_tiempos_de_produccion d ON d.id_product = a.id_woo
            JOIN ActiveOrders ao ON ao._id = a.id_orden
        )
        SELECT * FROM (
            SELECT
                a.id_orden,
                c.status,
                d.id_departamento,
                dep.departamento AS nombre_departamento,
                ad.fecha_inicio_agregada AS fecha_inicio,
                ad.fecha_terminado_agregada AS fecha_terminado,
                c.fecha_entrega AS fecha_entrega_de_la_orden,
                CONCAT(c.fecha_entrega, ' 08:30:00') AS fecha_entrega_orden,
                ot.total_unidades,
                (SUM(d.tiempo * a.cantidad) / COALESCE(ad.numero_de_empleados, 1)) AS tiempo_total_orden_depto,
                ofo.orden_fila AS orden_fila_orden,
                dep.orden_proceso AS orden_proceso_departamento,
                COALESCE(ad.numero_de_empleados, 0) AS cant_empleados
            FROM
                ordenes_productos a
            JOIN
                products_tiempos_de_produccion d ON d.id_product = a.id_woo
            JOIN
                departamentos dep ON dep._id = d.id_departamento
            JOIN
                ActiveOrders c ON c._id = a.id_orden
            JOIN
                OrderTotals ot ON ot.id_orden = a.id_orden
            LEFT JOIN
                AssignmentData ad ON ad.id_orden = a.id_orden AND ad.id_departamento = d.id_departamento
            LEFT JOIN
                ordenes_fila_orden ofo ON ofo.id_orden = a.id_orden
            GROUP BY
                a.id_orden,
                d.id_departamento

            UNION ALL

            SELECT
                ad.id_orden,
                c.status,
                ad.id_departamento,
                dep.departamento AS nombre_departamento,
                ad.fecha_inicio_agregada AS fecha_inicio,
                ad.fecha_terminado_agregada AS fecha_terminado,
                c.fecha_entrega AS fecha_entrega_de_la_orden,
                CONCAT(c.fecha_entrega, ' 08:30:00') AS fecha_entrega_orden,
                ot.total_unidades,
                0 AS tiempo_total_orden_depto,
                ofo.orden_fila AS orden_fila_orden,
                dep.orden_proceso AS orden_proceso_departamento,
                ad.numero_de_empleados AS cant_empleados
            FROM
                AssignmentData ad
            JOIN
                ActiveOrders c ON c._id = ad.id_orden
            JOIN
                OrderTotals ot ON ot.id_orden = ad.id_orden
            JOIN
                departamentos dep ON dep._id = ad.id_departamento
            LEFT JOIN
                ordenes_fila_orden ofo ON ofo.id_orden = ad.id_orden
            LEFT JOIN
                ProjectedCoverage pc ON pc.id_orden = ad.id_orden AND pc.id_departamento = ad.id_departamento
            WHERE
                pc.id_orden IS NULL
        ) AS UnifiedResults
        ORDER BY
            orden_fila_orden ASC,
            id_orden ASC,
            orden_proceso_departamento ASC;
";

$explain = $db->goQuery("EXPLAIN " . $query);
if (isset($explain['status']) && $explain['status'] === 'error') {
    die("Error EXPLAIN: " . $explain['message']);
}

foreach($explain as $row) {
    echo $row['id'] . " | " . $row['select_type'] . " | " . $row['table'] . " | " . $row['type'] . " | " . $row['possible_keys'] . " | " . $row['key'] . " | " . $row['rows'] . " | " . $row['Extra'] . "\n";
}
