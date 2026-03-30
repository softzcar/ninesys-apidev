<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/model/LocalDB.php';

try {
    $conn = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // Encuentra bases de datos para indexar
    $sql = "SELECT table_schema FROM information_schema.tables WHERE table_schema LIKE 'api_emp_%' GROUP BY table_schema;";
    $result = $conn->goQuery($sql);
    
    foreach($result as $row) {
        if(isset($row['table_schema'])) {
            $db = $row['table_schema'];
            echo "Updating $db...\n";
            try {
                // Add index on pagos
                $conn->switchDatabase("mysql:host=localhost;dbname=$db", EMPRESAS_USER, EMPRESAS_PASS);
                $conn->goQuery("ALTER TABLE pagos ADD INDEX idx_pagos_empleado_moment (id_empleado, moment);");
                $conn->goQuery("ALTER TABLE pagos ADD INDEX idx_pagos_fecha (fecha_pago);");
                
                // Add index on pagos_salarios
                $conn->goQuery("ALTER TABLE pagos_salarios ADD INDEX idx_pagos_salarios_id_pago (id_pago);");
                echo "Added indexes for $db successfully.\n";
            } catch (Exception $e) {
                echo "Warning on $db: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "Done processing all databases.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
