<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/model/LocalDB.php';

header('Content-Type: text/plain');

// Para simular la resolución de empresas en index.php
$company = '194';
if (!defined('ID_EMPRESA')) {
    define('ID_EMPRESA', $company);
}
if (!defined('LOCAL_DB')) {
    define('LOCAL_DB', 'api_emp_' . $company);
}

// Configurar constantes de base de datos manualmente para pruebas rápidas
// replicando la lógica del middleware de resolución de base de datos
$driver = getenv('DB_DRIVER') ?: 'mysql';
define('DB_DRIVER', $driver);

// Leer configuración para DSN
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = 'api_emp_' . $company;

if ($driver === 'pgsql') {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
} else {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
}

if (!defined('LOCAL_DNS')) {
    define('LOCAL_DNS', $dsn);
    define('LOCAL_USER', $user);
    define('LOCAL_PASS', $pass);
}

try {
    $db = new LocalDB();
    echo "DB Driver: " . DB_DRIVER . "\n";
    echo "DSN: " . LOCAL_DNS . "\n";
    
    if (DB_DRIVER === 'pgsql') {
        $q = $db->goQuery("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tintas'");
        print_r($q);
    } else {
        $q = $db->goQuery("DESCRIBE tintas");
        print_r($q);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
