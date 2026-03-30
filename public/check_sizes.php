<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/model/LocalDB.php';

try {
    $conn = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    
    // Check sizes of pagos table across all databases
    $sql = "SELECT 
                TABLE_SCHEMA as db, 
                TABLE_NAME as table_name, 
                TABLE_ROWS as rows 
            FROM information_schema.tables 
            WHERE TABLE_NAME = 'pagos' AND TABLE_SCHEMA LIKE 'api_emp_%'
            ORDER BY TABLE_ROWS DESC;";
            
    $result = $conn->goQuery($sql);
    
    echo "Top large 'pagos' tables:\n";
    foreach($result as $row) {
        echo "- {$row['db']}: {$row['rows']} rows\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
