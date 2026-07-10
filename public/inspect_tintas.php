<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain');

try {
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../app/config.php';
    require __DIR__ . '/../app/model/LocalDB.php';
} catch (Throwable $t) {
    die("Error en requires: " . $t->getMessage() . " en " . $t->getFile() . ":" . $t->getLine() . "\n");
}

$company = '194';
if (!defined('ID_EMPRESA')) {
    define('ID_EMPRESA', $company);
}
if (!defined('LOCAL_DB')) {
    define('LOCAL_DB', 'api_emp_' . $company);
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
} catch (Throwable $e) {
    echo "Error ejecutando query: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
