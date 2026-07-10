<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain');

try {
    require __DIR__ . '/../app/config.php'; // Esto llama a loadEnvFile()
} catch (Throwable $t) {
    die("Error cargando config.php: " . $t->getMessage() . "\n");
}

$driver = 'mysql';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = 'api_emp_194'; // Empresa autorizada para pruebas

echo "Entorno detectado:\n";
echo "Driver: $driver\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "User: $user\n";
echo "DbName: $dbname\n\n";

try {
    if ($driver === 'pgsql') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "Conexión exitosa a PostgreSQL.\n\nEstructura de la tabla 'tintas':\n";
        
        $stmt = $pdo->query("SELECT column_name, data_type, character_maximum_length, column_default, is_nullable 
                             FROM information_schema.columns 
                             WHERE table_name = 'tintas' 
                             ORDER BY ordinal_position");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($columns);
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "Conexión exitosa a MySQL.\n\nEstructura de la tabla 'tintas':\n";
        
        $stmt = $pdo->query("DESCRIBE tintas");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($columns);
    }
} catch (Throwable $e) {
    echo "Error de conexión o query: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
