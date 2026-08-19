<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  $app->post('/impresoras', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    try {
      // Validación básica: el codigo_interno es obligatorio
      if (empty($data['codigo_interno'])) {
        $response->getBody()->write(json_encode(['error' => 'El campo codigo_interno es obligatorio.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      $sql = 'INSERT INTO catalogo_impresoras (codigo_interno, marca, modelo, ubicacion, tipo_tecnologia, id_catalogo_tintas, capacidad_contenedor, estado, notas, ml_tinta_por_metro, ingresar_tinta_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

      $params = [
        $data['codigo_interno'],
        $data['marca'] ?? null,
        $data['modelo'] ?? null,
        $data['ubicacion'] ?? null,
        $data['tipo_tecnologia'] ?? null,
        (isset($data['id_catalogo_tintas']) && $data['id_catalogo_tintas'] !== 'null' && $data['id_catalogo_tintas'] !== '') ? intval($data['id_catalogo_tintas']) : null,
        (isset($data['capacidad_contenedor']) && $data['capacidad_contenedor'] !== 'null' && $data['capacidad_contenedor'] !== '') ? floatval($data['capacidad_contenedor']) : null,
        $data['estado'] ?? 'activa',  // Valor por defecto 'activa'
        $data['notas'] ?? $data['notes'] ?? null,
        (isset($data['ml_tinta_por_metro']) && $data['ml_tinta_por_metro'] !== 'null' && $data['ml_tinta_por_metro'] !== '') ? floatval($data['ml_tinta_por_metro']) : 12.00,
        filter_var($data['ingresar_tinta_manual'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
      ];

      $localConnection->goQuery($sql, $params);
      $new_id = $localConnection->getLastID();

      // Guardar canales asociados
      $canales = $data['canales'] ?? [];
      if (is_array($canales)) {
        foreach ($canales as $id_color) {
          $sqlInsertColor = DB_DRIVER === 'pgsql'
            ? 'INSERT INTO impresoras_colores (id_catalogo_impresora, id_color_tinta) VALUES (?, ?) ON CONFLICT (id_catalogo_impresora, id_color_tinta) DO NOTHING'
            : 'INSERT IGNORE INTO impresoras_colores (id_catalogo_impresora, id_color_tinta) VALUES (?, ?)';
          $localConnection->goQuery($sqlInsertColor, [$new_id, intval($id_color)]);
        }
      }

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
      // Por defecto se excluyen las impresoras marcadas 'eliminada' (soft
      // delete) -- no deben aparecer en selectores de recarga/servicio ni en
      // dashboards de estado actual. La pantalla de Gestión de Impresoras SÍ
      // las pide explícitamente (?incluirEliminadas=1) para poder reactivarlas.
      $incluirEliminadas = filter_var($request->getQueryParams()['incluirEliminadas'] ?? false, FILTER_VALIDATE_BOOLEAN);
      $estadoWhere = $incluirEliminadas ? '' : "WHERE ci.estado != 'eliminada'";

      if (DB_DRIVER === 'pgsql') {
        $canalesColoresExpr = "(
                        SELECT (json_agg(json_build_object('id_color', ic.id_color_tinta, 'codigo', cct.codigo, 'nombre', cct.nombre, 'color_hex', cct.color_hex, 'ml_por_metro', tcc.ml_por_metro)))::text
                        FROM impresoras_colores ic
                        JOIN catalogo_colores_tintas cct ON ic.id_color_tinta = cct._id
                        LEFT JOIN tintas_calibracion_colores tcc ON tcc.id_catalogo_impresora = ci._id AND tcc.id_color_tinta = ic.id_color_tinta
                        WHERE ic.id_catalogo_impresora = ci._id
                    ) AS canales_colores";
        $tintasRecargasExpr = "(json_agg(
                        json_build_object(
                            'id', tr._id,
                            'id_catalogo_impresora', tr.id_catalogo_impresora,
                            'id_insumo', tr.id_insumo,
                            'color', cct_tr.codigo,
                            'cantidad', tr.cantidad,
                            'nivel_tanque_previo', COALESCE(tr.nivel_tanque_previo, 0),
                            'fecha_recarga', tr.fecha_recarga
                        )
                    ))::text AS tintas_recargas";
      } else {
        $canalesColoresExpr = "(
                        SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('id_color', ic.id_color_tinta, 'codigo', cct.codigo, 'nombre', cct.nombre, 'color_hex', cct.color_hex, 'ml_por_metro', tcc.ml_por_metro)), ']')
                        FROM impresoras_colores ic
                        JOIN catalogo_colores_tintas cct ON ic.id_color_tinta = cct._id
                        LEFT JOIN tintas_calibracion_colores tcc ON tcc.id_catalogo_impresora = ci._id AND tcc.id_color_tinta = ic.id_color_tinta
                        WHERE ic.id_catalogo_impresora = ci._id
                    ) AS canales_colores";
        $tintasRecargasExpr = "CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', tr._id,
                                'id_catalogo_impresora', tr.id_catalogo_impresora,
                                'id_insumo', tr.id_insumo,
                                'color', cct_tr.codigo,
                                'cantidad', tr.cantidad,
                                'nivel_tanque_previo', COALESCE(tr.nivel_tanque_previo, 0),
                                'fecha_recarga', tr.fecha_recarga
                            )
                        ),
                        ']'
                    ) AS tintas_recargas";
      }
      $sql = "SELECT
                    ci._id,
                    ci.codigo_interno,
                    ci.marca,
                    ci.modelo,
                    ci.capacidad_contenedor,
                    ci.ubicacion,
                    ci.tipo_tecnologia,
                    ci.id_catalogo_tintas,
                    ctt.nombre AS tecnologia_nombre,
                    ci.estado,
                    ci.notas,
                    ci.ml_tinta_por_metro,
                    ci.ingresar_tinta_manual,
                    ci.moment,
                    $canalesColoresExpr,
                    $tintasRecargasExpr
                FROM
                    catalogo_impresoras ci
                LEFT JOIN
                    catalogo_tintas ctt ON ci.id_catalogo_tintas = ctt._id
                LEFT JOIN
                    tintas_recargas tr ON ci._id = tr.id_catalogo_impresora
                LEFT JOIN
                    catalogo_colores_tintas cct_tr ON tr.id_color_tinta = cct_tr._id
                {$estadoWhere}
                GROUP BY
                    ci._id, ci.codigo_interno, ci.marca, ci.modelo, ci.capacidad_contenedor, ci.ubicacion, ci.tipo_tecnologia, ci.id_catalogo_tintas, ctt.nombre, ci.estado, ci.notas, ci.ml_tinta_por_metro, ci.ingresar_tinta_manual, ci.moment
                ORDER BY
                    ci._id DESC";
      $data = $localConnection->goQuery($sql);

      // --- 1. Obtener Consumo Detallado para calcular por ciclo ---
      $sqlConsumo = "
        SELECT t.id_catalogo_impresoras, cct.codigo AS color, t.cantidad, t.moment 
        FROM tintas t 
        JOIN catalogo_colores_tintas cct ON t.id_color_tinta = cct._id 
        WHERE t.cantidad > 0 
        ORDER BY t.moment ASC
      ";
      $allConsumos = $localConnection->goQuery($sqlConsumo);

      // Procesar cada impresora para calcular sus ciclos de tinta
      foreach ($data as &$row) {
        $row['canales_colores'] = json_decode($row['canales_colores'] ?? '[]', true) ?? [];
        $row['tintas_recargas'] = json_decode($row['tintas_recargas'] ?? '[]', true);
        if (!$row['tintas_recargas'] || (count($row['tintas_recargas']) == 1 && $row['tintas_recargas'][0]['id'] === null)) {
          $row['tintas_recargas'] = [];
          continue;
        }

        // Agrupar recargas por color
        $recargasPorColor = [];
        foreach ($row['tintas_recargas'] as $r) {
            if ($r['id'] === null) continue;
            $recargasPorColor[$r['color']][] = $r;
        }

        $procesadas = [];

        foreach ($recargasPorColor as $color => $colorRecargas) {
            // Ordenamos por fecha para procesar los ciclos cronológicamente
            usort($colorRecargas, function($a, $b) {
                return strcmp($a['fecha_recarga'], $b['fecha_recarga']);
            });

            for ($i = 0; $i < count($colorRecargas); $i++) {
                $curr = $colorRecargas[$i];
                $next = $colorRecargas[$i+1] ?? null;

                // Nivel total en tanque al terminar esta recarga
                $curr['restante_post_recarga'] = (float)$curr['nivel_tanque_previo'] + (float)$curr['cantidad'];
                
                // El ciclo de este insumo va desde que se echa hasta que se vuelve a recargar ese color
                $inicio = $curr['fecha_recarga'];
                $fin = $next ? $next['fecha_recarga'] : date('Y-m-d H:i:s');
                
                $consumoCiclo = 0;
                if (is_array($allConsumos)) {
                    foreach ($allConsumos as $c) {
                        if ($c['id_catalogo_impresoras'] == $row['_id'] && $c['color'] == $color) {
                            if ($c['moment'] >= $inicio && $c['moment'] < $fin) {
                                $consumoCiclo += (float)$c['cantidad'];
                            }
                        }
                    }
                }
                $curr['consumido_en_ciclo'] = $consumoCiclo;
                
                // Remanente (Ajuste): Diferencia al final del ciclo
                if ($next) {
                    $teoricoAlFinal = $curr['restante_post_recarga'] - $consumoCiclo;
                    $realAlFinal = (float)$next['nivel_tanque_previo'];
                    $curr['desperdicio_ajuste'] = $teoricoAlFinal - $realAlFinal;
                } else {
                    $curr['desperdicio_ajuste'] = null; // Ciclo aún abierto
                }
                
                $procesadas[] = $curr;
            }
        }
        
        $row['tintas_recargas'] = $procesadas;
        // Ordenamos DESC para la tabla del frontend
        usort($row['tintas_recargas'], function($a, $b) {
            return strcmp($b['fecha_recarga'], $a['fecha_recarga']);
        });
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

  $app->get('/impresoras-tintas-actual[/{id_impresora}]', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    try {
      $id_impresora = $args['id_impresora'] ?? null;
      $whereClause = $id_impresora
        ? "WHERE ci._id = " . intval($id_impresora) . " AND ci.estado != 'eliminada'"
        : "WHERE ci.estado != 'eliminada'";

      $sql = <<<SQL
        -- Usamos Common Table Expressions (CTEs) para organizar la lógica en pasos.

        WITH 
        -- =======================================================================================
        -- PASO 1: "Desglosar" el consumo. La tabla `tintas` ahora tiene una fila por color.
        -- =======================================================================================
        consumo_desglosado AS (
            SELECT 
                t.id_catalogo_impresoras, 
                cct.codigo AS color, 
                t.cantidad AS consumo, 
                t.moment AS fecha_orden 
            FROM tintas t 
            JOIN ordenes o ON t.id_orden = o._id 
            JOIN catalogo_colores_tintas cct ON t.id_color_tinta = cct._id
            WHERE t.cantidad > 0
        ),

        -- =======================================================================================
        -- PASO 3: Calcular métricas históricas y detalles de la última recarga.
        -- =======================================================================================
        stats_recargas AS (
            SELECT
                tr.id_catalogo_impresora,
                cct.codigo AS color,
                SUM(tr.cantidad) AS total_recargado_historico_ml,
                MAX(tr.fecha_recarga) AS fecha_ultima_recarga,
                -- Obtenemos la cantidad de la última recarga y el nivel previo
                (SELECT tr2.cantidad FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.id_color_tinta = tr.id_color_tinta ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1) AS ultima_cantidad_recargada_ml,
                (SELECT tr2.nivel_tanque_previo FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.id_color_tinta = tr.id_color_tinta ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1) AS nivel_tanque_previo_actual,
                -- Obtenemos los datos de la PENÚLTIMA recarga para calcular el desperdicio del ciclo que cerró
                (SELECT tr2.fecha_recarga FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.id_color_tinta = tr.id_color_tinta ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS fecha_penultima_recarga,
                (SELECT tr2.cantidad FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.id_color_tinta = tr.id_color_tinta ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS cantidad_penultima_recarga,
                (SELECT tr2.nivel_tanque_previo FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.id_color_tinta = tr.id_color_tinta ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS nivel_tanque_previo_penultima
            FROM
                tintas_recargas tr
            JOIN
                catalogo_colores_tintas cct ON tr.id_color_tinta = cct._id
            GROUP BY
                tr.id_catalogo_impresora,
                cct.codigo,
                tr.id_color_tinta
        ),

        -- =======================================================================================
        -- PASO 4: Calcular el consumo histórico total.
        -- =======================================================================================
        consumo_historico AS (
            SELECT
                id_catalogo_impresoras,
                color,
                SUM(consumo) AS consumo_total_historico_ml
            FROM
                consumo_desglosado
            GROUP BY
                id_catalogo_impresoras,
                color
        )

        -- =======================================================================================
        -- PASO 5: Unir todo y calcular los saldos finales.
        -- =======================================================================================
        SELECT
            ci.codigo_interno AS impresora,
            sr.color,
            ci.capacidad_contenedor AS capacidad_tanque_ml,
            sr.fecha_ultima_recarga AS fecha_ultima_recarga,
            sr.ultima_cantidad_recargada_ml,
            sr.nivel_tanque_previo_actual,
            sr.total_recargado_historico_ml,
            COALESCE(ch.consumo_total_historico_ml, 0) AS consumo_total_historico_ml,
            -- Saldo Histórico: Total Recargado - Consumo Total
            (COALESCE(sr.total_recargado_historico_ml, 0) - COALESCE(ch.consumo_total_historico_ml, 0)) AS tinta_restante_total_ml,
            
            -- Consumo desde la última recarga
            COALESCE((
                SELECT SUM(cd.consumo) 
                FROM consumo_desglosado cd 
                WHERE cd.id_catalogo_impresoras = sr.id_catalogo_impresora 
                AND cd.color = sr.color 
                AND cd.fecha_orden > sr.fecha_ultima_recarga
            ), 0) AS consumo_desde_ultima_recarga_ml,

            -- Tinta restante ACTUAL: Nivel previo + Nueva carga - Consumo desde entonces
            (COALESCE(sr.nivel_tanque_previo_actual, 0) + COALESCE(sr.ultima_cantidad_recargada_ml, 0) - COALESCE((
                SELECT SUM(cd.consumo) 
                FROM consumo_desglosado cd 
                WHERE cd.id_catalogo_impresoras = sr.id_catalogo_impresora 
                AND cd.color = sr.color 
                AND cd.fecha_orden > sr.fecha_ultima_recarga
            ), 0)) AS tinta_restante_ultima_recarga_ml,

            -- CÁLCULO DE DESPERDICIO DEL CICLO ANTERIOR:
            -- Nivel inicial ciclo anterior (NP-1 + Cant-1) - Consumo ciclo anterior - Nivel Real actual (NP)
            CASE 
                WHEN sr.fecha_penultima_recarga IS NOT NULL AND sr.nivel_tanque_previo_actual IS NOT NULL THEN
                    (COALESCE(sr.nivel_tanque_previo_penultima, 0) + COALESCE(sr.cantidad_penultima_recarga, 0)) -- Punto partida anterior
                    - COALESCE((
                        SELECT SUM(cd.consumo) 
                        FROM consumo_desglosado cd 
                        WHERE cd.id_catalogo_impresoras = sr.id_catalogo_impresora 
                        AND cd.color = sr.color 
                        AND cd.fecha_orden > sr.fecha_penultima_recarga
                        AND cd.fecha_orden <= sr.fecha_ultima_recarga
                    ), 0) -- Consumo en ese rango
                    - sr.nivel_tanque_previo_actual -- Lo que realmente había al final
                ELSE 0 
            END AS desperdicio_ciclo_pasado_ml
        FROM
            stats_recargas sr
        JOIN
            catalogo_impresoras ci ON ci._id = sr.id_catalogo_impresora
        LEFT JOIN
            consumo_historico ch ON sr.id_catalogo_impresora = ch.id_catalogo_impresoras AND sr.color = ch.color
        $whereClause
        ORDER BY
            impresora,
            sr.color;
        SQL;
      $data = $localConnection->goQuery($sql);

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
      $localConnection->beginTransaction();
      // Verificar si la impresora existe
      $check_sql = 'SELECT _id FROM catalogo_impresoras WHERE _id = ?';
      $existing = $localConnection->goQuery($check_sql, [$id_impresora]);
      if (!$existing) {
        $response->getBody()->write(json_encode(['error' => 'La impresora con el ID proporcionado no existe.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);  // Not Found
      }

      // Mapear notes a notas si viene del frontend
      if (isset($data['notes']) && !isset($data['notas'])) {
        $data['notas'] = $data['notes'];
      }

      // Construir la consulta de actualización dinámicamente
      $fields = [];
      $params = [];
      $allowed_fields = ['codigo_interno', 'marca', 'modelo', 'ubicacion', 'tipo_tecnologia', 'id_catalogo_tintas', 'capacidad_contenedor', 'estado', 'notas', 'ml_tinta_por_metro', 'ingresar_tinta_manual'];

      foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
          $fields[] = "{$key} = ?";
          if ($value === 'null' || $value === '') {
            $params[] = null;
          } else {
            $params[] = $value;
          }
        }
      }

      // Ejecutar actualización de datos básicos si hay campos
      if (!empty($fields)) {
        $sql = 'UPDATE catalogo_impresoras SET ' . implode(', ', $fields) . ' WHERE _id = ?';
        $params[] = $id_impresora;
        $localConnection->goQuery($sql, $params);
      }

      // Actualizar canales asociados si se envían
      if (isset($data['canales'])) {
        $localConnection->goQuery('DELETE FROM impresoras_colores WHERE id_catalogo_impresora = ?', [$id_impresora]);
        $canales = is_array($data['canales']) ? $data['canales'] : explode(',', $data['canales']);
        foreach ($canales as $id_color) {
          $id_color = intval(trim($id_color));
          if ($id_color > 0) {
            $sqlInsertColor = DB_DRIVER === 'pgsql'
              ? 'INSERT INTO impresoras_colores (id_catalogo_impresora, id_color_tinta) VALUES (?, ?) ON CONFLICT (id_catalogo_impresora, id_color_tinta) DO NOTHING'
              : 'INSERT IGNORE INTO impresoras_colores (id_catalogo_impresora, id_color_tinta) VALUES (?, ?)';
            $localConnection->goQuery($sqlInsertColor, [$id_impresora, $id_color]);
          }
        }
      }

      $localConnection->commit();
      $response->getBody()->write(json_encode(['message' => 'Impresora actualizada exitosamente.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if ($localConnection && $localConnection->inTransaction()) {
        $localConnection->rollback();
      }
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
      $id_color_tinta = $data['id_color_tinta'] ?? null;
      if (empty($id_color_tinta) && !empty($data['color'])) {
        $colorRow = $localConnection->goQuery('SELECT _id FROM catalogo_colores_tintas WHERE codigo = ? OR nombre = ? LIMIT 1', [$data['color'], $data['color']]);
        if (!empty($colorRow)) {
          $id_color_tinta = intval($colorRow[0]['_id']);
        }
      }

      // Validación básica
      if (empty($data['id_impresora']) || empty($data['id_insumo']) || empty($id_color_tinta) || empty($data['mililitros'])) {
        $response->getBody()->write(json_encode(['error' => 'Faltan campos obligatorios o el color de tinta no es válido.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      // PREPARAR FECHA
      $myDate = new CustomTime();
      $now = $myDate->today();

      // Validar stock ANTES de escribir nada: el frontend ya bloquea esto,
      // pero es una validación de cliente -- sin este chequeo en el backend,
      // una llamada directa a la API (o un bug futuro del frontend) podía
      // dejar 'inventario.cantidad' en negativo sin ningún aviso.
      $sql_get_cantidad = 'SELECT cantidad FROM inventario WHERE _id = ?';
      $current_cantidad_result = $localConnection->goQuery($sql_get_cantidad, [$data['id_insumo']]);

      if (!is_array($current_cantidad_result) || empty($current_cantidad_result) || !isset($current_cantidad_result[0]['cantidad'])) {
        $response->getBody()->write(json_encode(['error' => 'Insumo no encontrado o cantidad no disponible en inventario.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
      }

      $current_cantidad = (float) $current_cantidad_result[0]['cantidad'];
      $mililitros_a_restar = (float) $data['mililitros'];

      if ($mililitros_a_restar > $current_cantidad) {
        $response->getBody()->write(json_encode([
          'error' => "La cantidad a recargar ({$mililitros_a_restar} ml) excede el stock disponible del insumo ({$current_cantidad} ml).",
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
      }

      // Atomicidad FK: recarga (tintas_recargas + UPDATE inventario) en una transacción
      $localConnection->beginTransaction();

      $sql = 'INSERT INTO tintas_recargas (id_catalogo_impresora, id_insumo, id_color_tinta, cantidad, nivel_tanque_previo, fecha_recarga) VALUES (?, ?, ?, ?, ?, ?)';

      $params = [
        $data['id_impresora'],
        $data['id_insumo'],
        $id_color_tinta,
        $data['mililitros'],
        $data['nivel_tanque_previo'] ?? null,
        $now
      ];

      $localConnection->goQuery($sql, $params);
      $new_id = $localConnection->getLastID();

      $new_cantidad = $current_cantidad - $mililitros_a_restar;
      $sql_update_inventario = 'UPDATE inventario SET cantidad = ? WHERE _id = ?';
      $localConnection->goQuery($sql_update_inventario, [$new_cantidad, $data['id_insumo']]);

      // Auto-calibración de ml_tinta_por_metro, POR COLOR, a partir de datos
      // 100% reales (niveles de tanque medidos en 2 recargas consecutivas del
      // MISMO color -- nunca de lo que el empleado haya tipeado a mano, para
      // que también funcione con impresoras que nunca dan ese dato). Si esta
      // es la primera recarga registrada de este color, no hay ciclo cerrado
      // que medir todavía y no se hace nada.
      $sql_recarga_anterior = 'SELECT cantidad, nivel_tanque_previo, fecha_recarga FROM tintas_recargas WHERE id_catalogo_impresora = ? AND id_color_tinta = ? AND _id != ? ORDER BY fecha_recarga DESC LIMIT 1';
      $recargaAnteriorRes = $localConnection->goQuery($sql_recarga_anterior, [$data['id_impresora'], $id_color_tinta, $new_id]);

      if (!empty($recargaAnteriorRes)) {
        $recargaAnterior = $recargaAnteriorRes[0];
        $nivelPrevioAnterior = (float) $recargaAnterior['nivel_tanque_previo'];
        $cantidadAnterior = (float) $recargaAnterior['cantidad'];
        $nivelPrevioNuevo = (float) ($data['nivel_tanque_previo'] ?? 0);
        // Nivel justo después de la recarga anterior, menos nivel justo antes
        // de esta recarga = todo lo que salió del tanque en el ciclo (consumo
        // real de impresión + desperdicio, sin distinguir uno de otro).
        $consumoRealMl = ($nivelPrevioAnterior + $cantidadAnterior) - $nivelPrevioNuevo;

        if ($consumoRealMl > 0) {
          $fechaInicioCiclo = $recargaAnterior['fecha_recarga'];
          $fechaFinCiclo = $now;

          // Qué órdenes se imprimieron en ESTA impresora durante el ciclo --
          // sin filtrar por es_estimado: solo sirve para saber qué orden pasó
          // por qué impresora, no para el valor de ml (ese siempre sale de
          // arriba, de los niveles de tanque), así que funciona igual si la
          // impresora estuvo en modo automático durante el ciclo.
          $sql_ordenes_ciclo = 'SELECT DISTINCT id_orden FROM tintas WHERE id_catalogo_impresoras = ? AND moment >= ? AND moment < ? AND id_orden IS NOT NULL';
          $ordenesCicloRes = $localConnection->goQuery($sql_ordenes_ciclo, [$data['id_impresora'], $fechaInicioCiclo, $fechaFinCiclo]);
          $idsOrdenesCiclo = array_map(function ($row) {
            return intval($row['id_orden']);
          }, $ordenesCicloRes ?: []);

          if (!empty($idsOrdenesCiclo)) {
            $idsOrdenesStr = implode(',', $idsOrdenesCiclo);
            $likeOperator = DB_DRIVER === 'pgsql' ? 'ILIKE' : 'LIKE';
            $sql_metros_ciclo = "SELECT COALESCE(SUM((im.valor_final - im.valor_inicial) *
                                        CASE WHEN i.unidad = 'Kg' AND i.rendimiento IS NOT NULL THEN i.rendimiento ELSE 1 END), 0) AS metros
                                  FROM inventario_movimientos im
                                  JOIN inventario i ON im.id_insumo = i._id
                                  JOIN departamentos d ON d._id = im.id_departamento
                                  WHERE im.id_orden IN ({$idsOrdenesStr})
                                    AND d.tipo = 'impresion'
                                    AND (im.valor_final - im.valor_inicial) > 0
                                    AND (i.tipo_insumo IN ('papel', 'tela') OR i.insumo {$likeOperator} '%papel%' OR i.unidad = 'Mts')";
            $metrosRes = $localConnection->goQuery($sql_metros_ciclo);
            $metrosCiclo = floatval($metrosRes[0]['metros'] ?? 0);

            // Umbral mínimo para no calibrar con una sola pieza atípica.
            $UMBRAL_MINIMO_METROS_CALIBRACION = 2;
            if ($metrosCiclo >= $UMBRAL_MINIMO_METROS_CALIBRACION) {
              $nuevoRatioColor = round($consumoRealMl / $metrosCiclo, 2);
              if (DB_DRIVER === 'pgsql') {
                $sql_upsert_calib = 'INSERT INTO tintas_calibracion_colores (id_catalogo_impresora, id_color_tinta, ml_por_metro) VALUES (?, ?, ?)
                                      ON CONFLICT (id_catalogo_impresora, id_color_tinta) DO UPDATE SET ml_por_metro = EXCLUDED.ml_por_metro, moment = CURRENT_TIMESTAMP';
              } else {
                $sql_upsert_calib = 'INSERT INTO tintas_calibracion_colores (id_catalogo_impresora, id_color_tinta, ml_por_metro) VALUES (?, ?, ?)
                                      ON DUPLICATE KEY UPDATE ml_por_metro = VALUES(ml_por_metro), moment = CURRENT_TIMESTAMP';
              }
              $localConnection->goQuery($sql_upsert_calib, [$data['id_impresora'], $id_color_tinta, $nuevoRatioColor]);
            }
          }
        }
      }

      $localConnection->commit();
      $response->getBody()->write(json_encode(['message' => 'Recarga de tinta registrada exitosamente y cantidad de insumo actualizada.', 'id' => $new_id]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (Exception $e) {
      if ($localConnection && $localConnection->inTransaction()) {
        $localConnection->rollback();
      }
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

      // Soft delete: 'tintas' y 'tintas_recargas' guardan el histórico real de
      // consumo/recarga por impresora, usado por el reporte de costos de
      // producción (reports.php) para calcular el costo de tinta por orden.
      // Un DELETE físico (con ON DELETE SET NULL en esas FK) no rompe nada a
      // nivel de base de datos, pero degrada en silencio la precisión de
      // reportes de costos históricos (pierde el costo real por impresora y
      // cae a un promedio genérico). Se marca 'eliminada' en vez de borrar.
      $sql = "UPDATE catalogo_impresoras SET estado = 'eliminada' WHERE _id = ?";
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

}; // Fin de la función que envuelve las rutas
