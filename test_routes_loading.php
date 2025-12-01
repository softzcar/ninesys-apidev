<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';

// Mock de la clase App de Slim para probar la carga de rutas
class MockApp
{
    public function get($route, $callback)
    {
        echo "GET $route registrado\n";
    }
    public function post($route, $callback)
    {
        echo "POST $route registrado\n";
    }
    public function put($route, $callback)
    {
        echo "PUT $route registrado\n";
    }
    public function delete($route, $callback)
    {
        echo "DELETE $route registrado\n";
    }
    public function options($route, $callback)
    {
        echo "OPTIONS $route registrado\n";
    }
    public function add($middleware)
    {
        echo "Middleware agregado\n";
    }
}

$app = new MockApp();

echo "Iniciando prueba de carga de rutas...\n";

try {
    // Simular la carga del archivo principal routes.php
    // Como routes.php devuelve una función, la ejecutamos
    $routesFunction = require __DIR__ . '/app/routes.php';

    if (is_callable($routesFunction)) {
        $routesFunction($app);
        echo "✅ app/routes.php cargado y ejecutado correctamente.\n";
    } else {
        echo "❌ app/routes.php no devolvió una función ejecutable.\n";
    }

} catch (Throwable $e) {
    echo "\n❌ ERROR FATAL DETECTADO:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
