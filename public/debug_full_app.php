<?php
// debug_full_app.php - Intento de arranque completo de la app con captura de errores
// Sube este archivo a public/

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico Completo de Arranque</h1>";

// Definir constantes si es necesario (simulando index.php)
// define('APP_ROOT', __DIR__ . '/..');

try {
    echo "<h3>1. Cargando Autoload...</h3>";
    require __DIR__ . '/../vendor/autoload.php';
    echo "✅ Autoload OK.<br>";

    echo "<h3>2. Cargando Config y App Loader...</h3>";
    require '../app/config.php';
    echo "✅ Config OK.<br>";

    require '../app/app_loader.php';
    echo "✅ App Loader OK.<br>";

    echo "<h3>3. Configurando Contenedor...</h3>";

    $containerBuilder = new ContainerBuilder();

    $settings = require __DIR__ . '/../app/settings.php';
    $settings($containerBuilder);
    echo "✅ Settings OK.<br>";

    $dependencies = require __DIR__ . '/../app/dependencies.php';
    $dependencies($containerBuilder);
    echo "✅ Dependencies OK.<br>";

    $repositories = require __DIR__ . '/../app/repositories.php';
    $repositories($containerBuilder);
    echo "✅ Repositories OK.<br>";

    $container = $containerBuilder->build();
    AppFactory::setContainer($container);
    $app = AppFactory::create();
    echo "✅ App Factory Created OK.<br>";

    echo "<h3>4. Cargando Middleware...</h3>";
    $middleware = require __DIR__ . '/../app/middleware.php';
    $middleware($app);
    echo "✅ Middleware Registered OK.<br>";

    echo "<h3>5. Cargando Rutas...</h3>";
    $routes = require __DIR__ . '/../app/routes.php';
    $routes($app);
    echo "✅ Routes Registered OK.<br>";

    echo "<h2>🎉 ¡ÉXITO! La aplicación se inicializó correctamente sin errores fatales.</h2>";
    echo "<p>Si ves esto, el problema NO es el arranque de PHP, sino algo en tiempo de ejecución (ej. conexión a BD dentro de una ruta).</p>";

} catch (Throwable $e) {
    echo "<div style='background-color:#ffebee; border: 2px solid red; padding: 20px;'>";
    echo "<h2>❌ ERROR FATAL DETECTADO</h2>";
    echo "<strong>Mensaje:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
