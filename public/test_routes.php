<?php
// Test simple para verificar las rutas cargadas
require __DIR__ . '/../vendor/autoload.php';
require '../app/config.php';
require '../app/app_loader.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// Obtener todas las rutas
$routeCollector = $app->getRouteCollector();
$routes = $routeCollector->getRoutes();

echo "=== TODAS LAS RUTAS REGISTRADAS ===\n\n";
foreach ($routes as $route) {
    $pattern = $route->getPattern();
    $methods = implode(', ', $route->getMethods());
    if (strpos($pattern, 'buscar') !== false) {
        echo "RUTA BUSCAR ENCONTRADA:\n";
        echo "Pattern: $pattern\n";
        echo "Methods: $methods\n";
        echo "---\n";
    }
}

echo "\n=== TOTAL DE RUTAS: " . count($routes) . " ===\n";
