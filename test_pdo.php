<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=api_emp_163;charset=utf8', 'dev_user', 'dev_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
              SELECT a._id, 
                     a.form, 
                     a.tipo, 
                     b.id_usuario AS id_empleado, 
                     b.nombre AS empleado,
                     JSON_UNQUOTE(JSON_EXTRACT(a.form, '$.obs')) as observaciones,
                     JSON_EXTRACT(a.form, '$.productos') as productos_json
              FROM api_emp_163.ordenes_tmp a 
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              UNION ALL
              SELECT p._id, 
                     CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form, 
                     'Presupuesto Finalizado' as tipo, 
                     p.responsable as id_empleado, 
                     u.nombre as empleado,
                     p.observaciones,
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', t.tela)) 
                      FROM api_emp_163.presupuestos_productos pp 
                      LEFT JOIN api_emp_163.sizes s ON pp.id_size = s._id 
                      LEFT JOIN api_emp_163.catalogo_telas t ON pp.id_tela = t._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM api_emp_163.presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";
    $stmt = $pdo->query($sql);
    echo "Query OK. Rows: " . $stmt->rowCount() . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
