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
                                'nivel_tanque_previo', COALESCE(tr.nivel_tanque_previo, 0),
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

      // --- 1. Obtener Consumo Total por Impresora y Color ---
      $sqlConsumo = "
        SELECT id_catalogo_impresoras, 'C' AS color, SUM(c) AS total_consumido FROM tintas GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'M' AS color, SUM(m) AS total_consumido FROM tintas GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'Y' AS color, SUM(y) AS total_consumido FROM tintas GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'K' AS color, SUM(k) AS total_consumido FROM tintas GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'W' AS color, SUM(w) AS total_consumido FROM tintas GROUP BY id_catalogo_impresoras, color
      ";
      // Nota: Eliminamos 'color' de GROUP BY en la query anterior ya que los campos c,m,y,k,w ya desglosan por color.
      // Corregimos la query:
      $sqlConsumo = "
        SELECT id_catalogo_impresoras, 'C' AS color, SUM(c) AS total_consumido FROM tintas WHERE c > 0 GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'M' AS color, SUM(m) AS total_consumido FROM tintas WHERE m > 0 GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'Y' AS color, SUM(y) AS total_consumido FROM tintas WHERE y > 0 GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'K' AS color, SUM(k) AS total_consumido FROM tintas WHERE k > 0 GROUP BY id_catalogo_impresoras, color
        UNION ALL
        SELECT id_catalogo_impresoras, 'W' AS color, SUM(w) AS total_consumido FROM tintas WHERE w > 0 GROUP BY id_catalogo_impresoras, color
      ";
      $consumos = $localConnection->goQuery($sqlConsumo);
      $consumoMap = [];
      if (is_array($consumos)) {
        foreach ($consumos as $c) {
          $key = $c['id_catalogo_impresoras'] . '_' . $c['color'];
          $consumoMap[$key] = floatval($c['total_consumido']);
        }
      }

      // --- 2. Obtener Recargas Totales por Impresora y Color ---
      $sqlTotalRecargas = "SELECT id_catalogo_impresora, color, SUM(cantidad) AS total_recargado FROM tintas_recargas GROUP BY id_catalogo_impresora, color";
      $recargasTotales = $localConnection->goQuery($sqlTotalRecargas);
      $recargaMap = [];
      if (is_array($recargasTotales)) {
        foreach ($recargasTotales as $r) {
          $key = $r['id_catalogo_impresora'] . '_' . $r['color'];
          $recargaMap[$key] = floatval($r['total_recargado']);
        }
      }

      // Decodificar el JSON de tintas_recargas para cada fila y enriquecer con datos de consumo
      foreach ($data as &$row) {
        $row['tintas_recargas'] = json_decode($row['tintas_recargas'], true);
        // Si no hay recargas, json_decode puede devolver null o un array con un solo null. Aseguramos un array vacío.
        if ($row['tintas_recargas'] === null || (is_array($row['tintas_recargas']) && count($row['tintas_recargas']) == 1 && $row['tintas_recargas'][0] === null)) {
          $row['tintas_recargas'] = [];
        }

        // Enriquecer cada registro de recarga
        foreach ($row['tintas_recargas'] as &$recarga) {
          if ($recarga === null) continue;
          $key = $row['_id'] . '_' . $recarga['color'];
          $recarga['consumido_color'] = $consumoMap[$key] ?? 0;
          $recarga['total_recargado_color'] = $recargaMap[$key] ?? 0;
          $recarga['restante_color'] = $recarga['total_recargado_color'] - $recarga['consumido_color'];
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
        -- PASO 3: Calcular métricas históricas y detalles de la última recarga.
        -- =======================================================================================
        stats_recargas AS (
            SELECT
                tr.id_catalogo_impresora,
                tr.color,
                SUM(tr.cantidad) AS total_recargado_historico_ml,
                MAX(tr.fecha_recarga) AS fecha_ultima_recarga,
                -- Obtenemos la cantidad de la última recarga y el nivel previo
                (SELECT tr2.cantidad FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.color = tr.color ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1) AS ultima_cantidad_recargada_ml,
                (SELECT tr2.nivel_tanque_previo FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.color = tr.color ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1) AS nivel_tanque_previo_actual,
                -- Obtenemos los datos de la PENÚLTIMA recarga para calcular el desperdicio del ciclo que cerró
                (SELECT tr2.fecha_recarga FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.color = tr.color ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS fecha_penultima_recarga,
                (SELECT tr2.cantidad FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.color = tr.color ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS cantidad_penultima_recarga,
                (SELECT tr2.nivel_tanque_previo FROM tintas_recargas tr2 WHERE tr2.id_catalogo_impresora = tr.id_catalogo_impresora AND tr2.color = tr.color ORDER BY tr2.fecha_recarga DESC, tr2._id DESC LIMIT 1 OFFSET 1) AS nivel_tanque_previo_penultima
            FROM
                tintas_recargas tr
            GROUP BY
                tr.id_catalogo_impresora,
                tr.color
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
        ORDER BY
            impresora,
            sr.color;
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

      $sql = 'INSERT INTO tintas_recargas (id_catalogo_impresora, id_insumo, color, cantidad, nivel_tanque_previo, fecha_recarga) VALUES (?, ?, ?, ?, ?, ?)';

      $params = [
        $data['id_impresora'],
        $data['id_insumo'],
        $data['color'],
        $data['mililitros'],
        $data['nivel_tanque_previo'] ?? null,
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

}; // Fin de la función que envuelve las rutas
