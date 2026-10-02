<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Rutas internas de Cotizaciones y Creación Robusta de Presupuestos para el Asistente de IA
 *
 * Permite:
 * 1. POST /internal/presupuestos/{id_empresa}/calcular (Solo lectura: calcula subtotales, tramos y recargos XL sin tocar BD).
 * 2. POST /internal/presupuestos/{id_empresa}/crear (Mutación ACID: resuelve clientes, productos, tallas, telas, recargos e inserta presupuesto con asignación de vendedor).
 */
return function (App $app) {

    $makeRespondJson = function (Response $response) {
        return function (array $payload, int $status) use ($response) {
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        };
    };

    $validarEmpresa = function (int $idEmpresa): bool {
        try {
            $central = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
            $rows = $central->goQuery('SELECT id_empresa FROM empresas WHERE id_empresa = ? AND activo = 1', [$idEmpresa]);
            $central->disconnect();
            return !empty($rows) && !isset($rows['status']);
        } catch (\Throwable $e) {
            error_log('[ai_quotes] Error validando empresa: ' . $e->getMessage());
            return false;
        }
    };

    /**
     * Normaliza el corte a uno de los valores estándar del ERP
     */
    $normalizarCorte = function (?string $corte): string {
        if (!$corte) return 'Caballeros';
        $c = strtolower(trim($corte));
        if (strpos($c, 'dama') !== false) return 'Damas';
        if (strpos($c, 'niñ') !== false || strpos($c, 'nino') !== false) return 'Niños';
        if (strpos($c, 'unisex') !== false) return 'Unisex';
        return 'Caballeros';
    };

    /**
     * Calcula el recargo por talla XL (+$1 por cada X adicional a XL)
     */
    $calcularRecargoXL = function (?string $talla): float {
        if (!$talla) return 0.0;
        $t = strtoupper(trim($talla));
        if (strpos($t, 'XL') === false) return 0.0;

        // Extraer número si viene como '2XL', '3XL', etc.
        $numero = str_replace('XL', '', $t);
        if ($numero === '') return 1.0; // XL base = +$1
        if (is_numeric($numero)) {
            $val = (int) $numero;
            return $val > 0 ? (float) $val : 1.0;
        }
        // Si viene como 'XXL'
        $countX = substr_count($t, 'X');
        return $countX > 0 ? (float) $countX : 1.0;
    };

    /**
     * Valida y calcula una lista de items de pedido
     */
    $procesarItems = function (LocalDB $db, array $rawItems) use ($normalizarCorte, $calcularRecargoXL): array {
        $itemsProcesados = [];
        $granTotal = 0.0;
        $totalPrendas = 0;

        foreach ($rawItems as $raw) {
            $cant = isset($raw['cantidad']) ? (int) $raw['cantidad'] : 1;
            if ($cant <= 0) $cant = 1;

            $cod = !empty($raw['cod']) ? (int) $raw['cod'] : null;
            $nombreBuscado = trim((string) ($raw['productoNombre'] ?? $raw['nombre'] ?? ''));

            // 1. Buscar producto en catálogo (precio en products_prices, categoría en category_ids)
            $prodRow = null;
            $sqlSelectProd = "SELECT p._id, p.product, p.category_ids,
                COALESCE((SELECT pp.price FROM products_prices pp WHERE pp.id_product = p._id ORDER BY pp._id ASC LIMIT 1), 0) as price
                FROM products p";

            if ($cod) {
                $rows = $db->goQuery("{$sqlSelectProd} WHERE p._id = ? AND p.eliminado = 0 LIMIT 1", [$cod]);
                if (!empty($rows) && !isset($rows['status'])) {
                    $prodRow = $rows[0];
                }
            }
            if (!$prodRow && $nombreBuscado !== '') {
                $rows = $db->goQuery("{$sqlSelectProd} WHERE p.product ILIKE ? AND p.eliminado = 0 LIMIT 1", ["%{$nombreBuscado}%"]);
                if (!empty($rows) && !isset($rows['status'])) {
                    $prodRow = $rows[0];
                }
            }

            if (!$prodRow) {
                // Producto no encontrado en catálogo
                return [
                    'error'   => 'producto_invalido',
                    'message' => "El producto '{$nombreBuscado}' (código: " . ($cod ?: 'N/A') . ") no existe en el catálogo.",
                ];
            }

            $prodId = (int) $prodRow['_id'];
            $prodNombre = $prodRow['product'];
            $catId = 1;
            if (!empty($prodRow['category_ids'])) {
                $parts = explode(',', (string) $prodRow['category_ids']);
                $firstCat = (int) trim($parts[0]);
                if ($firstCat > 0) $catId = $firstCat;
            }

            // 2. Nombre de categoría
            $catNombre = 'General';
            $catRows = $db->goQuery('SELECT nombre FROM categories WHERE _id = ? LIMIT 1', [$catId]);
            if (!empty($catRows) && !isset($catRows['status'])) {
                $catNombre = $catRows[0]['nombre'];
            }

            // 3. Resolver Talla
            $tallaTexto = trim((string) ($raw['talla'] ?? 'M'));
            $sizeId = null;
            if ($tallaTexto !== '') {
                $sRows = $db->goQuery('SELECT _id, nombre FROM sizes WHERE eliminado = 0 AND (LOWER(TRIM(nombre)) = LOWER(TRIM(?)) OR _id = ?) LIMIT 1', [
                    $tallaTexto,
                    is_numeric($tallaTexto) ? (int) $tallaTexto : 0
                ]);
                if (!empty($sRows) && !isset($sRows['status'])) {
                    $sizeId = (int) $sRows[0]['_id'];
                    $tallaTexto = $sRows[0]['nombre'];
                }
            }

            // 4. Resolver Corte
            $corteFinal = $normalizarCorte($raw['corte'] ?? null);

            // 5. Resolver Tela
            $telaTexto = trim((string) ($raw['tela'] ?? ''));
            $telaId = null;
            if ($telaTexto !== '') {
                $tRows = $db->goQuery('SELECT _id, tela FROM catalogo_telas WHERE eliminado = 0 AND (LOWER(TRIM(tela)) = LOWER(TRIM(?)) OR _id = ?) LIMIT 1', [
                    $telaTexto,
                    is_numeric($telaTexto) ? (int) $telaTexto : 0
                ]);
                if (!empty($tRows) && !isset($tRows['status'])) {
                    $telaId = (int) $tRows[0]['_id'];
                    $telaTexto = $tRows[0]['tela'];
                }
            }

            // 6. Precio Base y Recargo XL
            $precioBase = isset($raw['precio']) && (float) $raw['precio'] > 0
                ? (float) $raw['precio']
                : (float) $prodRow['price'];

            $recargoXL = $calcularRecargoXL($tallaTexto);
            $precioFinalUnitario = round($precioBase + $recargoXL, 2);
            $subtotal = round($precioFinalUnitario * $cant, 2);

            $granTotal += $subtotal;
            $totalPrendas += $cant;

            $itemsProcesados[] = [
                'cod'             => $prodId,
                'productoNombre'  => $prodNombre,
                'idCategory'      => $catId,
                'categoryName'    => $catNombre,
                'cantidad'        => $cant,
                'talla'           => $tallaTexto,
                'sizeId'          => $sizeId,
                'corte'           => $corteFinal,
                'tela'            => $telaTexto,
                'telaId'          => $telaId,
                'precio_base'     => round($precioBase, 2),
                'recargo_xl'      => round($recargoXL, 2),
                'precio_unitario' => $precioFinalUnitario,
                'subtotal'        => $subtotal,
            ];
        }

        return [
            'success'       => true,
            'items'         => $itemsProcesados,
            'total'         => round($granTotal, 2),
            'total_prendas' => $totalPrendas,
        ];
    };

    // =========================================================================
    // 1. POST /internal/presupuestos/{id_empresa}/calcular
    // =========================================================================
    $app->post('/internal/presupuestos/{id_empresa}/calcular', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa, $procesarItems) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = $makeRespondJson($response);

        $idEmpresa = filter_var($args['id_empresa'] ?? ($request->getHeader('Authorization')[0] ?? ''), FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_empresa inválido.'], 400);
        }

        if (!$validarEmpresa($idEmpresa)) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $body = $request->getBody()->getContents();
        $payload = json_decode($body, true);
        if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Se requiere el array "items" con los productos a cotizar.'], 400);
        }

        try {
            $db = new LocalDB();
            $res = $procesarItems($db, $payload['items']);
            $db->disconnect();

            if (isset($res['error'])) {
                return $respondJson(['success' => false, 'error' => $res['error'], 'message' => $res['message']], 422);
            }

            return $respondJson([
                'success'       => true,
                'id_empresa'    => $idEmpresa,
                'total'         => $res['total'],
                'total_prendas' => $res['total_prendas'],
                'items'         => $res['items'],
            ], 200);

        } catch (\Throwable $e) {
            error_log('[ai_quotes][calcular] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al calcular presupuesto: ' . $e->getMessage()], 500);
        }
    });

    // =========================================================================
    // 2. POST /internal/presupuestos/{id_empresa}/crear
    // =========================================================================
    $app->post('/internal/presupuestos/{id_empresa}/crear', function (Request $request, Response $response, array $args) use ($makeRespondJson, $validarEmpresa, $procesarItems) {
        if ($errorResponse = validarTokenInterno($request, $response)) {
            return $errorResponse;
        }
        $respondJson = $makeRespondJson($response);

        $idEmpresa = filter_var($args['id_empresa'] ?? ($request->getHeader('Authorization')[0] ?? ''), FILTER_VALIDATE_INT);
        if ($idEmpresa === false || $idEmpresa <= 0) {
            return $respondJson(['error' => 'bad_request', 'message' => 'id_empresa inválido.'], 400);
        }

        if (!$validarEmpresa($idEmpresa)) {
            return $respondJson(['error' => 'not_found', 'message' => "Empresa {$idEmpresa} no existe o está inactiva."], 404);
        }

        $body = $request->getBody()->getContents();
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return $respondJson(['error' => 'bad_request', 'message' => 'Cuerpo JSON inválido.'], 400);
        }

        $clienteData = is_array($payload['cliente'] ?? null) ? $payload['cliente'] : [];
        $nombreCliente = trim((string) ($clienteData['nombre'] ?? $payload['cliente_nombre'] ?? ''));
        if ($nombreCliente === '') {
            return $respondJson(['error' => 'bad_request', 'message' => 'Se requiere el nombre del cliente en cliente.nombre o cliente_nombre.'], 400);
        }

        $apellidoCliente = trim((string) ($clienteData['apellido'] ?? $payload['cliente_apellido'] ?? ''));
        $cedulaCliente = trim((string) ($clienteData['cedula'] ?? $payload['cliente_cedula'] ?? ''));
        $telefonoCliente = trim((string) ($clienteData['telefono'] ?? $payload['cliente_telefono'] ?? ''));
        $emailCliente = trim((string) ($clienteData['email'] ?? $payload['cliente_email'] ?? ''));
        $direccionCliente = trim((string) ($clienteData['direccion'] ?? $payload['cliente_direccion'] ?? ''));

        if ($apellidoCliente === '' && strpos($nombreCliente, ' ') !== false) {
            $nameParts = explode(' ', $nombreCliente, 2);
            $nombreCliente = $nameParts[0];
            $apellidoCliente = $nameParts[1];
        }

        $itemsRaw = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if (empty($itemsRaw)) {
            return $respondJson(['error' => 'bad_request', 'message' => 'El presupuesto debe incluir al menos un producto en "items".'], 400);
        }

        $obs = trim((string) ($payload['observaciones'] ?? $payload['obs'] ?? ''));
        $origen = trim((string) ($payload['origen'] ?? 'ia_assistant'));

        try {
            $db = new LocalDB();

            // 1. Validar y procesar ítems contra catálogo
            $res = $procesarItems($db, $itemsRaw);
            if (isset($res['error'])) {
                $db->disconnect();
                return $respondJson(['success' => false, 'error' => $res['error'], 'message' => $res['message']], 422);
            }

            $itemsValidados = $res['items'];
            $totalMonto = $res['total'];
            $totalPrendas = $res['total_prendas'];

            // 2. Iniciar transacción atómica ACID
            $db->beginTransaction();

            $nombreCompleto = trim("{$nombreCliente} {$apellidoCliente}");

            // 3. Resolución / Upsert de Cliente
            $customerId = null;
            $conditions = [];
            $params = [];

            if ($cedulaCliente !== '') {
                $conditions[] = 'cedula = ?';
                $params[] = $cedulaCliente;
            }

            if ($telefonoCliente !== '') {
                $digits = preg_replace('/\D/', '', $telefonoCliente);
                if (strlen($digits) >= 7) {
                    $last10 = substr($digits, -10);
                    $conditions[] = "REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?";
                    $params[] = "%{$last10}";
                } else {
                    $conditions[] = 'phone = ?';
                    $params[] = $telefonoCliente;
                }
            }

            if (!empty($conditions)) {
                $checkSql = 'SELECT _id, first_name, last_name, email, address, phone FROM customers WHERE (' . implode(' OR ', $conditions) . ') LIMIT 1';
                $cRows = $db->goQuery($checkSql, $params);
                if (!empty($cRows) && !isset($cRows['status'])) {
                    $customerId = (int) $cRows[0]['_id'];
                    // Actualización segura de datos faltantes
                    $updFields = [];
                    $updParams = [];
                    if ($apellidoCliente !== '' && empty($cRows[0]['last_name'])) {
                        $updFields[] = 'last_name = ?';
                        $updParams[] = $apellidoCliente;
                    }
                    if ($direccionCliente !== '' && empty($cRows[0]['address'])) {
                        $updFields[] = 'address = ?';
                        $updParams[] = $direccionCliente;
                    }
                    if ($emailCliente !== '' && empty($cRows[0]['email'])) {
                        $updFields[] = 'email = ?';
                        $updParams[] = $emailCliente;
                    }
                    if (!empty($updFields)) {
                        $updParams[] = $customerId;
                        $db->goQuery('UPDATE customers SET ' . implode(', ', $updFields) . ' WHERE _id = ?', $updParams);
                    }
                }
            }

            if (!$customerId) {
                // Generar email de respaldo si no vino provisto
                $emailFinal = $emailCliente ?: (strtolower(substr($nombreCliente, 0, 1)) . substr(str_shuffle('abcdef0123456789'), 0, 7) . '@email.com');
                $insCustomerSql = 'INSERT INTO customers (first_name, last_name, cedula, phone, email, address) VALUES (?, ?, ?, ?, ?, ?)';
                $insRes = $db->goQuery($insCustomerSql, [
                    $nombreCliente,
                    $apellidoCliente,
                    $cedulaCliente ?: null,
                    $telefonoCliente,
                    $emailFinal,
                    $direccionCliente ?: null
                ]);
                $customerId = isset($insRes['insert_id']) ? (int) $insRes['insert_id'] : null;
            }

            // 4. Asignación de Vendedor
            $vendedorId = null;
            $vendedorNombre = 'Sin asignar';

            // A. Si se especificó explícitamente un vendedor
            if (!empty($payload['responsable'])) {
                $vendedorId = (int) $payload['responsable'];
            }

            // B. Buscar si el cliente tiene un vendedor previo recurrente
            if (!$vendedorId && $customerId) {
                $prevPres = $db->goQuery('SELECT responsable FROM presupuestos WHERE id_wp = ? AND responsable IS NOT NULL ORDER BY _id DESC LIMIT 1', [$customerId]);
                if (!empty($prevPres) && !isset($prevPres['status'])) {
                    $vendedorId = (int) $prevPres[0]['responsable'];
                } else {
                    $prevOrd = $db->goQuery('SELECT responsable FROM ordenes WHERE id_wp = ? AND responsable IS NOT NULL ORDER BY _id DESC LIMIT 1', [$customerId]);
                    if (!empty($prevOrd) && !isset($prevOrd['status'])) {
                        $vendedorId = (int) $prevOrd[0]['responsable'];
                    }
                }
            }

            // C. Si no hay vendedor previo, asignar equitativamente de Comercialización
            if (!$vendedorId) {
                $vndRows = $db->goQuery("SELECT u.id_usuario, u.nombre
                    FROM api_empresas.empresas_usuarios u
                    JOIN api_empresas.empresas_usuarios_empresas eue ON eue.id_usuario = u.id_usuario AND eue.id_empresa = {$idEmpresa}
                    WHERE u.activo = 1 AND (u.departamento = 'Comercialización' OR u.departamentos_asignados::text ILIKE '%Comercialización%')
                    ORDER BY RANDOM() LIMIT 1");
                if (!empty($vndRows) && !isset($vndRows['status'])) {
                    $vendedorId = (int) $vndRows[0]['id_usuario'];
                    $vendedorNombre = $vndRows[0]['nombre'];
                }
            } else {
                // Obtener nombre del vendedor
                $vndName = $db->goQuery("SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = ? LIMIT 1", [$vendedorId]);
                if (!empty($vndName) && !isset($vndName['status'])) {
                    $vendedorNombre = $vndName[0]['nombre'];
                }
            }

            // 5. Insertar Cabecera de Presupuesto
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $obsFinal = $obs !== '' ? "[Origen: {$origen}] {$obs}" : "[Origen: {$origen}]";

            $insPresSql = "INSERT INTO presupuestos 
                (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, cliente_direccion, fecha_inicio, fecha_entrega, fecha_creacion, status)
                VALUES (?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'En espera')";

            $presRes = $db->goQuery($insPresSql, [
                $vendedorId,
                $now,
                $customerId,
                $cedulaCliente,
                $obsFinal,
                $totalMonto,
                $nombreCompleto,
                $direccionCliente,
                $today,
                $today,
                $today,
            ]);

            $presupuestoId = isset($presRes['insert_id']) ? (int) $presRes['insert_id'] : null;
            if (!$presupuestoId) {
                $db->rollBack();
                $db->disconnect();
                return $respondJson(['error' => 'insert_failed', 'message' => 'No se pudo obtener el ID del presupuesto generado.'], 500);
            }

            // 6. Insertar Líneas de Detalle en presupuestos_productos
            $insProdSql = "INSERT INTO presupuestos_productos
                (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela, id_size, id_tela)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            foreach ($itemsValidados as $it) {
                $db->goQuery($insProdSql, [
                    $now,
                    $it['precio_unitario'],
                    (string) $it['precio_unitario'],
                    $it['productoNombre'],
                    $presupuestoId,
                    $it['cod'],
                    $it['cantidad'],
                    $it['idCategory'],
                    $it['categoryName'],
                    $it['talla'],
                    $it['corte'],
                    $it['tela'],
                    $it['sizeId'],
                    $it['telaId'],
                ]);
            }

            // 7. Commit atómico
            $db->commit();
            $db->disconnect();

            return $respondJson([
                'success'        => true,
                'id_empresa'     => $idEmpresa,
                'id_presupuesto' => $presupuestoId,
                'cliente'        => [
                    'id_customer' => $customerId,
                    'nombre'      => $nombreCompleto,
                    'telefono'    => $telefonoCliente,
                    'cedula'      => $cedulaCliente,
                ],
                'responsable'    => [
                    'id_usuario'  => $vendedorId,
                    'nombre'      => $vendedorNombre,
                ],
                'total'          => $totalMonto,
                'total_prendas'  => $totalPrendas,
                'status'         => 'En espera',
                'items'          => $itemsValidados,
                'moment'         => $now,
            ], 201);

        } catch (\Throwable $e) {
            if (isset($db)) {
                try { $db->rollBack(); } catch (\Throwable $_) {}
                $db->disconnect();
            }
            error_log('[ai_quotes][crear] Error: ' . $e->getMessage());
            return $respondJson(['error' => 'internal_error', 'message' => 'Error al crear presupuesto: ' . $e->getMessage()], 500);
        }
    });

};
