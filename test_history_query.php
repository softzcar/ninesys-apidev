<?php
require 'vendor/autoload.php';
$config = require 'app/settings.php';
$db = $config['settings']['db'];
$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['database'], $db['username'], $db['password']);

$id_orden = 15;
$sql = "SELECT 
                CONCAT('P-', met._id) as _id, 
                ord._id as orden, 
                ord.responsable as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                met.metodo_pago as metodo_pago, 
                met.monto as monto, 
                met.detalle as detalle, 
                met.tasa as tasa, 
                met.moneda as moneda, 
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora,
                met.monto as abono, 0 as descuento, 0 as nota_credito,
                met.moment
            FROM metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE met.id_orden = $id_orden
            
            UNION ALL
            
            SELECT 
                CONCAT('NC-', ab._id) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Nota de Crédito' as metodo_pago, 
                ab.nota_credito as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                DATE_FORMAT(ab.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(ab.moment, '%h:%i %p') AS hora,
                0 as abono, 0 as descuento, ab.nota_credito as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = $id_orden AND ab.nota_credito > 0
            
            UNION ALL
            
            SELECT 
                CONCAT('D-', ab._id) as _id, 
                ab.id_orden as orden, 
                ab.id_empleado as id_empleado, 
                COALESCE(emp.nombre, 'Sistema') as empleado, 
                'Descuento' as metodo_pago, 
                ab.descuento as monto, 
                ab.detalle as detalle, 
                1 as tasa, 
                'Dólares' as moneda, 
                DATE_FORMAT(ab.moment, '%d/%m/%Y') AS fecha, 
                DATE_FORMAT(ab.moment, '%h:%i %p') AS hora,
                0 as abono, ab.descuento as descuento, 0 as nota_credito,
                ab.moment
            FROM abonos ab
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ab.id_empleado
            WHERE ab.id_orden = $id_orden AND ab.descuento > 0
            
            ORDER BY moment DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
