<?php
header('Content-Type: application/json');
require_once __DIR__ . '/app/lib/config.php';

$response = [
    'env' => 'unknown',
    'db_connection' => false,
    'empresas_count' => 0,
    'orders_count' => 0,
    'errors' => []
];

// Check Environment var
if (getenv('DB_USER') === 'dev_user') {
    $response['env'] = 'local (.env.local loaded)';
} else {
    $response['env'] = 'production/default';
}

// Test Connection
try {
    $pdo = new PDO(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $response['db_connection'] = true;

    // Query Empresas
    $stmt = $pdo->query("SELECT COUNT(*) FROM empresas");
    $response['empresas_count'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    $response['errors'][] = "Connection failed: " . $e->getMessage();
}

// Test Connection to 152 (manually constructing DSN as config.php logic for specific company might be dynamic in other files)
// Usually config.php connects to api_empresas. Specific company connection happens in logic.
// Connecting directly to verify import.

try {
    $dsn_152 = "mysql:host=127.0.0.1;dbname=api_emp_152";
    $pdo_152 = new PDO($dsn_152, 'dev_user', 'dev_pass');
    $stmt = $pdo_152->query("SELECT COUNT(*) FROM ordenes");
    $response['orders_count'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $response['errors'][] = "Connection to api_emp_152 failed: " . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
