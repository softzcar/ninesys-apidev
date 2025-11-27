<?php
// debug_routes.php - Listar rutas registradas
// Sube este archivo a public/

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Rutas</h1>";

try {
    // 1. Bootstrap
    require __DIR__ . '/../vendor/autoload.php';
    require '../app/config.php';
    require '../app/app_loader.php';

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

    // $routes = require __DIR__ . '/../app/routes.php';
    // $routes($app);

    // Carga manual granular para detectar fallos
    $routeFiles = [
        'config.php',
        'payroll.php',
        'auth.php',
        'inventory.php',
        'manufacturing.php',
        'employees.php',
        'reports.php',
        'payments.php',
        'orders.php',
        'products.php',
        'production.php',
        'designs.php',
        'finance.php',
        'catalogs.php',
        'tables.php',
        'communications.php',
        'assignments.php',
        'printers.php'
    ];

    echo "<h3>Cargando archivos de rutas individualmente...</h3>";
    echo "<ul>";
    foreach ($routeFiles as $file) {
        try {
            $path = __DIR__ . '/../app/routes/' . $file;
            if (file_exists($path)) {
                $closure = require $path;
                if (is_callable($closure)) {
                    $closure($app);
                    echo "<li style='color:green'>✅ $file cargado correctamente.</li>";
                } else {
                    echo "<li style='color:red'>❌ $file no devolvió un closure válido.</li>";
                }
            } else {
                echo "<li style='color:orange'>⚠️ $file no existe.</li>";
            }
        } catch (Throwable $e) {
            echo "<li style='color:red'>❌ <strong>ERROR FATAL en $file:</strong> " . $e->getMessage() . "</li>";
        }
    }
    echo "</ul>";

    // 2. Listar Rutas
    echo "<h2>Rutas Registradas (Filtrado por 'config')</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Method</th><th>Pattern</th><th>Name</th></tr>";

    $allRoutes = $app->getRouteCollector()->getRoutes();
    $configRouteFound = false;

    foreach ($allRoutes as $route) {
        $methods = implode(', ', $route->getMethods());
        $pattern = $route->getPattern();
        $name = $route->getName() ?? ''; // Fix null warning

        // Mostrar todas las rutas para estar seguros, pero resaltar config y otras de communications.php
        $style = "";
        if (
            strpos($pattern, 'config') !== false ||
            strpos($pattern, 'ws/') !== false ||
            strpos($pattern, 'send-message') !== false ||
            strpos($pattern, 'orders-json') !== false
        ) {
            $style = "style='background-color: #e3f2fd; font-weight: bold;'";
            if ($pattern === '/config')
                $configRouteFound = true;
        }

        echo "<tr $style>";
        echo "<td>" . htmlspecialchars($methods) . "</td>";
        echo "<td>" . htmlspecialchars($pattern) . "</td>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    if (!$configRouteFound) {
        echo "<h3 style='color: red;'>❌ ALERTA: La ruta /config NO aparece en la lista.</h3>";
    } else {
        echo "<h3 style='color: green;'>✅ La ruta /config está registrada.</h3>";
    }

    // 3. Simular Request a /config
    echo "<h2>Simulación de Request: GET /config</h2>";

    // Crear request simulado
    $request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
    $uri = $request->getUri()->withPath('/config')->withScheme('http')->withHost('localhost');

    // Resolver ruta - CORRECCIÓN AQUÍ: usar $app->getRouteResolver()
    // Si $app no expone getRouteResolver directamente (depende de la versión), usamos RouteCollector
    // En Slim 4, App tiene getRouteResolver().

    $routingResults = $app->getRouteResolver()->computeRoutingResults($uri->getPath(), 'GET');
    $status = $routingResults->getRouteStatus();

    echo "<strong>URI:</strong> " . $uri->getPath() . "<br>";
    echo "<strong>Status Code:</strong> " . $status . " (1=FOUND, 2=NOT_FOUND, 3=METHOD_NOT_ALLOWED)<br>";

    if ($status === \Slim\Routing\RoutingResults::FOUND) {
        echo "<span style='color: green;'>✅ Route Found!</span> Identifier: " . $routingResults->getRouteIdentifier() . "<br>";
    } elseif ($status === \Slim\Routing\RoutingResults::METHOD_NOT_ALLOWED) {
        echo "<span style='color: red;'>❌ Method Not Allowed!</span> Allowed methods: " . implode(', ', $routingResults->getAllowedMethods()) . "<br>";
    } else {
        echo "<span style='color: orange;'>⚠️ Route Not Found</span><br>";
    }

} catch (Throwable $e) {
    echo "<div style='background-color:#ffebee; border: 2px solid red; padding: 20px;'>";
    echo "<h2>❌ ERROR FATAL</h2>";
    echo "<strong>Mensaje:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
