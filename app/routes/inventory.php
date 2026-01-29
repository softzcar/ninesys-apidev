<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

return function (App $app) {

    $app->get('/api/inventario/template-excel', function (Request $request, Response $response) {
        try {
            $localConnection = new LocalDB();

            // Obtener departamentos para la lista de validación
            $departamentos = $localConnection->goQuery('SELECT _id, departamento FROM departamentos');
            if (!is_array($departamentos)) {
                $departamentos = [];
            }

            // === NUEVA CONSULTA: Obtener ítems del catálogo de insumos/productos ===
            $catalogoInsumos = $localConnection->goQuery('SELECT _id, nombre FROM catalogo_insumos_productos');
            if (!is_array($catalogoInsumos)) {
                $catalogoInsumos = [];
            }
            // ===================================================================

            // Obtener rollos existentes para validación de unicidad
            $rollosExistentes = $localConnection->goQuery("SELECT sku, insumo FROM inventario WHERE insumo IS NOT NULL AND insumo <> ''");
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
            // === ACTUALIZADO: Añadimos 'Insumo' después de 'Nombre' ===
            $headersInventario = ['SKU', 'Nombre', 'Insumo', 'Cantidad', 'Unidad', 'Costo', 'Rendimiento', 'Departamento'];
            $sheetInventario->fromArray($headersInventario, NULL, 'A1');

            // Set column widths for Inventario sheet
            // === ACTUALIZADO: El rango se extiende hasta 'H' (antes 'G') por la nueva columna ===
            foreach (range('A', 'H') as $col) {
                $sheetInventario->getColumnDimension($col)->setAutoSize(true);
            }

            // --- Hidden Sheet: ListadoRollosNormalizado ---
            $sheetRollosNormalizado = $spreadsheet->createSheet();
            $sheetRollosNormalizado->setTitle('ListadoRollosNormalizado');
            $sheetRollosNormalizado->setCellValue('A1', 'Rollo_Normalizado');  // Header
            $row = 2;
            foreach ($rollosExistentes as $rollo) {
                if (is_array($rollo) && isset($rollo['sku'])) {
                    $normalizedRollo = strtoupper(str_replace('_', '', $rollo['sku']));
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
            $sheetUnidades->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

            // --- Hidden Sheet: ListadoDepartamentos ---
            $sheetDepartamentos = $spreadsheet->createSheet();
            $sheetDepartamentos->setTitle('ListadoDepartamentos');
            $sheetDepartamentos->fromArray([['ID', 'Nombre']], NULL, 'A1');  // Headers for hidden sheet
            $row = 2;
            foreach ($departamentos as $departamento) {
                if (is_array($departamento) && isset($departamento['_id']) && isset($departamento['departamento'])) {
                    $sheetDepartamentos->setCellValue('A' . $row, $departamento['_id']);
                    $sheetDepartamentos->setCellValue('B' . $row, $departamento['departamento']);
                    $row++;
                }
            }
            $sheetDepartamentos->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

            // === NUEVA HOJA OCULTA: ListadoInsumosCatalogo ===
            $sheetCatalogoInsumos = $spreadsheet->createSheet();
            $sheetCatalogoInsumos->setTitle('ListadoInsumosCatalogo');
            $sheetCatalogoInsumos->fromArray([['ID', 'Nombre']], NULL, 'A1');  // Headers
            $row = 2;
            foreach ($catalogoInsumos as $item) {
                if (is_array($item) && isset($item['_id']) && isset($item['nombre'])) {
                    $sheetCatalogoInsumos->setCellValue('A' . $row, $item['_id']);
                    $sheetCatalogoInsumos->setCellValue('B' . $row, $item['nombre']);
                    $row++;
                }
            }
            $sheetCatalogoInsumos->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
            // ===================================================================

            // --- Data Validation for Inventario sheet ---
            // Rollo (Column A) - Custom validation for uniqueness (case-insensitive, underscore-insensitive)
            $rolloValidation = $sheetInventario->getCell('A2')->getDataValidation();
            $rolloValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);  // Usando ruta completa
            $rolloValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);  // Usando ruta completa
            $rolloValidation->setAllowBlank(false);
            $rolloValidation->setShowErrorMessage(true);
            $rolloValidation->setErrorTitle('Rollo Duplicado');
            $rolloValidation->setError('El Rollo que ingresó ya existe en la base de datos o en este mismo archivo.');
            $formula = 'AND(COUNTIF(ListadoRollosNormalizado!A:A, SUBSTITUTE(UPPER(A2),"_",""))=0, COUNTIF(A:A,A2)=1)';
            $rolloValidation->setFormula1($formula);

            // Nombre (Column B) - No specific validation, can be text

            // === NUEVA VALIDACIÓN: Insumo (Columna C) - List validation ===
            $insumoCatalogoValidation = $sheetInventario->getCell('C2')->getDataValidation();
            $insumoCatalogoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);  // Usando ruta completa
            $insumoCatalogoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);  // Usando ruta completa
            $insumoCatalogoValidation->setAllowBlank(false);  // Replicando el comportamiento de 'Departamento'
            $insumoCatalogoValidation->setShowInputMessage(true);
            $insumoCatalogoValidation->setShowErrorMessage(true);
            $insumoCatalogoValidation->setShowDropDown(true);
            $insumoCatalogoValidation->setErrorTitle('Error de entrada');
            $insumoCatalogoValidation->setError('El valor no está en la lista de insumos del catálogo.');
            $insumoCatalogoValidation->setPromptTitle('Seleccionar Insumo');
            $insumoCatalogoValidation->setPrompt('Por favor, seleccione un insumo del catálogo de la lista.');
            $insumoCatalogoValidation->setFormula1('\'ListadoInsumosCatalogo\'!B$2:B$' . (count($catalogoInsumos) + 1));
            // ==============================================================

            // Cantidad (Columna D - ANTES C) - Numeric validation
            $cantidadValidation = $sheetInventario->getCell('D2')->getDataValidation();
            $cantidadValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);  // Usando ruta completa
            $cantidadValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);  // Usando ruta completa
            $cantidadValidation->setAllowBlank(false);
            $cantidadValidation->setShowErrorMessage(true);
            $cantidadValidation->setErrorTitle('Cantidad Inválida');
            $cantidadValidation->setError('La cantidad debe ser un número.');
            $cantidadValidation->setFormula1('0');
            $cantidadValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);  // Usando ruta completa

            // Unidad (Columna E - ANTES D) - List validation
            $unidadValidation = $sheetInventario->getCell('E2')->getDataValidation();
            $unidadValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);  // Usando ruta completa
            $unidadValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);  // Usando ruta completa
            $unidadValidation->setAllowBlank(false);
            $unidadValidation->setShowInputMessage(true);
            $unidadValidation->setShowErrorMessage(true);
            $unidadValidation->setShowDropDown(true);
            $unidadValidation->setErrorTitle('Error de entrada');
            $unidadValidation->setError('El valor no está en la lista de unidades.');
            $unidadValidation->setPromptTitle('Seleccionar Unidad');
            $unidadValidation->setPrompt('Por favor, seleccione una unidad de la lista (Metros, Kilos, Unidades).');
            $unidadValidation->setFormula1('\'ListadoUnidades\'!A$2:A$' . (count($unidades) + 1));

            // Costo (Columna F - ANTES E) - Numeric validation
            $costoValidation = $sheetInventario->getCell('F2')->getDataValidation();
            $costoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);  // Usando ruta completa
            $costoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);  // Usando ruta completa
            $costoValidation->setAllowBlank(false);
            $costoValidation->setShowErrorMessage(true);
            $costoValidation->setErrorTitle('Costo Inválido');
            $costoValidation->setError('El costo debe ser un número.');
            $costoValidation->setFormula1('0');
            $costoValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);  // Usando ruta completa

            // Rendimiento (Columna G - ANTES F) - Numeric validation
            $rendimientoValidation = $sheetInventario->getCell('G2')->getDataValidation();
            $rendimientoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);  // Usando ruta completa
            $rendimientoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);  // Usando ruta completa
            $rendimientoValidation->setAllowBlank(true);
            $rendimientoValidation->setShowErrorMessage(true);
            $rendimientoValidation->setErrorTitle('Rendimiento Inválido');
            $rendimientoValidation->setError('El rendimiento debe ser un número.');
            $rendimientoValidation->setFormula1('0');
            $rendimientoValidation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);  // Usando ruta completa

            // Departamento (Columna H - ANTES G) - List validation
            $departamentoValidation = $sheetInventario->getCell('H2')->getDataValidation();
            $departamentoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);  // Usando ruta completa
            $departamentoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);  // Usando ruta completa
            $departamentoValidation->setAllowBlank(false);
            $departamentoValidation->setShowInputMessage(true);
            $departamentoValidation->setShowErrorMessage(true);
            $departamentoValidation->setShowDropDown(true);
            $departamentoValidation->setErrorTitle('Error de entrada');
            $departamentoValidation->setError('El valor no está en la lista de departamentos.');
            $departamentoValidation->setPromptTitle('Seleccionar Departamento');
            $departamentoValidation->setPrompt('Por favor, seleccione un departamento de la lista.');
            $departamentoValidation->setFormula1('\'ListadoDepartamentos\'!B$2:B$' . (count($departamentos) + 1));

            // Apply validation to a range (e.g., up to row 1000)
            for ($i = 2; $i <= 1000; $i++) {
                $sheetInventario->getCell('A' . $i)->setDataValidation(clone $rolloValidation);
                // === NUEVO: Aplicar validación para Insumo ===
                $sheetInventario->getCell('C' . $i)->setDataValidation(clone $insumoCatalogoValidation);
                // === ACTUALIZADO: Las referencias de las columnas se han desplazado ===
                $sheetInventario->getCell('D' . $i)->setDataValidation(clone $cantidadValidation);
                $sheetInventario->getCell('E' . $i)->setDataValidation(clone $unidadValidation);
                $sheetInventario->getCell('F' . $i)->setDataValidation(clone $costoValidation);
                $sheetInventario->getCell('G' . $i)->setDataValidation(clone $rendimientoValidation);
                $sheetInventario->getCell('H' . $i)->setDataValidation(clone $departamentoValidation);
            }

            // Save the Excel file
            $fileName = 'plantilla_inventario_' . ID_EMPRESA . '.xlsx';
            $outputDirectory = $_SERVER['DOCUMENT_ROOT'] . '/public/downloads/carga_inventario/';
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
                'error_details' => $e->getMessage()
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
        $filePath = $_SERVER['DOCUMENT_ROOT'] . "/public/downloads/carga_productos/carga_de_productos_{$idEmpresa}.xlsx";  // Guardar en el directorio public
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No se enviaron ítems de inventario para procesar.'
            ], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = new LocalDB();

        try {
            // Obtener mapeos para convertir nombres de departamentos a IDs
            // NOTA: La columna 'departamento' en 'inventario' guarda el NOMBRE del departamento,
            // este mapeo se usa principalmente para validar que el nombre proporcionado exista.
            $departamentos_db = $db->goQuery('SELECT _id, departamento FROM departamentos');
            $departamento_map = array_column($departamentos_db, '_id', 'departamento');  // ['NombreDepartamento' => ID_Departamento]

            // === INICIO DE CAMBIOS: Mapeo para el catálogo de insumos ===
            // Obtener mapeos para convertir nombres de catálogo de insumos a IDs.
            // La columna 'id_catalogo' en la tabla 'inventario' guarda el _id del catálogo.
            $catalogo_insumos_db = $db->goQuery('SELECT _id, nombre FROM catalogo_insumos_productos');
            $catalogo_insumo_map = array_column($catalogo_insumos_db, '_id', 'nombre');  // ['NombreInsumoCatalogo' => ID_Catalogo]
            // === FIN DE CAMBIOS ===

            $processed_count = 0;
            $error_list = [];

            foreach ($inventoryItems as $item) {
                // Extracción de datos del ítem del JSON
                $sku = $item['SKU'] ?? null;
                $nombre_inventario = $item['Nombre'] ?? null;  // Esto se guarda en la columna 'insumo' de la tabla 'inventario'
                $nombre_catalogo_excel = $item['Insumo'] ?? null;  // Esto es el nombre del catálogo, usado para buscar el ID
                $cantidad = $item['Cantidad'] ?? null;
                $unidad = $item['Unidad'] ?? null;
                $costo = $item['Costo'] ?? null;
                $rendimiento = $item['Rendimiento'] ?? null;
                $departamento_nombre_excel = $item['Departamento'] ?? null;  // Esto se guarda en la columna 'departamento' de la tabla 'inventario'

                // Validaciones básicas de campos obligatorios
                if (empty($sku) || empty($nombre_inventario) || empty($nombre_catalogo_excel) || empty($cantidad) || empty($unidad) || empty($costo) || empty($departamento_nombre_excel)) {
                    $error_list[] = "Ítem de inventario incompleto (SKU: {$sku}). Se omitió. Revise SKU, Nombre, Insumo, Cantidad, Unidad, Costo, Departamento.";
                    continue;
                }

                // Mapear el nombre del Departamento a su ID (para validación de existencia)
                $departamento_id_for_validation = $departamento_map[$departamento_nombre_excel] ?? null;

                if ($departamento_id_for_validation === null) {
                    $error_list[] = "Departamento '{$departamento_nombre_excel}' no encontrado para el SKU {$sku}. Se omitió.";
                    continue;
                }

                // === INICIO DE CAMBIOS: Obtener el ID del catálogo ===
                $id_catalogo = $catalogo_insumo_map[$nombre_catalogo_excel] ?? null;

                if ($id_catalogo === null) {
                    $error_list[] = "Insumo de catálogo '{$nombre_catalogo_excel}' no encontrado para el SKU {$sku}. Se omitió.";
                    continue;
                }
                // === FIN DE CAMBIOS ===

                // Normalizar SKU para la búsqueda y validación de unicidad.
                // Es crucial que esta lógica de normalización coincida con cómo se realiza en la validación de la plantilla Excel.
                $normalized_sku_for_db_check = strtoupper(str_replace('_', '', $sku));

                // Verificar si el ítem de inventario ya existe por SKU (normalizado para la búsqueda)
                // Se ajusta la consulta para normalizar el SKU de la base de datos para la comparación,
                // garantizando consistencia con la validación de unicidad en el Excel.
                $check_sql = "SELECT _id FROM inventario WHERE REPLACE(UPPER(sku), '_', '') = ?";
                $existing_item = $db->goQuery($check_sql, [$normalized_sku_for_db_check]);

                $item_id = null;
                if ($existing_item) {
                    // Lógica de ACTUALIZACIÓN
                    $item_id = $existing_item[0]['_id'];
                    // === INICIO DE CAMBIOS: Añadimos id_catalogo a la sentencia UPDATE ===
                    $update_sql = 'UPDATE inventario SET id_catalogo = ?, insumo = ?, unidad = ?, costo = ?, rendimiento = ?, cantidad = ?, departamento = ?, sku = ? WHERE _id = ?';
                    $db->goQuery($update_sql, [
                        $id_catalogo,  // Nuevo: ID del catálogo
                        $nombre_inventario,  // Nombre de inventario (columna 'insumo')
                        $unidad,
                        $costo,
                        $rendimiento,
                        $cantidad,
                        $departamento_nombre_excel,  // Nombre del departamento (columna 'departamento')
                        $sku,  // SKU original del Excel
                        $item_id
                    ]);
                    // === FIN DE CAMBIOS ===
                } else {
                    // Lógica de INSERCIÓN
                    // === INICIO DE CAMBIOS: Añadimos id_catalogo a la sentencia INSERT y los valores ===
                    // Asegúrate de que el número de placeholders (?) coincida con el número de valores.
                    $insert_sql = 'INSERT INTO inventario (id_catalogo, insumo, unidad, costo, rendimiento, cantidad, departamento, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
                    $response_insert = $db->goQuery($insert_sql, [
                        $id_catalogo,  // Nuevo: ID del catálogo
                        $nombre_inventario,  // Nombre de inventario (columna 'insumo')
                        $unidad,
                        $costo,
                        $rendimiento,
                        $cantidad,
                        $departamento_nombre_excel,  // Nombre del departamento (columna 'departamento')
                        $sku  // SKU original del Excel
                    ]);
                    // === FIN DE CAMBIOS ===
                    $item_id = $db->getLastID();  // Se asume que LocalDB tiene un método para obtener el último ID
                }
                $processed_count++;
            }

            $message = "Carga masiva de inventario completada. Se procesaron {$processed_count} ítems.";
            if (!empty($error_list)) {
                $message .= ' Se encontraron errores en algunos ítems.';
            }

            // === Mejoras en la respuesta ===
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => $message,
                'processed_count' => $processed_count,
                'errors' => $error_list  // Devolvemos la lista de errores para que el cliente la maneje
            ], JSON_NUMERIC_CHECK));
            // === Fin mejoras ===
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            error_log('Error en bulk-load de inventario: ' . $e->getMessage());  // Registro de errores detallado
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al procesar la carga masiva de inventario. Por favor, intente de nuevo más tarde.',
                'error_details' => $e->getMessage()  // Solo para depuración, quitar en producción
            ], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } finally {
            $db->disconnect();
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
            $outputDirectory = $_SERVER['DOCUMENT_ROOT'] . '/public/downloads/carga_productos/';
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
            ROUND(((inv.costo / inv.cantidad_inicial) * (imo.valor_inicial - imo.valor_final)), 2) AS total_insumo,
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
      -- Usamos tres CTEs: uno para recargas, otro para fallback desde inventario, y otro para calcular el costo total.
      WITH 
      -- CTE 1: Encuentra el costo por ml de la última recarga para cada tanque.
      last_ink_refill_cost AS (
          SELECT
              tr.id_catalogo_impresora,
              tr.color,
              CASE 
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0 
              END AS ink_cost_per_ml,
              ROW_NUMBER() OVER (PARTITION BY tr.id_catalogo_impresora, tr.color ORDER BY tr.fecha_recarga DESC) as rn
          FROM
              tintas_recargas tr
          JOIN
              inventario inv ON tr.id_insumo = inv._id
      ),
      -- CTE 2: Fallback - Obtiene el costo por ml desde inventario usando tinta_filtro
      fallback_ink_cost AS (
          SELECT
              tf.color AS color_code,
              CASE
                  WHEN inv.cantidad_inicial > 0 THEN (inv.costo / inv.cantidad_inicial)
                  ELSE 0
              END AS ink_cost_per_ml
          FROM tinta_filtro tf
          JOIN inventario inv ON tf.id_inventario = inv._id
      ),
      -- CTE 3: Usa los CTEs anteriores para calcular el costo total de la tinta para cada orden.
      costos_por_orden AS (
          SELECT
              tin.id_orden,
              ROUND(
                  (COALESCE(tin.c, 0) * COALESCE(lic_c.ink_cost_per_ml, fic_c.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.m, 0) * COALESCE(lic_m.ink_cost_per_ml, fic_m.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.y, 0) * COALESCE(lic_y.ink_cost_per_ml, fic_y.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.k, 0) * COALESCE(lic_k.ink_cost_per_ml, fic_k.ink_cost_per_ml, 0)) +
                  (COALESCE(tin.w, 0) * COALESCE(lic_w.ink_cost_per_ml, fic_w.ink_cost_per_ml, 0))
              , 2) AS total_tinta_costo
          FROM
              tintas tin
          LEFT JOIN last_ink_refill_cost lic_c ON lic_c.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_c.color = 'C' AND lic_c.rn = 1
          LEFT JOIN last_ink_refill_cost lic_m ON lic_m.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_m.color = 'M' AND lic_m.rn = 1
          LEFT JOIN last_ink_refill_cost lic_y ON lic_y.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_y.color = 'Y' AND lic_y.rn = 1
          LEFT JOIN last_ink_refill_cost lic_k ON lic_k.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_k.color = 'K' AND lic_k.rn = 1
          LEFT JOIN last_ink_refill_cost lic_w ON lic_w.id_catalogo_impresora = tin.id_catalogo_impresoras AND lic_w.color = 'W' AND lic_w.rn = 1
          -- Fallback desde inventario
          LEFT JOIN fallback_ink_cost fic_c ON fic_c.color_code = 'C'
          LEFT JOIN fallback_ink_cost fic_m ON fic_m.color_code = 'M'
          LEFT JOIN fallback_ink_cost fic_y ON fic_y.color_code = 'Y'
          LEFT JOIN fallback_ink_cost fic_k ON fic_k.color_code = 'K'
          LEFT JOIN fallback_ink_cost fic_w ON fic_w.color_code = 'W'
          GROUP BY tin.id_orden
      )

      -- Consulta Final: Unimos tu consulta original con nuestros costos calculados.
      SELECT 
          imo.id_orden, 
          imo.c AS cyan, 
          imo.m AS magenta, 
          imo.y AS yellow, 
          imo.k AS black,
          imo.w AS white,
          (COALESCE(imo.c, 0) + COALESCE(imo.m, 0) + COALESCE(imo.y, 0) + COALESCE(imo.k, 0) + COALESCE(imo.w, 0)) AS total_tinta_consumo_ml,
          cpo.total_tinta_costo
      FROM 
          tintas imo
      LEFT JOIN 
          costos_por_orden cpo ON imo.id_orden = cpo.id_orden
      {$where}
      ORDER BY 
          imo.id_orden ASC
      SQL;

        $object['tintas'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER TODOS LOS INSUMOS
    $app->get('/insumos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM inventario WHERE cantidad > 0 ORDER BY insumo ASC';
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

                    $id_catalogo = (isset($miInsumo['id_catalogo_producto']) && $miInsumo['id_catalogo_producto'] !== 'null' && $miInsumo['id_catalogo_producto'] !== '')
                        ? "'" . $miInsumo['id_catalogo_producto'] . "'"
                        : "NULL";

                    $values = "('{$now}', '{$miInsumo['insumo']}', '{$miInsumo['departamento']}', '{$miInsumo['unidad']}', '{$miInsumo['rendimiento']}', '{$miInsumo['costo']}', {$currentCantidad}, '{$miInsumo['sku']}', {$id_catalogo})";
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

                $id_catalogo = (isset($miInsumo['id_catalogo_producto']) && $miInsumo['id_catalogo_producto'] !== 'null' && $miInsumo['id_catalogo_producto'] !== '')
                    ? "'" . $miInsumo['id_catalogo_producto'] . "'"
                    : "NULL";

                $values = "('{$now}', '{$miInsumo['insumo']}', '{$miInsumo['departamento']}', '{$miInsumo['unidad']}', '{$miInsumo['rendimiento']}', '{$miInsumo['costo']}', {$cantidad}, '{$miInsumo['sku']}', {$id_catalogo})";
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

        $id_catalogo = (isset($miInsumo['id_catalogo_producto']) && $miInsumo['id_catalogo_producto'] !== 'null' && $miInsumo['id_catalogo_producto'] !== '')
            ? "'" . $miInsumo['id_catalogo_producto'] . "'"
            : ((isset($miInsumo['id_catalogo']) && $miInsumo['id_catalogo'] !== 'null' && $miInsumo['id_catalogo'] !== '') ? "'" . $miInsumo['id_catalogo'] . "'" : 'NULL');

        $values .= "id_catalogo=" . $id_catalogo;

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
            } {
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
        if (empty($miInsumo)) {
            $miInsumo = json_decode($request->getBody()->getContents(), true);
        }
        $object = []; // Init object
        $object['debug_received'] = $miInsumo; // DEBUG ECHO
        $localConnection = new LocalDB();

        // Verifcar si es reposicion y actualizar el campor `terminada`
        if ($miInsumo['es_reposicion'] == 1) {
            // $sql = "UPDATE reposiciones SET terminada = 1 WHERE id_orden = {$miInsumo['id_orden']} AND id_empleado = {$miInsumo['id_empleado']};";
            // $sql = "DELETE FROM `ordenes_fila_reposiciones` WHERE id_orden = {$miInsumo['id_orden']};";
            // $object["sql_update_reposiones"] = $sql;
            // $update_reposiciones = $localConnection->goQuery($sql);
        }

        // buscar cantidad actual del producto
        $sql_check = 'SELECT cantidad, sku, rendimiento FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
        $object['sql_cantidad_producto'] = $sql_check;
        $cantidad_producto = $localConnection->goQuery($sql_check);
        $object['cantidad_producto'] = $cantidad_producto;

        $cantidad_inicial = floatval($cantidad_producto[0]['cantidad']);
        $rendimiento = floatval($cantidad_producto[0]['rendimiento']); // Obtener rendimiento
        $object['cantidad_inicial'] = $cantidad_inicial;

        if ($miInsumo['departamento'] === 'Estampado') {
            // Para Estampado, el empleado ingresa Metros
            // La fórmula es: Kilos = Metros / Rendimiento
            $input_metros = floatval($miInsumo['cantidad_consumida']);

            // Validar rendimiento para evitar división por cero
            if ($rendimiento <= 0) {
                $rendimiento = 1; // Fallback 1:1 si no hay rendimiento definido
            }

            $cantidad_consumida_kg = $input_metros / $rendimiento;
            $cantidad_consumida = floatval($cantidad_inicial) - $cantidad_consumida_kg; // Restar los kilos calculados al inventario

            $object['cantidad_inicial'] = $cantidad_inicial;
            $object['cantidad_consumida_kilos'] = $cantidad_consumida_kg;
            $object['metros_ingresados'] = $input_metros;
            $object['rendimiento_utilizado'] = $rendimiento;

            // Logic for Auto Remanente (Employee Finish) - MUST HAPPEN BEFORE ANY UPDATE
            if (isset($miInsumo['auto_remanente']) && ($miInsumo['auto_remanente'] == 'true' || $miInsumo['auto_remanente'] === true)) {
                // Fetch current quantity BEFORE any modification
                $current_qty = $cantidad_inicial; // This is the actual current inventory

                // If we're in Estampado, convert to meters for display/logging purposes
                if ($miInsumo['departamento'] === 'Estampado') {
                    $current_qty_display = $current_qty * $rendimiento; // Convert Kg to Mt for logging
                    $object['remanente_kg'] = $current_qty;
                    $object['remanente_mt'] = $current_qty_display;
                }

                $sql_rem = "INSERT INTO inventario_remanentes (id_insumo, cantidad, motivo, observacion, id_empleado, fecha) VALUES ({$miInsumo['id_insumo']}, {$current_qty}, 'Consumo Total (Empleado)', 'Generado automáticamente al terminar todo desde empleados', {$miInsumo['id_empleado']}, NOW())";
                $rem_result = $localConnection->goQuery($sql_rem);

                $object['remanente_updated_auto'] = $current_qty;
                $object['debug_sql_rem'] = $sql_rem;
                $object['debug_rem_result'] = $rem_result;
                $object['debug_auto_remanente_triggered'] = true;
            }

            // Now update inventory based on consumption (or set to 0 if finishing)
            if (isset($miInsumo['tipo']) && $miInsumo['tipo'] === 'fin') {
                // Set to 0 when finishing
                $sql = 'UPDATE inventario SET cantidad = 0 WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            } else {
                // Normal consumption update
                $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            }

            $sql .= 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $update_cantidad_inventario = $localConnection->goQuery($sql);
            $object['update_cantidad_invrntario_SQL'] = $sql;
            $object['update_cantidad_inventario_RSP'] = $update_cantidad_inventario;
            $object['update_success'] = !empty($update_cantidad_inventario);


        } elseif ($miInsumo['departamento'] === 'Corte') {
            // Para Corte, se mantiene la lógica original (ingresan Kg directamente)
            $cantidad_consumida_kg = floatval($miInsumo['cantidad_consumida']);
            $cantidad_consumida = floatval($cantidad_inicial) - $cantidad_consumida_kg;

            $object['cantidad_inicial'] = $cantidad_inicial;
            $object['cantidad_consumida_kilos'] = $cantidad_consumida_kg;

            $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';

            // Logic for Auto Remanente (Employee Finish)
            if (isset($miInsumo['auto_remanente']) && $miInsumo['auto_remanente'] == 'true') {
                $current_qty = $cantidad_consumida;
                $current_qty = $cantidad_consumida;
                $sql_rem = "INSERT INTO inventario_remanentes (id_insumo, cantidad, motivo, observacion, id_empleado, fecha) VALUES ({$miInsumo['id_insumo']}, {$current_qty}, 'Consumo Total (Producción)', 'Generado automáticamente al terminar todo desde empleados', {$miInsumo['id_empleado']}, NOW())";
                $localConnection->goQuery($sql_rem);
                $object['remanente_updated_auto'] = $current_qty;
            }

            // Check if finishing
            if (isset($miInsumo['tipo']) && $miInsumo['tipo'] === 'fin') {
                $sql = 'UPDATE inventario SET cantidad = 0 WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            }
            $sql .= 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $update_cantidad_inventario = $localConnection->goQuery($sql);
            $object['update_cantidad_invrntario_SQL'] = $sql;
            $object['update_cantidad_inventario_RSP'] = $update_cantidad_inventario;
            $object['update_success'] = !empty($update_cantidad_inventario);
        } else {
            // Comportamiento por defecto (Impresión, etc.): Resta directa
            // Asumimos que la cantidad ingresada está en la misma unidad que el inventario
            $consumo_real = floatval($miInsumo['cantidad_consumida']);
            $cantidad_consumida = floatval($cantidad_inicial) - $consumo_real;

            $object['cantidad_inicial'] = $cantidad_inicial;
            $object['cantidad_consumida_real'] = $consumo_real;

            // Logic for Auto Remanente (Employee Finish) - MUST HAPPEN BEFORE ANY UPDATE
            if (isset($miInsumo['auto_remanente']) && ($miInsumo['auto_remanente'] == 'true' || $miInsumo['auto_remanente'] === true)) {
                // Use INITIAL quantity (current in DB) as remanente, not the calculated one after consumption
                $current_qty = $cantidad_inicial;
                $sql_rem = "INSERT INTO inventario_remanentes (id_insumo, cantidad, motivo, observacion, id_empleado, fecha) VALUES ({$miInsumo['id_insumo']}, {$current_qty}, 'Consumo Total (Empleado)', 'Generado automáticamente al terminar todo desde empleados', {$miInsumo['id_empleado']}, NOW())";
                $rem_result = $localConnection->goQuery($sql_rem);

                $object['remanente_updated_auto'] = $current_qty;
                $object['debug_sql_rem'] = $sql_rem;
                $object['debug_rem_result'] = $rem_result;
                $object['debug_auto_remanente_triggered'] = true;
            }

            // Now update inventory based on consumption (or set to 0 if finishing)
            if (isset($miInsumo['tipo']) && $miInsumo['tipo'] === 'fin') {
                // Set to 0 when finishing
                $sql = 'UPDATE inventario SET cantidad = 0 WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            } else {
                // Normal consumption update
                $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            }

            $sql .= 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $update_cantidad_inventario = $localConnection->goQuery($sql);
            $object['update_cantidad_invrntario_SQL'] = $sql;
            $object['update_cantidad_inventario_RSP'] = $update_cantidad_inventario;

            // Check if update was successful (assuming array return means success for select, or affected rows for update)
            // For now, if $update_cantidad_inventario is not null/false
            $object['update_success'] = !empty($update_cantidad_inventario);
        }

        // Logic for Remanente
        // Case 1: Explicit remanente (Admin Manual), BUT ONLY if auto_remanente is NOT active
        // Because if auto_remanente is active, we already calculated and saved the real value above.
        $auto_active = (isset($miInsumo['auto_remanente']) && ($miInsumo['auto_remanente'] == 'true' || $miInsumo['auto_remanente'] === true));

        // Adding strict check: remanente must be greater than 0
        if (!$auto_active && isset($miInsumo['remanente']) && is_numeric($miInsumo['remanente']) && floatval($miInsumo['remanente']) > 0) {
            $remanente_val = floatval($miInsumo['remanente']);
            $motivo = isset($miInsumo['motivo']) ? $miInsumo['motivo'] : 'Terminación (Manual)';
            $observacion = isset($miInsumo['observacion']) ? $miInsumo['observacion'] : '';

            $sql_rem = "INSERT INTO inventario_remanentes (id_insumo, cantidad, motivo, observacion, id_empleado, fecha) VALUES ({$miInsumo['id_insumo']}, {$remanente_val}, '{$motivo}', '{$observacion}', {$miInsumo['id_empleado']}, NOW())";
            $localConnection->goQuery($sql_rem);
            $object['remanente_updated'] = $remanente_val;
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

        // --- INICIO: Verificación de duplicados antes de insertar ---
        // Preparar el valor de id_reposicion
        $id_reposicion = (isset($miInsumo['id_reposicion']) && $miInsumo['id_reposicion'] !== 'null' && $miInsumo['id_reposicion'] !== '')
            ? intval($miInsumo['id_reposicion'])
            : null;

        // Verificar si ya existe un movimiento para esta orden/insumo/departamento
        $sql_check_mov = 'SELECT _id FROM inventario_movimientos 
                          WHERE id_orden = ? AND id_insumo = ? AND id_departamento = ?';
        $check_mov_params = [
            $miInsumo['id_orden'],
            $miInsumo['id_insumo'],
            $miInsumo['id_departamento']
        ];

        if ($id_reposicion) {
            $sql_check_mov .= ' AND id_reposicion = ?';
            $check_mov_params[] = $id_reposicion;
        } else {
            $sql_check_mov .= ' AND id_reposicion IS NULL';
        }

        $existing_mov = $localConnection->goQuery($sql_check_mov, $check_mov_params);

        // Preparar el valor de id_catalogo
        $id_catalogo = (isset($miInsumo['id_catalogo']) && $miInsumo['id_catalogo'] !== 'null' && $miInsumo['id_catalogo'] !== '')
            ? intval($miInsumo['id_catalogo'])
            : null;

        if (!empty($existing_mov) && isset($existing_mov[0]['_id'])) {
            // Ya existe, hacer UPDATE en lugar de INSERT
            $sql = 'UPDATE inventario_movimientos 
                    SET id_empleado = ?, 
                        id_producto = ?, 
                        id_catalogo_insumos_prodcutos = ?,
                        valor_inicial = ?, 
                        valor_final = ?,
                        moment = NOW()
                    WHERE _id = ?';
            $params = [
                $miInsumo['id_empleado'],
                $miInsumo['id_producto'],
                $id_catalogo,
                $cantidad_inicial,
                $cantidad_consumida,
                $existing_mov[0]['_id']
            ];
            $object['sql_inventario_movimientos'] = $sql;
            $object['resp_invetario_movimientos'] = $localConnection->goQuery($sql, $params);
            $object['movimiento_actualizado'] = true;
            $object['movimiento_id'] = isset($existing_mov[0]['_id']) ? $existing_mov[0]['_id'] : null;
        } else {
            // No existe, hacer INSERT
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
                 valor_final,
                 id_reposicion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $params = [
                $miInsumo['id_orden'],
                $miInsumo['id_empleado'],
                $miInsumo['id_producto'],
                $miInsumo['id_insumo'],
                $miInsumo['id_departamento'],
                $id_catalogo,
                $miInsumo['departamento'],
                $cantidad_inicial,
                $cantidad_consumida,
                $id_reposicion
            ];

            $object['sql_inventario_movimientos'] = $sql;
            $object['resp_invetario_movimientos'] = $localConnection->goQuery($sql, $params);
            $object['movimiento_creado'] = true;
        }
        // --- FIN: Verificación de duplicados ---

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
        $object['local_connection'] = $localConnection;

        $object['fields'][5]['label'] = 'ACCIONES';

        if ($args['departamento'] === 'todos') {
            $sql = 'SELECT
                _id,
                _id rollo,
                sku,
                insumo,
                id_catalogo AS id_catalogo_producto,
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
                id_catalogo AS id_catalogo_producto,
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
        $object['sql'] = $sql;
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
        a.insumo,
        b.color,
        a.costo,
        a.cantidad
        FROM
        inventario a
        JOIN tinta_filtro b ON b.id_inventario = a._id
        WHERE a.cantidad > 0';

        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode(
            $data,
            JSON_NUMERIC_CHECK
        ));
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

    // OBTENER CONSUMO DE MATERIAL POR INSUMO
    $app->get('/inventario/consumo/{id_insumo}', function (Request $request, Response $response, array $args) {
        $id_insumo = $args['id_insumo'] ?? null;

        if (!$id_insumo || !is_numeric($id_insumo)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de insumo inválido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $localConnection = new LocalDB();

        // Consulta para obtener el consumo de material con los datos relacionados
        $sql = "SELECT
            inv._id id_inventario,
            inv.insumo,
            imo._id id_movimiento,
            imo.id_orden,      
            ord.cliente_nombre,
            dep._id id_departamento,
            emp.id_usuario id_empleado,
            emp.nombre nombre_empleado,
            dep.departamento,
            (imo.valor_inicial - imo.valor_final) material_consumido,
            imo.valor_inicial,
            imo.valor_final,
            inv.cantidad cantidad_inventario,
            imo.moment fecha_del_consumo
        FROM
            inventario inv
        JOIN inventario_movimientos imo ON imo.id_insumo = inv._id 
        JOIN departamentos dep ON dep._id = imo.id_departamento 
        JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = imo.id_empleado 
        JOIN ordenes ord ON ord._id = imo.id_orden
        WHERE
            imo.id_insumo = ?
        ORDER BY imo.moment ASC";

        try {
            $result = $localConnection->goQuery($sql, [$id_insumo]);

            $object = [
                'success' => true,
                'data' => $result ?? [],
                'count' => count($result ?? [])
            ];

            $localConnection->disconnect();

            $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $e) {
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener el consumo de material',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    // ACTUALIZAR CONSUMO DE MATERIAL Y REGISTRAR EN HISTORIAL
    $app->patch('/inventario/consumo/{id_movimiento}', function (Request $request, Response $response, array $args) {
        $id_movimiento = $args['id_movimiento'] ?? null;

        if (!$id_movimiento || !is_numeric($id_movimiento)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de movimiento inválido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }


        // Parsear JSON del body
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'JSON inválido en el body'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $nuevo_valor = $data['material_consumido'] ?? null;
        $observaciones = $data['observaciones'] ?? '';
        $id_usuario = $data['id_usuario'] ?? null;

        // Validaciones
        if ($nuevo_valor === null || !is_numeric($nuevo_valor) || $nuevo_valor < 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Valor de material consumido inválido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        if (!$id_usuario || !is_numeric($id_usuario)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $localConnection = new LocalDB();

        try {
            // Iniciar transacción
            $localConnection->beginTransaction();

            // Obtener valor anterior
            $sql_get_current = "SELECT 
                (valor_inicial - valor_final) as material_consumido_actual,
                valor_inicial,
                id_insumo
            FROM inventario_movimientos 
            WHERE _id = ?";

            $current_data = $localConnection->goQuery($sql_get_current, [$id_movimiento]);

            if (empty($current_data)) {
                $localConnection->rollback();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Movimiento no encontrado'
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(404);
            }

            $valor_anterior = $current_data[0]['material_consumido_actual'];
            $valor_inicial = $current_data[0]['valor_inicial'];
            $id_insumo = $current_data[0]['id_insumo'];

            // Calcular nuevo valor_final
            $nuevo_valor_final = $valor_inicial - $nuevo_valor;

            // Registrar en historial si hubo cambio
            if ($valor_anterior != $nuevo_valor) {
                $sql_historial = "INSERT INTO inventario_movimientos_historial 
                    (id_movimiento, campo_modificado, valor_anterior, valor_nuevo, id_usuario_modificacion, observaciones) 
                    VALUES (?, 'material_consumido', ?, ?, ?, ?)";

                $localConnection->goQuery($sql_historial, [
                    $id_movimiento,
                    $valor_anterior,
                    $nuevo_valor,
                    $id_usuario,
                    $observaciones
                ]);
            }

            // Actualizar valor_final en inventario_movimientos
            $sql_update = "UPDATE inventario_movimientos 
                SET valor_final = ? 
                WHERE _id = ?";

            $localConnection->goQuery($sql_update, [$nuevo_valor_final, $id_movimiento]);

            // Confirmar transacción
            $localConnection->commit();
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Material consumido actualizado correctamente',
                'data' => [
                    'id_movimiento' => $id_movimiento,
                    'valor_anterior' => $valor_anterior,
                    'valor_nuevo' => $nuevo_valor,
                    'valor_final' => $nuevo_valor_final
                ]
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            if ($localConnection->inTransaction()) {
                $localConnection->rollback();
            }
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al actualizar el consumo de material',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    // OBTENER HISTORIAL DE CAMBIOS DE UN MOVIMIENTO
    $app->get('/inventario/consumo/{id_movimiento}/historial', function (Request $request, Response $response, array $args) {
        $id_movimiento = $args['id_movimiento'] ?? null;

        if (!$id_movimiento || !is_numeric($id_movimiento)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de movimiento inválido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $localConnection = new LocalDB();

        try {
            // Consulta para obtener el historial con datos del usuario
            $sql = "SELECT
                h._id,
                h.id_movimiento,
                h.campo_modificado,
                h.valor_anterior,
                h.valor_nuevo,
                h.id_usuario_modificacion,
                emp.nombre AS usuario_nombre,
                h.fecha_modificacion,
                h.observaciones
            FROM
                inventario_movimientos_historial h
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = h.id_usuario_modificacion
            WHERE
                h.id_movimiento = ?
            ORDER BY h.fecha_modificacion DESC";

            $historial = $localConnection->goQuery($sql, [$id_movimiento]);

            // Consulta para obtener datos de creación del movimiento
            $sql_creation = "SELECT 
                m._id as id_movimiento,
                (m.valor_inicial - m.valor_final) as nuevo_valor,
                m.id_empleado,
                emp.nombre as usuario_nombre,
                m.fecha as fecha_modificacion
            FROM inventario_movimientos m
            LEFT JOIN api_empresas.empresas_usuarios emp ON emp.id_usuario = m.id_empleado
            WHERE m._id = ?";

            $creation_data = $localConnection->goQuery($sql_creation, [$id_movimiento]);

            if (!empty($creation_data)) {
                $creation_record = $creation_data[0];
                // Formatear registro de creación para que coincida con estructura de historial
                $fake_history_record = [
                    '_id' => 'orig_' . $creation_record['id_movimiento'],
                    'id_movimiento' => $creation_record['id_movimiento'],
                    'campo_modificado' => 'Creación',
                    'valor_anterior' => 0,
                    'valor_nuevo' => $creation_record['nuevo_valor'],
                    'id_usuario_modificacion' => $creation_record['id_empleado'],
                    'usuario_nombre' => $creation_record['usuario_nombre'],
                    'fecha_modificacion' => $creation_record['fecha_modificacion'],
                    'observaciones' => 'Registro inicial de consumo'
                ];

                // Agregar el registro de creación al final del array (es el más antiguo)
                $historial[] = $fake_history_record;
            }

            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $historial ?? [],
                'count' => count($historial ?? [])
            ], JSON_NUMERIC_CHECK));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener el historial',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    // ============================================================================
    // GESTIÓN DE REMANENTES
    // ============================================================================

    /**
     * GET /api/inventario/remanentes
     * Lista remanentes con filtros, paginación y ordenamiento
     */
    $app->get('/api/inventario/remanentes', function (Request $request, Response $response) {
        try {
            $localConnection = new LocalDB();
            $params = $request->getQueryParams();

            // Parámetros de filtrado
            $tipo = $params['tipo'] ?? 'activos'; // 'activos', 'terminados', 'todos'
            $busqueda = $params['busqueda'] ?? '';
            $fecha_desde = $params['fecha_desde'] ?? null;
            $fecha_hasta = $params['fecha_hasta'] ?? null;

            // Parámetros de paginación
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 20;
            $offset = ($page - 1) * $limit;

            // Parámetros de ordenamiento
            $order_by = $params['order_by'] ?? 'r.fecha';
            $order_dir = $params['order_dir'] ?? 'DESC';

            // Construir WHERE clause
            $where_conditions = [];
            $bind_params = [];

            // Filtro por tipo
            if ($tipo === 'activos') {
                $where_conditions[] = "i.cantidad > 0";
            } elseif ($tipo === 'terminados') {
                $where_conditions[] = "i.cantidad = 0";
            }
            // 'todos' no agrega condición

            // Filtro por búsqueda (ID insumo, SKU, nombre empleado)
            if (!empty($busqueda)) {
                $where_conditions[] = "(i._id = ? OR i.sku LIKE ? OR i.insumo LIKE ? OR emp.nombre LIKE ?)";
                $bind_params[] = $busqueda;
                $bind_params[] = "%{$busqueda}%";
                $bind_params[] = "%{$busqueda}%";
                $bind_params[] = "%{$busqueda}%";
            }

            // Filtro por rango de fechas
            if ($fecha_desde) {
                $where_conditions[] = "r.fecha >= ?";
                $bind_params[] = $fecha_desde;
            }
            if ($fecha_hasta) {
                $where_conditions[] = "r.fecha <= ?";
                $bind_params[] = $fecha_hasta . ' 23:59:59';
            }

            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

            // Consulta de conteo total
            $sql_count = "SELECT COUNT(*) as total
                FROM inventario_remanentes r
                INNER JOIN inventario i ON r.id_insumo = i._id
                LEFT JOIN api_empresas.empresas_usuarios emp ON r.id_empleado = emp.id_usuario
                {$where_clause}";

            $count_result = $localConnection->goQuery($sql_count, $bind_params);
            $total = $count_result[0]['total'] ?? 0;

            // Consulta principal
            $sql = "SELECT 
                r._id as id_remanente,
                r.id_insumo,
                r.cantidad as cantidad_remanente,
                r.motivo,
                r.observacion,
                r.fecha,
                i.sku,
                i.insumo as nombre_insumo,
                i.cantidad as stock_actual,
                i.unidad,
                emp.id_usuario as id_empleado,
                emp.nombre as nombre_empleado
            FROM inventario_remanentes r
            INNER JOIN inventario i ON r.id_insumo = i._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON r.id_empleado = emp.id_usuario
            {$where_clause}
            ORDER BY {$order_by} {$order_dir}
            LIMIT {$limit} OFFSET {$offset}";

            $remanentes = $localConnection->goQuery($sql, $bind_params);
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $remanentes ?? [],
                'total' => intval($total),
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ], JSON_NUMERIC_CHECK));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            if (isset($localConnection)) {
                $localConnection->disconnect();
            }

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener remanentes',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    /**
     * PUT /api/inventario/remanentes/{id}
     * Edita un remanente existente
     */
    $app->put('/api/inventario/remanentes/{id}', function (Request $request, Response $response, array $args) {
        try {
            $localConnection = new LocalDB();
            $id_remanente = $args['id'];

            // Parsear manualmente el cuerpo JSON del PUT request
            $raw_body = (string) $request->getBody();
            $data = json_decode($raw_body, true);

            if ($data === null) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos JSON inválidos'
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }

            // Validaciones
            if (!isset($data['cantidad']) || floatval($data['cantidad']) < 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La cantidad debe ser mayor o igual a 0'
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }

            $cantidad = floatval($data['cantidad']);
            $motivo = $data['motivo'] ?? '';
            $observacion = $data['observacion'] ?? '';

            // Actualizar remanente
            $sql = "UPDATE inventario_remanentes 
                SET cantidad = ?, motivo = ?, observacion = ?
                WHERE _id = ?";

            $localConnection->goQuery($sql, [$cantidad, $motivo, $observacion, $id_remanente]);

            // Obtener el registro actualizado
            $sql_select = "SELECT 
                r._id as id_remanente,
                r.id_insumo,
                r.cantidad as cantidad_remanente,
                r.motivo,
                r.observacion,
                r.fecha,
                i.sku,
                i.insumo as nombre_insumo,
                i.cantidad as stock_actual,
                i.unidad,
                emp.id_usuario as id_empleado,
                emp.nombre as nombre_empleado
            FROM inventario_remanentes r
            INNER JOIN inventario i ON r.id_insumo = i._id
            LEFT JOIN api_empresas.empresas_usuarios emp ON r.id_empleado = emp.id_usuario
            WHERE r._id = ?";

            $updated = $localConnection->goQuery($sql_select, [$id_remanente]);
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Remanente actualizado correctamente',
                'data' => $updated[0] ?? null
            ], JSON_NUMERIC_CHECK));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            if (isset($localConnection)) {
                $localConnection->disconnect();
            }

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al actualizar remanente',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    /**
     * DELETE /api/inventario/remanentes/{id}
     * Elimina un remanente
     */
    $app->delete('/api/inventario/remanentes/{id}', function (Request $request, Response $response, array $args) {
        try {
            $localConnection = new LocalDB();
            $id_remanente = $args['id'];

            // Verificar que el remanente existe
            $sql_check = "SELECT _id FROM inventario_remanentes WHERE _id = ?";
            $exists = $localConnection->goQuery($sql_check, [$id_remanente]);

            if (empty($exists)) {
                $localConnection->disconnect();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Remanente no encontrado'
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(404);
            }

            // Eliminar remanente
            $sql_delete = "DELETE FROM inventario_remanentes WHERE _id = ?";
            $localConnection->goQuery($sql_delete, [$id_remanente]);
            $localConnection->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Remanente eliminado correctamente'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            if (isset($localConnection)) {
                $localConnection->disconnect();
            }

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al eliminar remanente',
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });

    /** FIN INVENTARIO */

}; // Fin de la función que envuelve las rutas
