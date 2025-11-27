<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Lib\NominaEngine;

return function (App $app) {

  $app->get('/test-salario/{id}', function (Request $request, Response $response, array $args) {
    // Incluir manualmente las nuevas clases
    // require_once __DIR__ . '/../lib/NominaBase.php';
    // require_once __DIR__ . '/../lib/VenezuelaNomina.php';

    // ... (Su código original para obtener ID de la empresa y país sigue aquí)

    // ... (asumimos que $paisId, $dedicatedConnection y $centralConnection ya fueron definidos)

    $employeeId = intval($args['id']);
    $periodoId = 1;
    $usuarioId = (int) $employeeId;

    // ... (1. Obtener conexiones y País ID como lo hacía originalmente)

    // CRear conexiones
    $centralConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $dedicatedConnection = new LocalDB();    // 4. INSTANCIAR LA CLASE DE PAÍS MANUALMENTE

    $sql = "SELECT pais FROM empresas WHERE id_empresa = " . ID_EMPRESA;
    $resPais = $centralConnection->goQuery($sql);

    $paisId = intval($resPais[0]['pais']);

    try {
      $strategyClass = '';

      // Mapeo simple: Seleccionamos la clase basada en el ID del país
      if ($paisId == 58) { // Usamos su ID 58 para Venezuela
        $strategyClass = 'VenezuelaNomina';
      } else {
        throw new \Exception("País ID {$paisId} no soportado.");
      }


      // Instanciar y ejecutar la clase de país
      $nominaStrategy = new $strategyClass($paisId, $employeeId, $dedicatedConnection, $centralConnection);

      $resultadosNomina = $nominaStrategy->liquidarPeriodo($periodoId, $usuarioId);

      $object['message'] = 'Liquidación de Nómina Ejecutada con Éxito.';
      $object['resultados'] = $resultadosNomina;
    } catch (\Exception $e) {
      $object['message'] = 'Error en el cálculo: ' . $e->getMessage();
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    } finally {
      $centralConnection->disconnect();
      $dedicatedConnection->disconnect();
    }

    // FIN DE LA IMPLEMENTACIÓN DE PRUEBA

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* // PRUEBAS DE CALCULO DE SALARIOS
  $app->get('/test-salario/{id}/_01', function (Request $request, Response $response, array $args) {
    $employeeId = $args['id'];

    // Conectar a la base de datos de empresas
    $empConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $localConnection = new LocalDB();

    $idEmpresa = ID_EMPRESA;

    $sql = 'SELECT pais from empresas WHERE id_empresa = ?;';
    $respEmp = $empConnection->goQuery($sql, [$idEmpresa]);
    $empConnection->disconnect();

    $object['id_empresa'] = $idEmpresa;
    $object['message'] = 'Ready';
    $object['resp'] = $respEmp[0]['pais'];

    // TODO PRUEBA DE IMPLEMENTACION DE LA NUEVA CLASE PARA CALCULO DE SALARIOS "AQUI"

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  }); */

  // PRUEBAS DE CALCULO DE SALARIOS
/*  $app->get('/test-salario/{id}', function (Request $request, Response $response, array $args) {
  $employeeId = $args['id'];
  $periodoId = 1;  // Asumimos un ID de período de prueba para la liquidación
  $usuarioId = (int) $employeeId;  // Usamos el ID del empleado pasado por URL

  // 1. Conexiones (Tal como lo hace en su código)
  // Conexión a la base de datos central (api_empresas)
  $centralConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
  // Conexión a la base de datos dedicada (empresa actual)
  $dedicatedConnection = new LocalDB();

  $idEmpresa = ID_EMPRESA;

  // 2. Obtener ID del País (Tal como lo hace en su código)
  $sql = 'SELECT pais from empresas WHERE id_empresa = ?;';
  $respEmp = $centralConnection->goQuery($sql, [$idEmpresa]);

  // 3. Validar y obtener ID del país
  if (empty($respEmp) || !isset($respEmp[0]['pais'])) {
    $centralConnection->disconnect();
    $object['message'] = 'Error: País de la empresa no encontrado.';
    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
  }
  $paisId = (int) $respEmp[0]['pais'];
  $centralConnection->disconnect();  // Desconectamos la BD Central tras obtener el dato

  // Inicialización de la clase de prueba
  $object['id_empresa'] = $idEmpresa;
  $object['pais_id'] = $paisId;
  $object['employee_id'] = $usuarioId;

  // TODO PRUEBA DE IMPLEMENTACION DE LA NUEVA CLASE PARA CALCULO DE SALARIOS "AQUI"

  // 4. INSTANCIAR Y USAR EL NOMINA ENGINE MANUALMENTE
  // Note: En producción usaría la inyección de dependencias (DI), pero aquí lo hacemos manual.
  try {
    // Creamos la instancia del Motor de Nómina, pasándole las dos conexiones
    $nominaEngine = new NominaEngine($dedicatedConnection, $centralConnection);

    // Ejecutamos el método liquidar, que internamente seleccionará a VenezuelaNomina (si paisId=1)
    $resultadosNomina = $nominaEngine->liquidar($periodoId, $usuarioId);

    $object['message'] = 'Liquidación de Nómina Ejecutada con Éxito.';
    $object['resultados'] = $resultadosNomina;
  } catch (\Exception $e) {
    $object['message'] = 'Error en el Motor de Nómina: ' . $e->getMessage();
    $object['error_trace'] = $e->getTraceAsString();
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(500);  // Error del servidor
  } finally {
    // Aseguramos que la conexión dedicada se cierre al finalizar
    $dedicatedConnection->disconnect();
  }
  // FIN DE LA IMPLEMENTACIÓN DE PRUEBA

  $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
  return $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus(200);
}); */

}; // Fin de la función que envuelve las rutas
