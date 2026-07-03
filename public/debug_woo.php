<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config.php';
// Cargar la base de datos y otras clases necesarias
require __DIR__ . '/../app/lib/db.php';
require __DIR__ . '/../app/lib/woome.php';

try {
    $woo = new WooMe();
    echo "Llamando a getAllCustomesrs()...\n";
    $res = $woo->getAllCustomesrs();
    echo "Resultado obtenido exitosamente. Longitud: " . strlen($res) . "\n";
    echo substr($res, 0, 500) . "\n";
} catch (Throwable $e) {
    echo "Excepcion capturada: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
