<?php
define('ID_EMPRESA', 174);
define('DB_HOST', '127.0.0.1');
define('DB_USER_EMP', 'api_user_174');
define('DB_PASS_EMP', 'f57f3765d314c3f25584bfb1');
define('DB_NAME_EMP', 'api_emp_174');

$conexion = new mysqli(DB_HOST, DB_USER_EMP, DB_PASS_EMP, DB_NAME_EMP);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$id_insumo = 722;
$sql = "SELECT
    inv._id id_inventario,
    inv.insumo,
    imo._id id_movimiento,
    imo.id_orden,      
    ord.cliente_nombre,
    dep._id id_departamento,
    emp.id_usuario id_empleado,
    emp.nombre nombre_empleado,
    dep.departamento,
    (imo.valor_inicial - imo.valor_final) material_consumido,
    imo.valor_inicial,
    imo.valor_final,
    inv.cantidad cantidad_inventario,
    imo.moment fecha_del_consumo,
    (
        SELECT SUM(op.cantidad * COALESCE(pia.cantidad, 0))
        FROM ordenes_productos op
        JOIN product_insumos_asignados pia ON op.id_woo = pia.id_product AND op.id_size = pia.id_talla
        WHERE op.id_orden = imo.id_orden 
            AND pia.id_departamento = imo.id_departamento
            AND pia.id_catalogo_insumos_productos = inv.id_catalogo
    ) AS material_estimado
FROM
    inventario inv
JOIN inventario_movimientos imo ON imo.id_insumo = inv._id 
JOIN departamentos dep ON dep._id = imo.id_departamento 
JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = imo.id_empleado 
JOIN ordenes ord ON ord._id = imo.id_orden
WHERE
    imo.id_insumo = 722
ORDER BY imo.moment ASC LIMIT 5";

$resultado = $conexion->query($sql);
if ($resultado) {
    while($row = $resultado->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conexion->error;
}
$conexion->close();
