<?php
require 'vendor/autoload.php';
$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);
require 'app/config/db.php';
$localConnection = new LocalDB();
$sql = "SELECT _id, form, tipo, id_empleado, empleado, observaciones, productos_json FROM (
              SELECT a._id, 
                     a.form, 
                     a.tipo, 
                     b.id_usuario AS id_empleado, 
                     b.nombre AS empleado,
                     JSON_UNQUOTE(JSON_EXTRACT(a.form, '$.obs')) as observaciones,
                     JSON_EXTRACT(a.form, '$.productos') as productos_json
              FROM ordenes_tmp a 
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              UNION ALL
              SELECT p._id, 
                     CONCAT('{\"id_presupuesto_original\":', p._id, ',\"nombre\":\"', p.cliente_nombre, '\",\"cedula\":\"', p.cliente_cedula, '\",\"total\":', p.pago_total, ',\"presupuesto_emitido\":true}') as form, 
                     'Presupuesto Finalizado' as tipo, 
                     p.responsable as id_empleado, 
                     u.nombre as empleado,
                     p.observaciones,
                     (SELECT JSON_ARRAYAGG(JSON_OBJECT('name', pp.name, 'cantidad', pp.cantidad, 'talla', s.nombre, 'tela', t.tela)) 
                      FROM presupuestos_productos pp 
                      LEFT JOIN sizes s ON pp.id_size = s._id 
                      LEFT JOIN catalogo_telas t ON pp.id_tela = t._id
                      WHERE pp.id_orden = p._id) as productos_json
              FROM presupuestos p
              JOIN api_empresas.empresas_usuarios u ON p.responsable = u.id_usuario
              WHERE p.status != 'Convertido'
            ) as combined
            ORDER BY _id DESC LIMIT 100";
$results = $localConnection->goQuery($sql);
var_dump($results === false ? "Connection or Query Error: " . $localConnection->error : count($results));
