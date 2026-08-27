<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use DI\ContainerBuilder;
use Slim\Psr7\Factory\StreamFactory;

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

$serverRequestCreator = ServerRequestCreatorFactory::create();

function testEndpoint($app, $serverRequestCreator, $method, $path, $body = null) {
    $request = $serverRequestCreator->createServerRequestFromGlobals();
    $request = $request->withMethod($method)
                       ->withUri(new \Slim\Psr7\Uri('http', 'localhost', null, $path))
                       ->withHeader('Authorization', '195');
    if ($body) {
        $request = $request->withParsedBody($body);
    }
    try {
        $response = $app->handle($request);
        $status = $response->getStatusCode();
        $ok = $status === 200 ? '✅' : '❌';
        echo "{$ok} [{$status}] {$method} {$path}\n";
        if ($status !== 200) {
            echo "   BODY: " . (string)$response->getBody() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ EXCEPTION {$method} {$path}: " . $e->getMessage() . "\n";
    }
}

// GET endpoints
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/semana/disenadores');
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/semana/empleados');
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/semana/vendedores');
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/vendedor/1');
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/reporte-empleado/1');
testEndpoint($app, $serverRequestCreator, 'GET', '/pagos/historico/28');

// POST endpoints (usar fechas de la semana actual)
$hoy = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$finSemana = date('Y-m-d', strtotime('sunday this week'));
testEndpoint($app, $serverRequestCreator, 'POST', '/pagos/semana', [
    'fecha_inicio' => $inicioSemana,
    'fecha_fin'    => $finSemana,
]);
testEndpoint($app, $serverRequestCreator, 'POST', '/pagos/semana/OLD', [
    'fecha_inicio' => $inicioSemana,
    'fecha_fin'    => $finSemana,
]);

echo "\n=== TODOS LOS ENDPOINTS VERIFICADOS ===\n";
