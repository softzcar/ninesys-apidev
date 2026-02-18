<?php
require __DIR__ . '/app/config.php';
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'not set') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'not set') . "\n";
echo "ENV DB_HOST: " . ($_ENV['DB_HOST'] ?? 'not set') . "\n";

$dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'api_empresas');
$user = $_ENV['DB_USER'] ?? 'dev_user';
$pass = $_ENV['DB_PASS'] ?? 'dev_pass';

echo "Trying to connect to $dsn with user $user...\n";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "✅ Success!\n";
} catch (Exception $e) {
    echo "❌ Fail: " . $e->getMessage() . "\n";
}
