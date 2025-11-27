<?php
// debug_app.php - Diagnóstico profundo de la aplicación Slim
// Sube este archivo a la carpeta public/ y ejecútalo

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Aplicación Slim</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// 1. Verificar Autoload
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
echo "<h3>1. Verificando Autoload</h3>";
echo "Ruta esperada: $autoloadPath <br>";

if (!file_exists($autoloadPath)) {
    die("<span style='color:red'>❌ ERROR CRÍTICO: No se encuentra vendor/autoload.php. Ejecuta 'composer install'.</span>");
}
echo "<span style='color:green'>✅ Archivo autoload encontrado.</span><br>";

try {
    require $autoloadPath;
    echo "<span style='color:green'>✅ Autoload cargado correctamente.</span><br>";
} catch (Throwable $e) {
    die("<span style='color:red'>❌ ERROR cargando autoload: " . $e->getMessage() . "</span>");
}

// 2. Verificar Dependencias Clave
echo "<h3>2. Verificando Dependencias</h3>";
$classesToCheck = [
    'Slim\Factory\AppFactory',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Message\ServerRequestInterface'
];

foreach ($classesToCheck as $class) {
    if (class_exists($class) || interface_exists($class)) {
        echo "<span style='color:green'>✅ Clase/Interfaz $class encontrada.</span><br>";
    } else {
        die("<span style='color:red'>❌ ERROR: La clase $class no existe. Tu carpeta vendor está incompleta o corrupta.</span>");
    }
}

// 3. Verificar Rutas
echo "<h3>3. Verificando Routes.php</h3>";
$routesPath = __DIR__ . '/../app/routes.php';
if (!file_exists($routesPath)) {
    die("<span style='color:red'>❌ ERROR: No se encuentra app/routes.php</span>");
}

try {
    // Intentar requerir el archivo de rutas
    // NOTA: Si el archivo devuelve una función, no la ejecutamos aún, solo verificamos que no tenga errores de sintaxis fatales al cargar
    $routes = require $routesPath;
    echo "<span style='color:green'>✅ app/routes.php cargado sin errores de sintaxis inmediatos.</span><br>";

    if (is_callable($routes)) {
        echo "<span style='color:green'>✅ app/routes.php devuelve una función (Formato Nuevo/Refactorizado).</span><br>";

        // Intentar simular la ejecución de la función de rutas con un Mock
        echo "Intentando ejecutar la función de rutas con un MockApp...<br>";
        $mockApp = new class {
            public function get($r, $c)
            {
            }
            public function post($r, $c)
            {
            }
            public function put($r, $c)
            {
            }
            public function delete($r, $c)
            {
            }
            public function options($r, $c)
            {
            }
            public function add($m)
            {
            }
            public function group($r, $c)
            {
            }
        };

        try {
            $routes($mockApp);
            echo "<span style='color:green'>✅ Función de rutas ejecutada exitosamente con MockApp.</span><br>";
        } catch (Throwable $e) {
            echo "<span style='color:red'>❌ ERROR al ejecutar la función de rutas: " . $e->getMessage() . "</span><br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }

    } else {
        echo "<span style='color:orange'>⚠️ app/routes.php NO devuelve una función. (Formato Antiguo o Incorrecto). Tipo devuelto: " . gettype($routes) . "</span><br>";
    }
} catch (Throwable $e) {
    die("<span style='color:red'>❌ ERROR FATAL al cargar routes.php: " . $e->getMessage() . "</span><br><pre>" . $e->getTraceAsString() . "</pre>");
}

// 4. Verificar Archivos de Rutas Individuales (si existen)
echo "<h3>4. Verificando Archivos de Rutas Individuales</h3>";
$routesDir = __DIR__ . '/../app/routes';
if (is_dir($routesDir)) {
    $files = glob($routesDir . '/*.php');
    foreach ($files as $file) {
        $basename = basename($file);
        try {
            // Solo verificamos sintaxis básica intentando incluirlo
            // No podemos ejecutarlo porque espera $app
            // Pero si tiene syntax error, fallará aquí
            // Usamos php_check_syntax si es posible, o un include silencioso dentro de try

            // Nota: No podemos hacer include directo porque devolvería una función y no haría nada, 
            // o si tiene código global podría fallar.
            // Mejor solo reportar que existen.
            echo "Archivo encontrado: $basename <br>";
        } catch (Throwable $e) {
            echo "<span style='color:red'>❌ Error en $basename: " . $e->getMessage() . "</span><br>";
        }
    }
} else {
    echo "Carpeta app/routes no existe (Normal si estás en la versión antigua).<br>";
}

echo "<h2>✅ Diagnóstico Finalizado. Si ves esto, PHP está cargando bien la estructura básica.</h2>";
