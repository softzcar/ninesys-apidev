<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/app_loader.php';

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

$app->addRoutingMiddleware();

$endpoints = [
    ['GET', '/ordenes/abono-detale/1'],
    ['GET', '/reportes/resumen/empleados/1/4'],
    ['GET', '/comercializacion/dashboard/1/4'],
    ['GET', '/comercializacion/ordenes/reporte/terminadas/esta-semana'],
    ['GET', '/comercializacion/ordenes/reporte/entregadas/esta-semana'],
];

$serverRequestCreator = ServerRequestCreatorFactory::create();

foreach ($endpoints as [$method, $path]) {
    $request = $serverRequestCreator->createServerRequestFromGlobals();
    $request = $request->withMethod($method)
                       ->withUri(new \Slim\Psr7\Uri('http', 'localhost', null, $path))
                       ->withHeader('Authorization', '195');
    try {
        $response = $app->handle($request);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $ok = $status === 200 ? '✅' : '❌';
        echo "{$ok} [{$status}] {$method} {$path}\n";
        if ($status !== 200) {
            echo "   BODY: " . substr($body, 0, 300) . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ EXCEPTION {$method} {$path}: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
echo "=== DONE ===\n";
