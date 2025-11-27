<?php
// public/test_communications_load.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Carga de communications.php</h1>";

$file = __DIR__ . '/../app/routes/communications.php';

echo "Intentando cargar: " . $file . "<br>";

if (!file_exists($file)) {
    echo "<h2 style='color:red'>❌ El archivo NO existe.</h2>";
    exit;
}

echo "✅ El archivo existe.<br>";

if (!is_readable($file)) {
    echo "<h2 style='color:red'>❌ El archivo NO es legible (permisos).</h2>";
    exit;
}

echo "✅ El archivo es legible.<br>";

// Simular clase App para el type hinting
namespace Slim;
class App
{
}

namespace {
    use Slim\App;

    try {
        $result = require $file;

        echo "✅ Require exitoso.<br>";

        echo "Tipo de retorno: " . gettype($result) . "<br>";

        if (is_callable($result)) {
            echo "<h2 style='color:green'>✅ El archivo devuelve una función (callable).</h2>";
            echo "Todo parece correcto con el archivo individualmente.";
        } else {
            echo "<h2 style='color:red'>❌ El archivo NO devuelve una función.</h2>";
            echo "Devuelve: " . var_export($result, true);
        }

    } catch (Throwable $e) {
        echo "<h2 style='color:red'>❌ Error al hacer require del archivo.</h2>";
        echo "Mensaje: " . $e->getMessage() . "<br>";
        echo "Archivo: " . $e->getFile() . "<br>";
        echo "Línea: " . $e->getLine() . "<br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}
