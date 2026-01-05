<?php
// Test de carga de routes/orders.php
echo "1. Verificando existencia del archivo...\n";
$path = __DIR__ . '/../app/routes/orders.php';
if (file_exists($path)) {
    echo "✓ Archivo existe: $path\n";
} else {
    echo "✗ Archivo NO existe: $path\n";
    exit(1);
}

echo "\n2. Intentando hacer require del archivo...\n";
try {
    $closure = require $path;
    if (is_callable($closure)) {
        echo "✓ El archivo retorna una función callable\n";
    } else {
        echo "✗ El archivo NO retorna una función callable\n";
        var_dump($closure);
    }
} catch (Exception $e) {
    echo "✗ Error al cargar el archivo: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n3. Verificando contenido del archivo para buscar el endpoint...\n";
$content = file_get_contents($path);
if (str pos($content, "'/buscar/{id}'") !== false) {
    echo "✓ Encontrado '/buscar/{id}' en el archivo\n";
} else {
    echo "✗ NO encontrado '/buscar/{id}' en el archivo\n";
}

if (strpos($content, 'obtenerRespuestaBuscar') !== false) {
    echo "✓ Encontrada función 'obtenerRespuestaBuscar' en el archivo\n";
} else {
    echo "✗ NO encontrada función 'obtenerRespuestaBuscar' en el archivo\n";
}

echo "\nTest completado.\n";
