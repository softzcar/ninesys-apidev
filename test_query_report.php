<?php
require_once "app/config.php";
require_once "app/app_loader.php";
require_once "app/model/LocalDB.php";

$db = new LocalDB();
$companyDB = LOCAL_DB;

// Simular una lista de órdenes
$orderIdsStr = "12345, 12346"; // IDs de prueba

$sqlInsumosDetalles = "SELECT 
        a._id id_inventario_movimientos, 
        a.id_orden, 
        a.id_producto, 
        d.product producto, 
        c.insumo,
        (ABS(a.valor_inicial - a.valor_final) * COALESCE(c.rendimiento, 1)) as `real`,
        COALESCE((
            SELECT SUM(op_sub.cantidad * pia_sub.cantidad)
            FROM $companyDB.ordenes_productos op_sub
            JOIN $companyDB.product_insumos_asignados pia_sub ON pia_sub.id_product = op_sub.id_woo AND pia_sub.id_talla = op_sub.id_size
            WHERE op_sub.id_orden = a.id_orden AND pia_sub.id_insumo = a.id_insumo
        ), 0) as meta
    FROM $companyDB.inventario_movimientos a 
    JOIN $companyDB.inventario c ON a.id_insumo = c._id 
    JOIN $companyDB.products d ON a.id_producto = d._id 
    WHERE a.id_orden IN ($orderIdsStr) 
    ORDER BY a.id_orden ASC";

$res = $db->goQuery($sqlInsumosDetalles);
print_r($res);
