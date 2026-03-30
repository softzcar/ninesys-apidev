<?php
// Standalone indexing script to avoid dependency issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We need DB credentials. Since we are on-server, we can try to find them in .env
function getDbConfig() {
    $envFile = __DIR__ . '/../.env';
    $config = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value, "\"' ");
            }
        }
    }
    return $config;
}

$config = getDbConfig();
$host = $config['DB_HOST'] ?? 'localhost';
$user = $config['DB_USER'] ?? 'root';
$pass = $config['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Find all company databases
    $stmt = $pdo->query("SHOW DATABASES LIKE 'api_emp_%'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($databases as $db) {
        echo "Processing database: $db\n";
        try {
            $pdo->exec("USE `$db` ");
            
            // Index for pagos
            echo " - Indexing pagos...\n";
            try {
                $pdo->exec("ALTER TABLE pagos ADD INDEX idx_pagos_empleado_moment (id_empleado, moment)");
            } catch (Exception $e) { echo "   Warning: " . $e->getMessage() . "\n"; }
            
            try {
                $pdo->exec("ALTER TABLE pagos ADD INDEX idx_pagos_fecha (fecha_pago)");
            } catch (Exception $e) { echo "   Warning: " . $e->getMessage() . "\n"; }

            // Index for pagos_salarios
            echo " - Indexing pagos_salarios...\n";
            try {
                $pdo->exec("ALTER TABLE pagos_salarios ADD INDEX idx_ps_id_pago (id_pago)");
            } catch (Exception $e) { echo "   Warning: " . $e->getMessage() . "\n"; }
            
            echo " - Done with $db\n";
        } catch (Exception $e) {
            echo "Error on $db: " . $e->getMessage() . "\n";
        }
    }
    echo "Summary: All databases processed.\n";

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
