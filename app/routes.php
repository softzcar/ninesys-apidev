<?php declare(strict_types=1);

// ini_set('implicit_flush', 1);

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

// use LocalDB;

date_default_timezone_set('America/Caracas');

return function (App $app) {
  // $app->add(new CorsMiddleware());
  // $app->add(new IdEmpresaMiddleware());

  function generateRandomToken($length = 32)
  {
    return bin2hex(random_bytes($length));
  }

  $app->options('/{routes:.*}', function (Request $request, Response $response, array $args) {
    // CORS Pre-Flight OPTIONS Request Handler
    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ROOT
  $app->get('/', function (Request $request, Response $response) {
    global $id_empresa;  // Acceder a la variable global

    // $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    /* $localConnection = new LocalDB();
    $sql = 'SELECT
        a.id_empleado,
        a.id_orden,
        b.nombre
    FROM
        disenos a
    JOIN api_empresas.empresas_usuarios b
    ON
        a.id_empleado = b.id_usuario
    ';
    $tableStructure = $localConnection->goQuery($sql);
    $createTableSQL = $tableStructure;
    // $createTableSQL = str_replace('empresas_usuarios', 'empleados', $createTableSQL);
    $array['join_dbs'] = $createTableSQL; */

    $array['api'] = 'ninesys DEVELOPEMENT';
    $array['Ver'] = '2.3';

    // Obtener el parámetro de consulta `id_empresa`
    $queryParams = $request->getQueryParams();
    $array['id_empresa'] = ID_EMPRESA;
    $array['test'] = 'Modificacion 02';

    $response->getBody()->write(json_encode($array, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Asumo que tu variable $app de Slim ya está inicializada
  // $app = new \Slim\Slim();

  $app->get('/disenos/images/{id_orden}', function (Request $request, Response $response, array $args) {
    // Directorio base de imágenes
    $baseDir = 'images/' . ID_EMPRESA . '/orden_' . $args['id_orden'];  // Array para almacenar las URLs de las imágenes
    $object['naseDir'] = $baseDir;
    $imageUrls = [];  // Verificar si el directorio existe
    if (is_dir($baseDir)) {
      // Abrir el directorio
      if ($handle = opendir($baseDir)) {
        // Leer archivos en el directorio
        while (false !== ($entry = readdir($handle))) {
          if ($entry != '.' && $entry != '..') {
            // Añadir la URL completa de la imagen al array
            $imageUrls[] = $baseDir . '/' . $entry;
          }
        }
        closedir($handle);
      }
    }
    // Si no se encontraron imágenes, añadir la URL por defecto
    if (empty($imageUrls)) {
      $imageUrls[] = 'images/no-image.png';
    }

    $response->getBody()->write(json_encode($imageUrls, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->get('/revision/image/{id_revision}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT url_image FROM revisiones WHERE _id = {$args['id_revision']};";
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data[0], JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ws-connect/{id_empresa}', function (Request $request, Response $response, array $args) {
    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/session-info/' . $args['id_empresa']);
    $testResp = $msgApi->getWSSeesionInfo($args['id_empresa']);

    $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/migracion/nineteencustom', function (Request $request, Response $response, array $args) {
    // Array para almacenar el reporte final de la ejecución 510530.Dev@Admin
    $log_report = [];
    $log_report['inicio_proceso'] = date('Y-m-d H:i:s');
    $log_report['resumen_por_orden'] = [];
    $status_code = 200;

    try {
      // --- Conexiones (Validadas) ---
      $db_antigua_host = 'localhost';
      $db_antigua_name = 'api_emp_3';
      $db_antigua_user = 'api_user_3';
      $db_antigua_pass = 'aqfh-4u3Eifp!hvD';
      $pdo_antigua = new PDO("mysql:host=$db_antigua_host;dbname=$db_antigua_name;charset=utf8mb4", $db_antigua_user, $db_antigua_pass);
      $pdo_antigua->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      $db_nueva_host = 'localhost';
      $db_nueva_name = 'api_emp_1';
      $db_nueva_user = 'api_user_1';
      $db_nueva_pass = '+e0o2GE+3rG%ari*';
      $pdo_nueva = new PDO("mysql:host=$db_nueva_host;dbname=$db_nueva_name;charset=utf8mb4", $db_nueva_user, $db_nueva_pass);
      $pdo_nueva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // La lista completa de órdenes a procesar
      // $ordenes_en_produccion = [1584, 1611, 1647, 1654, 1658, 1670, 1671, 1687, 1701, 1703, 1706, 1723, 1731, 1733, 1738, 1739, 1744, 1745, 1747, 1756, 1760];
      $ordenes_en_produccion = [1683];

      // Bucle principal sobre cada orden de producción
      foreach ($ordenes_en_produccion as $id_orden) {
        $orden_log = ['id_orden' => $id_orden];

        try {
          $pdo_nueva->beginTransaction();

          // 1. Limpieza previa para esta orden
          $pdo_nueva->prepare('DELETE FROM lotes_detalles_empleados_asignados WHERE id_orden = ?')->execute([$id_orden]);
          $pdo_nueva->prepare('DELETE FROM lotes_detalles WHERE id_orden = ?')->execute([$id_orden]);

          // 2. Leer todas las tareas de la BD antigua para esta orden
          $stmt_select = $pdo_antigua->prepare('SELECT * FROM lotes_detalles WHERE id_orden = ?');
          $stmt_select->execute([$id_orden]);
          $tareas_antiguas = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

          if (empty($tareas_antiguas)) {
            $orden_log['status'] = 'OMITIDA';
            $orden_log['mensaje'] = 'No se encontraron tareas de producción.';
            $pdo_nueva->commit();  // Es importante hacer commit para guardar las eliminaciones
            $log_report['resumen_por_orden'][] = $orden_log;
            continue;  // Pasa a la siguiente orden
          }

          // 3. Bucle interno para migrar cada tarea
          $tareas_migradas = 0;
          foreach ($tareas_antiguas as $tarea_a_migrar) {
            // Insertar en lotes_detalles
            $sql_insert_detalle = 'INSERT INTO lotes_detalles (id_orden, id_woo, progreso, id_ordenes_productos, id_reposicion, terminado, id_departamento, departamento, unidades_solicitadas, detalles, fecha_inicio, fecha_terminado, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt_insert_detalle = $pdo_nueva->prepare($sql_insert_detalle);
            $stmt_insert_detalle->execute([
              $tarea_a_migrar['id_orden'], $tarea_a_migrar['id_woo'], $tarea_a_migrar['progreso'], $tarea_a_migrar['id_ordenes_productos'], $tarea_a_migrar['id_reposicion'], $tarea_a_migrar['terminado'],
              $tarea_a_migrar['id_departamento'], $tarea_a_migrar['departamento'], $tarea_a_migrar['unidades_solicitadas'], $tarea_a_migrar['detalles'], $tarea_a_migrar['fecha_inicio'], $tarea_a_migrar['fecha_terminado'], $tarea_a_migrar['moment']
            ]);
            $new_lote_detalle_id = $pdo_nueva->lastInsertId();

            // Insertar en lotes_detalles_empleados_asignados
            $sql_insert_asignado = 'INSERT INTO lotes_detalles_empleados_asignados (id_lotes_detalles, id_orden, id_empleado, id_departamento, procentaje_comision, progreso, terminado, fecha_inicio, fecha_terminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt_insert_asignado = $pdo_nueva->prepare($sql_insert_asignado);
            $stmt_insert_asignado->execute([
              $new_lote_detalle_id, $tarea_a_migrar['id_orden'], $tarea_a_migrar['id_empleado'], $tarea_a_migrar['id_departamento'], 100.0,
              $tarea_a_migrar['progreso'], $tarea_a_migrar['terminado'], $tarea_a_migrar['fecha_inicio'], $tarea_a_migrar['fecha_terminado']
            ]);
            $tareas_migradas++;
          }

          // Si todas las tareas de esta orden se migraron, guardar cambios
          $pdo_nueva->commit();
          $orden_log['status'] = 'EXITO';
          $orden_log['mensaje'] = "Se migraron $tareas_migradas tareas correctamente.";
        } catch (PDOException $e) {
          $pdo_nueva->rollBack();
          $orden_log['status'] = 'ERROR';
          $orden_log['mensaje'] = 'Error en la transacción: ' . $e->getMessage();
        }
        $log_report['resumen_por_orden'][] = $orden_log;
      }
    } catch (PDOException $e) {
      $status_code = 500;
      $log_report['error_general'] = 'Fallo crítico durante la ejecución (posiblemente en la conexión inicial): ' . $e->getMessage();
    }

    $log_report['fin_proceso'] = date('Y-m-d H:i:s');

    // Devolvemos el reporte final en el formato que sabemos que funciona
    $response->getBody()->write(json_encode($log_report, JSON_PRETTY_PRINT));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status_code);
  });

  $app->get('/prueba-formato-mensaje/{id_orden}', function (Request $request, Response $response, array $args) {
    // Obtener la respuesta con los datos de la orden
    // $res = obtenerRespuestaBuscar($args['id_orden']);

    /* $localConnection = new LocalDB();
    $sql = 'SELECT * FROM ordenes_productos WHERE id_orden = ' . $args['id_orden'];
    $data = $localConnection->goQuery($sql); */

    $id_orden = $args['id_orden'];
    $data = obtenerRespuestaBuscar($args['id_orden']);

    // $localConnection->disconnect();

    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $args['id_orden']);
    $testResp = $msgApi->sendMessage(ID_EMPRESA, $args['id_orden'], 'welcome', $data);

    /*  $datos['phone'] = '584147307169';
     $datos['template'] = 'welcome';
     $datos['name'] = 'Roxana';
     $datos['message'] = 'Mensaje desde la api principal ahora de nuevo';
     $datos['id_cliente'] = '123';
     $datos['object'] = $res; */

    // $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));
    // $response->getBody()->write($testResp);
    // return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->delete('/disenos/images/{id_orden}/{image_name}', function (Request $request, Response $response, array $args) {
    // Directorio base de imágenes
    $baseDir = 'images/' . ID_EMPRESA . '/orden_' . $args['id_orden'];
    $imagePath = $baseDir . '/' . $args['image_name'];

    // Verificar si la imagen existe
    if (file_exists($imagePath)) {
      // Eliminar la imagen
      if (unlink($imagePath)) {
        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Imagen eliminada exitosamente.'], JSON_NUMERIC_CHECK));
      } else {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la imagen.'], JSON_NUMERIC_CHECK));
      }
    } else {
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'La imagen no existe.'], JSON_NUMERIC_CHECK));
    }

    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->post('/disenos/imagen/{id_orden}/{id_diseno}', function (Request $request, Response $response, array $args) {
    $id_orden = $args['id_orden'];
    $idEmpresa = $args['id_diseno'];
    $idEmpresa = ID_EMPRESA;

    $directory = 'images/' . $idEmpresa . "/orden_{$id_orden}/";

    // Crear el directorio si no existe
    if (!file_exists($directory)) {
      mkdir($directory, 0777, true);
    }

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['file'];

    try {
      if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        // Buscar el número incremental más alto en el directorio
        $files = glob($directory . "{$id_orden}-{$idEmpresa}-*.png");
        $maxIndex = 0;
        foreach ($files as $file) {
          if (preg_match("/{$id_orden}-{$idEmpresa}-(\d+)\.png\$/", $file, $matches)) {
            $index = (int) $matches[1];
            if ($index > $maxIndex) {
              $maxIndex = $index;
            }
          }
        }
        $nextIndex = $maxIndex + 1;
        $filename = "{$id_orden}-{$idEmpresa}-{$nextIndex}.png";
        $filePath = $directory . $filename;

        // Obtener contenido del archivo
        $stream = $uploadedFile->getStream();
        $imageContents = $stream->getContents();

        // Crear imagen desde el contenido
        $image = imagecreatefromstring($imageContents);
        if ($image === false) {
          throw new Exception('Error al crear la imagen desde el contenido.');
        }

        // Obtener dimensiones actuales
        $width = imagesx($image);
        $height = imagesy($image);

        // Calcular nuevas dimensiones manteniendo la proporción
        $new_width = 600;
        $new_height = intval(($height / $width) * $new_width);

        // Crear una nueva imagen en blanco
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        if ($resized_image === false) {
          throw new Exception('Error al crear la nueva imagen redimensionada.');
        }

        // Redimensionar la imagen
        $resampleResult = imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        if ($resampleResult === false) {
          throw new Exception('Error al redimensionar la imagen.');
        }

        // Guardar la imagen redimensionada
        $saveResult = imagepng($resized_image, $filePath, 8);  // Comprimir y guardar como PNG con calidad de 8
        if ($saveResult === false) {
          throw new Exception('Error al guardar la imagen redimensionada.');
        }

        imagedestroy($image);
        imagedestroy($resized_image);

        // Guardar la referencia en la base de datos
        // $localConnection = new LocalDB();
        // $sql = 'INSERT INTO disenos_imagenes (numero_orden, ruta_imagen, hashtags, diseñador) VALUES (?, ?, ?, ?)';
        // $localConnection->goQuery($sql, [$id_orden, $filePath, $hashtags, $diseñador]);
        // $localConnection->disconnect();

        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Imagen subida y guardada correctamente'], JSON_NUMERIC_CHECK));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
      } else {
        throw new Exception('Error al subir la imagen: Código de error ' . $uploadedFile->getError());
      }
    } catch (Exception $e) {
      error_log($e->getMessage());
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Error al subir la imagen: ' . $e->getMessage()], JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  $app->get('/api/products/template-excel', function (Request $request, Response $response) {
    try {
      $localConnection = new LocalDB();

      // Obtener categorías y atributos para las listas de validación y mapeo
      $categories = $localConnection->goQuery('SELECT _id, nombre FROM categories');
      $attributes = $localConnection->goQuery('SELECT _id, attribute_name FROM products_attributes');

      // Mapear categorías por ID para fácil acceso
      $categoryMap = [];
      foreach ($categories as $cat) {
        $categoryMap[$cat['_id']] = $cat['nombre'];
      }

      // Obtener productos existentes (filtrando SKUs nulos o vacíos)
      $products = $localConnection->goQuery("SELECT _id, product, sku, fisico, price, comision, stock_quantity, product_description, category_ids FROM products WHERE sku IS NOT NULL AND sku <> ''");

      $localConnection->disconnect();

      // Create new Spreadsheet object
      $spreadsheet = new Spreadsheet();

      // --- Sheet: Products (now for new products only) ---
      $sheetProducts = $spreadsheet->getActiveSheet();
      $sheetProducts->setTitle('Productos');

      // Set headers for Products sheet
      $headersProducts = ['SKU', 'Nombre', 'Precios', 'Precio Descripción', 'Categoría', 'Atributos'];
      $sheetProducts->fromArray($headersProducts, NULL, 'A1');

      // Set column widths for Products sheet
      foreach (range('A', 'F') as $col) {  // Adjusted range
        $sheetProducts->getColumnDimension($col)->setAutoSize(true);
      }

      // --- Hidden Sheet: ListadoSKUNormalizado ---
      $sheetSKUNormalizado = $spreadsheet->createSheet();
      $sheetSKUNormalizado->setTitle('ListadoSKUNormalizado');
      $sheetSKUNormalizado->setCellValue('A1', 'SKU_Normalizado');  // Header
      $row = 2;
      foreach ($products as $product) {
        $normalizedSku = strtoupper(str_replace('_', '', $product['sku']));
        $sheetSKUNormalizado->setCellValue('A' . $row, $normalizedSku);
        $row++;
      }
      $sheetSKUNormalizado->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

      // --- Hidden Sheet: ListadoCategorias ---
      $sheetCategories = $spreadsheet->createSheet();
      $sheetCategories->setTitle('ListadoCategorias');
      $sheetCategories->fromArray([['ID', 'Nombre']], NULL, 'A1');  // Headers for hidden sheet
      $row = 2;
      foreach ($categories as $category) {
        $sheetCategories->setCellValue('A' . $row, $category['_id']);
        $sheetCategories->setCellValue('B' . $row, $category['nombre']);
        $row++;
      }
      $sheetCategories->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);  // Hide the sheet

      // --- Hidden Sheet: ListadoAtributos ---
      $sheetAttributes = $spreadsheet->createSheet();
      $sheetAttributes->setTitle('ListadoAtributos');
      $sheetAttributes->fromArray([['ID', 'Nombre']], NULL, 'A1');  // Headers for hidden sheet
      $row = 2;
      foreach ($attributes as $attribute) {
        $sheetAttributes->setCellValue('A' . $row, $attribute['_id']);
        $sheetAttributes->setCellValue('B' . $row, $attribute['attribute_name']);
        $row++;
      }
      $sheetAttributes->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);  // Hide the sheet

      // --- Data Validation for Products sheet (CORRECTED) ---
      // SKU (Column A) - Custom validation for uniqueness (case-insensitive, underscore-insensitive)
      $skuValidation = $sheetProducts->getCell('A2')->getDataValidation();
      $skuValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
      $skuValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
      $skuValidation->setAllowBlank(false);
      $skuValidation->setShowErrorMessage(true);
      $skuValidation->setErrorTitle('SKU Duplicado');
      $skuValidation->setError('El SKU que ingresó ya existe en la base de datos o en este mismo archivo.');
      $formula = 'AND(COUNTIF(ListadoSKUNormalizado!A:A, SUBSTITUTE(UPPER(A2),"_",""))=0, COUNTIF(A:A,A2)=1)';
      $skuValidation->setFormula1($formula);

      // Category (Column E)
      $categoryValidation = $sheetProducts->getCell('E2')->getDataValidation();
      $categoryValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
      $categoryValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
      $categoryValidation->setAllowBlank(false);
      $categoryValidation->setShowInputMessage(true);
      $categoryValidation->setShowErrorMessage(true);
      $categoryValidation->setShowDropDown(true);
      $categoryValidation->setErrorTitle('Error de entrada');
      $categoryValidation->setError('El valor no está en la lista.');
      $categoryValidation->setPromptTitle('Seleccionar Categoría');
      $categoryValidation->setPrompt('Por favor, seleccione una categoría de la lista.');
      $categoryValidation->setFormula1('\'ListadoCategorias\'!B$2:B$' . (count($categories) + 1));  // Reference to names in hidden sheet

      // Attributes (Column F)
      $attributeValidation = $sheetProducts->getCell('F2')->getDataValidation();
      $attributeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
      $attributeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
      $attributeValidation->setAllowBlank(true);  // Attributes can be optional
      $attributeValidation->setShowInputMessage(true);
      $attributeValidation->setShowErrorMessage(true);
      $attributeValidation->setShowDropDown(true);
      $attributeValidation->setErrorTitle('Error de entrada');
      $attributeValidation->setError('El valor no está en la lista de atributos.');
      $attributeValidation->setPromptTitle('Seleccionar Atributo');
      $attributeValidation->setPrompt('Por favor, seleccione un atributo de la lista.');
      $attributeValidation->setFormula1('\'ListadoAtributos\'!B$2:B$' . (count($attributes) + 1));  // Reference to names in hidden sheet

      // Apply validation to a range (e.g., up to row 1000 for now, can be adjusted)
      for ($i = 2; $i <= 1000; $i++) {
        $sheetProducts->getCell('A' . $i)->setDataValidation(clone $skuValidation);
        $sheetProducts->getCell('E' . $i)->setDataValidation(clone $categoryValidation);
        $sheetProducts->getCell('F' . $i)->setDataValidation(clone $attributeValidation);  // Apply to Attributes column
      }

      // Save the Excel file
      $fileName = 'plantilla_productos_' . ID_EMPRESA . '.xlsx';
      $outputDirectory = __DIR__ . '/../public/downloads/carga_productos/';
      $filePath = $outputDirectory . $fileName;

      // Ensure the directory exists
      if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
      }

      $writer = new Xlsx($spreadsheet);
      $writer->save($filePath);

      // Generate the file URL with a cache-busting query parameter
      $fileUrl = '/downloads/carga_productos/' . $fileName . '?v=' . time();

      // Return success response with file URL
      $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Plantilla Excel generada exitosamente.',
        'file_url' => $fileUrl
      ], JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    } catch (\Exception $e) {
      error_log('Error generating Excel: ' . $e->getMessage());
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Error al generar la plantilla Excel. Por favor, inténtelo de nuevo más tarde.',
        'error_details' => $e->getMessage()  // Solo para depuración, quitar en producción
      ], JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    }
  });

  $app->get('/api/inventario/template-excel', function (Request $request, Response $response) {
    try {
      $localConnection = new LocalDB();

      // Obtener departamentos para la lista de validación
      $departamentos = $localConnection->goQuery('SELECT _id, departamento FROM departamentos');
      if (!is_array($departamentos)) {
        $departamentos = [];
      }

      // Obtener rollos existentes para validación de unicidad
      $rollosExistentes = $localConnection->goQuery("SELECT insumo FROM inventario WHERE insumo IS NOT NULL AND insumo <> ''");
      if (!is_array($rollosExistentes)) {
        $rollosExistentes = [];
      }

      $localConnection->disconnect();

      // Create new Spreadsheet object
      $spreadsheet = new Spreadsheet();

      // --- Sheet: Inventario ---
      $sheetInventario = $spreadsheet->getActiveSheet();
      $sheetInventario->setTitle('Inventario');

      // Set headers for Inventario sheet
      $headersInventario = ['Rollo', 'Nombre', 'Cantidad', 'Unidad', 'Costo', 'Rendimiento', 'Departamento'];
      $sheetInventario->fromArray($headersInventario, NULL, 'A1');

      // Set column widths for Inventario sheet
      foreach (range('A', 'G') as $col) {  // Adjusted range for new headers
        $sheetInventario->getColumnDimension($col)->setAutoSize(true);
      }

      // --- Hidden Sheet: ListadoRollosNormalizado ---
      $sheetRollosNormalizado = $spreadsheet->createSheet();
      $sheetRollosNormalizado->setTitle('ListadoRollosNormalizado');
      $sheetRollosNormalizado->setCellValue('A1', 'Rollo_Normalizado');  // Header
      $row = 2;
      foreach ($rollosExistentes as $rollo) {
        if (is_array($rollo) && isset($rollo['rollo'])) {  // Add this check
          $normalizedRollo = strtoupper(str_replace('_', '', $rollo['rollo']));
          $sheetRollosNormalizado->setCellValue('A' . $row, $normalizedRollo);
          $row++;
        }
      }
      $sheetRollosNormalizado->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

      // --- Hidden Sheet: ListadoUnidades ---
      $sheetUnidades = $spreadsheet->createSheet();
      $sheetUnidades->setTitle('ListadoUnidades');
      $unidades = ['Metros', 'Kilos', 'Unidades'];
      $sheetUnidades->fromArray([['Unidad']], NULL, 'A1');  // Header
      $row = 2;
      foreach ($unidades as $unidad) {
        $sheetUnidades->setCellValue('A' . $row, $unidad);
        $row++;
      }
      $sheetUnidades->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);  // Hide the sheet

      // --- Hidden Sheet: ListadoDepartamentos ---
      $sheetDepartamentos = $spreadsheet->createSheet();
      $sheetDepartamentos->setTitle('ListadoDepartamentos');
      $sheetDepartamentos->fromArray([['ID', 'Nombre']], NULL, 'A1');  // Headers for hidden sheet
      $row = 2;
      foreach ($departamentos as $departamento) {
        if (is_array($departamento) && isset($departamento['_id']) && isset($departamento['departamento'])) {  // Add this check
          $sheetDepartamentos->setCellValue('A' . $row, $departamento['_id']);
          $sheetDepartamentos->setCellValue('B' . $row, $departamento['departamento']);
          $row++;
        }
      }
      $sheetDepartamentos->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);  // Hide the sheet

      // --- Data Validation for Inventario sheet ---
      // Rollo (Column A) - Custom validation for uniqueness (case-insensitive, underscore-insensitive)
      $rolloValidation = $sheetInventario->getCell('A2')->getDataValidation();
      $rolloValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
      $rolloValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
      $rolloValidation->setAllowBlank(false);
      $rolloValidation->setShowErrorMessage(true);
      $rolloValidation->setErrorTitle('Rollo Duplicado');
      $rolloValidation->setError('El Rollo que ingresó ya existe en la base de datos o en este mismo archivo.');
      $formula = 'AND(COUNTIF(ListadoRollosNormalizado!A:A, SUBSTITUTE(UPPER(A2),"_",""))=0, COUNTIF(A:A,A2)=1)';
      $rolloValidation->setFormula1($formula);

      // Nombre (Column B) - No specific validation, can be text

      // Cantidad (Column C) - Numeric validation
      $cantidadValidation = $sheetInventario->getCell('C2')->getDataValidation();
      $cantidadValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
      $cantidadValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
      $cantidadValidation->setAllowBlank(false);
      $cantidadValidation->setShowErrorMessage(true);
      $cantidadValidation->setErrorTitle('Cantidad Inválida');
      $cantidadValidation->setError('La cantidad debe ser un número.');
      $cantidadValidation->setFormula1('0');  // Minimum value
      $cantidadValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);

      // Unidad (Column D) - List validation
      $unidadValidation = $sheetInventario->getCell('D2')->getDataValidation();
      $unidadValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
      $unidadValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
      $unidadValidation->setAllowBlank(false);
      $unidadValidation->setShowInputMessage(true);
      $unidadValidation->setShowErrorMessage(true);
      $unidadValidation->setShowDropDown(true);
      $unidadValidation->setErrorTitle('Error de entrada');
      $unidadValidation->setError('El valor no está en la lista de unidades.');
      $unidadValidation->setPromptTitle('Seleccionar Unidad');
      $unidadValidation->setPrompt('Por favor, seleccione una unidad de la lista (Metros, Kilos, Unidades).');
      $unidadValidation->setFormula1('\'ListadoUnidades\'!A$2:A$' . (count($unidades) + 1));  // Reference to names in hidden sheet

      // Costo (Column E) - Numeric validation
      $costoValidation = $sheetInventario->getCell('E2')->getDataValidation();
      $costoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
      $costoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
      $costoValidation->setAllowBlank(false);
      $costoValidation->setShowErrorMessage(true);
      $costoValidation->setErrorTitle('Costo Inválido');
      $costoValidation->setError('El costo debe ser un número.');
      $costoValidation->setFormula1('0');  // Minimum value
      $costoValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);

      // Rendimiento (Column F) - Numeric validation
      $rendimientoValidation = $sheetInventario->getCell('F2')->getDataValidation();
      $rendimientoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
      $rendimientoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
      $rendimientoValidation->setAllowBlank(true);  // Can be blank
      $rendimientoValidation->setShowErrorMessage(true);
      $rendimientoValidation->setErrorTitle('Rendimiento Inválido');
      $rendimientoValidation->setError('El rendimiento debe ser un número.');
      $rendimientoValidation->setFormula1('0');  // Minimum value
      $rendimientoValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);

      // Departamento (Column G) - List validation
      $departamentoValidation = $sheetInventario->getCell('G2')->getDataValidation();
      $departamentoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
      $departamentoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
      $departamentoValidation->setAllowBlank(false);
      $departamentoValidation->setShowInputMessage(true);
      $departamentoValidation->setShowErrorMessage(true);
      $departamentoValidation->setShowDropDown(true);
      $departamentoValidation->setErrorTitle('Error de entrada');
      $departamentoValidation->setError('El valor no está en la lista de departamentos.');
      $departamentoValidation->setPromptTitle('Seleccionar Departamento');
      $departamentoValidation->setPrompt('Por favor, seleccione un departamento de la lista.');
      $departamentoValidation->setFormula1('\'ListadoDepartamentos\'!B$2:B$' . (count($departamentos) + 1));  // Reference to names in hidden sheet

      // Apply validation to a range (e.g., up to row 1000)
      for ($i = 2; $i <= 1000; $i++) {
        $sheetInventario->getCell('A' . $i)->setDataValidation(clone $rolloValidation);  // Apply to Rollo column
        $sheetInventario->getCell('C' . $i)->setDataValidation(clone $cantidadValidation);
        $sheetInventario->getCell('D' . $i)->setDataValidation(clone $unidadValidation);
        $sheetInventario->getCell('E' . $i)->setDataValidation(clone $costoValidation);
        $sheetInventario->getCell('F' . $i)->setDataValidation(clone $rendimientoValidation);
        $sheetInventario->getCell('G' . $i)->setDataValidation(clone $departamentoValidation);
      }

      // Save the Excel file
      $fileName = 'plantilla_inventario_' . ID_EMPRESA . '.xlsx';
      $outputDirectory = __DIR__ . '/../public/downloads/carga_inventario/';  // New directory for inventory templates
      $filePath = $outputDirectory . $fileName;

      // Ensure the directory exists
      if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
      }

      $writer = new Xlsx($spreadsheet);
      $writer->save($filePath);

      // Generate the file URL with a cache-busting query parameter
      $fileUrl = '/downloads/carga_inventario/' . $fileName . '?v=' . time();

      // Return success response with file URL
      $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Plantilla Excel de inventario generada exitosamente.',
        'file_url' => $fileUrl
      ], JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    } catch (\Exception $e) {
      error_log('Error generating Excel for inventory: ' . $e->getMessage());
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Error al generar la plantilla Excel de inventario. Por favor, inténtelo de nuevo más tarde.',
        'error_details' => $e->getMessage()  // Solo para depuración, quitar en producción
      ], JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    }
  });

  $app->get('/api/products/template-excel-test', function (Request $request, Response $response) {
    // Conexiona a la base de datos
    $localConnection = new LocalDB();

    // ATRIBUTOS
    $sql = 'SELECT _id, attribute_name FROM products_attributes';
    $datax['atributos'] = $localConnection->goQuery($sql);

    // CATEGORIAS
    $sql = 'SELECT _id, nombre FROM categories';
    $datax['categorias'] = $localConnection->goQuery($sql);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Hola Mundo desde PhpSpreadsheet (Correcto)!');

    $writer = new Xlsx($spreadsheet);

    $idEmpresa = ID_EMPRESA;
    $filePath = __DIR__ . "/../public/downloads/carga_productos/carga_de_productos_{$idEmpresa}.xlsx";  // Guardar en el directorio public
    $writer->save($filePath);

    $fileUrl = "/downloads/carga_productos/carga_de_productos_{$idEmpresa}.xlsx";  // URL para acceder al archivo

    $localConnection->disconnect();
    // $response->getBody()->write(json_encode(['message' => 'Archivo Excel generado correctamente (Correcto)!', 'file_url' => $fileUrl], JSON_NUMERIC_CHECK));
    $response->getBody()->write(json_encode($datax, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/api/products/bulk-load', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // Forzar la conversión a array asociativo para asegurar compatibilidad
    if (is_object($data)) {
      $data = json_decode(json_encode($data), true);
    }

    $products = is_string($data['products']) ? json_decode($data['products'], true) : ($data['products'] ?? []);

    if (empty($products)) {
      $response->getBody()->write(json_encode(['error' => 'No se enviaron productos para procesar.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $db = new LocalDB();

    try {
      // 1. Obtener mapeos para convertir nombres de categorías a IDs
      $categories_db = $db->goQuery('SELECT _id, nombre FROM categories');
      $category_map = array_column($categories_db, '_id', 'nombre');

      $processed_count = 0;
      $error_list = [];

      foreach ($products as $product) {
        $sku = $product['SKU'] ?? null;
        if (empty($sku)) {
          $error_list[] = 'Se omitió un producto por no tener SKU.';
          continue;
        }

        // 2. Mapear el nombre de la Categoría a su ID
        $category_name = $product['Categoría'] ?? null;
        $category_id = $category_map[$category_name] ?? null;

        // 3. Verificar si el producto ya existe por SKU
        $check_sql = 'SELECT _id FROM products WHERE sku = ?';
        $existing_product = $db->goQuery($check_sql, [$sku]);

        $product_id = null;

        if ($existing_product) {
          // Lógica de ACTUALIZACIÓN
          $product_id = $existing_product[0]['_id'];
          $update_sql = 'UPDATE products SET product = ?, category_ids = ? WHERE _id = ?';
          $db->goQuery($update_sql, [
            $product['Nombre'] ?? 'Sin Nombre',
            $category_id,
            $product_id
          ]);

          // Borrar precios antiguos para reemplazarlos
          $delete_prices_sql = 'DELETE FROM products_prices WHERE id_product = ?';
          $db->goQuery($delete_prices_sql, [$product_id]);
        } else {
          // Lógica de INSERCIÓN
          $insert_sql = 'INSERT INTO products (product, sku, category_ids, fisico) VALUES (?, ?, ?, 1)';
          $db->goQuery($insert_sql, [
            $product['Nombre'] ?? 'Sin Nombre',
            $sku,
            $category_id
          ]);
          $product_id = $db->getLastID();  // Se asume que LocalDB tiene un método para obtener el último ID
        }

        // 4. Insertar los precios (tanto para productos nuevos como actualizados)
        if ($product_id && isset($product['precios']) && is_array($product['precios'])) {
          foreach ($product['precios'] as $price_info) {
            // Asegurarse de que tanto el valor como la descripción existan
            if (isset($price_info['valor']) && isset($price_info['descripcion'])) {
              $insert_price_sql = 'INSERT INTO products_prices (id_product, price, descripcion) VALUES (?, ?, ?)';
              $db->goQuery($insert_price_sql, [
                $product_id,
                $price_info['valor'],
                $price_info['descripcion']
              ]);
            }
          }
        }
        $processed_count++;
      }

      $message = "Carga masiva completada. Se procesaron {$processed_count} productos.";
      if (!empty($error_list)) {
        $message .= ' Errores: ' . implode(', ', $error_list);
      }

      $response->getBody()->write(json_encode(['message' => $message]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al procesar la carga masiva: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $db->disconnect();
    }
  });

  $app->post('/api/inventario/bulk-load', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // Forzar la conversión a array asociativo para asegurar compatibilidad
    if (is_object($data)) {
      $data = json_decode(json_encode($data), true);
    }

    $inventoryItems = is_string($data['inventoryItems']) ? json_decode($data['inventoryItems'], true) : ($data['inventoryItems'] ?? []);

    if (empty($inventoryItems)) {
      $response->getBody()->write(json_encode(['error' => 'No se enviaron ítems de inventario para procesar.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $db = new LocalDB();

    try {
      // Obtener mapeos para convertir nombres de departamentos a IDs
      $departamentos_db = $db->goQuery('SELECT _id, departamento FROM departamentos');
      $departamento_map = array_column($departamentos_db, '_id', 'departamento');

      $processed_count = 0;
      $error_list = [];

      foreach ($inventoryItems as $item) {
        $rollo = $item['Rollo'] ?? null;
        $insumo = $item['Nombre'] ?? null;
        $cantidad = $item['Cantidad'] ?? null;
        $unidad = $item['Unidad'] ?? null;
        $costo = $item['Costo'] ?? null;
        $rendimiento = $item['Rendimiento'] ?? null;
        $departamento_nombre = $item['Departamento'] ?? null;
        $sku = $item['SKU'] ?? null;

        // Validaciones básicas
        if (empty($rollo) || empty($insumo) || empty($cantidad) || empty($unidad) || empty($costo) || empty($departamento_nombre)) {
          $error_list[] = "Ítem de inventario incompleto (Rollo: {$rollo}, Nombre: {$insumo}). Se omitió.";
          continue;
        }

        // Normalizar Rollo para la búsqueda
        $normalized_rollo = strtoupper(str_replace('_', '', $rollo));

        // Mapear el nombre del Departamento a su ID
        $departamento_id = $departamento_map[$departamento_nombre] ?? null;

        if ($departamento_id === null) {
          $error_list[] = "Departamento '{$departamento_nombre}' no encontrado para el rollo {$rollo}. Se omitió.";
          continue;
        }

        // Verificar si el ítem de inventario ya existe por Rollo (normalizado)
        $check_sql = "SELECT _id, rollo, sku FROM inventario WHERE REPLACE(UPPER(rollo), '_', '') = ?";
        $existing_item = $db->goQuery($check_sql, [$normalized_rollo]);

        $item_id = null;

        if ($existing_item) {
          // Lógica de ACTUALIZACIÓN
          $item_id = $existing_item[0]['_id'];
          $update_sql = 'UPDATE inventario SET insumo = ?, unidad = ?, costo = ?, rendimiento = ?, cantidad = ?, departamento = ?, sku = ? WHERE _id = ?';
          $db->goQuery($update_sql, [
            $insumo,
            $unidad,
            $costo,
            $rendimiento,
            $cantidad,
            $departamento_nombre,
            $sku,
            $item_id
          ]);
        } else {
          // Lógica de INSERCIÓN
          $insert_sql = 'INSERT INTO inventario (rollo, insumo, unidad, costo, rendimiento, cantidad, departamento, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
          $db->goQuery($insert_sql, [
            $rollo,
            $insumo,
            $unidad,
            $costo,
            $rendimiento,
            $cantidad,
            $departamento_nombre,
            $sku
          ]);
          $item_id = $db->getLastID();  // Se asume que LocalDB tiene un método para obtener el último ID
        }
        $processed_count++;
      }

      $message = "Carga masiva de inventario completada. Se procesaron {$processed_count} ítems.";
      if (!empty($error_list)) {
        $message .= ' Errores: ' . implode(', ', $error_list);
      }

      $response->getBody()->write(json_encode(['message' => $message]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al procesar la carga masiva de inventario: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $db->disconnect();
    }
  });

  /**
   * =================================================================
   * ENDPOINTS PARA GESTIÓN DE LOTES DE FABRICACIÓN (CORREGIDO)
   * =================================================================
   */

  /**
   * POST /lotes
   * Crea un nuevo lote de fabricación a partir de una lista de órdenes.
   */
  $app->post('/lotes', function (Request $request, Response $response) {
    // 1. Obtener los datos del cuerpo de la solicitud
    $data = $request->getParsedBody();
    $id_empleado = $data['id_empleado'] ?? null;
    $id_departamento = $data['id_departamento'] ?? null;
    $ordenes_str = $data['ordenes'] ?? '';

    // 2. Validar que los datos necesarios están presentes
    if (empty($id_empleado) || empty($id_departamento) || empty($ordenes_str)) {
      $error_response = ['error' => 'Faltan parámetros requeridos: id_empleado, id_departamento y ordenes son obligatorios.'];
      $response->getBody()->write(json_encode($error_response));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    $object = [];  // Objeto para la respuesta final
    $status_code = 201;  // Código de éxito por defecto

    try {
      // 3. Crear el registro maestro del lote en la tabla `empleados_lotes_fabricacion`.
      // Se asume que el estado inicial es '0' (pendiente).
      $sql_create_lote = 'INSERT INTO empleados_lotes_fabricacion (id_empleado, id_departamento, estado) VALUES (?, ?, 0)';
      $params_create_lote = [$id_empleado, $id_departamento];

      $localConnection->goQuery($sql_create_lote, $params_create_lote);

      // 4. Obtener el ID del lote recién creado desde la tabla correcta
      $result_last_id = $localConnection->goQuery('SELECT MAX(_id) id FROM empleados_lotes_fabricacion');
      $id_lote = $result_last_id[0]['id'] ?? null;

      if (empty($id_lote)) {
        throw new Exception('No se pudo obtener el ID del lote recién creado tras la inserción.');
      }

      // 5. Vincular cada orden al nuevo lote en la tabla `empleados_lotes_fabricacion_items`
      $ordenes_ids = explode(',', $ordenes_str);

      $sql_link_orden = 'INSERT INTO empleados_lotes_fabricacion_items (id_lote, id_orden) VALUES (?, ?)';

      foreach ($ordenes_ids as $id_orden) {
        $trimmed_id_orden = trim($id_orden);
        if (is_numeric($trimmed_id_orden) && !empty($trimmed_id_orden)) {
          $params_link_orden = [$id_lote, $trimmed_id_orden];
          $localConnection->goQuery($sql_link_orden, $params_link_orden);
        }
      }

      // 6. Preparar la respuesta de éxito
      $object['message'] = 'Lote creado exitosamente.';
      $object['id_lote'] = $id_lote;
    } catch (Exception $e) {
      $object['error'] = 'Error del servidor al crear el lote: ' . $e->getMessage();
      $status_code = 500;
    } finally {
      if ($localConnection) {
        $localConnection->disconnect();
      }
    }

    // 7. Devolver la respuesta JSON
    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($status_code);
  });

  /**
   * POST /lotes/{id}/iniciar
   * Inicia todas las órdenes contenidas en un lote de fabricación.
   */
  $app->post('/lotes/{id}/iniciar', function (Request $request, Response $response, array $args) {
    $id_lote = intval($args['id']);
    $localConnection = new LocalDB();

    // NOTA: Operaciones secuenciales. Considerar añadir transacciones a LocalDB
    // para mayor robustez.
    try {
      // 1. Actualizar el estado del lote
      $sql_update_lote = "UPDATE empleados_lotes_fabricacion SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = ? AND estado = 'pendiente'";
      $result = $localConnection->goQuery($sql_update_lote, [$id_lote]);

      // 2. Obtener todas las órdenes del lote
      $sql_get_ordenes = 'SELECT id_orden FROM empleados_lotes_fabricacion_items WHERE id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_get_ordenes, [$id_lote]);

      if (empty($ordenes_del_lote)) {
        throw new Exception("No se encontraron órdenes para el lote {$id_lote}.");
      }

      // 3. Iniciar cada orden individualmente (ajusta la tabla/campos según tu lógica real)
      $sql_iniciar_orden = "UPDATE lotes_detalles_empleados_asignados SET fecha_inicio = NOW(), progreso = 'en curso' WHERE id_orden = ?";

      foreach ($ordenes_del_lote as $orden) {
        $localConnection->goQuery($sql_iniciar_orden, [$orden['id_orden']]);
      }

      $response->getBody()->write(json_encode([
        'status' => 'success',
        'message' => "Lote {$id_lote} y sus " . count($ordenes_del_lote) . ' órdenes han sido iniciados.'
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log("Error al iniciar lote {$id_lote}: " . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al iniciar el lote.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // CRUD para Catalogo de Impresoras
  $app->post('/impresoras', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    try {
      // Validación básica: el codigo_interno es obligatorio
      if (empty($data['codigo_interno'])) {
        $response->getBody()->write(json_encode(['error' => 'El campo codigo_interno es obligatorio.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      $sql = 'INSERT INTO catalogo_impresoras (codigo_interno, marca, modelo, ubicacion, tipo_tecnologia, estado, notas) VALUES (?, ?, ?, ?, ?, ?, ?)';

      $params = [
        $data['codigo_interno'],
        $data['marca'] ?? null,
        $data['modelo'] ?? null,
        $data['ubicacion'] ?? null,
        $data['tipo_tecnologia'] ?? null,
        $data['estado'] ?? 'activa',  // Valor por defecto 'activa'
        $data['notas'] ?? null
      ];

      $localConnection->goQuery($sql, $params);
      $new_id = $localConnection->getLastID();

      $response->getBody()->write(json_encode(['message' => 'Impresora creada exitosamente.', 'id' => $new_id]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);  // 201 Created
    } catch (Exception $e) {
      // Manejo de error, por ejemplo, si el codigo_interno ya existe (duplicado)
      if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $response->getBody()->write(json_encode(['error' => 'Error: El codigo_interno ya existe.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);  // 409 Conflict
      }

      error_log('Error al crear impresora: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al crear la impresora.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  $app->get('/impresoras', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    try {
      $sql = "SELECT 
                    ci._id, 
                    ci.codigo_interno, 
                    ci.marca, 
                    ci.modelo, 
                    ci.ubicacion, 
                    ci.tipo_tecnologia, 
                    ci.estado, 
                    ci.notas, 
                    ci.moment,
                    CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', tr._id,
                                'id_catalogo_impresora', tr.id_catalogo_impresora,
                                'id_insumo', tr.id_insumo,
                                'color', tr.color,
                                'cantidad', tr.cantidad,
                                'fecha_recarga', tr.fecha_recarga
                            )
                        ),
                        ']'
                    ) AS tintas_recargas
                FROM 
                    catalogo_impresoras ci
                LEFT JOIN 
                    tintas_recargas tr ON ci._id = tr.id_catalogo_impresora
                GROUP BY 
                    ci._id, ci.codigo_interno, ci.marca, ci.modelo, ci.ubicacion, ci.tipo_tecnologia, ci.estado, ci.notas, ci.moment
                ORDER BY 
                    ci._id DESC";
      $data = $localConnection->goQuery($sql);

      // Decodificar el JSON de tintas_recargas para cada fila
      foreach ($data as &$row) {
        $row['tintas_recargas'] = json_decode($row['tintas_recargas'], true);
        // Si no hay recargas, json_decode puede devolver null o un array con un solo null. Aseguramos un array vacío.
        if ($row['tintas_recargas'] === null || (is_array($row['tintas_recargas']) && count($row['tintas_recargas']) == 1 && $row['tintas_recargas'][0] === null)) {
          $row['tintas_recargas'] = [];
        }
      }

      $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log('Error al obtener impresoras: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al obtener las impresoras.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });
  $app->get('/impresoras-tintas-actual', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    try {
      $sql = <<<SQL
        -- Usamos Common Table Expressions (CTEs) para organizar la lógica en pasos.

        WITH 
        -- =======================================================================================
        -- PASO 1: "Desglosar" el consumo. La tabla `tintas` tiene una columna por color.
        -- La convertimos a un formato largo (una fila por consumo de color) para facilitar los cálculos.
        -- =======================================================================================
        consumo_desglosado AS (
            SELECT id_catalogo_impresoras, 'C' AS color, c AS consumo, t.moment AS fecha_orden FROM tintas t JOIN ordenes o ON t.id_orden = o._id WHERE c > 0
            UNION ALL
            SELECT id_catalogo_impresoras, 'M' AS color, m AS consumo, t.moment AS fecha_orden FROM tintas t JOIN ordenes o ON t.id_orden = o._id WHERE m > 0
            UNION ALL
            SELECT id_catalogo_impresoras, 'Y' AS color, y AS consumo, t.moment AS fecha_orden FROM tintas t JOIN ordenes o ON t.id_orden = o._id WHERE y > 0
            UNION ALL
            SELECT id_catalogo_impresoras, 'K' AS color, k AS consumo, t.moment AS fecha_orden FROM tintas t JOIN ordenes o ON t.id_orden = o._id WHERE k > 0
            UNION ALL
            SELECT id_catalogo_impresoras, 'W' AS color, w AS consumo, t.moment AS fecha_orden FROM tintas t JOIN ordenes o ON t.id_orden = o._id WHERE w > 0
        ),

        -- =======================================================================================
        -- PASO 2: Encontrar la fecha de la ÚLTIMA CONSUMO para cada tanque (impresora + color).
        -- Esto nos ayudará a saber desde cuándo debemos sumar las recargas.
        -- =======================================================================================
        last_consumption_per_tank AS (
            SELECT
                id_catalogo_impresoras,
                color,
                MAX(fecha_orden) AS last_consumption_date
            FROM
                consumo_desglosado
            GROUP BY
                id_catalogo_impresoras,
                color
        ),

        -- =======================================================================================
        -- PASO 3: Calcular la CANTIDAD TOTAL recargada para cada tanque desde la última vez que hubo consumo.
        -- Si nunca hubo consumo, se suman todas las recargas.
        -- =======================================================================================
        total_recargas_desde_ultimo_consumo AS (
            SELECT
                tr.id_catalogo_impresora,
                tr.color,
                SUM(tr.cantidad) AS total_cantidad_recargada,
                MAX(tr.fecha_recarga) AS fecha_ultima_recarga -- La fecha de la última recarga sigue siendo útil
            FROM
                tintas_recargas tr
            GROUP BY
                tr.id_catalogo_impresora,
                tr.color
        )

        -- =======================================================================================
        -- PASO 4: Unir todo y calcular el resultado final.
        -- =======================================================================================
        SELECT
            ci.codigo_interno AS impresora,
            trslc.color,
            ci.capacidad_contenedor AS capacidad_tanque_ml,
            trslc.fecha_ultima_recarga AS fecha_ultima_recarga,
            trslc.total_cantidad_recargada AS total_recargado_ml,
            -- Sumamos todo el consumo que ocurrió DESPUÉS de la última recarga.
            COALESCE(SUM(cd.consumo), 0) AS consumo_desde_ultima_recarga_ml,
            -- El cálculo final: Tinta recargada MENOS tinta consumida.
            (COALESCE(trslc.total_cantidad_recargada, 0) - COALESCE(SUM(cd.consumo), 0)) AS tinta_restante_estimada_ml
        FROM
            -- Empezamos con el total de recargas desde el último consumo
            total_recargas_desde_ultimo_consumo trslc
            
        -- Unimos con el catálogo de impresoras para obtener sus nombres
        JOIN
            catalogo_impresoras ci ON ci._id = trslc.id_catalogo_impresora
            
        -- Hacemos un LEFT JOIN con el consumo. Usamos LEFT por si no ha habido consumo desde la última recarga.
        LEFT JOIN
            consumo_desglosado cd 
            ON trslc.id_catalogo_impresora = cd.id_catalogo_impresoras 
            AND trslc.color = cd.color
            -- ¡ESTA ES LA LÓGICA CLAVE! Solo contamos el consumo cuya fecha de orden es POSTERIOR a la fecha de la última recarga.
            AND cd.fecha_orden > trslc.fecha_ultima_recarga
            
        GROUP BY
            ci.codigo_interno,
            trslc.color,
            ci.capacidad_contenedor,
            trslc.fecha_ultima_recarga,
            trslc.total_cantidad_recargada
        ORDER BY
            impresora,
            color;
        SQL;
      $data = $localConnection->goQuery($sql);

      // Decodificar el JSON de tintas_recargas para cada fila
      /* foreach ($data as &$row) {
          $row['tintas_recargas'] = json_decode($row['tintas_recargas'], true);
          // Si no hay recargas, json_decode puede devolver null o un array con un solo null. Aseguramos un array vacío.
          if ($row['tintas_recargas'] === null || (is_array($row['tintas_recargas']) && count($row['tintas_recargas']) == 1 && $row['tintas_recargas'][0] === null)) {
              $row['tintas_recargas'] = [];
          }
      } */

      $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log('Error al obtener las tintas de las impresoras: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al obtener las tintas de las impresoras.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  $app->put('/impresoras/{id}', function (Request $request, Response $response, array $args) {
    $id_impresora = $args['id'];

    // Parsear manualmente el cuerpo de la solicitud PUT
    $raw_body = (string) $request->getBody();
    parse_str($raw_body, $data);

    $localConnection = new LocalDB();

    try {
      // Verificar si la impresora existe
      $check_sql = 'SELECT _id FROM catalogo_impresoras WHERE _id = ?';
      $existing = $localConnection->goQuery($check_sql, [$id_impresora]);
      if (!$existing) {
        $response->getBody()->write(json_encode(['error' => 'La impresora con el ID proporcionado no existe.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);  // Not Found
      }

      // Construir la consulta de actualización dinámicamente
      $fields = [];
      $params = [];
      $allowed_fields = ['codigo_interno', 'marca', 'modelo', 'ubicacion', 'tipo_tecnologia', 'estado', 'notas'];

      foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
          $fields[] = "`{$key}` = ?";
          $params[] = $value;
        }
      }

      if (empty($fields)) {
        $response->getBody()->write(json_encode(['error' => 'No se proporcionaron campos para actualizar.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      $sql = 'UPDATE catalogo_impresoras SET ' . implode(', ', $fields) . ' WHERE _id = ?';
      $params[] = $id_impresora;

      $localConnection->goQuery($sql, $params);

      $response->getBody()->write(json_encode(['message' => 'Impresora actualizada exitosamente.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $response->getBody()->write(json_encode(['error' => 'Error: El codigo_interno ya existe.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);  // Conflict
      }

      error_log('Error al actualizar impresora: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al actualizar la impresora.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  $app->post('/inventario-tintas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    try {
      // Validación básica
      if (empty($data['id_impresora']) || empty($data['id_insumo']) || empty($data['color']) || empty($data['mililitros'])) {
        $response->getBody()->write(json_encode(['error' => 'Faltan campos obligatorios.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      // PREPARAR FECHA
      $myDate = new CustomTime();
      $now = $myDate->today();

      $sql = 'INSERT INTO tintas_recargas (id_catalogo_impresora, id_insumo, color, cantidad, fecha_recarga) VALUES (?, ?, ?, ?, ?)';

      $params = [
        $data['id_impresora'],
        $data['id_insumo'],
        $data['color'],
        $data['mililitros'],
        $now
      ];

      $localConnection->goQuery($sql, $params);
      $new_id = $localConnection->getLastID();

      // Obtener la cantidad actual del insumo en inventario
      $sql_get_cantidad = 'SELECT cantidad FROM inventario WHERE _id = ?';
      $current_cantidad_result = $localConnection->goQuery($sql_get_cantidad, [$data['id_insumo']]);

      if (is_array($current_cantidad_result) && !empty($current_cantidad_result) && isset($current_cantidad_result[0]['cantidad'])) {
        $current_cantidad = (float) $current_cantidad_result[0]['cantidad'];
        $mililitros_a_restar = (float) $data['mililitros'];
        $new_cantidad = $current_cantidad - $mililitros_a_restar;

        // Actualizar la cantidad en la tabla inventario
        $sql_update_inventario = 'UPDATE inventario SET cantidad = ? WHERE _id = ?';
        $localConnection->goQuery($sql_update_inventario, [$new_cantidad, $data['id_insumo']]);
      } else {
        // Manejar el caso donde el insumo no se encuentra o no tiene cantidad
        throw new Exception('Insumo no encontrado o cantidad no disponible en inventario.');
      }

      $response->getBody()->write(json_encode(['message' => 'Recarga de tinta registrada exitosamente y cantidad de insumo actualizada.', 'id' => $new_id]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (Exception $e) {
      error_log('Error al registrar recarga de tinta: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al registrar la recarga.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  $app->delete('/impresoras/{id}', function (Request $request, Response $response, array $args) {
    $id_impresora = $args['id'];
    $localConnection = new LocalDB();

    try {
      // Opcional: Verificar si la impresora existe antes de intentar eliminarla
      $check_sql = 'SELECT _id FROM catalogo_impresoras WHERE _id = ?';
      $existing = $localConnection->goQuery($check_sql, [$id_impresora]);
      if (!$existing) {
        $response->getBody()->write(json_encode(['error' => 'La impresora con el ID proporcionado no existe.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);  // Not Found
      }

      $sql = 'DELETE FROM catalogo_impresoras WHERE _id = ?';
      $localConnection->goQuery($sql, [$id_impresora]);

      $response->getBody()->write(json_encode(['message' => 'Impresora eliminada exitosamente.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log('Error al eliminar impresora: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al eliminar la impresora.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  /** * PRUEBAS DE HISTÓRICO */

  /* $app->get('/h/backup/pagos', function (Request $request, Response $response) {
        $sql = "SELECT MAX(_id) + 1 id FROM ordenes";

        $localConnection = new LocalDB();
        $data = $localConnection->goQueryCopy($sql);
        $localConnection->disconnect();

        if (!$data[0]["id"]) {
            $data[0]["id"] = "1";
        }

        $input = str_pad($data[0]["id"], 3, "0", STR_PAD_LEFT);
        // $input = '33';
        // $nextId["crudo"] =  $data[0]["id"];
        $nextId["id"] = str_pad($input, 3, "0", STR_PAD_LEFT);

        $response->getBody()->write(json_encode($nextId, JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }); */
  /** * FIN PRUEBAS DE HISTÓRICO */

  /** WhsatsApp */

  // GUARDAR DATOS DE LA CONFIGURACIÓN DEL SISTEMA
  // $app->get('/config', function (Request $request, Response $response) {
  $app->post('/config/select-empleados', function (Request $request, Response $response, $args) {
    $datos = $request->getParsedBody();

    /*  if ($datos["estado"] == true) {
       $estado = 1;
     } else {
       $estado = 0;
     } */

    // DETERMINAR QUE DEPARTAMENTO HACE QUE
    $departamento = $datos['departamento'];

    switch ($departamento) {
      case 'Estampado':
        $campo = 'sys_mostrar_rollo_en_empleado_estampado';
        break;
      case 'Corte':
        $campo = 'sys_mostrar_rollo_en_empleado_corte';
        break;
      case 'Costura':
        $campo = 'sys_mostrar_insumo_en_empleado_costura';
        break;
      case 'Limpieza':
        $campo = 'sys_mostrar_insumo_en_empleado_limpieza';
        break;
      case 'Revisión':
        $campo = 'sys_mostrar_insumo_en_empleado_revision';
        break;
      default:
        $campo = 'Unknown';
        break;
    }

    if ($campo != 'Unknown') {
      $localConnection = new LocalDB();
      $sql = 'UPDATE config SET ' . $campo . ' = ' . $datos['estado'] . ' WHERE _id = 1';
      $object['sql'] = $sql;
      $object['response'] = $localConnection->goQuery($sql);
      $localConnection->disconnect();
    } else {
      $object['response'] = 'No existe el departamento ' . $datos['departamento'];
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CONVERTIR ORDENES EN JSON
  $app->get('/orders-json/{id_orden}', function (Request $request, Response $response, $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                ord._id id,
                ord.cliente_cedula cedula,
                ord.cliente_nombre nombre_completo,
                cus.first_name nombre,
                cus.last_name apellido,
                cus.phone telefono,
                cus.email,
                cus.address,
                ord.fecha_entrega fechaEntrega,
                (
                    SELECT
                        CONCAT(
                            \"[\",
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    \"item\",
                                    orp.id_woo,
                                    \"cod\",
                                    orp._id,
                                    \"producto\",
                                    orp.name,
                                    \"existencia\",
                                    orp.cantidad,
                                    \"talla\",
                                    orp.talla,
                                    \"tela\",
                                    orp.tela,
                                    \"corte\",
                                    orp.corte,                                   
                                    \"precio\",
                                    orp.precio_unitario
                                )
                            ),
                            \"]\"
                        )
                    FROM
                        api_emp_2.ordenes_productos orp
                    WHERE
                        orp.id_orden = ord._id
                ) AS productos,
                'TRAER DESDE EL `ENDPOINT` DEDICADO' obs,
                ord.pago_abono abono,
                '0' descuento,
                ord.pago_total total,
                NULL diseno_grafico,
                NULL diseno_modas,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Zelle' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresZelle,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Zelle' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresZelleDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Panamá' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresPanama,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Panamá' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresPanamaDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosTransferencia,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosTransferenciaDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Punto' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPunto,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Punto' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPuntoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Pagomovil' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPagomovil,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Pagomovil' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPagomovilDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Transferencia' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresTransferencia,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Transferencia' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresTransferenciaDetalle,
                NULL descuentoDetalle,
                1 sales_commision,
                NULL diseno_grafico_cantidad
            FROM
                api_emp_2.ordenes ord
            LEFT JOIN api_emp_2.ordenes_productos orp ON orp.id_orden = ord._id
            LEFT JOIN api_emp_2.customers cus ON  cus._id = ord.id_wp
            WHERE ord._id  = {$args['id_orden']} 
            GROUP BY ord._id, ord.cliente_cedula, ord.cliente_nombre, cus.first_name, cus.last_name, cus.phone, cus.email, cus.address, ord.fecha_entrega, ord.observaciones, ord.pago_abono, ord.pago_total;
        ";
    $data['form'] = $localConnection->goQuery($sql);

    foreach ($data['form'] as &$row) {
      if ($row['productos'] !== null) {
        $row['productos'] = json_decode($row['productos'], true);
      }
    }
    // GUARDAR LA ORDEN
    $data['tipo'] = 'Orden';
    $data['id_empleado'] = '1';

    $sql = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES ('" . json_encode($data['form'][0]) . "', " . $data['id_empleado'] . ", '" . $data['tipo'] . "')";
    $object['sql_insert'] = $sql;
    $data['response_INSERT'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/send-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();

    // Enviar WhatsApp Aqui
    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
    $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'general', $dataMensaje['mensaje']);

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));

    return $response;
  });

  // OBTENER DEPARTAMENTOS PARA ENVIO DE WHATSAPP
  $app->get('/ws/departamentos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT
            a.id_orden,
            b._id id_departamento,
            b.departamento,
            b.orden_proceso,
            a.progreso
        FROM
            lotes_detalles_empleados_asignados a
        JOIN departamentos b ON
            b._id = a.id_departamento
        JOIN ordenes c ON c._id = a.id_orden
        WHERE
            c.status LIKE 'activa' OR c.status LIKE 'pausada' OR c.status LIKE 'En espera'
        ORDER BY c._id, b.orden_proceso ASC
        ";
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CONSTRUIR MENSAJES DE WHATSAPP PARA CLIENTES
  $app->post('/ws/build-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $localConnection = new LocalDB();  // Asegúrate de que esta clase y su conexión/desconexión sean manejadas correctamente.

    $result = [];  // Inicializar $result para evitar errores si no se entra en ninguna condición

    // Validar que los datos necesarios están presentes
    if (!isset($dataMensaje['tipo']) || !isset($dataMensaje['id_orden'])) {
      $result['error'] = 'Faltan parámetros requeridos: tipo o id_orden.';
      $localConnection->disconnect();  // Asegúrate de desconectar incluso en caso de error temprano

      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')  // Eliminado el duplicado 'Access-Control-Allow-Headers'
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400)  // Bad Request si faltan parámetros
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }

    $tipo = $dataMensaje['tipo'];
    $id_orden = $dataMensaje['id_orden'];  // Considera sanitizar/validar $id_orden antes de usarlo en SQL

    // Buscar datos de la orden
    // Es MUY RECOMENDABLE usar sentencias preparadas para prevenir inyección SQL
    // Ejemplo conceptual (la implementación exacta depende de tu clase LocalDB):
    // $sql = "SELECT a._id id_orden, ... FROM ordenes a ... WHERE a._id = ?";
    // $orden = $localConnection->goQuery($sql, [$id_orden]);
    $sql = "SELECT
        a._id id_orden,
        a.cliente_nombre,
        b.phone,
        a.pago_descuento,
        a.pago_abono,
        a.pago_total,
        ((a.pago_total -  a.pago_descuento) - a.pago_abono) monto_pendiente,
        DATE_FORMAT(a.fecha_entrega, '%d/%m/%Y') fecha_entrega
        FROM
            ordenes a
        JOIN customers b ON b._id = a.id_wp
        WHERE
            a._id = " . intval($id_orden);  // Sanitización básica, pero sentencias preparadas son mejores.
    $orden_data = $localConnection->goQuery($sql);

    if (empty($orden_data)) {
      $result['error'] = "Orden con ID {$id_orden} no encontrada.";
      $localConnection->disconnect();
      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404)  // Not Found
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }
    $orden = $orden_data[0];  // Asumimos que _id es único y siempre devuelve un solo registro si existe

    // Buscar los productos asociados a la orden
    $sql_products = "SELECT CONCAT('- *',a.name, ':* Talla ', a.talla, ', ', a.cantidad, ' unidades') item_product FROM ordenes_productos a WHERE a.id_orden = " . intval($id_orden) . ' ORDER BY a.name ASC';
    $products_data = $localConnection->goQuery($sql_products);

    // CARGAR DATOS

    // DATOS ORDEN
    $phone = $orden['phone'];

    // PRODUCTOS
    // Usar comillas dobles para que \n se interprete como salto de línea
    $productos_string = '';  // Inicializar como cadena vacía. El primer \n puede venir de la plantilla.
    if (!empty($products_data)) {
      foreach ($products_data as $item) {
        $productos_string .= $item['item_product'] . "\n";  // Usar comillas dobles para el salto de línea
      }
      // Opcional: remover el último \n si no se desea un salto de línea extra al final de la lista
      $productos_string = rtrim($productos_string, "\n");
    } else {
      $productos_string = 'No hay productos detallados para esta orden.';  // Mensaje por si no hay productos
    }

    // Determinar tipo de mensaje
    switch ($tipo) {
      case 'inicio':
        $sql_config = 'SELECT msg_welcome msg FROM config WHERE _id = 1';  // Las comillas simples están bien aquí si no hay \n
        break;

      case 'fin':
        $sql_config = 'SELECT msg_bye msg FROM config WHERE _id = 1';  // Las comillas simples están bien aquí si no hay \n
        break;

      case 'paso':
        $sql_config = "SELECT mensaje msg FROM departamentos WHERE _id = {$dataMensaje['id_departamento']}";
        break;

      case 'custom':
        $sql_config = null;
        break;

      case 'cobro':
        $sql_config = "SELECT
                    a._id id_orden,
                    a.pago_total,        
                    (SUM(b.abono)) total_abonos,
                    (SUM(b.descuento)) total_descuentos,
                    (a.pago_total - (SUM(b.descuento)) - SUM(b.abono))  deuda,
                    (SELECT msg_saldo FROM config WHERE _id = 1) msg
                FROM
                    ordenes a
                    JOIN abonos b ON b.id_orden = a._id    
                WHERE
                    a._id = $id_orden";  // Las comillas simples están bien aquí si no hay \n
        break;
    }

    if ($sql_config !== null) {
      $config_data = $localConnection->goQuery($sql_config);

      if (empty($config_data) || !isset($config_data[0]['msg'])) {
        $result['error'] = 'Mensaje configurado en la base de datos.';
        $localConnection->disconnect();
        return $response
          ->withHeader('Access-Control-Allow-Origin', '*')
          ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500)  // Error del servidor: configuración faltante
          ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
      }
      $mensaje_plantilla = $config_data[0]['msg'];

      // Importante: Si la plantilla `msg_welcome` de la BD también tiene '\n' literales,
      // necesitarás reemplazarlos también.
      // Ejemplo: $mensaje_plantilla = str_replace('\n', "\n", $mensaje_plantilla);
      $reemplazo = [];

      if ($tipo === 'inicio' || $tipo === 'fin' || $tipo === 'paso') {
        $busqueda = ['[CLIENTE]', '[ORDEN_ID]', '[FECHA_ENTREGA]', '[PRODUCTOS]', '[TOTAL_ORDEN]'];
        $reemplazo = [
          "{$orden['cliente_nombre']}",
          "{$orden['id_orden']}",
          "{$orden['fecha_entrega']}",
          $productos_string,
          // CORRECCIÓN: Convertir la cadena a float antes de formatear
          // number_format(floatval($orden['pago_total']), 2, '.', ',')
        ];
      } else if ($tipo === 'cobro') {
        $busqueda = ['[CLIENTE]', '[ORDEN_ID]', '[FECHA_ENTREGA]', '[PRODUCTOS]', '[TOTAL_ORDEN]', '[TOTAL_ABONOS]', '[TOTAL_DESCUENTOS]', '[TOTAL_DEUDA]'];
        $reemplazo = [
          "{$orden['cliente_nombre']}",
          "{$orden['id_orden']}",
          "{$orden['fecha_entrega']}",
          "{$productos_string}",
          "{$config_data[0]['pago_total']}",
          "{$config_data[0]['total_abonos']}",
          "{$config_data[0]['total_descuentos']}",
          "{$config_data[0]['deuda']}",
          $productos_string,
          // CORRECCIÓN: Convertir la cadena a float antes de formatear
          // number_format(floatval($orden['pago_total']), 2, '.', ',')
        ];
      } else if ($tipo === 'custom') {
        $mensaje_plantilla = $dataMensaje['message'];
      }

      $result['msg_ws'] = str_replace($busqueda, $reemplazo, $mensaje_plantilla);
      $result['mensaje_plantilla_original'] = $mensaje_plantilla;  // Para depuración
    } else {
      $result['msg_ws'] = $dataMensaje['message'];
      // Enviar mensaje personalizado
    }

    // Enviar mensaje
    // Considera obtener ID_EMPRESA de una constante o configuración de forma más segura.
    if (!defined('ID_EMPRESA')) {
      // Manejar el caso donde ID_EMPRESA no está definida
      $result['error_envio'] = 'ID_EMPRESA no está definida. No se puede enviar el mensaje.';
      $localConnection->disconnect();
      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500)
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }

    // La URL base del API de WhatsApp debería ser idealmente una constante o configuración
    // El constructor de WhatsAppAPIClient debería tomar la URL base del API, no un endpoint específico.
    // $apiBaseUrl = 'https://ws.nineteengreen.com/'; // O 'http://localhost:3000' si es local
    // $msgApi = new WhatsAppAPIClient($apiBaseUrl);

    // Asumiendo que la clase WhatsAppAPIClient fue ajustada como se discutió o que el constructor maneja esto:
    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/');  // Ajustar según la implementación de tu clase

    $testResp = $msgApi->sendMessageCustom(ID_EMPRESA, $id_orden, $phone, $result['msg_ws']);
    $result['result_msg'] = $testResp;

    $localConnection->disconnect();

    // Verificar datos\
    if (isset($reemplazo)) {
      $result['reemplazo'] = $reemplazo;
    } else {
      $result['reemplazo'] = [];
    }

    // Asegúrate de que Access-Control-Allow-Headers no esté duplicado y contenga todos los necesarios.
    // El segundo 'Access-Control-Allow-Headers' en tu código original hacía que el primero se ignorara.
    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')  // Corregido
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));

    return $response;
  });

  // CONSTRUIR MENSAJES DE WHATSAPP PARA EMPLEADOS (INTERNO)
  $app->post('/ws/build-message/interno', function (Request $request, Response $response, $args) {
    $result = [];  // Inicializar el array de resultado
    try {
      $dataMensaje = $request->getParsedBody();

      // Validar que los datos necesarios están presentes
      if (!isset($dataMensaje['id_destino']) || !isset($dataMensaje['message'])) {
        // Si ID_EMPRESA no viene en el request, debes obtenerlo de alguna otra forma (sesión, constante, etc.)
        // Para este ejemplo, asumiré que lo envías en el request o lo tienes definido como constante.
        // Si viene en el request, asegúrate que Nuxt lo envíe.
        // Por ahora, usaré una constante placeholder o lo tomaré del request.
        // Si lo vas a enviar desde Nuxt, añade 'id_empresa' al URLSearchParams

        $result['error'] = 'Faltan parámetros requeridos (id_destino, message).';
        $response->getBody()->write(json_encode($result));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      // DEFINE O RECUPERA ID_EMPRESA AQUÍ. Ejemplo:
      // $id_empresa_para_node = ID_EMPRESA_CONSTANTE; // Si es una constante global
      // O si lo envías desde Nuxt:
      $id_empresa_para_node = ID_EMPRESA;  // Usa un valor por defecto o maneja error si no está

      $id_destino_empleado = $dataMensaje['id_destino'];
      $message_to_send = $dataMensaje['message'];
      // $id_orden = $dataMensaje['id_orden'] ?? null; // Opcional, si lo necesitas para algo más
      // $id_remitente = $dataMensaje['id_remitente'] ?? null; // Opcional
      // $id_departamento = $dataMensaje['id_departamento'] ?? null; // Opcional

      $localConnection = new LocalDB();  // Asegúrate de que esta clase y su conexión/desconexión sean manejadas correctamente.

      // BUSCAR TELEFONO DEL EMPLEADO AL QUE SE LE ENVIARÁ EL MENSAJE
      // Es importante sanitizar $id_destino_empleado antes de usarlo en una consulta SQL
      $sql = "SELECT nombre, telefono FROM api_empresas.empresas_usuarios WHERE id_usuario = '" . $dataMensaje['id_destino'] . "'";
      $data_emp = $localConnection->goQuery($sql);

      if (empty($data_emp) || !isset($data_emp[0]['telefono'])) {
        $result['error'] = 'No se encontró el teléfono para el empleado destino.';
        $result['id_destino_buscado'] = $id_destino_empleado;
        $response->getBody()->write(json_encode($result));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(404);  // Not Found
      }

      // BUSCAR NOMBRE DEL DEPARTAMENTO
      $sql = "SELECT departamento FROM departamentos WHERE _id = '" . $dataMensaje['id_departamento'] . "'";
      $data_dep = $localConnection->goQuery($sql);

      $localConnection->disconnect();  // Desconectar tan pronto como ya no se necesite

      $phone_destino = $data_emp[0]['telefono'];
      $name_destino = $data_emp[0]['nombre'];
      $departamento = $data_dep[0]['departamento'];

      // Preparar mensaje
      $formatted_msg = "*Mensaje Interno*\nDepartamento: $departamento\nDe: {$dataMensaje['nombre_empleado']}\nPara:$name_destino\n\n$message_to_send";

      // Instanciar el cliente de la API de WhatsApp
      $whatsAppApiClient = new WhatsAppAPIClient('https://ws.nineteengreen.com/');

      // Llamar a la nueva función para enviar el mensaje a través de la API de Node.js
      $nodeApiResponse = $whatsAppApiClient->sendDirectMessageToNode(
        ID_EMPRESA,  // El ID de la empresa que usa tu API de Node.js
        $phone_destino,
        $formatted_msg
      );

      $result['node_api_response'] = $nodeApiResponse;

      // Determinar el código de estado basado en la respuesta de la API de Node
      // Tu API Node devuelve 'success: true/false'. Usemos eso.
      if (isset($nodeApiResponse['success']) && $nodeApiResponse['success'] === true) {
        $httpStatus = 200;
      } elseif (isset($nodeApiResponse['error'])) {  // Si hubo un error en la clase WhatsAppAPIClient o la API Node devolvió error
        $httpStatus = 500;  // Internal Server Error o el código que corresponda
        if (isset($nodeApiResponse['http_code_received']) && $nodeApiResponse['http_code_received']) {
          // Si la clase capturó un código de error HTTP específico de Node, úsalo.
          $httpStatus = $nodeApiResponse['http_code_received'];
        } elseif (isset($nodeApiResponse['details']) && strpos($nodeApiResponse['details'], 'Error HTTP 400') !== false) {
          $httpStatus = 400;  // Bad request a la API de Node
        } elseif (isset($nodeApiResponse['details']) && strpos($nodeApiResponse['details'], 'Error HTTP 503') !== false) {
          $httpStatus = 503;  // Service unavailable (cliente Node no listo)
        }
      } else {
        $httpStatus = 500;  // Respuesta inesperada
      }
    } catch (\Exception $e) {
      // Capturar cualquier excepción inesperada durante el proceso
      $result['error'] = 'Excepción general en el endpoint /ws/build-message/interno.';
      $result['exception_message'] = $e->getMessage();
      $httpStatus = 500;
    }

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($httpStatus);  // Usar el status determinado

    $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  // JSON_PRETTY_PRINT para depuración

    return $response;
  });

  // GUARDAR MENSAJES DE INICIO Y FIN DE ORDEN
  $app->post('/update-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $localConnection = new LocalDB();

    // VERIFICAR EL TIPO DE MENSAJE welcome ó bye
    if ($dataMensaje['tipo'] === 'welcome') {
      $sql = "UPDATE config SET msg_welcome = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    } else if ($dataMensaje['tipo'] === 'bye') {
      $sql = "UPDATE config SET msg_bye = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    } else if ($dataMensaje['tipo'] === 'saldo') {
      $sql = "UPDATE config SET msg_saldo = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    }

    $result = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));

    return $response;
  });

  // OBTENER DATOS DE LA CONFIGURACIÓN DEL SISTEMA
  $app->get('/config', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT * FROM config';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data[0], JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER FECHA Y HORA DE LAS TABLAS MODIFICACAS

  /*
   * $app->get('/check-tables-updates', function (Request $request, Response $response) {
   *     $localConnection = new LocalDB();
   *
   *     $sql = "SELECT TABLE_NAME, UPDATE_TIME
   *     FROM INFORMATION_SCHEMA.TABLES
   *     WHERE TABLE_SCHEMA = '" . EMPRESA_DB . "'
   *     AND TABLE_TYPE = 'BASE TABLE'
   *     AND UPDATE_TIME IS NOT NULL
   *     ORDER BY UPDATE_TIME DESC;";
   *
   *     $data = $localConnection->goQuery($sql);
   *
   *     $localConnection->disconnect();
   *
   *     $response->getBody()->write(json_encode($sql, JSON_NUMERIC_CHECK));
   *     return $response
   *         ->withHeader('Content-Type', 'application/json')
   *         ->withStatus(200);
   * });
   */
  /* $app->post('/send-message', function (Request $request, Response $response, $args) {
      $dataMensaje = $request->getParsedBody();

      // Enviar WhatsApp Aqui
      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'general', $dataMensaje['mensaje']);

      $response = $response
          ->withHeader('Access-Control-Allow-Origin', '*')
          ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);

      $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));

      return $response;
  }); */

  // Endpoint para actualizar la configuración de mensajes (mensaje y enviar_mensaje) de un departamento
  $app->post('/departamentos/editar/settings', function (Request $request, Response $response, $args) {  // Añadimos 'use ($localConnection)' si es una variable externa
    // Obtener los datos del cuerpo de la solicitud POST
    $data = $request->getParsedBody();

    // Crear instancia de la conexión a la base de datos
    $localConnection = new LocalDB();

    // Validar que los datos necesarios estén presentes
    if (!isset($data['id_departamento']) || !isset($data['enviar_mensaje']) || !isset($data['mensaje'])) {
      $response = $response->withStatus(400);  // Bad Request
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Faltan parámetros necesarios (id_departamento, enviar_mensaje, mensaje).',
      ]));
      return $response;
    }

    $id_departamento = $data['id_departamento'];
    // Asegurarse de que enviar_mensaje sea un valor booleano o numérico seguro (0 o 1)
    $enviar_mensaje = filter_var($data['enviar_mensaje'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
    $mensaje = $data['mensaje'];

    // Validar que id_departamento sea un número entero
    if (!filter_var($id_departamento, FILTER_VALIDATE_INT)) {
      $response = $response->withStatus(400);  // Bad Request
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'ID de departamento inválido.',
      ]));
      return $response;
    }

    // *** La conexión a la base de datos ($localConnection) se asume disponible aquí ***
    // *** Eliminada la instanciación: $localConnection = new LocalDB(); ***

    // Preparar la consulta SQL UPDATE
    // Usamos sentencias preparadas para prevenir inyecciones SQL
    $sql = 'UPDATE departamentos SET enviar_mensaje = ?, mensaje = ? WHERE _id = ?';

    try {
      // Ejecutar la consulta preparada usando la conexión disponible
      // Asume que goQuery o un método similar en LocalDB soporta sentencias preparadas
      // Si LocalDB no soporta sentencias preparadas, deberás sanitizar $mensaje y $enviar_mensaje manualmente
      // o adaptar LocalDB. Sanitizar $mensaje es CRUCIAL si no usas preparadas.
      // Ejemplo de sanitización básica (NO RECOMENDADO sobre preparadas):
      // $mensaje_sanitized = $localConnection->escape_string($mensaje);
      // $sql = "UPDATE departamentos SET enviar_mensaje = $enviar_mensaje, mensaje = '$mensaje_sanitized' WHERE _id = $id_departamento";

      // Suponiendo que LocalDB tiene un método para ejecutar consultas preparadas:
      $params = [$enviar_mensaje, $mensaje, $id_departamento];
      $result = $localConnection->goQuery($sql, $params);  // Ajusta el método si es necesario

      // Verificar si la actualización fue exitosa
      // goQuery podría devolver true/false, el número de filas afectadas, etc.
      // Aquí asumimos que si no lanza una excepción, fue exitoso, o que $result indica éxito.
      // Deberías verificar la implementación de goQuery para confirmarlo.
      // Por ejemplo, si goQuery devuelve el número de filas afectadas:
      // if ($result > 0) { ... } else { // No se encontró el departamento o no hubo cambios ... }

      // Para simplificar, asumimos éxito si la consulta se ejecutó sin errores.
      // Una verificación más robusta podría ser necesaria.

      $response = $response->withStatus(200);  // OK
      $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Configuración del departamento actualizada correctamente.',
        'result' => $result,
      ]));
    } catch (\Exception $e) {
      // Capturar excepciones (ej: error de base de datos)
      error_log('Error al actualizar departamento: ' . $e->getMessage());  // Log del error en el servidor
      $response = $response->withStatus(500);  // Internal Server Error
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Ocurrió un error al actualizar la configuración del departamento.',
        'error' => $e->getMessage()  // Opcional: incluir mensaje de error detallado (útil en desarrollo)
      ]));
    } finally {
      // *** Eliminada la desconexión: $localConnection->disconnect(); ***
      // La gestión de la conexión (cierre) se asume manejada externamente.
    }

    // Añadir encabezados CORS (aunque el frontend use wildcard, es buena práctica)
    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')  // O el origen(es) específico(s) en producción
      ->withHeader('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-ID-Empresa')  // Asegúrate de incluir todos los headers que tu frontend envía
      ->withHeader('Content-Type', 'application/json');

    return $response;
  });

  // ENVIAR MENSAJES A CLIENTES PASOS DE PRODUCCIÓN
  $app->post('/send-message-produccion', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $id_dep = $dataMensaje['id_departamento_empelado'];

    $localConnection = new LocalDB();

    // Buscar estado de enviar_mensaje
    $sql = "SELECT enviar_mensaje FROM departamentos WHERE _id = $id_dep";
    $response_departamentos = $localConnection->goQuery($sql);
    $enviar_mensaje = intval($response_departamentos[0]['enviar_mensaje']);

    // Buscamos el nombre del cliente
    $sql = 'SELECT
                    a.id_wp,
                    b.first_name
                FROM
                    ordenes a
                LEFT JOIN customers b ON b._id = a.id_wp
                WHERE
                    a._id = ' . $dataMensaje['id_orden'];
    $response_client = $localConnection->goQuery($sql);
    $cliente = $response_client[0]['first_name'];

    if ($dataMensaje['tipo'] == 'terminar') {
      $msg['mensaje'] = 'Hola ' . $cliente . ', *su orden número ' . $dataMensaje['id_orden'] . ' está lista, puede pasar a retir su pedido.*';
    } else if ($dataMensaje['tipo'] == 'paso') {
      $msg['mensaje'] = 'Hola ' . $cliente . ', en este momento su orden número ' . $dataMensaje['id_orden'] . ' se encuentra en el departamento de ' . $dataMensaje['departamento'] . ', ';

      // Enviamos Whastapp
      if ($enviar_mensaje) {
        switch ($dataMensaje['departamento']) {
          case 'Impresión':
            $msg['mensaje'] .= 'Tu diseño personalizado está siendo impreso con los más altos estándares de calidad. Este proceso garantiza colores vibrantes y una gran durabilidad. ¡En breve podrás lucir tu prenda única';
            break;
          case 'Estampado':
            $msg['mensaje'] .= 'En este proceso, se transfiere un diseño a la tela mediante diferentes técnicas como la sublimación, dtf o la impresión digital. Esto permite crear patrones, logos o imágenes personalizadas sobre el tejido';
            break;
          case 'Corte':
            $msg['mensaje'] .= 'Una vez impresa la tela, se corta siguiendo patrones precisos para obtener las piezas que conformarán la prenda. Este proceso se realiza con corte laser de última tecnología que garantizan la precisión de cada corte';
            break;
          case 'Costura':
            $msg['mensaje'] .= ' Las piezas cortadas se unen mediante costura para dar forma a la prenda. Este proceso se realiza con máquinas industriales, dependiendo del tipo de prenda y el nivel de detalle';
            break;
          case 'Limpieza':
            $msg['mensaje'] .= 'Cada prenda se revisa minuciosamente para detectar posibles defectos como costuras mal hechas, hilos sueltos o manchas. Este paso es fundamental para garantizar la calidad del producto final';
            break;
          case 'Revisión':
            $msg['mensaje'] .= 'Se realizan pruebas de resistencia, color y acabado para asegurar que la prenda cumpla con los estándares de calidad establecidos';
            break;
          default:
            $msg['mensaje'] = 'Unknown';
            break;
        }
      } else {
        $msg['mensaje'] = '';
      }
    } elseif ($dataMensaje['tipo'] == 'cobrar') {
      $msg['mensaje'] = 'Hola ' . $cliente . ' le recordamos que tiene una deuda pendiente de *' . $dataMensaje['monto'] . ' USD* de su Orden número *' . $dataMensaje['id_orden'] . '*';
    }

    // Enviar WhatsApp Aqui

    if ($msg['mensaje'] != '') {
      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'paso-produccion', $msg);
    }

    /*
     * $sql = "SELECT _id, username, departamento, nombre, email FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND password = '" . $datosAcceso['password'] . "' AND activo = 1 AND acceso = 1";
     * $object['sql'] = $sql;
     * $resp = $localConnection->goQuery($sql);
     *
     * if (empty($resp)) {
     *     $object['access'] = false;
     *     $object['user_data'] = null;
     * } else {
     *     $object['access'] = true;
     *     $object['user_data'] = $resp;
     * }
     */
    $localConnection->disconnect();

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));

    return $response;
  });

  /** * Login */
  $app->post('/login', function (Request $request, Response $response, $args) {
    $datosAcceso = $request->getParsedBody();
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // VERIFICAR ACCCESO DEL EMPLEADO
    $sql = "SELECT email FROM api_empresas.empresas_usuarios WHERE email = '" . $datosAcceso['email'] . "'";
    $verificar_email = $localConnection->goQuery($sql);

    if (empty($verificar_email)) {
      $object['msg'] = 'El email ' . $datosAcceso['email'] . ' no está registrado en el sistema.';
      $data[0] = false;
      $object['data']['access'] = false;
      $object['data']['id_empleado'] = null;
      $object['data']['departamento'] = null;
      $object['data']['nombre'] = null;
      $object['data']['username'] = null;
      $object['data']['email'] = null;
      $object['data']['comision'] = 0;
      $object['data']['acceso'] = 0;
      $object['data']['orden_proceso'] = 0;
    } else {
      $sql = "SELECT id_usuario, email, `password`, nombre, departamento, id_empresa, activo, acceso, comision FROM empresas_usuarios WHERE email = '" . $datosAcceso['email'] . "' AND `password` = '" . $datosAcceso['password'] . "';";
      $credenciales = $localConnection->goQuery($sql);

      if (empty($credenciales)) {
        $object['msg'] = 'Los datos de acceso proporcionados no son correctos';
        $data[0] = false;
        $object['data']['access'] = false;
        $object['data']['id_empleado'] = null;
        $object['data']['departamento'] = null;
        $object['data']['nombre'] = null;
        $object['data']['username'] = null;
        $object['data']['email'] = null;
        $object['data']['comision'] = 0;
        $object['data']['acceso'] = 0;
        $object['data']['orden_proceso'] = 0;
      } else {
        // OBTENER DATOS DE LA EMPRESA
        $sql = 'SELECT id_empresa, nombre, direccion, telefono, email, pais, numero_registro_legal, horario_laboral, tipos_de_monedas, activo, db_host, db_user, db_password, `db_name` FROM empresas WHERE id_empresa = ' . $credenciales[0]['id_empresa'];
        $data_empresa = $localConnection->goQuery($sql);

        // ESTABLECEMOS LOS DATOS DE CONEXIÓN A LA BASE DE DATOS
        define('EMPRESA_ID', $credenciales[0]['id_empresa']);
        // define('EMPRESA_DB', $credenciales[0]['db_name']);
        $object['empresa_id'] = EMPRESA_ID;

        // OBTENER MODULOS DEL SISTEMA
        $sql = 'SELECT _id, modulo, folder, descripcion from api_empresas.modulos ORDER BY modulo ASC';
        $object['modulos'] = $localConnection->goQuery($sql);

        // CREAMOS UNA NUEVA CONEXION CON LAS CREDENCIALES DE LA EMPRESA SELECCIONADA A TRAVES DEL USUARIO
        /*  $object['LOCAL_DNS'] = $LOCAL_DNS = 'mysql:host=' . $data_empresa[0]['db_host'] . ';dbname=' . $data_empresa[0]['db_name'];
         $object['LOCAL_USER'] = $LOCAL_USER = $data_empresa[0]['db_user'];
         $object['LOCAL_PASS'] = $LOCAL_PASS = $data_empresa[0]['db_password'];
         $object['local_connection'] = $localConnection = new LocalDB('', $LOCAL_DNS, $LOCAL_USER, $LOCAL_PASS); */

        // SINCRONIZAMOS LOS EMPLEADOS
        try {
          $localConnection->syncEmpleados(EMPRESA_ID);
          $data = ['status' => 'success', 'message' => 'Empleados sincronizados correctamente'];
        } catch (Exception $e) {
          $data = ['status' => 'error', 'message' => 'Error al sincronizar empleados: ' . $e->getMessage()];
        }

        // OBTENER DEPARTEMNTOS
        $sql = 'SELECT
                    msg_welcome,
                    msg_bye,
                    msg_saldo,
                    sys_mostrar_detalle_terminar_indicidual,
                    sys_mostrar_rollo_en_empleado_corte,
                    sys_mostrar_rollo_en_empleado_estampado,
                    sys_mostrar_insumo_en_empleado_costura,
                    sys_mostrar_insumo_en_empleado_limpieza,
                    sys_mostrar_insumo_en_empleado_revision
                FROM
                    config';
        $object['empresa']['config_empresa'] = $localConnection->goQuery($sql)[0];

        // OBTENER DATOS DE CONFIGURACION DE LA EMPRESA
        $sql = 'SELECT * from departamentos ORDER BY orden_proceso ASC';
        $object['departamentos'] = $localConnection->goQuery($sql);

        // OBTENER DATOS DEL EMPLEADO
        $sql = 'SELECT
                    a.id_usuario AS _id,
                    a.email AS username,
                    a.password,
                    a.nombre,
                    a.email,
                    a.departamento,
                    c.orden_proceso,
                    a.comision,
                    a.comision_tipo,
                    a.acceso,
                    -- FNULL(GROUP_CONCAT(b.id_departamento), null) AS departamentos
                    IFNULL(
                        CONCAT(
                            "[",
                            GROUP_CONCAT(
                                CONCAT(
                                    "{\"id\":", b.id_departamento, 
                                    ",\"modulo\":\"", d.folder, 
                                    "\",\"id_modulo\":\"", c.id_modulo, 
                                    "\",\"orden_proceso\":\"", c.orden_proceso, 
                                    "\",\"nombre\":\"", c.departamento, 
                                    "\"}"
                                ) SEPARATOR ","
                            ),
                            "]"
                        ),
                        "[]"
                    ) AS departamentos
                FROM
                    api_empresas.empresas_usuarios a
                LEFT JOIN api_empresas.empresas_usuarios_departamentos b
                ON
                    b.id_empleado = a.id_usuario
                LEFT JOIN departamentos c
                ON
                    c._id = b.id_departamento
                LEFT JOIN api_empresas.modulos d ON d._id = c.id_modulo
                WHERE
                    a.id_usuario = ' . $credenciales[0]['id_usuario'] . ' AND a.activo = 1  AND a.id_empresa = ' . $data_empresa[0]['id_empresa'] . ' GROUP BY 
                    a.id_usuario, a.email, a.password, a.nombre, a.departamento, 
                    a.comision, a.comision_tipo, a.acceso;';
        $object['sql_empleado'] = $sql;
        $items = $localConnection->goQuery($sql);

        // Decodificar el campo `departamentos`
        foreach ($items as &$item) {
          if (!empty($item['departamentos'])) {
            $item['departamentos'] = json_decode($item['departamentos'], true);
          }
        }
        $object['empleado'] = $items;

        $object['statys_emp_sync'] = $data;

        $localConnection->disconnect();

        // CARGAMOS DATOS DE INICIO DE SESIÓN
        $object['msg'] = 'Bienvenido ' . $credenciales[0]['nombre'] . '.';

        $object['empresa']['id'] = $data_empresa[0]['id_empresa'];
        $object['empresa']['nombre'] = $data_empresa[0]['nombre'];
        $object['empresa']['direccion'] = $data_empresa[0]['direccion'];
        $object['empresa']['telefono'] = $data_empresa[0]['telefono'];
        $object['empresa']['email'] = $data_empresa[0]['email'];
        $object['empresa']['horario_laboral'] = json_decode($data_empresa[0]['horario_laboral']);
        $object['empresa']['tipos_de_monedas'] = json_decode($data_empresa[0]['tipos_de_monedas']);
        $object['empresa']['pais'] = $data_empresa[0]['pais'];
        $object['empresa']['numero_registro_legal'] = $data_empresa[0]['numero_registro_legal'];
        $object['empresa']['activo'] = $data_empresa[0]['activo'];

        $object['data']['access'] = true;
        $object['data']['id_empleado'] = $credenciales[0]['id_usuario'];
        $object['data']['departamento'] = $credenciales[0]['departamento'];
        $object['data']['nombre'] = $credenciales[0]['nombre'];
        $object['data']['username'] = $credenciales[0]['email'];
        $object['data']['email'] = $credenciales[0]['email'];
        $object['data']['comision'] = $credenciales[0]['comision'];
        $object['data']['acceso'] = intval($credenciales[0]['acceso']);
      }
    }

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response;

    /* $response = $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response; */
  });

  /** * Login mensajes */
  $app->post('/verify-credentials', function (Request $request, Response $response, $args) {
    $datosAcceso = $request->getParsedBody();
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    $sql = "SELECT
            b.id_empresa empresa_id,
            b.activo empresa_activa,
            b.nombre empresa_nombre,    
            b.telefono empresa_telefono,
            b.email email_empresa,
            a.activo usuario_activo,
            a.acceso usuario_nivel_acceso,
            a.id_usuario usuario_id,
            a.email usuario_email,
            a.telefono usuario_email,
            a.departamento usuario_departamento,
            a.nombre usuario_nombre
        FROM
            empresas_usuarios a
        JOIN empresas b ON a.id_empresa = b.id_empresa
        WHERE
            a.email = '" . $datosAcceso['username'] . "' AND a.password = '" . $datosAcceso['password'] . "';";
    // $object['sql'] = $sql;
    $resp = $localConnection->goQuery($sql);

    if (empty($resp)) {
      $object['access'] = false;
      $object['user_data'] = null;
      $object['msg'] = 'Las credenciales proporcionadas no son válidas';
    } else {
      $ban = true;

      // VERIFICAR QUE LA EMPRESA ESTÉ ACTIVA
      if (!$resp[0]['empresa_activa']) {
        $ban = false;
        $object['access'] = false;
        $object['msg'] = 'La empresa ' . $resp[0]['empresa_nombre'] . ' no se encuentra activa';
        $object['user_data'] = $resp[0];
      }

      // VERIFICAR QUE EL USUARIO ESTÉ ACTIVO
      if (!$resp[0]['usuario_activo']) {
        $ban = false;
        $object['access'] = false;
        $object['msg'] = 'El usuario ' . $resp[0]['usuario_nombre'] . ' no se encuentra activo';
        $object['user_data'] = $resp[0];
      }

      // VERIFICAR NIVEL DE ACCESO DEL USUARIO
      if (!$resp[0]['usuario_nivel_acceso']) {
        $ban = false;
        $object['access'] = false;
        $object['msg'] = 'El usuario ' . $resp[0]['usuario_nombre'] . ' no tiene permisos suficientes';
        $object['user_data'] = $resp[0];
      }

      if ($ban) {
        $object['access'] = true;
        $object['msg'] = 'Bienvenido ' . $resp[0]['usuario_nombre'];
        $object['user_data'] = $resp[0];
      }
    }

    $localConnection->disconnect();

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
      ->withHeader('Content-Type', 'application/json')
      ->withHeader('Authorization', 'Bearer ' . generateRandomToken())  // Añade el token en la cabecera
      ->withStatus(200);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response;
  });

  /** FIN LOGIN */

  /** * GENERAL */
  $app->get('/next-id-order', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT MAX(_id) + 1 id FROM ordenes';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    if (!$data[0]['id']) {
      $data[0]['id'] = '1';
    }

    // Convertir el ID a string antes de pasarlo a str_pad() para compatibilidad con PHP 8+
    // y eliminar la llamada redundante a str_pad.
    $nextId['id'] = str_pad((string) $data[0]['id'], 3, '0', STR_PAD_LEFT);

    $response->getBody()->write(json_encode($nextId, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN GENRAL */

  /** * TABLAS */
  // REPORTE DE PRODUCCIÓN SEMANAL
  $app->get('/ordenes-reporte-semanal-produccion/{fecha}', function (Request $request, Response $response, array $args) {
    $fechaSegundos = strtotime($args['fecha']);
    $week = date('W', $fechaSegundos);
    $object['week'] = $week;
    $localConnection = new LocalDB();

    // ORDENES DE PRODUCIDAS EN LA SEMANA
    $sql = "SELECT
        a._id id_orden,
        a.cliente_nombre cliente,    
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') AS fecha_inicio,
        DATE_FORMAT(a.fecha_entrega, '%d/%m/%Y') AS fecha_entrega,
        a.status estatus
        FROM
        ordenes a
        WHERE
        WEEK(a.moment) = " . $week;
    $object['items'] = $localConnection->goQuery($sql);

    // PROPDUCTOS ASOICIADOS A LAS ORDENES DE LA SEMANA
    $sql = 'SELECT
        a._id id_ordenes_productos,
        a.id_orden,
        a.id_woo,
        a.name,
        a.cantidad,
        a.talla,
        a.corte,
        a.tela
        FROM
        ordenes_productos a
        WHERE
        a.id_woo != 11 AND 
        a.id_woo != 12 AND 
        a.id_woo != 13 AND 
        a.id_woo != 14 AND 
        a.id_woo != 15 AND 
        a.id_woo != 16 AND 
        a.id_woo != 112 AND 
        a.id_woo != 113 AND 
        a.id_woo != 168 AND 
        a.id_woo != 169 AND 
        WEEK(a.moment) = ' . $week . ' 
        ORDER BY a.name ASC, a.corte ASC, a.talla ASC, a.tela ASC, a.id_orden ASC;';
    $object['items_productos'] = $localConnection->goQuery($sql);

    // INSERTAR PRODUCTOS EN items

    foreach ($object['items'] as $key => $orden) {
      foreach ($object['items_productos'] as $producto) {
        if (!isset($object['items'][$key]['productos'])) {
          $object['items'][$key]['productos'] = [];
        }

        if ($producto['id_orden'] === $orden['id_orden']) {
          $object['items'][$key]['productos'][] = $producto;
        }
      }
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE SEMANAL DE ORDENES
  $app->get('/ordenes-reporte-semanal/{fecha}', function (Request $request, Response $response, array $args) {
    $fechaSegundos = strtotime($args['fecha']);
    $week = date('W', $fechaSegundos);
    $object['week'] = $week;
    $localConnection = new LocalDB();

    $sql = 'SELECT
        a._id orden,
        a.cliente_nombre cliente,
        a.pago_total total,
        a.pago_abono abono,
        a.pago_descuento descuento,
        b.nombre empleado,
        (a.pago_total - a.pago_descuento) - a.pago_abono AS total_pendiente
        FROM
        ordenes a
        JOIN empleados b ON a.responsable = b._id
        WHERE
        WEEK(a.moment) = ' . $week;
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT
        SUM(pago_abono) total_semana
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_week'] = $localConnection->goQuery($sql);

    if (is_null($object['total_week'][0]['total_semana'])) {
      $object['total_week'][0]['total_semana'] = '0';
    }

    $sql = 'SELECT
        (SUM(pago_total) - SUM(pago_descuento)) - SUM(pago_abono) total_credito
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_credito'] = $localConnection->goQuery($sql);

    if (is_null($object['total_credito'][0]['total_credito'])) {
      $object['total_credito'][0]['total_credito'] = '0';
    }

    $sql = 'SELECT
        SUM(pago_descuento) total_descuentos
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
    $object['total_descuentos'] = $localConnection->goQuery($sql);

    if (is_null($object['total_descuentos'][0]['total_descuentos'])) {
      $object['total_descuentos'][0]['total_descuentos'] = '0';
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER PRESUPUESTOS GUARDADOS
  $app->get('/presupuestos/guardados', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a._id, a.form, a.tipo, b._id AS id_empleadodo, b.nombre AS empleado 
          FROM ordenes_tmp a 
          JOIN empleados b ON a.id_empleado = b._id';

    $object['items'] = $localConnection->goQuery($sql);

    foreach ($object['items'] as $key => $item) {
      $item[$key]['form'] = json_decode($item['form']);
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER ORDENES GUARDADAS
  $app->get('/ordenes/guardadas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a._id, a.form, a.tipo, b.id_usuario AS id_empleado, b.nombre AS empleado 
          FROM ordenes_tmp a 
          JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario';

    $object['items'] = $localConnection->goQuery($sql);

    foreach ($object['items'] as $key => $item) {
      // $item[$key]['form'] = json_decode($item['form']);
      if (is_array($item) && isset($item['form'])) {
        $item['form'] = json_decode($item['form']);
      }
    }

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ordenes/observaciones/{id_orden}/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // $sql = "SELECT a.observaciones observaciones_orden, b.borrador observaciones_empleado FROM ordenes a JOIN ordenes_borrador_empleado b ON b.id_orden = a._id WHERE a._id = {$args['id_orden']} AND b.id_empleado = {$args['id_empleado']} AND b.id_departamento = {$args['id_departamento']}";

    $sql = "SELECT
        obs.observaciones AS observaciones_ordenes,
            (SELECT borrador FROM ordenes_borrador_empleado WHERE id_orden = {$args['id_orden']} AND id_empleado = {$args['id_empleado']} AND id_departamento = {$args['id_departamento']}) observaciones_empleado
        FROM
            ordenes a
        LEFT JOIN ordenes_observaciones obs ON a._id = obs.id_orden
        WHERE
            a._id = {$args['id_orden']}";

    $object = $localConnection->goQuery($sql);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/ordenes/borrador/reporte-semanal/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                b._id id_orden,
                b.cliente_nombre,    
                a._id id_ordenes_borador_empleado,
                a.borrador,
                a.moment
            FROM
                ordenes_borrador_empleado a
            LEFT JOIN ordenes b ON b._id = a.id_orden
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']}
              AND YEARWEEK(a.moment, 1) = YEARWEEK(CURDATE(), 1)
        ";

    $object = $localConnection->goQuery($sql);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR BORRADOR DEL EMPLEADO
  $app->post('/ordenes/borrador', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Verificar si ya existe un registro para la orden
    $sql = 'SELECT _id FROM ordenes_borrador_empleado WHERE id_orden = ' . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'] . ' AND id_departamento = ' . $data['id_departamento'];
    $resp = $localConnection->goQuery($sql);

    if (empty($resp)) {
      $sql = 'INSERT INTO ordenes_borrador_empleado (`id_orden`, `id_empleado`, `id_departamento`, `borrador`) VALUES (' . $data['id_orden'] . ', ' . $data['id_empleado'] . ", '{$data['id_departamento']}', '" . addslashes($data['borrador']) . "');";
    } else {
      $sql = 'UPDATE ordenes_borrador_empleado SET id_departamento = ' . $data['id_departamento'] . ', id_orden = ' . $data['id_orden'] . ', id_empleado = ' . $data['id_empleado'] . ", borrador = '" . addslashes($data['borrador']) . "' WHERE id_orden = " . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'];
    }
    $object['sql'] = $sql;
    $resp = $localConnection->goQuery($sql);
    $object['resp'] = $localConnection->disconnect($sql);

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ELIMINAR ORDENES GUARDADAS
  $app->post('/ordenes/guardadas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $data = $request->getParsedBody();
    $sql = 'DELETE FROM ordenes_tmp WHERE _id =  ' . $data['id'];
    $object['response_delete'] = json_encode($localConnection->goQuery($sql));
    $object['sql_delete'] = $sql;

    $sql = 'SELECT a._id, a.form, b._id id_empleado, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id';
    $object['items'] = $localConnection->goQuery($sql);

    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR ORDEN PARA REPTMARLA LUEGO
  $app->post('/orden/guardar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $localConnection = new LocalDB();

    $sql = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES ('" . $data['form'] . "', " . $data['id_empleado'] . ", '" . $data['tipo'] . "')";
    $object['sql_insert'] = $sql;
    $localConnection->goQuery($sql);

    $sql = 'SELECT a._id, a.form, b._id id_empleado, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id';
    $object['items'] = $localConnection->goQuery($sql);
    $object['sql'] = $sql;

    $string = implode('', explode('\\', json_encode($object['items'])));
    $items = stripslashes(trim($string));
    $items = stripslashes(trim($items));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($items));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES ACTIVAS
  $app->get('/table/ordenes-activas/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    $sql = 'SELECT departamento FROM  empresas_usuarios  WHERE id_usuario = ' . $args['id_empleado'];
    $departamento = $localConnection->goQuery($sql)[0]['departamento'];

    $localConnection = new LocalDB();

    if ($departamento === 'Administración') {
      $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.responsable id_vendedor,
                emp.nombre vendedor,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.pago_total total,
                ord.fecha_inicio,
                ord.fecha_entrega,
                -- ord.observaciones obs,
                -- (SELECT DISTINCT a.id_orden, SUM(a.abono) abono, SUM(a.descuento) descuento, b.pago_total total, SUM(a.abono) + SUM(a.descuento) total_abono_descuento, a.detalle, a.moment  FROM abonos a JOIN ordenes b ON a.id_orden = b._id WHERE a.id_orden = ord._id) data_bono,
                ord.status estatus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                (
                ord.status
                    = 'activa' OR
                ord.status
                    = 'En espera' OR
                ord.status
                    = 'terminada' OR
                ord.status
                    = 'pausada'
            )
            ORDER BY
                ord._id
            DESC;";
    } else {
      $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.responsable id_vendedor,
                emp.nombre vendedor,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.fecha_inicio,
                ord.fecha_entrega,
                -- ord.observaciones obs,
                ord.status estaus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                ord.responsable = '" . $args['id_empleado'] . "' AND(
                ord.status
                    = 'activa' OR
                ord.status
                    = 'En espera' OR
                ord.status
                    = 'terminada' OR
                ord.status
                    = 'pausada'
            ) AND ord.pago_comision = 'pendiente'
            ORDER BY
                ord._id
            DESC;";
    }

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'estatus';
    $object['fields'][1]['label'] = 'Estatus';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'fecha_inicio';
    $object['fields'][2]['label'] = 'Inicio';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'fecha_entrega';
    $object['fields'][3]['label'] = 'Entrega';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][4]['key'] = 'cliente_nombre';
    $object['fields'][4]['label'] = 'Cliente';
    $object['fields'][4]['sortable'] = true;

    $object['fields'][5]['key'] = 'id_father';
    $object['fields'][5]['label'] = 'Vinculadas';
    $object['fields'][5]['sortable'] = false;

    $object['fields'][6]['key'] = 'acc';
    $object['fields'][6]['label'] = 'Acciones';
    $object['fields'][6]['sortable'] = false;

    $object['items'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODAS LAS ORDENES
  $app->get('/table/ordenes-todas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT
    ord.responsable,
    ord._id orden,
    ord._id id_father,
    ord._id acc,
    ord.responsable id_vendedor,
    emp.nombre vendedor,
    ord.cliente_nombre,
    cus.phone,
    cus.email,
    ord.fecha_inicio,
    ord.fecha_entrega,
    ord.pago_total AS total, -- <-- AÑADE ESTA LÍNEA
    -- ord.observaciones obs,
    ord.status estatus
FROM
    ordenes ord
JOIN customers cus ON ord.id_wp = cus._id
LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
/*WHERE
    (
    ord.status
        = 'activa' OR
    ord.status
        = 'cancelada' OR
    ord.status
        = 'En espera' OR
    ord.status
        = 'terminada' OR
    ord.status
        = 'pausada' OR ord.status = 'entregada'
    )*/
ORDER BY
    ord._id
DESC;";

    $object['items'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'estatus';
    $object['fields'][1]['label'] = 'Estatus';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'fecha_inicio';
    $object['fields'][2]['label'] = 'Inicio';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'fecha_entrega';
    $object['fields'][3]['label'] = 'Entrega';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][4]['key'] = 'cliente_nombre';
    $object['fields'][4]['label'] = 'Cliente';
    $object['fields'][4]['sortable'] = true;

    $object['fields'][5]['key'] = 'id_father';
    $object['fields'][5]['label'] = 'Vinculadas';
    $object['fields'][5]['sortable'] = false;

    $object['fields'][6]['key'] = 'acc';
    $object['fields'][6]['label'] = 'Acciones';
    $object['fields'][6]['sortable'] = false;

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODAS LAS ORDENES OLD
  /* $app->get('/table/ordenes-todas_OLD', function (Request $request, Response $response, array $args) {
      $sql = "SELECT
      a._id AS orden,
      DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
      a.cliente_nombre AS cliente,
      a.pago_total AS monto,
      a.pago_abono abono,
      (SELECT cus.phone FROM customers cus WHERE cus._id = a.id_wp) phone,
      (
       SELECT
       d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
       FROM
       abonos c
       JOIN ordenes d ON
       c.id_orden = d._id
       WHERE
       c.id_orden = a._id
       ) AS monto_pendiente,
      a.status estatus
      FROM
      ordenes AS a
      WHERE a.status != 'cancelada'
      ORDER BY a._id DESC;";

      $localConnection = new LocalDB();
      $items = $localConnection->goQuery($sql);

      $object['items'] = $items;

      // Cabeceras de la tabla
      $object['fields'][0]['key'] = 'orden';
      $object['fields'][0]['label'] = 'Orden';
      $object['fields'][0]['sortable'] = true;

      $object['fields'][1]['key'] = 'fecha';
      $object['fields'][1]['label'] = 'Fecha';
      $object['fields'][1]['sortable'] = true;

      $object['fields'][2]['key'] = 'cliente';
      $object['fields'][2]['label'] = 'Cliente';
      $object['fields'][2]['sortable'] = true;

      $object['fields'][3]['key'] = 'monto';
      $object['fields'][3]['label'] = 'Monto';
      $object['fields'][3]['sortable'] = true;

      $object['fields'][3]['key'] = 'acc';
      $object['fields'][3]['label'] = 'Acciones';
      $object['fields'][3]['sortable'] = false;

      // $object['items'] = $localConnection->goQuery($sql);
      $localConnection->disconnect();

      $response->getBody()->write(json_encode($object));
      return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
  }); */

  // ORDENES CON DEUDAA
  $app->get('/table/ordenes-con-deuda', function (Request $request, Response $response, array $args) {
    $sql = "SELECT
        _id AS orden,
        DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
        cliente_nombre AS cliente,
        pago_total AS monto
        FROM
        ordenes       
        ORDER BY _id DESC;";

    $sql = "SELECT
        a._id orden,
        a.responsable,
        a._id orden,
        a._id id_father,
        a._id acc,
        a.cliente_nombre cliente,
        a.fecha_inicio,
        a.fecha_entrega,
        'TRAER DESDE EL `ENDPOINT` DEDICADO' obs,
        a.status estatus,
        a.pago_total AS monto,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id
         ) AS total_deuda 
        FROM
        ordenes AS a
        WHERE
        a.status!= 'cancelada' AND 
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id) > 0
        ORDER BY
        _id
        DESC
        ";
    $localConnection = new LocalDB();
    $items = $localConnection->goQuery($sql);

    $object['items'] = $items;

    // Cabeceras de la tabla
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'fecha';
    $object['fields'][1]['label'] = 'Fecha';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'cliente';
    $object['fields'][2]['label'] = 'Cliente';
    $object['fields'][2]['sortable'] = true;

    $object['fields'][3]['key'] = 'monto';
    $object['fields'][3]['label'] = 'Monto';
    $object['fields'][3]['sortable'] = true;

    $object['fields'][3]['key'] = 'acc';
    $object['fields'][3]['label'] = 'Acciones';
    $object['fields'][3]['sortable'] = false;

    // $object['items'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TABLAS */

  /** OBTENR INSUMOS ASIGNADOS A PRODUCTOS */
  $app->delete('/insumos-productos-asignados/{id_insumo}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'DELETE FROM product_insumos_asignados WHERE _id =  ' . $args['id_insumo'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/insumos-productos/{id_orden}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = "SELECT DISTINCT
                    a._id id_product_insumos_asignados,
                    b._id id_product,
                    e.id_orden,
                    d._id id_departamento,
                    b.product producto,
                    (SELECT nombre FROM sizes WHERE _id = e.talla) talla,
                    e.cantidad unidades_solicitadas,
                    c.nombre insumo,
                    d.departamento,
                    a.cantidad cantidad_estimada_de_consumo,
                    a.unidad,
                    (
                    SELECT
                        tiempo
                    FROM
                        products_tiempos_de_produccion
                    WHERE
                        id_product = b._id AND id_departamento = a.id_departamento
                ) tiempo_estimado_de_fabricación
                FROM
                    product_insumos_asignados a
                LEFT JOIN products b ON
                    b._id = a.id_product
                JOIN catalogo_insumos_productos c ON
                    c._id = a.id_catalogo_insumos_productos
                JOIN departamentos d ON
                    d._id = a.id_departamento
                JOIN ordenes_productos e ON a.id_product = e.id_woo
                WHERE e.id_orden = {$args['id_orden']} AND d._id = {$args['id_departamento']}        ";
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/insumos-productos-asignados', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
                a._id id_product_insumos_asignados,
                b._id id_product,
                d._id id_departamento,
                b.product producto,
                c.nombre insumo,
                d.departamento,
                a.cantidad,
                a.unidad,
                s.nombre AS talla,
                a.tiempo tiempo_cero,
                (SELECT tiempo FROM products_tiempos_de_produccion WHERE id_product = b._id AND id_departamento = a.id_departamento) tiempo
            FROM
                product_insumos_asignados a
            LEFT JOIN products b ON b._id = a.id_product
            JOIN catalogo_insumos_productos c ON c._id = a.id_catalogo_insumos_productos
            JOIN departamentos d ON d._id = a.id_departamento
            LEFT JOIN sizes s ON s._id = a.id_talla
        ';
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** OBTENER TIEMPOS DE PRODUCCIÓN */
  $app->get('/tiempos-de-produccion', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
                tp._id id_tiempos_de_produccion,
                pr._id id_product,
                tp.tiempo,
                de._id id_departamento,
                pr.product,
                de.departamento
            FROM
                products_tiempos_de_produccion tp
            JOIN products pr ON pr._id = tp.id_product
            JOIN departamentos de ON de._id = tp.id_departamento 
            ORDER BY pr._id ASC
        ';
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** OBTENER TOTAL DE TIEMPOS DE PRODUCCIÓN */
  $app->get('/tiempos-de-produccion-total', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
            a._id id_product,
            a.product,
            SUM(b.tiempo) total_tiempo,
            CONCAT(
                "[",
                GROUP_CONCAT(
                    JSON_OBJECT(
                        "id_departamento",
                        c._id,
                        "departamento",
                        c.departamento,
                        "tiempo",
                        b.tiempo
                    )
                ),
                "]"
            ) AS departamentos
        FROM
            products a
        JOIN products_tiempos_de_produccion b ON
            b.id_product = a._id
        LEFT JOIN departamentos c ON
            c._id = b.id_departamento
        GROUP BY
            a._id
        ORDER BY a.product ASC
        ';
    $products = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // PARSEAR RESULTADOS
    $key = 0;
    foreach ($products as $product) {
      $data[$key]['id_product'] = intval($product['id_product']);
      $data[$key]['product'] = $product['product'];
      $data[$key]['total_tiempo'] = $product['total_tiempo'];
      $data[$key]['tiempo_por_departamentos'] = json_decode($product['departamentos']);

      $key++;
    }

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** NUEVO TIEMPO DE PRODUCCION */
  $app->post('/tiempos-de-produccion', function (Request $request, Response $response) {
    $miTiempo = $request->getParsedBody();
    $localConnection = new LocalDB();

    $id_product = intval($miTiempo['id_product']);  // Convertir a entero para seguridad
    $departamento = intval($miTiempo['id_departamento']);  // Convertir a entero para seguridad
    $tiempo = intval($miTiempo['tiempo']);  // Convertir a entero para seguridad

    /** VERIFICAR EXISTENCIA DEL REGISTRO */
    $sql = "SELECT _id FROM products_tiempos_de_produccion WHERE id_product = $id_product AND id_departamento = $departamento";
    $object['response_verify'] = $localConnection->goQuery($sql);

    if (empty($object['response_verify'])) {
      // No existe, insertar nuevo registro
      $sql = "INSERT INTO products_tiempos_de_produccion (id_product, id_departamento, tiempo) VALUES ($id_product, $departamento, $tiempo);";
    } else {
      // Existe, actualizar registro
      $sql = "UPDATE products_tiempos_de_produccion SET tiempo = $tiempo WHERE id_product = $id_product AND id_departamento = $departamento;";
    }

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** CATALOG DE INSUMOS PARA CLASIFICACIÓN DE PROIDUCTOS */
  $app->post('/insumos-productos', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Usar sentencias preparadas para prevenir inyección SQL
    $sql = 'INSERT INTO product_insumos_asignados 
                    (id_product, id_departamento, id_catalogo_insumos_productos, cantidad, unidad, id_talla) 
                VALUES (?, ?, ?, ?, ?, ?)';

    // Preparar los parámetros para la consulta
    // Aseguramos que id_talla (recibido como id_size) sea null si no se envía o está vacío
    $id_talla = isset($miInsumo['id_size']) && !empty($miInsumo['id_size']) && $miInsumo['id_size'] !== 'null' ? $miInsumo['id_size'] : null;

    $params = [
      $miInsumo['id_product'],
      $miInsumo['departamento'],
      $miInsumo['insumo'],
      $miInsumo['cantidad'],
      $miInsumo['unidad'],
      $id_talla
    ];

    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql, $params);
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** CATALOG DE INSUMOS PARA CLASIFICACIÓN DE PROIDUCTOS */
  $app->post('/catalogo-insumos-productos', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'INSERT INTO catalogo_insumos_productos (id_departamento, nombre, id_product) VALUES (' . $miInsumo['id_departamento'] . ", '" . $miInsumo['insumo'] . "', " . $miInsumo['id_product'] . ');';
    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** OBTENR CATALOGO DE INSUMOS PARA PRODUCTOS */
  $app->get('/catalogo-insumos-productos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_insumos_productos ORDER BY nombre';
    $object = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** * TELAS */
  $app->get('/telas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['data'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas', function (Request $request, Response $response) {
    $miTela = $request->getParsedBody();
    $object['miTela'] = $miTela;

    $miTela = $request->getParsedBody();

    // Crear estructura de valores para insertar nuevo cliente
    $values = '(';
    $values .= "'" . $miTela['tela'] . "')";

    $sql = 'INSERT INTO catalogo_telas (`tela`) VALUES ' . $values . ';';
    $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';

    $localConnection = new LocalDB();
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas/{_id}/{tela}', function (Request $request, Response $response, array $args) {
    // $miTela = $request->getParsedBody();
    $localConnection = new LocalDB();
    $values = "tela='" . $args['tela'] . "'";
    $sql = 'UPDATE catalogo_telas SET ' . $values . ' WHERE _id = ' . $args['_id'] . ';';
    $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/telas/eliminar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $miEmpleado = $request->getParsedBody();
    $object['miEmpleado'] = $miEmpleado;
    $sql = 'DELETE FROM catalogo_telas WHERE _id =  ' . $miEmpleado['id'];
    $object['sql'] = $sql;

    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TELAS */

  /** RETIROS */

  // REPORTE GENERAL DE PAGOS Y ABONOS
  $app->get('/reporte-de-pagos[/{inicio}/{fin}/{id_vendedor}]', function (Request $request, Response $response, array $args) {
    /** FONDO */
    $localConnection = new LocalDB();
    $inicio = isset($args['inicio']) ? $args['inicio'] : null;
    $fin = isset($args['fin']) ? $args['fin'] : null;
    $vendedor = isset($args['id_vendedor']) ? $args['id_vendedor'] : null;

    /* if (isset($args["id_vendedor"])) {
            $vendedor = $args["id_vendedor"];
        } else {
            $object["vendedor"] = $args["id_vendedor"];
            $vendedor = null;
        } */

    if (!is_null($vendedor)) {
      if ($vendedor == '0') {
        $searchVendedor = '';
      } else {
        $searchVendedor = ' AND ord.responsable = ' . $vendedor . ' ';
      }
    } else {
      $searchVendedor = '';
    }

    $object['searchVendedor'] = $searchVendedor;

    if (is_null($inicio) || is_null($fin)) {
      $sql = "SELECT
    met._id,
    ord._id orden,
    ord.responsable id_empleado,
    emp.nombre empleado,
    met.metodo_pago,
    met.monto,
    met.detalle,
    met.tasa,
    met.moneda,
    DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
    DATE_FORMAT(met.moment, '%h:%i %p') AS hora
FROM
    metodos_de_pago met
JOIN ordenes ord ON met.id_orden = ord._id
JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
WHERE
    YEAR(met.moment) = YEAR(CURDATE())
    -- AND MONTH(met.moment) = MONTH(CURDATE()) -- Comentar esta línea
    {$searchVendedor}
ORDER BY
    met.id_orden DESC, met.moment ASC;";
      /* $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                YEAR(met.moment) = YEAR(CURDATE())
                AND MONTH(met.moment) = MONTH(CURDATE())
                " . $searchVendedor . '
            ORDER BY
                met.id_orden DESC, met.moment ASC;
            '; */
    } else {
      $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = ord.responsable
            WHERE
                DATE(met.moment) BETWEEN '" . $inicio . "' AND '" . $fin . "' 
                " . $searchVendedor . '
                ORDER BY
                met.id_orden DESC, met.moment ASC;';
    }

    $object['sql_pagos'] = $sql;

    $object['pagos'] = $localConnection->goQuery($sql);

    // Buscar todos los empleados que sean vendedres o administradores
    $sqlv = "SELECT id_usuario _id, nombre FROM api_empresas.empresas_usuarios WHERE departamento = 'Comercialización' OR departamento = 'Administración' AND activo = 1;";
    $object['vendedores'] = $localConnection->goQuery($sqlv);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Datos para efectuar el cietre de caja
  $app->get('/cierre-de-caja/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_vendedor = $args['id_vendedor'];
    $object = ['data' => []];

    // 1. Get the latest cash fund for the vendor using a prepared statement
    $sql_fondo = 'SELECT dolares, pesos, bolivares FROM caja_fondos WHERE id_empleado = ? ORDER BY _id DESC LIMIT 1';
    $fondo = $localConnection->goQuery($sql_fondo, [$id_vendedor]);

    $fondo_dolares = !empty($fondo) ? (float) $fondo[0]['dolares'] : 0;
    $fondo_pesos = !empty($fondo) ? (float) $fondo[0]['pesos'] : 0;
    $fondo_bolivares = !empty($fondo) ? (float) $fondo[0]['bolivares'] : 0;

    $object['data']['fondo'] = [['dolares' => $fondo_dolares, 'pesos' => $fondo_pesos, 'bolivares' => $fondo_bolivares]];

    // 2. Get the sum of open cash transactions for each currency using a prepared statement
    $sql_caja = 'SELECT 
                        moneda, 
                        (SUM(monto)) as total_monto                        
                     FROM caja 
                     WHERE id_empleado = ? AND id_caja_cierres IS NULL 
                     GROUP BY moneda';
    $caja_entries = $localConnection->goQuery($sql_caja, [$id_vendedor]);

    $caja_dolares = 0;
    $caja_pesos = 0;
    $caja_bolivares = 0;

    foreach ($caja_entries as $entry) {
      switch ($entry['moneda']) {
        case 'Dólares':
          $caja_dolares = (float) $entry['total_monto'];
          break;
        case 'Pesos':
          $caja_pesos = (float) $entry['total_monto'];
          break;
        case 'Bolívares':
          $caja_bolivares = (float) $entry['total_monto'];
          break;
      }
    }

    // 3. Calculate total cash on hand and structure the response
    $total_dolares = $fondo_dolares + $caja_dolares;
    $total_pesos = $fondo_pesos + $caja_pesos;
    $total_bolivares = $fondo_bolivares + $caja_bolivares;

    $object['data']['dolares'] = [['moneda' => 'Dólares', 'monto' => $total_dolares]];
    $object['data']['pesos'] = [['moneda' => 'Pesos', 'monto' => $total_pesos]];
    $object['data']['bolivares'] = [['moneda' => 'Bolívares', 'monto' => $total_bolivares]];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar Cierre de caja
  $app->post('/cierre-de-caja-vendedor', function (Request $request, Response $response, $args) {
    $datosCierre = $request->getParsedBody();
    $localConnection = new LocalDB();

    // $object['response_DB'] = $localConnection;

    // Guardamos el cierre
    $sql = ' INSERT INTO caja_cierres (dolares, pesos, bolivares, id_empleado) VALUES (' . $datosCierre['cierreDolaresEfectivo'] . ', ' . $datosCierre['cierrePesosEfectivo'] . ', ' . $datosCierre['cierreBolivaresEfectivo'] . ', ' . $datosCierre['id_empleado'] . ');';
    $responseCierreCaja = $localConnection->goQuery($sql);

    // Identificamos el ID del INSERT
    $insertID = $responseCierreCaja['insert_id'];

    // Insertamos caja_fondos
    $sql = "INSERT INTO caja_fondos (id_empleado, dolares, id_caja_cierres, pesos, bolivares) VALUES ({$datosCierre['id_empleado']}, {$datosCierre['fondoDolares']}, $insertID, {$datosCierre['fondoPesos']}, {$datosCierre['fondoBolivares']})";
    $object['response_insert_caja_fondos'] = $localConnection->goQuery($sql);

    // Actualizamos caja para los registros cerrados
    $sql = "UPDATE caja SET id_caja_cierres = $insertID WHERE id_empleado = {$datosCierre['id_empleado']}";
    $object['response_update_caja'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode(str_replace("\r", '', $object)));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Reporte de caja
  $app->get('/reporte-de-caja/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    if ($args['inicio'] === $args['fin']) {
      $where = "a.moment LIKE '" . $args['inicio'] . "%';";
    } else {
      $where = "a.moment BETWEEN '" . $args['inicio'] . "' AND '" . $args['fin'] . "'";
    }

    $where .= ' AND o.responsable = ' . $args['id_vendedor'] . ';';

    /** EFECTIVO */
    $sql = "SELECT
                    monto,
                    'Dólares' moneda,
                    tasa,
                    (monto / tasa) dolares,
                    (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = {$args['id_vendedor']}) vendedor
                    FROM caja WHERE id_empleado = {$args['id_vendedor']} AND moneda LIKE 'Dólares';
                ";
    $object['sql_dolares'] = $sql;
    $object['data']['efectivo']['dolares'] = $localConnection->goQuery($sql);

    // Pesos
    $sql = "SELECT
                    monto,
                    'Pesos' moneda,
                    tasa,
                    (monto / tasa) dolares,
                    (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = {$args['id_vendedor']}) vendedor
                FROM caja WHERE id_empleado = {$args['id_vendedor']} AND moneda LIKE 'Pesos'
        ";
    $object['sql_pesos'] = $sql;

    $object['data']['efectivo']['pesos'] = $localConnection->goQuery($sql);

    // Bolívares
    $sql = "SELECT
                    monto,
                    'Bolívares' moneda,
                    tasa,
                    (monto / tasa) dolares,
                    (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = {$args['id_vendedor']}) vendedor
                FROM caja WHERE id_empleado = {$args['id_vendedor']} AND moneda LIKE 'Bolívares'
        ";
    $object['sql_bolivares'] = $sql;

    $object['data']['efectivo']['bolivares'] = $localConnection->goQuery($sql);

    /** MONEDA DIGITAL */

    // ZELLE

    $sql = "SELECT 
             SUM(a.monto) monto, 
             a.tasa, 
             SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
             a.moneda, 
             'Zelle' metodo_pago, 
             a.tipo_de_pago 
             FROM metodos_de_pago AS a 
             JOIN ordenes AS o 
             ON a.id_orden = o._id
             WHERE a.metodo_pago = 'Zelle' AND " . $where;
    $object['data']['digital']['zelle'] = $localConnection->goQuery($sql);

    // PAGOMOVIL (bOLIVARES)
    $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Pagomovil' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Pagomovil' AND " . $where;

    $object['data']['digital']['pagomovil'] = $localConnection->goQuery($sql);

    // PUNTO (BOLIVARES)
    $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Punto' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS  o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Punto' AND " . $where;

    $object['data']['digital']['punto'] = $localConnection->goQuery($sql);

    // TRANSFERENCIA (BOLIVARES)
    $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Transferencia' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Transferencia' AND " . $where;

    $object['data']['digital']['transferencia'] = $localConnection->goQuery($sql);

    /** RETIROS */
    $sql = 'SELECT 
            a.monto, 
            a.moneda, 
            a.tasa, 
            SUM(ROUND(a.monto / tasa, 2)) AS dolares, 
            a.detalle_retiro, 
            b.nombre 
            FROM retiros AS a 
            JOIN ordenes AS o ON o._id = a.id_empleado
            -- JOIN empleados b ON b._id = o.responsable 
            JOIN api_empresas.empresas_usuarios b ON b.id_usuario = o.responsable 
            WHERE ' . $where;

    // $object["data"]["retiros"] = $sql;
    $object['data']['retiros'] = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar nuevo retiro
  $app->post('/retiro', function (Request $request, Response $response) {
    $arr = $request->getParsedBody();
    $localConnection = new LocalDB();
    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql = '';

    if (intval($arr['montoDolaresEfectivo']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '" . $arr['detalle'] . "', '1');";
    }

    if (intval($arr['montoDolaresZelle']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '" . $arr['detalle'] . "', '1');";
    }

    if (intval($arr['montoDolaresPanama']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '" . $arr['detalle'] . "', '1');";
    }

    if (intval($arr['montoPesosEfectivo']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
    }

    if (intval($arr['montoPesosTransferencia']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
    }

    if (intval($arr['montoBolivaresEfectivo']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
    }

    if (intval($arr['montoBolivaresPunto']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
    }

    if (intval($arr['montoBolivaresPagomovil']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
    }

    if (intval($arr['montoBolivaresTransferencia']) > 0) {
      $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
    }

    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar nuevo abono
  $app->post('/otro-abono', function (Request $request, Response $response) {
    $arr = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Validar si se ha enviado un id_orden y asignarlo. De lo contrario, usar 0.
    $id_orden_abono = isset($arr['id_orden']) ? $arr['id_orden'] : 0;

    $sql = '';

    // Si es un abono a una orden específica, registrarlo también en la tabla `abonos`
    if ($id_orden_abono > 0) {
      $now = date('Y-m-d H:i:s');
      $abono = isset($arr['abono']) ? floatval($arr['abono']) : 0;
      $descuento = 0;  // Este formulario no maneja descuentos, se asume 0.
      $id_empleado = isset($arr['id_empleado']) ? intval($arr['id_empleado']) : 0;

      $sql .= "INSERT INTO abonos (moment, id_orden, abono, descuento, id_empleado) VALUES ('{$now}', {$id_orden_abono}, {$abono}, {$descuento}, {$id_empleado});";
    }

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    if (intval($arr['montoDolaresEfectivo']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Dólares', 'Efectivo', '{$arr['montoDolaresEfectivo']}', '{$arr['detalle']}', '1');";
      $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('{$arr['montoDolaresEfectivo']}', 'Dólares', 1, 'abono', '{$arr['id_empleado']}');";
    }

    if (intval($arr['montoDolaresZelle']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Dólares', 'Zelle', '{$arr['montoDolaresZelle']}', '{$arr['detalle']}', '1');";
    }

    if (intval($arr['montoDolaresPanama']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Dólares', 'Panamá', '{$arr['montoDolaresPanama']}', '{$arr['detalle']}', '1');";
    }

    if (intval($arr['montoPesosEfectivo']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Pesos', 'Efectivo', '{$arr['montoPesosEfectivo']}', '{$arr['detalle']}', '{$arr['tasa_peso']}');";
      $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES  ('{$arr['montoPesosEfectivo']}', 'Pesos', '{$arr['tasa_peso']}', 'abono', '{$arr['id_empleado']}');";
    }

    if (intval($arr['montoPesosTransferencia']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Pesos', 'Transferencia', '{$arr['montoPesosTransferencia']}', '{$arr['detalle']}', '{$arr['tasa_peso']}');";
    }

    if (intval($arr['montoBolivaresEfectivo']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Bolívares', 'Efectivo', '{$arr['montoBolivaresEfectivo']}', '{$arr['detalle']}', '{$arr['tasa_dolar']}');";
      $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('{$arr['montoBolivaresEfectivo']}', 'Bolívares', '{$arr['tasa_dolar']}', 'abono', '{$arr['id_empleado']}');";
    }

    if (intval($arr['montoBolivaresPunto']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Bolívares', 'Punto', '{$arr['montoBolivaresPunto']}', '{$arr['detalle']}', '{$arr['tasa_dolar']}');";
    }

    if (intval($arr['montoBolivaresPagomovil']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Bolívares', 'Pagomovil', '{$arr['montoBolivaresPagomovil']}', '{$arr['detalle']}', '{$arr['tasa_dolar']}');";
    }

    if (intval($arr['montoBolivaresTransferencia']) > 0) {
      $sql .= "INSERT INTO metodos_de_pago (id_orden, tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ({$id_orden_abono}, '{$arr['tipoAbono']}', 'Bolívares', 'Transferencia', '{$arr['montoBolivaresTransferencia']}', '{$arr['detalle']}', '{$arr['tasa_dolar']}');";
    }

    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obteber Retiros
  $app->get('/retiros/{fecha}/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // Obtener retiros
    $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario WHERE a.moment LIKE '" . $args['fecha'] . "%'";

    $pbject['sql']['data_retiros'] = $sql;
    $object['data']['retiros'] = $localConnection->goQuery($sql);

    /** FONDO */
    $sql = 'SELECT dolares, pesos, bolivares FROM caja_fondos ORDER BY _id DESC LIMIT 1';
    $fondo = $localConnection->goQuery($sql);
    // $pbject['sql']['data_fondo'] = $sql;
    $object['data']['fondo'] = $fondo;

    if (empty($fondo)) {
      $fondo[0]['dolares'] = 0;
      $fondo[0]['pesos'] = 0;
      $fondo[0]['bolivares'] = 0;
    }

    // DÓLARES EN CAJA,

    $sql = "SELECT 
            (SUM(c.monto) - IFNULL(SUM(a.monto), 0)) AS monto, 
            c.moneda, 
            c.tasa, 
            FORMAT(((SUM(c.monto) - IFNULL(SUM(a.monto), 0)) / c.tasa), 'C2') AS dolares 
        FROM 
            caja c 
        LEFT JOIN 
            retiros a ON c.id_empleado = a.id_empleado AND a.moneda = 'Dólares' 
        WHERE 
            c.moneda = 'Dólares' 
            AND c.id_empleado = " . $args['id_empleado'] . ';';
    // $object['data']['sql_dolares'] = $sql;
    $object['data']['caja'] = $localConnection->goQuery($sql);

    // PESOS EN CAJA,
    $sql = 'SELECT (SUM(c.monto) + ' . $fondo[0]['pesos'] . ' - IFNULL(SUM(a.monto), 0)) AS monto, c.moneda, c.tasa, FORMAT(((SUM(c.monto) + ' . $fondo[0]['pesos'] . " - IFNULL(SUM(a.monto), 0)) / c.tasa), 'C2') AS dolares FROM caja c LEFT JOIN retiros a ON c.id_empleado = a.id_empleado AND a.moneda = 'Pesos' WHERE c.moneda = 'Pesos' AND c.id_empleado = " . $args['id_empleado'] . ';';

    // $object['data']['sql_2'] = $sql;
    array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

    // BOLIVARES     EN CAJA,
    $sql = 'SELECT 
            (SUM(c.monto) + ' . $fondo[0]['bolivares'] . ' - IFNULL(SUM(a.monto), 0)) AS monto, 
            c.moneda, 
            c.tasa, 
            FORMAT(((SUM(c.monto) + ' . $fondo[0]['bolivares'] . " - IFNULL(SUM(a.monto), 0)) / c.tasa), 'C2') AS dolares 
        FROM 
            caja c 
        LEFT JOIN 
            retiros a ON c.id_empleado = a.id_empleado AND a.moneda = 'Bolívares' 
        WHERE 
            c.moneda = 'Bolívares' 
            AND c.id_empleado = " . $args['id_empleado'];

    // $object['data']['sql_3'] = $sql;
    array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Pagos Ordenes
  $app->get('/pagos-ordenes/{fecha}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT _id, moment, monto, moneda, metodo_pago, id_orden, tasa FROM metodos_de_pago WHERE moment LIKE '" . $args['fecha'] . "%'";
    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN RETIROS */

  /** * Diseños * */
  // OBTENER TODAS LAS REVISIONES
  $app->get('/diseno/revisiones', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();

    $sql = "SELECT
                b.id_orden,
                b._id id_revision,
                b.id_empleado id_disenador,
                b.id_product,
                (SELECT product FROM products WHERE _id = b.id_product) producto,
                d.nombre disenador,
                b.tipo tipo_diseno,
                b.detalles,
                b.estatus,
                b.revision,
                c.id_wp id_cliente,
                c.cliente_nombre cliente
            FROM
                revisiones b
            JOIN ordenes c ON
                c._id = b.id_orden
            LEFT JOIN api_empresas.empresas_usuarios d
            ON
                b.id_empleado = d.id_usuario
            WHERE
                b.estatus = 'Esperando Respuesta' AND b.id_product IS NOT NULL
            ORDER BY
                b.id_empleado ASC
        ";
    $object['revisiones'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['total_revisiones'] = count($object['revisiones']);

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REVISAR DISEÑOS APRBADOS Y RECHAZADOS
  $app->get('/diseno/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();

    $sql = 'SELECT a.id_orden, b._id id_revision, a._id id_diseno, b.detalles, b.estatus, b.revision, c.id_wp id_cliente, c.cliente_nombre cliente FROM disenos a JOIN revisiones b ON a._id = b.id_diseno JOIN ordenes c ON c._id = a.id_orden WHERE a.id_empleado =' . $args['id_empleado'] . " AND c.status != 'entregada' AND c.status != 'cancelada' AND c.status != 'terminado'";
    $object['revisiones'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['total_revisiones'] = count($object['revisiones']);

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REVISAR DISEÑO APROBADO
  $app->get('/diseno/aprobado/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();
    $sql = 'SELECT revision, estatus, id_diseno FROM revisiones WHERE id_orden = ' . $args['id_orden'] . " AND estatus = 'Aprobado'";
    $resp = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    if (empty($resp)) {
      $object['aprobado'] = false;
    } else {
      $object['aprobado'] = true;
      $object['data'] = $resp[0];
    }

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar link de google drive
  $app->post('/disenos/link', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE disenos SET linkdrive = '" . $data['url'] . "' WHERE id_orden = " . $data['id'];
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTAULOZAR TIPO DE DISEÑO
  $app->post('/diseno/update-tipo', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // 1. Validar y sanitizar datos
    if (empty($data['id_product']) || empty($data['id_diseno']) || empty($data['id_revision'])) {
      $response->getBody()->write(json_encode(['error' => 'Faltan datos requeridos (id_product, id_diseno, o id_revision).']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $id_product = intval($data['id_product']);
    $id_diseno = intval($data['id_diseno']);
    $id_revision = intval($data['id_revision']);
    $url_image = $data['url_imagen'];

    try {
      // 2. Obtener el nombre del producto de forma segura
      $sql_product = 'SELECT product FROM products WHERE _id = ?';
      $product_result = $localConnection->goQuery($sql_product, [$id_product]);

      if (empty($product_result)) {
        throw new Exception('Producto no encontrado con ID: ' . $id_product);
      }
      $product_name = $product_result[0]['product'];

      // 3. Actualizar ambas tablas
      $sql_update_disenos = 'UPDATE disenos SET tipo = ?, id_product = ? WHERE _id = ?';
      $localConnection->goQuery($sql_update_disenos, [$product_name, $id_product, $id_diseno]);

      $sql_update_revisiones = 'UPDATE revisiones SET tipo = ?, id_product = ?, url_image = ? WHERE _id = ?';
      $localConnection->goQuery($sql_update_revisiones, [$product_name, $id_product, $url_image, $id_revision]);

      $response->getBody()->write(json_encode(['success' => true, 'message' => 'Tipo de diseño actualizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al actualizar el tipo de diseño: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // Obtener datos para la aprobación del cliente
  $app->get('/disenos/aprobacion-de-cliente/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // $sql = "INSERT INTO aprobacion_clientes(id_orden, id_diseno) VALUES (" . $data["id_orden"] . ", " . $data["id_diseno"] . ");";
    $sql = 'SELECT
            a._id id_orden,    
            b._id id_diseno,
            c.revision revision,
            c.estatus estatus_aprobado, 
            a.cliente_nombre nombre_cliente
        FROM
            ordenes AS a
        LEFT JOIN disenos AS b ON a._id = b.id_orden
        LEFT JOIN revisiones AS c ON c.id_diseno = b._id 
        WHERE
            a._id = ' . $args['id_orden'];

    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object['data']));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar registro de aprobacion de clientes
  $app->post('/disenos/parobacion-de-cliente', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE revisiones SET estatus = 'Aprobado' WHERE id_orden = " . $data['id_orden'] . ';';
    $sql .= 'INSERT INTO aprobacion_clientes(id_orden, id_diseno) VALUES (' . $data['id_orden'] . ', ' . $data['id_diseno'] . ');';
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar ajustes y personalizaciones
  $app->post('/diseno/ajustes-y-personalizaciones', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $monto_ajustes = $data['ajustes'];
    $monto_personalizacion = $data['personalizaciones'];
    $comision_ajustes = 0.2 * intval($monto_ajustes);
    $comision_pesonalizacion = 0.3 * intval($monto_personalizacion);
    $sql = '';

    // Verificar si el registro de diseños y ajuste ya existe
    $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'ajuste' AND id_diseno = " . $data['id_diseno'] . ' ORDER BY tipo ASC';
    $dataRequest = $localConnection->goQuery($sql_tipo);
    if (count($dataRequest) > 0) {
      $ajuste = true;
    } else {
      $ajuste = false;
    }

    $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'personalización' AND id_diseno = " . $data['id_diseno'] . ' ORDER BY tipo ASC';
    $dataRequest = $localConnection->goQuery($sql_tipo);
    $object['personalizacion'] = count($dataRequest);
    if (count($dataRequest) > 0) {
      $personalizacion = true;
    } else {
      $personalizacion = false;
    }

    $sqlord = 'SELECT id_orden, id_empleado FROM disenos WHERE _id = ' . $data['id_diseno'];
    $resultDiseno = $localConnection->goQuery($sqlord);

    // Guardar cantidades de personalizaciones y ajustes
    $sqlpa = '';
    if ($ajuste) {
      $sqlpa .= 'UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ' . $monto_ajustes . ' WHERE id_diseno = ' . $data['id_diseno'] . " AND tipo = 'ajuste';";
    } else {
      $sqlpa .= 'INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (' . $data['id_diseno'] . ', ' . $resultDiseno[0]['id_orden'] . ", 'ajuste', " . $monto_ajustes . ');';
    }

    if ($personalizacion) {
      $sqlpa .= 'UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ' . $monto_personalizacion . ' WHERE id_diseno = ' . $data['id_diseno'] . " AND tipo = 'personalizacion';";
    } else {
      $sqlpa .= 'INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (' . $data['id_diseno'] . ', ' . $resultDiseno[0]['id_orden'] . ", 'personalizacion', " . $monto_personalizacion . ');';
    }
    $data = $localConnection->goQuery($sqlpa);

    // Preparar datos para los pagos

    // Buscar datos para el guardar los pagos
    if (empty($dataRequest)) {
      $sql .= 'INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (' . $monto_ajustes . ', ' . $resultDiseno[0]['id_orden'] . ", 'aprobado' , " . $comision_ajustes . ', ' . $resultDiseno[0]['id_empleado'] . ", 'ajuste');";
      $sql .= 'INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (' . $monto_personalizacion . ', ' . $resultDiseno[0]['id_orden'] . ", 'aprobado' , " . $comision_pesonalizacion . ', ' . $resultDiseno[0]['id_empleado'] . ", 'personalización');";
    } else {
      $values = "monto_pago ='" . $comision_ajustes . "', cantidad = " . $monto_ajustes;
      $sql .= 'UPDATE pagos SET ' . $values . ' WHERE id_empleado = ' . $resultDiseno[0]['id_empleado'] . ' AND id_orden = ' . $resultDiseno[0]['id_orden'] . " AND detalle = 'ajuste';";
      $values = "monto_pago ='" . $comision_pesonalizacion . "', cantidad = " . $monto_personalizacion;
      $sql .= 'UPDATE pagos SET ' . $values . ' WHERE id_empleado = ' . $resultDiseno[0]['id_empleado'] . ' AND id_orden = ' . $resultDiseno[0]['id_orden'] . " AND detalle = 'personalización';";
    }
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener ajustes y personalizaciones de un diseno
  $app->get('/disenos/ajustes-y-personalizaciones/{id_diseno}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = ' . $args['id_diseno'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener link de google drive
  $app->get('/disenos/link/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT linkdrive FROM disenos WHERE _id = ' . $args['id'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object[0]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener codigo del diseño
  $app->get('/disenos/codigo/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT codigo_diseno FROM disenos WHERE _id = ' . $args['id'];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar codigo de diseno
  $app->post('/disenos/codigo', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE disenos SET codigo_diseno = '" . $data['cod'] . "' WHERE id_orden = " . $data['id'];
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($sql));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/disenador-asignado', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // ELIMINAR DISEÑO Y REVISIONES
    $sql = 'DELETE FROM revisiones WHERE id_orden =  ' . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'] . ';';
    $sql .= 'DELETE FROM disenos WHERE _id =  ' . $data['id_diseno'] . ';';
    $object['response_delete_diseno_sql'] = $sql;
    $object['response_delete_diseno'] = $localConnection->goQuery($sql);

    // ELIMINAR PAGOS
    $sql = 'DELETE FROM pagos WHERE id_empleado =  ' . $data['id_empleado'] . ' AND id_orden = ' . $data['id_orden'] . ';';
    $object['response_delete_pagos_sql'] = $sql;
    $object['response_delete_pagos'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener diseños sin asignar
  $app->get('/disenos', function (Request $request, Response $response) {
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $localConnection_emp = new LocalDB();

    // $sql = 'SELECT * FROM  empresas_usuarios a JOIN empresas_usuarios_departamentos WHERE id_empresa = ' . ID_EMPRESA; // IMPORTANTE EL DEPARTAMENTO ID = 9 ESTA ASIGNADO A DISEñO Y EN LA CONFIGURACION DE EMPRESA DEBE SER UN DEPARTAMENTO FIJO

    // DETERMINAR EL ID DEL DEPARTAMENTO DISEÕ DE LA EMPRESA (ESTE PROCEDIMIENTO DEBE SER CAMBIADO POR ASIGANR UN ID FIJO PARA EL DEPARTAMENTO DISEñO IGUAL PARA TODAS LAS EMPRESAS)

    $sql = "SELECT _id id FROM departamentos WHERE departamento LIKE 'Diseño' LIMIT 1";
    $response_id_departamento = $localConnection_emp->goQuery($sql);

    if (empty($response_id_departamento)) {
      $depId = 0;
    } else {
      $depId = $response_id_departamento[0]['id'];
    }

    $sql = "SELECT
          a.id_empleado id_usuario,
          b.nombre
      FROM
          empresas_usuarios_departamentos a
      LEFT JOIN empresas_usuarios b ON b.id_usuario = a.id_empleado
      WHERE  id_departamento = $depId AND b.id_empresa = " . ID_EMPRESA;
    $object['empleados'] = $localConnection->goQuery($sql);

    $localConnection = new LocalDB();

    $sql = "SELECT
            a._id AS id_diseno,
            b._id AS id_orden,
            b._id AS imagen,
            b._id AS vinculadas,
            a.tipo,
            b._id AS id,
            e.id_usuario id_empleado,
            a.id_empleado AS empleado,
            e.nombre AS nombre_empleado,
            b.responsable,
            cus._id AS id_cliente,
            cus.first_name,
            cus.last_name,
            a.linkdrive
        FROM
            ordenes b
        LEFT JOIN disenos a ON
            b._id = a.id_orden
        LEFT JOIN customers cus ON
            b.id_wp = cus._id
        LEFT JOIN api_empresas.empresas_usuarios e ON
            e.id_usuario = a.id_empleado
        WHERE
            b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera'
        ORDER BY
            b._id DESC;
         ";

    $object['disenos']['items'] = $localConnection->goQuery($sql);

    // BUSCAR ASIGNACIONES DE DISEÑOS
    $sql = 'SELECT
            a.id_orden,
            a._id id_diseno,
            a.id_empleado,
            b.nombre,
            a.tipo
        FROM
            disenos a
        JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
        WHERE 
            a.terminado = 0
        ';
    $object['disenos']['asignados'] = $localConnection->goQuery($sql);

    // BUSCAR PRODUCTOS DE DISEñO
    $sql = "SELECT
            a._id id_producto,
            a.product,
            a.price,
            a.comision
        FROM
            products a
        WHERE
            a.category_ids LIKE '%17%'
        ORDER BY product ASC;
        ";
    $object['disenos']['productos'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo "Proyecto de Diseño" con su primera revisión
  $app->post('/disenos/nuevo-con-revision', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // 1. Validar que los datos necesarios llegaron
    if (empty($data['id_orden']) || empty($data['id_empleado'])) {
      $response->getBody()->write(json_encode(['error' => 'Faltan datos requeridos (orden o empleado).']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // 2. Sanitizar datos de entrada para prevenir inyección SQL
    $id_orden = intval($data['id_orden']);
    $id_empleado = intval($data['id_empleado']);
    // Usar valores por defecto si no se proporcionan
    $id_product = isset($data['id_product']) ? intval($data['id_product']) : 'NULL';
    $tipo_diseno = isset($data['tipo_diseno']) ? addslashes($data['tipo_diseno']) : 'Diseño por definir';

    // 3. Crear el nuevo "Proyecto de Diseño" en la tabla `disenos`
    $sqlDiseno = "INSERT INTO disenos (id_orden, id_empleado, id_product, tipo, origen) VALUES ({$id_orden}, {$id_empleado}, {$id_product}, '{$tipo_diseno}', 'agregado_posterior')";
    $resultDiseno = $localConnection->goQuery($sqlDiseno);

    // Verificar si hubo un error en la primera inserción
    if (isset($resultDiseno['status']) && $resultDiseno['status'] === 'error') {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear el proyecto de diseño.', 'details' => $resultDiseno['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 4. Obtener el ID del nuevo registro en `disenos`
    $id_diseno_nuevo = $resultDiseno['insert_id'];

    // Verificar que obtuvimos un ID válido
    if (empty($id_diseno_nuevo) || $id_diseno_nuevo == 0) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo obtener el ID del nuevo diseño.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 5. Crear la primera revisión para este nuevo proyecto en la tabla `revisiones`
    $sqlRevision = "INSERT INTO revisiones (id_orden, id_diseno, id_empleado, id_product, revision, tipo) VALUES ({$id_orden}, {$id_diseno_nuevo}, {$id_empleado}, {$id_product}, 1, '{$tipo_diseno}')";
    $resultRevision = $localConnection->goQuery($sqlRevision);

    // 6. Cerrar la conexión y enviar respuesta exitosa
    $localConnection->disconnect();

    $payload = json_encode([
      'success' => true,
      'message' => 'Nuevo proyecto de diseño y su primera revisión han sido creados.',
      'sql_rev' => $sqlRevision,
      'id_diseno_nuevo' => $id_diseno_nuevo
    ]);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);  // 201 Created
  });

  // Todos los diseños asignados
  $app->get('/disenos/asignados', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $object['disenos']['fields'][0]['key'] = 'id';
    $object['disenos']['fields'][0]['label'] = 'Orden';
    $object['disenos']['fields'][1]['key'] = 'tipo';
    $object['disenos']['fields'][1]['label'] = 'Tipo';
    $object['disenos']['fields'][2]['key'] = 'empleado';
    $object['disenos']['fields'][2]['label'] = 'Empleado';

    $sql = "SELECT a.tipo, a.id_orden, b.email username, b.nombre, b.id_usuario id_empleado FROM disenos a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario  WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";

    $object['disenos']['items'] = $localConnection->goQuery($sql);
    $object['empleados'] = $localConnection->goQuery('SELECT * FROM api_empresas.empresas_usuarios');

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // diseños asignados por empleado
  $app->get('/disenos/asignar/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT DISTINCT
            a._id id_diseno,
            a.tipo,
            a.id_orden,
            a.id_empleado,
            a.linkdrive,
            a.codigo_diseno,
            a.moment creacion_diseno,
            b.status orden_estatus,
            b.id_wp id_cliente,
            b.cliente_nombre
        FROM
            disenos a
        LEFT JOIN ordenes b ON
            b._id = a.id_orden
        WHERE
            a.terminado = 0 AND a.id_orden = ' . $args['id_orden'] . '
        ';

    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Todos los diseños terminados
  $app->get('/disenos/terminados', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'disenador';
    $object['fields'][2]['label'] = 'Diseñador';

    $object['fields'][3]['key'] = 'inicio';
    $object['fields'][3]['label'] = 'Inicio';

    $object['fields'][4]['key'] = 'entrega';
    $object['fields'][4]['label'] = 'Entregado';

    $object['fields'][5]['key'] = 'tipo';
    $object['fields'][5]['label'] = 'Tipo';

    $object['fields'][6]['key'] = 'codigo_diseno';
    $object['fields'][6]['label'] = 'Codigo';

    $object['fields'][7]['key'] = 'linkdrive';
    $object['fields'][7]['label'] = 'Drive';

    $object['fields'][8]['key'] = 'imagen';
    $object['fields'][8]['label'] = 'Imagen';

    // $sql = "SELECT a.id_orden orden, b.cliente_nombre cliente, c.nombre disenador, b.fecha_inicio inicio, b.fecha_entrega entrega, a.tipo, b._id imagen FROM disenos a JOIN ordenes b ON a.id_orden = b._id JOIN empleados c ON a.id_empleado = c._id WHERE a.terminado = 1;";

    $sql = "SELECT
            a.id_orden orden,
            d._id id_revision,
            b.cliente_nombre cliente,
            c.id_usuario id_empleado,
            c.nombre disenador,
            b.fecha_inicio inicio,
            b.fecha_entrega entrega,
            a.linkdrive,
            a.codigo_diseno,
            d.tipo, 
            b.status estatus_orden,
            b._id imagen
        FROM
            disenos a
        JOIN ordenes b ON
            a.id_orden = b._id
        LEFT JOIN revisiones d ON a._id = d.id_diseno
        -- JOIN empleados c ON
        JOIN api_empresas.empresas_usuarios c ON 
            a.id_empleado = c.id_usuario
        WHERE
            a.terminado = 1 AND (b.status != 'entregada' OR b.status != 'cancelada')";

    $object['sql'] = $sql;

    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Diseñosasignados a Diseñador

  $app->get('/disenos/asignados/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'inicio';
    $object['fields'][2]['label'] = 'Inicio';

    $object['fields'][3]['key'] = 'revision';
    $object['fields'][3]['label'] = 'Revisión';
    $object['fields'][3]['class'] = 'text-center';

    $object['fields'][4]['key'] = 'tallas_y_personalizacion';
    $object['fields'][4]['label'] = 'Tallas y Personalización';
    $object['fields'][4]['class'] = 'text-center';

    $object['fields'][5]['key'] = 'id_orden';
    $object['fields'][5]['label'] = 'Vinculadas';
    $object['fields'][5]['class'] = 'text-center';

    $object['fields'][6]['key'] = 'codigo_diseno';
    $object['fields'][6]['label'] = 'Código Diseño';
    $object['fields'][6]['class'] = 'text-center';

    $object['fields'][7]['key'] = 'linkdrive';
    $object['fields'][7]['label'] = 'Google Drive';
    $object['fields'][7]['class'] = 'text-center';

    $object['fields'][8]['key'] = 'revision';
    $object['fields'][8]['label'] = 'Revisiones';
    $object['fields'][8]['class'] = 'text-center';

    $sql = 'SELECT 
    a._id linkdrive, 
    a.codigo_diseno, 
    a.id_orden, 
    a._id id_diseno,
    a._id tallas_y_personalizacion,
    a.id_orden id, 
    a.id_orden imagen, 
    a.id_orden revision, 
    b.cliente_nombre cliente, 
    b.fecha_inicio inicio, 
    a.tipo,
    c.estatus 
    FROM disenos a 
    LEFT JOIN revisiones c 
    ON a._id = c.id_diseno 
    JOIN ordenes b 
    ON b._id = a.id_orden 
    LEFT JOIN disenos d ON d._id = c.id_diseno
    WHERE a.id_empleado =    ' . $args['id_empleado'] . ' 
    AND a.terminado = 0 
    ORDER BY a.id_orden ASC
    ';
    $object['sql_items'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ' . $args['id_empleado'];
    $object['sql_revisiones'] = $sql;
    $object['revisiones'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODO eliminar ninesys antiguo => Obtener diseños pendientes por diseñador
  $app->get('/disenos/pendientes/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND terminado = 0';

    $disenos = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($disenos));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener diseños terminados por diseñador
  $app->get('/disenos/terminados/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND terminado = 1';

    $disenos = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($disenos));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Asignar diseñador
  $app->put('/disenos/asign/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // Sanitizar datos de entrada
    $id_orden = intval($args['id_orden']);
    $id_empleado = intval($args['empleado']);

    // 1. Crear el nuevo "Proyecto de Diseño" en la tabla `disenos`
    // Se usa un tipo por defecto que indica que fue asignado pero no definido.
    $sqlDiseno = "INSERT INTO disenos (id_orden, id_empleado, tipo, origen) VALUES ({$id_orden}, {$id_empleado}, 'Diseño Asignado', 'asignado')";
    $resultDiseno = $localConnection->goQuery($sqlDiseno);

    // Verificar si hubo un error en la inserción del diseño
    if (isset($resultDiseno['status']) && $resultDiseno['status'] === 'error') {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear el proyecto de diseño.', 'details' => $resultDiseno['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 2. Obtener el ID del nuevo registro en `disenos`
    $id_diseno_nuevo = $resultDiseno['insert_id'];

    // Verificar que obtuvimos un ID válido
    if (empty($id_diseno_nuevo) || $id_diseno_nuevo == 0) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo obtener el ID del nuevo diseño.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 3. Crear la primera revisión para este nuevo proyecto en la tabla `revisiones`
    $sqlRevision = "INSERT INTO revisiones (id_orden, id_diseno, id_empleado, revision, tipo) VALUES ({$id_orden}, {$id_diseno_nuevo}, {$id_empleado}, 1, 'Diseño Asignado')";
    $resultRevision = $localConnection->goQuery($sqlRevision);

    // Verificar si hubo un error en la inserción de la revisión
    if (isset($resultRevision['status']) && $resultRevision['status'] === 'error') {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear la revisión inicial.', 'details' => $resultRevision['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 4. Cerrar la conexión y enviar respuesta exitosa
    $localConnection->disconnect();

    $payload = json_encode(['success' => true, 'message' => 'Diseño asignado y primera revisión creada correctamente.', 'id_diseno_nuevo' => $id_diseno_nuevo]);

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // Diseñador dar diseño por terminado
  $app->put('/disenos/close/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'UPDATE disenos SET terminado = 1 WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $args['empleado'];
    $asignacion = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($asignacion));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** Fin Diseños */

  /** PAGOS */
  // Terminar planilla de pago
  $app->post('/pagos/terminar-planilla', function (Request $request, Response $response, $args) {
    // $order = $request->getParsedBody();
    $localConnection = new LocalDB();
    $myDate = new CustomTime();
    $now = $myDate->today();

    $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE fecha_pago IS NULL";
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REALIZAR PAGO A EMPLEADOS
  $app->post('/pagos/pagar-a-empleados', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $myDate = new CustomTime();
    $now = $myDate->today();

    $listaDeIdPagos = explode(',', $data['id_pagos']);
    $params = '';

    // REGISTRAR PAGOS
    foreach ($listaDeIdPagos as $key => $value) {
      $params .= ' _id = ' . $value . ' OR ';
    }

    $params = substr($params, 0, -4);  // Eliminamos el ultimo OR

    $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE " . $params . ';';
    $data['resp_update'] = $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM pagos WHERE ' . $params;
    $registrosParaProcesar = $localConnection->goQuery($sql);

    // TODO PASAR PAGOS REGISTRADOS A HISTÓRICO
    /* $newSql = '';
    foreach ($registrosParaProcesar as $key => $pago) {
        $values = $pago['_id'] . ',';
        $values .= $pago['id_orden'] . ',';
        if (!is_null($pago['id_metodos_de_pago'])) {
            $values .= $pago['id_metodos_de_pago'] . ',';
        } else {
            $values .= 'null,';
        }
        if (!is_null($pago['id_lotes_detalles'])) {
            $values .= $pago['id_lotes_detalles'] . ',';
        } else {
            $values .= 'null,';
        }
        $values .= $pago['id_empleado'] . ',';

        if (!is_null($values .= $pago['cantidad'])) {
            $values .= $pago['cantidad'] . ',';
        } else {
            $values .= 'null,';
        }

        $values .= $pago['monto_pago'] . ',';
        $values .= "'" . $pago['detalle'] . "',";
        $values .= "'" . $pago['estatus'] . "',";
        $values .= "'" . $pago['fecha_pago'] . "',";
        $values .= "'" . $pago['moment'] . "'";

        $newSql .= 'INSERT INTO history.pagos(_id, id_orden, id_metodos_de_pago, id_lotes_detalles, id_empleado, cantidad, monto_pago, detalle, estatus, fecha_pago, moment) VALUES (' . $values . ');';
    }
    $data['newSql'] = $newSql;

    $data['resp_insert_history'] = $localConnection->goQuery($newSql);

    // ELIMINAR LOS ITEMS QUE SE GUARDARON EN EL HISTÓRICO
    $sqlDelete = 'DELETE FROM api.pagos WHERE _id IN (' . $data['id_pagos'] . ')';
    $data['sqlDelete'] = $sqlDelete;
    $data['resp_delete'] = $localConnection->goQuery($sqlDelete); */

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/pagos/pagar-a-empleados-OLD', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $myDate = new CustomTime();
    $now = $myDate->today();

    $listaDeIdPagos = explode(',', $data['id_pagos']);
    $params = '';

    foreach ($listaDeIdPagos as $key => $value) {
      $params .= ' _id = ' . $value . ' OR ';
    }
    $params = substr($params, 0, -4);  // Eliminamos el ultimo OR
    $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE " . $params;

    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($sql));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Lista de pagos semanales
  $app->get('/pagos/semana/disenadores', function (Request $request, Response $response, array $args) {
    // OBTERER PAGOS DE VENDEDORES
    $localConnection = new LocalDB();

    // DISEÑADORES
    $sql = 'SELECT
                p._id id_pago,
                p.id_orden,
                r._id id_revision,
                p.monto_pago,
                p.id_empleado,
                p.fecha_pago,
                (
                SELECT
                    departamento
                FROM
                    api_empresas.empresas_usuarios
                WHERE
                    id_usuario = r.id_empleado
            ) departamento,
            (
                SELECT
                    nombre
                FROM
                    api_empresas.empresas_usuarios
                WHERE
                    id_usuario = r.id_empleado
            ) nombre,
            (
                SELECT
                    product producto
                FROM
                    products
                WHERE
                    _id = r.id_product
            ) producto
            FROM
                pagos p
            JOIN revisiones r ON
                p.id_orden = r.id_orden AND p.id_empleado = r.id_empleado 
            WHERE p.fecha_pago IS  NULL 
            GROUP BY p._id
        ';
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);

      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }
    /* $app->get('/pagos/semana/disenadores', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        $localConnection = new LocalDB();

        // DISEÑADORES
        $sql = "SELECT
            ord._id id_orden,
            dis._id id_diseno,
            dis.id_product,
            rev._id id_revision,
            dis.id_empleado id_disenador,
            (
            SELECT
                nombre
            FROM
                api_empresas.empresas_usuarios
            WHERE
                id_usuario = dis.id_empleado
        ) disenador,
        ord.id_wp id_cliente,
        ord.cliente_nombre,
        dis.tipo tipo_diseno,
        rev.detalles,
        rev.estatus,
        rev.revision
        FROM
            ordenes ord
        RIGHT JOIN disenos dis ON
            dis.id_orden = ord._id
        LEFT JOIN revisiones rev ON
            rev.id_orden = ord._id AND rev.id_empleado = dis.id_empleado
        WHERE
            dis.id_product IS NOT NULL AND rev.estatus = 'Esperando Respuesta'
        ORDER BY ord._id ASC, ord.cliente_nombre ASC
        ";
        $object['data']['diseno'] = $localConnection->goQuery($sql);

        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);

            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        } */

    // TODO REPROGRAMAR PAGOS POR TAREASADICIONALES, PRIMERO DEBEN SER APROBADAS PARA SU PAGO
    /* $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
        foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
            $existe = false;
            foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $trabajos_adicionales_nuevos[] = $trabajo_adicional;
            }
        }
        $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
        $object['data']['trabajos_adicionales'] = [];
    } */

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/semana/empleados', function (Request $request, Response $response, array $args) {
    // OBTERER PAGOS DE VENDEDORES
    $localConnection = new LocalDB();

    // EMPLEADOS
    $sql = 'SELECT DISTINCT
                a._id id_pago,
                d.id_woo cod,
                b._id id_lotes_detalles,
                b.id_orden orden,    
                d.name producto,
                -- SUM(d.cantidad) cantidad, 
                a.cantidad,   
                d.talla,
                c.id_usuario id_empleado,
                c.nombre,
                (a.comision * a.cantidad) pago,
                a.comision,
                a.comision_tipo,
                -- (SELECT departamento FROM departamentos WHERE _id = b.id_departamento) departamento,
                a.detalle departamento,
                DATE_FORMAT(b.fecha_terminado, "%a") dia,
                DATE_FORMAT(b.fecha_terminado, "%v") semana,
                DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
                a.fecha_pago,
                TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
                pagos a
            JOIN lotes_detalles_empleados_asignados b ON
                a.id_lotes_detalles = b._id
            LEFT JOIN api_empresas.empresas_usuarios c
            ON
                b.id_empleado = c.id_usuario
            LEFT JOIN ordenes_productos d ON
                b.id_orden = d.id_orden
            WHERE
                a.fecha_pago IS NULL
            GROUP BY a._id 
            ORDER BY
                c.nombre ASC,
                b.id_orden ASC,
                a._id ASC
        ';
    /*  $sql = 'SELECT
         a._id id_pago,
         b.id_woo cod,
         b._id id_lotes_detalles,
         b.id_orden orden,
         b.id_woo id_woo,
         d.name producto,
         b.unidades_solicitadas cantidad,
         d.talla,
         c.id_usuario id_empleado,
         c.nombre,
         a.monto_pago pago,
         a.comision,
         a.comision_tipo,
         b.departamento,
         DATE_FORMAT(b.fecha_terminado, "%a") dia,
         DATE_FORMAT(b.fecha_terminado, "%v") semana,
         DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
         a.fecha_pago,
         TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
         FROM
         pagos a
         JOIN lotes_detalles b ON
         a.id_lotes_detalles = b._id
         JOIN api_empresas.empresas_usuarios c ON
         b.id_empleado = c.id_usuario
         JOIN ordenes_productos d ON
         b.id_ordenes_productos = d._id
         WHERE
         a.fecha_pago IS NULL
         ORDER BY
         c.nombre ASC,
         b.id_orden ASC,
         a._id ASC;'; */

    $object['sql'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/semana/vendedores', function (Request $request, Response $response, array $args) {
    // OBTERER PAGOS DE VENDEDORES
    $localConnection = new LocalDB();

    // VENDEDORES
    // $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE a.fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC";
    $sql = "SELECT
                a._id AS id_pago,
                a.id_orden,
                a.id_empleado,
                a.detalle,
                a.cantidad,
                a.monto_pago AS pago,
                -- (a.comision * a.cantidad) pago,
                a.comision,
                a.comision_tipo,
                c.nombre,
                d.pago_abono monto_abonado,
                e.monto monto_abonado_abono,
                d.status,
                e.tipo_de_pago,
                a.fecha_pago,
                DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago
            FROM
                pagos a
            JOIN abonos b ON
                b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
            JOIN api_empresas.empresas_usuarios c
            ON
                a.id_empleado = c.id_usuario
            JOIN ordenes d ON
                a.id_orden = d._id
            LEFT JOIN metodos_de_pago e ON
                e._id = a.id_metodos_de_pago
            WHERE
                a.fecha_pago IS NULL
            GROUP BY
                a._id
            ORDER BY
                d._id ASC,
                a._id
            DESC
    ;
        ";

    // $object['sql'] = $sql;
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/pagos/historico/{semana}', function (Request $request, Response $response, array $args) {
    // $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PAGOS VENDEDORES
    $sql = "SELECT 
        a._id AS id_pago,
        a.id_orden,
        a.id_empleado,
        a.detalle,
        a.cantidad,
        a.monto_pago AS pago,
        a.comision,
        a.comision_tipo,
        c.nombre,
        d.pago_abono monto_abonado,
        e.monto monto_abonado_abono,
        d.status,
        e.tipo_de_pago,
        a.fecha_pago,
        DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago
        FROM 
        pagos a
        JOIN 
        abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
        JOIN 
        api_empresas.empresas_usuarios c ON a.id_empleado = c.id_usuario
        JOIN 
        ordenes d ON a.id_orden = d._id
        LEFT JOIN 
        metodos_de_pago e ON e._id = a.id_metodos_de_pago
        WHERE WEEK(a.moment, 1) = {$args['semana']} AND a.fecha_pago IS NOT NULL
        GROUP BY 
        a._id
        ORDER BY 
        d._id ASC, a._id DESC;
        ";
    $object['data']['vendedores'] = $localConnection->goQuery($sql);

    // PAGOS ESPLEADOS
    $sql = 'SELECT
            a._id id_pago,
            b.id_woo cod,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            b.unidades_solicitadas cantidad,
            d.talla,
            c.id_usuario id_empleado,
            c.nombre,
            a.monto_pago pago,
            a.comision,
            a.comision_tipo,
            c.departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            a.fecha_pago,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            JOIN lotes_detalles b ON
            a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON
            b.id_empleado = c.id_usuario
            JOIN ordenes_productos d ON
            b.id_ordenes_productos = d._id
            WHERE WEEK(a.moment, 1) = ' . $args['semana'] . ' AND a.fecha_pago IS NOT NULL
            ORDER BY
            c.nombre ASC,
            b.id_orden ASC,
            a._id ASC;';
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    $object['sql_pagos_empleados'] = $sql;

    // DISENADORES
    $sql = 'SELECT
            p.id_orden,
            r._id id_revision,
            p.monto_pago,
            p.id_empleado,
            p.fecha_pago,
            (SELECT departamento FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado) departamento,
            (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado) nombre,
            (SELECT product producto FROM products WHERE _id = r.id_product) producto

        FROM
            pagos p
        JOIN revisiones r ON p.id_orden = r.id_orden AND p.id_empleado = r.id_empleado 
        GROUP BY p._id
        ';
    $object['sql_disenos'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    /* if (!empty($object['data']['diseno'])) {
        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);

            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        }
    } else {
        $object['data']['trabajos_adicionales'] = [];
    } */

    $trabajos_adicionales_nuevos = [];
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Lista de pagos semanales con filtro de fechas
  $app->post('/pagos/semana', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    if (isset($data['numero_semana'])) {
      //
      $where = 'WEEK(e.moment) = ' . $data['numero_semana'] . "%' AND e.fecha_pago IS NULL";
      // $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
    } else {
    }

    if ($data['fecha_inicio'] === $data['fecha_fin']) {
      $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
      // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
    } else {
      $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
      $whereEmpleados = "b.fecha_inicio >= '" . $data['fecha_inicio'] . "' AND DATE_ADD(b.fecha_terminado, INTERVAL -1 DAY) <= '" . $data['fecha_fin'] . "' ";
    }

    $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    $object['data']['vendedores'] = $localConnection->goQuery($sql);
    // FIN BUSCAR PAGOS DE VENDEDORES

    // OBTENER PAGOS DE EMPLEADOS
    $sql = 'SELECT
            a._id id_pago,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            d.talla,
            c.id_usuario id_empleado,
            c.nombre,
            c.comision,
            b.id_departamento,
            b.departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            b.unidades_solicitadas cantidad,
            a.monto_pago pago,
            a.fecha_pago,
            a.cantidad,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            JOIN lotes_detalles b ON
            a.id_lotes_detalles = b._id
            JOIN api_empresas.empresas_usuarios c ON
            b.id_empleado = c.id_usuario
            JOIN ordenes_productos d ON
            b.id_ordenes_productos = d._id
            WHERE ' . $whereEmpleados . ' AND a.fecha_pago IS NULL 
            ORDER BY
            c.nombre ASC,
            b.id_orden ASC,
            a._id ASC;
        ';

    $object['sql']['empleados'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    // FIN PAGOS EMPLEADOS

    // OBTENER INFORMACION DE DISEÑADORES
    $sql = "SELECT 
        e._id id_pago,
        e.id_orden, 
        e.id_empleado,
        e.detalle detalle_pago,
        a._id id_diseno, 
        b.nombre nombre, 
        b.departamento, 
        e.monto_pago pago,
        e.cantidad,
        c.name producto 
        FROM pagos e   
        JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
        JOIN api_empresas.empresas_usuarios b 
        ON b.id_usuario = e.id_empleado 
        JOIN ordenes_productos c 
        ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
        WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
    $object['sql']['diseno'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);
      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }

    $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
      foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
        $existe = false;
        foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
          if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
            $existe = true;
            break;
          }
        }
        if (!$existe) {
          $trabajos_adicionales_nuevos[] = $trabajo_adicional;
        }
      }
      $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
      $object['data']['trabajos_adicionales'] = [];
    }
    // FIN PAGOS DISEÑADORES

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/pagos/semana/OLD', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    if ($data['fecha_inicio'] === $data['fecha_fin']) {
      $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
      $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
      // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
    } else {
      $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
      $whereEmpleados = "b.fecha_terminado BETWEEN '" . $data['fecha_inicio'] . "%' AND '" . $data['fecha_fin'] . "' AND e.fecha_pago IS NULL ";

      // $where = "(DATE(e.moment) BETWEEN '" . $data["fecha_inicio"] . "' AND '" . $data["fecha_fin"] . "') ";
    }

    $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
    $object['data']['vendedores'] = $localConnection->goQuery($sql);
    // FIN BUSCAR PAGOS DE VENDEDORES

    // OBTENER PAGOS DE EMPLEADOS
    $sql = 'SELECT
    a._id id_pago,
    b._id id_lotes_detalles,
    b.id_orden orden,
    b.id_woo id_woo,
    d.name producto,
    d.talla,
    c._id id_empleado,
    c.nombre,
    c.comision,
    c.departamento,
    DATE_FORMAT(b.fecha_terminado, "%a") dia,
    DATE_FORMAT(b.fecha_terminado, "%v") semana,
    DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
    b.unidades_solicitadas cantidad,
    a.monto_pago pago,
    a.fecha_pago,
    a.cantidad,
    TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
    FROM
    pagos a
    JOIN lotes_detalles b ON
    a.id_lotes_detalles = b._id
    JOIN empleados c ON
    b.id_empleado = c._id
    JOIN ordenes_productos d ON
    b.id_ordenes_productos = d._id
    WHERE ' . $whereEmpleados . ' AND e.fecha_pago IS NULL 
    ORDER BY
    c.nombre ASC,
    b.id_orden ASC,
    a._id ASC;
    ';

    $object['sql']['empleados'] = $sql;
    $object['data']['empleados'] = $localConnection->goQuery($sql);
    // FIN PAGOS EMPLEADOS

    // OBTENER INFORMACION DE DISEÑADORES
    $sql = "SELECT 
    e._id id_pago,
    e.id_orden, 
    e.id_empleado,
    e.detalle detalle_pago,
    a._id id_diseno, 
    b.nombre nombre, 
    b.departamento, 
    e.monto_pago pago,
    e.cantidad,
    c.name producto 
    FROM pagos e   
    JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
    JOIN empleados b 
    ON b._id = e.id_empleado 
    JOIN ordenes_productos c 
    ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
    WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
    $object['sql']['diseno'] = $sql;
    $object['data']['diseno'] = $localConnection->goQuery($sql);

    foreach ($object['data']['diseno'] as $key => $value) {
      // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
      $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
      $tmpResp = $localConnection->goQuery($sqlTMP);
      if (!empty($tmpResp)) {
        foreach ($tmpResp as $key2 => $value2) {
          $object['data']['trabajos_adicionales'][] = $value2;
        }
      }
    }

    $trabajos_adicionales_nuevos = [];

    if (!empty($object['data']['trabajos_adicionales'])) {
      foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
        $existe = false;
        foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
          if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
            $existe = true;
            break;
          }
        }
        if (!$existe) {
          $trabajos_adicionales_nuevos[] = $trabajo_adicional;
        }
      }
      $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
    } else {
      $object['data']['trabajos_adicionales'] = [];
    }
    // FIN PAGOS DISEÑADORES
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN PAGOS */

  /** ENVIAR EMAILS */
  $app->get('/send-email', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $recipient = 'ozcaratemcio@gmail.com';
    $subject = 'titulo del mensaje';
    $message = '<h3>Tiutlo H3</h3><p>Un parrafo...</p>';

    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type:text/html;charset=UTF-8' . "\r\n";
    $headers .= 'From: Your Name <your_email@example.com>' . "\r\n";

    $sent = mail($recipient, $subject, $message, $headers);

    if ($sent) {
      $response->getBody()->write(json_encode(['success' => true]));
    } else {
      $response->getBody()->write(json_encode(['success' => false]));
    }

    return $response->withHeader('Content-Type', 'application/json');
  });

  $app->get('/sendmail/{id_orden}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();

    // Aquí deberías llamar a la función de la clase WooMe para enviar el correo electrónico.
    // Por ejemplo:
    $html = '<!DOCTYPE html> <html> <head> <title>Confirmación de Pedido</title> <style> /* Estilos para el correo electrónico */ body { font-family: Arial, sans-serif; background-color: #f5f5f5; } .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); } h1 { color: #333333; margin-bottom: 10px; } p { color: #666666; margin-bottom: 20px; } table { width: 100%; border-collapse: collapse; margin-bottom: 20px; } th, td { border: 1px solid #e5e5e5; padding: 8px; text-align: left; } th { background-color: #f5f5f5; font-weight: bold; } .total { font-weight: bold; text-align: right; } </style> </head> <body> <div class="container"> <h1>Confirmación de Pedido</h1> <p>Estimado cliente, gracias por su pedido. A continuación, encontrará los detalles del pedido:</p> <table> <thead> <tr> <th>Producto</th> <th>Talla</th> <th>Cantidad</th> <th>Tipo de Tela</th> <th>Precio</th> </tr> </thead> <tbody> <tr> <td>Camiseta</td> <td>XL</td> <td>2</td> <td>Algodón</td> <td>$20.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> </tbody> <tfoot> <tr> <td colspan="4" class="total">Total:</td> <td>$XXX.XX</td> <!-- Reemplaza con el total real --> </tr> </tfoot> </table> <p>Gracias por elegir nuestros productos. Esperamos que disfrute de su compra.</p> </div> </body> </html>';

    // $object['dataOrder'] = $woo->getOrderById($args['id_orden']);
    // $html = '<h1>Prueba mensaje en html</h1><p>Esto es un parrafo </p> <p style="color:red">Este es ptro con texto rojo</p>';
    // $result = $woo->sendMail($args['id_orden'], $html); // Reemplaza "enviarCorreoElectronico" con la función real para enviar correos
    // Verifica el resultado y devuelve una respuesta adecuada
    if ($result) {
      $object['respuesta'] = 'Correo electrónico enviado con éxito';
    } else {
      $object['respuesta'] = 'No se envió el correo electrónico';
    }
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** PRODUCTOS */

  // Obtener todos los productos

  $app->get('/customer/create/nine', function (Request $request, Response $response) {
    $woo = new WooMe();
    // $response->getBody()->write($woo->createCustomerNeneteen());
    $response->getBody()->write($woo->updateCustomerNine(36));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products', function (Request $request, Response $response) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getAllProducts());

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products-attributes', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT attribute_name as name, _id FROM products_attributes ORDER BY name ASC';
    $attributes = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $object['data'] = $attributes;

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/products/categories/{id_category}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getCategoryById($args['id_category']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener todos los productos asignados a una orden
  $app->get('/productos-asignados/{orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = ' . $args['orden'] . " AND category_name != 'Diseños'";

    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener prodcuto por ID
  $app->get('/products/{id}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $product = $woo->getProductById($args['id']);
    $response->getBody()->write(json_encode($product));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTUALIZAR PRECIOS DE PRODUCTOS (VAMOS A RECIBIR UN ARREGLO DE PRECIOS)
  $app->post('/products-update-price', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'INSERT INTO `products_prices`(`id_product`, `price`, `descripcion`) VALUES (' . $data['id_product'] . ',' . $data['price'] . ",'" . $data['descripcion'] . "')";
    $object['request'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo producto
  $app->post('/products/{name}/{sku}/{price}/{stock_quantity}/{categories}/{sizes}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $response->getBody()->write($woo->createProduct($args['name'], $args['sku'], $args['price'], $args['stock_quantity'], $args['categories'], $args['sizes']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo producto lite
  // $app->post('/products/lite/{name}/{price}/{categories}', function (Request $request, Response $response, array $args) {
  $app->post('/products/lite', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $product['product'] = $data['product'];
    $product['category'] = $data['category'];
    $product['sku'] = $data['sku'];
    $product['prices'] = $data['prices'];

    $woo = new WooMe();
    $responseProd = $woo->createProductLite($data['product'], $product['prices'], $data['category'], $data['sku']);

    // $response->getBody()->write($responseProd);
    $response->getBody()->write(json_encode($responseProd));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
    // $response->getBody()->write(json_encode($woo->getAllProducts()));
  });

  // Editar producto
  $app->post('/editar-producto', function (Request $request, Response $response) {
    /* $woo = new WooMe();
        $response->getBody()->write($woo->updateProduct($data["id"], $data["product"], $data["price"], $data["unidades"], $data["sku"], $data["category"]));

        $response->getBody()->write(json_encode($data)); */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // ACTUALIZAR PRODUCTO
    $sql = "UPDATE
            `products`
        SET
            `product` = '" . $data['product'] . "',
            `sku` = '" . $data['sku'] . "',
            `category_ids` = '" . $data['category'] . "'
        WHERE
            `_id` = " . $data['id'] . ';';

    $sql .= 'SELECT * FROM products WHERE _id = ' . $data['id'];
    $resp = $localConnection->goQuery($sql);

    // ACTUALIZAR PRECIOS
    $sql = '';
    foreach (json_decode($data['prices'], true) as $price) {
      $sql .= "UPDATE products_prices 
            SET price = {$price['price']}, descripcion = '{$price['description']}' 
            WHERE id_product = {$data['id']};";
    }

    // Ejecutar la consulta
    $localConnection->goQuery($sql);

    $localConnection->disconnect();
    $object['response'] = $resp;
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar Stock
  $app->put('/products/stock/{id}/{stock_quantity}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();

    $response->getBody()->write($woo->updateProductStock($args['id'], $args['stock_quantity']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar Producto (Usamos el metodo `options` porque noo acepta metodo `delete`  da ERROR 405)
  $app->delete('/products/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // VERIFICAR ASIGNACIÓN DEL PRODUCTO
    $sql = 'SELECT COUNT(_id) cantidad_prod FROM ordenes_productos WHERE id_woo = ' . $args['id'];
    // $product_exist = json_encode($localConnection->goQuery($sql));
    $product_exist = $localConnection->goQuery($sql);

    $object['cantidad_prod'] = $product_exist[0]['cantidad_prod'];

    if (intval($object['cantidad_prod']) === 0) {
      $sql = 'DELETE FROM products WHERE _id =  ' . $args['id'];
      $object['response'] = json_encode($localConnection->goQuery($sql));

      $sql = 'DELETE FROM products_prices WHERE id_product =  ' . $args['id'];
      $object['response'] = json_encode($localConnection->goQuery($sql));

      $sql = 'DELETE FROM products_attributes_values WHERE id_product =  ' . $args['id'];
      $object['response'] = json_encode($localConnection->goQuery($sql));
    }

    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object), JSON_NUMERIC_CHECK);

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN PROIDUCTOS */

  /** MANEJO DE ATRIBUTOS PARA PRODUCTOS */

  // ASIGNAR COMISION A PRODUCTO
  $app->post('/product-set-comision-producto', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $tmpConnection = new LocalDB();

    $sql = "SELECT _id FROM products_comisiones WHERE id_product = {$data['id_product']} AND id_departamento = {$data['id_departamento']}";
    $object['response_verify'] = $tmpConnection->goQuery($sql);

    if (empty($object['response_verify'])) {
      $sql = 'INSERT INTO products_comisiones (`comision`, `id_product`, `id_departamento`) VALUES (' . $data['comision'] . ', ' . $data['id_product'] . ", '" . $data['id_departamento'] . "');";
    } else {
      $sql = "UPDATE products_comisiones SET comision = {$data['comision']} WHERE id_product = {$data['id_product']} AND id_departamento = {$data['id_departamento']}";
    }

    $object['sql'] = $sql;

    $object['response'] = $tmpConnection->goQuery($sql);

    $tmpConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ASIGNAR COMISION A PRODUCTO Y EMPLEADO
  $app->get('/product-set-comision/{id}/{comision}', function (Request $request, Response $response, array $args) {
    $tmpConnection = new LocalDB();
    /* $woo = new WooMe();
    $res = $woo->updateProductComision($args['id'], $args['comision']);
    $object['res'] = $res; */

    $sql = 'UPDATE products SET comision = ' . floatval($args['comision']) . ' WHERE _id = ' . $args['id'] . ';';
    $sql .= 'SELECT * FROM products WHERE _id = ' . $args['id'];
    $object['res'] = $tmpConnection->goQuery($sql);

    $sql1 = 'SELECT _id id_lotes_detalles, unidades_solicitadas, id_empleado FROM lotes_detalles WHERE id_woo = ' . $args['id'] . " AND departamento = 'Costura' AND fecha_terminado IS NOT NULL";
    $resp = $tmpConnection->goQuery($sql1);

    if (!empty($resp)) {
      foreach ($resp as $row) {
        $nuevo_pago = floatval($args['comision']) * intval($row['unidades_solicitadas']);
        $sql2 = 'UPDATE pagos SET monto_pago = ' . $nuevo_pago . ' WHERE id_empleado = ' . $row['id_empleado'] . ' AND fecha_pago IS NULL AND id_lotes_detalles = ' . $row['id_lotes_detalles'];
        $tmpConnection->goQuery($sql2);
      }
    }

    $tmpConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENR LOS PARODUCTOS APRA LE MANEJO DE ATRIBUTOS DE COMSIONES
  $app->get('/atributos/comisiones', function (Request $request, Response $response) {
    $woo = new WooMe();
    // $object['data'] = json_decode($woo->getProductsAttr());
    $object['data'] = json_decode($woo->getAllProducts());
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PRUEBA DE CONEXION DIRECTA A LA BASE DE DATOS `ninetengreen` de Wordpress
  $app->get('/wp/products', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = $woo->getAllProducts();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/wp/products/{id_product}', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = $woo->getAllProducts();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN MANEJO DE ATRIBUTOS PARA PRODUCTOS */

  /** * CLIENTES */

  // ELIMINAR UN CLIENTE
  $app->post('/customers/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER TODOS LOS CLIENTES
  $app->get('/customers', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllCustomesrs());
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OPTMIZAR CLIENTES
  $app->get('/customers/optimize/{key}/{acc}', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $woo = new WooMe();
    $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER TODAS LAS ORDENES ASOCIADAS A UN CLIENTE
  $app->get('/customers/orders/{id_customer}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $object['data'] = $woo->getCustomerOrders($args['id_customer']);
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/wp/customers', function (Request $request, Response $response) {
    $woo = new WooMe();
    $object['data'] = json_decode($woo->getAllCustomesrs());
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/customers/orders-count/{customer_email}/{customer_id}', function (Request $request, Response $response, array $args) {
    // Buscar la cantidad de ordenes en woocommerce
    $woo = new WooMe();
    $object['ordenes_wc'] = $woo->getOrdersCount($args['customer_email']);

    // Buscar si teiene ordenes activas en el sistema de prodsucción
    $localConnection = new LocalDB();
    $sql = "SELECT COUNT(a._id) total_ordenes FROM ordenes a WHERE (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') AND a.id_wp =  " . $args['customer_id'];
    $tmpRes = $localConnection->goQuery($sql);
    $object['ordenes_ns'] = intval($tmpRes[0]['total_ordenes']);

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener Cliente por ID
  $app->get('/customers/{id}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    // $customer = $woo->getCustomerById($args["id"]);
    $customer = $woo->getCustomerByIdWP($args['id']);

    $response->getBody()->write(json_encode($customer));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear un nuevo cliente
  $app->post('/customers/{first_name}/{last_name}/{cedula}/{phone}/{email}/{address}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();

    $response->getBody()->write(
      $woo->createCustomer(
        $args['first_name'],
        $args['last_name'],
        $args['cedula'],
        $args['phone'],
        $args['email'],
        $args['address']
      )
    );
    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar Cliente
  $app->put('/customers/{id}/{first_name}/{last_name}/{cedula}/{phone}/{email}/{billing_address}', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $respWC = json_encode($woo->updateCustomer($args['id'], $args['first_name'], $args['last_name'], $args['cedula'], $args['phone'], $args['email'], $args['billing_address']));
    $response->getBody()->write($respWC);

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar Cliente desde Admin
  $app->post('/customers/edit1', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $woo = new WooMe();
    $response->getBody()->write($woo->updateCustomer($data['id'], $data['first_name'], $data['last_name'], $data['cedula'], $data['phone'], $data['email'], $data['address']));

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Nuevo cliente desde Admin
  $app->post('/customers/nuevo', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $woo = new WooMe();

    $response->getBody()->write(
      $woo->createCustomer(
        $data['first_name'],
        $data['last_name'],
        $data['cedula'],
        $data['phone'],
        $data['email'],
        $data['address']
      )
    );

    /* $response->getBody()->write($woo->updateCustomer($data["id"], $data["first_name"], $data["last_name"], $data["cedula"], $data["phone"], $data["email"], $data["address"])); */

    return $response
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** *  CATEGORIAS */
  $app->get('/categories', function (Request $request, Response $response) {
    $woo = new WooMe();

    $response->getBody()->write($woo->getAllCategories());
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** *  DEPARTAMETOS */
  $app->get('/departamentos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT _id, departamento, id_modulo, enviar_mensaje, orden_proceso, asignar_numero_de_paso FROM departamentos ORDER BY orden_proceso ASC';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Departamentos por empleado
  $app->get('/departamentos-empleado/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT a.id_departamento, b.departamento from api_empresas.empresas_usuarios_departamentos a JOIN departamentos b On b._id = a.id_departamento WHERE a.id_empleado = {$args['id_empleado']}";
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* NUEVO DEPARTAMENTO */
  $app->post('/departamentos/nuevo', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Buscar último número de orden_proceso
    $sql = 'SELECT COUNT(*) + 1 total_departamentos FROM departamentos;';
    $resTotal = $localConnection->goQuery($sql);
    $totalDepartamentos = intval($resTotal[0]['total_departamentos']);

    $sql = "INSERT INTO departamentos (orden_proceso, enviar_mensaje, id_modulo, asignar_numero_de_paso, departamento) VALUES ({$totalDepartamentos}, {$data['enviar_mensaje']}, {$data['modulo']}, {$data['asignar_paso']}, '{$data['departamento']}')";
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR NOMBRE DEL DEPARTAMENTO */
  $app->post('/departamentos/editar', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE departamentos SET enviar_mensaje = {$data['enviar_mensaje']}, asignar_numero_de_paso = {$data['asignar_paso']}, departamento = '" . $data['departamento'] . "' WHERE _id = " . $data['id_departamento'];
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR NOMBRE ENVIO DE MENSAJE */
  $app->post('/departamentos/editar/mensaje', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE departamentos SET enviar_mensaje = {$data['enviar_mensaje']} WHERE _id = " . $data['id_departamento'];
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ELIMINAR DEPARTAMENTO */
  $app->delete('/departamentos/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "DELETE FROM departamentos WHERE _id = {$args['id_departamento']}";
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* ACTUALIZAR ORDEN DE PROCESO DE DEPARTAMENTO */
  $app->post('/departamentos/orden-paso', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $nuevoOrden = intval($data['orden_proceso']);

    // ACTUALIZAR ORDEN DE DEPARTAMENTOS SIGUIENTES SUMANDO 1 AL ORDEN DEL DEPARTAMENTO
    // $sql = 'UPDATE departamentos SET orden_proceso = (orden_proceso + 1) WHERE orden_proceso > ' . $data['orden_proceso_cur'] . ';';
    $sql = 'UPDATE departamentos SET orden_proceso = ' . $data['orden_proceso'] . ' WHERE _id = ' . $data['id_departamento'] . ';';
    $sql .= 'SELECT _id, departamento, orden_proceso FROM departamentos WHERE _id = ' . $data['id_departamento'] . ' ORDER BY orden_proceso ASC';
    $object['sql'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN CATEGORIAS */

  /** * ATRIBUTOS */
  $app->get('/attributes', function (Request $request, Response $response) {
    $woo = new WooMe();
    $response->getBody()->write($woo->getAllAttributes());

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN ATRINUTOS */

  /** * TALLAS */
  $app->get('/sizes', function (Request $request, Response $response) {
    $woo = new WooMe();
    $sizes = json_decode($woo->getSizes());
    $object['data'] = $sizes;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN TALLAS */

  /** * ORDENES */

  // Editar orden -> Actualixar datos cambio de endpoint a _null previniendo acceso

  $app->post('/orden/editar', function (Request $request, Response $response) {
    /**
     * opciones de edición
     *   - editar-talla
     *   - editar-cantidad
     *   - editar-corte
     *   - editar-tela
     *   - nuevo-producto
     *   - eliminar-producto
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    switch ($data['accion']) {
      case 'editar-cantidad':
        $sql = 'UPDATE ordenes_productos SET cantidad = ' . $data['cantidad'] . ' WHERE _id = ' . $data['id'];
        $resp = $localConnection->goQuery($sql);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

        $resp = $localConnection->goQuery($sql);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
        break;

      case 'editar-talla':
        // Guardar nuevos datos
        $sql = "UPDATE ordenes_productos SET precio_unitario = '" . $data['precio'] . "', talla = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
        $resp = $localConnection->goQuery($sql);

        // Recalcular nuevo pago_total de la orden
        $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

        $resp = $localConnection->goQuery($sql);
        $object['total_sql'] = $sql;
        $nuevototal = $resp[0]['total'];

        // Guardar nuevo pago_total de la orden
        $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
        break;

      case 'editar-corte':
        $sql = "UPDATE ordenes_productos SET corte = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'];
        break;

      case 'eliminar-producto':
        $sql = 'DELETE FROM ordenes_productos WHERE _id = ' . $data['id'];
        break;

      case 'editar-tela':
        // Guardar cambios
        $sql = "UPDATE ordenes_productos SET tela = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
        break;

      case 'nuevo-producto':
        $campos = '(moment, id_orden, id_woo, precio_woo, name, cantidad, talla, corte, tela, precio_unitario)';

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $values = '(';
        $values .= "'" . $now . "',";
        $values .= '' . $data['id_orden'] . ',';
        $values .= '' . $data['id_woo'] . ',';
        $values .= '' . $data['precio_woo'] . ',';
        $values .= "'" . $data['name'] . "',";
        $values .= '' . $data['cantidad'] . ',';
        $values .= "'" . $data['talla'] . "',";
        $values .= "'" . $data['corte'] . "',";
        $values .= "'" . $data['tela'] . "',";
        $values .= '' . $data['precio_unitario'] . ')';

        $sql = 'INSERT INTO ordenes_productos ' . $campos . ' VALUES ' . $values;
        break;

      default:
        // code...
        break;
    }

    $resp = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['response'] = $resp;

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar estado de la orden
  $app->post('/orden/actualizar-estado', function (Request $request, Response $response, $args) {
    $order = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE ordenes SET status = '" . $order['estado'] . "' WHERE _id = " . $order['id'];
    $data = $localConnection->goQuery($sql);

    // Generar el regstro en Woocomemrce si la orden está terminada
    if ($order['estado'] === 'terminada' || $order['estado'] === 'entregada') {
      $sql = 'SELECT id_wp_order FROM ordenes WHERE _id = ' . $order['id'];
      $data = $localConnection->goQuery($sql);

      if (!is_null($data[0]['id_wp_order'])) {
        $woo = new WooMe();

        if ($order['estado'] === 'terminada') {
          // UPDATE PRODUCTS QUANTITY
          // Buscar cantidades de productos en ninesys
          $sql = 'SELECT id_woo, cantidad FROM `ordenes_productos` WHERE id_orden = ' . $order['id'];
          $productos = $localConnection->goQuery($sql);

          // $data['prod_ninesys'] = $productos;

          foreach ($productos as $key => $producto) {
            // Buscar existencia de productos en WC
            $tmpProd = $woo->getProductById($producto['id_woo']);

            // Sumar cantidades de ambas fuentes
            $tmpCantidad = $tmpProd->stock_quantity + $producto['cantidad'];

            $woo->updateProductQuantity($producto['id_woo'], $tmpCantidad);
          }
        }

        if ($order['estado'] === 'entregada') {
          $r = $woo->updateOrderStatus(intval($data[0]['id_wp_order']), 'completed');
        }
      } else {
        $r['wc'] = 'La orden no tiene ID de pedido de Woocomemrce';
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Buscar ordenes para asignación

  $app->get('/orden/asignacion/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id = $args['id'];

    $sql['detalle_empleados'] = 'SELECT `dep_responsable_detalles` responsable, `dep_diseno_detalles` diseno, `dep_corte_detalles` corte, `dep_impresion_detalles` impresion, `dep_estampado_detalles` estampado, `dep_confeccion_detalles` confeccion, `dep_revision_detalles` revision FROM `ordenes` WHERE `_id` = ' . $id;

    $sql['orden'] = " SELECT _id, status, cliente_nombre, cliente_cedula, lote_id lote, fecha_inicio, fecha_entrega FROM ordenes WHERE _id = '" . $id . "' ";
    $sql['orden_personas'] = "SELECT * FROM ordenes_personas WHERE id_order = '" . $id . "'";
    $sql['ordeen_personas_productos'] = "SELECT a._id, a.id_orden, a.idp, a.prodcuto, a.cantidad, a.talla, a.tela, a.detalles, b.nombre FROM ordenes_personas_productos a JOIN ordenes_personas b ON a.idp = b.idp WHERE id_orden = '" . $id . "'";
    $sql['orden_productos'] = "SELECT _id, id_woo, name FROM ordenes_productos WHERE id_orden = '" . $id . "'";
    $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['corte'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_corte = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['impresion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_impresion = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['estampado'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_estampado = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['confeccion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_confeccion = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['revision'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_revision = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['responsable'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_responsable = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
    $sql['orden_productos_cantidad'] = "SELECT a.cantidad, a.prodcuto,. a.idp FROM ordenes_personas_productos a WHERE  id_orden = '" . $id . "'";
    $sql['lotes_detalles'] = 'SELECT producto, unidades_solicitadas, unidades_restantes, departamento, id_orden FROM lotes_detalles WHERE id_orden = ' . $id;

    $object = $localConnection->goQuery($sql['orden'])[0];

    $object['detalle_empleados'] = $localConnection->goQuery($sql['detalle_empleados'])[0];

    $object['orden_productos_cantidad'] = $localConnection->goQuery($sql['orden_productos_cantidad']);

    $object['orden_personas'] = $localConnection->goQuery($sql['orden_personas']);

    $object['orden_personas_productos'] = $localConnection->goQuery($sql['ordeen_personas_productos']);

    $object['orden_productos'] = $localConnection->goQuery($sql['orden_productos']);

    // LOTES DETALLES
    $object['lotes_detalles'] = $localConnection->goQuery($sql['lotes_detalles']);

    // EMPLEADOS
    $object['empleados']['corte'] = $localConnection->goQuery($sql['orden_empleados']['corte']);
    if ($object['empleados']['corte'] == null) {
      $object['empleados']['corte'] = '';
    }

    $object['empleados']['impresion'] = $localConnection->goQuery($sql['orden_empleados']['impresion']);
    if ($object['empleados']['impresion'] == null) {
      $object['empleados']['impresion'] = '';
    }

    $object['empleados']['estampado'] = $localConnection->goQuery($sql['orden_empleados']['estampado']);
    if ($object['empleados']['estampado'] == null) {
      $object['empleados']['estampado'] = '';
    }

    $object['empleados']['confeccion'] = $localConnection->goQuery($sql['orden_empleados']['confeccion']);
    if ($object['empleados']['confeccion'] == null) {
      $object['empleados']['confeccion'] = '';
    }

    $object['empleados']['revision'] = $localConnection->goQuery($sql['orden_empleados']['revision']);
    if ($object['empleados']['revision'] == null) {
      $object['empleados']['revision'] = '';
    }

    $object['empleados']['responsable'] = $localConnection->goQuery($sql['orden_empleados']['responsable']);
    if ($object['empleados']['responsable'] == null) {
      $object['empleados']['responsable'] = '';
    }

    $object['empleados']['diseno'] = $localConnection->goQuery($sql['orden_empleados']['diseno']);
    if ($object['empleados']['diseno'] == null) {
      $object['empleados']['diseno'] = '';
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN PPARA EL ABONO
  $app->get('/ordenes/abono/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = 'SELECT a.id_orden, SUM(a.abono) abono, SUM(a.descuento) descuento, b.pago_total total, SUM(a.abono) + SUM(a.descuento) total_abono_descuento, a.detalle, a.moment  FROM abonos a JOIN ordenes b ON a.id_orden = b._id WHERE a.id_orden = ' . $args['id'];
    $datosAbono = $localConnection->goQuery($sql);

    $object['sql'] = $sql;
    $object['data'] = $datosAbono[0];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PASARELAS DE PAGO
  $app->get('/metodos-de-pago', function (Request $request, Response $response, array $args) {
    $woo = new WooMe();
    $object['data'] = $woo->getPG();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // VERIFICAR SI LA ORDEN SE PUEDE EDITAR DESDE COMERCIALIZACION
  $app->get('/ordenes/verificar-edición/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT paso  FROM lotes WHERE id_orden = ' . $args['id'];
    $datosAbono = $localConnection->goQuery($sql);
    $object = $datosAbono[0];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/orden/abono', function (Request $request, Response $response, $args) {
    $datosAbono = $request->getParsedBody();
    $localConnection = new LocalDB();

    // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
    $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $id_vendedor = $localConnection->goQuery($sql)[0]['responsable'];

    // BUSCAR COMISION DEL VENDEDOR
    $sql = 'SELECT comision FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $id_vendedor;
    $respComision = $localConnection->goQuery($sql)[0]['comision'];
    $comisionFloat = floatval($respComision);
    $floatValue = floatval($comisionFloat);
    $comision = number_format($floatValue, 2);
    $object['sql'] = $sql;
    $object['comision'] = $comision;

    /*  $localConnection->disconnect();

     $response->getBody()->write(json_encode($floatValue), JSON_NUMERIC_CHECK);
     return $response
         ->withHeader('Content-Type', 'application/json')
         ->withStatus(200); */

    // ******************************************

    // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
    $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $id_vendedor = $localConnection->goQuery($sql)[0]['responsable'];

    $sql = 'SELECT pago_abono FROM ordenes WHERE _id = ' . $datosAbono['id'];
    $primerAbono = $localConnection->goQuery($sql);
    $totalAbono = floatval($datosAbono['abono']);

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    $values = "'" . $now . "',";
    $values .= "'" . $datosAbono['id'] . "',";
    $values .= "'" . $totalAbono . "',";
    $values .= "'" . $datosAbono['descuento'] . "',";
    $values .= "'" . $datosAbono['empleado'] . "'";

    $sql = 'INSERT INTO abonos(moment, id_orden, abono, descuento, id_empleado) VALUES (' . $values . ')';
    $data = $localConnection->goQuery($sql);

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql_metodos_pago = '';
    if (intval($datosAbono['montoDolaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Efectivo', '" . $datosAbono['montoDolaresEfectivo'] . "', '1');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoDolaresEfectivo'] . "', 'Dólares', 1, '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoDolaresZelle']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleZelle'] . "',  '" . $datosAbono['id'] . "', 'Dólares', 'Zelle', '" . $datosAbono['montoDolaresZelle'] . "', '1');";
    }

    if (intval($datosAbono['montoDolaresPanama']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (detalle, tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePanama'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Panamá', '" . $datosAbono['montoDolaresPanama'] . "', '1');";
    }

    if (intval($datosAbono['montoPesosEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Efectivo', '" . $datosAbono['montoPesosEfectivo'] . "', '" . $datosAbono['tasa_peso'] . "');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoPesosEfectivo'] . "', 'Pesos', '" . $datosAbono['tasa_peso'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoPesosTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePesosTransferencia'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Transferencia', '" . $datosAbono['montoPesosTransferencia'] . "', '" . $datosAbono['tasa_peso'] . "');";
    }

    if (intval($datosAbono['montoBolivaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Efectivo', '" . $datosAbono['montoBolivaresEfectivo'] . "', '" . $datosAbono['tasa_dolar'] . "');";

      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $datosAbono['tasa_dolar'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
    }

    if (intval($datosAbono['montoBolivaresPunto']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Punto', '" . $datosAbono['montoBolivaresPunto'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    if (intval($datosAbono['montoBolivaresPagomovil']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePagomovil'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Pagomovil', '" . $datosAbono['montoBolivaresPagomovil'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    if (intval($datosAbono['montoBolivaresTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleBolivaresTransferencia'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Transferencia', '" . $datosAbono['montoBolivaresTransferencia'] . "', '" . $datosAbono['tasa_dolar'] . "');";
    }

    $object['metodos_pago'] = $localConnection->goQuery($sql_metodos_pago);

    // OBTENER ULTIMO DE LA TABLA metodos_de_pago
    $sql_max_id = 'SELECT MAX(_id) last_id FROM metodos_de_pago';
    $last_id_pago = $localConnection->goQuery($sql_max_id)[0]['last_id'];

    // GUARDAR PAGO
    $comision_vendedor = number_format((floatval($datosAbono['abono']) * $comision / 100), 2);
    $sql = "INSERT INTO pagos (detalle, estatus, monto_pago, id_empleado, id_orden, id_metodos_de_pago) VALUES ('Comercialización', 'aprobado', " . $comision_vendedor . ', ' . $id_vendedor . ', ' . $datosAbono['id'] . ', ' . $last_id_pago . ')';

    $object['response_SET_pago'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR OBSERVACIONES DESDE EDITAR EN COMERCIALIZACION
  $app->post('/orden/edit/obs', function (Request $request, Response $response, $args) {
    $datosObs = $request->getParsedBody();
    $localConnection = new LocalDB();

    // $sql = "UPDATE ordenes SET observaciones = '" . $datosObs["obs"] . "'  WHERE _id = " . $datosObs["id"];
    $sql = "UPDATE ordenes SET observaciones = 'Editada sin concentimiento por " . $datosObs['empleado'] . "'  WHERE _id = " . $datosObs['id'];
    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR DETALLES DEL ABONO
  $app->get('/ordenes/abono-detale/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'moment';
    $object['fields'][0]['label'] = 'Fecha y hora';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'abono';
    $object['fields'][1]['label'] = 'Abono';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'descuento';
    $object['fields'][2]['label'] = 'Descuento';
    $object['fields'][2]['sortable'] = true;
    //  Verificar existencia de la orden
    $sql = 'SELECT _id id_abono, abono, descuento, moment FROM abonos  WHERE id_orden = ' . $args['id'] . ' GROUP BY _id, id_orden';
    $datosAbono = $localConnection->goQuery($sql);
    $object['items'] = $datosAbono;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // REPORTE PAGOS DISEÑADORES
  $app->get('/reportes/resumen/disenadores/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT departamento FROM departamentos WHERE _id = ' . $args['id_departamento'];
    $departamento = $localConnection->goQuery($sql);
    $object['departamento'] = $departamento[0]['departamento'];

    $sql = "SELECT
                a._id id_revision,
                a.id_orden,
                (SELECT product FROM products WHERE _id = a.id_product) producto,
                b.monto_pago,
                '{$object['departamento']}' departamento,
                b.fecha_pago,
                'terminada' progreso
            FROM
                revisiones a
            JOIN pagos b ON b.id_orden = a.id_orden
            JOIN ordenes c ON c._id = a.id_orden AND c.status NOT LIKE 'entregada' AND c.status NOT LIKE 'cancelada' AND c.status NOT LIKE 'terminada' 
            WHERE b.id_empleado = {$args['id_empleado']} AND b.fecha_pago IS NOT NULL
        ";
    $object['ordenes_terminadas'] = $localConnection->goQuery($sql);

    $sql = "SELECT
            a._id AS id_revision,
            a.id_orden,
            p.product AS producto, -- Columna traída directamente del JOIN
            'Diseño' AS departamento
        FROM
            revisiones a
        -- Unir con productos para obtener el nombre de forma eficiente
        LEFT JOIN products p ON p._id = a.id_product
        -- Unir con pagos para poder filtrar por empleado
        JOIN pagos b ON b.id_orden = a.id_orden
        -- Unir con órdenes para filtrar por su estado
        JOIN ordenes c ON c._id = a.id_orden
        WHERE 
            -- Condición principal sobre el empleado
            b.id_empleado = {$args['id_empleado']}
            -- Condición sobre el estado de la orden, simplificada con NOT IN
            AND c.status NOT IN ('entregada', 'cancelada', 'terminada');
        ";
    $object['ordenes_pendientes'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE PAGOS DE EMPLEADOS
  $app->get('/reportes/resumen/empleados/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT departamento FROM departamentos WHERE _id = ' . $args['id_departamento'];
    $departamento = $localConnection->goQuery($sql);
    $object['departamento'] = $departamento[0]['departamento'];

    $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                (SELECT cliente_nombre FROM ordenes WHERE _id = a.id_orden) cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = a.id_orden) total_productos,
                d.comision_tipo,
                -- d.monto_pago,
                (d.comision* d.cantidad) monto_pago,
                TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) - c.tiempo) rendimiento,
                d.cantidad unidades,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                b.id_woo id_producto,
                e.product,
                'EficienciaInsumos' eficiencia_insumos,
                b.talla
            FROM
                lotes_detalles_empleados_asignados a
            -- RIGHT JOIN ordenes ord ON ord._id = a.id_orden
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            JOIN pagos d ON d.id_lotes_detalles = a._id -- DIVIDIR ENTRE CANTIDAD DE EMPLEADOS ASIGNADOS
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']} ORDER BY a.id_orden ASC
            
        ";
    $object['sql_terminadas'] = $sql;

    $pagos = $localConnection->goQuery($sql);
    $object['ordenes_terminadas'] = $pagos;

    // ORDENES PENDIENTES  ((SUM(c.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_variable,
    $sql = "SELECT DISTINCT
                a._id id_lote_detalles,
                a.id_orden,
                ord.cliente_nombre cliente,
                a.fecha_inicio,
                a.fecha_terminado,
                a.progreso,
                d.comision_tipo,
                TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, a.fecha_inicio, a.fecha_terminado) - c.tiempo) rendimiento,
                b.cantidad unidades,
                SUM(b.cantidad) total_productos,
                (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$args['id_departamento']}) cantidad_empleados_asigandos,
                b.id_woo id_producto,
                e.product,
                b.talla,
                ((SUM(b.cantidad) * e.comision) * a.procentaje_comision / 100) AS total_comision_variable,
                ((SUM(b.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_fija
            FROM
                lotes_detalles_empleados_asignados a
            JOIN ordenes ord ON ord._id = a.id_orden
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products e ON e._id = b.id_woo
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            LEFT JOIN api_empresas.empresas_usuarios d ON d.id_usuario = a.id_empleado
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = ord._id
            WHERE a.id_empleado = {$args['id_empleado']} AND a.id_departamento = {$args['id_departamento']} AND (ord.status LIKE 'En espera' OR ord.status LIKE 'activa' OR ord.status LIKE 'pausada') AND e.fisico = 1
            GROUP BY a.id_orden ORDER BY ofo.orden_fila ASC, a.id_orden DESC, a.progreso ASC
        ";
    $pendientes = $localConnection->goQuery($sql);
    $object['sql_pendientes'] = $sql;
    $object['ordenes_pendientes'] = $pendientes;

    // ORDENES PARA CALCULO DE TIEMPO
    $sql = "SELECT 
            y.id_orden,
            MAX(ofo.orden_fila) AS orden_fila,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = y.id_orden AND id_empleado = y.id_empleado) AS extra,
            (SELECT COUNT(_id) FROM reposiciones WHERE id_departamento = 4 AND id_empleado = 20 AND terminada = 0 AND id_orden = y.id_orden) AS en_reposiciones,
            (SELECT COUNT(_id) FROM tintas WHERE id_orden = y.id_orden) AS en_tintas,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = y.id_orden AND id_empleado = 20) AS en_inv_mov,
            (SELECT valor_inicial FROM inventario_movimientos WHERE id_orden = y.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_inicial,
            (SELECT valor_final FROM inventario_movimientos WHERE id_orden = y.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_final,
            MAX(c.prioridad) AS prioridad,
            MAX(z.unidades_produccion) AS unidades_solicitadas,
            SUM(a.cantidad) AS unidades,
            SUM(a.cantidad) AS piezas_actuales,
            MAX(y.fecha_inicio) AS fecha_inicio,
            MAX(y.fecha_terminado) AS fecha_terminado,
            MAX(DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y')) AS fecha_entrega,
            MAX(y._id) AS lotes_detalles_empleados_asignados,
            y.id_departamento,
            (SELECT MIN(dep.orden_proceso) FROM lotes_detalles_empleados_asignados ldea JOIN departamentos dep ON ldea.id_departamento = dep._id WHERE ldea.id_orden = y.id_orden) AS orden_proceso_min,
            (SELECT orden_proceso FROM departamentos WHERE _id = 4) AS orden_proceso_departamento,            
            (SELECT orden_proceso FROM departamentos WHERE _id = MAX(c.id_departamento_actual)) AS orden_proceso,
            MAX(c.id_departamento_actual) AS id_departamento_actual,
            y.id_orden AS orden,
            GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS producto,
            y.id_empleado,
            MAX(x.detalle) AS detalle_reposicion,
            GROUP_CONCAT(DISTINCT (SELECT nombre FROM sizes WHERE _id = a.id_size) SEPARATOR ', ') AS talla,
            GROUP_CONCAT(DISTINCT a.corte SEPARATOR ', ') AS corte,
            GROUP_CONCAT(DISTINCT a.tela SEPARATOR ', ') AS tela,
            MAX(tp.tiempo) AS tiempo_produccion,
            MAX(y.procentaje_comision) AS procentaje_comision,
            MAX(c.paso) AS paso,
            MAX(d.status) AS status,
            MAX(d.fecha_entrega) AS fecha_enrega_raw,
            MAX(d.fecha_entrega) AS fecha_enrega_orden,
            MAX(y.progreso) AS progreso,
            NULL AS detalles_revision
        FROM
            lotes_detalles_empleados_asignados y
            JOIN ordenes_productos a ON y.id_orden = a.id_orden
            JOIN ordenes d ON a.id_orden = d._id
            JOIN products p ON p._id = a.id_woo
            JOIN products_tiempos_de_produccion tp ON tp.id_product = p._id AND tp.id_departamento = 4
            LEFT JOIN lotes c ON c.id_orden = y.id_orden
            LEFT JOIN lotes_historico_solicitadas z ON z.id_orden = a.id_orden
            LEFT JOIN reposiciones x ON x.id_orden = d._id AND x.id_empleado = y.id_empleado AND x.id_ordenes_productos = a._id
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = d._id
        -- ============================ WHERE CORREGIDO Y ALINEADO ============================
        WHERE  
            (y.id_empleado = 20)
            AND (y.id_departamento = 4)
            -- Se eliminan los filtros extra de 'status' y 'fisico' para que la lógica de filtrado sea idéntica a la de la consulta de 'ordenes pendientes'.
        -- ========================================================================
        GROUP BY
            y.id_orden, y.id_empleado, y.id_departamento
        ORDER BY
            orden_fila ASC,
            y.id_orden DESC;
        ";

    $ordenes = $localConnection->goQuery($sql);
    $object['sql_ordenes'] = $sql;
    $object['ordenes'] = $ordenes;

    // EFICIENCIA DE INSUMOS
    $sql = "SELECT
          est.id_orden,
          est.id_empleado,
          est.id_departamento,
          est.nombre_empleado,
          est.nombre_departamento,
          est.fecha_asignacion,
          est.nombre_producto,
          est.talla,
          est.nombre_insumo,
          est.cantidad_piezas,
          est.consumo_estimado_total,
          COALESCE(consumo_r.consumo_real_total, 0) AS consumo_real_total,
          (COALESCE(consumo_r.consumo_real_total, 0) - COALESCE(est.consumo_estimado_total, 0)) AS diferencia
      FROM
          (
              SELECT
                  op.id_orden,
                  ldea.id_empleado,
                  ldea.id_departamento,
                  p.product AS nombre_producto,
                  s.nombre AS talla,
                  cip.nombre AS nombre_insumo,
                  SUM(op.cantidad) AS cantidad_piezas,
                  SUM(op.cantidad * COALESCE(pia.cantidad, 0)) AS consumo_estimado_total,
                  eu.nombre AS nombre_empleado,
                  dep.departamento AS nombre_departamento,
                  ldea.fecha_inicio AS fecha_asignacion
              FROM
                  lotes_detalles_empleados_asignados ldea
              JOIN ordenes_productos op ON ldea.id_orden = op.id_orden
              JOIN products p ON op.id_woo = p._id
              LEFT JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario
              LEFT JOIN departamentos dep ON ldea.id_departamento = dep._id
              LEFT JOIN product_insumos_asignados pia ON op.id_woo = pia.id_product 
                                                      AND op.id_size = pia.id_talla
                                                      AND ldea.id_departamento = pia.id_departamento
              LEFT JOIN sizes s ON op.id_size = s._id
              LEFT JOIN catalogo_insumos_productos cip ON pia.id_catalogo_insumos_productos = cip._id
              WHERE
                  -- ldea.id_orden = 2008
                  ldea.id_empleado = {$args['id_empleado']}
                  AND ldea.id_departamento = {$args['id_departamento']}
              GROUP BY
                  ldea.id_orden, ldea.id_empleado, ldea.id_departamento,
                  p.product, s.nombre, cip.nombre,
                  eu.nombre, dep.departamento, ldea.fecha_inicio
          ) AS est
      LEFT JOIN
          (
              SELECT
                  id_orden,
                  id_departamento,
                  id_empleado,
                  -- Agrupamos todo el consumo real, sin importar el insumo específico
                  SUM(valor_inicial - valor_final) AS consumo_real_total
              FROM
                  inventario_movimientos
              WHERE
                  -- id_orden = 2008
                  id_empleado = {$args['id_empleado']}
                  AND id_departamento = {$args['id_departamento']}
              GROUP BY
                  -- Quitamos id_catalogo_insumos_prodcutos del GROUP BY
                  id_orden, id_departamento, id_empleado
          ) AS consumo_r ON est.id_orden = consumo_r.id_orden
                        AND est.id_departamento = consumo_r.id_departamento
                        AND est.id_empleado = consumo_r.id_empleado;
                        -- Se elimina la condición de 'id_catalogo_insumos_productos' del JOIN final
      ";
    $reficiencia_insumos = $localConnection->goQuery($sql);
    $object['sql_eficiencia_insumos'] = $sql;
    $object['eficiencia_inusmos'] = $reficiencia_insumos;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
    /* if ($departamento === 'Costura') {
    $sql = "SELECT
        a._id id_lote_detalles,
        a.id_orden,
        a.id_woo,
        a.id_orden detalle,
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        b.departamento,
        a.progreso,
        c.name producto,
        c.talla,
        c.corte,
        c.tela,
        c.cantidad,
        b.comision,
        d.fecha_pago,
        d.monto_pago,
        0 calculo_pago
        FROM
        lotes_detalles a
        LEFT JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
        LEFT JOIN pagos d ON d.id_lotes_detalles = a._id
        WHERE
        d.fecha_pago IS NULL AND
        d.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

    $ordenes = $localConnection->goQuery($sql);
    $object['ordenes'] = $ordenes;

    // Buscar comision de productos en woocommerce y recalcular `calculo_pago`
    $tmpOrdenes = null;
    foreach ($object['ordenes'] as $key => $orden) {
        $tmpOrdenes[$key] = $orden;
        $idwc = intval($orden['id_woo']);
        $woo = new WooMe();
        $producto = $woo->getProductById($idwc);

        if (isset($producto->attributes[0]->options)) {
            $comision = $producto->attributes[0]->options[0];
            $tmpOrdenes[$key]['comision'] = $producto->attributes[0]->options[0];
            $comision = floatval($comision) * intval($orden['cantidad']);
            $tmpOrdenes[$key]['calculo_pago'] = $comision;
        } else {
            $comision = 0;
        }
    }
    $object['ordenes'] = $tmpOrdenes;
    $object['ordenes_semana'] = $tmpOrdenes;
}

if ($departamento === 'Corte' || $departamento === 'Estampado' || $departamento === 'Limpieza' || $departamento === 'Revisión' || $departamento === 'Impresión') {
    // REporte todo lo no pagado
    $sql = "SELECT
        a._id id_lotes_detalles,
        a.id_orden,
        a.id_orden detalle,
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        a.unidades_solicitadas cantidad,
        b.name producto,
        FORMAT(b.cantidad * c.comision, 2) AS calculo_pago
        FROM
        lotes_detalles a
        JOIN api_empresas.empresas_usuarios c ON
        a.id_empleado = c.id_usuario
        JOIN ordenes_productos b ON
        a.id_ordenes_productos = b._id
        JOIN pagos d ON
        d.id_lotes_detalles = a._id
        WHERE  d.fecha_pago IS NULL AND
        a.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

    $ordenes = $localConnection->goQuery($sql);
    $object['ordenes'] = $ordenes;

    $sql = "SELECT
        a._id id_lote_detalles,
        a.id_orden,
        a.id_orden detalle,
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        b.departamento,
        a.progreso,
        c.name producto,
        c.talla,
        c.corte,
        c.tela,
        c.cantidad,
        b.comision,
        d.fecha_pago,
        d.monto_pago,
        FORMAT(c.cantidad * b.comision, 2) AS calculo_pago

        FROM
        pagos d
        LEFT JOIN lotes_detalles a ON d.id_lotes_detalles = a._id
        JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
        WHERE
        b.id_usuario = " . $args['id_empleado'] . ' AND  d.fecha_pago IS NULL ORDER BY a.id_orden ASC
    ';
    $ordenes = $localConnection->goQuery($sql);
    $object['ordenes_semana'] = $ordenes;
}

if ($departamento === 'Comercialización' || $departamento === 'Administración') {
    $sql = "SELECT
        a._id id_pagos,
        a.id_orden,
        DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
        b.tipo_de_pago,
        a.monto_pago monto_pago
        FROM pagos a
        LEFT JOIN metodos_de_pago b ON b._id = a.id_metodos_de_pago
        WHERE a.id_empleado = " . $args['id_empleado'] . ' AND a.fecha_pago IS NULL
    ';

    $ordenes = $localConnection->goQuery($sql);
    $object['ordenes_semana'] = $ordenes;
}

if ($departamento === 'Diseño') {
    $sql = "SELECT
        a._id id_pago,
        a.id_orden,
        a.cantidad,
        a.fecha_pago fecha_terminado,
        a.detalle producto,
        b.tipo tipo_arreglo,
        a.monto_pago calculo_pago,
        CASE
        WHEN b.tipo IS NOT NULL THEN b.tipo
        ELSE 'Diseño'
        END AS tipo
        FROM
        pagos a
        LEFT JOIN disenos_ajustes_y_personalizaciones b
        ON b.id_orden = a.id_orden
        WHERE
        a.fecha_pago IS NULL AND a.id_empleado = " . $args['id_empleado'] . '
        GROUP BY a._id
        ORDER BY
        a._id
        DESC;
    ';

    $ordenes = $localConnection->goQuery($sql);

    $object['ordenes'] = $ordenes;
    $object['ordenes_semana'] = $ordenes;
} */
  });

  // REPORTE SEMANAL DE PAGOS Y ABONOS
  $app->get('/comercializacion/reportes/pagos-abonos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'id_orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'moment';
    $object['fields'][1]['label'] = 'Fecha y hora';

    $object['fields'][2]['key'] = 'abono';
    $object['fields'][2]['label'] = 'Abono';

    $object['fields'][3]['key'] = 'descuento';
    $object['fields'][3]['label'] = 'Descuento';

    $sql = 'SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a  WHERE YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC';
    $datosAbono = $localConnection->goQuery($sql);
    $object['items'] = $datosAbono;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN POR ID

  $app->get('/ordenes/reporte/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
    $resp = $localConnection->goQuery($sql);

    if (!$resp) {
      $object = $resp;
    } else {
      // Buscar datos del cliente en Woocommerce ...
      $sql = 'SELECT id_wp FROM ordenes WHERE _id  = ' . $id;
      $id_wp = $localConnection->goQuery($sql);
      $id_customer = $id_wp[0]['id_wp'];

      $woo = new WooMe();
      $object = array();

      // Buscar datos de la orden
      $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, 'TREAR DESDE EL `ENDPOINT` DEDICADO' observaciones, a.pago_total, a.pago_abono FROM ordenes a  WHERE _id =  " . $id;
      $object['orden'] = $localConnection->goQuery($sql);

      // Buscar datos del diseño
      // $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
      $sql = 'SELECT a._id id_diseno, a.tipo, a.id_orden, b.revision revision FROM disenos a JOIN revisiones b ON b.id_diseno = a._id WHERE a.id_orden =' . $id;
      $object['diseno'] = $localConnection->goQuery($sql);

      // Buscar datos del cliente
      $object['customer'][0] = $woo->getCustomerById($id_customer);

      // Buscar datos de productos
      $sql = 'SELECT _id, name, id_woo cod, cantidad, talla, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = ' . $id;
      $object['productos'] = $localConnection->goQuery($sql);
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // BUSCAR ORDENES QUE NO TIENEN NINGUN EMPLEADO ASIGNADO

  $app->get('/ordenes/sin-asignacion/{id_vendedor}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = "SELECT
            a._id id_orden,
            cliente_nombre
        FROM
            ordenes a
        LEFT JOIN
            lotes_detalles_empleados_asignados b ON b.id_orden = a._id
        WHERE
            b.id_orden IS NULL 
            AND (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') 
            AND a.responsable = {$args['id_vendedor']}
    ";

    $resp = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($resp));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // BUSCAR ORDEN POR ID
  // Función para obtener la respuesta de /buscar
  function obtenerRespuestaBuscar($id, $email = null): array
  {
    $object = array();
    $localConnection = new LocalDB();

    //  Verificar existencia de la orden
    $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
    $resp = $localConnection->goQuery($sql);

    if (!$resp) {
      $object = $resp;
    } else {
      // Buscar datos del cliente en Woocommerce ...
      $sql = 'SELECT id_wp FROM ordenes WHERE _id  = ' . $id;
      $id_wp = $localConnection->goQuery($sql);
      $id_customer = $id_wp[0]['id_wp'];
      $id_customer = $id_wp[0]['id_wp'];

      // $object["id_customer"] = $id_customer;

      $woo = new WooMe();
      // Buscar datos del cliente
      // $object["customer"][0] = $woo->getCustomerById($id_customer);
      $data = $woo->getCustomerByIdWP($id_customer);
      $customer = json_decode(json_encode($data), true);
      $object['customer']['data'] = $customer;

      $object['customer']['nombre'] = $customer[0]['billing_first_name'] . ' ' . $customer[0]['billing_last_name'];
      $object['customer']['direccion'] = $customer[0]['billing_address_1'];
      $object['customer']['email'] = $customer[0]['billing_email'];
      $object['customer']['cedula'] = $customer[0]['billing_postcode'];
      $object['customer']['telefono'] = $customer[0]['billing_phone'];

      // Buscar datos de la orden!
      $sql = 'SELECT
                a._id,
                a.status,
                a.cliente_nombre,
                c.nombre vendedor,
                b.cedula,
                a.fecha_inicio,
                a.fecha_entrega,
                obs.observaciones,
                a.pago_total
            FROM
                ordenes a
            JOIN customers b ON a.id_wp = b._id
            LEFT JOIN ordenes_observaciones obs ON obs.id_orden = a._id
            LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = a.responsable 
            WHERE
                a._id =' . $id;
      $object['orden'] = $localConnection->goQuery($sql);

      // --- INICIO: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---
      // 1. Obtener la suma total de todos los abonos y descuentos desde la tabla `abonos`.
      $sql_abonos = 'SELECT SUM(abono) AS total_abonos, SUM(descuento) AS total_descuentos FROM abonos WHERE id_orden = ' . $id;
      $totales_abonos = $localConnection->goQuery($sql_abonos);

      // 2. Asignar los totales calculados directamente al objeto de la orden para reflejar el estado de cuenta real.
      // Se usa el operador de fusión de null (??) para asignar 0 si no hay registros de abonos.
      $object['orden'][0]['pago_abono'] = (float) ($totales_abonos[0]['total_abonos'] ?? 0);
      $object['orden'][0]['pago_descuento'] = (float) ($totales_abonos[0]['total_descuentos'] ?? 0);
      // --- FIN: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---

      // Buscar datos del diseño
      $sql = 'SELECT tipo FROM disenos WHERE id_orden =  ' . $id;
      $object['diseno'] = $localConnection->goQuery($sql);
      if (empty($object['diseno'])) {
        $object['diseno'][]['tipo'] = 'Ninguno';
      }

      // Buscar datos de productos
      $sql = 'SELECT
              op._id,
              op.name,
              pr.sku AS sku,
              pr._id AS cod,
              pr.fisico AS producto_fisico,
              op.id_woo,
              op.cantidad,
              op.id_size AS id_talla,
              s.nombre AS talla,
              op.id_tela,
              prices_json.prices, -- Aquí usamos el alias de la subconsulta derivada
              op.tela,
              op.id_tela,
              op.corte,
              op.precio_unitario AS precio,
              (SELECT attribute_name FROM products_attributes WHERE _id = op.id_products_attributes) atributo_nombre,
              op.id_products_attributes AS atributo -- Añadir el atributo del producto
          FROM
              ordenes_productos op
          LEFT JOIN
              products pr ON pr._id = op.id_woo
          LEFT JOIN
              sizes s ON s._id = op.id_size -- Unir directamente con sizes para la talla
          LEFT JOIN (
              -- Subconsulta derivada para agrupar los precios por producto
              SELECT
                  pp.id_product AS product_id,
                  CONCAT(
                      "[",
                      GROUP_CONCAT(
                          JSON_OBJECT(
                              "id",
                              pp._id,
                              "price",
                              pp.price,
                              "description",
                              pp.descripcion
                          )
                      ),
                      "]"
                  ) AS prices
              FROM
                  products_prices pp
              GROUP BY
                  pp.id_product
          ) AS prices_json ON prices_json.product_id = pr._id -- Unir con la tabla de productos
          WHERE
              op.id_orden = 
      ' . $id;

      $tmpProducts = $localConnection->goQuery($sql);

      // PARSEAR PRODUCTOS
      $key = 0;
      foreach ($tmpProducts as $product) {
        $data[$key]['_id'] = intval($product['_id']);
        $data[$key]['name'] = $product['name'];
        $data[$key]['cod'] = $product['cod'];
        $data[$key]['producto_fisico'] = $product['producto_fisico'];
        $data[$key]['id_woo'] = $product['id_woo'];
        $data[$key]['cantidad'] = $product['cantidad'];
        $data[$key]['id_tela'] = $product['id_tela'];
        $data[$key]['id_talla'] = $product['id_talla'];
        $data[$key]['talla'] = $product['talla'];
        $data[$key]['tela'] = $product['tela'];
        $data[$key]['corte'] = $product['corte'];
        $data[$key]['precio'] = $product['precio'];
        $data[$key]['atributo'] = $product['atributo'];  // Añadir el atributo al objeto de producto
        $data[$key]['atributo_nombre'] = $product['atributo_nombre'];  // Añadir el nombre del atributo al objeto de producto
        $data[$key]['prices'] = json_decode($product['prices']);
        /* $data[$key]['producto_fisico'] = json_decode($product['producto_fisico']);
        $data[$key]['comisiones'] = json_decode($product['comisiones']);
        $data[$key]['categories'] = json_decode($product['categories'], true); */

        $key++;
      }
      $object['productos'] = $data;

      $object['productos_count'] = count($object['productos']);

      $object['conterwoo'] = count($object['productos']);

      // Buscar SKU de los productos
      /* $counter = 0;
            foreach ($object['productos'] as $key => $value) { // if (!is_object($value->id_woo)) {
                // $wcprod = $woo->getProductSKU($value->id_woo);
                $object['productos'][$key]['cod'] = $woo->getProductSKU(intval($value['id_woo']));
                // }
                $counter++;
            } */

      // Crear estructura del email de bienvenida:
      /* if ($email) {
                $emailCliente = new EmailClienteBienvenida($object);
                $emailContent = $emailCliente->obtenerContenido();
                $object = $emailContent;
                $contentType = 'text/html';
            } else {
                $object = json_encode($object);
            $contentType = 'application/json';
            } */
    }

    $localConnection->disconnect();

    // $object = json_encode($object, JSON_NUMERIC_CHECK);
    $contentType = 'application/json';
    return array('object' => $object, 'contentType' => $contentType);
  }

  $app->get('/buscar/{id}[/{email}]', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $email = isset($args['email']) ? $args['email'] : null;

    $result = obtenerRespuestaBuscar($id, $email);
    $response->getBody()->write(json_encode($result['object'], JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', $result['contentType'])
      ->withStatus(200);
  });

  /*$app->get('/buscar_old/{id}[/{email}]', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $id = $args["id"];
        $object = array();

//  Verificar existencia de la orden
        $sql = "SELECT _id FROM ordenes WHERE _id=" . $id;
        $resp = $localConnection->goQuery($sql);

        if (!$resp) {
            $object = $resp;
            } else {
// Buscar datos del cliente en Woocommerce ...
                $sql = "SELECT id_wp FROM ordenes WHERE _id  = " . $id;
                $id_wp = $localConnection->goQuery($sql);
                $id_customer = $id_wp[0]["id_wp"];

                $object["id_customer"] = $id_customer;

                $woo = new WooMe();
// Buscar datos del cliente
// $object["customer"][0] = $woo->getCustomerById($id_customer);
                $data = $woo->getCustomerById($id_customer);
                $customer = json_decode(json_encode($data), true);

                $object["customer"]["nombre"] = $customer["first_name"] . " " . $customer["last_name"];
                $object["customer"]["direccion"] = $customer["billing"]["address_1"];
                $object["customer"]["email"] = $customer["billing"]["email"];
                $object["customer"]["cedula"] = $customer["billing"]["postcode"];
                $object["customer"]["telefono"] = $customer["billing"]["phone"];

// Buscar datos de la orden
                $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono, a.pago_descuento FROM ordenes a  WHERE _id =  " . $id;
                $object["orden"] = $localConnection->goQuery($sql);

// Buscar datos del diseño
                $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
                $object['diseno'] = $localConnection->goQuery($sql);
                if (empty($object['diseno'])) {
                    $object['diseno'][]['tipo'] = "Ninguno";
                }

// Buscar datos de productos
                $sql = "SELECT _id, name, id_woo cod, cantidad, talla, tela, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = " . $id;
                $object['productos'] = $localConnection->goQuery($sql);

// Crear estructura del email de bienvenida:
                if (isset($args['email'])) {
                    $emailCliente = new EmailClienteBienvenida($object);
                    $email = $emailCliente->obtenerContenido();
                    $object = $email;
// $object = json_encode($email);
                    $contentType = 'text/html';
                    } else {
                        $object = json_encode($object);
                        $contentType = 'application/json';
                    }
                }

                $localConnection->disconnect();

                $response->getBody()->write(json_encode($object));
                return $response
                ->withHeader('Content-Type', $contentType)
                ->withStatus(200);
            });*/

  $app->get('/ruta2', function (Request $request, Response $response, array $args) {
    // Llamamos a la función que encapsula la lógica de /buscar
    $resultBuscar = obtenerRespuestaBuscar(303, 'true');

    // Modificamos la respuesta si es necesario
    /* $resultBuscar['object'] = json_decode($resultBuscar['object'], true);
        $resultBuscar['object']['modificado_en_ruta2'] = true;
        $resultBuscar['object'] = json_encode($resultBuscar['object']); */

    $response->getBody()->write($resultBuscar['object']);
    return $response
      ->withHeader('Content-Type', $resultBuscar['contentType'])
      ->withStatus(200);
  });

  // ORDENES ACTIVAS, TERMINADAS Y PAUSADAS
  $app->get('/comercializacion/ordenes/reporte', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // BUSCAR ORENES EN CURSO
    $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'activa' OR status = 'pausada' OR status = 'En espera' OR status = 'terminada'  ORDER BY _id DESC";
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES TERMNADAS Y NO ENTREGADAS
  $app->get('/comercializacion/ordenes/reporte/terminadas/{rango}', function (Request $request, Response $response, array $args) {
    $object['rango'] = $args['rango'];
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime($args['rango']);
    $now = $myDate->today();
    $before = $myDate->before();
    $object['moment-today'] = $now;
    $object['moment-before'] = $before;
    $momentInit = $now;
    $momentEnd = $before;

    // BUSCAR ORENES EN CURSO
    $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'terminada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ORDENES ENTREGADAS
  $app->get('/comercializacion/ordenes/reporte/entregadas/{rango}', function (Request $request, Response $response, array $args) {
    $object['rango'] = $args['rango'];
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime($args['rango']);
    $now = $myDate->today();
    $before = $myDate->before();
    $object['moment-today'] = $now;
    $object['moment-before'] = $before;
    $momentInit = $now;
    $momentEnd = $before;

    // BUSCAR ORENES EN CURSO
    // $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'entregada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
    $sql = 'SELECT _id, status, cliente_nombre, _id vinculada from ordenes ORDER BY _id ASC';

    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';

    $object['vinculadas'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = '_id';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente_nombre';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'status';
    $object['fields'][2]['label'] = 'Status';

    $object['fields'][3]['key'] = 'vinculada';
    $object['fields'][3]['label'] = 'Vinculadas';

    $localConnection->disconnect();

    // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE VENTA AL DETAL EN LA TIENDA
  $app->post('/ordenes/nueva', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC; */
    $orderWC = 0;
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

    $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

    // Obtenr id de la orden creada
    $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
    $last_id = intval($last[0]['id']);
    $object['last_id'] = $last_id;

    // Guardar orden vinculada
    if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
      $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
      $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
    }

    // Crear abono inicial de la orden
    $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
    $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

    // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
    // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
    if (floatval($newJson['abono']) > 0) {
      // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
      $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
      $pago_vendedor = number_format($pago_vendedor, 2);
      $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
      $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
      $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
      /* if ($arg["sales_commission"] === true) {
                            $object['sales_commission_ISSET'][] = true;
                            } else {
                                $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                            } */
    }  /*  else {
             $object['sales_commission_ISSET'][] = false;
             } */

    // GUARDAR DATOS DE DISEÑO
    $sql_diseno = '';
    if ($newJson['diseno_grafico'] == 'true') {
      for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
        $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
      }
    }

    if ($newJson['diseno_modas'] == 'true') {
      for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
        $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
      }
    }

    $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));

    // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
    $sql = 'SELECT _id';

    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        /* $woo = new WooMe();
                $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                $tmp = json_decode($data_category);
                $cat_name = $tmp->name; */
        /* if ($tmp->statusCode === 500) {
                    $cat_name = "Uncatagorized";
                    } else {
                    } */

        $cat_name = 'Uncatagorized';

        $values = "'" . $now . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precioWoo'] . "',";
        $values .= "'" . $decodedObj['producto'] . "',";
        $values .= $last_id . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";
        // $values .= "'" . $tmp["->name"] . "',";

        if (isset($decodedObj['talla'])) {
          $values .= "'" . $decodedObj['talla'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['corte'])) {
          $values .= "'" . $decodedObj['corte'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['tela'])) {
          $values .= "'" . $decodedObj['tela'] . "'";
        } else {
          $values .= "''";
        }

        $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
        $object['sql_ordenes_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2);

        // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
        if ($misProductos[$i] != '') {
          $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
          $myOrder = $localConnection->goQuery($sql_order);
          $object['myOrder_sql'] = $sql_order;
          $object['myOrder'] = $myOrder;

          // Obtenr ultimo ID del producto creado
          $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
          $last_id_ordenes_productos = intval($last_prod[0]['id']);

          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
          $myWooId = intval($decodedObj['cod']);
          if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
            $sql_lote_detalles = '';
            // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
            // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
            $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
            $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
          }
        }
      }
    }

    // GUARDAR LOTE

    // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
    $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
    $resultVerify = $localConnection->goQuery($sql_verify);

    $guardarLote = true;
    if (!empty($resultVerify)) {
      // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
      if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
        $guardarLote = false;
      }
    }

    $object['guardar_en_lote'] = $guardarLote;

    if ($guardarLote) {
      $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
      $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
    }

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql_metodos_pago = '';

    if (intval($arr['montoDolaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresZelle']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresPanama']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPunto']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPagomovil']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);

    // enviar email - obtener formato
    $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    $object['resultBuscar'] = $resultBuscar['object'];

    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $args['id_orden']);
    $testResp = $msgApi->sendMessage(ID_EMPRESA, $last_id, 'welcome', $resultBuscar);
    /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
        $object["sendMail"] = $result; */

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVO PRESUPUESTO
  $app->post('/presupuesto/nuevo', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson); */
    // $object["create_product_WC"] = $orderWC;
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO presupuestos (id_wp_order, responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $orderWC . ', ' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

    $object['nuevo_presupuesto_response'] = json_encode($localConnection->goQuery($sql));

    // Obtenr id de la orden creada
    $last = $localConnection->goQuery('SELECT MAX(_id) id FROM presupuestos');
    $last_id = intval($last[0]['id']);
    $object['last_id'] = $last_id;

    // Guardar orden vinculada
    /* if ($arr["vinculada"] != 0 || $arr["vinculada"] != '0') {
            $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr["vinculada"] . ", " . $last_id . ")";
            $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
        } */

    // Crear abono inicial de la orden

    /*
     * $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson["abono"] . "', '" . $newJson['descuento'] . "');";
     *  $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));
     */
    // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
    // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono

    // GUARDAR DATOS DE DISEÑO
    /*  $sql_diseno = "";
         if ($newJson["diseno_grafico"] == "true") {
             for ($i = 0; $i < intval($newJson["diseno_grafico_cantidad"]); $i++) {
                 $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
             }
         }

         if ($newJson["diseno_modas"] == "true") {
             for ($i = 0; $i < intval($newJson["diseno_modas_cantidad"]); $i++) {
                 $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
             }
         }

         $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno)); */

    // GUARDAR PRODUCTOS ASOCIADOS AL PRESUPUESTO
    $sql = 'SELECT _id';

    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        $cat_name = 'Uncatagorized';

        $values = "'" . $now . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precioWoo'] . "',";
        $values .= "'" . $decodedObj['producto'] . "',";
        $values .= $last_id . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";
        // $values .= "'" . $tmp["->name"] . "',";

        if (isset($decodedObj['talla'])) {
          $values .= "'" . $decodedObj['talla'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['corte'])) {
          $values .= "'" . $decodedObj['corte'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['tela'])) {
          $values .= "'" . $decodedObj['tela'] . "'";
        } else {
          $values .= "''";
        }

        $sql2 = 'INSERT INTO presupuestos_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
        $object['sql_presupuestos_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2);
      }
    }

    // enviar email - obtener formato
    // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    // $object["resultBuscar"] = $resultBuscar["object"];
    // $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
    // $object["sendMail"] = $result;

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE CUSTOM
  $app->post('/ordenes/nueva/custom_old', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    $count = count($misProductos);

    $arr['id_wp'] = intval(json_decode($newJson['id']));
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC;*/
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* DEBuG */
    // $object['newJson'] = $newJson;

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";
    $object['nueva_oreden_sql'] = $sql;

    $nueva_oreden_response = $localConnection->goQuery($sql);
    // $object['nueva_oreden_response'] = $nueva_oreden_response['message'];

    if (isset($nueva_oreden_response['status'])) {
      if ($nueva_oreden_response['status'] === 'error') {
        $object['orden_creada'] = false;
        $object['response'] = $nueva_oreden_response;
      }
    } else {
      $object['orden_creada'] = true;
      $object['STAUSSSS'] = 'estaus no reconocido';
      // Obtenr id de la orden creada
      $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
      $last_id = intval($last[0]['id']);
      $object['last_id'] = $last_id;

      // Guardar orden vinculada
      if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
        $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
        $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
      }

      // Crear abono inicial de la orden
      $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
      $object['sql_abonos'] = $sql;
      $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

      // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
      // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
      if (floatval($newJson['abono']) > 0) {
        // $object['sales_commission_ISSET'][] = $arg["sales_commission"];

        // BUSCAR COMISION DEL VENDEDOR
        $sql = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $newJson['responsable'];
        $respComision = $localConnection->goQuery($sql)[0];

        $comisionTipo = $respComision['comision_tipo'];

        $comisionFloat = floatval($respComision['comision']);
        $floatValue = floatval($comisionFloat);
        $comision = number_format($floatValue, 2);
        $object['sql'] = $sql;
        $object['comision'] = $comision;

        $pago_vendedor = floatval($newJson['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);
        $sql = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ", '" . $comisionTipo . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
        $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
        $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
        /* if ($arg["sales_commission"] === true) {
                  $object['sales_commission_ISSET'][] = true;
                  } else {
                      $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                  } */
      }  /*  else {
                   $object['sales_commission_ISSET'][] = false;
               } */

      // GUARDAR DATOS DE DISEÑO
      $sql_diseno = '';
      if ($newJson['diseno_grafico'] == true) {
        for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
          $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
        }
      }

      if ($newJson['diseno_modas'] == 'true') {
        for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
          $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
        }
      }

      // AHORA LAS ORDENES PUEDEN PASAR SIN DISEñO Y SE PUEDE ASIGNAR POSERIORMETE A UN DISEÑADOR DE SER NECESARIO EN EL MODULO DE ADMINISRACION->ASIGNACION DE DISEÑOS
      if ($sql_diseno != '') {
        $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));
      }

      // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
      $sql = 'SELECT _id';

      for ($i = 0; $i <= $count; $i++) {
        if (isset($misProductos[$i])) {
          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          $decodedObj = $misProductos[$i];

          /* $woo = new WooMe();
                  $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                  $tmp = json_decode($data_category);
                  $cat_name = $tmp->name; */
          /* if ($tmp->statusCode === 500) {
                      $cat_name = "Uncatagorized";
                      } else {
                      } */
          $sqlc = 'SELECT `nombre` FROM `categories` WHERE  _id = ' . $decodedObj['categoria'];
          $cat_name_base = $localConnection->goQuery($sqlc);
          $cat_name = $cat_name_base[0]['nombre'];
          // $cat_name = "Uncatagorized";

          $values = "'" . $now . "',";
          $values .= $decodedObj['precio'] . ',';
          $values .= "'" . $decodedObj['precio'] . "',";
          $values .= "'" . $decodedObj['producto'] . "',";
          $values .= $last_id . ',';
          $values .= $decodedObj['cod'] . ',';
          $values .= $decodedObj['cantidad'] . ',';
          $values .= $decodedObj['categoria'] . ',';
          $values .= "'" . $cat_name . "',";
          // $values .= "'" . $tmp["->name"] . "',";

          if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
            $values .= intval($decodedObj['talla']) . ',';
          } else {
            $values .= 'NULL,';
          }

          if (isset($decodedObj['corte'])) {
            $values .= "'" . $decodedObj['corte'] . "',";
          } else {
            $values .= "'',";
          }

          if (isset($decodedObj['tela'])) {
            $values .= "'" . $decodedObj['tela'] . "'";
          } else {
            $values .= "''";
          }

          $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
          $object['sql_ordenes_productos'] = $sql2;
          $object['producto_detalle'][] = $localConnection->goQuery($sql2);

          // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
          if ($misProductos[$i] != '') {
            $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
            $myOrder = $localConnection->goQuery($sql_order);
            $object['myOrder_sql'] = $sql_order;
            $object['myOrder'] = $myOrder;

            // Obtenr ultimo ID del producto creado
            $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
            $last_id_ordenes_productos = intval($last_prod[0]['id']);

            // PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();

            // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
            $myWooId = intval($decodedObj['cod']);
            if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
              $sql_lote_detalles = '';
              // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
              // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
              $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
              $object['lotes_detalles_sql'][$i] = $sql_lote_detalles;
              $object['lote_detalles_response'][$i] = $localConnection->goQuery($sql_lote_detalles);
            }
          }
        }
      }

      // GUARDAR LOTE

      // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
      $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
      $resultVerify = $localConnection->goQuery($sql_verify);

      $guardarLote = true;
      if (!empty($resultVerify)) {
        // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
        if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
          $guardarLote = false;
        }
      }

      $object['guardar_en_lote'] = $guardarLote;

      if ($guardarLote) {
        $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
        $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
      }

      // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
      $sql_metodos_pago = '';

      if (intval($arr['montoDolaresEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoDolaresZelle']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', ' " . $arr['montoDolaresZelleDetalle'] . " ');";
      }

      if (intval($arr['montoDolaresPanama']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', '" . $arr['montoDolaresPanamaDetalle'] . "');";
      }

      if (intval($arr['montoPesosEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoPesosTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', '" . $arr['montoPesosTransferenciaDetalle'] . "');";
      }

      if (intval($arr['montoBolivaresEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoBolivaresPunto']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', '');";
      }

      if (intval($arr['montoBolivaresPagomovil']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', '" . $arr['montoBolivaresPagomovilDetalle'] . "');";
      }

      if (intval($arr['montoBolivaresTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', '" . $arr['montoBolivaresTransferenciaDetalle'] . "');";
      }
      $object['sql_metodos_pago'] = $sql_metodos_pago;

      if ($sql_metodos_pago != '') {
        $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);
      }

      // enviar email - obtener formato
      // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
      // $object["resultBuscar"] = $resultBuscar["object"];
      /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
          $object["sendMail"] = $result; */

      // enviar WhatsApp al cliente
      $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
      $object['resultBuscar'] = $resultBuscar['object'];

      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $last_id);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $last_id, 'welcome', $resultBuscar);
    }

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTUALIZR ORDEN DE LA FILA DE PRODUCCIÓN
  $app->post('/ordenes/actualizar-fila', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    $sql = 'UPDATE ordenes_fila_orden SET orden_fila = ' . $data['orden_fila'] . ' WHERE id_orden = ' . $data['id_orden'] . ';';
    $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM ordenes_fila_orden ORDER BY orden_fila ASC';
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // EDITAR UNA ORDEN EXISTENTE
  $app->post('/ordenes/nueva/custom/edit', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $localConnection = new LocalDB();
    $object = [];  // Objeto de respuesta

    // 1. OBTENER EL ID DE LA ORDEN A EDITAR. ¡ESTO ES CRÍTICO!
    // El frontend DEBE enviar 'id_orden_edit' en el payload.
    if (!isset($newJson['id_orden_edit']) || empty($newJson['id_orden_edit'])) {
      $object['response']['status'] = 'error';
      $object['response']['message'] = 'No se proporcionó el ID de la orden para editar.';
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_orden_a_editar = intval($newJson['id_orden_edit']);

    // 2. PROCESAR DATOS DEL PAYLOAD (similar al endpoint original)
    $arr = [];
    $arr['id_wp'] = $newJson['id'];
    $arr['fechaEntrega'] = $newJson['fechaEntrega'];
    $arr['obs'] = addslashes($newJson['obs']);
    $arr['total'] = floatval($newJson['total']);  // Nuevo total recalculado en el frontend
    $arr['abono'] = floatval($newJson['abono']);  // ¡IMPORTANTE! Este debe ser SOLO el nuevo abono, no el total histórico.
    $arr['descuento'] = floatval($newJson['descuento']);  // Descuento total actualizado
    $arr['descuentoDetalle'] = $newJson['descuentoDetalle'];
    $arr['responsable'] = intval($newJson['responsable']);
    $arr['sales_commission'] = ($newJson['sales_commission'] === 'true');
    $arr['tasa_dolar'] = floatval($newJson['tasa_dolar']);
    $arr['tasa_peso'] = floatval($newJson['tasa_peso']);
    $nuevos_productos = json_decode($newJson['productos'], true);

    // 3. MANEJO DE PRODUCTOS (INSERT, UPDATE, DELETE)
    // 3.1. Obtener productos actuales de la base de datos para comparar
    $sql_productos_actuales = "SELECT _id, _id cod, cantidad, precio_unitario, talla, tela, corte, id_products_attributes FROM ordenes_productos WHERE id_orden = {$id_orden_a_editar}";
    $productos_actuales_db = $localConnection->goQuery($sql_productos_actuales);

    $map_productos_actuales = [];
    foreach ($productos_actuales_db as $p_actual) {
      // Usamos el ID del registro como clave para una búsqueda fácil
      $map_productos_actuales[$p_actual['_id']] = $p_actual;
    }

    // 3.2. Crear un mapa de los productos que llegan del frontend
    $map_productos_nuevos = [];
    foreach ($nuevos_productos as $p_nuevo) {
      // Si el producto tiene un '_id', es uno existente. Si no, es nuevo.
      if (isset($p_nuevo['_id']) && !empty($p_nuevo['_id'])) {
        $map_productos_nuevos[$p_nuevo['_id']] = $p_nuevo;
      } else {
        // Para los nuevos, los agregamos a un array separado para insertarlos luego.
        $productos_a_insertar[] = $p_nuevo;
      }
    }

    // 3.3. Identificar y ELIMINAR productos
    foreach ($map_productos_actuales as $id_actual => $p_actual) {
      if (!isset($map_productos_nuevos[$id_actual])) {
        // Este producto ya no está en la lista del frontend, hay que borrarlo.
        $sql_delete_prod = "DELETE FROM ordenes_productos WHERE _id = {$id_actual}";
        $localConnection->goQuery($sql_delete_prod);
        // También borrar sus detalles de lote
        $sql_delete_lote = "DELETE FROM lotes_detalles WHERE id_ordenes_productos = {$id_actual}";
        $localConnection->goQuery($sql_delete_lote);
      }
    }

    // 3.4. Identificar y ACTUALIZAR productos existentes
    foreach ($map_productos_nuevos as $id_nuevo => $p_nuevo) {
      if (isset($map_productos_actuales[$id_nuevo])) {
        $p_actual = $map_productos_actuales[$id_nuevo];
        // Comparamos si algo cambió para evitar updates innecesarios
        // Usamos el operador de fusión de null (??) para evitar errores de "Undefined index"
        if (
          ($p_actual['cantidad'] ?? null) != ($p_nuevo['cantidad'] ?? null) ||
          ($p_actual['precio_unitario'] ?? null) != ($p_nuevo['precio'] ?? null) ||
          ($p_actual['talla'] ?? null) != ($p_nuevo['talla'] ?? null) ||
          ($p_actual['tela'] ?? null) != ($p_nuevo['tela'] ?? null) ||
          ($p_actual['corte'] ?? null) != ($p_nuevo['corte'] ?? null) ||
          ($p_actual['id_products_attributes'] ?? null) != ($p_nuevo['atributo'] ?? null)
        ) {
          $sql_update_prod = "UPDATE ordenes_productos SET
                    cantidad = {$p_nuevo['cantidad']},
                    precio_unitario = {$p_nuevo['precio']},
                    corte = '{$p_nuevo['corte']}',
                    id_size = " . (isset($p_nuevo['talla']) && !is_null($p_nuevo['talla']) ? intval($p_nuevo['talla']) : 'NULL') . ',
                    talla = (SELECT nombre FROM sizes WHERE _id = ' . (isset($p_nuevo['talla']) && !is_null($p_nuevo['talla']) ? intval($p_nuevo['talla']) : 'NULL') . '),
                    id_tela = ' . (isset($p_nuevo['tela']) && !is_null($p_nuevo['tela']) ? intval($p_nuevo['tela']) : 'NULL') . ',
                    tela = (SELECT tela FROM catalogo_telas WHERE _id = ' . (isset($p_nuevo['tela']) && !is_null($p_nuevo['tela']) ? intval($p_nuevo['tela']) : 'NULL') . '),
                    id_products_attributes = ' . (isset($p_nuevo['atributo']) ? intval($p_nuevo['atributo']) : 'NULL') . "
                    WHERE _id = {$id_nuevo}";
          $localConnection->goQuery($sql_update_prod);
        }
      }
    }

    // 3.5. INSERTAR nuevos productos
    if (!empty($productos_a_insertar)) {
      foreach ($productos_a_insertar as $decodedObj) {
        // Reutilizamos la lógica de inserción del endpoint original
        $cat_name = 'Uncatagorized';  // Valor por defecto

        $values = "'" . date('Y-m-d H:i:s') . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precio'] . "',";  // precio_woo
        $values .= "'" . addslashes($decodedObj['producto']) . "',";
        $values .= $id_orden_a_editar . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";

        // Talla
        if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
          $id_talla = intval($decodedObj['talla']);
          $values .= $id_talla . ',';
          $values .= "(SELECT nombre FROM sizes WHERE _id = {$id_talla}),";
        } else {
          $values .= 'NULL, NULL,';
        }

        // Corte
        $values .= (isset($decodedObj['corte']) ? "'" . $decodedObj['corte'] . "'," : "'',");

        // Tela
        if (isset($decodedObj['tela']) && !is_null($decodedObj['tela']) && $decodedObj['tela'] !== '') {
          $id_tela = intval($decodedObj['tela']);
          $values .= $id_tela . ',';
          $values .= "(SELECT tela FROM catalogo_telas WHERE _id = {$id_tela})";
        } else {
          $values .= "NULL, ''";
        }

        $id_products_attributes = (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo'])) ? intval($decodedObj['atributo']) : 'NULL';

        $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ' . $id_products_attributes . ')';

        $res_insert = $localConnection->goQuery($sql2);
        $object['sql_insert_new_product'] = $sql2;

        /* $response->getBody()->write(json_encode($res_insert));
        $localConnection->disconnect();

        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200); */

        // $last_id_ordenes_productos = $res_insert['insert_id'];

        // Lógica para insertar en lotes_detalles para el nuevo producto
        // ... (copiar la lógica de lotes_detalles del endpoint original) ...
      }
    }

    // 4. ACTUALIZAR LA ORDEN PRINCIPAL
    // Primero, obtener el abono actual para sumarle el nuevo
    $sql_abono_actual = "SELECT pago_abono FROM ordenes WHERE _id = {$id_orden_a_editar}";
    $res_abono = $localConnection->goQuery($sql_abono_actual);
    $abono_historico = $res_abono[0]['pago_abono'];
    $nuevo_abono_total = $abono_historico + $arr['abono'];

    // Se elimina el campo `observaciones` de la actualización principal
    $sql_update_orden = 'UPDATE ordenes SET
        pago_total = ' . $arr['total'] . ',
        pago_abono = ' . $nuevo_abono_total . ',
        pago_descuento = ' . $arr['descuento'] . ",
        fecha_entrega = '" . $arr['fechaEntrega'] . "'
        WHERE _id = {$id_orden_a_editar}";
    $localConnection->goQuery($sql_update_orden);
    $object['sql_update_orden'] = $sql_update_orden;

    // NUEVO: Lógica para insertar o actualizar las observaciones en la tabla dedicada
    $sql_check_obs = "SELECT _id FROM ordenes_observaciones WHERE id_orden = {$id_orden_a_editar}";
    $obs_existente = $localConnection->goQuery($sql_check_obs);

    if (empty($obs_existente)) {
      $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$id_orden_a_editar}, '{$arr['obs']}')";
    } else {
      $sql_obs = "UPDATE ordenes_observaciones SET observaciones = '{$arr['obs']}' WHERE id_orden = {$id_orden_a_editar}";
    }
    $localConnection->goQuery($sql_obs);
    $object['sql_observaciones'] = $sql_obs;

    // 5. REGISTRAR NUEVOS ABONOS Y COMISIONES (Solo sobre el nuevo pago)
    $myDate = new CustomTime();
    $now = $myDate->today();

    if (floatval($arr['abono']) > 0) {
      // Crear registro del nuevo abono
      $sql_abono = "INSERT INTO abonos (moment, id_orden, id_empleado, abono) VALUES ('" . $now . "', '" . $id_orden_a_editar . "',  '" . $arr['responsable'] . "', '"
        . $arr['abono'] . "');";
      $localConnection->goQuery($sql_abono);
      $object['sql_nuevo_abono'] = $sql_abono;

      // Calcular comisión SOLO sobre el nuevo abono
      if ($arr['sales_commission'] === true) {
        $sql_comision = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $arr['responsable'];
        $respComision = $localConnection->goQuery($sql_comision)[0];
        $comisionFloat = floatval($respComision['comision']);
        $comision = number_format($comisionFloat, 2);

        $pago_vendedor = floatval($arr['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);

        $sql_pago = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ",
       '" . $respComision['comision_tipo'] . "', '" . $id_orden_a_editar . "',  '" . $arr['responsable'] . "', '" . $pago_vendedor . "', 'Abono a orden', 'aprobado')";
        $object['sql_pago_response'] = $localConnection->goQuery($sql_pago);

        $object['sql_pago'] = $sql_pago;
        $object['pago_a_vendedor_por_abono'] = 'SI hubo comisión por el nuevo abono.';
      }
    }

    // 6. GUARDAR NUEVOS METODOS DE PAGO (La lógica original sirve, ya que solo inserta lo que recibe)
    $sql_metodos_pago = '';
    // ... Aquí va exactamente el mismo bloque de código que verifica cada 'monto...' y crea los INSERTs ...
    // Ejemplo:
    if (intval($newJson['montoDolaresEfectivo']) > 0) {
      $monto = intval($newJson['montoDolaresEfectivo']);
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $id_orden_a_editar . "', 'Dólares', 'Efectivo',
       '{$monto}', '1', '');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('{$monto}', 'Dólares', 1, 'abono_orden', '" . $arr['responsable']
        . "', 'Abono a Orden #{$id_orden_a_editar}');";
    }
    // ... Repetir para Zelle, Pesos, Bolívares, etc.

    if ($sql_metodos_pago != '') {
      $object['metodos_pago_response'] = $localConnection->goQuery($sql_metodos_pago);
    }

    $object['response']['status'] = 'success';
    $object['response']['message'] = 'La orden número ' . $id_orden_a_editar . ' ha sido actualizada correctamente.';

    $response->getBody()->write(json_encode($object));
    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE CUSTOM CON VALIDACIONES
  $app->post('/ordenes/nueva/custom', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    if (is_null(json_decode($newJson['email']))) {
      $arr['email'] = json_decode($newJson['email']);
    } else {
      $arr['email'] = strtolower(json_decode($newJson['email']));
    }
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC;*/
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* DEBuG */
    // $object['newJson'] = $newJson;

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, `status` ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";
    $nueva_oreden_response = $localConnection->goQuery($sql);
    $object['nueva_oreden_sql'] = $sql;

    // $object['nueva_oreden_response'] = $nueva_oreden_response['message'];

    if (isset($nueva_oreden_response['status'])) {
      if ($nueva_oreden_response['status'] === 'error') {
        $object['orden_creada'] = false;
        $object['response'] = $nueva_oreden_response;
        $object['response']['status'] = 'error';
      } else {
        $object['response']['status'] = 'success';
        $object['response']['message'] = 'Vrifique la creación de la orden';
      }
    } else {
      $object['orden_creada'] = true;
      // Obtenr id de la orden creada
      // $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
      $last = $nueva_oreden_response['insert_id'];
      $last_id = intval($last);

      // NUEVO: Guardar las observaciones en la tabla dedicada
      if (!empty($newJson['obs'])) {
        $observaciones = addslashes($newJson['obs']);
        $sql_obs = "INSERT INTO ordenes_observaciones (id_orden, observaciones) VALUES ({$last_id}, '{$observaciones}')";
        $object['sql_observaciones'] = $sql_obs;
        $localConnection->goQuery($sql_obs);
      }

      // Crear registro en la fila de producción
      $lastOrdenFila = $localConnection->goQuery('SELECT MAX(orden_fila) AS max FROM ordenes_fila_orden;');
      $lastOrdenFila = $lastOrdenFila[0]['max'] + 1;

      $sql = "INSERT INTO `ordenes_fila_orden`(`id_orden`, `orden_fila`)  VALUES ($last_id, $lastOrdenFila)";
      $object['sql_orden_fila'] = $sql;
      $response_last_fila = $localConnection->goQuery($sql);

      // Guardar orden vinculada
      if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
        $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
        $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
      }

      // Crear abono inicial de la orden
      $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
      $object['sql_abonos'] = $sql;
      $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

      // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
      // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
      if (floatval($newJson['abono']) > 0) {
        // $object['sales_commission_ISSET'][] = $arg["sales_commission"];

        // BUSCAR COMISION DEL VENDEDOR
        $sql = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $newJson['responsable'];
        $respComision = $localConnection->goQuery($sql)[0];

        $comisionTipo = $respComision['comision_tipo'];

        $comisionFloat = floatval($respComision['comision']);
        $floatValue = floatval($comisionFloat);
        $comision = number_format($floatValue, 2);
        $object['sql'] = $sql;
        $object['comision'] = $comision;

        $pago_vendedor = floatval($newJson['abono']) * $comision / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);
        $sql = "INSERT INTO pagos (moment, comision, comision_tipo, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', " . $comision . ", '" . $comisionTipo . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
        $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
        $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
        /* if ($arg["sales_commission"] === true) {
                  $object['sales_commission_ISSET'][] = true;
                  } else {
                      $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                  } */
      }  /*  else {
                   $object['sales_commission_ISSET'][] = false;
               } */

      /* // GUARDAR DATOS DE DISEÑO
      $sql_diseno = '';
      if ($newJson['diseno_grafico'] == true) {
          for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
              $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
          }
      }

      if ($newJson['diseno_modas'] == 'true') {
          for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
              $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
          }
      }

      // AHORA LAS ORDENES PUEDEN PASAR SIN DISEñO Y SE PUEDE ASIGNAR POSERIORMETE A UN DISEÑADOR DE SER NECESARIO EN EL MODULO DE ADMINISRACION->ASIGNACION DE DISEÑOS
      if ($sql_diseno != '') {
          $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));
      } */

      // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
      $sql = 'SELECT _id';

      for ($i = 0; $i <= $count; $i++) {
        if (isset($misProductos[$i])) {
          // PREPARAR FECHAS
          $myDate = new CustomTime();
          $now = $myDate->today();

          $decodedObj = $misProductos[$i];

          /* $woo = new WooMe();
                  $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                  $tmp = json_decode($data_category);
                  $cat_name = $tmp->name; */
          /* if ($tmp->statusCode === 500) {
                      $cat_name = "Uncatagorized";
                      } else {
                      } */
          /* $sqlc = 'SELECT `nombre` FROM `categories` WHERE  _id = ' . $decodedObj['categoria'];
           $cat_name_base = $localConnection->goQuery($sqlc);
           $object['CAT_sql'] = $sqlc;
           $object['CAT_response'] = $cat_name_base[0]['nombre'];

            if (empty($cat_name_base)) {
               $cat_name = 'Uncatagorized';
               } else {
                   $cat_name = $cat_name_base[0]['nombre'];
           } */
          $cat_name = 'Uncatagorized';

          $values = "'" . $now . "',";
          $values .= $decodedObj['precio'] . ',';
          $values .= "'" . $decodedObj['precio'] . "',";
          $values .= "'" . $decodedObj['producto'] . "',";
          $values .= $last_id . ',';
          $values .= $decodedObj['cod'] . ',';
          $values .= $decodedObj['cantidad'] . ',';
          $values .= $decodedObj['categoria'] . ',';
          $values .= "'" . $cat_name . "',";
          // $values .= "'" . $tmp["->name"] . "',";

          // --- INICIO: Corrección para guardar ID y Nombre de la Talla ---
          if (isset($decodedObj['talla']) && !is_null($decodedObj['talla']) && $decodedObj['talla'] !== '') {
            $id_talla = intval($decodedObj['talla']);
            $values .= $id_talla . ',';  // Para la columna id_size
            $values .= "(SELECT nombre FROM sizes WHERE _id = {$id_talla}),";  // Para la columna talla
          } else {
            $values .= 'NULL, NULL,';  // Para id_size y talla
          }
          // --- FIN: Corrección ---

          if (isset($decodedObj['corte'])) {
            $values .= "'" . $decodedObj['corte'] . "',";
          } else {
            $values .= "'',";
          }

          if (isset($decodedObj['tela'])) {
            $values .= "'" . $decodedObj['tela'] . "',";  // ID de la tela para la columna `id_tela`
            $values .= '(SELECT tela FROM catalogo_telas WHERE _id = ' . intval($decodedObj['tela']) . ')';  // Nombre para la columna `tela`
          } else {
            $values .= "NULL, ''";  // Valores por defecto si no hay tela
          }

          // Manejar el nuevo atributo. Si no está presente o está vacío, se insertará NULL.
          $id_products_attributes = 'NULL';
          if (isset($decodedObj['atributo']) && !is_null($decodedObj['atributo']) && $decodedObj['atributo'] !== '') {
            // Asegurarse de que es un entero para seguridad
            $id_products_attributes = intval($decodedObj['atributo']);
          }

          $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, id_size, talla, corte, id_tela, tela, id_products_attributes) VALUES (' . $values . ', ' . $id_products_attributes . ')';
          $object['sql_ordenes_productos'] = $sql2;
          $object['producto_detalle'][] = $localConnection->goQuery($sql2);

          // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
          if ($misProductos[$i] != '') {
            $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
            $myOrder = $localConnection->goQuery($sql_order);
            $object['myOrder_sql'] = $sql_order;
            $object['myOrder'] = $myOrder;

            // Obtenr ultimo ID del producto creado
            $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
            $last_id_ordenes_productos = intval($last_prod[0]['id']);

            // PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();

            // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
            $myWooId = intval($decodedObj['cod']);

            // VERIFICAR SI ES UN PRODCTO FISICO
            $sqlpv = "SELECT fisico FROM products WHERE _id = {$myWooId} AND fisico = 1";
            $resultProductoFisico = $localConnection->goQuery($sqlpv);

            if (!empty($resultProductoFisico)) {
              // BUSCAR DEPARTAMETNOS DEL PROCESO DE PRODUCCIÓN
              $sqlpd = 'SELECT _id, departamento FROM departamentos WHERE asignar_numero_de_paso = 1 ORDER BY asignar_numero_de_paso ASC';
              $resultDepartamentos = $localConnection->goQuery($sqlpd);

              $sql_lote_detalles = '';

              foreach ($resultDepartamentos as $departamento) {
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `id_departamento`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', {$departamento['_id']}, '{$departamento['departamento']}');";
              }

              $object['lotes_detalles_sql'][$i] = $sql_lote_detalles;
              $object['lote_detalles_response'][$i] = $localConnection->goQuery($sql_lote_detalles);
            }
          }
        }
      }

      // GUARDAR LOTE

      // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
      $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
      $resultVerify = $localConnection->goQuery($sql_verify);

      $guardarLote = true;
      if (!empty($resultVerify)) {
        // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
        if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
          $guardarLote = false;
        }
      }

      $object['guardar_en_lote'] = $guardarLote;

      if ($guardarLote) {
        $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
        $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
      }

      // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
      $sql_metodos_pago = '';

      if (intval($arr['montoDolaresEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoDolaresZelle']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', ' " . $arr['montoDolaresZelleDetalle'] . " ');";
      }

      if (intval($arr['montoDolaresPanama']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', '" . $arr['montoDolaresPanamaDetalle'] . "');";
      }

      if (intval($arr['montoPesosEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoPesosTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', '" . $arr['montoPesosTransferenciaDetalle'] . "');";
      }

      if (intval($arr['montoBolivaresEfectivo']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', '');";
        $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
      }

      if (intval($arr['montoBolivaresPunto']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', '');";
      }

      if (intval($arr['montoBolivaresPagomovil']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', '" . $arr['montoBolivaresPagomovilDetalle'] . "');";
      }

      if (intval($arr['montoBolivaresTransferencia']) > 0) {
        $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', '" . $arr['montoBolivaresTransferenciaDetalle'] . "');";
      }
      $object['sql_metodos_pago'] = $sql_metodos_pago;

      if ($sql_metodos_pago != '') {
        $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);
      }

      // enviar email - obtener formato
      // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
      // $object["resultBuscar"] = $resultBuscar["object"];
      /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
          $object["sendMail"] = $result; */

      // enviar WhatsApp al cliente
      /* $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
      $object['resultBuscar'] = $resultBuscar['object'];

      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $last_id);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $last_id, 'welcome', $resultBuscar);

      $object['ws_response'] = $testResp; */

      $object['response']['status'] = 'success';
      $object['response']['message'] = 'La orden número ' . $last_id . ' ha sido creada correctamente';
    }

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CREAR NUEVA ORDEN ANTES DE SPORT
  $app->post('/ordenes/nueva/sport', function (Request $request, Response $response, $arg) {
    $newJson = $request->getParsedBody();
    $misProductos = json_decode($newJson['productos'], true);
    $localConnection = new LocalDB();

    // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
    $count = count($misProductos);

    $arr['id_wp'] = json_decode($newJson['id']);
    $arr['nombre'] = json_decode($newJson['nombre']);
    $arr['vinculada'] = json_decode($newJson['vinculada']);
    $arr['apellido'] = json_decode($newJson['apellido']);
    $arr['cedula'] = json_decode($newJson['cedula']);
    $arr['telefono'] = json_decode($newJson['telefono']);
    $arr['email'] = json_decode($newJson['email']);
    $arr['direccion'] = json_decode($newJson['direccion']);
    $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
    $arr['misProductos'] = json_decode($newJson['productos'], true);
    $arr['obs'] = json_decode($newJson['obs']);
    $arr['total'] = json_decode($newJson['total']);
    $arr['abono'] = json_decode($newJson['abono']);
    $arr['descuento'] = json_decode($newJson['descuento']);
    $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
    $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
    $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
    $arr['responsable'] = json_decode($newJson['responsable']);
    $arr['sales_commission'] = json_decode($newJson['sales_commission']);

    // RECIBIR LOS METODOS DE PAGO
    $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
    $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
    $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
    $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
    $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
    $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
    $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
    $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
    $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
    $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
    $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
    $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
    $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
    $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
    $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
    $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
    $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
    $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
    $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
    $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

    $arr['hoy'] = date('d/m/Y');
    // $object["arr"] = $arr;
    $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear nueva orden en Woocommerce
    $orderWC = 0;
    /* $woo = new WooMe();
        $orderWC = $woo->createOrder($arr, $newJson);
        $object["create_product_WC"] = $orderWC;*/
    /* $response->getBody()->write(json_encode($object));
        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */
    /* *** */

    /* Enviar email al cliente */
    // $woo = new WooMe();
    // Por ejemplo:
    // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

    /* Craer orden en nunesys */
    $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status, tipo ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'entregada', 'sport')";

    $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

    // Obtenr id de la orden creada
    $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
    $last_id = intval($last[0]['id']);
    $object['last_id'] = $last_id;

    // Guardar orden vinculada
    if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
      $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
      $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
    }

    // Crear abono inicial de la orden
    $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
    $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

    // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
    // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
    if (floatval($newJson['abono']) > 0) {
      // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
      $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
      $pago_vendedor = number_format($pago_vendedor, 2);
      $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
      $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
      $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
      /* if ($arg["sales_commission"] === true) {
                $object['sales_commission_ISSET'][] = true;
                } else {
                    $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                } */
    }  /*  else {
    $object['sales_commission_ISSET'][] = false;
    } */

    // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
    $sql = 'SELECT _id';

    for ($i = 0; $i <= $count; $i++) {
      if (isset($misProductos[$i])) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $decodedObj = $misProductos[$i];

        $cat_name = 'Uncatagorized';

        $values = "'" . $now . "',";
        $values .= $decodedObj['precio'] . ',';
        $values .= "'" . $decodedObj['precioWoo'] . "',";
        $values .= "'" . $decodedObj['producto'] . "',";
        $values .= $last_id . ',';
        $values .= $decodedObj['cod'] . ',';
        $values .= $decodedObj['cantidad'] . ',';
        $values .= $decodedObj['categoria'] . ',';
        $values .= "'" . $cat_name . "',";
        // $values .= "'" . $tmp["->name"] . "',";

        if (isset($decodedObj['talla'])) {
          $values .= "'" . $decodedObj['talla'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['corte'])) {
          $values .= "'" . $decodedObj['corte'] . "',";
        } else {
          $values .= "'',";
        }

        if (isset($decodedObj['tela'])) {
          $values .= "'" . $decodedObj['tela'] . "'";
        } else {
          $values .= "''";
        }

        $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
        $object['sql_ordenes_productos'] = $sql2;
        $object['producto_detalle'][] = $localConnection->goQuery($sql2);

        // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
        /* if ($misProductos[$i] != '') {
            $sql_order = "SELECT * FROM ordenes WHERE _id = " . $last_id;
            $myOrder = $localConnection->goQuery($sql_order);
            $object['myOrder_sql'] = $sql_order;
            $object['myOrder'] = $myOrder;

    // Obtenr ultimo ID del producto creado
                        $last_prod = $localConnection->goQuery("SELECT MAX(_id) id FROM ordenes_productos");
                        $last_id_ordenes_productos = intval($last_prod[0]['id']);

    // PREPARAR FECHAS
                        $myDate = new CustomTime();
                        $now = $myDate->today();

    // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
                        $myWooId = intval($decodedObj['cod']);
                        if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
                            $sql_lote_detalles = "";
    // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
    // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
                            $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
                            $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
                            $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
                        }
                    } */
      }
    }

    // GUARDAR LOTE

    /* // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
                $sql_verify = "SELECT category_name FROM ordenes_productos WHERE id_orden = " . $last_id;
                $resultVerify = $localConnection->goQuery($sql_verify);

                $guardarLote = true;
                if (!empty($resultVerify)) {
        // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
                    if (count($resultVerify) === 1 && $resultVerify[0]["category_name"] === "Diseños") {
                        $guardarLote = false;
                    }
                }

    $object["guardar_en_lote"] = $guardarLote;

    if ($guardarLote) {
        $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date("Y-m-d") . "', " . $last_id . ", " . $last_id . ", 'producción')";
        $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
    } */

    // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
    $sql_metodos_pago = '';

    if (intval($arr['montoDolaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresZelle']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoDolaresPanama']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoPesosTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresEfectivo']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

      $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPunto']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresPagomovil']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    if (intval($arr['montoBolivaresTransferencia']) > 0) {
      $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
    }

    $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);

    // enviar email - obtener formato
    $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
    $object['resultBuscar'] = $resultBuscar['object'];
    /*  $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
        $object["sendMail"] = $result; */

    $response->getBody()->write(json_encode($object));

    $localConnection->disconnect();

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // FIN CREAR NUEVA ORDEN

  // CAMBIAR ESTATUS DE LA REVISIÓN
  $app->post('/comercializacion/revisiones-estatus/{estatus}/{id_revision}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new localDB();

    $sql = "UPDATE revisiones SET estatus = '" . $args['estatus'] . "' WHERE _id = " . $args['id_revision'];
    $localConnection->goQuery($sql);

    // BUSCAR EL ID DE LA ORDEN EN `revisiones`
    // $sql = "SELECT id_orden FROM revisiones WHERE _id = " . $args["id_revision"];
    // $miRevision = $localConnection->goQuery($sql);
    // $miRevision = $args['id_revision'];

    // CON EL ID DE LA ORDEN BUSCAMOS EL ID DEL DISEÑADOR EN `disenos` `revisiones`
    $sql = 'SELECT id_orden, id_empleado FROM revisiones WHERE _id = ' . $args['id_revision'];
    $miDiseno = $localConnection->goQuery($sql);

    // VERIFICAR PAGO EXISTENTE
    $sqlPago = "SELECT count(_id) exist FROM pagos WHERE detalle = 'Diseño' AND id_orden = " . $miDiseno[0]['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
    $object['pago_exist'] = $localConnection->goQuery($sqlPago)[0];

    // ELIMINAR PAGO A DISEñADOR POR RECHAZO DE PROPUESTA
    if ($args['estatus'] === 'Rechazado') {
      $sql = "UPDATE revisiones SET estatus = 'Rechazado' WHERE _id = " . $args['id_revision'];
      $resultUpdateRevisiones = $localConnection->goQuery($sql);
    }

    // APROBAR PROPUESTA
    if ($args['estatus'] === 'Aprobado') {
      $estatusTerminado = 1;
      $sql = 'UPDATE disenos SET terminado = ' . $estatusTerminado . ' WHERE id_orden = ' . $miDiseno[0]['id_orden'] . ';';
      $sql .= "UPDATE ordenes SET status = 'activa' WHERE _id = " . $args['id_orden'] . ';';
      $miRevision = $localConnection->goQuery($sql);
      $object['sql_revision'] = $sql;

      /* // Buscar el id_woo
      $sqlwoo = 'SELECT id_woo FROM ordenes_productos WHERE id_category = 17 AND id_orden = ' . $miDiseno[0]['id_orden'];
      $idWoo = $localConnection->goQuery($sqlwoo); */

      // BUSCAR DATOS DE LA REVISON
      $sql = 'SELECT id_empleado, id_product FROM revisiones WHERE _id = ' . $args['id_revision'];
      $id_tmp = $localConnection->goQuery($sql);
      $id_disenador = $id_tmp[0]['id_empleado'];
      $id_product = $id_tmp[0]['id_product'];

      // BUSCAR DISEÑOS ASIGANDO PARA UBICAR EL MONTO DE LA COMISIÓN
      $sql = 'SELECT
                    pro._id id_porducto,
                    pro.product,
                    pro.comision
                FROM
                    disenos dis
                LEFT JOIN revisiones rev ON
                    rev.id_diseno = dis._id
                LEFT JOIN products pro ON
                    dis.id_product = pro._id
                WHERE
                    rev._id = ' . $args['id_revision'] . ' AND rev.id_orden = ' . $args['id_orden'] . ' AND dis.id_empleado = ' . $miDiseno[0]['id_empleado'] . '
            ';
      $comision_tmp = $localConnection->goQuery($sql);

      if (empty($comision_tmp)) {
        $object['comision_diseno'] = 0;
        $comision = 0;
      } else {
        $comision = $comision_tmp[0]['comision'];
        // Verificar si el pago existe
        /* $sql = "SELECT _id FROM pagos WHERE detalle = 'Diseño' AND id_empleado = " . $miDiseno[0]['id_empleado'] . ' AND id_orden = ' . $args['id_orden'];
        $object['sql_pago_exist'] = $sql;
        $miPago = $localConnection->goQuery($sql); */

        /*$object['id_woo'] = $idWoo[0]['id_woo'];

         // Buscar en WooMe la comision asociada a el producto $idWoo
        $woo = new WooMe();
        $woomeResponse = $woo->getProductById($idWoo[0]['id_woo']);

        // $object["woo-response"] = json_encode($woomeResponse);
        if (isset($woomeResponse->attributes[0]->options[0])) {
            $object['comision_diseno'] = json_encode($woomeResponse->attributes[0]->options[0]);
        } else {
            $object['comision_diseno'] = 0;
        }

        if (empty($woomeResponse->attributes)) {
            $comision = 0;
        } else {
            $comision = $woomeResponse->attributes[0]->options[0];
        } */

        $sql = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miDiseno[0]['id_empleado'];
        $respComision = $localConnection->goQuery($sql);
        $comision = $respComision[0]['comision'];
        $comision_tipo = $respComision[0]['comision_tipo'];

        if ($comision_tipo === 'variable') {
          // Buscar la comision en el producto
          $sql = 'SELECT comision FROM products WHERE _id = ' . $id_product;
          $respComisionProd = $localConnection->goQuery($sql);
          $comision = $respComisionProd[0]['comision'];
        } else {
          // Preparar la comision para guardarla
          $comisionFloat = floatval($respComision);
          $floatValue = floatval($comisionFloat);
          $comision = number_format($floatValue, 2);
        }

        // $comision_disenador = number_format(floatval($comision, 2));

        /* if (empty($miPago)) {
            $sqlPago = 'INSERT INTO pagos (cantidad, comision, comision_tipo, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, ' . $comision . ", '" . $comision_tipo . "',  " . $args['id_orden'] . ", 'aprobado' , " . $comision . ', ' . $miDiseno[0]['id_empleado'] . ", 'Diseño');";
            $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
        } else {
            // UPDATE pagos
            $sqlPago = 'UPDATE pagos SET monto_pago = ' . $comision . ' WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
            $object['sqlPago'] = $sqlPago;
            $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
        } */
        $sqlPago = 'INSERT INTO pagos (cantidad, comision, comision_tipo, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, ' . $comision . ", '" . $comision_tipo . "',  " . $args['id_orden'] . ", 'aprobado' , " . $comision . ', ' . $miDiseno[0]['id_empleado'] . ", 'Diseño');";
        $object['sql_pago'] = $sqlPago;
        $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR DETALLES DE LA REVISIÓN
  $app->post('/comercializacion/revisiones-detalles/{id_revision}', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    $sql = "UPDATE revisiones SET detalles = '" . htmlspecialchars($data['detalles']) . "' WHERE _id = " . $args['id_revision'];
    $object['revisiones'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REVISAR REVISIONES PENDIENTES
  $app->get('/comercializacion/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    $sql = 'SELECT acceso FROM  empresas_usuarios  WHERE id_usuario = ' . $args['id_empleado'];
    $miEmpleado = $localConnection->goQuery($sql);

    $localConnection = new localDB();

    if ($miEmpleado[0]['acceso']) {
      // Mostrar todos los registros de revisiones
      $sql = "SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
    } else {
      // Mostrar solo los registros del venededor
      $sql = 'SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id AND b.responsable = ' . $args['id_empleado'] . " WHERE b.responsable = '" . $args['id_empleado'] . "' AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
    }

    $object['revisiones'] = $localConnection->goQuery($sql);

    $object['total_revisiones'] = count($object['revisiones']);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ACTUALIZAR ORDEN DE LA FILA DE REPOSICIONES
  $app->post('/reposiciones/actualizar-fila', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new localDB();

    // Validar que los datos necesarios están presentes
    if (!isset($data['id_reposicion']) || !isset($data['orden_fila'])) {
      $response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos: id_reposicion u orden_fila.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'UPDATE ordenes_fila_reposiciones SET orden_fila = ' . intval($data['orden_fila']) . ' WHERE id_reposicion = ' . intval($data['id_reposicion']) . ';';
    $object['sql_update_fila_reposicion'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->get('/ordenes-observaciones/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // Se corrige la consulta para seleccionar el campo 'observaciones'
    $sql = "SELECT
            observaciones
        FROM
            ordenes_observaciones a
        WHERE
            a.id_orden = {$args['id_orden']}";

    $object = $localConnection->goQuery($sql);

    // Se añade JSON_UNESCAPED_UNICODE para asegurar que los caracteres especiales
    // dentro del HTML (como tildes o eñes) se envíen correctamente.
    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN ORDENES */

  /** LOTES */

  // Obtener departamento asignado al empleado
  $app->get('/empleado/asignado/{departamento}/{orden}/{item_id}', function (Request $request, Response $response, array $args) {
    // Verificar la asignacion
    $localConnection = new LocalDB();
    $sql = "SELECT id_empleado FROM lotes_detalles  WHERE id_orden = '" . $args['orden'] . "' AND id_ordenes_productos = '" . $args['item_id'] . "' AND departamento = '" . $args['departamento'] . "'";
    $object['id_empleado'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/lotes/en-proceso', function (Request $request, Response $response, array $args) {
    // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE
    $localConnection = new LocalDB();
    $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, 'TRAER DEL `ENDPOINT` DEDICADO' observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
    $object['items'] = $localConnection->goQuery($sql);

    // CREAR CAMPOS DE LA TABLA
    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'prioridad';
    $object['fields'][2]['label'] = 'Prioridad';

    $object['fields'][2]['key'] = 'paso';
    $object['fields'][2]['label'] = 'Progreso';

    $object['fields'][3]['key'] = 'inicio';
    $object['fields'][3]['label'] = 'Inicio';

    $object['fields'][4]['key'] = 'entrega';
    $object['fields'][4]['label'] = 'Entrega';

    $object['fields'][5]['key'] = 'vinculada';
    $object['fields'][5]['label'] = 'Vinculada';

    $object['fields'][6]['key'] = 'estatus';
    $object['fields'][6]['label'] = 'Estatus';

    $object['fields'][7]['key'] = 'detalles';
    $object['fields'][7]['label'] = 'Detalles';

    $object['fields'][8]['key'] = 'acciones';
    $object['fields'][8]['label'] = 'Acciones';

    $go = $object;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($go));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // VERIFICAR CANTIDAD ASIGNADA EN LOTES
  $app->get('/lotes/verificar-cantidad-asignada/{id_ordenes_productos}/{departamento}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // -> VERIFICAR EXISTENCIA DEL REGISTRO
    $sql = "SELECT _id FROM lotes_detalles WHERE id_ordenes_productos  = '" . $args['id_ordenes_productos'] . "' AND departamento = '" . $args['departamento'] . "' AND id_orden = " . $args['id_orden'];
    $object['data'] = $localConnection->goQuery($sql);

    // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE ???

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER DATOS PARA REPOSICIONES EN EL MODULO DE EMPLEADOS
  $app->get('/empleados/reposicion/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // ITEM
    $sql = "SELECT DISTINCT
            a._id AS orden,
            a._id AS vinculada,    
            a.cliente_nombre AS cliente,
            b.prioridad,
            b.paso,
            d.detalle detalle_supervisor,
            d.detalle_emisor,
            a.fecha_inicio AS inicio,
            a.fecha_entrega AS entrega,
            obs.observaciones AS detalles,
            a._id AS acciones,
            a.status AS estatus,
            c._id AS id_diseno,
            d.unidades,
            d.id_ordenes_productos,
            COALESCE(e.nombre, 'Sin asignar') AS disenador
            FROM
            ordenes a
            JOIN lotes b ON a._id = b.id_orden
            LEFT JOIN ordenes_observaciones obs ON obs.id_orden = a._id
            LEFT JOIN disenos c ON a._id = c.id_orden
            LEFT JOIN reposiciones d ON d.id_orden = a._id
            LEFT JOIN api_empresas.empresas_usuarios e ON e.id_usuario = (CASE WHEN c.id_empleado = 0 THEN 0 ELSE c.id_empleado END)
            WHERE a._id = " . $args['id_orden'] . " AND 
            (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera')
            -- AND (SELECT COUNT(_id) FROM lotes_detalles WHERE id_orden = a._id) > 0
            ORDER BY a._id DESC";
    $object['item']['data'] = $localConnection->goQuery($sql)[0];
    // DETALLES DE REPOSICIÓN
    $sql = 'SELECT
            r._id id_reposicion,
            r.id_orden,
            r.id_empleado,
            r.id_ordenes_productos,
            o.name producto,
            r.unidades,
            r.detalle detalle_supervisor,
            r.detalle_emisor detalle_empleado
        FROM
            reposiciones r
        LEFT JOIN ordenes_productos o ON r.id_ordenes_productos = o._id
        WHERE r.terminada = 0 AND r.id_orden = ' . $args['id_orden'];

    $object['item']['detalles_reposicion'] = $localConnection->goQuery($sql);

    // REPOSICION ORDENES PRODUCTOS
    $sql = 'SELECT
                b._id,
                b.id_orden,
                b._id item,
                b.id_woo cod,
                b.name producto,
                b.cantidad,
                b.talla,
                b.tela,
                b.corte,
                b.precio_unitario precio,
                b.precio_woo precioWoo
            FROM
                ordenes_productos b
            JOIN ordenes a ON
                a._id = b.id_orden
            JOIN products p ON p._id = b.id_woo
            WHERE a._id = ' . $args['id_orden'] . " AND 
                (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND p.fisico = 1
        ";
    $object['reposicion_ordenes_productos'] = $localConnection->goQuery($sql);

    /* // BUSCAR PASO ACTUAL EN EL LOTE
    $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
    $tmpPaso = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    if (!empty($tmpPaso)) {
      $object["paso"] = $tmpPaso[0]["paso"];
    } else {
      $object["paso"] = null;
    } */

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/empleados/reasignar', function (Request $request, Response $response, $args) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB();

    // BUSCAR NOMBRE DEL DEPARTAMENTO
    $sql = "SELECT departamento FROM departamentos WHERE _id = {$miEmpleado['id_departamento']}";
    $respDep = $localConnection->goQuery($sql);
    $nombreDepartamento = $respDep[0]['departamento'];

    // CALCULAR CANTIDAD DE UNIDADES SOLICITADAS
    $sql = "SELECT SUM(cantidad) total_cantidad FROM ordenes_productos WHERE id_orden = {$miEmpleado['id_orden']}";
    $total_cantidad = $localConnection->goQuery($sql)[0]['total_cantidad'];

    $values = "id_departamento ='" . $miEmpleado['id_departamento'] . "',";
    $values .= "departamento ='" . $nombreDepartamento . "',";
    $values .= "unidades_solicitadas ='$total_cantidad'";

    // ACTUALIZAR REGISTRO EN lotes_detalles
    $sql = 'UPDATE lotes_detalles SET ' . $values . " WHERE id_departamento = '" . $miEmpleado['id_departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'];
    $resultados = $localConnection->goQuery($sql);

    // VERIFICAR REGISTRO EN lotes_detalles_empleados_asigandos
    $sql2 = "SELECT _id cantida_registros FROM lotes_detalles_empleados_asignados WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']}";
    $resultados = $localConnection->goQuery($sql2);
    $object['sql_verificar'] = $sql;
    $object['result_verificar'] = $resultados;

    if (empty($resultados)) {
      $sql = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_departamento, id_empleado, procentaje_comision) VALUES ({$miEmpleado['id_orden']}, {$miEmpleado['id_departamento']}, {$miEmpleado['id_empleado']}, {$miEmpleado['porcentaje']})";
    } else {
      $sql = "UPDATE lotes_detalles_empleados_asignados SET id_empleado = {$miEmpleado['id_empleado']}, procentaje_comision = {$miEmpleado['porcentaje']}, id_departamento = {$miEmpleado['id_departamento']} WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']}";
    }
    $afectarEmpleado = $localConnection->goQuery($sql);
    $object['sql'] = $sql;
    $object['result'] = $afectarEmpleado;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/empleados/eliminar', function (Request $request, Response $response, $args) {
    $miEmpleado = $request->getParsedBody();
    $object['parsed_body'] = $miEmpleado;
    $localConnection = new LocalDB();

    $sql = 'SELECT _id FROM lotes_detalles WHERE ';

    // ELIMINAR REGISTRO DE EMPLEADO ASIGNADO
    $sql = "DELETE FROM `lotes_detalles_empleados_asignados` WHERE id_empleado = {$miEmpleado['id_empleado']} AND id_orden = {$miEmpleado['id_orden']} AND id_departamento = {$miEmpleado['id_departamento']}";
    $resultados = $localConnection->goQuery($sql);
    $object['sql'] = $sql;
    $object['resultados'] = $resultados;

    // ACTUALIAR PROCENTAJE
    $sql = "SELECT COUNT(*) total FROM lotes_detalles_empleados_asignados WHERE id_orden = {$miEmpleado['id_orden']} AND id_departamento = {$miEmpleado['id_departamento']}";
    $resultados = $localConnection->goQuery($sql);

    if ($resultados[0]['total'] > 0) {
      $nuevoPorcentaje = 100 / $resultados[0]['total'];
    } else {
      $nuevoPorcentaje = 99;
    }

    $sql = "UPDATE lotes_detalles_empleados_asignados SET procentaje_comision = $nuevoPorcentaje WHERE id_orden = {$miEmpleado['id_orden']} AND id_departamento = {$miEmpleado['id_departamento']}";
    $resultados = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($sql));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/empleados/reasignar_old', function (Request $request, Response $response, $args) {
    $miEmpleado = $request->getParsedBody();
    $object['parsed_body'] = $miEmpleado;
    $localConnection = new LocalDB();

    $sql = 'SELECT _id, unidades_solicitadas FROM lotes_detalles WHERE id_ordenes_productos  = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'];
    $exist = $localConnection->goQuery($sql);

    $object['sql_count'] = $sql;
    $object['count'] = count($exist);

    if ($object['count']) {
      // BUSCAR NOMBRE DEL DEPARTAMENTO
      $sql = "SELECT departamento FROM departamentos WHERE _id = {$miEmpleado['id_departamento']}";
      $respDep = $localConnection->goQuery($sql);
      $nombreDepartamento = $respDep[0]['departamento'];

      if ($miEmpleado['departamento'] === 'Corte') {
        $nuevaCantiadSolicitada = intval($miEmpleado['cantidad']) + intval($exist[0]['unidades_solicitadas']);

        $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
        $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
        $values .= "id_departamento ='" . $miEmpleado['id_departamento'] . "',";
        $values .= "departamento ='" . $nombreDepartamento . "',";
        $values .= "unidades_solicitadas ='" . $nuevaCantiadSolicitada . "'";
      } else {
        $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
        $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
        $values .= "unidades_solicitadas ='" . $miEmpleado['cantidad_orden'] . "'";
      }

      $sql = 'UPDATE lotes_detalles SET ' . $values . " WHERE id_departamento = '" . $miEmpleado['id_departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'];
    } else {
      // TODO Verificar si ya hay una asignacion para hacer un `UPDATE` de lo contrario hacer un `INSERT`
      $sql = 'SELECT _id FROM lotes_detalles WHERE id_orden = ' . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "'";

      $verificacion = $localConnection->goQuery($sql);
      $object['verificacion'] = $verificacion;

      if (empty($verificacion)) {
        // BUSCAR CANTIDAD EN `ordenes_productos`
        $sql = 'SELECT cantidad FROM ordenes_productos WHERE _id = ' . $miEmpleado['id_ordenes_productos'];
        $cantidad_orden = $localConnection->goQuery($sql)[0]['cantidad'];

        $myDate = new CustomTime();
        $now = $myDate->today();

        // ASIGNAR EMPLEADO
        $values = "'" . $now . "',";
        $values .= "'" . $miEmpleado['id_woo'] . "',";
        $values .= "'" . $cantidad_orden . "',";
        $values .= "'" . $miEmpleado['id_orden'] . "',";
        $values .= "'" . $miEmpleado['id_ordenes_productos'] . "',";
        $values .= "'" . $miEmpleado['id_empleado'] . "',";
        $values .= "'" . $miEmpleado['departamento'] . "'";

        $sql = 'INSERT INTO lotes_detalles (moment, id_woo, unidades_solicitadas, id_orden, id_ordenes_productos, id_empleado, departamento) VALUES (' . $values . ')';
      } else {
        // Hacer un UPDATE
        $sql = 'UPDATE lotes_detalles SET unidades_solicitadas = ' . $miEmpleado['cantidad'] . ', id_empleado = ' . $miEmpleado['id_empleado'] . ' WHERE id_orden = ' . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "'";
      }
    }
    $object['sql_asignacion'] = $sql;

    $object['asigancion'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    // ACTUALIZAR PAGOS (UNICAMENTE SI AÚN NO SE HA PAGADO -> fechapago = NULL)

    /*  $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "'";
        $sql = "UPDATE pagos SET " . $values . " WHERE departamento = '" . $miEmpleado['departamento'] . "' AND fecha_pago IS NULL AND id_orden = " . $miEmpleado['id_orden'];
        $object['lotes_pagos'] = $sql;
        $object['response_pagos'] = json_encode($localConnection->goQuery($sql)); */
  });

  $app->post('/lotes/get-detalles', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'SELECT id_orden, id_woo, category_name, name, cantidad, talla, corte, tela, moment FROM ordenes_productos WHERE id_woo = ' . $data['id_woo'] . ' AND talla =  ' . $data['talla'];

    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/update/cantidad', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $cantidad_orden = intval($data['cantidad_orden']);
    $cantidad_a_cortar = intval($data['cantidad_a_cortar']);
    $localConnection = new LocalDB();
    $object['request'] = $data;

    // -> -> VERIFICAR SI EL REGISTRO EXISTE EN `lotes_fisicos`
    $sql = "SELECT _id,  piezas_actuales FROM lotes_fisicos WHERE tela = '" . $data['tela'] . "' AND talla = '" . $data['talla'] . "' AND corte = '" . $data['corte'] . "' AND categoria = '" . $data['id_category'] . "'";

    $object['sql_count_lotes_fisicos'] = $sql;
    $cantidad_lotes_fisicos = $localConnection->goQuery($sql);
    $object['response_lotes_fisicos'] = $cantidad_lotes_fisicos;

    $last_id_lotes_fisicos = 0;

    if ($cantidad_a_cortar > 0) {
      // GUARDAR EN HISTORICO SOLICITADAS
      $sql = 'INSERT INTO lotes_historico_solicitadas (id_orden, id_lotes_fisicos, unidades_produccion) VALUES (' . $data['id_orden'] . ', ' . $last_id_lotes_fisicos . ', ' . $cantidad_a_cortar . ')';
      $object['response_insert_historico_solicitadas'] = $localConnection->goQuery($sql);
    }

    if (empty($cantidad_lotes_fisicos)) {
      $cantidad_unidades = $cantidad_a_cortar - $cantidad_orden;
      $object['dataResp'] = $cantidad_unidades;

      $sql = 'INSERT INTO lotes_fisicos (id_orden, id_woo, tela, talla, corte, categoria, piezas_actuales) VALUES (' . $data['id_orden'] . ', ' . $data['id_woo'] . ", '" . $data['tela'] . "', '" . $data['talla'] . "', '" . $data['corte'] . "', '" . $data['id_category'] . "', '" . $cantidad_unidades . "');";
      // $object['response_insert_lotes_fidicos'] = $localConnection->goQuery($sql);

      // OBTENER EL ULTIMO ID DE lotes_fisicos
      $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM lotes_fisicos');
      $last_id_lotes_fisicos = intval($last_prod[0]['id']);
      // TODO ASIGNAT PAGO A CORTE CON LAS UNIDADES SOLICITADAS
    } else {
      // ACTUALIZAR EL REGISTRO EN `lotes_fisicos`
      $cantidad_unidades = (intval($data['cantidad_existencia']) - $cantidad_orden) + $cantidad_a_cortar;
      // $cantidad_unidades = intval($data["cantidad_existencia"]) + $cantidad_a_cortar;

      $sql = "UPDATE lotes_fisicos SET piezas_actuales = '" . $cantidad_unidades . "', id_woo = " . $data['id_woo'] . ' , id_orden = ' . $data['id_orden'] . ' WHERE _id = ' . $cantidad_lotes_fisicos[0]['_id'];
      // $object['response_get_lotes_fisicos'] = $localConnection->goQuery($sql);
      $object['dataResp'] = $object['response_lotes_fisicos'][0]['piezas_actuales'];
    }

    // VERIFICAR SI EXISTEN REGISTROS EN LA TABLA LOTES MOVIMIENTOS
    // GUARDAR EN lotes_movimientos SIEMPRE!!!
    $sql = 'SELECT _id FROM lotes_movimientos WHERE id_orden = ' . $data['id_orden'] . ' AND id_lotes_detalles = ' . $data['id'];
    $verificar_lm = $localConnection->goQuery($sql);

    if (empty($verificar_lm)) {
      // INSERT
      $sql = 'INSERT INTO lotes_movimientos (id_lotes_detalles, id_orden, unidades_existentes, unidades_solicitadas_corte) VALUES (' . $data['id'] . ', ' . $data['id_orden'] . ', ' . $cantidad_unidades . ', ' . $cantidad_a_cortar . ')';
    } else {
      $sql = 'UPDATE lotes_movimientos SET unidades_existentes = ' . $cantidad_unidades . ', unidades_solicitadas_corte = ' . $cantidad_a_cortar . ' WHERE id_orden = ' . $data['id_orden'] . ' AND id_lotes_detalles = ' . $data['id'];
      // UPDATE
    }
    $object['sql_revisar'] = $sql;
    $object['response_insert_lotes_movimientos'] = $localConnection->goQuery($sql);

    // CONSULTA DE RETORNO DE DATOS.
    if ($last_id_lotes_fisicos > 0) {
      // $last_id_lotes_fisicos = intval($cantidad_lotes_fisicos[0]["_id"]);
    }
    $sql = 'SELECT piezas_actuales FROM lotes_fisicos WHERE _id = ' . $last_id_lotes_fisicos;
    $cantidad_piezas = $localConnection->goQuery($sql);
    $object['cantidad_piezas'] = $cantidad_piezas;

    $sql = 'SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos';
    $object['lotes_fisicos'] = $localConnection->goQuery($sql);

    $object['sql_with_error'] = $sql;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes/update/prioridad', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE lotes SET prioridad = '" . $data['prioridad'] . "' WHERE _id = '" . $data['id'] . "'";

    $object['sql'] = $sql;
    $object['response_orden'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Editar la cantidad en lotes
  $app->get('/lotes-fisicos/tabla-editar', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT
    categoria categoria_tienda,
    tela,
    corte,
    talla,
    id_orden,
    id_woo,
    _id acciones,
    _id eliminar
    FROM
    lotes_fisicos
    ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC';

    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['telas'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $woo = new WooMe();
    $object['categories'] = json_decode($woo->getAllCategories());

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes-fisicos/tabla-editar-filter', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    if ($data['tela'] === 'all') {
      $sql = 'SELECT
        categoria categoria_tienda,
        tela,
        corte,
        talla,
        id_orden,
        id_woo,
        _id acciones,
        _id eliminar
        FROM
        lotes_fisicos
        ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC';
    } else {
      $sql = "SELECT
        categoria categoria_tienda,
        tela,
        corte,
        talla,
        id_orden,
        id_woo,
        _id acciones,
        _id eliminar
        FROM
        lotes_fisicos
        WHERE tela = '" . $data['tela'] . "'
        ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC";
    }

    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
    $object['telas'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $woo = new WooMe();
    $object['categories'] = json_decode($woo->getAllCategories());

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar lote
  $app->post('/lotes-fisicos/eliminar', function (Request $request, Response $response) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB();

    $object['miEmpleado'] = $miEmpleado;
    $sql = 'DELETE FROM lotes_fisicos WHERE _id =  ' . $miEmpleado['id'];
    $object['sql'] = $sql;

    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/lotes-fisicos/update', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "UPDATE lotes_fisicos SET piezas_actuales = '" . $data['cantidad'] . "' WHERE _id = '" . $data['id_lote'] . "'";

    $object['sql'] = $sql;
    $object['response_orden'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** LOCAL LOTES ACTIVOS */
  $app->get('/lotes/activos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = "SELECT a.lote, a.fecha, a.id_orden, a.paso, b.cliente_nombre FROM lotes a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'pre-order' ORDER BY a.lote DESC";
    $object['lotes'] = $localConnection->goQuery($sql);

    $sql = 'SELECT a.id_orden, b.departamento, c.username empleado, b.producto, b.unidades_restantes, b.unidades_solicitadas, b.detalles, a.lote FROM lotes a JOIN lotes_detalles b ON a.id_orden = b.id_orden JOIN empleados c ON b.id_empleado = c._id';
    $object['lotes_detalles'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/lotes/fisicos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a.unidades FROM lotes_fisicos a JOIN inventario b ON a.id_inventario = b._id';
    $object['lotes'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/lotes/existencia/{talla}/{tela}/{corte}/{categoria}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT piezas_actuales FROM lotes_fisicos WHERE talla = '" . $args['talla'] . "' AND tela = '" . $args['tela'] . "' AND corte = '" . $args['corte'] . "' AND categoria = '" . $args['categoria'] . "'";
    $response_lotes = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    if (empty($response_lotes)) {
      $cantidad = 0;
    } else {
      $cantidad = $response_lotes[0]['piezas_actuales'];
    }

    $response->getBody()->write(json_encode($cantidad));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN LOTES */

  /** Asignacion */
  // Obtener datos para la asignaciond e empelados
  $app->get('/asignacion/ordenes', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'cliente';
    $object['fields'][1]['label'] = 'Cliente';

    $object['fields'][2]['key'] = 'inicio';
    $object['fields'][2]['label'] = 'Inicio';

    $object['fields'][3]['key'] = 'entrega';
    $object['fields'][3]['label'] = 'Entrega';

    $object['fields'][4]['key'] = 'status';
    $object['fields'][4]['label'] = 'Estatus';

    $object['fields'][4]['key'] = 'asignar';
    $object['fields'][4]['label'] = 'Asignar';

    $sql = "SELECT a._id orden, a._id asignar, a.cliente_nombre cliente, a.fecha_inicio inicio, a.fecha_entrega entrega, a.status estatus, b.terminado FROM `ordenes` a JOIN disenos b ON a._id = b.id_orden WHERE (a.status = 'activa' OR a.status = 'terminada' OR a.status = 'En espera' OR status = 'pausada') AND b.terminado = 1 OR b.tipo = 'no' ORDER BY a._id DESC";

    $object['items'] = $localConnection->goQuery($sql);
    $object['data'] = $object['items'];

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener empleados de la asignados de la orden
  $app->get('/asignacion/empleados/{orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT dep_responsable responsable,dep_diseno diseno, dep_jefe_diseno, dep_corte corte,dep_impresion impresion,dep_estampado estampado,dep_confeccion confeccion,dep_revision revision FROM ordenes WHERE _id = ' . $args['orden'];
    $object['sql'] = $sql;
    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GUARDAR ASIGNACION
  $app->post('/asignacion/{orden}/{departamento}/{empleado}', function (Request $request, Response $response, $args) {
    // ACTUALIZAR DATOS DE LA ORDEN
    $localConnection = new LocalDB();
    $departamento = 'dep_' . $args['departamento'];

    if ($args['empleado'] === 'none') {
      $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = NULL WHERE _id = ' . $args['orden'];
      $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
    } else {
      // BUSCAR COMISION DEL EMPLEADO PARA LA ORDEN
      $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = ' . $args['empleado'] . ' WHERE _id = ' . $args['orden'];
      $sql_comision = 'SELECT  comision FROM empleados WHERE _id = ' . $args['empleado'];
      $dataEmpleado = $localConnection->goQuery($sql_comision);

      $comision = $dataEmpleado[0]['comision'];
      // PREPARAR FECHAS
      $myDate = new CustomTime();
      $now = $myDate->today();

      // GUARDAR DATOS DEL PAGO
      $values = "'" . $now . "',";
      $values .= $args['empleado'] . ',';
      $values .= $args['orden'] . ',';
      $values .= "'" . $args['departamento'] . "',";
      $values .= "'0000-00-00',";
      $values .= '0,';
      $values .= $comision . ',';
      $values .= '0';

      $object['sql_pagos'] = $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
      $object['sql_pagos'] .= $sql_pagos = 'INSERT INTO pagos (moment, id_empleado, id_orden, departamento, fecha_terminado, dolar,  comision, pago) VALUES (' . $values . ')';
    }

    $dataEmpleado = $localConnection->goQuery($sql_ordenes);
    $dataEmpleado = $localConnection->goQuery($sql_pagos);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ELIMINAR ASIGNACION
  $app->post('/asignacion/elimianr/{orden}/{departamento}', function (Request $request, Response $response, $args) {
    // ACTUALIZAR DATOS DE LA ORDEN
    $localConnection = new LocalDB();
    // ELIMINAR DATOS DEL PAGO
    $sql = 'DELETE FROM PAGOS WHERE id_orden = ' . $request['id_orden'] . ' AND departamento = ' . $args['departamento'];
    $object['dataEmpleado'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** Fin asignacion */

  /** PRODUCCION */

  // SSE PRODUCCION
  $app->get('/sse/produccion/ordenes-activas', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    $localConnection = new LocalDB();
    $sql = "SELECT 
            a.id_orden orden, 
            b.nombre AS empleado, 
            c.name producto, 
            c.cantidad, 
            c.talla, 
            c.corte, 
            c.tela, 
            DATE_FORMAT(a.fecha_inicio, '%h:%i:%s %p') AS hora, 
            DATE_FORMAT(a.fecha_inicio, '%d-%m-%Y') AS fecha 
            FROM lotes_detalles a 
            JOIN empleados b ON a.id_empleado = b._id 
            JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
            WHERE a.progreso = 'en curso' 
            ORDER BY a.fecha_inicio DESC, b.nombre ASC
        ";
    $object['items'] = $localConnection->goQuery($sql);

    // $sse = new SSE($obj);
    // $events = $sse->SsePrint();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // SSE DATA
  // SSE PRODUCCION
  $app->get('/sse/produccion', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    $localConnection = new LocalDB();

    // EMPLEADOS ASIGANDOS A TAREAS
    $sql = "SELECT
                ord._id id_orden,
                ord.status status_orden,
                loa.id_empleado,
                emp.nombre empleado,
                loa.procentaje_comision,
                loa.id_departamento,
                dep.departamento
            FROM
                ordenes ord
            JOIN lotes_detalles_empleados_asignados loa ON loa.id_orden = ord._id
            JOIN api_empresas.empresas_usuarios emp on emp.id_usuario = loa.id_empleado 
            JOIN departamentos dep ON dep._id = loa.id_departamento 
            WHERE ord.status LIKE 'En espera' OR ord.status LIKE 'activa' OR ord.status OR ord.status = 'pausada'
        ";
    $obj['emp_asignados'] = $localConnection->goQuery($sql);

    // ITEMS DE LISTA DE PRODUCCIÓN
    $sql = "SELECT DISTINCT
            a._id AS orden,
            f.orden_fila,
            a._id AS vinculada,
            CONCAT(
                cus.first_name,
                ' ',
                cus.last_name
            ) AS cliente,
            b.prioridad,
            b.paso,
            d.estatus AS estatus_revision,
            a.fecha_inicio AS inicio,
            a.fecha_entrega AS entrega,
            -- a.observaciones AS detalles,
            'CARGAR DINAMICAMNNTE' detalles,
            n.borrador AS detalle_empleado,
            a._id AS acciones,
            a.status AS estatus,
            c._id AS id_diseno,
            (
            SELECT
                SUM(o.cantidad)
            FROM
                ordenes_productos o
            JOIN products p ON
                o.id_woo = p._id
            WHERE
                id_orden = a._id AND p.fisico = 1
        ) AS unidades,
        COALESCE(e.nombre, 'Sin asignar') AS disenador
        FROM
            ordenes a
        LEFT JOIN ordenes_borrador_empleado n ON
            a._id = n.id_orden
        JOIN lotes b ON
            a._id = b.id_orden
        LEFT JOIN customers cus ON
            cus._id = a.id_wp
        LEFT JOIN disenos c ON
            a._id = c.id_orden
        LEFT JOIN revisiones d ON
            d.id_diseno = c._id
        LEFT JOIN api_empresas.empresas_usuarios e
        ON
            e.id_usuario =(
                CASE WHEN c.id_empleado = 0 THEN 0 ELSE c.id_empleado
            END
        )
        LEFT JOIN ordenes_fila_orden f ON f.id_orden = a._id
        WHERE
            (
                a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'
            )
        GROUP BY
            a._id
        ORDER BY f.orden_fila ASC;
        ";
    $obj['items_old'] = $localConnection->goQuery($sql);

    // ITEMS DE LISTA DE PRODUCCIÓN
    $sql = "SELECT
            a._id AS orden,
            f.orden_fila,
            a._id AS vinculada,
            CONCAT(
                cus.first_name,
                ' ',
                cus.last_name
            ) AS cliente,
            b.prioridad,
            b.paso,
            d.estatus AS estatus_revision,
            a.fecha_inicio AS inicio,
            a.fecha_entrega AS entrega,
            'CARGAR DINAMICAMNNTE' AS detalles,
            n.borrador AS detalle_empleado,
            a._id AS acciones,
            a.status AS estatus,
            c._id AS id_diseno,
            (
                SELECT
                    SUM(o.cantidad)
                FROM
                    ordenes_productos o
                JOIN products p ON
                    o.id_woo = p._id
                WHERE
                    id_orden = a._id AND p.fisico = 1
            ) AS unidades,
            COALESCE(e.nombre, 'Sin asignar') AS disenador,

            /* ================================================================== */
            /* INICIO: NUEVAS COLUMNAS DE PROGRESO                                  */
            /* ================================================================== */

            (
                CASE b.paso
                    WHEN 'producción' THEN 0.6 WHEN 'Corte' THEN 1 WHEN 'Estampado' THEN 2
                    WHEN 'Impresión' THEN 3 WHEN 'Costura' THEN 4 WHEN 'Limpieza' THEN 5
                    WHEN 'Revisión' THEN 5.88 ELSE 1
                END
            ) AS progreso_paso_valor,

            COALESCE(pasos_info.total_pasos, 0) AS progreso_total_pasos,

            COALESCE(
                ROUND(
                    (
                        CASE b.paso
                            WHEN 'producción' THEN 0.6 WHEN 'Corte' THEN 1 WHEN 'Estampado' THEN 2
                            WHEN 'Impresión' THEN 3 WHEN 'Costura' THEN 4 WHEN 'Limpieza' THEN 5
                            WHEN 'Revisión' THEN 5.88 ELSE 1
                        END * 100
                    ) / NULLIF(pasos_info.total_pasos, 0)
                ), 0
            ) AS progreso_porcentaje

            /* ================================================================== */
            /* FIN: NUEVAS COLUMNAS DE PROGRESO                                     */
            /* ================================================================== */

        FROM
            ordenes a
        LEFT JOIN ordenes_borrador_empleado n ON a._id = n.id_orden
        JOIN lotes b ON a._id = b.id_orden
        LEFT JOIN customers cus ON cus._id = a.id_wp
        LEFT JOIN disenos c ON a._id = c.id_orden
        LEFT JOIN revisiones d ON d.id_diseno = c._id
        LEFT JOIN api_empresas.empresas_usuarios e ON e.id_usuario = c.id_empleado
        LEFT JOIN ordenes_fila_orden f ON f.id_orden = a._id
        LEFT JOIN (
            SELECT
                id_orden,
                COUNT(DISTINCT departamento) AS total_pasos
            FROM
                lotes_detalles
            GROUP BY
                id_orden
        ) AS pasos_info ON a._id = pasos_info.id_orden
        WHERE
            a.status IN ('activa', 'pausada', 'En espera')
        GROUP BY
            a._id -- Se agrupa por el ID de la orden para obtener una fila por orden.
        ORDER BY
            f.orden_fila ASC;
    ";

    $obj['items'] = $localConnection->goQuery($sql);

    // ITEMS POR ASIGNAR
    $sql = 'SELECT
            lot.id_orden,
            lot.id_ordenes_productos,
            lot.id_empleado id_empleado_asignado,
            lot.progreso,
            emp.id_usuario id_emlpleado,
            emp.nombre nombre_empleado,
            emp.departamento emp_departamento,
            lot.departamento lot_departamento
        FROM
            lotes_detalles lot
        LEFT JOIN api_empresas.empresas_usuarios emp
        ON
            lot.id_empleado = emp.id_usuario
        LEFT JOIN ordenes ord ON ord._id = lot.id_orden
        WHERE lot.id_empleado IS NULL
        ';
    $obj['por_asignar'] = $localConnection->goQuery($sql);

    // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
    $sql = "SELECT a._id id_orden, b.departamento FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' GROUP BY a._id, b.departamento";
    $obj['pactivos'] = $localConnection->goQuery($sql);

    // ORDENES VINCULADAS
    $sql = "SELECT b.id_father, b.id_child FROM ordenes_vinculadas b JOIN ordenes a ON a._id = b.id_father WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
    $obj['vinculadas'] = $localConnection->goQuery($sql);

    // EMPLEADOS
    $sql = 'SELECT id_usuario _id, email username, nombre, comision, departamento FROM api_empresas.empresas_usuarios ORDER BY nombre ASC';
    $obj['asignacion'] = $localConnection->goQuery($sql);

    $sql = "SELECT b.id_orden, b.paso from lotes b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
    $obj['pasos'] = $localConnection->goQuery($sql);

    $sql = "SELECT DISTINCT
        b._id,
        b._id id_ordenes_productos,
        b.id_orden,
        (SELECT _id FROM lotes_fisicos WHERE id_orden = b._id) id_lotes, 
        b.id_woo,
        p.fisico,
        b.id_category,
        b.category_name,
        b.name,        
        b.cantidad, 
        c.piezas_actuales,
        b.talla,
        b.corte,
        b.tela,
        b.precio_unitario,
        b.precio_woo,   
        b.moment
        FROM 
            ordenes_productos b
        LEFT JOIN products p ON p._id = b.id_woo
        LEFT JOIN lotes_fisicos c ON c.id_orden = b._id
        LEFT JOIN ordenes a ON
            b.id_orden = a._id
        LEFT JOIN products_comisiones pc ON pc.id_product = b.id_woo
        WHERE
            a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' AND b.category_name != 'Diseños' -- AND p.fisico = 1
        ORDER BY b._id DESC, c.piezas_actuales DESC";

    $obj['orden_productos'] = $localConnection->goQuery($sql);

    $key = 0;
    foreach ($obj['orden_productos'] as $producto) {
      $data[$key]['_id'] = $producto['_id'];
      $data[$key]['id_orden'] = $producto['id_orden'];
      $data[$key]['id_lotes'] = $producto['id_lotes'];
      $data[$key]['id_woo'] = $producto['id_woo'];
      $data[$key]['id_woo'] = $producto['id_woo'];
      $data[$key]['fisico'] = $producto['fisico'];
      $data[$key]['category_name'] = $producto['category_name'];
      //    $data[$key]['comisiones'] = json_decode($producto['comisiones'], true);
      $data[$key]['cantidad'] = $producto['cantidad'];
      $data[$key]['piezas_actuales'] = $producto['piezas_actuales'];
      $data[$key]['talla'] = $producto['talla'];
      $data[$key]['corte'] = $producto['corte'];
      $data[$key]['tela'] = $producto['tela'];
      $data[$key]['precio_unitario'] = $producto['precio_unitario'];
      $data[$key]['precio_woo'] = $producto['precio_woo'];
      $data[$key]['moment'] = $producto['moment'];
      $key++;
    }

    if (isset($data)) {
      $obj['orden_productos'] = $data;

      $sql = "SELECT b._id, b.id_orden, b.id_woo, b.progreso, b.unidades_solicitadas cantidad, b.id_ordenes_productos, b.id_empleado, b.departamento, b.id_departamento, b.unidades_solicitadas, b.comision, 'CARGAR DINAMINAMENTE' detalles, /*b.detalles,*/ b.fecha_inicio, b.fecha_terminado, b.moment FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
      $obj['lote_detalles'] = $localConnection->goQuery($sql);

      $sql = 'SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos';
      $obj['lotes_fisicos'] = $localConnection->goQuery($sql);

      $sql = "SELECT
        b._id,
        b.id_orden,
        b._id item,
        b.id_woo cod,
        b.name producto,
        b.cantidad,
        b.talla,
        b.tela,
        b.corte,
        b.precio_unitario precio,
        b.precio_woo precioWoo
        FROM
            ordenes_productos b
        JOIN ordenes a ON
            a._id = b.id_orden
        JOIN products p ON p._id = b.id_woo
        WHERE
            (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND p.fisico = 1";
      $obj['reposicion_ordenes_productos'] = $localConnection->goQuery($sql);

      // BUSCAR REPOSICIONES SOLICITADAS POR EMPLEADOS
      $sql = "SELECT
        a._id id_reposicion,
        a.id_orden,
        c._id id_ordenes_productos,
        b.nombre empleado,  
        a.detalle_emisor,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        DATE_FORMAT(a.moment, '%I:%i %p') AS hora,
        c.name producto,
        a.unidades,
        c.talla,
        c.corte,
        c.tela
        FROM
            reposiciones a
        LEFT JOIN ordenes_fila_reposiciones d ON d.id_reposicion = a._id
        LEFT JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado_emisor 
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos 
        WHERE
            (a.aprobada IS NULL OR a.aprobada = 0) AND a.id_empleado IS NULL
        ORDER BY d.orden_fila ASC;
        ";
      $obj['reposiciones_solicitadas'] = $localConnection->goQuery($sql);

      // Deetalles de los productos
      $sql = "SELECT
        a._id id_orden,
        b._id id_lotes_detalles,
        p.fisico,
        b.name,
        b.cantidad,
        b.talla,
        b.corte,
        b.tela
        FROM
            ordenes a
        JOIN ordenes_productos b ON
            a._id = b.id_orden
        LEFT JOIN products p ON p._id = b.id_woo
        WHERE
            a.status LIKE 'En espera' OR a.status LIKE 'activa'
        ORDER BY
            b.id_orden ASC;";
      $obj['productos'] = $localConnection->goQuery($sql);

      // EMPLEADOS
      $sql = 'SELECT
            a.id_usuario AS _id,
            a.id_usuario AS acciones,
            a.email AS username,
            a.password,
            a.nombre,
            a.email,
            a.departamento,
            a.comision,
            a.comision_tipo,
            a.acceso,
            IFNULL(CONCAT("[", GROUP_CONCAT(
                CONCAT("{\"id\":", b.id_departamento, ",\"nombre\":\"", c.departamento, "\"}")
                SEPARATOR ","), "]"), "[]") AS departamentos
        FROM 
            api_empresas.empresas_usuarios a
        LEFT JOIN api_empresas.empresas_usuarios_departamentos b ON b.id_empleado = a.id_usuario
        LEFT JOIN ' . LOCAL_DB . '.departamentos c ON c._id = b.id_departamento
        WHERE
            a.activo = 1  AND a.id_empresa = ' . ID_EMPRESA . ' GROUP BY 
            a.id_usuario, a.email, a.password, a.nombre, a.departamento, 
            a.comision, a.comision_tipo, a.acceso;
            ';
      $items = $localConnection->goQuery($sql);
      $obj['sql_empleados'] = $sql;

      // Decodificar el campo `departamentos`
      foreach ($items as &$item) {
        if (!empty($item['departamentos'])) {
          $item['departamentos'] = json_decode($item['departamentos'], true);
        }
      }
      $obj['empleados'] = $items;

      $localConnection->disconnect();

      // CREAR CAMPOS DE LA TABLA
      $obj['fields'][0]['key'] = 'orden';
      $obj['fields'][0]['label'] = 'Orden';

      $obj['fields'][1]['key'] = 'cliente';
      $obj['fields'][1]['label'] = 'Cliente';

      $obj['fields'][2]['key'] = 'prioridad';
      $obj['fields'][2]['label'] = 'Prioridad';

      $obj['fields'][2]['key'] = 'paso';
      $obj['fields'][2]['label'] = 'Progreso';

      $obj['fields'][3]['key'] = 'inicio';
      $obj['fields'][3]['label'] = 'Inicio';

      $obj['fields'][4]['key'] = 'entrega';
      $obj['fields'][4]['label'] = 'Entrega';

      $obj['fields'][5]['key'] = 'vinculada';
      $obj['fields'][5]['label'] = 'Vinculada';

      $obj['fields'][6]['key'] = 'estatus';
      $obj['fields'][6]['label'] = 'Estatus';

      $obj['fields'][7]['key'] = 'detalles';
      $obj['fields'][7]['label'] = 'Detalles';

      $obj['fields'][8]['key'] = 'acciones';
      $obj['fields'][8]['label'] = 'Acciones';
    } else {
      $obj['orden_productos'] = [];
    }

    $response->getBody()->write(json_encode($obj, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // SSE CORTE
  $app->get('/sse/produccion/corte/{id_empleado}', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    $sql = 'SELECT 
                                                                                                                                                                                                                                                                                                                                                                        a._id AS id_lotes_detalles, 
                                                                                                                                                                                                                                                                                                                                                                        a.id_orden orden,
                                                                                                                                                                                                                                                                                                                                                                        a.id_orden acciones,
                                                                                                                                                                                                                                                                                                                                                                        b.name producto,
                                                                                                                                                                                                                                                                                                                                                                        b.cantidad, 
                                                                                                                                                                                                                                                                                                                                                                        b.cantidad cantidadIndividual, 
                                                                                                                                                                                                                                                                                                                                                                        a.progreso,
                                                                                                                                                                                                                                                                                                                                                                        b.talla, 
                                                                                                                                                                                                                                                                                                                                                                        b.corte, 
                                                                                                                                                                                                                                                                                                                                                                        b.tela,
                                                                                                                                                                                                                                                                                                                                                                        b.category_name categoria,
                                                                                                                                                                                                                                                                                                                                                                        COALESCE(c.piezas_actuales, 0) AS piezas_en_lote 
                                                                                                                                                                                                                                                                                                                                                                        FROM lotes_detalles a 
                                                                                                                                                                                                                                                                                                                                                                        JOIN ordenes_productos b 
                                                                                                                                                                                                                                                                                                                                                                        ON a.id_ordenes_productos = b._id 
                                                                                                                                                                                                                                                                                                                                                                        LEFT JOIN lotes_fisicos c ON c.tela = b.tela AND c.talla = b.talla AND c.corte = b.corte
                                                                                                                                                                                                                                                                                                                                                                        WHERE 
                                                                                                                                                                                                                                                                                                                                                                        a.id_empleado = ' . $args['id_empleado'] . " 
                                                                                                                                                                                                                                                                                                                                                                        AND a.departamento = 'Corte' 
                                                                                                                                                                                                                                                                                                                                                                        AND b.corte != 'No aplica' 
                                                                                                                                                                                                                                                                                                                                                                        AND (a.progreso = 'por iniciar' OR a.progreso = 'en curso')
                                                                                                                                                                                                                                                                                                                                                                        ORDER BY b.talla ASC, b.corte ASC, b.tela ASC;
                                                                                                                                                                                                                                                                                                                                                                        ";
    $obj[0]['sql'] = $sql;
    $obj[0]['name'] = 'items';

    $sql = "SELECT _id id_empleado, nombre FROM empleados WHERE departamento = 'Corte'";
    $obj[1]['sql'] = $sql;
    $obj[1]['name'] = 'empleados';

    $sse = new SSE($obj);
    $events = $sse->SsePrint();

    $response->getBody()->write(json_encode($events));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // SSE DISENO
  $app->get('/sse/diseno/{id_empleado}', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    // $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
    $localConnection = new LocalDB();
    $sql = "SELECT
                    c._id id_revision,
                    a.id_orden id,
                    a.id_orden,
                    a._id tallas_y_personalizacion,
                    a.id_orden imagen,
                    c._id revision,
                    c.id_product,
                    a._id id_diseno,
                    a.id_empleado id_disenador,
                    a.linkdrive,
                    a.codigo_diseno,
                    a.linkdrive,
                    b.cliente_nombre cliente,
                    (SELECT cus.phone FROM customers cus WHERE cus._id = b.id_wp) phone,
                    b.fecha_inicio inicio,
                    c.tipo,
                    c.estatus,
                    b.status estatus_orden
                FROM
                    disenos a
                LEFT JOIN revisiones c ON
                    a._id = c.id_diseno 
                JOIN ordenes b ON
                    b._id = a.id_orden 
                -- LEFT JOIN disenos d ON
                   -- d._id = c.id_diseno
                WHERE
                    a.id_empleado = {$args['id_empleado']} 
                    AND a.terminado = 0 
                    AND(b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera')
                GROUP BY c._id
                ORDER BY
                    a.id_orden
                DESC
        ";
    $obj['sql_items'] = $sql;

    $obj['items'] = $localConnection->goQuery($sql);

    // $sql = "SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = " . $args["id_empleado"];
    // $sql = "SELECT estatus, detalles FROM revisiones WHERE _id = " . $args["id"];
    $sql = 'SELECT a._id id_revision, a.id_orden, a.id_diseno, a.id_empleado, a.id_product, a.revision, a.estatus, a.detalles FROM revisiones a JOIN disenos b ON b.id_orden = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND b.id_empleado = ' . $args['id_empleado'] . ' ORDER BY a._id DESC';

    $sql = "SELECT DISTINCT
                a._id id_revision,
                a.id_orden,
                a.id_diseno,
                a.id_empleado,
                a.id_product,
                a.revision,
                a.estatus,
                a.tipo,
                a.detalles
            FROM
                revisiones a
            RIGHT JOIN disenos b ON
                b.id_orden = a.id_orden
            WHERE
                a.id_empleado = {$args['id_empleado']} AND b.id_empleado = {$args['id_empleado']} AND a.estatus LIKE 'Esperando Respuesta' 
            ORDER BY
                a.id_orden
            ASC
        ";
    $obj['sql_revisiones'] = $sql;
    $obj['revisiones'] = $localConnection->goQuery($sql);

    $sql = 'SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ' . $args['id_empleado'];
    $obj['ajustes'] = $localConnection->goQuery($sql);

    $sql = "SELECT pro._id id_producto, pro.product, pro.comision FROM products pro WHERE pro.category_ids LIKE '%17%' ORDER BY pro.product ASC;";
    $obj['productos'] = $localConnection->goQuery($sql);

    // $sse = new SSE($obj);
    // $events = $sse->SsePrint();
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($obj, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER DATOS PARA LA ASIGNACION DE TALLAS Y PERSONALIZACION DE TODOS LAS ORDENES ACTIVAS.
  $app->get('/sse/disenos-todo', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    $sql = "SELECT a._id id_orden, a._id tallas_personalizacion FROM ordenes a WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
    $obj[0]['sql'] = $sql;
    $obj[0]['name'] = 'items';

    // $sql = "SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno;";
    $sql = "SELECT
        a.id_diseno,
        a.tipo,
        a.cantidad,
        b.id_orden
        FROM
        ordenes o
        JOIN disenos_ajustes_y_personalizaciones a ON a.id_orden = o._id
        JOIN disenos b ON
        b._id = a.id_diseno
        WHERE o.status = 'activa' OR o.status = 'pausada' OR o.status = 'En espera' ORDER BY o._id DESC";
    $obj[2]['sql'] = $sql;
    $obj[2]['name'] = 'ajustes';

    $sse = new SSE($obj);
    $events = $sse->SsePrint();

    $response->getBody()->write(json_encode($events));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // FIN SSE DATA

  // OBTENER PASO DEL LOTE
  $app->get('/lotes/paso-actual/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // BUSCAR PASO ACTUAL EN EL LOTE
    $sql = 'SELECT paso from lotes WHERE _id = ' . $args['id_orden'];
    $tmpPaso = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    if (!empty($tmpPaso)) {
      $object['paso'] = $tmpPaso[0]['paso'];
    } else {
      $object['paso'] = null;
    }

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  //  REPOSICIONES

  // obtener reposiciones de un item y orden especifico
  $app->get('/reposiciones/{id_ordenes_productos}/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT
            a._id id_repo,
            c.name producto,
            a.unidades,
            c.talla,
            c.corte,
            c.tela,
            a.id_empleado,
            b.nombre empleado,
            detalle
        FROM
            reposiciones a
        LEFT JOIN api_empresas.empresas_usuarios b
        ON
            a.id_empleado = b.id_usuario
        JOIN ordenes_productos c ON
            a.id_ordenes_productos = c._id
        WHERE
            a.id_ordenes_productos = ' . $args['id_ordenes_productos'] . ' AND a.id_orden = ' . $args['id_orden'];
    $object['sql'] = $sql;
    $object['data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /**
   * REPORTE DE REPOSICIONES
   * - Si en `estaus_orden` se recibe el parámetro `todas` vamos a mosntrar las TODAS laordenes incuyendo las canceladas
   * - Si no se recibe ningún parámetro vamos a mostrar solo las reposciones de las ordenes activas
   * -
   */
  $app->get('/reposiciones-reporte/{estatus_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // $whereParams = '';

    if ($args['estatus_orden'] === 'activa') {
      // $whereParams = '';
      $whereParams = "WHERE ord.status = 'Activa' OR ord.status = 'Pausada' OR ord.status = 'En espera' OR ord.status = 'Terminada'";
    } elseif ($args['estatus_orden'] === 'todas') {
      $whereParams = '';
    } else {
      $whereParams = "WHERE ord.status = '" . $args['estatus_orden'] . "'";
    }

    $sql = "SELECT
                re._id id_reposicion,
                re.id_orden,
                re.id_empleado,
                re.id_empleado_emisor,
                re.id_ordenes_productos,
                op.id_woo id_producto,
                ord.status estatus_orden,    
                op.name producto,
                op.talla,
                op.corte,
                op.tela,
                re.unidades unidades,
                em_emisor.nombre empleado_emisor,
                em_asignado.nombre empleado_asignado,
                re.detalle detalle_emisor,
                re.detalle detalle_encargado,                
                (inm.valor_inicial - inm.valor_final) material_consumido,
                inv.unidad,
                DATE_FORMAT(re.moment, '%d/%m/%Y') fecha_creacion,
                DATE_FORMAT(re.moment, '%h:%i %p') hora_creacion
            FROM
                reposiciones re
            LEFT JOIN api_empresas.empresas_usuarios em_asignado ON re.id_empleado = em_asignado.id_usuario
            LEFT JOIN ordenes ord On ord._id = re.id_orden
            JOIN api_empresas.empresas_usuarios em_emisor ON re.id_empleado_emisor = em_emisor.id_usuario
            JOIN ordenes_productos op ON op._id = re.id_ordenes_productos 
            JOIN inventario_movimientos inm ON inm.id_orden = re.id_orden AND inm.id_producto = op.id_woo AND inm.id_empleado = re.id_empleado
            JOIN inventario inv ON inv._id = inm.id_producto
            {$whereParams}
            ORDER BY re.id_orden ASC, re._id ASC;";

    /*
     * $sql = 'SELECT
     *     a._id id_repo,
     *     c.name producto,
     *     a.unidades,
     *     c.talla,
     *     c.corte,
     *     c.tela,
     *     a.id_empleado,
     *     b.nombre empleado,
     *     detalle
     * FROM
     *     reposiciones a
     * LEFT JOIN api_empresas.empresas_usuarios b
     * ON
     *     a.id_empleado = b.id_usuario
     * JOIN ordenes_productos c ON
     *     a.id_ordenes_productos = c._id
     * WHERE
     *     a.id_ordenes_productos = ' . $args['id_ordenes_productos'] . ' AND a.id_orden = ' . $args['id_orden'];
     */
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/produccion/reposicion', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
    $producto = $localConnection->goQuery($sql)[0];

    $myDate = new CustomTime();
    $now = $myDate->today();

    // Verificamos si se ha enviado la solicitud desde PRoduccion, lelgan los dos id de emploados
    if (isset($data['id_empleado_emisor'])) {
      // crear estructura de datos para los dos empleados
      $campos = '(moment, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor)';
      $values = '(';
      $values .= "'" . $now . "',";
      $values .= '' . $producto['id_orden'] . ',';
      $values .= '' . $data['id_empleado'] . ',';
      $values .= '' . $data['id_empleado_emisor'] . ',';
      $values .= '' . $producto['_id'] . ',';
      $values .= '' . $data['cantidad'] . ',';
      $values .= "'" . $data['detalle'] . "')";
    } else {
      // Si no viene id_empleado_emisor, asumimos que es la creación desde el módulo de empleados
      // Añadimos id_departamento_solicitante aquí
      $campos = '(moment, id_orden, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor, id_departamento_solicitante)';
      $values = '(';
      $values .= "'" . $now . "',";
      $values .= '' . $producto['id_orden'] . ',';
      $values .= '' . $data['id_empleado'] . ',';
      $values .= '' . $producto['_id'] . ',';
      $values .= '' . $data['cantidad'] . ',';
      $values .= "'" . $data['detalle'] . "',";
      // Aseguramos que id_departamento_solicitante esté presente en los datos POST
      if (isset($data['id_departamento_solicitante'])) {
        $values .= intval($data['id_departamento_solicitante']);
      } else {
        $values .= 'NULL';  // O 0, dependiendo de cómo manejes IDs nulos/vacíos
      }
      $values .= ')';
    }  // Fin del else (creación desde módulo de empleados)

    $sql = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
    $object['sql_insert_reposiciones'] = $sql;
    $object['response'] = $localConnection->goQuery($sql);

    // Si la inserción fue exitosa y tenemos un ID de reposición
    if (isset($object['response']['insert_id']) && $object['response']['insert_id'] > 0) {
      $id_reposicion_creada = $object['response']['insert_id'];

      // Crear registro en la fila de reposiciones
      $lastOrdenFilaRepo = $localConnection->goQuery('SELECT MAX(orden_fila) AS max FROM ordenes_fila_reposiciones;');
      $nextOrdenFilaRepo = 1;  // Valor por defecto si la tabla está vacía
      if (isset($lastOrdenFilaRepo[0]['max']) && !is_null($lastOrdenFilaRepo[0]['max'])) {
        $nextOrdenFilaRepo = intval($lastOrdenFilaRepo[0]['max']) + 1;
      }

      $sql_fila_repo = "INSERT INTO `ordenes_fila_reposiciones`(`id_reposicion`, `orden_fila`) VALUES ({$id_reposicion_creada}, {$nextOrdenFilaRepo})";
      $object['sql_orden_fila_reposicion'] = $sql_fila_repo;
      $object['response_orden_fila_reposicion'] = $localConnection->goQuery($sql_fila_repo);
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/produccion/reposicion/final', function (Request $request, Response $response) {
    /**
     * VAMOS A SACAR LA PARTE DE LA CREACIÓN DE LA REP[OSCICIÓN PUES ESTA LA ESTA HACCIENDO EL EMPLEADO
     * AQUI VAMOS A REASIGANAR EMPLEADOS Y DEMÁS COSAS QUE CONLLEVAN LA REPOSICIÓN
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Validar si la reposicion ha sido aprobada
    if ($data['aprobada'] === '0') {
      $sql = "UPDATE reposiciones SET aprobada = 0, detalle = '" . $data['detalle'] . "' WHERE _id = " . $data['id_reposicion'];
      $aprobacion = $localConnection->goQuery($sql);
      $object['resp_reposiciones'] = $aprobacion;
    } else {
      $sql = "UPDATE reposiciones SET aprobada = 1, detalle = '" . $data['detalle'] . "', id_empleado = " . $data['id_empleado'] . ', id_departamento = ' . $data['id_departamento'] . ' WHERE _id = ' . $data['id_reposicion'];
      $aprobacion = $localConnection->goQuery($sql);

      // BUSCAR DATOS FALTANTES
      // Buscar ID del producto
      $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
      $producto = $localConnection->goQuery($sql)[0];
      $id_woo = $producto['id_woo'];

      // BUSCAR DEPARTAMENTO DEL EMPLEADO PARA DETERMINAR LOS PASOS INVOLUCRADOS EN LA REPOSICIÓN Y ASIA SIGNARLES COMO TRABAJO LAS PIEZAS EN LOTES DETALLES.
      // $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $data['id_empleado'];
      $sql = 'SELECT
                a.id_empleado_emisor,
                b.departamento
            FROM
                reposiciones a 
            JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado_emisor
            WHERE a._id = ' . $data['id_reposicion'];
      $departamento = $localConnection->goQuery($sql)[0]['departamento'];

      // DEVOLVER EL PASO A CORTE EN lotes
      // ASIGNAR NUEVAS TAREAS A EMPLEADOS ¿CREAR NUEVOS REGISTROS EN lotes_detalles?

      // -> BUSCAR DATOS EN ordenes_productos
      /* $sql = 'SELECT id_orden, id_woo FROM ordenes_productos WHERE _id = ' . $producto['_id'];
      $object['result_ordenes_detalles'] = $localConnection->goQuery($sql)[0];
      $id_woo = $object['result_ordenes_detalles']['id_woo'];
      $object['id_woo'] = $object['result_ordenes_detalles']['id_woo']; */

      // TODO VERIFICAR EXISTENCIA EN LOTE Y NOTIFICAR A JEFE DE PRODUCCION

      // REASIGNAR TRABAJO A EMPLEADOS Y NO SE EXCLUIRÁ AL TRABAJADOR QUE ESTE INVOLUCRADO, ESO SE DECIDIRÁ AL MOMENTO DE SACAR EL REPORTE DE PAGOS
      $sql_lote_detalles = '';
      $sql_reposiciones = '';

      $object['departamento'] = $departamento;

      switch ($departamento) {
        case 'Impresión':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }
          break;

        case 'Estampado':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }

          // ESTAMPADO
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $resp_emp_estampado = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_estampado)) {
            $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

            if (intval($id_emp_estampado != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_estampado . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_est = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_est = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_est[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            }
          }
          break;
        case 'Corte':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }

          // ESTAMPADO
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $resp_emp_estampado = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_estampado)) {
            $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

            if (intval($id_emp_estampado != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_estampado . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_est = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_est = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_est[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            }
          }

          // CORTE
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $resp_emp_corte = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_corte)) {
            $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

            if (intval($id_emp_corte != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_corte . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cor = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cor = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cor[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            }
          }

          break;

        case 'Costura':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }

          // ESTAMPADO
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $resp_emp_estampado = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_estampado)) {
            $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

            if (intval($id_emp_estampado != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_estampado . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_est = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_est = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_est[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            }
          }

          // CORTE
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $resp_emp_corte = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_corte)) {
            $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

            if (intval($id_emp_corte != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_corte . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cor = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cor = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cor[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            }
          }

          // COSTURA
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
          $resp_emp_costura = $localConnection->goQuery($sqlw);
          $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

          if (intval($id_emp_costura != intval($data['id_empleado']))) {
            if (!empty($resp_emp_costura)) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_costura . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cos = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cos = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cos[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            }
          }

        case 'Limpieza':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }

          // ESTAMPADO
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $resp_emp_estampado = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_estampado)) {
            $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

            if (intval($id_emp_estampado != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_estampado . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_est = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_est = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_est[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            }
          }

          // CORTE
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $resp_emp_corte = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_corte)) {
            $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

            if (intval($id_emp_corte != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_corte . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cor = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cor = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cor[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            }
          }

          // COSTURA
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
          $resp_emp_costura = $localConnection->goQuery($sqlw);
          $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

          if (intval($id_emp_costura != intval($data['id_empleado']))) {
            if (!empty($resp_emp_costura)) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_costura . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cos = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cos = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cos[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            }
          }

          // LIMPIEZA
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
          $resp_emp_limpieza = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_limpieza)) {
            $id_emp_limpieza = $resp_emp_limpieza[0]['id_empleado'];

            if (intval($id_emp_limpieza != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_limpieza . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_lim = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_lim = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_lim[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
            }
          }

          break;

        case 'Revisión':
          // IMPRESIÓN
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $resp_emp_impresion = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_impresion)) {
            $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

            if (intval($id_emp_impresion != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_impresion . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $object['sqlr_imp'] = $sqlr;
              $id_rep_imp = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_imp = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_imp[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
            }
          }

          // ESTAMPADO
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $resp_emp_estampado = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_estampado)) {
            $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

            if (intval($id_emp_estampado != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_estampado . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $object['sqlr_est'] = $sqlr;
              $id_rep_est = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_est = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_est[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
            }
          }

          // CORTE
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $resp_emp_corte = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_corte)) {
            $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

            if (intval($id_emp_corte != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_corte . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $object['sqlr_cor'] = $sqlr;
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_cor = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cor = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cor[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
            }
          }

          // COSTURA
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
          $resp_emp_costura = $localConnection->goQuery($sqlw);
          $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

          if (intval($id_emp_costura != intval($data['id_empleado']))) {
            if (!empty($resp_emp_costura)) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_costura . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $object['sqlr_cos'] = $sqlr;
              $id_rep_cos = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_cos = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_cos[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
            }
          }

          // LIMPIEZA
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
          $resp_emp_limpieza = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_limpieza)) {
            $id_emp_limpieza = $resp_emp_limpieza[0]['id_empleado'];

            if (intval($id_emp_limpieza != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_limpieza . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $object['sqlr_lim'] = $sqlr;
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $id_rep_lim = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_lim = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_lim[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
            }
          }

          // REVISION
          $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Revisión'";
          $resp_emp_revision = $localConnection->goQuery($sqlw);

          if (!empty($resp_emp_revision)) {
            $id_emp_revision = $resp_emp_revision[0]['id_empleado'];

            if (intval($id_emp_revision != intval($data['id_empleado']))) {
              $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
              $values = '(';
              $values .= "'" . $now . "',";
              $values .= '1,';
              $values .= '' . $data['id_orden'] . ',';
              $values .= '' . $id_emp_revision . ',';
              $values .= '' . $data['id_empleado_emisor'] . ',';
              $values .= '' . $data['id_ordenes_productos'] . ',';
              $values .= '' . $data['cantidad'] . ',';
              $values .= "'" . $data['detalle'] . "',";
              $values .= "'" . $data['detalle_emisor'] . "')";
              $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
              $object['sqlr_rev'] = $sqlr;
              $id_rep_rev = $localConnection->goQuery($sqlr);

              $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
              $id_rep_rev = $localConnection->goQuery($sqlr);
              $id_rep = $id_rep_rev[0]['id'];

              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
            } else {
              $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
            }
          }
          break;

        default:
          $sql_lote_detalles = '';
          break;
      }

      $object['sql_insert_lotes_detalles'] = $sql_lote_detalles;

      if (!empty($sql_lote_detalles)) {
        $object['result_insert_lotes_detalles'] = $localConnection->goQuery($sql_lote_detalles);
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/produccion/reposicion/final/BAK', function (Request $request, Response $response) {
    /**
     * VAMOS A SACAR LA PARTE DE LA CREACIÓN DE LA REP[OSCICIÓN PUES ESTA LA ESTA HACCIENDO EL EMPLEADO
     * AQUI VAMOS A REASIGANAR EMPLEADOS Y DEMÁS COSAS QUE CONLLEVAN LA REPOSICIÓN
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Validar si la reposicion ha sido aprobada
    if ($data['aprobada'] === '0') {
      $sql = "UPDATE reposiciones SET aprobada = 0, detalle = '" . $data['detalle'] . "' WHERE _id = " . $data['id_reposicion'];
      $aprobacion = $localConnection->goQuery($sql);
      $object['resp_reposiciones'] = $aprobacion;
    } else {
      $sql = "UPDATE reposiciones SET aprobada = 1, detalle = '" . $data['detalle'] . "', id_empleado = " . $data['id_empleado'] . ' WHERE _id = ' . $data['id_reposicion'];
      $object['sql_reposiciones'] = $sql;
      $aprobacion = $localConnection->goQuery($sql);

      // BUSCAR DATOS FALTANTES
      // Buscar ID del producto
      $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
      $producto = $localConnection->goQuery($sql)[0];
      $id_woo = $producto['id_woo'];

      // BUSCAR DEPARTAMENTO DEL EMPLEADO PARA DETERMINAR LOS PASOS INVOLUCRADOS EN LA REPOSICIÓN Y ASIA SIGNARLES COMO TRABAJO LAS PIEZAS EN LOTES DETALLES.
      $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $data['id_empleado'];
      $object['sql_get_departamento_empleado'] = $sql;
      $departamento = $localConnection->goQuery($sql)[0]['departamento'];

      // DEVOLVER EL PASO A CORTE EN lotes
      // ASIGNAR NUEVAS TAREAS A EMPLEADOS ¿CREAR NUEVOS REGISTROS EN lotes_detalles?

      // -> BUSCAR DATOS EN ordenes_productos
      /* $sql = 'SELECT id_orden, id_woo FROM ordenes_productos WHERE _id = ' . $producto['_id'];
      $object['sql_get_idwoo_ordenes_productos'] = $sql;
      $object['result_ordenes_detalles'] = $localConnection->goQuery($sql)[0];
      $id_woo = $object['result_ordenes_detalles']['id_woo'];
      $object['id_woo'] = $object['result_ordenes_detalles']['id_woo']; */

      // TODO VERIFICAR EXISTENCIA EN LOTE Y NOTIFICAR A JEFE DE PRODUCCION

      // REASIGNAR TRABAJO A EMPLEADOS Y NO SE EXCLUIRÁ AL TRABAJADOR QUE ESTE INVOLUCRADO, ESO SE DECIDIRÁ AL MOMENTO DE SACAR EL REPORTE DE PAGOS
      $sql_lote_detalles = '';
      $sql_reposiciones = '';
      switch ($departamento) {
        case 'Impresión':
          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
          break;

        case 'Estampado':
          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
          break;

        case 'Corte':
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          break;

        case 'Costura':
          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
          break;

        case 'Limpieza':
          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
          $id_emp_costura = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
          break;

        case 'Revisión':
          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
          $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
          $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
          $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
          $id_emp_costura = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
          $id_emp_limpieza = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Revisión'";
          $id_emp_revision = $localConnection->goQuery($sqlw)[0]['id_empleado'];

          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
          $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
          break;

        default:
          $sql_lote_detalles = '';
          break;
      }

      $object['sql_insert_lotes_detalles'] = $sql_lote_detalles;

      if (!empty($sql_lote_detalles)) {
        // $object['result_insert_lotes_detalles'] = $localConnection->goQuery($sql_lote_detalles);
      }
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TERMINAR CICLO DE PRODUCCION
  $app->post('/produccion/terminar/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $localConnection = new LocalDB();

    $sql = "UPDATE `ordenes` SET `status`='terminado' WHERE `_id` = " . $id;
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // ASIGNAR VARIAS ORDENES A CORTE A LA VEZ
  $app->post('/produccion/asignar-varias-ordenes-a-corte', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $object['data'] = $data;
    $object['request_data'] = json_decode($data['data']);

    $sql = '';
    foreach ($object['request_data'] as $key => $item) {
      $sql .= 'UPDATE lotes_detalles SET id_empleado = ' . $data['id_empleado'] . ', unidades_solicitadas = ' . $object['request_data'][$key]->cantidad . '  WHERE _id = ' . $object['request_data'][$key]->id_lotes_detalles . ';';
    }

    $object['response_update'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    /*
     * $listaDeIdsDetalles = explode(',', $data['id_lotes_detalles']);
     *         $countIdLotesDetalles = count($listaDeIdsDetalles);
     *         $listaDeIdsOrdenes = explode(',', $data['ordenes']);
     * // BUSCAR EN ordenes_productos
     *         $sql = "";
     *         foreach ($listaDeIdsDetalles as $idLoteDetalles) {
     * // $sql2 = "SELECT cantidad FROM ordenes_productos WHERE id_orden = " . $data["id_orden"];
     *             $sql2 = "SELECT a.cantidad FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos WHERE b.id_orden = " . $data[""];
     *             $cantidadPiezas = $localConnection->goQuery($sql2);
     *
     *             $sql .= "UPDATE lotes_detalles SET id_empleado = " . $data["id_empleado"] . " WHERE _id = " . $idLoteDetalles . ";";
     *         }
     *         $object['response'] = $localConnection->goQuery($sql);
     */

    // PRETARA UPDATE
    /* $UpdateParams = "(";
                             foreach ($listaDeIdsDetalles as $idLoteDetalles) {
                                $UpdateParams .= "";
                            }
                            $sql = "";
                            foreach ($listaDeIdsOrdenes as $idOrden) {
                                $sql .= "UPDATE lotes_detalles SET id_empleado = " . $data["id_epleado"] . " WHERE id_orden = " . $idOrden . " AND ";
                            } */

    // $response->getBody()->write(json_encode($object));
    $response->getBody()->write(json_encode($sql));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // UPDATE PASO
  $app->post('/produccion/update/paso', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // VERIFCAR SI EXISTE PERSONAL ASIGNADO APR ESTE PRODUCTO EN EL LOTE
    $sql = 'SELECT COUNT(*) cuenta FROM lotes_detalles WHERE id_orden = ' . $data['id_orden'] . " AND departamento = '" . $data['paso'] . "'";
    $object['sql_empty'] = $sql;
    $cuenta = $localConnection->goQuery($sql);

    $asignados = $cuenta[0]['cuenta'];
    $object['asignados'] = $cuenta[0]['cuenta'];
    $object['empty'] = empty($asignados);

    if (empty($asignados)) {
      $object['nodata'] = true;
    } else {
      // TODO buscar datos para el calculo de pagos
      $sql = "UPDATE lotes SET paso = '" . $data['paso'] . "' WHERE _id = '" . $data['id_orden'] . "'";
      $object['response_orden'] = json_encode($localConnection->goQuery($sql));
      $object['nodata'] = false;
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PROGRESSBAR
  $app->get('/produccion/progressbar/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT id_empleado, id_departamento FROM lotes_detalles_empleados_asignados WHERE id_orden =' . $args['id_orden'];
    $data['data']['empleados_asignados'] = $localConnection->goQuery($sql);

    // VERIFCAR STATUS DE LA ORDEN
    $sql = 'SELECT status from ordenes WHERE _id = ' . $args['id_orden'];
    $tmpStatus = $localConnection->goQuery($sql);

    if (!empty($tmpStatus)) {
      $object['status'] = $tmpStatus[0]['status'];
    }

    // BUSCAR PASO ACTUAL EN EL LOTE
    $sql = 'SELECT paso from lotes WHERE id_orden = ' . $args['id_orden'];
    $tmpPaso = $localConnection->goQuery($sql);

    if (!empty($tmpPaso)) {
      $object['paso'] = $tmpPaso[0]['paso'];

      // BUSCAR TIPO DE DISEÑO
      $sql = 'SELECT a.tipo, a.id_empleado, b.nombre FROM disenos a JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado WHERE id_orden = ' . $args['id_orden'];
      $d = $localConnection->goQuery($sql);

      if (empty($d)) {
        $diseno = 'no';
      } else {
        if (isset($d[0]['tipo'])) {
          $diseno = $d[0]['tipo'];
        } else {
          $diseno = 'no';
        }
      }

      if ($diseno === 'no') {
        $cuentaDisenos = 0;
      } else {
        $cuentaDisenos = 2;
      }
      $object['data']['cuentaDisenos'] = $cuentaDisenos;

      // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
      $sql = 'SELECT `departamento` FROM lotes_detalles WHERE id_orden = ' . $args['id_orden'] . ' GROUP BY departamento';
      $pActivos = $localConnection->goQuery($sql);
      $object['data']['pActivos'] = $pActivos;

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

      $pasoActual = max($x);
      $object['data']['pasoActual'] = $pasoActual;
      $totalPasos = count($pActivos);
      $object['data']['totalPasos'] = count($pActivos);

      if (!$totalPasos) {
        $totalPasos = 1;
      }

      $object['porcentaje'] = round($pasoActual * 100 / $totalPasos);
    } else {
      $pasoActual = 0;
      $object['data']['pasoActual'] = $pasoActual;
      $totalPasos = 0;
      $object['data']['totalPasos'] = 0;
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Detalles para la asignacion de personal V2
  $app->get('/lotes/detalles/v2/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $localConnection = new LocalDB();

    // OBTENER PRODUCTOS DEL LOTE
    // EXCLUIR DISEÑOS FILTRANDO POR NOMBRE
    $sql = "SELECT * FROM ordenes_productos WHERE category_name != 'Diseños' AND id_orden = " . $id;
    $object['query_orden_productos'] = $sql;
    $object['orden_productos'] = $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM lotes_detalles WHERE id_orden = ' . $id;
    $object['query_lotes_detalle'] = $sql;
    $object['lote_detalles'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // VERIFICAR SI EXISTE EMPLEADO ASIGNADO PARA ASIGNACION DE EMPLEADOS EN PRDUCCIÓN
  $app->get('/produccion/verificar-asignacion-empleado/{departamento}/{id_orden}/{id_ordenes_productos}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    /* $sql = "SELECT id_empleado FROM lotes_detalles WHERE id_orden = " . $args["id_orden"] . " AND id_ordenes_productos = " . $args["id_ordenes_productos"] . " AND departamento = '" . $args["departamento"] . "'";
    $object["sql"] = $sql; */

    $sql = 'SELECT
        lot.id_empleado, 
        emp.departamento emp_departamento,
        lot.departamento lot_departamento
    FROM
        lotes_detalles lot
      LEFT JOIN api_empresas.empresas_usuarios emp ON lot.id_empleado = emp.id_usuario
    WHERE
        lot.id_orden = ' . $args['id_orden'] . ' AND lot.id_ordenes_productos = ' . $args['id_ordenes_productos'] . " AND lot.departamento = '" . $args['departamento'] . "'";
    $object['sql'] = $sql;

    $resp = $localConnection->goQuery($sql);

    if (count($resp)) {
      $object = $resp[0];
    } else {
      $object['OKOK'] = $resp;
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Detalles para la asignacion de personal
  $app->get('/lotes/detalles/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $localConnection = new LocalDB();

    // OBTENER LOTE
    $sql = 'SELECT _id, lote, fecha, id_orden, paso  FROM lotes WHERE _id = ' . $id;
    $object['lote'] = $localConnection->goQuery($sql);

    // OBTENER PRODUCTOS DEL LOTE
    $sql = 'SELECT _id, name producto FROM ordenes_productos WHERE id_orden = ' . $id;
    $object['orden_productos'] = $localConnection->goQuery($sql);

    // OBTENER PAGOS
    $sql = 'SELECT * FROM pagos WHERE id_orden = ' . $id;
    $object['orden_pagos'] = $localConnection->goQuery($sql);

    // OBTENER DETALLES DEL LOTE
    $sql = 'SELECT * FROM lotes_detalles WHERE id_orden = ' . $id;
    $object['lote_detalles'] = $localConnection->goQuery($sql);
    $object['lote_detalles_SQL'] = $sql;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // obtener detalles de empleados de la orden

  $app->get('/ordenes/detalles/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT observaciones FROM ordenes WHERE _id = ' . $args['id'];
    $object['sql'] = $sql;
    $object['detalle'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // obtener ordenes vinculadas

  $app->get('/ordenes/vinculadas/{id_orden_father}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT id_child FROM ordenes_vinculadas WHERE id_father = ' . $args['id_orden_father'];
    $vinculadas = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($vinculadas));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN PRODUCCION */

  /** TRUNCAR ORDER Y LOTES */
  $app->post('/truncate', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    // Deshabilitar las restricciones de claves foráneas y truncar las tablas
    $sql = 'SET FOREIGN_KEY_CHECKS = 0;
            TRUNCATE `abonos`;
            TRUNCATE `aprobacion_clientes`;
            TRUNCATE `asistencias`;
            TRUNCATE `caja`;
            TRUNCATE `caja_cierres`;
            TRUNCATE `caja_fondos`;
            -- TRUNCATE `catalogo_insumos_productos`;
            -- TRUNCATE `catalogo_telas`;
            -- TRUNCATE `categories`;
            -- TRUNCATE `config`;
            -- TRUNCATE `customers`;
            -- TRUNCATE `departamentos`;
            TRUNCATE `disenos`;
            TRUNCATE `disenos_ajustes_y_personalizaciones`;
            -- TRUNCATE `inventario`;
            TRUNCATE `inventario_movimientos`;
            TRUNCATE `lotes`;
            TRUNCATE `lotes_detalles`;
            TRUNCATE `lotes_detalles_empleados_asignados`;
            TRUNCATE `lotes_detalles_empleados_asignados_pausas`;
            TRUNCATE `lotes_fisicos`;
            TRUNCATE `lotes_historico_solicitadas`;
            TRUNCATE `lotes_movimientos`;
            TRUNCATE `metodos_de_pago`;
            TRUNCATE `ordenes`;
            TRUNCATE `ordenes_borrador_empleado`;
            TRUNCATE `ordenes_fila_orden`;
            TRUNCATE `ordenes_fila_orden_cambios`;
            TRUNCATE `ordenes_fila_reposiciones`;
            TRUNCATE `ordenes_productos`;
            -- TRUNCATE `ordenes_tmp`;
            TRUNCATE `ordenes_vinculadas`;
            TRUNCATE `pagos`;
            TRUNCATE `piezas_cortadas`;
            TRUNCATE `presupuestos`;
            TRUNCATE `presupuestos_productos`;
            -- TRUNCATE `products`;
            -- TRUNCATE `products_attributes`;
            -- TRUNCATE `products_attributes_values`;
            -- TRUNCATE `products_comisiones`;
            -- TRUNCATE `products_prices`;
            -- TRUNCATE `products_tiempos_de_produccion`;
            -- TRUNCATE `products_insumos_asignados`;
            TRUNCATE `rendimiento`;
            TRUNCATE `reposiciones`;
            TRUNCATE `retiros`;
            TRUNCATE `revisiones`;
            -- TRUNCATE `sizes`;
            TRUNCATE `tintas`;
            SET FOREIGN_KEY_CHECKS = 1;
        ';

    // Ejecutar el comando de truncado
    $localConnection->goQuery($sql);

    // Obtener la lista de tablas y su cantidad de registros
    $sql_tables = "
            SELECT 
                table_name AS 'Tabla', 
                table_rows AS 'Registros' 
            FROM 
                information_schema.tables 
            WHERE 
                table_schema = DATABASE() 
            ORDER BY 
                table_name;
        ";

    $table_data = $localConnection->goQuery($sql_tables);
    $localConnection->disconnect();

    // Preparar la respuesta con la lista de tablas y su cantidad de registros
    $response->getBody()->write(json_encode([
      'message' => 'Tablas truncadas y registros contados correctamente.',
      'tables' => $table_data,
    ]));

    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  /** Revisión */

  // CREAR UNA NUEVA REVISIONrevision
  $app->post('/revision/nuevo', function (Request $request, Response $response) {
    $miRevision = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
    // $object['sql_MAX_REVIEW'] = $sql;
    $tmpRevID = $localConnection->goQuery($sql);

    if ($tmpRevID[0]['revision'] === null) {
      $currID = 1;
    } else {
      $currID = intval($tmpRevID[0]['revision']) + 1;
    }

    // CREAR REVISION
    $values = '(';
    $values .= "'" . $miRevision['id_diseno'] . "',";
    $values .= "'" . $miRevision['id_orden'] . "',";
    $values .= "'" . $miRevision['id_empleado'] . "',";
    $values .= "'" . $currID . "')";

    $sql = 'INSERT INTO revisiones (`id_diseno`, `id_orden`, `id_empleado`, `revision`) VALUES ' . $values;
    $object['response_insert'] = json_encode($localConnection->goQuery($sql));

    $object['sql_insert'] = $sql;

    $sql =
      'SELECT * FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'] . ' AND id_empleado = ' . $miRevision['id_empleado'];
    $tmpRevision = $localConnection->goQuery($sql);

    if (count($tmpRevision) > 0) {
      $object['revision'] = $tmpRevision[0];
    } else {
      $object['revision'] = $tmpRevision;
    }

    $object['sql_get_review'] = $sql;

    // obtener numero de la última revision
    $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'] . ' AND id_empleado = ' . $miRevision['id_empleado'];

    // $object['sql_MAX_REVIEW'] = $sql;
    $object['lastId'] = $localConnection->goQuery($sql);

    $object['image_name'] = $miRevision['id_orden'] . '-' . $miRevision['id_diseno'] . '-' . $object['lastId'][0]['revision'];

    $sql = 'SELECT
            a.id_orden imagen,
            a.id_orden vinculadas,
            a.tipo,
            a.id_orden id,
            a.id_empleado empleado,
            b.responsable
        FROM
            disenos a
        JOIN ordenes b ON
            b._id = a.id_orden
        WHERE
            a.id_empleado = ' . $miRevision['id_empleado'] . " AND a.tipo NOT LIKE 'no' AND(
                a.terminado = 0 AND b.status NOT LIKE 'entregada' AND b.status != 'cancelada' AND b.status NOT LIKE 'terminado')";
    // $object['sql_new_data'] = $sql;
    $object['new_data'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /* // CREAR UNA NUEVA REVISION CON VERIFICACION DE REVISION EXISTENTE
  $app->post('/revision/nuevo', function (Request $request, Response $response) {
      $miRevision = $request->getParsedBody();
      $localConnection = new LocalDB();

      // verificar que la revision exista
      $sql = 'SELECT _id FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
      $object['sql_count'] = $sql;
      // obtener numero de la última revision
      $object['exist'] = $exist = $localConnection->goQuery($sql);

      if (count($exist) > 0) {
          // UPDATE
          $sql = "UPDATE revisiones SET estatus = 'Esperando Respuesta' WHERE id_diseno = " . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
          $object['response_update'] = json_encode($localConnection->goQuery($sql));
          $localConnection->disconnect();

      } else {
          $object['sql_MAX_REVIEW'] = $sql;
          $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
          $tmpRevID = $localConnection->goQuery($sql);

          if ($tmpRevID[0]['revision'] === null) {
              $currID = 1;
          } else {
              $currID = intval($tmpRevID[0]['revision']) + 1;
          }

          // CREAR REVISION
          $values = '(';
          $values .= "'" . $miRevision['id_diseno'] . "',";
          $values .= "'" . $miRevision['id_orden'] . "',";
          $values .= "'" . $currID . "')";

          $sql = 'INSERT INTO revisiones (`id_diseno`, `id_orden`, `revision`) VALUES ' . $values;
          $object['response_insert'] = json_encode($localConnection->goQuery($sql));

          $object['sql_insert'] = $sql;

          $sql =
              'SELECT * FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
          $tmpRevision = $localConnection->goQuery($sql);

          if (count($tmpRevision) > 0) {
              $object['revision'] = $tmpRevision[0];
          } else {
              $object['revision'] = $tmpRevision;
          }

          $object['sql_get_review'] = $sql;

          // obtener numero de la última revision
          $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];

          $object['sql_MAX_REVIEW'] = $sql;
          $object['lastId'] = $localConnection->goQuery($sql);

          $object['image_name'] = $miRevision['id_orden'] . '-' . $miRevision['id_diseno'] . '-' . $object['lastId'][0]['revision'];

          $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = '" . $miRevision['id_empleado'] . "' a.tipo != 'no' AND (a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado')";
          $object['new_data'] = $localConnection->goQuery($sql);
      }

      $localConnection->disconnect();

      $response->getBody()->write(json_encode($object));
      return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
  }); */

  // OBTENER DATOS DE LA REVISION DE UN DISEÑO POR SU ID
  $app->get('/revision/diseno/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    /* $sql = 'SELECT
        a._id id_revision,
        a.id_diseno,
        a.id_orden,
        a.id_product,
        a.revision,
        a.estatus,
        a.detalles
    FROM
        revisiones a
    LEFT JOIN disenos d ON a.id_diseno = d._id AND a.id_orden = d.id_orden AND a.id_empleado = d.id_empleado
    WHERE
        a.id_orden =' . $args['id'] . ' ORDER BY
        a._id
    DESC'; */
    $sql = 'SELECT a._id id_revision, a.id_orden, a.id_diseno, a.id_empleado, a.id_product, a.revision, a.estatus, a.detalles FROM revisiones a JOIN disenos b ON b.id_orden = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND b.id_empleado = ' . $args['id_empleado'] . ' ORDER BY a._id DESC';
    $object['sql'] = $sql;
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER ESTATUS DE LA REVISION
  $app->get('/revisiones/estatus/{id_revision}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT
            rev.estatus,
            rev.detalles,
            dis.id_product
        FROM
            revisiones rev
        LEFT JOIN disenos dis ON rev.id_diseno = dis._id AND rev.id_orden = dis.id_orden AND rev.id_empleado = dis.id_empleado
        WHERE
            rev._id = ' . $args['id_revision'];
    // $object = $localConnection->goQuery($sql)[0];
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Datos para la revisiond e trabajos
  $app->get('/revision/trabajos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT a._id id_lotes_detalles, a.id_orden, b.name producto, b.cantidad, c.nombre empleado, d.estatus, d._id id_pagos, e.status estatus_orden FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id JOIN empleados c ON a.id_empleado = c._id JOIN pagos d ON d.id_lotes_detalles = a._id JOIN ordenes e ON e._id = a.id_orden WHERE (e.status = 'Activa' OR e.status = 'Pausada' OR e.status = 'En espera') AND d.estatus = 'aprobado'";
    $object['sql'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Update estatus de pago
  $app->get('/revision/actualizar-estatus-de-pago/{estatus}/{id_pago}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "UPDATE pagos SET estatus = '" . $args['estatus'] . "' WHERE _id = " . $args['id_pago'];
    $object['sql'] = $sql;
    $object['save'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** Empleados */

  // Guardar Treas
  $app->post('/empleados/tareas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $time_terminado = $myDate->today();

    /**
     * Determinamos si terminado = 0 eliminamos el registro de la tabla
     * y si terminado = 1 creamos el registro
     */
    $miTerminado = intval($data['terminado']);

    if ($miTerminado) {
      $sql = "INSERT INTO check_tareas (
        id_orden,
        id_lotes_detalles_empleados_asigandos,
        id_ordenes_productos,
        id_departamento,
        id_empleado,
        moment
    ) VALUES (
        {$data['id_orden']},
        {$data['id_lotes_detalles']},
        {$data['id_ordenes_productos']},
        {$data['id_departamento']},
        {$data['id_empleado']},
        '{$time_terminado}'
    )";
    } else {
      $sql = "DELETE FROM `check_tareas` 
        WHERE id_orden = {$data['id_orden']} 
        AND id_empleado = {$data['id_empleado']} 
        AND id_departamento = {$data['id_departamento']} 
        AND id_lotes_detalles_empleados_asigandos = {$data['id_lotes_detalles']} 
        AND id_ordenes_productos = {$data['id_ordenes_productos']}";
    }

    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Guardar tintas
  $app->post('/empleados/tintas', function (Request $request, Response $response) {
    $misTintas = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear estructura de valores para insertar nuevo cliente
    $values = '(';
    $values .= (isset($misTintas['c']) && $misTintas['c'] !== '' && $misTintas['c'] !== null && $misTintas['c'] !== 'null') ? "'" . $misTintas['c'] . "'" : 'NULL';
    $values .= ',';
    $values .= (isset($misTintas['m']) && $misTintas['m'] !== '' && $misTintas['m'] !== null && $misTintas['m'] !== 'null') ? "'" . $misTintas['m'] . "'" : 'NULL';
    $values .= ',';
    $values .= (isset($misTintas['y']) && $misTintas['y'] !== '' && $misTintas['y'] !== null && $misTintas['y'] !== 'null') ? "'" . $misTintas['y'] . "'" : 'NULL';
    $values .= ',';
    $values .= (isset($misTintas['k']) && $misTintas['k'] !== '' && $misTintas['k'] !== null && $misTintas['k'] !== 'null') ? "'" . $misTintas['k'] . "'" : 'NULL';
    $values .= ',';
    $values .= (isset($misTintas['w']) && $misTintas['w'] !== '' && $misTintas['w'] !== null && $misTintas['w'] !== 'null') ? "'" . $misTintas['w'] . "'" : 'NULL';
    $values .= ',';
    $values .= "'" . $misTintas['id_orden'] . "',";
    $values .= "'" . $misTintas['id_empleado'] . "',";
    $values .= "'" . $misTintas['id_impresora'] . "')";

    $sql = 'INSERT INTO tintas (`c`, `m`, `y`, `k`, `w`, `id_orden`, `id_empleado`, `id_catalogo_impresoras`) VALUES ' . $values;
    $object['sql'] = $sql;

    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Cargar datos adicionales para el calculo del rendimiento del material
  $app->post('/insumos/rendimiento', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // 0- Preparar datos
    if ($data['departamento'] === 'Impresión') {
      $campo_valor = 'metros';
      $campo_empleado = 'id_empleado_impresion';
    }
    if ($data['departamento'] === 'Estampado') {
      $campo_valor = 'id_insumo';
      $campo_empleado = 'id_empleado_estampado';
    }
    if ($data['departamento'] === 'Corte') {
      $campo_valor = 'desperdicio';
      $campo_empleado = 'id_empleado_corte';
    }

    // 1- Determinar si el registro existe (INSERT o UPDATE)
    $sql = 'SELECT COUNT(id_orden) FROM rendimiento WHERE id_orden = ' . $data['id_orden'];
    $exist = $localConnection->goQuery($sql);

    if ($exist > 0) {
      // $sql = "INSERT INTO rendimiento (id_orden, id_insumo, " . $campo_empleado . ", " . $campo_valor . ") VALUES (" . $data["id_orden"] . ", " . $data["id_insumo"] . ", " . $data["id_empleado"] . ", " . $data["valor"] . ");";
      $sql = 'INSERT INTO rendimiento (id_orden, ' . $campo_empleado . ', ' . $campo_valor . ') VALUES (' . $data['id_orden'] . ', ' . $data['id_empleado'] . ', ' . $data['valor'] . ');';
    } else {
      $sql = 'UPDATE rendimiento SET ' . $campo_empleado . ' = ' . $data['id_empleado'] . ', ' . $campo_valor . ' = ' . $data['valor'] . ' WHERE id_orden = ' . $data['id_orden'] . ';';
    }

    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  //

  // Control de de estado del proceso de produccion del empleado con varios empleados
  $app->post('/registrar-paso-empleado', function (Request $request, Response $response, array $args) {
    $miEmpleado = $request->getParsedBody();
    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();
    $sql = '';

    $localConnection = new LocalDB();

    $object['departamento'] = $miEmpleado['departamento'];
    $object['tipo'] = $miEmpleado['tipo'];

    // CALCULAR CANTIDAD DE PIEZAS ELABORADAS POR EMPLEADO
    /* $sqlxxx = "SELECT
            (a.unidades_solicitadas * b.procentaje_comision / 100) piezas
        FROM
            lotes_detalles a
        JOIN lotes_detalles_empleados_asignados b ON b.id_orden = a.id_orden
        WHERE
            a.id_orden = {$miEmpleado['id_orden']} AND a.id_departamento = {$miEmpleado['id_departamento']}
    "; */

    if ($miEmpleado['tipo'] === 'inicio') {
      $campo = 'fecha_inicio';
      $progreso = 'en curso';

      $sqln = "UPDATE lotes SET paso = '{$miEmpleado['departamento']}', id_departamento_actual = {$miEmpleado['id_departamento']}  WHERE id_orden = " . $miEmpleado['id_orden'];
      $object['sql_update_lotes'] = $sqln;
      $response_update = $localConnection->goQuery($sqln);
      $object['response_update_lotes'] = $response_update;
    }

    if ($miEmpleado['tipo'] === 'fin') {
      // Procesar REposición
      if ($miEmpleado['es_reposicion']) {
        // Procesar el termino de la reposición aquí
        // Terminar reposicion
        // $sqlRepo = "UPDATE reposiciones SET terminada = 1 WHERE _id = {$miEmpleado['id_reposicion']};";
        // $sqlRepo .= "UPDATE reposiciones SET terminada = 1 WHERE _id = {$miEmpleado['id_reposicion']}";
        // $sqlRepo .= "DELETE FROM `ordenes_fila_reposiciones` WHERE _id = {$miInsumo['id_orden']};";

        // $response_update_reposicion = $localConnection->goQuery($sqlRepo);

        // Emininar reposcion de orden_reposiciones
        // Verificar si existe el departamento para actualziarlo
      }

      $pasoActual = intval($miEmpleado['orden_proceso']) + 1;
      // Buscar departamento para actualziarlo
      $sqlDep = "SELECT _id id_departamento, departamento, orden_proceso FROM departamentos WHERE asignar_numero_de_paso > 0 AND orden_proceso = $pasoActual";
      $object['sql_select_next_departament'] = $sqlDep;
      $response_departamentos = $localConnection->goQuery($sqlDep);

      // Verificar si existe el departamento, de no ser así indica que es el último paso.
      if (empty($response_departamentos)) {
        // Es el último paso debemos asignar terminado o el paso que viene despues de el último despues de producción
        $sql5 = "UPDATE lotes SET paso = 'terminado', id_departamento_actual = 0 WHERE id_orden = {$miEmpleado['id_orden']};";
      } else {
        // El paso existe, lo actualizamos para el semáforo y progressbar
        $departmentName = $response_departamentos[0]['departamento'] ?? 'terminado';  // Usar el nombre del departamento
        $next_department_id = $response_departamentos[0]['id_departamento'];  // Usar el ID correcto del departamento
        $sql5 = "UPDATE lotes SET paso = '{$departmentName}', id_departamento_actual = {$next_department_id} WHERE id_orden = {$miEmpleado['id_orden']};";
      }
      $response_update2 = $localConnection->goQuery($sql5);

      // Calculo de comisiones
      $sqlComisionEmpleado = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miEmpleado['id_empleado'];
      $respComisionEmpleado = $localConnection->goQuery($sqlComisionEmpleado);
      $object['rsp_empleados_comision'] = $respComisionEmpleado;  // Para depuración

      $comisionTipo = 'fija';  // Valor por defecto
      $comisionValue = 0;  // Valor por defecto
      if (!empty($respComisionEmpleado)) {
        $comisionTipo = $respComisionEmpleado[0]['comision_tipo'];
        $comisionValue = floatval($respComisionEmpleado[0]['comision']);
      }

      $object['comision_tipo'] = $comisionTipo;  // Para depuración

      $piezas = 0;  // Valor por defecto
      $id_lotes_detalles = null;  // Valor por defecto
      $totalComimision = 0;  // Valor por defecto

      // Consulta para obtener datos de comisión y otros datos relacionados
      // Se asume que esta consulta devuelve una única fila o que la primera fila es suficiente.
      // Si la estructura de la consulta es más compleja y puede devolver múltiples filas, se necesitaría un manejo diferente.
      $sql = "SELECT 
                    a._id AS id_lotes_detalles,
                    a.procentaje_comision,
                    b.comision AS comision_fija,
                    d.comision AS comision_variable,
                    (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = a.id_orden) AS total_productos,
                    (SELECT COUNT(id_empleado) FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_departamento = {$miEmpleado['id_departamento']}) AS cantidad_empleados_asigandos,
                    ((SUM(c.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_variable,
                    ((SUM(c.cantidad) * b.comision) * a.procentaje_comision / 100) AS total_comision_fija
                FROM
                    lotes_detalles_empleados_asignados a
                JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
                JOIN ordenes_productos c ON c.id_orden = a.id_orden
                JOIN products d ON d._id = c.id_woo
                WHERE a.id_empleado = {$miEmpleado['id_empleado']} AND a.id_orden = {$miEmpleado['id_orden']}
            ";

      $respComision = $localConnection->goQuery($sql);

      $piezas = $respComision[0]['total_productos'];
      $id_lotes_detalles = $respComision[0]['id_lotes_detalles'];

      if ($comisionTipo === 'fija') {
        $comimision = $respComision[0]['comision_fija'];
        $totalComimision = $respComision[0]['total_comision_fija'];
      } else {
        $comimision = $respComision[0]['comision_variable'];
        $totalComimision = $respComision[0]['total_comision_variable'];
      }

      // GUARDAR PAGO
      // Determinar unidades dependiendo si es una orden o una repoición
      if ($miEmpleado['es_reposicion']) {
        // $piezas = Buscar Unidades en la tabla reposiciones con el ID de la reposición
        $sqlUnidades = "SELECT unidades FROM reposiciones WHERE _id = {$miEmpleado['id_reposicion']}";
        $piezas = $localConnection->goQuery($sqlUnidades)[0]['unidades'];
      } else {
        // Ya tenemos la cantidad en la variable `$piezas`
      }

      // foreach ($respComision as $key => $item) {
      $sql = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $miEmpleado['id_orden'] . ', ' . $miEmpleado['id_reposicion'] . ', ' . $miEmpleado['id_departamento'] . ', ' . $comimision . ", '" . $comisionTipo . "', " . $piezas . ', ' . $id_lotes_detalles . ", 'aprobado', " . $totalComimision . ', ' . $miEmpleado['id_empleado'] . ", '" . $miEmpleado['departamento'] . "');";
      $object['sql_pagos'][] = $sql;
      $object['resp_pagos'] = $localConnection->goQuery($sql);
      // }

      $campo = 'fecha_terminado';
      $progreso = 'terminada';
    }

    // ACTUALIZAR DATOS DE INICIO DE TAREA
    $sql = 'UPDATE lotes_detalles SET ' . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE id_departamento = " . $miEmpleado['id_departamento'] . ' AND id_orden = ' . $miEmpleado['id_orden'];
    $object['sql_update_ld'] = $sql;
    $object['sql_update_lotes_detalles'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $sql = "UPDATE lotes_detalles_empleados_asignados SET $campo = '$now', progreso = '$progreso' WHERE id_departamento = {$miEmpleado['id_departamento']} AND id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']};";
    $object['result_update_lotes_detalles_detalles_empleados'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Control de de estado del proceso de produccion del empleado
  $app->post('/empleados/registrar-paso/{tipo}/{departamento}/{id_lotes_detalles}/{unidades}', function (Request $request, Response $response, array $args) {
    // PREPARAR FECHAS
    $localConnection = new LocalDB();
    $myDate = new CustomTime();
    $now = $myDate->today();
    $sql = '';
    $object['departamento'] = $args['departamento'];
    $object['tipo'] = $args['tipo'];

    // BUSCAR ID DEL EMPLEADO
    $sqlxxx = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
    $miEmpleado = $localConnection->goQuery($sqlxxx);

    // REGISTRAR EL PASO ACTUAL EN lotes
    $sql = 'SELECT id_orden FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'] . ';';
    $object['sql_total_pendientes'] = $sql;
    $id_orden = $localConnection->goQuery($sql)[0]['id_orden'];
    $object['id_orden'] = $id_orden;

    if ($args['tipo'] === 'inicio') {
      $campo = 'fecha_inicio';
      $progreso = 'en curso';

      $sqln = "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $object['id_orden'];
      $object['sql_update_lotes'] = $sqln;
      $response_update = $localConnection->goQuery($sqln);
      $object['response_update'] = $response_update;
    }

    if ($args['tipo'] === 'fin') {
      $sqle = 'SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
      $respLotesDetalles = $localConnection->goQuery($sqle);

      $object['resp'] = $respLotesDetalles;

      // BUSCAR TIPO DE COMISION DEL EMPLEADO
      // BUSCAR COMISION DEL VENDEDOR
      $sql = 'SELECT comision, comision_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miEmpleado[0]['id_empleado'];
      $respComision = $localConnection->goQuery($sql);
      $object['rsp_empleados'] = $respComision;
      $comisionTipo = $respComision[0]['comision_tipo'];

      // DETERMINAR TIPO DE COMISION
      if ($comisionTipo === 'variable') {
        // Buscar comision en el producto
        $sqlc = 'SELECT b.comision FROM lotes_detalles a JOIN products b ON b._id = a.id_woo WHERE a._id = ' . $args['id_lotes_detalles'];
        $object['sql_comision_variable'] = $sqlc;
        $comisionEmpleado = $localConnection->goQuery($sqlc);
        $miComision = $comisionEmpleado[0]['comision'];
      } else {
        // Preparar comision del registro del empleado (Multipliar la comision en la tabla products_comisiones=>comision por el porcentaje asingado en la tabla lotes_detalles_empleados_asignados => porcentaje_comsion)

        // Buscar id de la orden

        $comisionFloat = floatval($respComision[0]['comision']);
        $floatValue = floatval($comisionFloat);
        $miComision = number_format($floatValue, 2);
      }

      $monto_pago = floatval($miComision) * floatval($args['unidades']);

      /* if ($args['departamento'] === 'Costura') {
          $sql_comision = 'SELECT sys_comision_de_costura tipo FROM config';
          $tipo_comision = $localConnection->goQuery($sql_comision)[0]['tipo'];
          // $tipo_comision = $tmp_comision["tipo"];

          if ($tipo_comision === 'producto') {
              $sqlc = 'SELECT b.comision FROM lotes_detalles a JOIN products b ON b._id = a.id_woo WHERE a._id = ' . $args['id_lotes_detalles'];
          } else {
              $sqlc = 'SELECT comision FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $respLotesDetalles[0]['id_empleado'];
          }
      } else {
          $sqlc = 'SELECT comision FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $respLotesDetalles[0]['id_empleado'];
      } */
      // CALCULAR MONTO DEL PAGO

      // $sqlc = "SELECT comision FROM api_empresas.empresas_usuarios WHERE id_usuario = " . $respLotesDetalles[0]["id_empleado"];
      /* $comisionEmpleado = $localConnection->goQuery($sqlc);
      $object['comision'] = $respLotesDetalles;

      $calculo_pago = floatval($comisionEmpleado[0]['comision']) * floatval($args['unidades']);

      // $monto_pago = number_format($calculo_pago, 2);
      $monto_pago = $calculo_pago;
      $object['monto_pago'] = $monto_pago; */

      // GUARDAR PAGO
      $sql = 'INSERT INTO pagos(id_orden, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) 
        VALUES (' . $object['id_orden'] . ', ' . $miComision . ", '" . $comisionTipo . "', " . $args['unidades'] . ', ' . $args['id_lotes_detalles'] . ", 'aprobado', " . $monto_pago . ', ' . $miEmpleado[0]['id_empleado'] . ", '" . $args['departamento'] . "');";

      $campo = 'fecha_terminado';

      $progreso = 'terminada';
    }

    // ACTUALIZAR DATOS DE INICIO DE TAREA
    $sql .= 'UPDATE lotes_detalles SET ' . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $args['id_lotes_detalles'];
    $object['sql'] = $sql;
    $object['sql_update_pagos'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/empleados/registrar-paso-por-lotes/{departamento}', function (Request $request, Response $response, array $args) {
    // OBTENER DATOS VIA POST
    $misTareas = $request->getParsedBody();
    $object['request'] = json_decode($misTareas['item']);
    $object['args'] = $args;
    $localConnection = new LocalDB();

    $myDate = new CustomTime();
    $now = $myDate->today();
    $sql = '';
    $tipo_fecha = '';
    $progreso = '';

    foreach ($object['request'] as $key => $value) {
      $object['foreach'][$key] = $value->id_lotes_detalles;
      $id_orden = $value->id_orden;

      $object['progreso'] = $value->progreso;
      if ($value->progreso === 'por iniciar') {
        $tipo_fecha = 'fecha_inicio';
        $progreso = 'en curso';
        $sql .= "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $id_orden . ';';
      } else if ($value->progreso === 'en curso') {
        $tipo_fecha = 'fecha_terminado';
        $progreso = 'terminado';
        $sql .= "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $id_orden . ';';

        $sqle = 'SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
        $respLotesDetalles = $localConnection->goQuery($sqle);

        if ($args['departamento'] === 'Costura') {
          $sqlpr = 'SELECT id_woo FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
          $res_lotes_detalles = $localConnection->goQuery($sqlpr)[0]['id_woo'];

          $id_prod = intval($res_lotes_detalles);
          $woo = new WooMe();
          $prod_woo = $woo->getProductById($id_prod);
          $object['product_woo'] = $prod_woo;

          // $object["product-attributes"] = $prod_woo->attributes;
          if (empty($prod_woo->attributes)) {
            $monto_pago = 0;
            $object['product-attributes-vacio'] = true;
          } else {
            $object['product-attributes-vacio'] = false;
            $object['procesar_pago']['unidades'] = $respLotesDetalles[0]['unidades'];
            $object['procesar_pago']['comison_woo'] = floatval($prod_woo->attributes[0]->options[0]);
            $calculo_pago = intval($respLotesDetalles[0]['unidades']) * floatval($prod_woo->attributes[0]->options[0]);
            $monto_pago = number_format($calculo_pago, 2);
            $object['procesar_pago']['monto_pago'] = $monto_pago;
          }
        } else {
          // CALCULAR MONTO DEL PAGO

          $sqlc = 'SELECT comision FROM empleados WHERE _id = ' . $respLotesDetalles[0]['id_empleado'];
          $comisionEmpleado = $localConnection->goQuery($sqlc);
          $object['comision'] = $respLotesDetalles;

          $calculo_pago = floatval($comisionEmpleado[0]['comision']) * floatval($respLotesDetalles[0]['unidades']);
          // $monto_pago = number_format($calculo_pago, 2);
          $monto_pago = $calculo_pago;
          $object['monto_pago'] = $monto_pago;
        }

        // GUARDAR PAGO
        $sqlxxx = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
        $miEmpleado = $localConnection->goQuery($sqlxxx);

        $sql .= 'INSERT INTO pagos(id_orden, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $id_orden . ', ' . $respLotesDetalles[0]['unidades'] . ', ' . $value->id_lotes_detalles . ", 'aprobado' , " . $monto_pago . ', ' . $miEmpleado[0]['id_empleado'] . ", '" . $args['departamento'] . "');";
        $tipo_fecha = 'fecha_terminado';
        $progreso = 'terminada';
      }

      $sql .= 'UPDATE lotes_detalles SET ' . $tipo_fecha . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $value->id_lotes_detalles . ';';
    }

    $object['sql'] = $sql;
    $result_sql = $localConnection->goQuery($sql);
    $object['result_sql'] = $result_sql;

    $localConnection->disconnect();

    // $object["goQuery_response"] = $localConnection->goQuery($sql);

    /* foreach ($object["request"] as $key => $value) {
            $id_lotes_detalles = $value->id_lotes_detalles;
// PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();
            $sql = "";
            $object["departamento"] = $args["departamento"];
            $object["tipo"] = $args["tipo"];
// REGISTRAR EL PASO ACTUAL EN lotes
            $id_orden = $value->id_orden;
            if ($args["tipo"] === "inicio") {
                $campo = "fecha_inicio";
                $progreso = "en curso";
                $sqln = "UPDATE lotes SET paso = '" . $args["departamento"] . "' WHERE id_orden = " . $id_orden;
                $object["sql_update_lotes"] = $sqln;
                $object["response_update"] = $localConnection->goQuery($sqln);
            }
            if ($args["tipo"] === "fin") {
                $sqle = "SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                $respLotesDetalles = $localConnection->goQuery($sqle);
                $object["resp"] = $respLotesDetalles;
                if ($args["departamento"] === "Costura") {
                    $sqlpr = "SELECT id_woo FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                    $res_lotes_detalles = $localConnection->goQuery($sqlpr)[0]["id_woo"];
                    $id_prod = intval($res_lotes_detalles);
                    $woo = new WooMe();
                    $prod_woo = $woo->getProductById($id_prod);
// $object["product_woo"] = $prod_woo;
                    $object["product-attributes"] = $prod_woo->attributes;
                    if (empty($prod_woo->attributes)) {
                        $monto_pago = 0;
                        $object["product-attributes-vacio"] = true;
                        } else {
                            $object["product-attributes-vacio"] = false;
                            $object["procesar_pago"]["unidades"] = $respLotesDetalles[0]["unidades"];
                            $object["procesar_pago"]["comison_woo"] = floatval($prod_woo->attributes[0]->options[0]);
                            $calculo_pago = intval($respLotesDetalles[0]["unidades"]) * floatval($prod_woo->attributes[0]->options[0]);
                            $monto_pago = number_format($calculo_pago, 2);
                            $object["procesar_pago"]["monto_pago"] = $monto_pago;
                        }
                        } else {
// CALCULAR MONTO DEL PAGO
                            $sqlc = "SELECT comision FROM empleados WHERE _id = " . $respLotesDetalles[0]["id_empleado"];
                            $comisionEmpleado = $localConnection->goQuery($sqlc);
                            $object["comision"] = $respLotesDetalles;
                            $calculo_pago = floatval($comisionEmpleado[0]["comision"]) * floatval($respLotesDetalles[0]["unidades"]);
// $monto_pago = number_format($calculo_pago, 2);
                            $monto_pago = $calculo_pago;
                            $object["monto_pago"] = $monto_pago;
                        }
// GUARDAR PAGO
                        $sqlxxx = "SELECT id_empleado FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                        $miEmpleado = $localConnection->goQuery($sqlxxx);
                        $sql .= "INSERT INTO pagos(id_orden, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (" . $id_orden . ", " . $respLotesDetalles[0]["unidades"] . ", " . $id_lotes_detalles . ", 'aprobado' , " . $monto_pago . ", " . $miEmpleado[0]["id_empleado"] . ", '" . $args["departamento"] . "');";
                        $campo = "fecha_terminado";
                        $progreso = "terminada";
                    }
// ACTUALIZAR DATOS DE INICIO DE TAREA
                    $sql .= "UPDATE lotes_detalles SET " . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $id_lotes_detalles;
                    $object['items'] = $localConnection->goQuery($sql);
                } */

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Resgistrar pago del empleado en el momento que indica que ha terminado su tarea
  $app->get('/empleados/registrar-pago/{id_lotes_detalles}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
    $miEmpleado = $localConnection->goQuery($sql);

    $sql = 'INSERT INTO pagos(id_lotes_detalles, estatus, id_empleado) VALUES (' . $args['id_lotes_detalles'] . ", 'aprobado', " . $miEmpleado['id_empleado'] . ')';
    $object['sql'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener ordenes asociadas a los empleados
  $app->get('/empleados/ordenes-asignadas/v1/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT c.prioridad, a.id_orden, b.unidades_solicitadas, b.unidades_solicitadas piezas_actuales, b.fecha_inicio, b.fecha_terminado, b._id id_lotes_detalles, b.departamento, a.id_woo, a._id id_ordenes_productos, a.name producto, b.id_empleado, a.talla, a.corte, a.tela, b.departamento, b.progreso, b.detalles detalles_revision FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos LEFT JOIN lotes c ON c.id_orden = b.id_orden WHERE b.id_empleado = ' . $args['id_empleado'] . " AND b.progreso NOT LIKE 'terminada' ORDER BY c.prioridad DESC , b.progreso ASC, b.id_orden ASC";

    $items = $localConnection->goQuery($sql);
    $object['ordenes'] = $items;

    /* $sql = "SELECT a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = " . $args['id_empleado'] . " AND progreso = 'en curso'";
        $object['trabajos_en_curso'] = $localConnection->goQuery($sql); */

    // BUSCAR PAGOS EXISTENTES PARA LOS REGISTROS ENCONTRADOS EN EL PASO ANTERIOR
    $object['pagos'] = [];
    if (empty($ordenes)) {
      $object['pagos'] = [];
    } else {
      foreach ($ordenes as $key => $item_lote) {
        $sqlx = 'SELECT id_lotes_detalles, monto_pago, estatus, fecha_pago FROM pagos WHERE id_lotes_detalles = ' . $item_lote['id_lotes_detalles'];
        $tmpPago = $localConnection->goQuery($sqlx);

        if (!empty($tmpPago)) {
          $object['pagos'][] = $tmpPago;
        }
      }
    }

    $object['fields'][0]['key'] = 'nombre';
    $object['fields'][0]['label'] = 'Nombre';
    $object['fields'][1]['key'] = 'username';
    $object['fields'][1]['label'] = 'Usuario';
    $object['fields'][2]['key'] = 'departamento';
    $object['fields'][2]['label'] = 'Departamento';
    $object['fields'][3]['key'] = 'acciones';
    $object['fields'][3]['label'] = 'Acciones';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTNERE DATOS DE RENDIMIENTOS (TIEMPOS E INSUMOS)
  $app->get('/rendimiento-empleado/{id_orden}/{id_departamento}/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT DISTINCT
                a._id id_lote_Detalles,
                a.id_orden,
                a.fecha_inicio,
                a.fecha_terminado,
                TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_terminado) AS tiempo_empleado,
                c.tiempo tiempo_estimado_de_produccion,
                (TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_terminado) - c.tiempo) rendimiento,
                b.id_woo id_producto,
                b.talla
            FROM
                lotes_detalles_empleados_asignados a
            JOIN ordenes_productos b ON b.id_orden = a.id_orden
            JOIN products_tiempos_de_produccion c ON c.id_product = b.id_woo AND c.id_departamento = {$args['id_departamento']}
            WHERE a.id_orden = {$args['id_orden']} AND a.id_empleado = {$args['id_empleado']}
        ";

    $rspRendimientoTiempo = $localConnection->goQuery($sql);
    $object['rendimiento'] = $rspRendimientoTiempo;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Registrar acciones de pausas
  $app->post('/pausas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();
    $sql = '';
    $status_order = 'En espera';

    if ($data['accion'] === 'iniciar') {
      // Actualizar status de la orden
      $status_order = 'pausada';

      // SQL Iniciar pausa
      $sql = "INSERT INTO lotes_detalles_empleados_asignados_pausas (motivo, pausa_inicio, id_lotes_detalles_empleados_asignados) VALUES ('{$data['motivo']}', '{$now}', '{$data['id_lote_detalles_empleados']}');";
    }

    if ($data['accion'] === 'reanudar') {
      $status_order = 'activa';
      $sql = "UPDATE lotes_detalles_empleados_asignados_pausas SET pausa_fin = '$now' WHERE _id = {$data['id_pausa']};";
    }

    if ($data['accion'] === 'eliminar') {
      $status_order = 'activa';
      $sql = '';  // Eliminar desde administración
    }

    if ($data['accion'] === 'editar') {
      $status_order = 'activa';
      $sql = '';  // Editar desde administración
    }

    // Actualizar Status de la orden
    $sql .= "UPDATE ordenes SET `status` = '$status_order' WHERE _id = {$data['id_orden']}";

    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // PROYECCION DE FECHAS DE ENTREGA DE ORDENES
  $app->get('/ordenes/proyeccion-entrega', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    // PRIMERA VERSION CON DETALLES POR DEPARTAMENTO
    /* $sql = "SELECT
            a.id_orden,
            a.id_woo AS id_producto,
            b.id_empleado,
            a.name AS nombre_producto,
            a.cantidad,
            dep._id AS id_departamento,
            dep.departamento AS nombre_departamento,
            b.fecha_inicio,
            b.fecha_terminado,
            (SELECT CONCAT(fecha_entrega, ' 08:30:00' ) FROM ordenes WHERE _id = a.id_orden) fecha_entrega_orden,
            d.tiempo AS tiempo_estimado_produccion,
            (SELECT SUM(tiempo) FROM products_tiempos_de_produccion WHERE id_product = a.id_woo) tiempo_acumulado,
            ofo.orden_fila AS orden_fila_orden,
            dep.orden_proceso AS orden_proceso_departamento
        FROM
            ordenes_productos a
        LEFT JOIN
            lotes_detalles_empleados_asignados b ON b.id_orden = a.id_orden
        JOIN
            products_tiempos_de_produccion d ON d.id_product = a.id_woo AND d.id_departamento = b.id_departamento
        JOIN
            ordenes c ON c._id = b.id_orden
        JOIN
            departamentos dep ON dep._id = b.id_departamento
        LEFT JOIN
            ordenes_fila_orden ofo ON ofo.id_orden = a.id_orden
        WHERE
            (c.status LIKE 'En espera' OR c.status LIKE 'activa')
        GROUP BY a._id, b.id_departamento
        ORDER BY
            ofo.orden_fila ASC,
            c._id ASC,
            dep.orden_proceso ASC,
            a.id_woo ASC;
    "; */

    // SEBUNDA VERSIÓN CON TIEMPO GLOBAL POR ORDEN
    $sql = "SELECT
                    a.id_orden,
                    c.status,
                    b.id_empleado, -- Asume un único empleado por orden-departamento
                    (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario =  b.id_empleado) nombre_empleado,
                    dep._id AS id_departamento,
                    dep.departamento AS nombre_departamento,
                    b.fecha_inicio,
                    b.fecha_terminado,                    
                    c.fecha_entrega fecha_entrega_de_la_orden,
                    (SELECT CONCAT(fecha_entrega, ' 08:30:00' ) FROM ordenes WHERE _id = a.id_orden) AS fecha_entrega_orden,
                    SUM(a.cantidad) total_unidades,
                    SUM(d.tiempo * a.cantidad) AS tiempo_total_orden_depto, -- Suma de tiempos de todos los productos por este depto
                    ofo.orden_fila AS orden_fila_orden,
                    dep.orden_proceso AS orden_proceso_departamento
                FROM
                    ordenes_productos a
                LEFT JOIN
                    lotes_detalles_empleados_asignados b ON b.id_orden = a.id_orden -- Sólo unimos por id_orden aquí
                LEFT JOIN
                    departamentos dep ON dep._id = b.id_departamento -- Primero unimos 'departamentos' para que 'dep' esté disponible
                LEFT JOIN
                    products_tiempos_de_produccion d ON d.id_product = a.id_woo AND d.id_departamento = dep._id -- Ahora 'dep._id' se puede usar aquí
                JOIN
                    ordenes c ON c._id = a.id_orden
                LEFT JOIN
                    ordenes_fila_orden ofo ON ofo.id_orden = a.id_orden
                WHERE
                    (c.status LIKE 'En espera' OR c.status LIKE 'activa' OR c.status LIKE 'pausada') AND (dep.departamento IS NOT NULL AND dep._id IS NOT NULL)
                GROUP BY
                    a.id_orden,
                    b.id_empleado,
                    dep._id,
                    dep.departamento,
                    b.fecha_inicio,
                    b.fecha_terminado,
                    -- fecha_entrega_orden, -- Las subconsultas escalares deben estar en GROUP BY si no son agregadas
                    ofo.orden_fila,
                    dep.orden_proceso
                ORDER BY
                    ofo.orden_fila ASC,
                    a.id_orden ASC,
                    dep.orden_proceso ASC;
        ";

    $rspRendimientoTiempo = $localConnection->goQuery($sql);
    $object = $rspRendimientoTiempo;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener ordenes asociadas a los empleados V2
  $app->get('/empleados/ordenes-asignadas/v2/{id_empleado}/{id_departamento}/{orden_proceso}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // Reposiciones
    // Buscar orden_proceso
    $sql = "SELECT orden_proceso FROM departamentos WHERE _id = {$args['id_departamento']}";
    $resp_orden_proceso = $localConnection->goQuery($sql);

    // Buscar reposiciones
    $sql = "SELECT
                a._id id_reposicion,
                a.id_orden,
                a.id_departamento,
                a.id_empleado,
                a.id_empleado_emisor,
                a.id_ordenes_productos,
                a.unidades,
                c.tela,
                a.detalle detalle_empleado,
                a.detalle_emisor,
                a.aprobada,
                a.terminada,
                b.fecha_entrega,
                c.id_woo id_producto,
                -- d.orden_proceso orden_proceso_empleado_en_departamentos,
                c.name nombre_producto,
                (SELECT nombre FROM sizes WHERE _id =  c.talla) talla,
                {$args['orden_proceso']} orden_proceso_recibido,                
                (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento_solicitante) orden_proceso_solicitante,
                (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento) orden_proceso_inicial,
                c.corte    
            FROM
                reposiciones a  
            LEFT JOIN ordenes b ON b._id = a.id_orden 
            JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
            LEFT JOIN departamentos d ON d._id = a.id_departamento
            WHERE a.terminada = 0
                AND {$args['orden_proceso']} >= (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento) -- Filtramos que no se incluyan departamentos ateriores al asignado
                AND {$args['orden_proceso']} <= (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento_solicitante) -- Filtramos que no se incluyan departamentos ateriores al asignado
                AND NOT EXISTS (
                    SELECT 1
                    FROM pagos p
                    WHERE p.id_reposicion = a._id
                    AND p.fecha_pago IS NULL
                    AND p.id_empleado = {$args['id_empleado']} 
                    AND p.id_departamento = {$args['id_departamento']} 
                )
                -- Filtramos por departamento para ver los logs de el departamento unicamente
        ";
    $object['sql_reposiciones'] = $sql;
    $object['reposiciones'] = $localConnection->goQuery($sql);

    $sql = "SELECT DISTINCT 
            a.id_orden,
            ofo.orden_fila,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = y.id_empleado) AS extra,
            (SELECT COUNT(_id) FROM reposiciones WHERE id_departamento = {$args['id_departamento']} AND id_empleado = {$args['id_empleado']} AND terminada = 0 AND id_orden = a.id_orden) AS en_reposiciones,
            (SELECT COUNT(_id) FROM tintas WHERE id_orden = a.id_orden) AS en_tintas,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = {$args['id_empleado']}) AS en_inv_mov,
            (SELECT valor_inicial FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_inicial,
            (SELECT valor_final FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_final,
            c.prioridad,
            z.unidades_produccion AS unidades_solicitadas,
            a.cantidad AS unidades,
            a.cantidad AS piezas_actuales,
            y.fecha_inicio,
            y.fecha_terminado,
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            -- Se eliminan las referencias a lotes_detalles (alias 'b')
            -- y.id_lotes_detalles AS id_lotes_detalles, -- Puedes descomentar esto para depurar si lo necesitas
            y._id AS lotes_detalles_empleados_asignados,
            y._id AS id_lotes_detalles_empleados_asignados,
            y.id_departamento, -- Tomado directamente de la asignación del empleado
            (SELECT MIN(dep.orden_proceso) FROM lotes_detalles_empleados_asignados ldea JOIN departamentos dep ON ldea.id_departamento = dep._id WHERE ldea.id_orden = y.id_orden) AS orden_proceso_min,
            (SELECT orden_proceso FROM departamentos WHERE _id = {$args['id_departamento']}) AS orden_proceso_departamento,            
            (SELECT orden_proceso FROM departamentos WHERE _id = c.id_departamento_actual) AS orden_proceso,
            c.id_departamento_actual,
            a.id_orden AS orden,
            a.id_woo,
            a._id AS id_ordenes_productos,
            a.name AS producto,
            y.id_empleado,
            x.detalle AS detalle_reposicion,
            (SELECT nombre FROM sizes WHERE _id = a.id_size) AS talla,
            a.corte,
            a.tela,
            tp.tiempo AS tiempo_produccion,
            y.procentaje_comision,
            c.paso,
            d.status,
            y.progreso,
            NULL AS detalles_revision -- Este campo venía de lotes_detalles, ahora es NULL
        FROM
            -- ============================ CAMBIO PRINCIPAL ============================
            -- El punto de partida ahora es la asignación del empleado
            lotes_detalles_empleados_asignados y
            -- Unimos con los productos a través del id_orden (menos preciso, pero necesario con los datos actuales)
            JOIN ordenes_productos a ON y.id_orden = a.id_orden
            -- ========================================================================
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c ON c.id_orden = y.id_orden -- Unido a través de 'y'
            LEFT JOIN lotes_historico_solicitadas z ON z.id_orden = a.id_orden
            LEFT JOIN products p ON p._id = a.id_woo
            LEFT JOIN products_tiempos_de_produccion tp ON tp.id_product = p._id AND tp.id_departamento = {$args['id_departamento']}
            LEFT JOIN reposiciones x ON x.id_orden = d._id AND x.id_empleado = y.id_empleado AND x.id_ordenes_productos = a._id
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = d._id
        WHERE  
            (y.id_empleado = {$args['id_empleado']})
            AND (d.status LIKE 'En espera' OR d.status LIKE 'activa' OR d.status LIKE 'pausada')
            AND p.fisico = 1 
            AND y.id_departamento = {$args['id_departamento']} -- El filtro del departamento ahora se aplica sobre la tabla 'y'
        ORDER BY
            ofo.orden_fila ASC,
            y.id_orden DESC,
            y.progreso ASC; -- El orden del progreso ahora se basa en 'y'
        ";

    // VERSION CON lotes_detalles en un JOIN (ya no funciona porque lotes_detalles no tiiene registros)
    /* $sql = "SELECT DISTINCT
            a.id_orden,
            ofo.orden_fila,
            -- Corregido: Usar y.id_empleado, que es la referencia correcta al empleado asignado.
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = y.id_empleado) AS extra,
            (SELECT COUNT(_id) FROM reposiciones WHERE id_departamento = {$args['id_departamento']} AND id_empleado = {$args['id_empleado']} AND terminada = 0 AND id_orden = a.id_orden) AS en_reposiciones,
            (SELECT COUNT(_id) FROM tintas WHERE id_orden = a.id_orden) AS en_tintas,
            (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = {$args['id_empleado']}) AS en_inv_mov,
            (SELECT valor_inicial FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_inicial,
            (SELECT valor_final FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_final,
            c.prioridad,
            z.unidades_produccion AS unidades_solicitadas,
            a.cantidad AS unidades,
            a.cantidad AS piezas_actuales,
            y.fecha_inicio,
            y.fecha_terminado,
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            b._id AS id_lotes_detalles,
            y._id AS lotes_detalles_empleados_asignados, -- Añadido alias AS
            y._id AS id_lotes_detalles_empleados_asignados, -- Añadido alias AS
            b.departamento,
            (SELECT MIN(dep.orden_proceso) FROM lotes_detalles_empleados_asignados ldea JOIN departamentos dep ON ldea.id_departamento = dep._id WHERE ldea.id_orden = y.id_orden) AS orden_proceso_min,
            (SELECT orden_proceso FROM departamentos WHERE _id = {$args['id_departamento']}) AS orden_proceso_departamento,
            c.id_departamento_actual AS orden_proceso, -- Añadido alias AS
            a.id_orden AS orden,
            a.id_woo,
            a._id AS id_ordenes_productos,
            a.name AS producto,
            y.id_empleado, -- Corregido: Tomamos el id_empleado de la tabla de asignaciones 'y'
            x.detalle AS detalle_reposicion,
            -- Corregido: La columna `a.talla` antigua ya no se selecciona para evitar conflicto de nombres.
            (SELECT nombre FROM sizes WHERE _id = a.id_size) AS talla, -- Corregido: usa `id_size` y tiene un alias claro.
            a.corte,
            a.tela,
            tp.tiempo AS tiempo_produccion,
            y.procentaje_comision,
            c.paso,
            d.status,
            y.progreso,
            b.detalles AS detalles_revision
        FROM
            ordenes_productos a
            JOIN lotes_detalles b ON a._id = b.id_ordenes_productos
            -- ============================ CAMBIO PRINCIPAL ============================
            -- Corregido el JOIN para usar la clave foránea correcta.
            JOIN lotes_detalles_empleados_asignados y ON y.id_lotes_detalles = b._id
            -- ========================================================================
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c ON c.id_orden = b.id_orden
            LEFT JOIN lotes_historico_solicitadas z ON z.id_orden = a.id_orden
            LEFT JOIN products p ON p._id = a.id_woo
            LEFT JOIN products_tiempos_de_produccion tp ON tp.id_product = p._id AND tp.id_departamento = {$args['id_departamento']}
            -- Corregido: El JOIN a reposiciones ahora usa y.id_empleado
            LEFT JOIN reposiciones x ON x.id_orden = d._id AND x.id_empleado = y.id_empleado AND x.id_ordenes_productos = a._id
            LEFT JOIN ordenes_fila_orden ofo ON ofo.id_orden = d._id
        WHERE
            (y.id_empleado = {$args['id_empleado']})
            AND (d.status LIKE 'En espera' OR d.status LIKE 'activa' OR d.status LIKE 'pausada')
            AND p.fisico = 1
            AND b.id_departamento = {$args['id_departamento']}
        ORDER BY
            ofo.orden_fila ASC,
            y.id_orden DESC,
            b.progreso ASC;
        "; */
    $object['sql_ordenes'] = $sql;
    $object['ordenes'] = $localConnection->goQuery($sql);

    // ORDENES VINCULADAS
    $sql = "SELECT
            a._id,
            a.id_child,
            a.id_father
        FROM
            ordenes_vinculadas a 
        LEFT JOIN ordenes b ON b._id = a.id_father
        WHERE b.status NOT LIKE 'pausada' OR b.status NOT LIKE 'cancelada' OR b.status NOT LIKE 'terminada'
        ORDER BY
            id_father ASC
        ";
    $object['vinculadas'] = $localConnection->goQuery($sql);

    // Pausas
    $sql = "SELECT
                a._id id_pausa,
                c._id id_orden,
                a.id_lotes_detalles_empleados_asignados,
                b.id_empleado,
                b.id_departamento,
                a.pausa_inicio,
                a.pausa_fin,
                a.motivo
            FROM
                lotes_detalles_empleados_asignados_pausas a 
            JOIN lotes_detalles_empleados_asignados b ON b._id = a.id_lotes_detalles_empleados_asignados
            LEFT JOIN ordenes c ON c._id = b.id_orden
            WHERE /* b.id_empleado = {$args['id_empleado']} AND b.id_departamento = {$args['id_departamento']} AND */(c.status LIKE 'pausada') AND a.pausa_fin IS NULL
            ORDER BY a._id ASC
        ";
    $object['pausas'] = $localConnection->goQuery($sql);

    // Deetalles de los productos
    /* $sql = 'SELECT DISTINCT
        a._id id_ordenes_productos,
        b.id_orden,
        r.terminada reposicion_terminada,
        b._id id_lotes_detalles,
        b.terminado,
        a.name,
        d.unidades_produccion cantidad_corte,
        a.cantidad,
        r.unidades unidades_reposicion,
        r.detalle detalle_reposicion,
        a.talla,
        a.corte,
        a.tela
        FROM
            ordenes_productos a
        LEFT JOIN
            lotes_detalles b ON a._id = b.id_ordenes_productos
        LEFT JOIN ordenes c ON c._id = b.id_orden
        LEFT JOIN lotes_historico_solicitadas d ON d.id_orden = a.id_orden
        LEFT JOIN reposiciones r ON r.id_ordenes_productos = a._id AND r.id_empleado
        WHERE
            b.id_empleado = ' . $args['id_empleado'] . " AND (c.status LIKE 'En espera' OR c.status LIKE 'activa') AND b.id_departamento = {$args['id_departamento']}
    ORDER BY b.id_orden ASC"; */

    $sql = "SELECT
                a._id id_ordenes_productos,
                a.id_woo id_product,
                a.id_orden,
                b._id id_lotes_detalles,
                r.terminada reposicion_terminada,
                -- b.terminado,
                ch.moment terminado,
                a.name,
                (SELECT nombre FROM sizes WHERE _id = a.talla) talla,
                r.unidades unidades_reposicion,
                r.detalle detalle_reposicion,
                a.cantidad,
                a.tela,
                a.corte
            FROM
                ordenes_productos a
            LEFT JOIN products p ON p._id = a.id_woo
            JOIN lotes_detalles_empleados_asignados b ON b.id_orden = a.id_orden 
            LEFT JOIN check_tareas ch ON 
              ch.id_ordenes_productos = a._id 
              AND ch.id_empleado = b.id_empleado 
              AND ch.id_orden = a.id_orden 
              AND ch.id_departamento = b.id_departamento 
              AND ch.id_lotes_detalles_empleados_asigandos = b._id
            LEFT JOIN reposiciones r ON r.id_ordenes_productos = a._id AND r.id_empleado
            WHERE b.id_empleado = {$args['id_empleado']} AND b.id_departamento = {$args['id_departamento']} AND p.fisico > 0
            GROUP BY a._id
        ";

    $object['productos'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    // $response->getBody()->write(json_encode($object));
    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // SSE Obtener ordenes asociadas a los empleados via SSE
  $app->get('/sse/empleados/ordenes-asignadas/{id_empleado}', function (Request $request, Response $response, array $args) {
    $sql = "SELECT 
            c.prioridad, 
            a.cantidad unidades_solicitadas, 
            a.cantidad unidades, 
            a.cantidad piezas_actuales, 
            b.fecha_inicio, 
            b.fecha_terminado, 
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            b._id id_lotes_detalles, 
            b.departamento, 
            a.id_orden, 
            a.id_orden orden, 
            a.id_woo, 
            a._id id_ordenes_productos, 
            a.name producto, 
            b.id_empleado, 
            (SELECT orden_proceso FROM departamentos WHERE _id = {$args['id_departamento']}) orden_proceso,
            a.talla, 
            a.corte, 
            a.tela, 
            b.departamento, 
            c.prioridad,
            c.paso,
            d.status,
            b.progreso, 
            b.detalles detalles_revision 
            FROM ordenes_productos a 
            JOIN lotes_detalles b 
            ON a._id = b.id_ordenes_productos 
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c 
            ON c.id_orden = b.id_orden 
            WHERE (b.id_empleado = " . $args['id_empleado'] . " AND b.progreso NOT LIKE 'terminada') AND (d.status LIKE 'En espera' OR d.status LIKE 'activa') ORDER BY c.prioridad DESC, b.progreso ASC, b.id_orden ASC
        ";
    $obj[0]['sql'] = $sql;
    $obj[0]['name'] = 'items';

    $sql = 'SELECT a._id id_lotes_detalles, a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = ' . $args['id_empleado'] . " AND progreso = 'en curso'";
    $sql = "SELECT 
            c.prioridad, 
            a.cantidad unidades_solicitadas, 
            a.cantidad unidades, 
            a.cantidad piezas_actuales, 
            b.fecha_inicio, 
            b.fecha_terminado, 
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            b._id id_lotes_detalles, 
            b.departamento, 
            (SELECT orden_proceso FROM departamentos WHERE _id = {$args['id_departamento']}) orden_proceso,
            a.id_orden, 
            a.id_orden orden, 
            a.id_woo, 
            a._id id_ordenes_productos, 
            a.name producto, 
            b.id_empleado, 
            a.talla, 
            a.corte, 
            a.tela, 
            b.departamento, 
            c.prioridad, 
            b.progreso, 
            b.detalles detalles_revision 
            FROM ordenes_productos a 
            JOIN lotes_detalles b 
            ON a._id = b.id_ordenes_productos 
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c 
            ON c.id_orden = b.id_orden 
            WHERE b.id_empleado = " . $args['id_empleado'] . " AND b.progreso = 'en curso'
        ";

    // $object['sql_en_curso'] = $sql;
    // $object['trabajos_en_curso'] = $localConnection->goQuery();

    $obj[1]['sql'] = $sql;
    $obj[1]['name'] = 'trabajos_en_curso';

    // BUSCAR ORDENES ACTIVAS ASIGNADAS AL EMPLEADO
    $sql = "SELECT DISTINCT a.id_orden FROM lotes_detalles a JOIN ordenes b ON b._id = a.id_orden WHERE (a.id_empleado = 24 AND a.progreso NOT LIKE 'terminada') AND (b.status LIKE 'En espera' OR b.status LIKE 'activa') ORDER BY a.id_orden ASC";
    $obj[2]['sql'] = $sql;
    $obj[2]['name'] = 'ordenes_asignadas';

    $sql = 'SELECT COUNT(_id) FROM ';

    $sse = new SSE($obj);
    $sse->SsePrint();

    $object['fields'][0]['key'] = 'nombre';
    $object['fields'][0]['label'] = 'Nombre';
    $object['fields'][1]['key'] = 'username';
    $object['fields'][1]['label'] = 'Usuario';
    $object['fields'][2]['key'] = 'departamento';
    $object['fields'][2]['label'] = 'Departamento';
    $object['fields'][3]['key'] = 'acciones';
    $object['fields'][3]['label'] = 'Acciones';

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener todos los empleados
  $app->get('/empleados', function (Request $request, Response $response) {
    // $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $localConnection = new LocalDB();
    $idEmp = ID_EMPRESA;
    $sql = 'SELECT
            a.id_usuario AS _id,
            a.email AS username,
            a.password,
            a.nombre,
            a.email,
            a.telefono,
            a.departamento,
            a.comision,
            a.comision_tipo,
            a.acceso,
            -- FNULL(GROUP_CONCAT(b.id_departamento), null) AS departamentos
            IFNULL(CONCAT("[", GROUP_CONCAT(
                CONCAT("{\"id\":", b.id_departamento, ",\"nombre\":\"", c.departamento, "\"}")
                SEPARATOR ","), "]"), "[]") AS departamentos
        FROM 
            api_empresas.empresas_usuarios a
        LEFT JOIN api_empresas.empresas_usuarios_departamentos b ON b.id_empleado = a.id_usuario
        LEFT JOIN ' . LOCAL_DB . '.departamentos c ON c._id = b.id_departamento
        WHERE
            a.activo = 1  AND a.id_empresa = ' . ID_EMPRESA . ' GROUP BY 
            a.id_usuario, a.email, a.password, a.nombre, a.departamento, 
            a.comision, a.comision_tipo, a.acceso;';
    $items = $localConnection->goQuery($sql);

    // Decodificar el campo `departamentos`
    foreach ($items as &$item) {
      if (!empty($item['departamentos'])) {
        $item['departamentos'] = json_decode($item['departamentos'], true);
      }
    }

    $object['items'] = $items;

    $localConnection->disconnect();

    $object['fields'][0]['key'] = 'nombre';
    $object['fields'][0]['label'] = 'Nombre';
    $object['fields'][1]['key'] = 'username';
    $object['fields'][1]['label'] = 'Usuario';
    $object['fields'][2]['key'] = 'departamentos';
    $object['fields'][2]['label'] = 'Departamentos';
    $object['fields'][3]['key'] = 'acciones';
    $object['fields'][3]['label'] = 'Acciones';

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Nuevo Empleado
  $app->post('/empleados/nuevo', function (Request $request, Response $response) {
    $miEmpleado = $request->getParsedBody();

    /* $response->getBody()->write(json_encode($miEmpleado));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); */

    // ////////////////////////////////

    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    // Crear estructura de valores para insertar nuevo empleado
    $values = '(';
    $values .= "'" . $now . "',";
    $values .= "'" . $miEmpleado['acceso'] . "',";
    $values .= "'" . $miEmpleado['comision'] . "',";
    $values .= "'" . $miEmpleado['comsion_tipo'] . "',";
    $values .= "'" . $miEmpleado['email'] . "',";
    $values .= "'" . $miEmpleado['nombre'] . "',";
    $values .= "'" . ID_EMPRESA . "',";
    $values .= "'" . $miEmpleado['password'] . "')";

    $sql = 'INSERT INTO api_empresas.empresas_usuarios (`moment`, `acceso`, `comision`, `comision_tipo`, `email`, `nombre`, `id_empresa`, `password`) VALUES ' . $values;
    $object['response'] = $localConnection->goQuery($sql);
    $lastInsert = $object['response']['insert_id'];

    // Guardar departamentos asignados al empleado
    $sql = 'DELETE FROM api_empresas.empresas_usuarios_departamentos WHERE id_empleado = ' . $lastInsert;
    $object['response_delete'] = $localConnection->goQuery($sql);

    $departamentos = explode(',', $miEmpleado['departamentos']);
    $sql = '';
    foreach ($departamentos as $id) {
      $sql .= "INSERT INTO api_empresas.empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES ({$lastInsert}, {$id});";
    }
    $object['response_deps'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Elditar Empleados
  $app->post('/empleados/editar', function (Request $request, Response $response) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // Actualizar empleado
    $values = "nombre='" . $miEmpleado['nombre'] . "',";
    // $values .= "departamento='" . $miEmpleado['departamento'] . "',";
    $values .= "acceso='" . $miEmpleado['acceso'] . "',";
    $values .= "password='" . $miEmpleado['password'] . "',";
    $values .= "email='" . $miEmpleado['email'] . "',";
    $values .= "comision_tipo='" . $miEmpleado['comision_tipo'] . "',";
    $values .= "comision='" . $miEmpleado['comision'] . "'";

    $sql = 'UPDATE api_empresas.empresas_usuarios SET ' . $values . ' WHERE id_usuario = ' . $miEmpleado['_id'];
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    // Limpiar registros anteriores
    $sql = "DELETE FROM api_empresas.empresas_usuarios_departamentos WHERE id_empleado = {$miEmpleado['_id']};";

    $object['sql_delete'] = $sql;
    $object['response_delete'] = json_encode($localConnection->goQuery($sql));

    // Insertar nuevas asiganciones de departamentos
    $misDeps = explode(',', $miEmpleado['departamentos']);

    if (count($misDeps) > 0) {
      $sql = '';
      // if ($dep != 0) {
      foreach ($misDeps as $id_dep) {
        $sql .= "INSERT INTO api_empresas.empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES ({$miEmpleado['_id']}, {$id_dep});";
      }
      // }
    }
    $object['sql_update'] = $sql;
    $object['response_update'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    // $response->getBody()->write(json_encode($object));
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar Empleados
  $app->post('/empleados/eliminar', function (Request $request, Response $response) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB();

    // VERIFICAR ASIGNACIÓN DE EMPLEADOS
    $sql = 'SELECT COUNT(_id) asignaciones FROM lotes_detalles WHERE id_empleado = ' . $miEmpleado['id'];
    $respCountRegs = $localConnection->goQuery($sql);
    $object['sql']['contar_registros_Asignados'] = $sql;
    $object['asignaciones'] = $respCountRegs[0]['asignaciones'];

    if (intval($object['asignaciones']) == 0) {  // Eliminar empleado solo si no tiene asignaciones de tareas anteriores
      // eliminar de base de datos de empleados
      $sql = 'DELETE FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miEmpleado['id'] . ';';

      $sql .= 'DELETE FROM lotes_detalles WHERE id_empleado = ' . $miEmpleado['id'] . '; DELETE FROM empleados WHERE _id =  ' . $miEmpleado['id'];
      $object['sql']['eliminar_registros'] = $sql;
      $object['sql'] = $sql;
      $object['response'] = json_encode($localConnection->goQuery($sql));
    }

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object), JSON_NUMERIC_CHECK);

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener empelados de produccion y diseño y los demas tambien...
  $app->get('/empleados/produccion/asignacion', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC';
    $object['response'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object['response']));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** Fin Empleados */

  /** INSUMOS */

  // REPORTE DE INSUMOS CONSUMIDO RPO PRODUCTOS DE CADA ORDEN
  $app->get('/reporte/insumos-cosumidos-por-orden[/{id_orden}[/{fecha_inicio}[/{fecha_fin}]]]', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $fecha_inicio = $args['fecha_inicio'] ?? null;
    $fecha_fin = $args['fecha_fin'] ?? null;
    $id_orden = $args['id_orden'] ?? null;

    // BUSQUEDA POR ID DE LA ORDEN
    if (is_null($id_orden) || intval($id_orden) === 0) {  // Recibimos orden = 0  en caso que se busque por fechas pero no se proporcione el número de la orden
      $where = '';
    } else {
      $where = ' WHERE imo.id_orden = ' . $id_orden . ' ';
    }

    // BUSQUEDA POR FECHAS
    if (!is_null($fecha_inicio) && !is_null($fecha_fin)) {
      if ($where === '') {
        $where = $where . " WHERE DATE(imo.moment) BETWEEN '" . $fecha_inicio . "' AND '" . $fecha_fin . "' ";
      } else {
        $where = $where . " AND DATE(imo.moment) BETWEEN '" . $fecha_inicio . "' AND '" . $fecha_fin . "' ";
      }
    }

    $sql = 'SELECT
            imo.id_orden,
            inv.sku,
            inv._id id_insumo,
            inv.insumo nombre_insumo,    
            inv.color,
            inv.costo,    
            inv.rendimiento,       
            (imo.valor_inicial - imo.valor_final) cantidad_utilizada,
            inv.cantidad cantidad_restante, 
            ROUND((inv.costo * (imo.valor_inicial - imo.valor_final)), 2) AS total_insumo,
            inv.unidad,    
            inv.departamento
        FROM
            inventario inv
        RIGHT JOIN inventario_movimientos imo ON imo.id_insumo = inv._id
        INNER JOIN tintas tin On tin.id_orden = imo.id_orden 
        ' . $where . '
        ORDER BY imo.id_orden ASC, inv.insumo ASC';
    $object['insumos_consumidos'] = $localConnection->goQuery($sql);

    // $sql = 'SELECT imo.id_orden, imo.c cyan, imo.m magenta, imo.y yellow, imo.k black, (imo.c + imo.m + imo.y + imo.k) total_tinta FROM tintas imo ' . $where . ' ORDER BY imo.id_orden ASC';
    $sql = <<<SQL
      -- Usamos dos CTEs: uno para encontrar el costo por ml, y otro para calcular el costo total por orden.
      WITH 
      -- CTE 1: Encuentra el costo por ml de la última recarga para cada tanque.
      last_ink_refill_cost AS (
          SELECT
              tr.id_catalogo_impresora,
              tr.color,
              -- Calculamos el costo por ml dividiendo el costo total de la botella por su cantidad.
              CASE 
                  WHEN inv.cantidad > 0 THEN (inv.costo / inv.cantidad)
                  ELSE 0 
              END AS ink_cost_per_ml,
              ROW_NUMBER() OVER (PARTITION BY tr.id_catalogo_impresora, tr.color ORDER BY tr.fecha_recarga DESC) as rn
          FROM
              tintas_recargas tr
          JOIN
              inventario inv ON tr.id_insumo = inv._id
      ),
      -- CTE 2: Usa el CTE anterior para calcular el costo total de la tinta para cada orden.
      costos_por_orden AS (
          SELECT
              tin.id_orden,
              ROUND(
                  (COALESCE(tin.c, 0) * COALESCE(lic_c.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.m, 0) * COALESCE(lic_m.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.y, 0) * COALESCE(lic_y.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.k, 0) * COALESCE(lic_k.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.w, 0) * COALESCE(lic_w.ink_cost_per_ml, 0))
              , 2) AS total_tinta_costo
          FROM
              tintas tin
          LEFT JOIN last_ink_refill_cost lic_c ON lic_c.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_c.color = 'C' AND lic_c.rn = 1
          LEFT JOIN last_ink_refill_cost lic_m ON lic_m.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_m.color = 'M' AND lic_m.rn = 1
          LEFT JOIN last_ink_refill_cost lic_y ON lic_y.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_y.color = 'Y' AND lic_y.rn = 1
          LEFT JOIN last_ink_refill_cost lic_k ON lic_k.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_k.color = 'K' AND lic_k.rn = 1
          LEFT JOIN last_ink_refill_cost lic_w ON lic_w.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_w.color = 'W' AND lic_w.rn = 1
          GROUP BY tin.id_orden
      )

      -- Consulta Final: Unimos tu consulta original con nuestros costos calculados.
      SELECT 
          imo.id_orden, 
          imo.c AS cyan, 
          imo.m AS magenta, 
          imo.y AS yellow, 
          imo.k AS black,
          -- Nota: He añadido la tinta blanca (w) al total para que sea consistente con el cálculo de costos.
          (COALESCE(imo.c, 0) + COALESCE(imo.m, 0) + COALESCE(imo.y, 0) + COALESCE(imo.k, 0) + COALESCE(imo.w, 0)) AS total_tinta_consumo_ml,
          -- La nueva columna con el costo total
          cpo.total_tinta_costo
      FROM 
          tintas imo
      -- Unimos con los resultados de nuestro CTE de costos
      LEFT JOIN 
          costos_por_orden cpo ON imo.id_orden = cpo.id_orden
      {$where}
      ORDER BY 
          imo.id_orden ASC
      SQL;
    $object['tintas'] = $localConnection->goQuery($sql);
    $object['sql'] = $sql;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER TODOS LOS INSUMOS
  $app->get('/insumos', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT * FROM inventario ORDER BY insumo ASC';
    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER DETALLES DEL INSUMO
  $app->get('/insumos/{id_insumo}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT * FROM inventario WHERE _id = ' . $args['id_insumo'];
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // NUEVO INSUMO
  $app->post('/insumos/nuevo', function (Request $request, Response $response, $args) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();
    $myDate = new CustomTime();
    $now = $myDate->today();
    $createdInsumos = [];

    try {
      if (isset($miInsumo['es_tinta']) && filter_var($miInsumo['es_tinta'], FILTER_VALIDATE_BOOLEAN) && isset($miInsumo['cantidad']) && intval($miInsumo['cantidad']) > 1) {
        for ($i = 0; $i < intval($miInsumo['cantidad']); $i++) {
          $currentCantidad = (isset($miInsumo['mililitros']) && filter_var($miInsumo['es_tinta'], FILTER_VALIDATE_BOOLEAN)) ? $miInsumo['mililitros'] : 1;
          $values = "('{$now}', '{$miInsumo['insumo']}', '{$miInsumo['departamento']}', '{$miInsumo['unidad']}', '{$miInsumo['rendimiento']}', '{$miInsumo['costo']}', {$currentCantidad}, '{$miInsumo['sku']}', '{$miInsumo['id_catalogo_producto']}')";
          $sql = 'INSERT INTO inventario (moment, insumo, departamento, unidad, rendimiento, costo, cantidad, sku, id_catalogo) VALUES ' . $values;
          $result = $localConnection->goQuery($sql);
          $lastId = $localConnection->getLastID();

          $sqlTinta = "INSERT INTO `tinta_filtro`(`id_inventario`, `color`) VALUES ({$lastId}, '{$miInsumo['color']}')";
          $localConnection->goQuery($sqlTinta);

          $newInsumo = $miInsumo;
          $newInsumo['_id'] = $lastId;
          $newInsumo['cantidad'] = 1;
          $newInsumo['sku'] = $miInsumo['sku'] . '-' . $lastId;
          $createdInsumos[] = $newInsumo;
        }
      } else {
        $cantidad = $miInsumo['cantidad'] ?? 1;
        if (isset($miInsumo['mililitros']) && filter_var($miInsumo['es_tinta'], FILTER_VALIDATE_BOOLEAN)) {
          $cantidad = $miInsumo['mililitros'];
        }
        $values = "('{$now}', '{$miInsumo['insumo']}', '{$miInsumo['departamento']}', '{$miInsumo['unidad']}', '{$miInsumo['rendimiento']}', '{$miInsumo['costo']}', {$cantidad}, '{$miInsumo['sku']}', '{$miInsumo['id_catalogo_producto']}')";
        $sql = 'INSERT INTO inventario (moment, insumo, departamento, unidad, rendimiento, costo, cantidad, sku, id_catalogo) VALUES ' . $values;
        $result = $localConnection->goQuery($sql);
        $lastId = $localConnection->getLastID();

        if (isset($miInsumo['es_tinta']) && filter_var($miInsumo['es_tinta'], FILTER_VALIDATE_BOOLEAN)) {
          $sqlTinta = "INSERT INTO `tinta_filtro`(`id_inventario`, `color`) VALUES ({$lastId}, '{$miInsumo['color']}')";
          $localConnection->goQuery($sqlTinta);
        }

        $newInsumo = $miInsumo;
        $newInsumo['_id'] = $lastId;
        $newInsumo['sku'] = $miInsumo['sku'] . '-' . $lastId;
        $createdInsumos[] = $newInsumo;
      }

      $localConnection->disconnect();

      $responseData = [
        'error' => false,
        'message' => 'Insumos creados exitosamente.',
        'data' => $createdInsumos
      ];

      $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $localConnection->disconnect();
      $responseData = [
        'error' => true,
        'message' => 'Error al crear insumo(s): ' . $e->getMessage(),
        'data' => []
      ];
      $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // EDITAR INSUMO
  $app->post('/insumos/editar', function (Request $request, Response $response, $args) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Crear estructura de valores para insertar nuevo cliente
    $values = "insumo='" . $miInsumo['insumo'] . "',";
    $values .= "unidad='" . $miInsumo['unidad'] . "',";
    $values .= "cantidad='" . $miInsumo['cantidad'] . "',";
    $values .= "rendimiento='" . $miInsumo['rendimiento'] . "',";
    $values .= "costo='" . $miInsumo['costo'] . "',";
    $values .= "departamento='" . $miInsumo['departamento'] . "',";
    $values .= "sku='" . $miInsumo['sku'] . "',";
    $values .= "id_catalogo='" . $miInsumo['id_catalogo'] . "'";

    $sql = 'UPDATE inventario SET ' . $values . ' WHERE _id = ' . $miInsumo['_id'];
    $object['sql'] = $sql;
    $object['data'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER INSUMOS PERTENECIENTES A UNA ORDEN
  // Eliminar Insumos

  $app->post('/insumos/eliminar', function (Request $request, Response $response) {
    $miEmpleado = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'DELETE FROM inventario WHERE _id =  ' . $miEmpleado['id'];
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Insumos por empleado
  $app->get('/inventario-movimientos/{id_orden}/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT * FROM ordenes_productos WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $args['id_empleado'];
    $object['items'] = $localConnection->goQuery($sql);

    $sql = 'SELECT b._id, a._id id_insumo, a.cantidad, a.unidad, a.insumo, a.sku FROM inventario a JOIN inventario_movimientos b ON a._id = b.id_insumo  WHERE b.id_orden = ' . $args['id_orden'] . ' AND b.id_empleado = ' . $args['id_empleado'];
    $object['movimientos'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Insumos historial por orden (Verificar si se han hecho cambios previamente en el valor de las cantidades)
  $app->get('/inventario/historial/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT id_insumo, valor_inicial, valor_final, departamento FROM inventario_movimientos WHERE id_orden = ' . $args['id_orden'];
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Crear nuevo insumo asignado a empleados
  $app->post('/inventario-movimientos/nuevo', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();
    $object['body'] = $miInsumo;

    // Verificar existencia del registro
    $sql = 'SELECT _id FROM inventario_movimientos WHERE id_orden = ' . $miInsumo['id_orden'] . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_producto = ' . $miInsumo['id_producto'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "'";
    $object['miinsumo'] = json_encode($localConnection->goQuery($sql));
    // $object['id_insumo'] = $object['miinsumo']->_id;

    if (empty(json_decode($object['miinsumo']))) {
      $sql = 'SELECT cantidad, insumo, unidad, sku FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
      $cantidad = $localConnection->goQuery($sql);
      $object['cantidad_Recuperada'] = $cantidad;

      // PREPARAR FECHAS
      $myDate = new CustomTime();
      $now = $myDate->today();

      $values = "'" . $now . "',";
      $values .= "'" . $miInsumo['departamento'] . "',";
      // $values .= $miInsumo["id_empleado"] . ",";
      $values .= $miInsumo['id_insumo'] . ',';
      $values .= "'" . $cantidad[0]['cantidad'] . "',";
      $values .= $miInsumo['id_producto'];

      $array_ordenes = explode(',', $miInsumo['ordenes']);

      foreach ($array_ordenes as $key => $value) {
        $sql = 'INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (' . $values . ');';
      }
      $result = json_encode($localConnection->goQuery($sql));

      $sql = '';
      if (count($result) > 0) {
        // UPDATE
      }
      {
        // INSERT
      }

      // $sql = "INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (" . $values . ");";
      $object['sql'] = $sql;
      $object['insert'] = json_encode($localConnection->goQuery($sql));
    }  /*else {
$arrayOrdenes = explode(',', $miInsumo['ordenes'])
$sql = "";
foreach ($arrayOrdenes as $key => $orden) {
$sal .= "UPDATE inventario_movimientos SET id_orden = " $orden . " WHERE id_empleado = " . $miInsumo['id_empleado'];
}

// UPDATE
// $sql = "INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (" . $values . ")";
$ql = "UPDATE inventario_movimientos SET ";
$object["sql"] = $sql;
$object['insert'] = json_encode($localConnection->goQuery($sql));
}*/

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar cantidad del insumo desde produccion
  $app->post('/inventario-movimientos/piezas-cortadas', function (Request $request, Response $response) {
    $miPieza = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'INSERT INTO piezas_cortadas (peso, id_orden, id_inventario, id_ordenes_productos, id_empleado) VALUES (' . $miPieza['peso'] . ', ' . $miPieza['id_orden'] . ', ' . $miPieza['id_inventario'] . ', ' . $miPieza['id_ordenes_productos'] . ', ' . $miPieza['id_empleado'] . ')';
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar invetario_movimientos desde módulo de empleados
  $app->post('/inventario-movimientos/empleados/update-insumo', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Verifcar si es reposicion y actualizar el campor `terminada`
    if ($miInsumo['es_reposicion'] == 1) {
      // $sql = "UPDATE reposiciones SET terminada = 1 WHERE id_orden = {$miInsumo['id_orden']} AND id_empleado = {$miInsumo['id_empleado']};";
      // $sql = "DELETE FROM `ordenes_fila_reposiciones` WHERE id_orden = {$miInsumo['id_orden']};";
      // $object["sql_update_reposiones"] = $sql;
      // $update_reposiciones = $localConnection->goQuery($sql);
    }

    // buscar cantidad actual del producto
    $sql_check = 'SELECT cantidad, sku FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
    $object['sql_cantidad_producto'] = $sql_check;
    $cantidad_producto = $localConnection->goQuery($sql_check);
    $object['cantidad_producto'] = $cantidad_producto;

    $cantidad_inicial = floatval($cantidad_producto[0]['cantidad']);
    $object['cantidad_inicial'] = $cantidad_inicial;

    if ($miInsumo['departamento'] === 'Estampado' || $miInsumo['departamento'] === 'Corte') {
      // 1.- Busar rendimiento de la tela
      $sql = 'SELECT rendimiento, sku FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
      $tmpRendimiento = $localConnection->goQuery($sql);
      $rendimiento = floatval($tmpRendimiento[0]['rendimiento']);

      // 2.- Dividir la cantidad que me llega en metros entre el rendimiento para obtener el resultado en Kilos
      if ($rendimiento != 0) {
        $kilos = floatval($miInsumo['cantidad_consumida']) / $rendimiento;
      } else {
        $kilos = 0;  // O cualquier otro valor que consideres apropiado
      }
      // #.- una vez tengo los kilos se los resto al rollo
      $cantidad_consumida = floatval($cantidad_inicial) - floatval($kilos);  // Cantidad final se refiere a la cantidad del insumo consumido

      $object['cantidad_inicial'] = $cantidad_inicial;
      $object['cantidad_consumida_kilos'] = $kilos;
      // $object["cantidad_consumida"] = $cantidad_consumida;

      // $object["cantidad_previa_del_insumo"] = $miInsumo["cantidad_inicial"];

      $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
      $sql .= 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'] . ';';
      $update_cantidad_inventario = $localConnection->goQuery($sql);
      $object['update_cantidad_invrntario_SQL'] = $sql;
      $object['update_cantidad_inventario_RSP'] = $update_cantidad_inventario;
    } else {
      $cantidad_consumida = floatval($cantidad_inicial) - floatval($miInsumo['cantidad_consumida']);
      $object['cantidad_consumida'] = $cantidad_consumida;
      $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
      $object['resp_update_cantidad'] = $localConnection->goQuery($sql);
    }

    // Guardar en rendimiento
    if ($miInsumo['departamento'] === 'Corte') {
      // 1- Determinar si el registro existe (INSERT o UPDATE)
      $sql = 'SELECT COUNT(id_orden) FROM rendimiento WHERE id_orden = ' . $miInsumo['id_orden'];
      $exist = $localConnection->goQuery($sql);

      if ($exist > 0) {
        // 0- Preparar datos
        /** De momento estamos asumiendo que el departamento por defecto es corte, en realidad es estampado, debemos programar que se pueda determinar el departamento de alguna manera */
        if ($miInsumo['departamento'] === 'Impresión') {
          $campo_valor = 'metros';
          $campo_empleado = 'id_empleado_impresion';
        }
        if ($miInsumo['departamento'] === 'Estampado') {
          $campo_valor = 'id_insumo';
          $campo_empleado = 'id_empleado_estampado';
        }
        if ($miInsumo['departamento'] === 'Corte') {
          $campo_valor = 'desperdicio';
          $campo_empleado = 'id_empleado_corte';
        }

        $sql = "INSERT INTO rendimiento (id_orden, $campo_empleado, $campo_valor, id_insumo, metros) VALUES ({$miInsumo['id_orden']}, {$miInsumo['id_empleado']}, {$miInsumo['valor']}, {$miInsumo['id_insumo']}, {$miInsumo['cantidad_consumida']});";
      } else {
        $sql = "UPDATE rendimiento SET id_insumo = {$miInsumo['id_insumo']}, metros = {$miInsumo['cantidad_consumida']}, $campo_empleado = {$miInsumo['id_empleado']}, $campo_valor  = {$miInsumo['valor']} WHERE id_orden = {$miInsumo['id_orden']};";
      }

      $object['response_rendimiento'] = json_encode($localConnection->goQuery($sql));
    }

    // --- INICIO: Corrección para manejar NULL y usar sentencias preparadas ---
    // 1. Preparar la consulta SQL con placeholders (?)
    $sql = 'INSERT INTO inventario_movimientos
            (
             id_orden, 
             id_empleado, 
             id_producto, 
             id_insumo, 
             id_departamento, 
             id_catalogo_insumos_prodcutos,
             departamento, 
             valor_inicial, 
             valor_final)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

    // 2. Preparar los parámetros, convirtiendo la cadena "null" a un verdadero NULL de PHP
    $id_catalogo = (isset($miInsumo['id_catalogo']) && $miInsumo['id_catalogo'] !== 'null' && $miInsumo['id_catalogo'] !== '')
      ? intval($miInsumo['id_catalogo'])
      : null;

    $params = [
      $miInsumo['id_orden'],
      $miInsumo['id_empleado'],
      $miInsumo['id_producto'],
      $miInsumo['id_insumo'],
      $miInsumo['id_departamento'],
      $id_catalogo,  // Aquí va el valor ya procesado (sea un número o un NULL de PHP)
      $miInsumo['departamento'],
      $cantidad_inicial,
      $cantidad_consumida
    ];

    $object['sql_inventario_movimientos'] = $sql;
    $object['resp_invetario_movimientos'] = $localConnection->goQuery($sql, $params);
    // --- FIN: Corrección ---

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar cantidad del insumo desde produccion
  $app->post('/inventario-movimientos/update-insumo', function (Request $request, Response $response) {
    $miInsumo = $request->getParsedBody();
    $localConnection = new LocalDB();

    $arrayOrdenes = explode(',', $miInsumo['ordenes']);
    $object['count_Array'] = count($arrayOrdenes);
    $sql = "UPDATE inventario SET cantidad = '" . $miInsumo['cantidad_final'] . "' WHERE _id =  " . $miInsumo['id_insumo'] . ';';

    foreach ($arrayOrdenes as $key => $orden) {
      $sql_check = 'SELECT _id existe FROM inventario_movimientos WHERE id_orden = ' . $orden . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "';";
      $respuesta = $localConnection->goQuery($sql_check);
      $object['respuesta_check'][$key] = $respuesta;

      if (count($respuesta) > 0) {
        $sql .= '
            UPDATE inventario_movimientos 
            SET id_orden = ' . $orden . ', 
            id_empleado = ' . $miInsumo['id_empleado'] . ', 
            id_insumo = ' . $miInsumo['id_insumo'] . ", 
            id_departamento = '" . $miInsumo['id_departamento'] . "', 
            departamento = '" . $miInsumo['departamento'] . "', 
            valor_inicial = " . $miInsumo['cantidad_inicial'] . ', 
            valor_final = ' . $miInsumo['cantidad_final'] . ' 
            WHERE id_orden = ' . $orden . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "';";
      } else {
        $sql .= '
            INSERT INTO inventario_movimientos 
            (
             id_orden, 
             id_empleado, 
             id_insumo, 
             id_departamento, 
             departamento, 
             valor_inicial, 
             valor_final)
            VALUES (
                    ' . $orden . ',
                    ' . $miInsumo['id_empleado'] . ',
                    ' . $miInsumo['id_insumo'] . ",
                    '" . $miInsumo['id_departamento'] . "',
                    '" . $miInsumo['departamento'] . "',
                    " . $miInsumo['cantidad_inicial'] . ',
                    ' . $miInsumo['cantidad_final'] . '
                    );
            ';
      }
    }

    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Actualizar prioridad del lote
  $app->post('/inventario-movimientos/update-prioridad', function (Request $request, Response $response) {
    $prioridad = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'UPDATE lotes SET prioridad = ' . $prioridad['prioridad'] . ' WHERE id_orden = ' . $prioridad['id'];
    $object['sql'] = $sql;
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eliminar insumo asignado
  $app->post('/inventario-movimientos/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'DELETE FROM `inventario_movimientos` WHERE _id = ' . $data['id'];
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Reporte de insumos por número de orden
  $app->get('/insumos/reporte/orden/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = "SELECT b._id id_insumo, a.id_orden,  b.insumo, b.sku, a.valor_inicial, a.valor_final, a.id_producto, DATE_FORMAT(a.moment, '%d/%m/%Y') moment FROM inventario_movimientos a JOIN inventario b ON a.id_insumo = b._id WHERE a.id_orden = " . $args['id'] . ' ORDER BY a.id_producto';

    $object['sql'] = $sql;

    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['fields'][0]['key'] = 'id_insumo';
    $object['fields'][0]['label'] = 'ID';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'insumo';
    $object['fields'][1]['label'] = 'Insumo';
    $object['fields'][1]['sortable'] = true;

    $object['fields'][2]['key'] = 'valor_inicial';
    $object['fields'][2]['label'] = 'Valor Inicial';
    // $object['fields'][1]['sortable'] = true;
    $object['fields'][3]['key'] = 'valor_final';
    $object['fields'][3]['label'] = 'Valor Final';
    // $object['fields'][2]['sortable'] = true;
    $object['fields'][4]['key'] = 'id_producto';
    $object['fields'][4]['label'] = 'Producto';
    $object['fields'][4]['sortable'] = true;

    $object['fields'][4]['key'] = 'moment';
    $object['fields'][4]['label'] = 'Fecha';
    $object['fields'][4]['sortable'] = true;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Reporte de insumos por insumo
  $app->get('/insumos/reporte/insumos/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT a.id_orden, b.nombre, c.insumo, c.sku, a.valor_inicial, a.valor_final, DATE_FORMAT(a.moment, '%d/%m/%Y') moment FROM inventario_movimientos a JOIN empleados b ON a.id_empleado = b._id JOIN inventario c ON a.id_insumo = c._id WHERE a.id_insumo =" . $args['id'] . ' ORDER BY c.insumo';
    $object['sql'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['fields'][0]['key'] = 'id_orden';
    $object['fields'][0]['label'] = 'Orden';
    $object['fields'][0]['sortable'] = true;

    $object['fields'][1]['key'] = 'valor_inicial';
    $object['fields'][1]['label'] = 'Valor Inicial';
    // $object['fields'][1]['sortable'] = true;
    $object['fields'][2]['key'] = 'valor_final';
    $object['fields'][2]['label'] = 'Valor Final';
    // $object['fields'][2]['sortable'] = true;
    $object['fields'][3]['key'] = 'nombre';
    $object['fields'][3]['label'] = 'Empleado';

    $object['fields'][4]['key'] = 'moment';
    $object['fields'][4]['label'] = 'Fecha';
    $object['fields'][3]['sortable'] = true;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  // Reporte de insumos por producto
  $app->get('/insumos/reporte/insumos/producto/{id_producto}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
    a.id_orden,
    a.id_woo id_producto,
    a.name producto,
    b.id_insumo,   
    d.insumo,
    d.sku,
    b.valor_inicial,
    b.valor_final,   
    c.nombre,
    b.departamento,
    DATE_FORMAT(b.moment, '%d/%m/%Y')moment

    FROM
    ordenes_productos a
    JOIN inventario_movimientos b ON b.id_orden = a.id_orden 
    JOIN inventario d ON b.id_insumo = d._id
    JOIN empleados c ON c._id = b.id_empleado 
    WHERE a.id_woo =" . $args['id_producto'] . ' ORDER BY a.category_name
    ';

    $object['sql'] = $sql;
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['fields'][0]['key'] = 'id_orden';
    $object['fields'][0]['label'] = 'Orden';

    $object['fields'][1]['key'] = 'producto';
    $object['fields'][1]['label'] = 'Producto';

    $object['fields'][2]['key'] = 'id_insumo';
    $object['fields'][2]['label'] = 'ID insumo';

    $object['fields'][3]['key'] = 'insumo';
    $object['fields'][3]['label'] = 'Insumo';
    // $object['fields'][0]['sortable'] = true;
    $object['fields'][4]['key'] = 'valor_inicial';
    $object['fields'][4]['label'] = 'Valor Inicial';
    // $object['fields'][1]['sortable'] = true;
    $object['fields'][5]['key'] = 'valor_final';
    $object['fields'][5]['label'] = 'Valor Final';
    // $object['fields'][2]['sortable'] = true;
    $object['fields'][6]['key'] = 'nombre';
    $object['fields'][6]['label'] = 'Empleado';

    $object['fields'][7]['key'] = 'moment';
    $object['fields'][7]['label'] = 'Fecha';

    $object['fields'][8]['key'] = 'moment';
    $object['fields'][8]['label'] = 'Fecha';
    // $object['fields'][3]['sortable'] = true;

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN INSUMOS */

  /** INVENTARIO */
  $app->get('/inventario/{departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][5]['label'] = 'ACCIONES';

    if ($args['departamento'] === 'todos') {
      $sql = 'SELECT
                _id,
                _id rollo,
                sku,
                insumo,
                cantidad cantidad_inicial,
                cantidad cantidad_final,
                cantidad,
                ROUND((rendimiento * cantidad), 2) AS metros,
                unidad,
                costo,
                rendimiento,
                departamento,
                moment
            FROM
                inventario
            ORDER BY
                insumo ASC;';
    } else {
      $sql = "SELECT
                _id,
                _id rollo,
                sku,
                insumo,
                cantidad cantidad_inicial,
                cantidad cantidad_final,
                cantidad,
                ROUND((rendimiento * cantidad),
                2) AS metros,
                unidad,
                costo,
                rendimiento,
                departamento,
                moment
            FROM
                inventario
            WHERE
                departamento = '" . $args['departamento'] . "'
            ORDER BY
                insumo ASC;";
    }
    $object['items'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $object['fields'][0]['key'] = 'rollo';
    $object['fields'][0]['label'] = 'Rollo';
    $object['fields'][0]['sortable'] = false;
    $object['fields'][1]['key'] = 'insumo';
    $object['fields'][1]['label'] = 'Nombre';
    $object['fields'][1]['sortable'] = false;
    $object['fields'][2]['key'] = 'rendimiento';
    $object['fields'][2]['label'] = 'Rendimiento';
    $object['fields'][2]['sortable'] = false;
    $object['fields'][3]['key'] = 'costp';
    $object['fields'][3]['label'] = 'Costo';
    $object['fields'][3]['sortable'] = false;
    $object['fields'][4]['key'] = 'departamento';
    $object['fields'][4]['label'] = 'Departamento';
    $object['fields'][4]['sortable'] = true;
    $object['fields'][5]['key'] = 'unidad';
    $object['fields'][5]['label'] = 'Unidad';
    $object['fields'][5]['sortable'] = false;
    $object['fields'][6]['key'] = 'cantidad';
    $object['fields'][6]['label'] = 'Cantidad';
    $object['fields'][6]['sortable'] = false;
    $object['fields'][7]['key'] = '_id';
    $object['fields'][7]['sortable'] = false;

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // INVENTARIO DE TINTAS
  $app->get('/inventario-tintas', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // $localConnection->conectar();

    $sql = 'SELECT
        a._id id_insumo,
        a.sku,
        b.color,
        a.costo,
        a.cantidad
        FROM
        inventario a
        JOIN tinta_filtro b ON b.id_inventario = a._id
        WHERE a.cantidad > 0';

    $data = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data,
      JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // EFICIENCIA CON DATOS COMPLETOS
  // TODO ESTABLECER PARÁMETROS APRA OBTENER VARIAS ORDENES
  $app->get('/inventario/eficiencia/{id_orden}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                    a.name producto,
                    (SELECT insumo FROM inventario WHERE _id = a.id_woo) insumo,
                    (SELECT sku FROM inventario WHERE _id = a.id_woo) sku,
                    SUM(a.cantidad) cantidadProductosOrden,
                    (b.valor_inicial - b.valor_final) consumoRealTotalOrdenUnidadBase,
                    (SELECT rendimiento FROM inventario WHERE _id = a.id_woo) factorConversionUnidadInsumo,
                    c.cantidad consumoTeoricoPorProductoUnidadConvertida
                FROM
                    ordenes_productos a
                JOIN inventario_movimientos b ON b.id_orden = a.id_orden
                JOIN product_insumos_asignados c ON c.id_product = a.id_woo
                WHERE
                    a.id_orden = {$args['id_orden']} AND c.id_departamento = {$args['id_departamento']};
        ";

    $object = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Eficiencia Orden
  $app->get('/eficiencia-orden/{id_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                      a._id id_ordenes_productos,
                      a.id_woo id_product,
                      a.name producto,
                      (SELECT nombre FROM sizes WHERE _id = a.talla) talla,
                      a.cantidad unidades,
                      b.id_departamento,
                      (SELECT departamento FROM departamentos WHERE _id = b.id_departamento) departamento,
                      b.cantidad cantidad_estimada_de_consumo,
                      b.unidad unidad_de_medida,
                      b.id_catalogo_insumos_productos,
                      (SELECT nombre FROM catalogo_insumos_productos WHERE _id = b.id_catalogo_insumos_productos) catalogo
                  FROM
                      ordenes_productos a
                  LEFT JOIN product_insumos_asignados b ON b.id_product = a.id_woo
                  WHERE
                      a.id_orden = {$args['id_orden']}
                  ORDER BY a.talla ASC";

    $object['insumos_asignados'] = $localConnection->goQuery($sql);
    // $object['insumos_asignados'] = null;

    $sql = "SELECT
                a.id_orden,
                a._id id_ordenes_productos,
                a.id_woo id_product,
                a.name,
                b.id_size,
                (SELECT nombre FROM sizes WHERE _id = a.talla) talla,
                a.cantidad unidades,
                b.cantidad valor_eficiencia,
                b.unidad,
                b.id_catalogo_insumos_prodcutos,
                c.nombre,
                b.cantidad * a.cantidad eficiencia_estimada
            FROM
                ordenes_productos a 
            LEFT JOIN products_sizes_eficiencia b ON b.id_size = a.talla 
            JOIN catalogo_insumos_productos c ON c._id = b.id_catalogo_insumos_prodcutos
            WHERE
                id_orden =  {$args['id_orden']}
        ";
    $object['detalles'] = $localConnection->goQuery($sql);

    $sql = "SELECT
            a.id_orden,
            SUM(b.cantidad * a.cantidad) eficiencia_estimada
        FROM
            ordenes_productos a
        LEFT JOIN products_sizes_eficiencia b ON b.id_size = a.talla
        WHERE
            id_orden = {$args['id_orden']}
        ";
    $object['total_eficiencia'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // EFICIENCIA PARA MODULO DE EMPLEADOS
  $app->post('/empleados/eficiencia', function (Request $request, Response $response) {
    /**
     * Recibimos:
     *
     * id_orden
     * id_insumo
     * id_departamento
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = "SELECT
                    a._id id_insumo,
                    a.insumo nombre_inusmo,
                    a.sku,
                    a.rendimiento,
                    a.cantidad cantidad_insumo,
                    (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = {$data['id_orden']}) total_productos                    
                FROM
                    inventario a
                WHERE
                    a._id = {$data['id_insumo']};
        ";

    $object['insumos'] = $localConnection->goQuery($sql);

    $sql = "SELECT
                    a.id_woo id_product,
                        a.name,
                        a.cantidad,
                        a.talla id_talla,
                        (SELECT nombre FROM sizes WHERE _id = b._id) talla,
                        b.cantidad rendimiento_talla,
                        b.unidad
                    FROM
                        ordenes_productos a 
                    LEFT JOIN products_sizes_eficiencia b on a.talla = b.id_size
                    WHERE a.id_orden =  {$data['id_orden']}
        ";
    $object['productos'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** FIN INVENTARIO */

  /** ASISTENCIAS */

  // Crear nuevo registro en la tabla de asistencias
  $app->post('/asistencias', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    $sql = 'INSERT INTO `asistencias`(`id_empleado`, `registro`, `moment`) VALUES (' . $data['id_empleado'] . ",'" . $data['registro'] . "','" . $data['moment'] . "')";
    $object['response'] = json_encode($localConnection->goQuery($sql));

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/asistencias/semanal', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT 
        a._id id_asistencias,
        e._id id_empleado,
        e.nombre AS empleado,
        DATE_FORMAT(a.moment, '%H:%i') AS hora,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        CASE 
        WHEN DAYOFWEEK(a.moment) = 2 THEN 'L'
        WHEN DAYOFWEEK(a.moment) = 3 THEN 'M'
        WHEN DAYOFWEEK(a.moment) = 4 THEN 'X'
        WHEN DAYOFWEEK(a.moment) = 5 THEN 'J'
        WHEN DAYOFWEEK(a.moment) = 6 THEN 'V'
        WHEN DAYOFWEEK(a.moment) = 7 THEN 'S'
        WHEN DAYOFWEEK(a.moment) = 1 THEN 'D'
        END AS dia,
        CASE 
        WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
        WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
        WHEN a.registro = 'entrada_tarde' THEN 'Entrada tarde'
        WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
        END AS registro
        FROM 
        asistencias a
        JOIN 
        empleados e ON a.id_empleado = e._id
        WHERE 
        YEARWEEK(a.moment) = YEARWEEK(NOW())
        ORDER BY 
        e.nombre ASC,
        a.moment ASC;
        ";
    $object['data_semana'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE DE ASISTENCIAS POR FECHA UNICA
  $app->get('/asistencias/tabla/{fecha}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields'][0]['key'] = 'nombre';
    $object['fields'][0]['label'] = 'Nombre';
    $object['fields'][1]['key'] = 'moment';
    $object['fields'][1]['label'] = 'Entrada Mañana';
    $object['fields'][2]['key'] = 'moment';
    $object['fields'][2]['label'] = 'Salida Mañana';
    $object['fields'][3]['key'] = 'moment';
    $object['fields'][3]['label'] = 'Entrada Tarde';
    $object['fields'][4]['key'] = 'moment';
    $object['fields'][4]['label'] = 'Salida Tarde';

    // OBTENER TODOS LOS EMPLEADOS
    $sql = 'SELECT * FROM empleados ORDER BY nombre ASC';
    $object['empleados'] = $localConnection->goQuery($sql);

    // TODO las dos variables siguinetes estan mal arreglar esto
    $today = null;
    $date = null;
    // $myDate = new CustomTime();
    // $now = $myDate->today();
    // $fecha = explode(' ', $now); // La fecha la recibimos por args
    $fecha = $args['fecha'];

    // OBTENER ASISTENCIAS DIARIAS
    // $sql = "SELECT a._id id_empleado, b._id id_asistencia, a.nombre, DATE_FORMAT(b.moment, '%h:%i %p') AS hora, DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha, b.registro, b.detalle FROM empleados a LEFT JOIN asistencias b ON b.id_empleado = a._id WHERE b.moment LIKE '" . $fecha . "%' OR a._id > 0;";
    $sql = "SELECT
        a._id id_empleado,
        b._id id_asistencia,
        a.nombre,
        DATE_FORMAT(b.moment, '%h:%i %p') AS hora,
        DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha,
        b.registro,
        b.detalle
        FROM
        empleados a
        LEFT JOIN asistencias b ON
        b.id_empleado = a._id
        WHERE
        (a._id > 0  AND b.moment LIKE '" . $fecha . "%') OR (a._id > 0 AND b.moment IS NULL)
         ORDER BY a.nombre ASC;";
    $object['sql_diarias'] = $sql;
    $mod_date = strtotime($date . '+ 0 days');
    $object['diarias'] = $localConnection->goQuery($sql);

    // NUEVO REPORTE
    $sql = 'SELECT a.id_empleado, b.username, a.moment, DATE(a.moment) fecha, UNIX_TIMESTAMP(a.moment) - 3600 timestamp, DAYNAME(a.moment) dia, a.registro FROM asistencias a JOIN empleados b ON a.id_empleado = b._id WHERE WEEK(a.moment) = WEEK(NOW());';

    $today . "%'";
    $object['reporte'] = $localConnection->goQuery($sql);

    // ASISTENCIAS SEMANA
    $today = date('Y-m-d', $mod_date);

    $sql = "SELECT
         b._id,
         b.username empleado
         FROM asistencias a
         JOIN empleados b ON b._id = a.id_empleado
         WHERE WEEK(a.moment) = WEEK('" . $today . "')
                                     GROUP BY b.username
                                     ORDER BY
                                     b.username ASC,
                                     a.moment ASC";
    $today . "%'";

    $object['semana'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/asistencias/reporte/resumen/{fecha_inicio}/{fecha_fin}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $object['fields_resumen'][0]['key'] = 'nombre';
    $object['fields_resumen'][0]['label'] = 'Nombre';
    $object['fields_resumen'][1]['key'] = 'horas_trabajadas';
    $object['fields_resumen'][1]['label'] = 'Horas Trabajadas';
    $object['fields_resumen'][2]['key'] = 'acciones';
    $object['fields_resumen'][2]['label'] = 'Acciones';

    $object['fields_detallado'][0]['key'] = 'nombre';
    $object['fields_detallado'][0]['label'] = 'Nombre';
    $object['fields_detallado'][1]['key'] = 'registro';
    $object['fields_detallado'][1]['label'] = 'Registro';
    $object['fields_detallado'][2]['key'] = 'hora';
    $object['fields_detallado'][2]['label'] = 'Hora';
    $object['fields_detallado'][3]['key'] = 'fecha';
    $object['fields_detallado'][3]['label'] = 'Fecha';

    // REPORTE RESUMEN
    $sql = "SELECT
            a.id_empleado,
            a.id_empleado acciones,
            b.nombre, 
            ROUND(
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_manana' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_manana' THEN a.moment END)
                                       ) / 60.0,
                         0
                         )
                  +
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_tarde' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_tarde' THEN a.moment END)
                                       ) / 60.0,
                         0
                         ),
                  2
                  ) AS horas_trabajadas
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            GROUP BY a.id_empleado;
            ";
    $object['resumen'] = $localConnection->goQuery($sql);

    // REPORTE DETALLADO
    $sql = "SELECT
            b._id id_empleado,
            b.nombre,
            DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
            DATE_FORMAT(a.moment, '%h:%i %p') AS hora,
            CASE 
            WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
            WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
            WHEN a.registro = 'entrada_tarde' THEN 'Entrada Tarde'
            WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
            ELSE a.registro 
            END AS registro 
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            ORDER BY b.nombre ASC, a.moment ASC,
            FIELD(a.registro, 'entrada_manana', 'salida_manana', 'entrada_tarde', 'salida_tarde');
            ";
    $object['detallado'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /* FIN ASISTENCIAS */

  // =================================================================
  // ENDPOINTS PARA GESTIÓN DE GASTOS FIJOS DE LA EMPRESA
  // =================================================================

  /**
   * GET /gastos
   * Obtiene todos los gastos fijos de la empresa autenticada.
   */
  $app->get('/gastos', function (Request $request, Response $response, array $args) {
    $id_empresa = ID_EMPRESA;
    if (!$id_empresa) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado. No se pudo identificar la empresa.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $sql = 'SELECT _id, nombre, descripcion, monto, moneda, periodicidad, estatus 
                FROM api_empresas.empresas_gastos 
                WHERE id_empresa = ?';
    $params = [$id_empresa];

    try {
      $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $gastos = $db->goQuery($sql, $params);
      $db->disconnect();

      $response->getBody()->write(json_encode($gastos, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  /**
   * POST /gastos
   * Crea un nuevo registro de gasto para la empresa.
   */
  $app->post('/gastos', function (Request $request, Response $response, array $args) {
    $id_empresa = ID_EMPRESA;
    if (!$id_empresa) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $data = $request->getParsedBody();

    if (empty($data['nombre']) || !isset($data['monto'])) {
      $response->getBody()->write(json_encode(['error' => 'Los campos "nombre" y "monto" son obligatorios.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'INSERT INTO api_empresas.empresas_gastos 
                    (id_empresa, nombre, descripcion, monto, moneda, periodicidad, estatus) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?)';

    try {
      $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $params = [
        $id_empresa,
        $data['nombre'],
        $data['descripcion'] ?? null,
        $data['monto'],
        $data['moneda'] ?? 'USD',
        $data['periodicidad'] ?? 'mensual',
        $data['estatus'] ?? 'activo'
      ];

      $result = $db->goQuery($sql, $params);
      $newId = $db->getLastID();
      $db->disconnect();

      $response->getBody()->write(json_encode(['message' => 'Gasto creado exitosamente', 'id_gasto' => $newId]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al crear el gasto: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  /**
   * PUT /gastos/{id_gasto}
   * Actualiza un gasto existente.
   */
  $app->put('/gastos/{id_gasto}', function (Request $request, Response $response, array $args) {
    $id_empresa = ID_EMPRESA;
    $id_gasto = $args['id_gasto'];
    if (!$id_empresa) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    // Corrección: Leer y parsear manualmente el cuerpo de la solicitud PUT
    $put_body = $request->getBody()->getContents();
    parse_str($put_body, $data);

    if (empty($data)) {
      $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos para actualizar.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $fields = [];
    $params = [];
    foreach ($data as $key => $value) {
      $fields[] = "$key = ?";
      $params[] = $value;
    }
    $params[] = $id_gasto;
    $params[] = $id_empresa;
    $set_clause = implode(', ', $fields);

    $sql = "UPDATE api_empresas.empresas_gastos SET $set_clause WHERE _id = ? AND id_empresa = ?";

    try {
      $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $result = $db->goQuery($sql, $params);
      $db->disconnect();

      // Nota: El método goQuery no parece devolver el número de filas afectadas,
      // por lo que no podemos verificar si el gasto existía antes de actualizar.
      // Se asume éxito si no hay excepción.

      $response->getBody()->write(json_encode(['message' => 'Gasto actualizado exitosamente.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al actualizar el gasto: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  /**
   * DELETE /gastos/{id_gasto}
   * Elimina un gasto.
   */
  $app->delete('/gastos/{id_gasto}', function (Request $request, Response $response, array $args) {
    $id_empresa = ID_EMPRESA;
    $id_gasto = $args['id_gasto'];
    if (!$id_empresa) {
      $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $sql = 'DELETE FROM api_empresas.empresas_gastos WHERE _id = ? AND id_empresa = ?';
    $params = [$id_gasto, $id_empresa];

    try {
      $db = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $result = $db->goQuery($sql, $params);
      $db->disconnect();

      $response->getBody()->write(json_encode(['message' => 'Gasto eliminado exitosamente.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error al eliminar el gasto: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // FIN GESTIÓN GASTOS FIJOS DE LA EMPRESA

  // =================================================================
  // REPORTE DE COSTOS DE PRODUCCIÓN
  // =================================================================
  $app->get('/reportes/costos-produccion/{inicio}/{fin}', function (Request $request, Response $response, array $args) {
    $inicio = $args['inicio'] ?? null;
    $fin = $args['fin'] ?? null;
    $id_empresa = ID_EMPRESA;

    $finalResponse = [];

    try {
      // --- 1. Consulta del Reporte Principal (Base de datos de la empresa) ---
      $db = new LocalDB();
      $whereConditions = [];
      $params = [];

      if ($inicio && $fin) {
        $whereConditions[] = 'DATE(a.moment) BETWEEN ? AND ?';
        $params[] = $inicio;
        $params[] = $fin;
      }

      $sqlReporte = 'SELECT
            a._id AS id_orden,                
            (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = a.responsable) vendedor,
            COALESCE(prod.total_productos, 0) AS total_productos,
            a.pago_total,
            COALESCE(ins.costos_de_insumos, 0) AS costos_de_insumos,
            COALESCE(mat.material_consumido_val, 0) AS material_consumido,
            (
                CASE
                    WHEN COALESCE(mat.material_consumido_val, 0) > 0 
                    THEN ((COALESCE(mat.material_consumido_val, 0) - COALESCE(mat.desperdicio_total, 0)) / COALESCE(mat.material_consumido_val, 0)) * 100
                    ELSE 0
                END) AS eficiencia,
            COALESCE(mano_de_obra.costo_mano_de_obra, 0) AS costo_mano_de_obra,
            (COALESCE(ins.costos_de_insumos, 0) + COALESCE(mano_de_obra.costo_mano_de_obra, 0)) AS costo_total,
            (a.pago_total - (COALESCE(ins.costos_de_insumos, 0) + COALESCE(mano_de_obra.costo_mano_de_obra, 0))) AS ganancia,
            COALESCE(tiempo.tiempo_de_produccion, 0) AS tiempo_de_produccion,
            COALESCE(repo.total_reposiciones, 0) AS reposiciones
        FROM
            ordenes a
        LEFT JOIN (
            SELECT id_orden, SUM(cantidad) AS total_productos
            FROM ordenes_productos
            GROUP BY id_orden
        ) prod ON a._id = prod.id_orden
        LEFT JOIN (
            SELECT c.id_orden, SUM((c.valor_inicial - c.valor_final) * d.costo) AS costos_de_insumos, GROUP_CONCAT(d.sku) AS skus
            FROM inventario_movimientos c JOIN inventario d ON c.id_insumo = d._id
            GROUP BY c.id_orden
        ) ins ON a._id = ins.id_orden
        LEFT JOIN (
            SELECT
                r.id_orden,
                SUM(r.metros) AS material_consumido_val,
                SUM(r.desperdicio) AS desperdicio_total
            FROM rendimiento r GROUP BY r.id_orden
        ) mat ON a._id = mat.id_orden
        LEFT JOIN (
            SELECT id_orden, SUM(monto_pago) AS costo_mano_de_obra
            FROM pagos
            GROUP BY id_orden
        ) mano_de_obra ON a._id = mano_de_obra.id_orden
        LEFT JOIN (
            SELECT
                ldea.id_orden,
                api_empresas.CalcularHorasLaborales(
                    MIN(ldea.fecha_inicio),
                    MAX(ldea.fecha_terminado),
                    (SELECT horario_laboral FROM api_empresas.empresas LIMIT 1)
                ) AS tiempo_de_produccion
            FROM
                lotes_detalles_empleados_asignados ldea
            WHERE ldea.fecha_inicio IS NOT NULL AND ldea.fecha_terminado IS NOT NULL
            GROUP BY ldea.id_orden
        ) tiempo ON a._id = tiempo.id_orden
        LEFT JOIN (
            SELECT id_orden, COUNT(_id) AS total_reposiciones
            FROM reposiciones
            GROUP BY id_orden
        ) repo ON a._id = repo.id_orden';

      if (!empty($whereConditions)) {
        $sqlReporte .= ' WHERE ' . implode(' AND ', $whereConditions);
      }

      $reporteData = $db->goQuery($sqlReporte, $params);
      $finalResponse['reporte_data'] = $reporteData;
      $db->disconnect();

      // --- 2. Consulta de Gastos Fijos (Base de datos de empresas) ---
      $dbEmpresas = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      $sqlGastos = "SELECT SUM(
                        CASE periodicidad
                            WHEN 'mensual' THEN monto / 4.33
                            WHEN 'trimestral' THEN monto / 13
                            WHEN 'semestral' THEN monto / 26
                            WHEN 'anual' THEN monto / 52
                            ELSE 0
                        END
                    ) AS total_gastos_semanales
                    FROM empresas_gastos
                    WHERE id_empresa = ? AND estatus = 'activo'";

      $gastosResult = $dbEmpresas->goQuery($sqlGastos, [$id_empresa]);
      $totalGastosSemanales = !empty($gastosResult) ? (float) $gastosResult[0]['total_gastos_semanales'] : 0;
      $dbEmpresas->disconnect();

      // --- 3. Cálculos combinados ---
      $totalProductosPeriodo = 0;
      foreach ($reporteData as $row) {
        $totalProductosPeriodo += $row['total_productos'];
      }

      $costoOperativoPorProducto = 0;
      if ($totalProductosPeriodo > 0) {
        $costoOperativoPorProducto = $totalGastosSemanales / $totalProductosPeriodo;
      }

      $finalResponse['costos_operativos'] = [
        'total_gastos_semanales' => $totalGastosSemanales,
        'total_productos_periodo' => $totalProductosPeriodo,
        'costo_operativo_por_producto' => $costoOperativoPorProducto
      ];

      // --- 4. Enviar respuesta ---
      $response->getBody()->write(json_encode($finalResponse, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => 'Error en la consulta del reporte: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // =================================================================
  // REPORTE DE MANO DE OBRA POR ORDEN
  // =================================================================
  $app->get('/reportes/mano-obra-por-orden/{id_orden}', function (Request $request, Response $response, array $args) {
    $id_orden = $args['id_orden'];

    $sql = 'SELECT
                    p.id_empleado,
                    eu.nombre AS nombre_empleado,
                    p.detalle AS departamento,
                    p.cantidad,
                    p.monto_pago
                FROM
                    pagos p
                JOIN
                    api_empresas.empresas_usuarios eu ON p.id_empleado = eu.id_usuario
                WHERE
                    p.id_orden = ?
                ORDER BY
                    eu.nombre, p.detalle';
    $db = new LocalDB();
    $data = $db->goQuery($sql, [$id_orden]);
    $db->disconnect();
    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // =================================================================
  // REPORTE DE MATERIAL CONSUMIDO POR ORDEN
  // =================================================================
  $app->get('/reportes/material-consumido-por-orden/{id_orden}', function (Request $request, Response $response, array $args) {
    $id_orden = $args['id_orden'];

    $sql = "SELECT
                    r.id_orden,
                    i.insumo AS material,
                    i.sku,
                    r.metros AS cantidad_consumida,
                    i.unidad,
                    r.desperdicio,
                    CASE
                        WHEN r.id_empleado_corte IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_corte)
                        WHEN r.id_empleado_impresion IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_impresion)
                        WHEN r.id_empleado_estampado IS NOT NULL THEN (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = r.id_empleado_estampado)
                        ELSE 'No Asignado'
                    END AS empleado,
                    CASE
                        WHEN r.id_empleado_corte IS NOT NULL THEN 'Corte'
                        WHEN r.id_empleado_impresion IS NOT NULL THEN 'Impresión'
                        WHEN r.id_empleado_estampado IS NOT NULL THEN 'Estampado'
                        ELSE 'No Asignado'
                    END AS departamento
                FROM rendimiento r
                JOIN inventario i ON r.id_insumo = i._id
                WHERE r.id_orden = ?";
    $db = new LocalDB();
    $data = $db->goQuery($sql, [$id_orden]);
    $db->disconnect();
    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->get('/calculo-pago-real', function (Request $request, Response $response) {
    $sql = "
        SELECT AVG(tasa) as promedio_tasa
        FROM metodos_de_pago
        WHERE
            moneda = 'Bolívares'
            AND metodo_pago IN ('Pagomovil', 'Transferencia')
            AND DATE(moment) = CURDATE()
    ";

$db = new LocalDB();
$data = $db->goQuery($sql);
$db->disconnect();
$response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });
};
