<?php
try {
    // Cargar variables de entorno manualmente
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                putenv("$key=$value");
            }
        }
    }

    $dsn_empresas = 'mysql:host=' . getenv('DB_HOST') . ';dbname=api_empresas';
    $user_empresas = getenv('DB_USER');
    $pass_empresas = getenv('DB_PASS');
    
    $pdo_emp = new PDO($dsn_empresas, $user_empresas, $pass_empresas);
    $stmt = $pdo_emp->prepare("SELECT * FROM empresas WHERE id_empresa = 163");
    $stmt->execute();
    $conn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    print_r($conn);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
