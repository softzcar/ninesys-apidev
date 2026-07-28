<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // =================================================================
    // HELPER: CALCULAR COSTO HORA EMPLEADOS (SALARIADOS)
    // =================================================================
    $getSalarios = function($db, $dbEmpresas, $id_empresa) {
        if (DB_DRIVER === 'pgsql') {
            $horarioQuery = "SELECT horario_laboral FROM api_empresas.empresas WHERE id_empresa = ?";
        } else {
            $horarioQuery = "SELECT horario_laboral FROM empresas WHERE id_empresa = ?";
        }
        $horarioResult = $dbEmpresas->goQuery($horarioQuery, [$id_empresa]);
        
        $horario_laboral = (!empty($horarioResult) && isset($horarioResult[0]['horario_laboral'])) ? $horarioResult[0]['horario_laboral'] : null;
        $horarioObj = $horario_laboral ? json_decode($horario_laboral, true) : null;
        
        $horasDia = 0;
        $diasSemana = 0;
        if (is_array($horarioObj)) {
            $horasDia = (float)($horarioObj['horaFinManana'] ?? 0) - (float)($horarioObj['horaInicioManana'] ?? 0);
            $horasDia += (float)($horarioObj['horaFinTarde'] ?? 0) - (float)($horarioObj['horaInicioTarde'] ?? 0);
            $diasSemana = is_array($horarioObj['diasLaborales'] ?? null) ? count($horarioObj['diasLaborales']) : 0;
        }
        $horasSemana = max(0, $horasDia) * max(0, $diasSemana);

        $sqlSalarios = "SELECT id_usuario, nombre, salario_monto, salario_periodo, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_empresa = $id_empresa AND activo = 1";
        $salariosData = $dbEmpresas->goQuery($sqlSalarios);
        
        $costoHoraMap = [];
        if (is_array($salariosData) && !isset($salariosData['status'])) {
            foreach ($salariosData as $emp) {
                $monto = (float)($emp['salario_monto'] ?? 0);
                $periodo = strtolower((string)($emp['salario_periodo'] ?? ''));
                $factorSemanas = 0;
                $salarioPeriodo = 0;

                if ($periodo === 'semanal') {
                    $salarioPeriodo = $monto / 4;
                    $factorSemanas = 1;
                } else if ($periodo === 'quincenal') {
                    $salarioPeriodo = $monto / 2;
                    $factorSemanas = 2;
                } else if ($periodo === 'mensual') {
                    $salarioPeriodo = $monto;
                    $factorSemanas = 4.33;
                } else if ($periodo === 'bimensual') {
                    $salarioPeriodo = $monto * 2;
                    $factorSemanas = 8.66;
                } else if ($periodo === 'trimestral') {
                    $salarioPeriodo = $monto * 3;
                    $factorSemanas = 13;
                } else if ($periodo === 'semestral') {
                    $salarioPeriodo = $monto * 6;
                    $factorSemanas = 26;
                } else if ($periodo === 'anual') {
                    $salarioPeriodo = $monto * 12;
                    $factorSemanas = 52;
                }

                $horasPeriodo = $horasSemana > 0 && $factorSemanas > 0 ? ($horasSemana * $factorSemanas) : 0;
                $costoHoraMap[$emp['id_usuario']] = $horasPeriodo > 0 ? round($salarioPeriodo / $horasPeriodo, 4) : 0;
            }
        }
        return $costoHoraMap;
    };

    // =================================================================
    // HELPER: OBTENER HORARIO LABORAL (para calcularHorasLaboradasReales(),
    // definida globalmente en reports.php -- ambos archivos se cargan en
    // cada request vía app/routes.php, la función ya está disponible aquí)
    // =================================================================
    $getHorarioLaboral = function($dbEmpresas, $id_empresa) {
        if (DB_DRIVER === 'pgsql') {
            $horarioQuery = "SELECT horario_laboral FROM api_empresas.empresas WHERE id_empresa = ?";
        } else {
            $horarioQuery = "SELECT horario_laboral FROM empresas WHERE id_empresa = ?";
        }
        $horarioResult = $dbEmpresas->goQuery($horarioQuery, [$id_empresa]);
        $horario_laboral = (!empty($horarioResult) && isset($horarioResult[0]['horario_laboral'])) ? $horarioResult[0]['horario_laboral'] : null;
        return $horario_laboral ? json_decode($horario_laboral, true) : null;
    };

    // =================================================================
    // ENDPOINT: LISTADO CONSOLIDADO POR PRODUCTO (REAL VS ESTIMADO)
    // =================================================================
    $app->get('/reportes/costos-productos', function (Request $request, Response $response) use ($getSalarios, $getHorarioLaboral) {
        $queryParams = $request->getQueryParams();
        $fecha_inicio = !empty($queryParams['fecha_inicio']) ? $queryParams['fecha_inicio'] : null;
        $fecha_fin = !empty($queryParams['fecha_fin']) ? $queryParams['fecha_fin'] : null;
        $id_empresa = ID_EMPRESA;

        try {
            $db = new LocalDB();
            if (DB_DRIVER === 'pgsql') {
                $dbEmpresas = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
            } else {
                $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
                $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);
            }

            // 1. Obtener todos los productos físicos (o con sku)
            // precio_venta viene de products_prices (primer registro por _id, mismo criterio
            // ya usado en WooMe::getAllProducts()/orders.php). products.price ya no se referencia
            // en absoluto (columna legacy sin escritura activa, en vías de eliminarse del esquema).
            $productsRaw = $db->goQuery("SELECT p._id, p.product as nombre, p.sku, COALESCE((SELECT price FROM products_prices WHERE id_product = p._id ORDER BY _id ASC LIMIT 1), 0) as precio_venta, p.category_ids FROM products p WHERE p.sku IS NOT NULL AND p.sku <> ''");
            if (isset($productsRaw['status']) && $productsRaw['status'] === 'error') {
                throw new Exception('Error al consultar productos: ' . $productsRaw['message']);
            }

            // 2. Obtener categorías de la base de datos
            $categoriesRaw = $db->goQuery("SELECT _id, nombre FROM categories");
            $categoriesMap = [];
            if (is_array($categoriesRaw)) {
                foreach ($categoriesRaw as $c) {
                    $categoriesMap[$c['_id']] = $c['nombre'];
                }
            }

            // 3. Obtener costos unitarios promedio actuales de inventario
            // El costo se normaliza a "por Mt" dividiendo por el rendimiento (Mt por Kg) SOLO cuando
            // el insumo se compra por Kg -- la Ficha Técnica (product_insumos_asignados) especifica
            // la tela en metros, mismo criterio de conversión Kg->Mt ya usado en reports.php/manufacturing.php.
            $invCostRaw = $db->goQuery("SELECT id_catalogo, AVG((costo / NULLIF(cantidad_inicial, 0)) / CASE WHEN unidad = 'Kg' AND rendimiento IS NOT NULL THEN rendimiento ELSE 1 END) AS costo_unitario FROM inventario GROUP BY id_catalogo");
            $invCostMap = [];
            if (is_array($invCostRaw)) {
                foreach ($invCostRaw as $ic) {
                    $invCostMap[$ic['id_catalogo']] = (float)$ic['costo_unitario'];
                }
            }

            // 4. Insumos Estimados agrupados por producto y talla para promediar
            $piaRaw = $db->goQuery("SELECT id_product, id_talla, id_catalogo_insumos_productos, cantidad FROM product_insumos_asignados");
            $piaEstimados = [];
            if (is_array($piaRaw)) {
                foreach ($piaRaw as $pia) {
                    $prodId = $pia['id_product'];
                    $tallaId = $pia['id_talla'];
                    $catId = $pia['id_catalogo_insumos_productos'];
                    $cant = (float)$pia['cantidad'];
                    $costoUnit = $invCostMap[$catId] ?? 0.0;
                    
                    if (!isset($piaEstimados[$prodId][$tallaId])) {
                        $piaEstimados[$prodId][$tallaId] = 0.0;
                    }
                    $piaEstimados[$prodId][$tallaId] += ($cant * $costoUnit);
                }
            }

            // 5. Mano de Obra Estimada (Comisiones teóricas)
            $comisionesRaw = $db->goQuery("SELECT id_product, SUM(comision) AS total_comision FROM products_comisiones GROUP BY id_product");
            $teoricoLaborMap = [];
            if (is_array($comisionesRaw)) {
                foreach ($comisionesRaw as $c) {
                    $teoricoLaborMap[$c['id_product']] = (float)$c['total_comision'];
                }
            }

            // 6. Tiempo Estimado (Tiempos de producción teóricos)
            $tiemposRaw = $db->goQuery("SELECT id_product, SUM(tiempo) AS total_tiempo FROM products_tiempos_de_produccion GROUP BY id_product");
            $teoricoTiempoMap = [];
            if (is_array($tiemposRaw)) {
                foreach ($tiemposRaw as $t) {
                    $teoricoTiempoMap[$t['id_product']] = (float)$t['total_tiempo'];
                }
            }

            // --- DATOS REALES HISTÓRICOS DESDE EL TALLER ---
            $momentDateExpr = DB_DRIVER === 'pgsql' ? 'o.moment::date' : 'DATE(o.moment)';
            $fechaCierreExpr = DB_DRIVER === 'pgsql'
                ? "COALESCE(ldea.fecha_terminado, NULLIF(o.fecha_entrega, '')::date, o.fecha_creacion, o.moment::date)"
                : "COALESCE(ldea.fecha_terminado, STR_TO_DATE(NULLIF(o.fecha_entrega, ''), '%Y-%m-%d'), o.fecha_creacion, DATE(o.moment))";

            $params = [];
            $dateCond = "";
            if ($fecha_inicio && $fecha_fin) {
                $dateCond = " AND $momentDateExpr BETWEEN :fecha_inicio AND :fecha_fin ";
                $params['fecha_inicio'] = $fecha_inicio;
                $params['fecha_fin'] = $fecha_fin;
            }

            // 7. Cantidad total de piezas reales producidas por producto
            $cantRealRaw = $db->goQuery("
                SELECT op.id_woo as id_producto, SUM(op.cantidad) AS total_cantidad
                FROM ordenes_productos op
                JOIN ordenes o ON op.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY op.id_woo
            ", $params);
            $realCantidadMap = [];
            if (is_array($cantRealRaw)) {
                foreach ($cantRealRaw as $r) {
                    $realCantidadMap[$r['id_producto']] = (float)$r['total_cantidad'];
                }
            }

            // 7b. Cantidad real producida por producto Y talla, para ponderar el costo estimado
            //     de insumos por la mezcla real de tallas fabricadas (en vez de un promedio simple
            //     entre las tallas configuradas en la Ficha Técnica, que puede incluir tallas nunca
            //     producidas en el rango y distorsionar el estimado).
            $cantRealPorTallaRaw = $db->goQuery("
                SELECT op.id_woo as id_producto, op.id_size as id_talla, SUM(op.cantidad) AS total_cantidad
                FROM ordenes_productos op
                JOIN ordenes o ON op.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY op.id_woo, op.id_size
            ", $params);
            $realCantidadPorTallaMap = [];
            if (is_array($cantRealPorTallaRaw)) {
                foreach ($cantRealPorTallaRaw as $r) {
                    $realCantidadPorTallaMap[$r['id_producto']][$r['id_talla']] = (float)$r['total_cantidad'];
                }
            }

            // Costo teórico de insumos ponderado por la mezcla real de tallas producidas en el
            // rango. Si el producto no tiene ninguna unidad real de las tallas configuradas
            // (ej. producto nunca fabricado en el rango), se usa un promedio simple entre las
            // tallas de la ficha técnica como respaldo.
            $teoricoInsumosMap = [];
            foreach ($piaEstimados as $prodId => $tallas) {
                $pesoTotal = 0.0;
                $sumaPonderada = 0.0;
                foreach ($tallas as $tallaId => $costoTalla) {
                    $peso = $realCantidadPorTallaMap[$prodId][$tallaId] ?? 0.0;
                    $sumaPonderada += $costoTalla * $peso;
                    $pesoTotal += $peso;
                }
                if ($pesoTotal > 0) {
                    $teoricoInsumosMap[$prodId] = round($sumaPonderada / $pesoTotal, 2);
                } else {
                    $total = array_sum($tallas);
                    $cnt = count($tallas);
                    $teoricoInsumosMap[$prodId] = $cnt > 0 ? round($total / $cnt, 2) : 0.0;
                }
            }

            // 8. Insumos consumidos reales (inventario_movimientos)
            $insumosRealRaw = $db->goQuery("
                SELECT im.id_producto, SUM(ABS(im.valor_inicial - im.valor_final) * COALESCE((inv.costo / NULLIF(inv.cantidad_inicial, 0)), 0)) AS total_costo
                FROM inventario_movimientos im
                JOIN inventario inv ON im.id_insumo = inv._id
                JOIN ordenes o ON im.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY im.id_producto
            ", $params);
            $realInsumosMap = [];
            if (is_array($insumosRealRaw)) {
                foreach ($insumosRealRaw as $r) {
                    $realInsumosMap[$r['id_producto']] = (float)$r['total_costo'];
                }
            }

            // 9. Comisiones reales pagadas (pagos) — se reparten entre los productos REALES de la
            //    orden (ordenes_productos), ponderando por comisión estimada x cantidad. NO se usa
            //    lotes_detalles: su enlace pago->producto está roto (id_lotes_detalles apunta a lotes
            //    de otras órdenes en ~99% de los casos, inflando la mano de obra de productos sueltos).
            //    Se excluyen los pagos de comisión de VENDEDOR (detalle='Comercialización'/'Abono a
            //    orden', siempre pagados al responsable de la orden, confirmado en 5969/5971 y 41/41
            //    registros) -- no son mano de obra de producción, inflaban labor_real ~2x.
            $pagosRealRaw = $db->goQuery("
                SELECT id_woo AS id_producto, SUM(share) AS total_pagos
                FROM (
                    SELECT op.id_woo,
                           p.monto_pago * (COALESCE(peso.w, 0) * op.cantidad + 0.0001)
                             / SUM(COALESCE(peso.w, 0) * op.cantidad + 0.0001) OVER (PARTITION BY p._id) AS share
                    FROM pagos p
                    JOIN ordenes o ON p.id_orden = o._id
                    JOIN ordenes_productos op ON op.id_orden = p.id_orden
                    LEFT JOIN (SELECT id_product, SUM(comision) w FROM products_comisiones GROUP BY id_product) peso
                        ON peso.id_product = op.id_woo
                    WHERE o.status IN ('terminada', 'entregada')
                      AND p.detalle NOT IN ('Comercialización', 'Abono a orden') $dateCond
                ) t
                GROUP BY id_woo
            ", $params);
            $realPagosMap = [];
            if (is_array($pagosRealRaw)) {
                foreach ($pagosRealRaw as $r) {
                    $realPagosMap[$r['id_producto']] = (float)$r['total_pagos'];
                }
            }

            // 10. Tiempos reales laborados (segundos) — atribuidos a los productos REALES de la orden
            //     (ordenes_productos) que pasan por ese departamento, ponderando por tiempo teórico
            //     (products_tiempos_de_produccion) x cantidad. Sin lotes_detalles. Las horas se calculan
            //     con calcularHorasLaboradasReales() (filtrando por horario laboral real de la empresa),
            //     no con la resta cruda de fecha_terminado - fecha_inicio -- 66 de 899 tareas del último
            //     mes tienen brechas de más de 8h (tareas dejadas abiertas varios días sin cerrarse de
            //     inmediato), inflando el tiempo hasta ~150h para fabricar una sola unidad en un caso real.
            //     Los registros LDEA abiertos (sin fecha_terminado) se cierran con la fecha estimada de
            //     la orden (fecha_entrega), no con NOW(), para no inflar el tiempo de tareas viejas.
            $horarioObj = $getHorarioLaboral($dbEmpresas, $id_empresa);
            $ldeaRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre,
                       op.id_woo AS id_producto, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL $dateCond
            ", $params);

            $realTiempoMap = [];
            if (is_array($ldeaRaw)) {
                $porLdea = [];
                foreach ($ldeaRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_producto' => $row['id_producto'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $segundosReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj) * 3600;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        $share = $pesoTotal > 0 ? ($segundosReales * $linea['peso'] / $pesoTotal) : 0.0;
                        $prodId = $linea['id_producto'];
                        if (!isset($realTiempoMap[$prodId])) $realTiempoMap[$prodId] = 0.0;
                        $realTiempoMap[$prodId] += $share;
                    }
                }
            }

            // 11. Salarios proporcionales por horas de empleados fijos — misma atribución que el tiempo
            //     (ordenes_productos + departamento, ponderado por tiempo teórico x cantidad; horas
            //     reales vía calcularHorasLaboradasReales(), mismo motivo que el punto 10). Sin lotes_detalles.
            $costoHoraMap = $getSalarios($db, $dbEmpresas, $id_empresa);
            $ldeaSalarioRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre, ldea.id_empleado,
                       op.id_woo AS id_producto, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = ldea.id_empleado
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL
                  AND eu.salario_tipo IN ('Salario', 'Salario más Comisión') $dateCond
            ", $params);

            $realSalarioFijoMap = [];
            if (is_array($ldeaSalarioRaw)) {
                $porLdea = [];
                foreach ($ldeaSalarioRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['id_empleado'] = $row['id_empleado'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_producto' => $row['id_producto'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $horasReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj);
                    $costoHora = $costoHoraMap[$data['id_empleado']] ?? 0.0;
                    $costoTotalTarea = $horasReales * $costoHora;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        $share = $pesoTotal > 0 ? ($costoTotalTarea * $linea['peso'] / $pesoTotal) : 0.0;
                        $prodId = $linea['id_producto'];
                        if (!isset($realSalarioFijoMap[$prodId])) $realSalarioFijoMap[$prodId] = 0.0;
                        $realSalarioFijoMap[$prodId] += $share;
                    }
                }
            }

            // 12. Consolidar productos con métricas de comparación
            $resultList = [];
            foreach ($productsRaw as $p) {
                $id = (int)$p['_id'];
                
                // Mapear categorías
                $catNames = [];
                if (!empty($p['category_ids'])) {
                    $cIds = explode(',', $p['category_ids']);
                    foreach ($cIds as $cid) {
                        $cid = (int)trim($cid);
                        if (isset($categoriesMap[$cid])) {
                            $catNames[] = $categoriesMap[$cid];
                        }
                    }
                }
                
                $unidadesReales = $realCantidadMap[$id] ?? 0.0;
                if ($unidadesReales <= 0) {
                    continue;
                }

                // Teóricos
                $estInsumos = $teoricoInsumosMap[$id] ?? 0.0;
                $estLabor = $teoricoLaborMap[$id] ?? 0.0;
                $estTotal = $estInsumos + $estLabor;
                $estTiempo = $teoricoTiempoMap[$id] ?? 0.0;

                // Reales
                $realInsumos = 0.0;
                $realLabor = 0.0;
                $realTiempo = 0.0;

                if ($unidadesReales > 0) {
                    $realInsumos = ($realInsumosMap[$id] ?? 0.0) / $unidadesReales;
                    $realLabor = (($realPagosMap[$id] ?? 0.0) + ($realSalarioFijoMap[$id] ?? 0.0)) / $unidadesReales;
                    $realTiempo = ($realTiempoMap[$id] ?? 0.0) / $unidadesReales;
                }
                
                $realTotal = $realInsumos + $realLabor;

                // Desviaciones
                $devInsumos = $estInsumos > 0 ? (($realInsumos - $estInsumos) / $estInsumos) * 100 : 0.0;
                $devLabor = $estLabor > 0 ? (($realLabor - $estLabor) / $estLabor) * 100 : 0.0;
                $devTiempo = $estTiempo > 0 ? (($realTiempo - $estTiempo) / $estTiempo) * 100 : 0.0;
                $devTotal = $estTotal > 0 ? (($realTotal - $estTotal) / $estTotal) * 100 : 0.0;

                $precioVenta = (float)$p['precio_venta'];
                $margenEst = $precioVenta > 0 ? (($precioVenta - $estTotal) / $precioVenta) * 100 : 0.0;
                $margenReal = $precioVenta > 0 ? (($precioVenta - $realTotal) / $precioVenta) * 100 : 0.0;

                $resultList[] = [
                    'id_producto' => $id,
                    'nombre' => $p['nombre'],
                    'sku' => $p['sku'],
                    'categorias' => empty($catNames) ? 'Sin categoría' : implode(', ', $catNames),
                    'category_ids' => $p['category_ids'],
                    'precio_venta' => $precioVenta,
                    'unidades_fabricadas' => $unidadesReales,
                    // Tiempos
                    'tiempo_estimado' => round($estTiempo, 0), // en segundos
                    'tiempo_real' => round($realTiempo, 0),
                    'desviacion_tiempo_pct' => round($devTiempo, 1),
                    // Insumos
                    'insumos_estimado' => round($estInsumos, 2),
                    'insumos_real' => round($realInsumos, 2),
                    'desviacion_insumos_pct' => round($devInsumos, 1),
                    // Mano de Obra
                    'labor_estimado' => round($estLabor, 2),
                    'labor_real' => round($realLabor, 2),
                    'desviacion_labor_pct' => round($devLabor, 1),
                    // Costo Total
                    'costo_total_estimado' => round($estTotal, 2),
                    'costo_total_real' => round($realTotal, 2),
                    'desviacion_total_pct' => round($devTotal, 1),
                    // Márgenes
                    'margen_estimado_pct' => round($margenEst, 1),
                    'margen_real_pct' => round($margenReal, 1)
                ];
            }

            $dbEmpresas->disconnect();
            $db->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $resultList
            ], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            if (isset($db)) $db->disconnect();
            if (isset($dbEmpresas)) $dbEmpresas->disconnect();
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // ENDPOINT: CONSOLIDADO POR CATEGORÍA
    // =================================================================
    $app->get('/reportes/costos-productos/categorias', function (Request $request, Response $response) use ($getSalarios, $getHorarioLaboral) {
        $queryParams = $request->getQueryParams();
        $fecha_inicio = !empty($queryParams['fecha_inicio']) ? $queryParams['fecha_inicio'] : null;
        $fecha_fin = !empty($queryParams['fecha_fin']) ? $queryParams['fecha_fin'] : null;
        $id_empresa = ID_EMPRESA;

        try {
            $db = new LocalDB();
            if (DB_DRIVER === 'pgsql') {
                $dbEmpresas = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
            } else {
                $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
                $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);
            }

            // Reusar toda la lógica agregada a nivel de producto
            // precio_venta viene de products_prices (primer registro por _id, mismo criterio
            // ya usado en WooMe::getAllProducts()/orders.php). products.price ya no se referencia
            // en absoluto (columna legacy sin escritura activa, en vías de eliminarse del esquema).
            $productsRaw = $db->goQuery("SELECT p._id, p.product as nombre, p.sku, COALESCE((SELECT price FROM products_prices WHERE id_product = p._id ORDER BY _id ASC LIMIT 1), 0) as precio_venta, p.category_ids FROM products p WHERE p.sku IS NOT NULL AND p.sku <> ''");
            $categoriesRaw = $db->goQuery("SELECT _id, nombre FROM categories");
            
            $categoriesMap = [];
            $categoriesAggregation = [];
            if (is_array($categoriesRaw)) {
                foreach ($categoriesRaw as $c) {
                    $categoriesMap[$c['_id']] = $c['nombre'];
                    $categoriesAggregation[$c['_id']] = [
                        'id_categoria' => (int)$c['_id'],
                        'nombre' => $c['nombre'],
                        'unidades_fabricadas' => 0.0,
                        'total_precio_venta' => 0.0,
                        // Acumuladores para ponderaciones/promedios
                        'productos_count' => 0,
                        'suma_tiempo_estimado' => 0.0,
                        'suma_tiempo_real' => 0.0,
                        'suma_insumos_estimado' => 0.0,
                        'suma_insumos_real' => 0.0,
                        'suma_labor_estimado' => 0.0,
                        'suma_labor_real' => 0.0,
                        'suma_costo_total_estimado' => 0.0,
                        'suma_costo_total_real' => 0.0
                    ];
                }
            }

            // Mapas temporales de costos
            // El costo se normaliza a "por Mt" dividiendo por el rendimiento (Mt por Kg) SOLO cuando
            // el insumo se compra por Kg -- la Ficha Técnica (product_insumos_asignados) especifica
            // la tela en metros, mismo criterio de conversión Kg->Mt ya usado en reports.php/manufacturing.php.
            $invCostRaw = $db->goQuery("SELECT id_catalogo, AVG((costo / NULLIF(cantidad_inicial, 0)) / CASE WHEN unidad = 'Kg' AND rendimiento IS NOT NULL THEN rendimiento ELSE 1 END) AS costo_unitario FROM inventario GROUP BY id_catalogo");
            $invCostMap = [];
            if (is_array($invCostRaw)) {
                foreach ($invCostRaw as $ic) {
                    $invCostMap[$ic['id_catalogo']] = (float)$ic['costo_unitario'];
                }
            }

            $piaRaw = $db->goQuery("SELECT id_product, id_talla, id_catalogo_insumos_productos, cantidad FROM product_insumos_asignados");
            $piaEstimados = [];
            if (is_array($piaRaw)) {
                foreach ($piaRaw as $pia) {
                    $prodId = $pia['id_product'];
                    $tallaId = $pia['id_talla'];
                    $catId = $pia['id_catalogo_insumos_productos'];
                    $cant = (float)$pia['cantidad'];
                    $costoUnit = $invCostMap[$catId] ?? 0.0;
                    
                    if (!isset($piaEstimados[$prodId][$tallaId])) {
                        $piaEstimados[$prodId][$tallaId] = 0.0;
                    }
                    $piaEstimados[$prodId][$tallaId] += ($cant * $costoUnit);
                }
            }

            $comisionesRaw = $db->goQuery("SELECT id_product, SUM(comision) AS total_comision FROM products_comisiones GROUP BY id_product");
            $teoricoLaborMap = [];
            if (is_array($comisionesRaw)) {
                foreach ($comisionesRaw as $c) {
                    $teoricoLaborMap[$c['id_product']] = (float)$c['total_comision'];
                }
            }

            $tiemposRaw = $db->goQuery("SELECT id_product, SUM(tiempo) AS total_tiempo FROM products_tiempos_de_produccion GROUP BY id_product");
            $teoricoTiempoMap = [];
            if (is_array($tiemposRaw)) {
                foreach ($tiemposRaw as $t) {
                    $teoricoTiempoMap[$t['id_product']] = (float)$t['total_tiempo'];
                }
            }

            // Datos reales filtrados
            $momentDateExpr = DB_DRIVER === 'pgsql' ? 'o.moment::date' : 'DATE(o.moment)';
            $fechaCierreExpr = DB_DRIVER === 'pgsql'
                ? "COALESCE(ldea.fecha_terminado, NULLIF(o.fecha_entrega, '')::date, o.fecha_creacion, o.moment::date)"
                : "COALESCE(ldea.fecha_terminado, STR_TO_DATE(NULLIF(o.fecha_entrega, ''), '%Y-%m-%d'), o.fecha_creacion, DATE(o.moment))";
            $params = [];
            $dateCond = "";
            if ($fecha_inicio && $fecha_fin) {
                $dateCond = " AND $momentDateExpr BETWEEN :fecha_inicio AND :fecha_fin ";
                $params['fecha_inicio'] = $fecha_inicio;
                $params['fecha_fin'] = $fecha_fin;
            }

            $cantRealRaw = $db->goQuery("
                SELECT op.id_woo as id_producto, SUM(op.cantidad) AS total_cantidad
                FROM ordenes_productos op
                JOIN ordenes o ON op.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY op.id_woo
            ", $params);
            $realCantidadMap = [];
            if (is_array($cantRealRaw)) {
                foreach ($cantRealRaw as $r) {
                    $realCantidadMap[$r['id_producto']] = (float)$r['total_cantidad'];
                }
            }

            // Cantidad real por producto y talla, para ponderar el costo estimado de insumos por
            // la mezcla real de tallas producidas -- mismo criterio que el endpoint de productos.
            $cantRealPorTallaRaw = $db->goQuery("
                SELECT op.id_woo as id_producto, op.id_size as id_talla, SUM(op.cantidad) AS total_cantidad
                FROM ordenes_productos op
                JOIN ordenes o ON op.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY op.id_woo, op.id_size
            ", $params);
            $realCantidadPorTallaMap = [];
            if (is_array($cantRealPorTallaRaw)) {
                foreach ($cantRealPorTallaRaw as $r) {
                    $realCantidadPorTallaMap[$r['id_producto']][$r['id_talla']] = (float)$r['total_cantidad'];
                }
            }

            $teoricoInsumosMap = [];
            foreach ($piaEstimados as $prodId => $tallas) {
                $pesoTotal = 0.0;
                $sumaPonderada = 0.0;
                foreach ($tallas as $tallaId => $costoTalla) {
                    $peso = $realCantidadPorTallaMap[$prodId][$tallaId] ?? 0.0;
                    $sumaPonderada += $costoTalla * $peso;
                    $pesoTotal += $peso;
                }
                if ($pesoTotal > 0) {
                    $teoricoInsumosMap[$prodId] = $sumaPonderada / $pesoTotal;
                } else {
                    $total = array_sum($tallas);
                    $cnt = count($tallas);
                    $teoricoInsumosMap[$prodId] = $cnt > 0 ? $total / $cnt : 0.0;
                }
            }

            $insumosRealRaw = $db->goQuery("
                SELECT im.id_producto, SUM(ABS(im.valor_inicial - im.valor_final) * COALESCE((inv.costo / NULLIF(inv.cantidad_inicial, 0)), 0)) AS total_costo
                FROM inventario_movimientos im
                JOIN inventario inv ON im.id_insumo = inv._id
                JOIN ordenes o ON im.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') $dateCond
                GROUP BY im.id_producto
            ", $params);
            $realInsumosMap = [];
            if (is_array($insumosRealRaw)) {
                foreach ($insumosRealRaw as $r) {
                    $realInsumosMap[$r['id_producto']] = (float)$r['total_costo'];
                }
            }

            // Comisiones reales (pagos) por producto para el rollup de categorías — mismo reparto
            // por orden->ordenes_productos (comisión x cantidad), sin lotes_detalles. Excluye comisión
            // de vendedor (Comercialización/Abono a orden), ver detalle en el endpoint de productos.
            $pagosRealRaw = $db->goQuery("
                SELECT id_woo AS id_producto, SUM(share) AS total_pagos
                FROM (
                    SELECT op.id_woo,
                           p.monto_pago * (COALESCE(peso.w, 0) * op.cantidad + 0.0001)
                             / SUM(COALESCE(peso.w, 0) * op.cantidad + 0.0001) OVER (PARTITION BY p._id) AS share
                    FROM pagos p
                    JOIN ordenes o ON p.id_orden = o._id
                    JOIN ordenes_productos op ON op.id_orden = p.id_orden
                    LEFT JOIN (SELECT id_product, SUM(comision) w FROM products_comisiones GROUP BY id_product) peso
                        ON peso.id_product = op.id_woo
                    WHERE o.status IN ('terminada', 'entregada')
                      AND p.detalle NOT IN ('Comercialización', 'Abono a orden') $dateCond
                ) t
                GROUP BY id_woo
            ", $params);
            $realPagosMap = [];
            if (is_array($pagosRealRaw)) {
                foreach ($pagosRealRaw as $r) {
                    $realPagosMap[$r['id_producto']] = (float)$r['total_pagos'];
                }
            }

            // Tiempos reales por producto para el rollup de categorías — misma atribución por
            // ordenes_productos + departamento (tiempo teórico x cantidad). Horas vía
            // calcularHorasLaboradasReales(), ver detalle en el endpoint de productos.
            $horarioObj = $getHorarioLaboral($dbEmpresas, $id_empresa);
            $ldeaRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre,
                       op.id_woo AS id_producto, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL $dateCond
            ", $params);

            $realTiempoMap = [];
            if (is_array($ldeaRaw)) {
                $porLdea = [];
                foreach ($ldeaRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_producto' => $row['id_producto'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $segundosReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj) * 3600;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        $share = $pesoTotal > 0 ? ($segundosReales * $linea['peso'] / $pesoTotal) : 0.0;
                        $prodId = $linea['id_producto'];
                        if (!isset($realTiempoMap[$prodId])) $realTiempoMap[$prodId] = 0.0;
                        $realTiempoMap[$prodId] += $share;
                    }
                }
            }

            // Salario fijo por horas para el rollup de categorías — misma atribución por
            // ordenes_productos + departamento (tiempo teórico x cantidad). Horas vía
            // calcularHorasLaboradasReales(), ver detalle en el endpoint de productos.
            $costoHoraMap = $getSalarios($db, $dbEmpresas, $id_empresa);
            $ldeaSalarioRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre, ldea.id_empleado,
                       op.id_woo AS id_producto, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = ldea.id_empleado
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL
                  AND eu.salario_tipo IN ('Salario', 'Salario más Comisión') $dateCond
            ", $params);

            $realSalarioFijoMap = [];
            if (is_array($ldeaSalarioRaw)) {
                $porLdea = [];
                foreach ($ldeaSalarioRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['id_empleado'] = $row['id_empleado'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_producto' => $row['id_producto'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $horasReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj);
                    $costoHora = $costoHoraMap[$data['id_empleado']] ?? 0.0;
                    $costoTotalTarea = $horasReales * $costoHora;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        $share = $pesoTotal > 0 ? ($costoTotalTarea * $linea['peso'] / $pesoTotal) : 0.0;
                        $prodId = $linea['id_producto'];
                        if (!isset($realSalarioFijoMap[$prodId])) $realSalarioFijoMap[$prodId] = 0.0;
                        $realSalarioFijoMap[$prodId] += $share;
                    }
                }
            }

            // Realizar la agregación por categorías
            foreach ($productsRaw as $p) {
                $id = (int)$p['_id'];
                if (empty($p['category_ids'])) continue;
                
                $cIds = array_map('intval', explode(',', $p['category_ids']));
                
                $unidadesReales = $realCantidadMap[$id] ?? 0.0;
                if ($unidadesReales <= 0) {
                    continue;
                }
                $estInsumos = $teoricoInsumosMap[$id] ?? 0.0;
                $estLabor = $teoricoLaborMap[$id] ?? 0.0;
                $estTotal = $estInsumos + $estLabor;
                $estTiempo = $teoricoTiempoMap[$id] ?? 0.0;

                $realInsumos = 0.0;
                $realLabor = 0.0;
                $realTiempo = 0.0;
                if ($unidadesReales > 0) {
                    $realInsumos = ($realInsumosMap[$id] ?? 0.0) / $unidadesReales;
                    $realLabor = (($realPagosMap[$id] ?? 0.0) + ($realSalarioFijoMap[$id] ?? 0.0)) / $unidadesReales;
                    $realTiempo = ($realTiempoMap[$id] ?? 0.0) / $unidadesReales;
                }
                $realTotal = $realInsumos + $realLabor;

                $precioVenta = (float)$p['precio_venta'];

                foreach ($cIds as $cid) {
                    if (!isset($categoriesAggregation[$cid])) continue;
                    
                    $categoriesAggregation[$cid]['productos_count']++;
                    $categoriesAggregation[$cid]['unidades_fabricadas'] += $unidadesReales;
                    $categoriesAggregation[$cid]['total_precio_venta'] += $precioVenta;
                    
                    $categoriesAggregation[$cid]['suma_tiempo_estimado'] += $estTiempo;
                    $categoriesAggregation[$cid]['suma_tiempo_real'] += $realTiempo;
                    $categoriesAggregation[$cid]['suma_insumos_estimado'] += $estInsumos;
                    $categoriesAggregation[$cid]['suma_insumos_real'] += $realInsumos;
                    $categoriesAggregation[$cid]['suma_labor_estimado'] += $estLabor;
                    $categoriesAggregation[$cid]['suma_labor_real'] += $realLabor;
                    $categoriesAggregation[$cid]['suma_costo_total_estimado'] += $estTotal;
                    $categoriesAggregation[$cid]['suma_costo_total_real'] += $realTotal;
                }
            }

            // Calcular los promedios finales de las categorías
            $resultList = [];
            foreach ($categoriesAggregation as $cid => $cat) {
                $count = $cat['productos_count'];
                if ($count === 0) continue; // no products in category, skip

                $estTiempo = $cat['suma_tiempo_estimado'] / $count;
                $realTiempo = $cat['suma_tiempo_real'] / $count;
                $estInsumos = $cat['suma_insumos_estimado'] / $count;
                $realInsumos = $cat['suma_insumos_real'] / $count;
                $estLabor = $cat['suma_labor_estimado'] / $count;
                $realLabor = $cat['suma_labor_real'] / $count;
                $estTotal = $cat['suma_costo_total_estimado'] / $count;
                $realTotal = $cat['suma_costo_total_real'] / $count;
                $precioPromedio = $cat['total_precio_venta'] / $count;

                // Desviaciones
                $devInsumos = $estInsumos > 0 ? (($realInsumos - $estInsumos) / $estInsumos) * 100 : 0.0;
                $devLabor = $estLabor > 0 ? (($realLabor - $estLabor) / $estLabor) * 100 : 0.0;
                $devTiempo = $estTiempo > 0 ? (($realTiempo - $estTiempo) / $estTiempo) * 100 : 0.0;
                $devTotal = $estTotal > 0 ? (($realTotal - $estTotal) / $estTotal) * 100 : 0.0;

                $margenEst = $precioPromedio > 0 ? (($precioPromedio - $estTotal) / $precioPromedio) * 100 : 0.0;
                $margenReal = $precioPromedio > 0 ? (($precioPromedio - $realTotal) / $precioPromedio) * 100 : 0.0;

                $resultList[] = [
                    'id_categoria' => $cid,
                    'nombre' => $cat['nombre'],
                    'productos_count' => $count,
                    'unidades_fabricadas' => $cat['unidades_fabricadas'],
                    'precio_venta' => round($precioPromedio, 2),
                    // Tiempos
                    'tiempo_estimado' => round($estTiempo, 0),
                    'tiempo_real' => round($realTiempo, 0),
                    'desviacion_tiempo_pct' => round($devTiempo, 1),
                    // Insumos
                    'insumos_estimado' => round($estInsumos, 2),
                    'insumos_real' => round($realInsumos, 2),
                    'desviacion_insumos_pct' => round($devInsumos, 1),
                    // Mano de Obra
                    'labor_estimado' => round($estLabor, 2),
                    'labor_real' => round($realLabor, 2),
                    'desviacion_labor_pct' => round($devLabor, 1),
                    // Costos totales
                    'costo_total_estimado' => round($estTotal, 2),
                    'costo_total_real' => round($realTotal, 2),
                    'desviacion_total_pct' => round($devTotal, 1),
                    // Márgenes
                    'margen_estimado_pct' => round($margenEst, 1),
                    'margen_real_pct' => round($margenReal, 1)
                ];
            }

            $dbEmpresas->disconnect();
            $db->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $resultList
            ], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            if (isset($db)) $db->disconnect();
            if (isset($dbEmpresas)) $dbEmpresas->disconnect();
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // =================================================================
    // ENDPOINT: DETALLE DE PRODUCTO POR TALLAS
    // =================================================================
    $app->get('/reportes/costos-productos/{id_producto}/detalle', function (Request $request, Response $response, array $args) use ($getSalarios, $getHorarioLaboral) {
        $id_producto = (int)$args['id_producto'];
        $queryParams = $request->getQueryParams();
        $fecha_inicio = !empty($queryParams['fecha_inicio']) ? $queryParams['fecha_inicio'] : null;
        $fecha_fin = !empty($queryParams['fecha_fin']) ? $queryParams['fecha_fin'] : null;
        $id_empresa = ID_EMPRESA;

        try {
            $db = new LocalDB();
            if (DB_DRIVER === 'pgsql') {
                $dbEmpresas = new LocalDB('', LOCAL_DNS, LOCAL_USER, LOCAL_PASS);
            } else {
                $adminDNS = str_replace('127.0.0.1', 'localhost', EMPRESAS_DNS);
                $dbEmpresas = new LocalDB('', $adminDNS, EMPRESAS_USER, EMPRESAS_PASS);
            }

            // 1. Obtener detalles básicos del producto
            $productRaw = $db->goQuery("SELECT p._id, p.product as nombre, p.sku, COALESCE((SELECT price FROM products_prices WHERE id_product = p._id ORDER BY _id ASC LIMIT 1), 0) as precio_venta FROM products p WHERE p._id = ?", [$id_producto]);
            if (empty($productRaw)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Producto no encontrado']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            $product = $productRaw[0];

            // 2. Obtener nombres de tallas/sizes
            $sizesRaw = $db->goQuery("SELECT _id, nombre FROM sizes");
            $sizesMap = [];
            if (is_array($sizesRaw)) {
                foreach ($sizesRaw as $s) {
                    $sizesMap[$s['_id']] = $s['nombre'];
                }
            }

            // 3. Obtener costos de inventario
            // El costo se normaliza a "por Mt" dividiendo por el rendimiento (Mt por Kg) SOLO cuando
            // el insumo se compra por Kg -- la Ficha Técnica (product_insumos_asignados) especifica
            // la tela en metros, mismo criterio de conversión Kg->Mt ya usado en reports.php/manufacturing.php.
            $invCostRaw = $db->goQuery("SELECT id_catalogo, AVG((costo / NULLIF(cantidad_inicial, 0)) / CASE WHEN unidad = 'Kg' AND rendimiento IS NOT NULL THEN rendimiento ELSE 1 END) AS costo_unitario FROM inventario GROUP BY id_catalogo");
            $invCostMap = [];
            if (is_array($invCostRaw)) {
                foreach ($invCostRaw as $ic) {
                    $invCostMap[$ic['id_catalogo']] = (float)$ic['costo_unitario'];
                }
            }

            // 4. Insumos Estimados (Configurados en Ficha Técnica) para este producto
            $piaRaw = $db->goQuery("
                SELECT pia.id_talla, cip.nombre AS nombre_insumo, pia.cantidad, pia.unidad, pia.id_catalogo_insumos_productos
                FROM product_insumos_asignados pia
                JOIN catalogo_insumos_productos cip ON cip._id = pia.id_catalogo_insumos_productos
                WHERE pia.id_product = ?
            ", [$id_producto]);

            $tallaInsumosTeoricos = [];
            if (is_array($piaRaw)) {
                foreach ($piaRaw as $pia) {
                    $tId = (int)$pia['id_talla'];
                    $catId = (int)$pia['id_catalogo_insumos_productos'];
                    $cant = (float)$pia['cantidad'];
                    $costoUnit = $invCostMap[$catId] ?? 0.0;
                    $totalCosto = $cant * $costoUnit;

                    $tallaInsumosTeoricos[$tId][] = [
                        'nombre_insumo' => $pia['nombre_insumo'],
                        'cantidad_estimada' => $cant,
                        'unidad' => $pia['unidad'] ?: 'Und',
                        'costo_unitario' => round($costoUnit, 2),
                        'costo_total_estimado' => round($totalCosto, 2)
                    ];
                }
            }

            // 5. Mano de Obra Estimada por departamento para el producto
            $comisionesRaw = $db->goQuery("
                SELECT d.departamento, pc.comision
                FROM products_comisiones pc
                JOIN departamentos d ON d._id = pc.id_departamento
                WHERE pc.id_product = ?
            ", [$id_producto]);
            $teoricoLabor = is_array($comisionesRaw) ? $comisionesRaw : [];

            // 6. Tiempo Estimado por departamento para el producto
            $tiemposRaw = $db->goQuery("
                SELECT d.departamento, ptp.tiempo
                FROM products_tiempos_de_produccion ptp
                JOIN departamentos d ON d._id = ptp.id_departamento
                WHERE ptp.id_product = ?
            ", [$id_producto]);
            $teoricoTiempo = is_array($tiemposRaw) ? $tiemposRaw : [];

            // --- HISTORIAL DE DATOS REALES REGISTRADOS POR TALLA EN TALLER ---
            $momentDateExpr = DB_DRIVER === 'pgsql' ? 'o.moment::date' : 'DATE(o.moment)';
            $fechaCierreExpr = DB_DRIVER === 'pgsql'
                ? "COALESCE(ldea.fecha_terminado, NULLIF(o.fecha_entrega, '')::date, o.fecha_creacion, o.moment::date)"
                : "COALESCE(ldea.fecha_terminado, STR_TO_DATE(NULLIF(o.fecha_entrega, ''), '%Y-%m-%d'), o.fecha_creacion, DATE(o.moment))";
            $params = [':id_producto' => $id_producto];
            $dateCond = "";
            if ($fecha_inicio && $fecha_fin) {
                $dateCond = " AND $momentDateExpr BETWEEN :fecha_inicio AND :fecha_fin ";
                $params['fecha_inicio'] = $fecha_inicio;
                $params['fecha_fin'] = $fecha_fin;
            }

            // 7. Cantidad producida por talla
            $cantRealRaw = $db->goQuery("
                SELECT op.id_size as id_talla, SUM(op.cantidad) AS total_cantidad
                FROM ordenes_productos op
                JOIN ordenes o ON op.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') AND op.id_woo = :id_producto $dateCond
                GROUP BY op.id_size
            ", $params);
            
            $realCantidadMap = [];
            if (is_array($cantRealRaw)) {
                foreach ($cantRealRaw as $r) {
                    $realCantidadMap[(int)$r['id_talla']] = (float)$r['total_cantidad'];
                }
            }

            // 8. Tiempos reales laborados por talla — misma atribución por ordenes_productos + departamento
            //    (tiempo teórico x cantidad); se distribuye cada tarea sobre TODAS las líneas de su
            //    orden (para el peso correcto) y luego se filtra a este producto, agrupando por talla.
            //    Horas vía calcularHorasLaboradasReales(), ver detalle en el endpoint de productos.
            $horarioObj = $getHorarioLaboral($dbEmpresas, $id_empresa);
            $ldeaRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre,
                       op.id_woo, op.id_size AS id_talla, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL $dateCond
                  AND ldea.id_orden IN (SELECT id_orden FROM ordenes_productos WHERE id_woo = :id_producto)
            ", $params);

            $realTiempoMap = [];
            if (is_array($ldeaRaw)) {
                $porLdea = [];
                foreach ($ldeaRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_woo' => $row['id_woo'],
                        'id_talla' => $row['id_talla'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $segundosReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj) * 3600;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        if ((int)$linea['id_woo'] !== (int)$id_producto) continue;
                        $share = $pesoTotal > 0 ? ($segundosReales * $linea['peso'] / $pesoTotal) : 0.0;
                        $tId = (int)$linea['id_talla'];
                        if (!isset($realTiempoMap[$tId])) $realTiempoMap[$tId] = 0.0;
                        $realTiempoMap[$tId] += $share;
                    }
                }
            }

            // 9. Comisiones reales pagadas (pagos) por talla — mismo reparto por orden->ordenes_productos
            //    (comisión x cantidad, sin lotes_detalles); se distribuye cada pago sobre TODAS las líneas
            //    de su orden y luego se filtra al producto y se agrupa por talla (id_size). Excluye
            //    comisión de vendedor (Comercialización/Abono a orden), ver detalle en el endpoint de productos.
            $pagosRealRaw = $db->goQuery("
                SELECT id_talla, SUM(share) AS total_pagos
                FROM (
                    SELECT op.id_woo, op.id_size AS id_talla,
                           p.monto_pago * (COALESCE(peso.w, 0) * op.cantidad + 0.0001)
                             / SUM(COALESCE(peso.w, 0) * op.cantidad + 0.0001) OVER (PARTITION BY p._id) AS share
                    FROM pagos p
                    JOIN ordenes o ON p.id_orden = o._id
                    JOIN ordenes_productos op ON op.id_orden = p.id_orden
                    LEFT JOIN (SELECT id_product, SUM(comision) w FROM products_comisiones GROUP BY id_product) peso
                        ON peso.id_product = op.id_woo
                    WHERE o.status IN ('terminada', 'entregada')
                      AND p.detalle NOT IN ('Comercialización', 'Abono a orden') $dateCond
                ) t
                WHERE id_woo = :id_producto
                GROUP BY id_talla
            ", $params);
            $realPagosMap = [];
            if (is_array($pagosRealRaw)) {
                foreach ($pagosRealRaw as $r) {
                    $realPagosMap[(int)$r['id_talla']] = (float)$r['total_pagos'];
                }
            }

            // 10. Salarios por hora y horas por talla — misma atribución por ordenes_productos + departamento
            //     (tiempo teórico x cantidad). Horas vía calcularHorasLaboradasReales(), ver detalle
            //     en el endpoint de productos.
            $costoHoraMap = $getSalarios($db, $dbEmpresas, $id_empresa);
            $ldeaSalarioRaw = $db->goQuery("
                SELECT ldea._id AS id_ldea, ldea.fecha_inicio, $fechaCierreExpr AS fecha_cierre, ldea.id_empleado,
                       op.id_woo, op.id_size AS id_talla, op.cantidad, COALESCE(pt.tiempo, 0) AS tiempo_teorico
                FROM lotes_detalles_empleados_asignados ldea
                JOIN ordenes o ON ldea.id_orden = o._id
                JOIN ordenes_productos op ON op.id_orden = ldea.id_orden
                LEFT JOIN products_tiempos_de_produccion pt ON pt.id_product = op.id_woo AND pt.id_departamento = ldea.id_departamento
                JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = ldea.id_empleado
                WHERE o.status IN ('terminada', 'entregada') AND ldea.fecha_inicio IS NOT NULL
                  AND eu.salario_tipo IN ('Salario', 'Salario más Comisión') $dateCond
                  AND ldea.id_orden IN (SELECT id_orden FROM ordenes_productos WHERE id_woo = :id_producto)
            ", $params);

            $realSalarioFijoMap = [];
            if (is_array($ldeaSalarioRaw)) {
                $porLdea = [];
                foreach ($ldeaSalarioRaw as $row) {
                    $lId = $row['id_ldea'];
                    $porLdea[$lId]['fecha_inicio'] = $row['fecha_inicio'];
                    $porLdea[$lId]['fecha_cierre'] = $row['fecha_cierre'];
                    $porLdea[$lId]['id_empleado'] = $row['id_empleado'];
                    $porLdea[$lId]['lineas'][] = [
                        'id_woo' => $row['id_woo'],
                        'id_talla' => $row['id_talla'],
                        'peso' => ((float)$row['tiempo_teorico'] * (float)$row['cantidad']) + 0.0001
                    ];
                }
                foreach ($porLdea as $data) {
                    $horasReales = calcularHorasLaboradasReales($data['fecha_inicio'], $data['fecha_cierre'], $horarioObj);
                    $costoHora = $costoHoraMap[$data['id_empleado']] ?? 0.0;
                    $costoTotalTarea = $horasReales * $costoHora;
                    $pesoTotal = array_sum(array_column($data['lineas'], 'peso'));
                    foreach ($data['lineas'] as $linea) {
                        if ((int)$linea['id_woo'] !== (int)$id_producto) continue;
                        $share = $pesoTotal > 0 ? ($costoTotalTarea * $linea['peso'] / $pesoTotal) : 0.0;
                        $tId = (int)$linea['id_talla'];
                        if (!isset($realSalarioFijoMap[$tId])) $realSalarioFijoMap[$tId] = 0.0;
                        $realSalarioFijoMap[$tId] += $share;
                    }
                }
            }

            // 11. Insumos consumidos reales para el producto completo (fallback a promediar a nivel de producto si la orden no especifica por talla)
            $totalInsumosRealProdRaw = $db->goQuery("
                SELECT im.id_catalogo_insumos_prodcutos AS id_catalogo, SUM(ABS(im.valor_inicial - im.valor_final)) AS cantidad_total, SUM(ABS(im.valor_inicial - im.valor_final) * COALESCE((inv.costo / NULLIF(inv.cantidad_inicial, 0)), 0)) AS costo_total
                FROM inventario_movimientos im
                JOIN inventario inv ON im.id_insumo = inv._id
                JOIN ordenes o ON im.id_orden = o._id
                WHERE o.status IN ('terminada', 'entregada') AND im.id_producto = :id_producto $dateCond
                GROUP BY im.id_catalogo_insumos_prodcutos
            ", $params);

            $insumosRealProdMap = [];
            if (is_array($totalInsumosRealProdRaw)) {
                foreach ($totalInsumosRealProdRaw as $ir) {
                    $insumosRealProdMap[(int)$ir['id_catalogo']] = [
                        'cantidad' => (float)$ir['cantidad_total'],
                        'costo' => (float)$ir['costo_total']
                    ];
                }
            }

            // Suma de unidades totales fabricadas de este producto
            $unidadesTotalesFabricadas = array_sum($realCantidadMap);

            // 12. Consolidar el detalle final por talla
            $tallasDetallesList = [];
            foreach ($sizesMap as $tId => $tName) {
                // Si esta talla no está configurada en la ficha técnica ni tiene datos reales, no la listamos
                $tieneEstimados = isset($tallaInsumosTeoricos[$tId]);
                $tieneReales = isset($realCantidadMap[$tId]) && $realCantidadMap[$tId] > 0;
                
                if (!$tieneEstimados && !$tieneReales) continue;

                $cantTalla = $realCantidadMap[$tId] ?? 0.0;

                // Estimar costos teóricos
                $insumosTeoricosList = $tallaInsumosTeoricos[$tId] ?? [];
                $costoInsumosTeorico = 0.0;
                foreach ($insumosTeoricosList as $it) {
                    $costoInsumosTeorico += $it['costo_total_estimado'];
                }

                $laborTeoricoSum = 0.0;
                foreach ($teoricoLabor as $l) {
                    $laborTeoricoSum += (float)$l['comision'];
                }

                $tiempoTeoricoSum = 0.0;
                foreach ($teoricoTiempo as $t) {
                    $tiempoTeoricoSum += (float)$t['tiempo'];
                }

                // Estimar costos reales
                $costoInsumosReal = 0.0;
                $insumosRealesList = [];

                // Mapear insumos consumidos reales para esta talla
                // Como los movimientos de inventario no se registran por talla sino por producto,
                // prorateamos la cantidad total consumida del producto en base a las unidades de esta talla
                foreach ($insumosTeoricosList as $it) {
                    // Buscar en el consumo total real del producto
                    $catId = null;
                    foreach ($piaRaw as $pia) {
                        if ((int)$pia['id_talla'] === $tId && $pia['nombre_insumo'] === $it['nombre_insumo']) {
                            $catId = (int)$pia['id_catalogo_insumos_productos'];
                            break;
                        }
                    }

                    $cantRealConsumidaTalla = 0.0;
                    $costoRealConsumidoTalla = 0.0;

                    // Solo se asigna el promedio si esta talla específica tuvo unidades reales
                    // fabricadas en el rango -- antes se aplicaba el promedio del producto a TODAS
                    // las tallas por igual, incluidas las que nunca se produjeron (mismas 0 unidades
                    // que ya dejan labor_real/tiempo_real en 0, pero insumos_real mostraba un costo
                    // no-cero engañoso).
                    if ($catId && isset($insumosRealProdMap[$catId]) && $unidadesTotalesFabricadas > 0 && $cantTalla > 0) {
                        // Consumo real promedio por prenda * prendas fabricadas de esta talla
                        $promedioPrendaCant = $insumosRealProdMap[$catId]['cantidad'] / $unidadesTotalesFabricadas;
                        $promedioPrendaCosto = $insumosRealProdMap[$catId]['costo'] / $unidadesTotalesFabricadas;

                        $cantRealConsumidaTalla = $promedioPrendaCant;
                        $costoRealConsumidoTalla = $promedioPrendaCosto;
                    }

                    $costoInsumosReal += $costoRealConsumidoTalla;

                    $insumosRealesList[] = [
                        'nombre_insumo' => $it['nombre_insumo'],
                        'cantidad_estimada' => $it['cantidad_estimada'],
                        'cantidad_real' => round($cantRealConsumidaTalla, 3),
                        'unidad' => $it['unidad'],
                        'costo_total_estimado' => $it['costo_total_estimado'],
                        'costo_total_real' => round($costoRealConsumidoTalla, 2),
                        'desviacion_pct' => $it['costo_total_estimado'] > 0 
                            ? round((($costoRealConsumidoTalla - $it['costo_total_estimado']) / $it['costo_total_estimado']) * 100, 1)
                            : 0.0
                    ];
                }

                $costoLaborReal = 0.0;
                $tiempoRealSeconds = 0.0;
                if ($cantTalla > 0) {
                    $costoLaborReal = (($realPagosMap[$tId] ?? 0.0) + ($realSalarioFijoMap[$tId] ?? 0.0)) / $cantTalla;
                    $tiempoRealSeconds = ($realTiempoMap[$tId] ?? 0.0) / $cantTalla;
                }

                $costoTotalTeorico = $costoInsumosTeorico + $laborTeoricoSum;
                $costoTotalReal = $costoInsumosReal + $costoLaborReal;

                $devTotal = $costoTotalTeorico > 0 ? (($costoTotalReal - $costoTotalTeorico) / $costoTotalTeorico) * 100 : 0.0;
                $devTiempo = $tiempoTeoricoSum > 0 ? (($tiempoRealSeconds - $tiempoTeoricoSum) / $tiempoTeoricoSum) * 100 : 0.0;

                $tallasDetallesList[] = [
                    'id_talla' => $tId,
                    'talla' => $tName,
                    'unidades_fabricadas' => $cantTalla,
                    // Tiempos
                    'tiempo_estimado' => round($tiempoTeoricoSum, 0),
                    'tiempo_real' => round($tiempoRealSeconds, 0),
                    'desviacion_tiempo_pct' => round($devTiempo, 1),
                    // Insumos
                    'insumos_estimado' => round($costoInsumosTeorico, 2),
                    'insumos_real' => round($costoInsumosReal, 2),
                    'insumos_detalle' => $insumosRealesList,
                    // Labor
                    'labor_estimado' => round($laborTeoricoSum, 2),
                    'labor_real' => round($costoLaborReal, 2),
                    // Totales
                    'costo_total_estimado' => round($costoTotalTeorico, 2),
                    'costo_total_real' => round($costoTotalReal, 2),
                    'desviacion_total_pct' => round($devTotal, 1)
                ];
            }

            $dbEmpresas->disconnect();
            $db->disconnect();

            $response->getBody()->write(json_encode([
                'success' => true,
                'producto' => [
                    'id_producto' => $id_producto,
                    'nombre' => $product['nombre'],
                    'sku' => $product['sku'],
                    'precio_venta' => (float)$product['precio_venta']
                ],
                'teorico_labor_dept' => $teoricoLabor,
                'teorico_tiempo_dept' => $teoricoTiempo,
                'tallas_detalle' => $tallasDetallesList
            ], JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            if (isset($db)) $db->disconnect();
            if (isset($dbEmpresas)) $dbEmpresas->disconnect();
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

};
