<?php
// Cargar variables de entorno manualmente
$envFile = __DIR__ . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\' ');
        }
    }
}

$host = $env['DB_HOST'];
$user = $env['DB_USER'];
$pass = $env['DB_PASS'];
$dbname = $env['DB_NAME'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "### LISTADO DE EMPRESAS EN ESTE SERVIDOR ###\n";
    $stmt = $pdo->query("SELECT id_empresa, nombre, db_name FROM empresas LIMIT 20");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
