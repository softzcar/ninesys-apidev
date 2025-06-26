<?php
declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use App\Application\Middleware\CorsMiddleware;
use App\Application\Middleware\IdEmpresaMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(SessionMiddleware::class);
    $app->add(CorsMiddleware::class);
    $app->add(IdEmpresaMiddleware::class);
};
