#!/usr/bin/env php
<?php

/**
 * Script para envolver archivos de rutas en funciones que reciben $app
 */

$routesDir = __DIR__ . '/app/routes';
$files = [
    'config.php',
    'payroll.php',
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
    'printers.php',
    'utils.php'
];

foreach ($files as $file) {
    $filePath = $routesDir . '/' . $file;

    if (!file_exists($filePath)) {
        echo "⚠️  Archivo no encontrado: $file\n";
        continue;
    }

    $content = file_get_contents($filePath);

    // Verificar si ya está envuelto
    if (strpos($content, 'return function (App $app)') !== false) {
        echo "✓ Ya procesado: $file\n";
        continue;
    }

    // Buscar el patrón de inicio
    $pattern = '/^(<\?php\s*\n\nuse Psr\\\\Http\\\\Message\\\\ResponseInterface as Response;\nuse Psr\\\\Http\\\\Message\\\\ServerRequestInterface as Request;)/m';

    if (preg_match($pattern, $content)) {
        // Agregar el use Slim\App y el return function
        $replacement = "<?php\n\nuse Psr\\Http\\Message\\ResponseInterface as Response;\nuse Psr\\Http\\Message\\ServerRequestInterface as Request;\nuse Slim\\App;\n\nreturn function (App \$app) {\n";

        $content = preg_replace($pattern, $replacement, $content);

        // Agregar el cierre de la función al final
        $content = rtrim($content) . "\n\n}; // Fin de la función que envuelve las rutas\n";

        file_put_contents($filePath, $content);
        echo "✓ Procesado: $file\n";
    } else {
        echo "⚠️  Patrón no encontrado en: $file\n";
    }
}

echo "\n✅ Proceso completado\n";
