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

$serverRequestCreator = ServerRequestCreatorFactory::create();

$request = $serverRequestCreator->createServerRequestFromGlobals();
$request = $request->withMethod('POST')
                   ->withUri(new \Slim\Psr7\Uri('http', 'localhost', null, '/login'))
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                   ->withParsedBody([
                       'email' => 'linyersaqb@gmail.com',
                       'password' => 'sayerlin01'
                   ]);

try {
    $response = $app->handle($request);
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();
    $ok = $status === 200 ? '✅' : '❌';
    echo "{$ok} [{$status}] POST /login\n";
    echo "BODY: {$body}\n";
} catch (\Exception $e) {
    echo "❌ EXCEPTION POST /login: " . $e->getMessage() . "\n";
}
echo "=== DONE ===\n";
