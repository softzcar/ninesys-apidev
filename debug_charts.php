<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/model/LocalDB.php';

// Mock constants if not defined (though they should be in the real environment)
if (!defined('LOCAL_DNS')) define('LOCAL_DNS', 'mysql:host=127.0.0.1;dbname=api_emp_163');
if (!defined('LOCAL_USER')) define('LOCAL_USER', 'dev_user');
if (!defined('LOCAL_PASS')) define('LOCAL_PASS', 'dev_pass');

try {
    $localConnection = new LocalDB();
    
    $departamento = 'Todas';
    $deptWhere = ($departamento && $departamento !== 'Todas' && $departamento !== 'todos')
                ? "AND i.departamento = '" . addslashes($departamento) . "'"
                : "";

    echo "--- Testing Materiales Query ---\n";
    $sqlMateriales = "SELECT 
                        i.insumo as label, 
                        ROUND(SUM((im.valor_inicial - im.valor_final) * IF(i.tipo_insumo = 'tela', COALESCE(NULLIF(i.rendimiento, 0), 1), 1)), 2) as value,
                        IF(i.tipo_insumo = 'tela', 'Mts', i.unidad) as unidad
                    FROM inventario_movimientos im 
                    JOIN inventario i ON im.id_insumo = i._id 
                    WHERE im.moment >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND (im.valor_inicial - im.valor_final) > 0
                      {$deptWhere}
                    GROUP BY i.sku 
                    ORDER BY value DESC 
                    LIMIT 5";
    
    $res = $localConnection->goQuery($sqlMateriales);
    print_r($res);

    echo "\n--- Testing Tintas Query ---\n";
    $sqlTintas = "SELECT 
                    ROUND(SUM(COALESCE(c, 0)), 2) as C, 
                    ROUND(SUM(COALESCE(m, 0)), 2) as M, 
                    ROUND(SUM(COALESCE(y, 0)), 2) as Y, 
                    ROUND(SUM(COALESCE(k, 0)), 2) as K, 
                    ROUND(SUM(COALESCE(w, 0)), 2) as W 
                FROM tintas 
                WHERE moment >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $resTintas = $localConnection->goQuery($sqlTintas);
    print_r($resTintas);

    echo "\n--- Testing Papel Query ---\n";
    $sqlPapel = "SELECT 
                    CONCAT('Sem ', WEEK(im.moment, 1)) as label, 
                    ROUND(SUM(im.valor_inicial - im.valor_final), 2) as value 
                FROM inventario_movimientos im 
                JOIN inventario i ON im.id_insumo = i._id 
                WHERE im.moment >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND (i.insumo LIKE '%Papel%' OR i.departamento IN ('Impresión', 'Impresion'))
                  AND (im.valor_inicial - im.valor_final) > 0
                  {$deptWhere}
                GROUP BY WEEK(im.moment, 1)
                ORDER BY MIN(im.moment) ASC";
    $resPapel = $localConnection->goQuery($sqlPapel);
    print_r($resPapel);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
