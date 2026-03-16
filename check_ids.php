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

    echo "### OBTENIENDO DETALLES DE EMPRESA 163 ###\n";
    $stmt = $pdo->prepare("SELECT db_host, db_user, db_password, db_name FROM empresas WHERE id_empresa = 163");
    $stmt->execute();
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emp) {
        die("No se encontró la empresa 163\n");
    }

    echo "Conectando a host: " . $emp['db_host'] . " DB: " . $emp['db_name'] . "\n";

    $eHost = $emp['db_host'];
    $eUser = $emp['db_user'];
    $ePass = $emp['db_password'];
    $eDb = $emp['db_name'];

    // Si el host es localhost o 127.0.0.1, usar el host local
    $connectionHost = ($eHost === 'localhost' || $eHost === '127.0.0.1') ? 'localhost' : $eHost;

    $ePdo = new PDO("mysql:host=$connectionHost;dbname=$eDb", $eUser, $ePass);
    $ePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "### REVISIÓN DE PRESUPUESTOS (Real) ###\n";
    $stmt = $ePdo->query("SELECT _id, cliente_nombre, status FROM presupuestos ORDER BY _id DESC LIMIT 10");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);

    echo "\n### REVISIÓN DE BORRADORES (ordenes_tmp) ###\n";
    $stmt = $ePdo->query("SELECT _id, tipo, JSON_EXTRACT(form, '$.nombre') as nombre FROM ordenes_tmp ORDER BY _id DESC LIMIT 10");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
