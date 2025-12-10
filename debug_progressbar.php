<?php

// require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config.php';
require __DIR__ . '/src/Application/Actions/Action.php';
// require __DIR__ . '/src/Infrastructure/Persistence/db.php';
require __DIR__ . '/app/model/LocalDB.php';

$id_orden = 3376;

echo "Debugging Progressbar for Order ID: $id_orden\n";

// 1. Connect to api_empresas
$dsn = 'mysql:host=localhost;dbname=api_empresas';
$user = 'api_adminemp';
$password = 'rkyaFy!dAs8L5Lq8';

try {
    $pdo = new PDO($dsn, $user, $password, [
        1002 => "SET lc_time_names = 'es_ES', NAMES utf8"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to api_empresas\n";

    $stmt = $pdo->query('SELECT id_empresa, db_host, db_user, db_password, nombre, db_name FROM empresas');
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $found = false;

    foreach ($empresas as $empresa) {
        echo "Checking company: " . $empresa['nombre'] . " (ID: " . $empresa['id_empresa'] . ")\n";

        // Define constants for LocalDB (simulating middleware)
        // Note: Constants can't be redefined, so we might need to handle this differently if we loop.
        // But LocalDB uses constructor args if provided, or constants if not.
        // Wait, LocalDB constructor signature: __construct($sql = '', $dsn = LOCAL_DNS, $user = LOCAL_USER, $pass = LOCAL_PASS)
        // So we can pass credentials to constructor!

        $local_dsn = 'mysql:host=' . $empresa['db_host'] . ';dbname=' . $empresa['db_name'];
        $local_user = $empresa['db_user'];
        $local_pass = $empresa['db_password'];

        try {
            $localConnection = new LocalDB('', $local_dsn, $local_user, $local_pass);

            // Check if order exists
            $sql = 'SELECT _id FROM ordenes WHERE _id = ' . $id_orden;
            $res = $localConnection->goQuery($sql);

            if (!empty($res)) {
                echo "Order $id_orden found in company " . $empresa['nombre'] . "\n";
                $found = true;
                runDebugLogic($localConnection, $id_orden);
                break;
            } else {
                // echo "Order not found in this company.\n";
            }

        } catch (Exception $e) {
            echo "Failed to connect to company DB: " . $e->getMessage() . "\n";
        }
    }

    if (!$found) {
        echo "Order $id_orden not found in any company.\n";
    }

} catch (PDOException $e) {
    echo "Failed to connect to api_empresas: " . $e->getMessage() . "\n";
}

function runDebugLogic($localConnection, $id_orden)
{
    echo "Running Debug Logic...\n";

    $sql = 'SELECT id_empleado, id_departamento FROM lotes_detalles_empleados_asignados WHERE id_orden =' . $id_orden;
    // echo "Executing: $sql\n";
    $empleados_asignados = $localConnection->goQuery($sql);
    echo "Empleados Asignados: " . count($empleados_asignados) . "\n";

    // VERIFCAR STATUS DE LA ORDEN
    $sql = 'SELECT status from ordenes WHERE _id = ' . $id_orden;
    $tmpStatus = $localConnection->goQuery($sql);
    echo "Status: " . ($tmpStatus[0]['status'] ?? 'N/A') . "\n";

    $object = [];
    if (!empty($tmpStatus)) {
        $object['status'] = $tmpStatus[0]['status'];
    }

    // BUSCAR PASO ACTUAL EN EL LOTE
    $sql = 'SELECT paso from lotes WHERE id_orden = ' . $id_orden;
    $tmpPaso = $localConnection->goQuery($sql);
    echo "Paso Query Result: " . json_encode($tmpPaso) . "\n";

    if (!empty($tmpPaso)) {
        $object['paso'] = $tmpPaso[0]['paso'];
        echo "Paso: " . $object['paso'] . "\n";

        // BUSCAR TIPO DE DISEÑO
        $sql = 'SELECT a.tipo, a.id_empleado, b.nombre FROM disenos a JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado WHERE id_orden = ' . $id_orden;
        echo "Executing: $sql\n";
        $d = $localConnection->goQuery($sql);
        echo "Diseno Query Result: " . json_encode($d) . "\n";

        if (empty($d)) {
            $diseno = 'no';
        } else {
            if (isset($d[0]['tipo'])) {
                $diseno = $d[0]['tipo'];
            } else {
                $diseno = 'no';
            }
        }
        echo "Diseno: $diseno\n";

        if ($diseno === 'no') {
            $cuentaDisenos = 0;
        } else {
            $cuentaDisenos = 2;
        }
        $object['data']['cuentaDisenos'] = $cuentaDisenos;

        // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
        $sql = 'SELECT `departamento` FROM lotes_detalles WHERE id_orden = ' . $id_orden . ' GROUP BY departamento';
        echo "Executing: $sql\n";
        $pActivos = $localConnection->goQuery($sql);
        echo "pActivos: " . json_encode($pActivos) . "\n";
        $object['data']['pActivos'] = $pActivos;

        $x = [];
        switch ($object['paso']) {
            case 'producción':
                $x[] = 0.6;
                break;

            case 'Corte':
                $x[] = 1;
                break;

            case 'Estampado':
                $x[] = 2;
                break;

            case 'Impresión':
                $x[] = 3;
                break;

            case 'Costura':
                $x[] = 4;
                break;

            case 'Limpieza':
                $x[] = 5;
                break;

            case 'Revisión':
                $x[] = 5.88;
                break;

            /*  case 'Diseno':
                $x[] = 0;
                break; */

            default:
                $x[] = 1;
                break;
        }

        echo "x array: " . json_encode($x) . "\n";

        $pasoActual = max($x);
        echo "pasoActual: $pasoActual\n";

        $object['data']['pasoActual'] = $pasoActual;
        $totalPasos = count($pActivos);
        $object['data']['totalPasos'] = count($pActivos);

        if (!$totalPasos) {
            $totalPasos = 1;
        }

        $object['porcentaje'] = round($pasoActual * 100 / $totalPasos);
        echo "Porcentaje: " . $object['porcentaje'] . "\n";
    } else {
        echo "tmpPaso is empty\n";
    }

    $localConnection->disconnect();
}
