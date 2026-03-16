<?php
require 'app/model/LocalDB.php';
require 'app/lib/config.php';

try {
    define('ID_EMPRESA', 163);
    define('LOCAL_DNS', 'mysql:host=127.0.0.1;dbname=ninesys_api');
    define('LOCAL_USER', 'dev_user');
    define('LOCAL_PASS', 'dev_pass');

    $db = new LocalDB();
    echo "--- inventario_movimientos ---\n";
    $res1 = $db->goQuery("DESCRIBE inventario_movimientos");
    if(isset($res1['status']) && $res1['status'] === 'error') {
        echo "Error: " . $res1['message'] . "\n";
    } else {
        foreach($res1 as $row) echo "{$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\n--- inventario_movimientos_historial ---\n";
    $res2 = $db->goQuery("DESCRIBE inventario_movimientos_historial");
    if(isset($res2['status']) && $res2['status'] === 'error') {
        echo "Error: " . $res2['message'] . "\n";
    } else {
        foreach($res2 as $row) echo "{$row['Field']} - {$row['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
