<?php
// Test directo: ejecutar el código de buscar SIN pasar por rutas
require __DIR__ . '/../vendor/autoload.php';
require '../app/config.php';
require '../app/app_loader.php';

// Simular headers necesarios
$_SERVER['HTTP_AUTHORIZATION'] = '165';
define('ID_EMPRESA', 165);
define('LOCAL_DNS', 'mysql:host=localhost;dbname=api_emp_165');
define('EMPRESA_NOMBRE', 'Test');
define('LOCAL_USER', 'api_user_165');
define('LOCAL_PASS', '35b2d8d7819d878030498d59');
define('LOCAL_DB', 'api_emp_165');
define('ESTATUS', 'accedido');

// Incluir la función directamente
require '../app/routes/orders.php';

echo "Test: llamando directamente a la lógica sin pasar por Slim\n";
echo "=====================================================\n\n";

try {
    // Este código NO debería funcionar porque la función está dentro del closure
    // Pero podemos probar si el archivo tiene errores de sintaxis
    echo "✓ El archivo routes/orders.php se carga sin errores de sintaxis\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nAhora vamos a verificar qué pasa con Slim...\n";
