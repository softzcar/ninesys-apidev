<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /**
   * =================================================================
   * ENDPOINTS PARA GESTIÓN DE LOTES DE FABRICACIÓN (CORREGIDO)
   * =================================================================
   */

  /**
   * POST /lotes
   * Crea un nuevo lote de producción.
   */
  $app->post('/lotes', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id_empleado = $data['id_empleado'] ?? null;
    $id_departamento = $data['id_departamento'] ?? null;
    $ordenes_str = $data['ordenes'] ?? '';

    if (empty($id_empleado) || empty($id_departamento) || empty($ordenes_str)) {
      $error_response = ['error' => 'Faltan parámetros requeridos: id_empleado, id_departamento y ordenes son obligatorios.'];
      $response->getBody()->write(json_encode($error_response));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    $object = [];
    $myDate = new CustomTime();
    $now = $myDate->today();

    // MODIFICADO: Se usa id_departamento_creador y id_departamento_actual
    $sql_create_lote = "INSERT INTO empleados_lotes_fabricacion (id_empleado, id_departamento_creador, id_departamento_actual, estado, fecha_inicio) VALUES (?, ?, ?, 'pendiente', ?)";
    $params_create = [$id_empleado, $id_departamento, $id_departamento, $now];
    $creation_response = $localConnection->goQuery($sql_create_lote, $params_create);

    $id_lote = $creation_response['insert_id'] ?? null;

    if (empty($id_lote)) {
      $object['error'] = 'No se pudo crear el lote o no se pudo obtener su ID.';
      $response->getBody()->write(json_encode($object));
      $localConnection->disconnect();
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $ordenes_ids = explode(',', $ordenes_str);
    foreach ($ordenes_ids as $id_orden) {
      $trimmed_id_orden = trim($id_orden);
      if (is_numeric($trimmed_id_orden) && !empty($trimmed_id_orden)) {
        $localConnection->goQuery('INSERT INTO empleados_lotes_fabricacion_items (id_lote, id_orden) VALUES (?, ?)', [$id_lote, $trimmed_id_orden]);
      }
    }

    $object['message'] = 'Lote creado exitosamente.';
    $object['id_lote'] = $id_lote;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
  });

  /**
   * POST /lotes/{id}/iniciar
   * Inicia el procesamiento de un lote de fabricación.
   * Actualiza el estado del lote y de las tareas de cada orden a "en_curso".
   */
  $app->post('/lotes/{id}/iniciar', function (Request $request, Response $response, array $args) {
    // Obtener el ID del lote de la URL
    $id_lote = intval($args['id']);
    $localConnection = new LocalDB();
    $debug_info = [];  // Array para la depuración

    try {
      // --- INICIO DE LA CORRECCIÓN ---

      // 1. OBTENER EL DEPARTAMENTO ACTUAL DEL LOTE
      $sql_get_lote_depto = 'SELECT id_departamento_actual FROM empleados_lotes_fabricacion WHERE _id = ?';
      $lote_info = $localConnection->goQuery($sql_get_lote_depto, [$id_lote]);

      if (empty($lote_info) || !isset($lote_info[0]['id_departamento_actual'])) {
        throw new Exception('No se pudo encontrar el lote o su departamento actual.');
      }
      $id_departamento_actual = $lote_info[0]['id_departamento_actual'];
      $debug_info['departamento_actual_del_lote'] = $id_departamento_actual;

      // --- FIN DE LA CORRECCIÓN ---

      // 2. Actualizar el estado del lote principal a 'en_curso' y registrar la fecha de inicio
      $sql_update_lote = "UPDATE empleados_lotes_fabricacion SET estado = 'en_curso', fecha_inicio = NOW() WHERE _id = ? AND estado = 'pendiente'";
      $update_result = $localConnection->goQuery($sql_update_lote, [$id_lote]);

      // Guardar información de depuración
      $debug_info['update_lote_sql'] = $sql_update_lote;
      $debug_info['update_lote_result'] = $update_result;

      // 3. Obtener todas las órdenes que pertenecen a este lote
      $sql_get_ordenes = 'SELECT id_orden FROM empleados_lotes_fabricacion_items WHERE id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_get_ordenes, [$id_lote]);

      if (!empty($ordenes_del_lote)) {
        // 4. Si hay órdenes en el lote, iniciar cada una de sus tareas de empleado
        $sql_iniciar_tareas = '';
        foreach ($ordenes_del_lote as $orden) {
          $id_orden_actual = $orden['id_orden'];

          // --- INICIO DE LA CORRECCIÓN ---
          // Actualizar el progreso a 'en curso' y registrar la fecha de inicio SOLO para las tareas del departamento actual.
          $sql_iniciar_tareas .= "UPDATE lotes_detalles_empleados_asignados SET fecha_inicio = NOW(), progreso = 'en curso'
                        WHERE id_orden = {$id_orden_actual} AND id_departamento = {$id_departamento_actual};";
          // --- FIN DE LA CORRECCIÓN ---
        }

        // Ejecutar las consultas de actualización en un solo lote para mayor eficiencia
        if (!empty($sql_iniciar_tareas)) {
          $debug_info['iniciar_tareas_sql'] = $sql_iniciar_tareas;
          $localConnection->goQuery($sql_iniciar_tareas);
        }
      }

      $localConnection->disconnect();

      // 5. Construir la respuesta final
      $final_response = [
        'status' => 'success',
        'message' => "Lote {$id_lote} y sus " . count($ordenes_del_lote) . " órdenes han sido iniciados en el departamento #{$id_departamento_actual}.",
        'debug' => $debug_info
      ];

      $response->getBody()->write(json_encode($final_response));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if ($localConnection) {
        $localConnection->disconnect();
      }
      $error_response = [
        'error' => 'Error al iniciar el lote: ' . $e->getMessage(),
        'debug' => $debug_info
      ];
      $response->getBody()->write(json_encode($error_response));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  /**
   * POST /lotes/{id}/terminar
   * Termina todas las órdenes contenidas en un lote de fabricación.
   */
  $app->post('/lotes/{id}/terminar', function (Request $request, Response $response, array $args) {
    $id_lote = intval($args['id']);
    $localConnection = new LocalDB();

    try {
      // 1. Actualizar el estado del lote a 'terminado'
      $sql_update_lote = "UPDATE empleados_lotes_fabricacion SET estado = 'terminado', fecha_terminado = NOW() WHERE _id = ? AND estado = 'pendiente'";
      $localConnection->goQuery($sql_update_lote, [$id_lote]);

      // 2. Obtener todas las órdenes del lote
      $sql_get_ordenes = 'SELECT id_orden FROM empleados_lotes_fabricacion_items WHERE id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_get_ordenes, [$id_lote]);

      if (empty($ordenes_del_lote)) {
        throw new Exception("No se encontraron órdenes para el lote {$id_lote}.");
      }

      // 3. Terminar cada orden individualmente (en la tabla de asignaciones)
      $sql_terminar_orden = "UPDATE lotes_detalles_empleados_asignados SET fecha_terminado = NOW(), progreso = 'terminado' WHERE id_orden = ? AND progreso = 'en curso'";

      foreach ($ordenes_del_lote as $orden) {
        $localConnection->goQuery($sql_terminar_orden, [$orden['id_orden']]);
      }

      $response->getBody()->write(json_encode([
        'status' => 'success',
        'message' => "Lote {$id_lote} y sus " . count($ordenes_del_lote) . ' órdenes han sido terminados.'
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log("Error al terminar lote {$id_lote}: " . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al terminar el lote.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });
  // Bloque de truncado de tablas eliminado por error de sintaxis. Se dejó como comentario.
/*
            -- TRUNCATE `products`;
            -- TRUNCATE `products_attributes`;
            -- TRUNCATE `products_attributes_values`;
            -- TRUNCATE `products_comisiones`;
            -- TRUNCATE `products_prices`;
            -- TRUNCATE `products_sizes_eficiencia`;
            -- TRUNCATE `products_tiempos_de_produccion`;
            -- TRUNCATE `products_insumos_asignados`;
            TRUNCATE `rendimiento`;
            TRUNCATE `reposiciones`;
            TRUNCATE `retiros`;
            TRUNCATE `revisiones`;
            -- TRUNCATE `sizes`;
            TRUNCATE `tintas`;
            TRUNCATE `tintas_recargas`;
            TRUNCATE `tinta_filtro`;
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
*/


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

    // Sanitizar valores booleanos y nulos que vienen como strings
    if (isset($miEmpleado['es_reposicion'])) {
      $miEmpleado['es_reposicion'] = filter_var($miEmpleado['es_reposicion'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($miEmpleado['id_reposicion']) && $miEmpleado['id_reposicion'] === 'null') {
      $miEmpleado['id_reposicion'] = null;
    }
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

      // CRITICAL FIX: Only update the global order step if it's NOT a reposition.
      // Repositions are partial and shouldn't move the entire order backward.
      if (!isset($miEmpleado['es_reposicion']) || !$miEmpleado['es_reposicion']) {
        $sqlUpdateLote = "UPDATE lotes SET paso = '{$miEmpleado['departamento']}', id_departamento_actual = {$miEmpleado['id_departamento']}  WHERE id_orden = " . $miEmpleado['id_orden'] . ";";
        $localConnection->goQuery($sqlUpdateLote);
        $object['sql_update_lote'] = $sqlUpdateLote;
      }


      // Reposition logic: specific tracking with id_reposicion
      if (isset($miEmpleado['es_reposicion']) && $miEmpleado['es_reposicion'] && isset($miEmpleado['id_reposicion']) && $miEmpleado['id_reposicion'] !== null) {
        $id_repo = $miEmpleado['id_reposicion'];
        // Check if exists specific record
        $check = $localConnection->goQuery("SELECT _id FROM lotes_detalles_empleados_asignados WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_reposicion = {$id_repo}");

        if (!empty($check) && isset($check[0])) {
          $sqlUpdateTracking = "UPDATE lotes_detalles_empleados_asignados SET `progreso` = 'en curso', `fecha_inicio` = '{$now}' WHERE _id = {$check[0]['_id']};";
          $localConnection->goQuery($sqlUpdateTracking);
        } else {
          $sqlInsertTracking = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_empleado, id_departamento, progreso, fecha_inicio, procentaje_comision, id_reposicion) VALUES ({$miEmpleado['id_orden']}, {$miEmpleado['id_empleado']}, {$miEmpleado['id_departamento']}, 'en curso', '{$now}', 0, {$id_repo});";
          $localConnection->goQuery($sqlInsertTracking);
        }
      } else {
        // Regular Order logic: original behavior
        $sqlUpdateTracking = "UPDATE lotes_detalles_empleados_asignados SET `progreso` = 'en curso', `fecha_inicio` = '{$now}' WHERE id_orden = " . $miEmpleado['id_orden'] . " AND id_empleado = " . $miEmpleado['id_empleado'] . " AND id_departamento = " . $miEmpleado['id_departamento'] . ";";
        $localConnection->goQuery($sqlUpdateTracking);
      }
      // Actualizar status de la orden a 'activa' al iniciar primer paso
      $sqlUpdateOrden = "UPDATE ordenes SET `status` = 'activa' WHERE _id = " . $miEmpleado['id_orden'] . " AND `status` = 'En espera';";
      $response_update = $localConnection->goQuery($sqlUpdateOrden);
      $object['response_update_lotes'] = $response_update;
    }

    if ($miEmpleado['tipo'] === 'fin') {
      // Procesar REposición
      if ($miEmpleado['es_reposicion']) {
        // Check for intermediate departments
        $repoId = $miEmpleado['id_reposicion'];
        $repoInfo = $localConnection->goQuery("SELECT id_departamento_solicitante FROM reposiciones WHERE _id = {$repoId}");

        $passToNext = false;

        if (!empty($repoInfo)) {
          $solicitorId = $repoInfo[0]['id_departamento_solicitante'];

          // Get OP for Current and Solicitor
          $sqlOps = "SELECT _id, orden_proceso FROM departamentos WHERE _id IN ({$miEmpleado['id_departamento']}, {$solicitorId})";
          $opsData = $localConnection->goQuery($sqlOps);

          $currentOp = 0;
          $solicitorOp = 0;
          foreach ($opsData as $op) {
            if ($op['_id'] == $miEmpleado['id_departamento'])
              $currentOp = $op['orden_proceso'];
            if ($op['_id'] == $solicitorId)
              $solicitorOp = $op['orden_proceso'];
          }

          // Find Next Department
          // Logic: Next department must be > current and <= solicitor
          $sqlNext = "SELECT _id, orden_proceso FROM departamentos WHERE orden_proceso > {$currentOp} AND orden_proceso <= {$solicitorOp} ORDER BY orden_proceso ASC LIMIT 1";
          $nextDept = $localConnection->goQuery($sqlNext);

          if (!empty($nextDept)) {
            // MOVE TO NEXT DEPARTMENT
            $passToNext = true;
            $nextDeptId = $nextDept[0]['_id'];

            // Update reposition to next department and clear employee (Pool)
            $sqlRepo = "UPDATE reposiciones SET id_departamento = {$nextDeptId}, id_empleado = NULL WHERE _id = {$repoId};";

            // Finish current tracking
            $sqlRepo .= "UPDATE lotes_detalles_empleados_asignados SET `progreso` = 'terminada', `fecha_terminado` = '{$now}' WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_reposicion = {$repoId};";

            $localConnection->goQuery($sqlRepo);
          }
        }

        if (!$passToNext) {
          // Terminar reposicion (Reached destination)
          $sqlRepo = "UPDATE reposiciones SET terminada = 1 WHERE _id = {$miEmpleado['id_reposicion']};";
          $sqlRepo .= "DELETE FROM `ordenes_fila_reposiciones` WHERE id_reposicion = {$miEmpleado['id_reposicion']};";
          $sqlRepo .= "UPDATE lotes_detalles_empleados_asignados SET `progreso` = 'terminada', `fecha_terminado` = '{$now}' WHERE id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_reposicion = {$miEmpleado['id_reposicion']};";
          $localConnection->goQuery($sqlRepo);
        }
      } else {
        // ONLY execute main order logic if NOT a reposition (or if we want repositions to trigger main logic, but usually not)

        // --- VERIFICAR SI HAY OTROS EMPLEADOS PENDIENTES ---
        // Antes de avanzar el paso de la orden, verificamos si hay otros empleados asignados a este departamento
        // que aún no han terminado su tarea.
        $sqlCheckPending = "SELECT COUNT(*) as pending 
                            FROM lotes_detalles_empleados_asignados 
                            WHERE id_orden = {$miEmpleado['id_orden']} 
                            AND id_departamento = {$miEmpleado['id_departamento']} 
                            AND (progreso != 'terminada' AND progreso != 'terminado')
                            AND id_empleado != {$miEmpleado['id_empleado']}";
        // Eliminamos filtros de reposición para asegurar que contamos tareas principales si estamos en flujo principal
        // Opcional: AND (id_reposicion IS NULL OR id_reposicion = 0)

        $checkResults = $localConnection->goQuery($sqlCheckPending);
        $pendingCount = 0;
        if (!empty($checkResults) && isset($checkResults[0]['pending'])) {
          $pendingCount = intval($checkResults[0]['pending']);
        }

        if ($pendingCount > 0) {
          // Hay otros empleados pendientes. NO avanzamos el lote.
          // Solo se registrará el fin de tarea del empleado actual (más abajo en el código) y su pago.
          $object['msg_extra'] = "Orden no avanzada. Quedan $pendingCount empleados pendientes en este paso.";
          $response_update2 = null;
        } else {
          // Todos han terminado (o este es el último). Avanzamos el paso.

          $current_orden_proceso = intval($miEmpleado['orden_proceso']);

          // LÓGICA CORREGIDA: Buscar el siguiente departamento en la secuencia de producción
          $sqlDep = 'SELECT _id AS id_departamento, departamento, orden_proceso
                     FROM departamentos
                     WHERE asignar_numero_de_paso = 1 AND orden_proceso > ?
                     ORDER BY orden_proceso ASC
                     LIMIT 1';
          $object['sql_select_next_departament'] = $sqlDep;
          $response_departamentos = $localConnection->goQuery($sqlDep, [$current_orden_proceso]);

          // Verificar si existe el departamento, de no ser así indica que es el último paso.
          if (empty($response_departamentos)) {
            // Es el último paso debemos asignar terminado o el paso que viene despues de el último despues de producción
            $sql5 = "UPDATE lotes SET paso = 'terminado', id_departamento_actual = 0 WHERE id_orden = {$miEmpleado['id_orden']}; UPDATE ordenes SET `status` = 'terminada' WHERE _id = {$miEmpleado['id_orden']};";
            // $sql5 .= "UPDATE ordenes SET `status` = 'terminado' WHERE _id = {$miEmpleado['id_orden']};";
          } else {
            // El paso existe, lo actualizamos para el semáforo y progressbar
            $departmentName = $response_departamentos[0]['departamento'] ?? 'terminado';  // Usar el nombre del departamento
            $next_department_id = $response_departamentos[0]['id_departamento'];  // Usar el ID correcto del departamento
            $sql5 = "UPDATE lotes SET paso = '{$departmentName}', id_departamento_actual = {$next_department_id} WHERE id_orden = {$miEmpleado['id_orden']};";
          }
          $response_update2 = $localConnection->goQuery($sql5);
        }
      } // End else !es_reposicion

      // Calculo de comisiones
      $sqlComisionEmpleado = 'SELECT comision, comision_tipo, comision_porcentaje, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miEmpleado['id_empleado'];
      $respComisionEmpleado = $localConnection->goQuery($sqlComisionEmpleado);
      $object['rsp_empleados_comision'] = $respComisionEmpleado;  // Para depuración

      $comisionTipo = 'fija';  // Valor por defecto
      $comisionValue = 0;  // Valor por defecto
      $salarioTipo = ''; // Variable control

      if (!empty($respComisionEmpleado)) {
        $comisionTipo = $respComisionEmpleado[0]['comision_tipo'];
        $salarioTipo = $respComisionEmpleado[0]['salario_tipo'];

        if ($comisionTipo === 'porcentaje') {
          $comisionValue = floatval($respComisionEmpleado[0]['comision_porcentaje']);
        } else {
          $comisionValue = floatval($respComisionEmpleado[0]['comision']);
        }
      }

      $object['comision_tipo'] = $comisionTipo;  // Para depuración

      $piezas = 0;  // Valor por defecto
      $id_lotes_detalles = null;  // Valor por defecto
      $totalComimision = 0;  // Valor por defecto

      // Consulta para obtener datos de comisión y otros datos relacionados
      if ($comisionTipo === 'porcentaje') {
        // Para comisión por porcentaje: calcular basado en precio del producto
        $sql = "SELECT
              a._id AS id_lotes_detalles,
              a.procentaje_comision,
              SUM(c.cantidad) AS total_productos_empleado,
              SUM(c.cantidad * c.precio_unitario * ($comisionValue / 100)) AS total_comision_porcentaje
          FROM
              lotes_detalles_empleados_asignados a
          JOIN
              ordenes_productos c ON c.id_orden = a.id_orden
          JOIN
              products p ON c.id_woo = p._id
          WHERE
              a.id_empleado = {$miEmpleado['id_empleado']} 
              AND a.id_orden = {$miEmpleado['id_orden']} 
              AND a.id_departamento = {$miEmpleado['id_departamento']}
              AND (p.fisico = 1 OR p.fisico IS NULL)
              AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
          GROUP BY
              a._id
          ;
        ";
        $object['sql_comision_porcentaje'] = $sql;
        $respComision = $localConnection->goQuery($sql);

        $piezas = $respComision[0]['total_productos_empleado'];
        $id_lotes_detalles = $respComision[0]['id_lotes_detalles'];
        $comimision = $comisionValue; // El porcentaje
        $totalComimision = $respComision[0]['total_comision_porcentaje'];

        // CORRECCIÓN: Aplicar porcentaje asignado (si trabajan 2 personas al 50%, se paga 50%)
        $porcentajeAsignado = floatval($respComision[0]['procentaje_comision']);
        if ($porcentajeAsignado <= 0)
          $porcentajeAsignado = 100; // Legacy support

        $totalComimision = $totalComimision * ($porcentajeAsignado / 100);

        // EXCEDENTE CORTE: Si es el dpto de Corte y no es reposición, usar cantidad real de inventario_corte
        if (intval($miEmpleado['id_departamento']) === 3 && !$miEmpleado['es_reposicion']) {
          $sqlExcedente = "SELECT IFNULL(SUM(ic.cantidad), 0) AS cantidad_real_cortada FROM inventario_corte ic JOIN ordenes_productos op ON op._id = ic.id_ordenes_productos WHERE ic.id_orden = {$miEmpleado['id_orden']}";
          $rspExcedente = $localConnection->goQuery($sqlExcedente);
          $cantidad_real = floatval($rspExcedente[0]['cantidad_real_cortada'] ?? 0);
          if ($cantidad_real > $piezas) {
            $excedente = $cantidad_real - floatval($piezas);
            // Aplicar porcentaje a la cantidad total
            $piezas = $cantidad_real * ($porcentajeAsignado / 100);
            $totalComimision += ($excedente * $miEmpleado['precio_unitario_promedio'] * ($comisionValue / 100) * ($porcentajeAsignado / 100));
          }
        }

        // CORRECCIÓN: Si el empleado tiene compensación SÓLO SALARIO, no se paga comisión
        if ($salarioTipo === 'Salario') {
          $totalComimision = 0;
        }

        if ($miEmpleado['es_reposicion']) {
          $sqlUnidades = "SELECT unidades FROM reposiciones WHERE _id = {$miEmpleado['id_reposicion']}";
          $piezas = $localConnection->goQuery($sqlUnidades)[0]['unidades'];
        }

        $id_reposicion_val = (isset($miEmpleado['id_reposicion']) && is_numeric($miEmpleado['id_reposicion'])) ? $miEmpleado['id_reposicion'] : '0';

        // CHECK DUPLICATE BEFORE INSERT (Revised to include product ID)
        $sqlCheck = "SELECT _id FROM pagos WHERE id_orden = {$miEmpleado['id_orden']} AND id_reposicion = $id_reposicion_val AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_lotes_detalles = $id_lotes_detalles LIMIT 1";
        $check = $localConnection->goQuery($sqlCheck);

        if (empty($check)) {
          $sql = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $miEmpleado['id_orden'] . ', ' . $id_reposicion_val . ', ' . $miEmpleado['id_departamento'] . ', ' . $comimision . ", '" . $comisionTipo . "', " . $piezas . ', ' . $id_lotes_detalles . ", 'aprobado', " . $totalComimision . ', ' . $miEmpleado['id_empleado'] . ", '" . $miEmpleado['departamento'] . "');";
          $object['sql_pagos'][] = $sql;
          $object['resp_pagos'] = $localConnection->goQuery($sql);
        }
      } elseif ($comisionTipo === 'fija') {
        // Para comisión fija: consulta agrupada para obtener total
        $sql = "SELECT
              a._id AS id_lotes_detalles,
              a.procentaje_comision,
              b.comision AS comision_fija,
              SUM(c.cantidad) AS total_productos_empleado,
              (SUM(c.cantidad) * b.comision) * (IF(a.procentaje_comision > 0, a.procentaje_comision, 100) / 100) AS total_comision_fija
          FROM
              lotes_detalles_empleados_asignados a
          JOIN
              api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
          JOIN
              ordenes_productos c ON c.id_orden = a.id_orden
          JOIN
              products p ON c.id_woo = p._id
          WHERE
              a.id_empleado = {$miEmpleado['id_empleado']} 
              AND a.id_orden = {$miEmpleado['id_orden']} 
              AND a.id_departamento = {$miEmpleado['id_departamento']}
              AND (p.fisico = 1 OR p.fisico IS NULL)
              AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
          GROUP BY
              a._id,
              a.procentaje_comision,
              b.comision,
              b.comision_tipo
          ;
        ";
        $object['sql_comision_fija'] = $sql;
        $respComision = $localConnection->goQuery($sql);

        $piezas = $respComision[0]['total_productos_empleado'];
        $id_lotes_detalles = $respComision[0]['id_lotes_detalles'];
        $comimision = $respComision[0]['comision_fija'];
        $totalComimision = $respComision[0]['total_comision_fija'];

        // EXCEDENTE CORTE: Si es el dpto de Corte y no es reposición, sumar piezas reales de inventario_corte
        if (intval($miEmpleado['id_departamento']) === 3 && !$miEmpleado['es_reposicion']) {
          $sqlExcedente = "SELECT IFNULL(SUM(ic.cantidad), 0) AS cantidad_real_cortada FROM inventario_corte ic JOIN ordenes_productos op ON op._id = ic.id_ordenes_productos WHERE ic.id_orden = {$miEmpleado['id_orden']}";
          $rspExcedente = $localConnection->goQuery($sqlExcedente);
          $cantidad_real = floatval($rspExcedente[0]['cantidad_real_cortada'] ?? 0);
          if ($cantidad_real > $piezas) {
            $excedente = $cantidad_real - floatval($piezas);
            $porcentajeFija = floatval($respComision[0]['procentaje_comision']) > 0 ? floatval($respComision[0]['procentaje_comision']) : 100;
            // Aplicar porcentaje a la cantidad total
            $piezas = $cantidad_real * ($porcentajeFija / 100);
            $totalComimision += ($excedente * floatval($comimision) * ($porcentajeFija / 100));
          }
        }


        // CORRECCIÓN: Si el empleado tiene compensación SÓLO SALARIO, no se paga comisión
        if ($salarioTipo === 'Salario') {
          $totalComimision = 0;
          // Opcional: $comimision = 0; si queremos que se guarde tasa 0
        }

        // GUARDAR PAGO PARA COMISIÓN FIJA
        if ($miEmpleado['es_reposicion']) {
          $sqlUnidades = "SELECT unidades FROM reposiciones WHERE _id = {$miEmpleado['id_reposicion']}";
          $piezas = $localConnection->goQuery($sqlUnidades)[0]['unidades'];
        }

        $id_reposicion_val = isset($miEmpleado['id_reposicion']) ? $miEmpleado['id_reposicion'] : 'NULL';

        // CHECK DUPLICATE BEFORE INSERT (Revised to include product ID)
        $sqlCheck = "SELECT _id FROM pagos WHERE id_orden = {$miEmpleado['id_orden']} AND id_reposicion = $id_reposicion_val AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_lotes_detalles = $id_lotes_detalles LIMIT 1";
        $check = $localConnection->goQuery($sqlCheck);

        if (empty($check)) {
          $sql = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $miEmpleado['id_orden'] . ', ' . $id_reposicion_val . ', ' . $miEmpleado['id_departamento'] . ', ' . $comimision . ", '" . $comisionTipo . "', " . $piezas . ', ' . $id_lotes_detalles . ", 'aprobado', " . $totalComimision . ', ' . $miEmpleado['id_empleado'] . ", '" . $miEmpleado['departamento'] . "');";
          $object['sql_pagos'][] = $sql;
          $object['resp_pagos'] = $localConnection->goQuery($sql);
        }
      } else {
        // Para comisión variable: consulta por producto individual usando comisión por departamento
        // Usamos COALESCE para tomar las piezas de la lotificación (si existen) o de la orden
        $sql = "SELECT
              a._id AS id_lotes_detalles,
              a.procentaje_comision,
              ( IF(a.id_departamento = 3,
                  (SELECT IFNULL(SUM(ic.cantidad), 0) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = c._id),
                  c.cantidad
                ) ) AS cantidad,
              IFNULL(pc.comision, 0) AS comision_producto,
              1 AS factor_empleado, 
              ( IF(a.id_departamento = 3,
                  (SELECT IFNULL(SUM(ic.cantidad), 0) FROM inventario_corte ic WHERE ic.id_orden = a.id_orden AND ic.id_ordenes_productos = c._id),
                  c.cantidad
                ) * IFNULL(pc.comision, 0) ) AS monto_comision_por_producto,
              c.id_woo AS id_producto
          FROM
              lotes_detalles_empleados_asignados a
          JOIN
              api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado
          JOIN
              ordenes_productos c ON c.id_orden = a.id_orden
          JOIN
              products p ON c.id_woo = p._id
          LEFT JOIN
              products_comisiones pc ON pc.id_product = c.id_woo AND pc.id_departamento = a.id_departamento
          WHERE
              a.id_empleado = {$miEmpleado['id_empleado']} 
              AND a.id_orden = {$miEmpleado['id_orden']} 
              AND a.id_departamento = {$miEmpleado['id_departamento']}
              AND (p.fisico = 1 OR p.fisico IS NULL)
              AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
          ;
        ";
        $object['sql_comision_variable'] = $sql;
        $respComision = $localConnection->goQuery($sql);

        // GUARDAR PAGO PARA CADA PRODUCTO EN COMISIÓN VARIABLE (Agrupado por Asignación)
        $montoTotalVariable = 0;
        $piezasTotales = 0;

        // Para multi-asignaciones, id_lotes_detalles de la asignación es NULL.
        // La query retorna a._id (el ID de la asignación) como 'id_lotes_detalles'.
        // Esto es lo que debemos guardar en pagos para poder hacer JOIN después.
        $id_lotes_detalles_principal = !empty($respComision) ? $respComision[0]['id_lotes_detalles'] : 'NULL';
        $comision_referencial = !empty($respComision) ? $respComision[0]['comision_producto'] : 0;

        // Si la query no retornó (sin productos configurados), buscar el _id de la asignación directamente
        if ($id_lotes_detalles_principal === 'NULL') {
          $sqlAsig = "SELECT _id FROM lotes_detalles_empleados_asignados WHERE id_empleado = {$miEmpleado['id_empleado']} AND id_orden = {$miEmpleado['id_orden']} AND id_departamento = {$miEmpleado['id_departamento']} LIMIT 1";
          $resAsig = $localConnection->goQuery($sqlAsig);
          if (!empty($resAsig)) {
            $id_lotes_detalles_principal = $resAsig[0]['_id'];
          }
        }

        foreach ($respComision as $producto) {
          $montoProd = floatval($producto['monto_comision_por_producto']);
          $porc = floatval($producto['procentaje_comision']) > 0 ? floatval($producto['procentaje_comision']) : 100;
          $montoTotalVariable += ($montoProd * ($porc / 100));
          $piezasTotales += (floatval($producto['cantidad']) * ($porc / 100));
        }

        // CORRECCIÓN: Si el empleado tiene compensación SÓLO SALARIO, no se paga comisión
        if ($salarioTipo === 'Salario') {
          $montoTotalVariable = 0;
        }

        $id_reposicion_val = (isset($miEmpleado['id_reposicion']) && is_numeric($miEmpleado['id_reposicion'])) ? $miEmpleado['id_reposicion'] : '0';

        // CHECK DUPLICATE BEFORE INSERT (Revised to be assignment-based)
        $sqlCheck = "SELECT _id FROM pagos WHERE id_orden = {$miEmpleado['id_orden']} AND id_reposicion = $id_reposicion_val AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} LIMIT 1";
        $check = $localConnection->goQuery($sqlCheck);

        if (empty($check)) {
          $sql = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $miEmpleado['id_orden'] . ', ' . $id_reposicion_val . ', ' . $miEmpleado['id_departamento'] . ', ' . $comision_referencial . ", 'variable', " . $piezasTotales . ', ' . $id_lotes_detalles_principal . ", 'aprobado', " . $montoTotalVariable . ', ' . $miEmpleado['id_empleado'] . ", '" . $miEmpleado['departamento'] . "');";
          $object['sql_pagos'][] = $sql;
          $localConnection->goQuery($sql);
        }

        // EXCEDENTE CORTE (Comisión Variable): Para Corte, insertar pago extra por piezas excedentes
        if (intval($miEmpleado['id_departamento']) === 3 && !$miEmpleado['es_reposicion']) {
          $sqlExcVar = "SELECT
              lca.id_ordenes_productos,
              (lca.cantidad_ajustada - lca.cantidad_solicitada) AS excedente,
              IFNULL(pc.comision, 0) AS comision_producto,
              a._id AS id_lotes_detalles,
              a.procentaje_comision
            FROM lotes_corte_ajustes lca
            JOIN lotes_detalles_empleados_asignados a ON a.id_orden = lca.id_orden AND a.id_empleado = {$miEmpleado['id_empleado']} AND a.id_departamento = {$miEmpleado['id_departamento']}
            LEFT JOIN products_comisiones pc ON pc.id_product = (SELECT id_woo FROM ordenes_productos WHERE _id = lca.id_ordenes_productos LIMIT 1) AND pc.id_departamento = {$miEmpleado['id_departamento']}
            WHERE lca.id_orden = {$miEmpleado['id_orden']}
              AND (lca.cantidad_ajustada - lca.cantidad_solicitada) > 0
          ";
          $rspExcVar = $localConnection->goQuery($sqlExcVar);
          foreach ($rspExcVar as $excProd) {
            $excedente_piezas = floatval($excProd['excedente']);
            $comision_exc = floatval($excProd['comision_producto']);
            $porc_exc = floatval($excProd['procentaje_comision']) > 0 ? floatval($excProd['procentaje_comision']) : 100;
            $monto_exc = ($excedente_piezas * $comision_exc) * ($porc_exc / 100);
            if ($salarioTipo === 'Salario')
              $monto_exc = 0;

            $id_lotes_exc = $excProd['id_lotes_detalles'];
            $sqlCheckExc = "SELECT _id FROM pagos WHERE id_orden = {$miEmpleado['id_orden']} AND id_reposicion = 0 AND id_empleado = {$miEmpleado['id_empleado']} AND id_departamento = {$miEmpleado['id_departamento']} AND id_lotes_detalles = {$id_lotes_exc} AND detalle = 'Corte-Excedente' LIMIT 1";
            $checkExc = $localConnection->goQuery($sqlCheckExc);
            if (empty($checkExc)) {
              $sqlExcIns = "INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES ({$miEmpleado['id_orden']}, 0, {$miEmpleado['id_departamento']}, {$comision_exc}, 'variable', {$excedente_piezas}, {$id_lotes_exc}, 'aprobado', {$monto_exc}, {$miEmpleado['id_empleado']}, 'Corte-Excedente');";
              $object['sql_pagos_excedente'][] = $sqlExcIns;
              $localConnection->goQuery($sqlExcIns);
            }
          }
        }
      }

      $sqlTerminar = "UPDATE lotes_detalles_empleados_asignados SET 
          fecha_terminado = '{$now}', 
          progreso = 'terminada' 
          WHERE id_orden = {$miEmpleado['id_orden']} 
          AND id_empleado = {$miEmpleado['id_empleado']} 
          AND id_departamento = {$miEmpleado['id_departamento']};";
      $localConnection->goQuery($sqlTerminar);
    } // Cierre if ($miEmpleado['tipo'] === 'fin')

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

    $sql = "SELECT * FROM ordenes";
    $object['lotes'] = $localConnection->goQuery($sql);


    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['$object']));

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
            -- TRUNCATE `catalogo_impresoras`;
            -- TRUNCATE `catalogo_insumos_productos`;
            -- TRUNCATE `catalogo_telas`;
            -- TRUNCATE `categories`;
            TRUNCATE `check_tareas`;
            -- TRUNCATE `config`;
            -- TRUNCATE `customers`;
            -- TRUNCATE `departamentos`;
            TRUNCATE `disenos`;
            TRUNCATE `disenos_ajustes_y_personalizaciones`;
            TRUNCATE `empleados_lotes_fabricacion`;
            TRUNCATE `empleados_lotes_fabricacion_items`;
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
            TRUNCATE `presupuestos_productos`;';

    $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['message' => 'Tablas truncadas correctamente']));
    return $response->withHeader('Content-Type', 'application/json');
  });

  /* CÓDIGO HUÉRFANO ELIMINADO
          $sql = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $miEmpleado['id_orden'] . ', ' . $miEmpleado['id_reposicion'] . ', ' . $miEmpleado['id_departamento'] . ', ' . $comimision . ", '" . $comisionTipo . "', " . $piezas . ', ' . $id_lotes_detalles . ", 'aprobado', " . $totalComimision . ', ' . $miEmpleado['id_empleado'] . ", '" . $miEmpleado['departamento'] . ' - Producto ID: ' . $producto['id_producto'] . "');";
          $object['sql_pagos'][] = $sql;
          $object['resp_pagos'][] = $localConnection->goQuery($sql);
        }
      }

      $campo = 'fecha_terminado';
      $progreso = 'terminada';
    }

    // ACTUALIZAR DATOS DE INICIO DE TAREA
    $sql = 'UPDATE lotes_detalles_empleados_asignados SET ' . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE id_departamento = " . $miEmpleado['id_departamento'] . ' AND id_orden = ' . $miEmpleado['id_orden'];

    $object['sql_update_ld'] = $sql;
    $object['sql_update_lotes_detalles'] = $sql;
    /* $object['items'] = $localConnection->goQuery($sql);

    $sql = "UPDATE lotes_detalles_empleados_asignados SET $campo = '$now', progreso = '$progreso' WHERE id_departamento = {$miEmpleado['id_departamento']} AND id_orden = {$miEmpleado['id_orden']} AND id_empleado = {$miEmpleado['id_empleado']};";
    $object['result_update_lotes_detalles_detalles_empleados'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  }); */

  // REGSTRAR LOTES DE ORDENES DESDE EMPLEADOS

  /**
   * POST /lotes/{id}/finalizar-departamento
   * Finaliza las tareas de un lote de fabricación para un departamento específico,
   * registra los consumos de insumos y gestiona la transición del lote al
   * siguiente departamento o lo finaliza.
   */
  $app->post('/lotes/{id}/finalizar-departamento', function (Request $request, Response $response, array $args) {
    $id_lote = intval($args['id']);
    $json_body = $request->getBody()->getContents();
    $data = json_decode($json_body, true);

    $id_departamento = $data['id_departamento'] ?? null;
    $id_empleado = $data['id_empleado'] ?? null;
    $consumos_lote = $data['consumos_lote'] ?? null;

    if (empty($id_departamento) || empty($id_empleado) || !is_array($consumos_lote)) {
      $response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos o el array consumos_lote es inválido.', 'debug_data_received' => $data]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    try {
      $sql_ordenes_lote = '
          SELECT
              elfi.id_orden,
              (SELECT SUM(op.cantidad) FROM ordenes_productos op WHERE op.id_orden = elfi.id_orden) as unidades_orden
          FROM
              empleados_lotes_fabricacion_items elfi
          WHERE elfi.id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_ordenes_lote, [$id_lote]);

      if (empty($ordenes_del_lote)) {
        throw new Exception("No se encontraron órdenes para el lote {$id_lote}.");
      }

      $gran_total_unidades_lote = array_sum(array_column($ordenes_del_lote, 'unidades_orden'));

      if ($gran_total_unidades_lote <= 0) {
        throw new Exception("El número total de unidades para el lote {$id_lote} es cero, no se puede distribuir el consumo.");
      }

      $now = date('Y-m-d H:i:s');

      if (!empty($consumos_lote)) {
        foreach ($consumos_lote as $consumo) {
          if (empty($consumo['id_insumo']) || !isset($consumo['cantidad_total']))
            continue;

          $id_insumo_actual = intval($consumo['id_insumo']);
          $cantidad_total_consumida = floatval($consumo['cantidad_total']);
          $id_ordenes_especificas = $consumo['id_ordenes'] ?? [];

          $sql_update_inventario = 'UPDATE inventario SET cantidad = cantidad - ? WHERE _id = ?';
          $localConnection->goQuery($sql_update_inventario, [$cantidad_total_consumida, $id_insumo_actual]);

          // Determinar qué órdenes cargarán con este consumo
          if (!empty($id_ordenes_especificas) && is_array($id_ordenes_especificas)) {
            $ordenes_para_distribuir = array_filter($ordenes_del_lote, function ($o) use ($id_ordenes_especificas) {
              return in_array($o['id_orden'], $id_ordenes_especificas);
            });
            $total_unidades_segmento = array_sum(array_column($ordenes_para_distribuir, 'unidades_orden'));
          } else {
            $ordenes_para_distribuir = $ordenes_del_lote;
            $total_unidades_segmento = $gran_total_unidades_lote;
          }

          if ($total_unidades_segmento > 0) {
            foreach ($ordenes_para_distribuir as $order) {
              $id_orden_actual = $order['id_orden'];
              $unidades_orden = intval($order['unidades_orden']);
              $proporcion = $unidades_orden / $total_unidades_segmento;
              $consumo_estimado_orden = $cantidad_total_consumida * $proporcion;

              $sql_movimiento = 'INSERT INTO inventario_movimientos (id_orden, id_empleado, id_insumo, id_departamento, departamento, valor_inicial, valor_final, moment) VALUES (?, ?, ?, ?, (SELECT departamento FROM departamentos WHERE _id = ?), ?, ?, ?)';
              $params_movimiento = [$id_orden_actual, $id_empleado, $id_insumo_actual, $id_departamento, $id_departamento, 0, $consumo_estimado_orden, $now];
              $localConnection->goQuery($sql_movimiento, $params_movimiento);

              // REGISTRO DE DESPERDICIO (Opcional, mayormente para Corte)
              if (isset($consumo['desperdicio_total']) && floatval($consumo['desperdicio_total']) > 0) {
                $desperdicio_total = floatval($consumo['desperdicio_total']);
                $desperdicio_estimado = $desperdicio_total * $proporcion;

                $sql_rendimiento = 'INSERT INTO rendimiento (id_orden, id_empleado_corte, desperdicio, id_insumo, metros) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE desperdicio = desperdicio + VALUES(desperdicio), metros = metros + VALUES(metros);';
                $localConnection->goQuery($sql_rendimiento, [$id_orden_actual, $id_empleado, $desperdicio_estimado, $id_insumo_actual, 0]);
              }
            }
          }

          // LÓGICA DE TERMINAR MATERIAL (REMANENTES)
          if (isset($consumo['terminar_material']) && ($consumo['terminar_material'] === true || $consumo['terminar_material'] === 1)) {
            $res_current = $localConnection->goQuery("SELECT cantidad FROM inventario WHERE _id = ?", [$id_insumo_actual]);
            if (!empty($res_current)) {
              $remanente_qty = floatval($res_current[0]['cantidad']);
              if ($remanente_qty > 0) {
                $sql_rem = "INSERT INTO inventario_remanentes (id_insumo, cantidad, motivo, observacion, id_empleado, fecha) VALUES (?, ?, 'Terminación (Lote)', 'Generado automáticamente desde Lote', ?, NOW())";
                $localConnection->goQuery($sql_rem, [$id_insumo_actual, $remanente_qty, $id_empleado]);
                $localConnection->goQuery("UPDATE inventario SET cantidad = 0 WHERE _id = ?", [$id_insumo_actual]);
              }
            }
          }
        }
      }

      // Procesar consumo de tintas (opcional, para Impresión)
      $consumo_tintas = $data['consumo_tintas'] ?? [];
      if (!empty($consumo_tintas) && is_array($consumo_tintas)) {
        foreach ($consumo_tintas as $tinta) {
          $id_orden_tinta = intval($tinta['id_orden'] ?? 0);
          $id_impresora = intval($tinta['id_impresora'] ?? 0);
          $tinta_c = floatval($tinta['c'] ?? 0);
          $tinta_m = floatval($tinta['m'] ?? 0);
          $tinta_y = floatval($tinta['y'] ?? 0);
          $tinta_k = floatval($tinta['k'] ?? 0);
          $tinta_w = floatval($tinta['w'] ?? 0);

          if ($id_orden_tinta > 0 && $id_impresora > 0) {
            $sql_tinta = 'INSERT INTO tintas (c, m, y, k, w, id_orden, id_empleado, id_catalogo_impresoras, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $localConnection->goQuery($sql_tinta, [
              $tinta_c,
              $tinta_m,
              $tinta_y,
              $tinta_k,
              $tinta_w,
              $id_orden_tinta,
              $id_empleado,
              $id_impresora,
              $now
            ]);
          }
        }
      }

      $sql_dep_info = 'SELECT orden_proceso, departamento FROM departamentos WHERE _id = ?';
      $dep_info = $localConnection->goQuery($sql_dep_info, [$id_departamento]);
      $nombre_departamento = $dep_info[0]['departamento'];
      $orden_proceso_actual = $dep_info[0]['orden_proceso'];

      foreach ($ordenes_del_lote as $order) {
        $id_orden_actual = $order['id_orden'];

        $siguiente_paso_proceso = intval($orden_proceso_actual) + 1;
        $next_dep_info = $localConnection->goQuery('SELECT _id, departamento FROM departamentos WHERE asignar_numero_de_paso > 0 AND orden_proceso = ? LIMIT 1', [$siguiente_paso_proceso]);
        if (empty($next_dep_info)) {
          $localConnection->goQuery("UPDATE lotes SET paso = 'terminado', id_departamento_actual = 0 WHERE id_orden = ?", [$id_orden_actual]);
        } else {
          $localConnection->goQuery('UPDATE lotes SET paso = ?, id_departamento_actual = ? WHERE id_orden = ?', [$next_dep_info[0]['departamento'], $next_dep_info[0]['_id'], $id_orden_actual]);
        }

        // UNIFICACIÓN DE LÓGICA DE PAGO (Replicando registrar-paso-empleado para multi-asignados)

        // 1. Obtener todas las asignaciones para esta orden y departamento
        $sql_asignaciones = "SELECT _id as id_lotes_detalles, id_empleado, procentaje_comision FROM lotes_detalles_empleados_asignados WHERE id_orden = ? AND id_departamento = ?";
        $asignaciones = $localConnection->goQuery($sql_asignaciones, [$id_orden_actual, $id_departamento]);

        // Si no hay nadie asignado (fallback por error en data), auto-asignamos al que hizo click.
        if (empty($asignaciones)) {
          $sql_insert_assign = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_empleado, id_departamento, progreso, fecha_inicio, fecha_terminado, procentaje_comision) VALUES (?, ?, ?, 'terminada', ?, ?, 100)";
          $res_ins = $localConnection->goQuery($sql_insert_assign, [$id_orden_actual, $id_empleado, $id_departamento, $now, $now, 100]);
          $asignaciones = [
            [
              'id_lotes_detalles' => $res_ins['insert_id'],
              'id_empleado' => $id_empleado,
              'procentaje_comision' => 100
            ]
          ];
        }

        foreach ($asignaciones as $asignacion) {
          $id_emp_asignado = $asignacion['id_empleado'];
          $id_lotes_detalles = $asignacion['id_lotes_detalles'];
          $procentaje_comision_asignado = floatval($asignacion['procentaje_comision']);

          // 2. Obtener tipo de comisión del empleado actual del loop
          $sql_comision_emp = 'SELECT comision, comision_tipo, comision_porcentaje, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ?';
          $resp_emp = $localConnection->goQuery($sql_comision_emp, [$id_emp_asignado]);

          $comision_tipo = $resp_emp[0]['comision_tipo'] ?? 'fija';
          $salario_tipo = $resp_emp[0]['salario_tipo'] ?? '';
          $comision_value_emp = ($comision_tipo === 'porcentaje') ? floatval($resp_emp[0]['comision_porcentaje'] ?? 0) : floatval($resp_emp[0]['comision'] ?? 0);

          // 3. Calcular monto según tipo de comisión
          $total_monto_pago = 0;
          $cantidad_piezas = 0;
          $comision_guardar = $comision_value_emp;

          if ($comision_tipo === 'porcentaje') {
            $sql_calc = "SELECT SUM(c.cantidad) as total_piezas, SUM(c.cantidad * c.precio_unitario * ($comision_value_emp / 100)) AS total_monto FROM ordenes_productos c JOIN products p ON c.id_woo = p._id WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_orden_actual]);
            $cantidad_piezas = $res_calc[0]['total_piezas'] ?? 0;
            $total_monto_pago = ($res_calc[0]['total_monto'] ?? 0) * ($procentaje_comision_asignado / 100);
          } elseif ($comision_tipo === 'fija') {
            $sql_calc = "SELECT SUM(c.cantidad) as total_piezas FROM ordenes_productos c JOIN products p ON c.id_woo = p._id WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_orden_actual]);
            $cantidad_piezas = $res_calc[0]['total_piezas'] ?? 0;
            $total_monto_pago = ($cantidad_piezas * $comision_value_emp) * ($procentaje_comision_asignado / 100);
          } else { // Variable (por producto - usando comision departamental)
            $sql_calc = "SELECT c.cantidad, IFNULL(pc.comision, 0) AS com_prod FROM ordenes_productos c JOIN products p ON c.id_woo = p._id LEFT JOIN products_comisiones pc ON pc.id_product = c.id_woo AND pc.id_departamento = ? WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_departamento, $id_orden_actual]);
            $comision_guardar = $res_calc[0]['com_prod'] ?? 0; // Referencia visual de la tabla
            foreach ($res_calc as $prod) {
              $cantidad_piezas += $prod['cantidad'];
              $total_monto_pago += ($prod['cantidad'] * $prod['com_prod']) * ($procentaje_comision_asignado / 100);
            }
          }

          if ($salario_tipo === 'Salario')
            $total_monto_pago = 0;

          // 4. Registrar Pago
          $sql_check_pago = "SELECT _id FROM pagos WHERE id_orden = ? AND id_empleado = ? AND id_departamento = ? AND id_reposicion = 0 LIMIT 1";
          $check_pago = $localConnection->goQuery($sql_check_pago, [$id_orden_actual, $id_emp_asignado, $id_departamento]);

          if (empty($check_pago)) {
            $sql_pago = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (?, 0, ?, ?, ?, ?, ?, "aprobado", ?, ?, ?)';
            $localConnection->goQuery($sql_pago, [$id_orden_actual, $id_departamento, $comision_guardar, $comision_tipo, $cantidad_piezas, $id_lotes_detalles, $total_monto_pago, $id_emp_asignado, $nombre_departamento]);
          }

          $localConnection->goQuery("UPDATE lotes_detalles_empleados_asignados SET fecha_terminado = ?, progreso = 'terminada' WHERE _id = ?", [$now, $id_lotes_detalles]);
        }

        $localConnection->goQuery("UPDATE lotes_detalles SET fecha_terminado = ?, progreso = 'terminada' WHERE id_departamento = ? AND id_orden = ?", [$now, $id_departamento, $id_orden_actual]);
      }

      $localConnection->goQuery("UPDATE empleados_lotes_fabricacion SET estado = 'terminado', fecha_fin = ? WHERE _id = ?", [$now, $id_lote]);

      $response_data = ['status' => 'success', 'message' => "Lote {$id_lote} finalizado en este departamento y transicionado correctamente."];
      $response->getBody()->write(json_encode($response_data, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if ($localConnection) {
        $localConnection->disconnect();
      }
      $response->getBody()->write(json_encode(['error' => 'Error al finalizar el lote: ' . $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  /**
   * POST /lotes/activos
   * Obtiene los lotes activos para un departamento específico.
   */
  $app->post('/lotes/activos', function (Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $id_departamento = $data['id_departamento'] ?? null;

    if (empty($id_departamento)) {
      $response->getBody()->write(json_encode(['error' => 'Falta el parámetro requerido: id_departamento.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    try {
      // MODIFICADO: Se busca por id_departamento_actual y se quita el filtro de id_empleado
      $sql = "SELECT
              elf._id AS id,
              elf.estado,
              elf.fecha_inicio,
              elf.fecha_fin,
              (SELECT nombre FROM api_empresas.empresas_usuarios WHERE id_usuario = elf.id_empleado) AS nombre_empleado_creador,
              (SELECT departamento FROM departamentos WHERE _id = elf.id_departamento_creador) AS nombre_departamento_creador,
              GROUP_CONCAT(
                  JSON_OBJECT(
                      'id_orden', elfi.id_orden,
                      'cliente_nombre', o.cliente_nombre
                  )
              ) AS ordenes
          FROM
              empleados_lotes_fabricacion elf
          JOIN
              empleados_lotes_fabricacion_items elfi ON elf._id = elfi.id_lote
          JOIN
              ordenes o ON elfi.id_orden = o._id
          WHERE
              -- elf.id_departamento_actual > 0 -- Hack para saltar el departamento del empelado y trascender el resultado a los demás departamentos
              elf.id_departamento_creador = {$data['id_departamento']} 
              AND
              elf.id_empleado = {$data['id_empleado']} 
              AND elf.estado IN ('pendiente', 'en_curso')
          GROUP BY
              elf._id, elf.estado, elf.fecha_inicio, elf.fecha_fin
          ORDER BY
              elf.fecha_inicio DESC, elf._id DESC
      ";

      $params = [$id_departamento];
      // $query_result = $localConnection->goQuery($sql, $params);
      $query_result = $localConnection->goQuery($sql);

      foreach ($query_result as &$row) {
        $row['ordenes'] = !empty($row['ordenes']) ? json_decode('[' . $row['ordenes'] . ']', true) : [];
      }

      $response->getBody()->write(json_encode($query_result, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      error_log('Error al obtener lotes activos: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['error' => 'Error interno del servidor al obtener lotes activos.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // FINALIZAR LOTE DE ORDENES DE IMPRESION
  $app->post('/lotes/{id}/finalizar-impresion', function (Request $request, Response $response, array $args) {
    $id_lote = intval($args['id']);

    $json_body = $request->getBody()->getContents();
    $data = json_decode($json_body, true);

    $id_departamento = $data['id_departamento'] ?? null;
    $id_empleado = $data['id_empleado'] ?? null;
    $consumo_papel = $data['consumo_papel'] ?? null;
    $consumo_tintas = $data['consumo_tintas'] ?? null;

    if (empty($id_empleado) || empty($id_departamento) || !is_array($consumo_papel) || empty($consumo_papel) || !is_array($consumo_tintas) || empty($consumo_tintas)) {
      $error_response = json_encode(['error' => 'Payload inválido. Se requieren id_empleado, id_departamento, consumo_papel y consumo_tintas (array).']);
      $response->getBody()->write($error_response);
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

    $localConnection = new LocalDB();

    try {
      // Lógica principal del endpoint... (la misma que antes)
      $sql_ordenes_lote = 'SELECT elfi.id_orden, (SELECT SUM(op.cantidad) FROM ordenes_productos op WHERE op.id_orden = elfi.id_orden) as unidades_orden FROM empleados_lotes_fabricacion_items elfi WHERE elfi.id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_ordenes_lote, [$id_lote]);

      if (empty($ordenes_del_lote)) {
        throw new Exception("No se encontraron órdenes para el lote {$id_lote}.");
      }

      $gran_total_unidades_lote = array_sum(array_column($ordenes_del_lote, 'unidades_orden'));
      if ($gran_total_unidades_lote <= 0) {
        throw new Exception('El número total de unidades para el lote es cero.');
      }

      $now = date('Y-m-d H:i:s');
      $nombre_departamento = 'Impresión';

      // Log para depuración (Archivo explicito)
      $logFile = __DIR__ . '/debug_manufacturing.log';
      $logData = "--------------------------------------------------\n";
      $logData .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
      $logData .= "Lote ID: {$id_lote}\n";
      $logData .= "Consumo Papel Raw: " . print_r($consumo_papel, true) . "\n";
      file_put_contents($logFile, $logData, FILE_APPEND);

      foreach ($consumo_papel as $papel) {
        $id_insumo_papel = intval($papel['id_insumo']);
        $cantidad_total_papel = floatval($papel['cantidad_total']);
        $id_ordenes_especificas = $papel['id_ordenes'] ?? [];

        $logMsg = "Procesando Papel - Insumo ID: {$id_insumo_papel}, Cantidad: {$cantidad_total_papel}\n";
        file_put_contents($logFile, $logMsg, FILE_APPEND);

        if ($cantidad_total_papel > 0) {
          $localConnection->goQuery('UPDATE inventario SET cantidad = cantidad - ? WHERE _id = ?', [$cantidad_total_papel, $id_insumo_papel]);

          // Determinar qué órdenes cargarán con este papel
          if (!empty($id_ordenes_especificas) && is_array($id_ordenes_especificas)) {
            $ordenes_para_distribuir = array_filter($ordenes_del_lote, function ($o) use ($id_ordenes_especificas) {
              return in_array($o['id_orden'], $id_ordenes_especificas);
            });
            $total_unidades_segmento = array_sum(array_column($ordenes_para_distribuir, 'unidades_orden'));
          } else {
            $ordenes_para_distribuir = $ordenes_del_lote;
            $total_unidades_segmento = $gran_total_unidades_lote;
          }

          if ($total_unidades_segmento > 0) {
            foreach ($ordenes_para_distribuir as $order) {
              $proporcion = intval($order['unidades_orden']) / $total_unidades_segmento;
              $consumo_estimado = $cantidad_total_papel * $proporcion;

              if ($consumo_estimado > 0) {
                $sql_movimiento = 'INSERT INTO inventario_movimientos (id_orden, id_empleado, id_insumo, id_departamento, departamento, valor_inicial, valor_final, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
                $localConnection->goQuery($sql_movimiento, [$order['id_orden'], $id_empleado, $id_insumo_papel, $id_departamento, $nombre_departamento, 0, $consumo_estimado, $now]);

                $logMsg = "INSERTADO movimiento para Orden {$order['id_orden']}, Qty: {$consumo_estimado}\n";
                file_put_contents($logFile, $logMsg, FILE_APPEND);
              }
            }
          }
        } else {
          $logMsg = "SKIPPED update/insert because cantidad_total_papel is <= 0\n";
          file_put_contents($logFile, $logMsg, FILE_APPEND);
        }
      }

      // Procesar cada impresora del array
      foreach ($consumo_tintas as $tinta) {
        $id_impresora = intval($tinta['id_impresora']);
        $tinta_c = floatval($tinta['c'] ?? 0);
        $tinta_m = floatval($tinta['m'] ?? 0);
        $tinta_y = floatval($tinta['y'] ?? 0);
        $tinta_k = floatval($tinta['k'] ?? 0);
        $tinta_w = floatval($tinta['w'] ?? 0);

        foreach ($ordenes_del_lote as $order) {
          $proporcion = intval($order['unidades_orden']) / $gran_total_unidades_lote;
          $sql_tinta = 'INSERT INTO tintas (c, m, y, k, w, id_orden, id_empleado, id_catalogo_impresoras, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
          $params_tinta = [
            $tinta_c * $proporcion,
            $tinta_m * $proporcion,
            $tinta_y * $proporcion,
            $tinta_k * $proporcion,
            $tinta_w * $proporcion,
            $order['id_orden'],
            $id_empleado,
            $id_impresora,
            $now
          ];
          $localConnection->goQuery($sql_tinta, $params_tinta);
        }
      }

      $orden_proceso_actual = $localConnection->goQuery('SELECT orden_proceso FROM departamentos WHERE _id = ?', [$id_departamento])[0]['orden_proceso'];

      foreach ($ordenes_del_lote as $order) {
        $id_orden_actual = $order['id_orden'];
        $siguiente_paso_proceso = intval($orden_proceso_actual) + 1;
        $next_dep_info = $localConnection->goQuery('SELECT _id, departamento FROM departamentos WHERE asignar_numero_de_paso > 0 AND orden_proceso = ? LIMIT 1', [$siguiente_paso_proceso]);
        if (empty($next_dep_info)) {
          $localConnection->goQuery("UPDATE lotes SET paso = 'terminado', id_departamento_actual = 0 WHERE id_orden = ?", [$id_orden_actual]);
        } else {
          $localConnection->goQuery('UPDATE lotes SET paso = ?, id_departamento_actual = ? WHERE id_orden = ?', [$next_dep_info[0]['departamento'], $next_dep_info[0]['_id'], $id_orden_actual]);
        }

        $sql_comision_empleado = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ?';
        $resp_comision_empleado = $localConnection->goQuery($sql_comision_empleado, [$id_empleado]);
        $comision_tipo = $resp_comision_empleado[0]['comision_tipo'] ?? 'fija';
        if ($comision_tipo === 'porcentaje') {
          $comision_valor = floatval($resp_comision_empleado[0]['comision_porcentaje'] ?? 0);
        } else {
          $comision_valor = floatval($resp_comision_empleado[0]['comision'] ?? 0);
        }
        $sql_calculo_pago = 'SELECT a._id AS id_lotes_detalles, a.procentaje_comision, ((SUM(c.cantidad) * d.comision) * a.procentaje_comision / 100) AS total_comision_variable, ((SUM(c.cantidad) * eu.comision) * a.procentaje_comision / 100) AS total_comision_fija FROM lotes_detalles_empleados_asignados a JOIN api_empresas.empresas_usuarios eu ON eu.id_usuario = a.id_empleado JOIN ordenes_productos c ON c.id_orden = a.id_orden JOIN products d ON d._id = c.id_woo WHERE a.id_empleado = ? AND a.id_orden = ? AND a.id_departamento = ? AND (d.fisico = 1 OR d.fisico IS NULL) AND (d.es_diseno = 0 OR d.es_diseno IS NULL) GROUP BY a._id, a.procentaje_comision';
        $resp_comision = $localConnection->goQuery($sql_calculo_pago, [$id_empleado, $id_orden_actual, $id_departamento]);
        if (!empty($resp_comision)) {
          $total_comision = ($comision_tipo === 'fija') ? $resp_comision[0]['total_comision_fija'] : $resp_comision[0]['total_comision_variable'];
          $sql_pago = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
          $params_pago = [$id_orden_actual, 0, $id_departamento, $comision_valor, $comision_tipo, intval($order['unidades_orden']), $resp_comision[0]['id_lotes_detalles'], 'aprobado', $total_comision, $id_empleado, $nombre_departamento];
          $localConnection->goQuery($sql_pago, $params_pago);
        }

        $localConnection->goQuery("UPDATE lotes_detalles SET fecha_terminado = ?, progreso = 'terminada' WHERE id_departamento = ? AND id_orden = ?", [$now, $id_departamento, $id_orden_actual]);
        $localConnection->goQuery("UPDATE lotes_detalles_empleados_asignados SET fecha_terminado = ?, progreso = 'terminada' WHERE id_departamento = ? AND id_orden = ? AND id_empleado = ?", [$now, $id_departamento, $id_orden_actual, $id_empleado]);
      }

      $localConnection->goQuery("UPDATE empleados_lotes_fabricacion SET estado = 'terminado', fecha_fin = ? WHERE _id = ?", [$now, $id_lote]);

      $response_data = ['status' => 'success', 'message' => "Lote de Impresión {$id_lote} finalizado y consumos registrados correctamente."];
      $response->getBody()->write(json_encode($response_data, JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    } catch (Exception $e) {
      if ($localConnection) {
        $localConnection->disconnect();
      }
      $error_response = json_encode(['error' => 'Error al finalizar el lote de impresión: ' . $e->getMessage()]);
      $response->getBody()->write($error_response);
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
  });

  // FINALIZAR LOTE DE ORDENES DE CORTE
  $app->post('/lotes/{id}/finalizar-corte', function (Request $request, Response $response, array $args) {
    $id_lote = intval($args['id']);

    $json_body = $request->getBody()->getContents();
    $data = json_decode($json_body, true);

    // 1. Validar payload específico para Corte
    $id_departamento = $data['id_departamento'] ?? null;
    $id_empleado = $data['id_empleado'] ?? null;
    $consumos_lote = $data['consumos_lote'] ?? null;

    if (empty($id_empleado) || empty($id_departamento) || !is_array($consumos_lote) || empty($consumos_lote)) {
      $error_response = json_encode(['error' => 'Payload inválido. Se requieren id_empleado, id_departamento y el array consumos_lote.']);
      $response->getBody()->write($error_response);
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

    $localConnection = new LocalDB();

    try {
      // 2. Obtener órdenes y unidades totales del lote
      $sql_ordenes_lote = 'SELECT elfi.id_orden, (SELECT SUM(op.cantidad) FROM ordenes_productos op WHERE op.id_orden = elfi.id_orden) as unidades_orden FROM empleados_lotes_fabricacion_items elfi WHERE elfi.id_lote = ?';
      $ordenes_del_lote = $localConnection->goQuery($sql_ordenes_lote, [$id_lote]);

      if (empty($ordenes_del_lote)) {
        throw new Exception("No se encontraron órdenes para el lote {$id_lote}.");
      }

      $gran_total_unidades_lote = array_sum(array_column($ordenes_del_lote, 'unidades_orden'));
      if ($gran_total_unidades_lote <= 0) {
        throw new Exception("El número total de unidades para el lote {$id_lote} es cero, no se puede distribuir el consumo.");
      }

      $now = date('Y-m-d H:i:s');
      $nombre_departamento = 'Corte';

      // 3. Bucle externo: Procesar cada insumo consumido y su desperdicio
      foreach ($consumos_lote as $consumo) {
        $id_insumo_actual = intval($consumo['id_insumo']);
        $cantidad_total_consumida = floatval($consumo['cantidad_total']);
        $desperdicio_total = floatval($consumo['desperdicio_total']);
        $id_ordenes_especificas = $consumo['id_ordenes'] ?? [];

        // 3a. Actualizar stock del insumo
        $localConnection->goQuery('UPDATE inventario SET cantidad = cantidad - ? WHERE _id = ?', [$cantidad_total_consumida, $id_insumo_actual]);

        // Determinar qué órdenes cargarán con este consumo y desperdicio
        if (!empty($id_ordenes_especificas) && is_array($id_ordenes_especificas)) {
          $ordenes_para_distribuir = array_filter($ordenes_del_lote, function ($o) use ($id_ordenes_especificas) {
            return in_array($o['id_orden'], $id_ordenes_especificas);
          });
          $total_unidades_segmento = array_sum(array_column($ordenes_para_distribuir, 'unidades_orden'));
        } else {
          $ordenes_para_distribuir = $ordenes_del_lote;
          $total_unidades_segmento = $gran_total_unidades_lote;
        }

        // 3b. Bucle interno para distribuir consumo y desperdicio
        if ($total_unidades_segmento > 0) {
          foreach ($ordenes_para_distribuir as $order) {
            $id_orden_actual = $order['id_orden'];
            $unidades_orden = intval($order['unidades_orden']);
            $proporcion = $unidades_orden / $total_unidades_segmento;

            // Distribuir y registrar consumo
            $consumo_estimado = $cantidad_total_consumida * $proporcion;
            $sql_movimiento = 'INSERT INTO inventario_movimientos (id_orden, id_empleado, id_insumo, id_departamento, departamento, valor_inicial, valor_final, moment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $localConnection->goQuery($sql_movimiento, [$id_orden_actual, $id_empleado, $id_insumo_actual, $id_departamento, $nombre_departamento, 0, $consumo_estimado, $now]);

            // Distribuir y registrar desperdicio en la tabla `rendimiento`
            $desperdicio_estimado = $desperdicio_total * $proporcion;
            $sql_rendimiento = 'INSERT INTO rendimiento (id_orden, id_empleado_corte, desperdicio, id_insumo, metros) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE desperdicio = desperdicio + VALUES(desperdicio), metros = metros + VALUES(metros);';
            $localConnection->goQuery($sql_rendimiento, [$id_orden_actual, $id_empleado, $desperdicio_estimado, $id_insumo_actual, $consumo_estimado]);
          }
        }
      }

      // 4. Bucle final para acciones de finalización (pagos, estados, etc.)
      $orden_proceso_actual = $localConnection->goQuery('SELECT orden_proceso FROM departamentos WHERE _id = ?', [$id_departamento])[0]['orden_proceso'];

      foreach ($ordenes_del_lote as $order) {
        $id_orden_actual = $order['id_orden'];

        // Lógica de actualización de paso en `lotes`
        $siguiente_paso_proceso = intval($orden_proceso_actual) + 1;
        $next_dep_info = $localConnection->goQuery('SELECT _id, departamento FROM departamentos WHERE asignar_numero_de_paso > 0 AND orden_proceso = ? LIMIT 1', [$siguiente_paso_proceso]);
        if (empty($next_dep_info)) {
          $localConnection->goQuery("UPDATE lotes SET paso = 'terminado', id_departamento_actual = 0 WHERE id_orden = ?", [$id_orden_actual]);
        } else {
          $localConnection->goQuery('UPDATE lotes SET paso = ?, id_departamento_actual = ? WHERE id_orden = ?', [$next_dep_info[0]['departamento'], $next_dep_info[0]['_id'], $id_orden_actual]);
        }

        // Lógica de Pagos y actualización de estados
        $sql_asignaciones = "SELECT _id as id_lotes_detalles, id_empleado, procentaje_comision FROM lotes_detalles_empleados_asignados WHERE id_orden = ? AND id_departamento = ?";
        $asignaciones = $localConnection->goQuery($sql_asignaciones, [$id_orden_actual, $id_departamento]);

        if (empty($asignaciones)) {
          $sql_insert_assign = "INSERT INTO lotes_detalles_empleados_asignados (id_orden, id_empleado, id_departamento, progreso, fecha_inicio, fecha_terminado, procentaje_comision) VALUES (?, ?, ?, 'terminada', ?, ?, 100)";
          $res_ins = $localConnection->goQuery($sql_insert_assign, [$id_orden_actual, $id_empleado, $id_departamento, $now, $now, 100]);
          $asignaciones = [
            [
              'id_lotes_detalles' => $res_ins['insert_id'],
              'id_empleado' => $id_empleado,
              'procentaje_comision' => 100
            ]
          ];
        }

        foreach ($asignaciones as $asignacion) {
          $id_emp_asignado = $asignacion['id_empleado'];
          $id_lotes_detalles = $asignacion['id_lotes_detalles'];
          $procentaje_comision_asignado = floatval($asignacion['procentaje_comision']);

          $sql_comision_empleado = 'SELECT comision, comision_tipo, comision_porcentaje, salario_tipo FROM api_empresas.empresas_usuarios WHERE id_usuario = ?';
          $resp_comision_empleado = $localConnection->goQuery($sql_comision_empleado, [$id_emp_asignado]);

          $comision_tipo = $resp_comision_empleado[0]['comision_tipo'] ?? 'fija';
          $salario_tipo = $resp_comision_empleado[0]['salario_tipo'] ?? '';
          $comision_value_emp = ($comision_tipo === 'porcentaje') ? floatval($resp_comision_empleado[0]['comision_porcentaje'] ?? 0) : floatval($resp_comision_empleado[0]['comision'] ?? 0);

          $total_monto_pago = 0;
          $cantidad_piezas = 0;
          $comision_guardar = $comision_value_emp;

          if ($comision_tipo === 'porcentaje') {
            $sql_calc = "SELECT SUM(c.cantidad) as total_piezas, SUM(c.cantidad * c.precio_unitario * ($comision_value_emp / 100)) AS total_monto FROM ordenes_productos c JOIN products p ON c.id_woo = p._id WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_orden_actual]);
            $cantidad_piezas = $res_calc[0]['total_piezas'] ?? 0;
            $total_monto_pago = ($res_calc[0]['total_monto'] ?? 0) * ($procentaje_comision_asignado / 100);
          } elseif ($comision_tipo === 'fija') {
            $sql_calc = "SELECT SUM(c.cantidad) as total_piezas FROM ordenes_productos c JOIN products p ON c.id_woo = p._id WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_orden_actual]);
            $cantidad_piezas = $res_calc[0]['total_piezas'] ?? 0;
            $total_monto_pago = ($cantidad_piezas * $comision_value_emp) * ($procentaje_comision_asignado / 100);
          } else {
            $sql_calc = "SELECT c.cantidad, IFNULL(pc.comision, 0) AS com_prod FROM ordenes_productos c JOIN products p ON c.id_woo = p._id LEFT JOIN products_comisiones pc ON pc.id_product = c.id_woo AND pc.id_departamento = ? WHERE c.id_orden = ? AND (p.fisico = 1 OR p.fisico IS NULL) AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
            $res_calc = $localConnection->goQuery($sql_calc, [$id_departamento, $id_orden_actual]);
            $comision_guardar = $res_calc[0]['com_prod'] ?? 0;
            foreach ($res_calc as $prod) {
              $cantidad_piezas += $prod['cantidad'];
              $total_monto_pago += ($prod['cantidad'] * $prod['com_prod']) * ($procentaje_comision_asignado / 100);
            }
          }

          if ($salario_tipo === 'Salario')
            $total_monto_pago = 0;

          $sql_check_pago = "SELECT _id FROM pagos WHERE id_orden = ? AND id_empleado = ? AND id_departamento = ? AND id_reposicion = 0 LIMIT 1";
          $check_pago = $localConnection->goQuery($sql_check_pago, [$id_orden_actual, $id_emp_asignado, $id_departamento]);

          if (empty($check_pago)) {
            $sql_pago = 'INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $localConnection->goQuery($sql_pago, [$id_orden_actual, 0, $id_departamento, $comision_guardar, $comision_tipo, $cantidad_piezas, $id_lotes_detalles, 'aprobado', $total_monto_pago, $id_emp_asignado, $nombre_departamento]);
          }

          // EXCEDENTES CORTE (Comisión Variable)
          if ($comision_tipo === 'variable') {
            $sqlExcVar = "SELECT
                  lca.id_ordenes_productos,
                  (lca.cantidad_ajustada - lca.cantidad_solicitada) AS excedente,
                  IFNULL(pc.comision, 0) AS comision_producto
                FROM lotes_corte_ajustes lca
                LEFT JOIN products_comisiones pc ON pc.id_product = (SELECT id_woo FROM ordenes_productos WHERE _id = lca.id_ordenes_productos LIMIT 1) AND pc.id_departamento = ?
                WHERE lca.id_orden = ?
                  AND (lca.cantidad_ajustada - lca.cantidad_solicitada) > 0
              ";
            $rspExcVar = $localConnection->goQuery($sqlExcVar, [$id_departamento, $id_orden_actual]);
            foreach ($rspExcVar as $excProd) {
              $excedente_piezas = floatval($excProd['excedente']);
              $comision_exc = floatval($excProd['comision_producto']);
              $monto_exc = ($excedente_piezas * $comision_exc) * ($procentaje_comision_asignado / 100);
              if ($salario_tipo === 'Salario')
                $monto_exc = 0;

              $sqlCheckExc = "SELECT _id FROM pagos WHERE id_orden = ? AND id_reposicion = 0 AND id_empleado = ? AND id_departamento = ? AND id_lotes_detalles = ? AND detalle = 'Corte-Excedente' LIMIT 1";
              $checkExc = $localConnection->goQuery($sqlCheckExc, [$id_orden_actual, $id_emp_asignado, $id_departamento, $id_lotes_detalles]);
              if (empty($checkExc)) {
                $sqlExcIns = "INSERT INTO pagos (id_orden, id_reposicion, id_departamento, comision, comision_tipo, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (?, 0, ?, ?, 'variable', ?, ?, 'aprobado', ?, ?, 'Corte-Excedente')";
                $localConnection->goQuery($sqlExcIns, [$id_orden_actual, $id_departamento, $comision_exc, $excedente_piezas, $id_lotes_detalles, $monto_exc, $id_emp_asignado]);
              }
            }
          }

          $localConnection->goQuery("UPDATE lotes_detalles_empleados_asignados SET fecha_terminado = ?, progreso = 'terminada' WHERE _id = ?", [$now, $id_lotes_detalles]);
        }

        $localConnection->goQuery("UPDATE lotes_detalles SET fecha_terminado = ?, progreso = 'terminada' WHERE id_departamento = ? AND id_orden = ?", [$now, $id_departamento, $id_orden_actual]);
      }

      // 5. Finalizar el lote principal
      $localConnection->goQuery("UPDATE empleados_lotes_fabricacion SET estado = 'terminado', fecha_fin = ? WHERE _id = ?", [$now, $id_lote]);

      $response_data = ['status' => 'success', 'message' => "Lote de Corte {$id_lote} finalizado y consumos registrados correctamente."];
      $response->getBody()->write(json_encode($response_data, JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    } catch (Exception $e) {
      if ($localConnection) {
        $localConnection->disconnect();
      }
      $error_response = json_encode(['error' => 'Error al finalizar el lote de corte: ' . $e->getMessage()]);
      $response->getBody()->write($error_response);
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
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
      $sql = 'SELECT comision, comision_tipo, comision_porcentaje FROM api_empresas.empresas_usuarios WHERE id_usuario = ' . $miEmpleado[0]['id_empleado'];
      $respComision = $localConnection->goQuery($sql);
      $object['rsp_empleados'] = $respComision;
      $comisionTipo = $respComision[0]['comision_tipo'];

      // DETERMINAR TIPO DE COMISION
      if ($comisionTipo === 'variable') {
        // Obtener ID del departamento
        $sqlDep = "SELECT _id FROM departamentos WHERE departamento = '" . $args['departamento'] . "'";
        $resDep = $localConnection->goQuery($sqlDep);
        $id_dep_actual = $resDep[0]['_id'] ?? 0;

        // Buscar comision en el producto (por departamento)
        $sqlc = "SELECT IFNULL(pc.comision, 0) AS comision 
                 FROM lotes_detalles a 
                 LEFT JOIN products_comisiones pc ON pc.id_product = a.id_woo AND pc.id_departamento = $id_dep_actual
                 WHERE a._id = " . $args['id_lotes_detalles'];
        $object['sql_comision_variable'] = $sqlc;
        $comisionEmpleado = $localConnection->goQuery($sqlc);
        $miComision = $comisionEmpleado[0]['comision'];
      } elseif ($comisionTipo === 'porcentaje') {
        $miComision = floatval($respComision[0]['comision_porcentaje']);
      } else {
        // Preparar comision del registro del empleado (Multipliar la comision en la tabla products_comisiones=>comision por el porcentaje asingado en la tabla lotes_detalles_empleados_asignados => porcentaje_comsion)

        // Buscar id de la orden

        $comisionFloat = floatval($respComision[0]['comision']);
        $floatValue = floatval($comisionFloat);
        $miComision = number_format($floatValue, 2);
      }

      // FIX: Recalcular unidades para excluir no físicos (diseños)
      // Nota: args['id_lotes_detalles'] es el ID de la ASIGNACIÓN (tabla lotes_detalles_empleados_asignados)
      $sql_orden_id = 'SELECT id_orden FROM lotes_detalles_empleados_asignados WHERE _id = ' . $args['id_lotes_detalles'];
      $resOrden = $localConnection->goQuery($sql_orden_id);
      $idOrdenActual = $resOrden[0]['id_orden'] ?? 0;

      if ($idOrdenActual > 0) {
        $sql_clean_units = "SELECT SUM(op.cantidad) as total 
                              FROM ordenes_productos op
                              JOIN products p ON op.id_woo = p._id
                              WHERE op.id_orden = $idOrdenActual
                              AND (p.fisico = 1 OR p.fisico IS NULL)
                              AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
        $resClean = $localConnection->goQuery($sql_clean_units);
        $cleanUnits = floatval($resClean[0]['total'] ?? 0);

        // Si el total limpio es menor que lo reportado, usamos el total limpio
        // Esto evita pagar por diseños si el frontend envía el total incluyendo diseños
        if ($cleanUnits > 0 && $cleanUnits < floatval($args['unidades'])) {
          $args['unidades'] = $cleanUnits;
        }

        $object['debug_fix'] = [
          'step' => 'check_fisico',
          'id_lotes_detalles_arg' => $args['id_lotes_detalles'],
          'idOrdenFound' => $idOrdenActual,
          'cleanUnitsCalculated' => $cleanUnits,
          'originalUnidades' => $args['unidades'], // Valor posiblemente modificado si entró al IF
          'sql_clean' => $sql_clean_units
        ];
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
          if (!is_object($prod_woo) || empty($prod_woo->attributes)) {
            $monto_pago = 0;
            $object['product-attributes-vacio'] = true;
          } else {
            $object['product-attributes-vacio'] = false;
            $object['procesar_pago']['unidades'] = $respLotesDetalles[0]['unidades'];

            // Acceso seguro a atributos de producto de WooCommerce
            $comision_valor = 0;
            if (isset($prod_woo->attributes[0]->options[0])) {
              $comision_valor = floatval($prod_woo->attributes[0]->options[0]);
            }

            $object['procesar_pago']['comison_woo'] = $comision_valor;
            $calculo_pago = intval($respLotesDetalles[0]['unidades']) * $comision_valor;
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

    // --- INICIO DE LA SEGUNDA CORRECCIÓN ---
    $sql = "
        -- Versión 4: Consulta robusta con CTE + UNION para cubrir tareas proyectadas y tareas extra/ad-hoc
        WITH AssignmentData AS (
            -- Consolidamos asignaciones por tarea (orden + departamento)
            SELECT
                ldea.id_orden,
                ldea.id_departamento,
                COUNT(DISTINCT ldea.id_empleado) AS numero_de_empleados,
                MIN(ldea.fecha_inicio) AS fecha_inicio_agregada,
                -- Solo consideramos terminado el departamento si TODOS los empleados terminaron
                CASE 
                    WHEN COUNT(ldea.id_empleado) = COUNT(ldea.fecha_terminado) THEN MAX(ldea.fecha_terminado) 
                    ELSE NULL 
                END AS fecha_terminado_agregada
            FROM
                lotes_detalles_empleados_asignados ldea
            JOIN ordenes o ON o._id = ldea.id_orden
            WHERE
                (o.status LIKE 'En espera' OR o.status LIKE 'activa' OR o.status LIKE 'pausada')
            GROUP BY
                ldea.id_orden,
                ldea.id_departamento
        )
        SELECT * FROM (
            -- PARTE A: Tareas Proyectadas (Configuradas en el producto)
            -- Se muestran existan o no asignaciones reales (LEFT JOIN AssignmentData)
            SELECT
                a.id_orden,
                c.status,
                d.id_departamento,
                dep.departamento AS nombre_departamento,
                ad.fecha_inicio_agregada AS fecha_inicio,
                ad.fecha_terminado_agregada AS fecha_terminado,
                c.fecha_entrega AS fecha_entrega_de_la_orden,
                (SELECT CONCAT(o.fecha_entrega, ' 08:30:00') FROM ordenes o WHERE o._id = a.id_orden) AS fecha_entrega_orden,
                SUM(a.cantidad) AS total_unidades,
                (SUM(d.tiempo * a.cantidad) / COALESCE(ad.numero_de_empleados, 1)) AS tiempo_total_orden_depto,
                ofo.orden_fila AS orden_fila_orden,
                dep.orden_proceso AS orden_proceso_departamento,
                COALESCE(ad.numero_de_empleados, 0) AS cant_empleados
            FROM
                ordenes_productos a
            JOIN
                products_tiempos_de_produccion d ON d.id_product = a.id_woo
            JOIN
                departamentos dep ON dep._id = d.id_departamento
            JOIN
                ordenes c ON c._id = a.id_orden
            LEFT JOIN
                AssignmentData ad ON ad.id_orden = a.id_orden AND ad.id_departamento = d.id_departamento
            LEFT JOIN
                ordenes_fila_orden ofo ON ofo.id_orden = a.id_orden
            WHERE
                (c.status LIKE 'En espera' OR c.status LIKE 'activa' OR c.status LIKE 'pausada')
            GROUP BY
                a.id_orden,
                d.id_departamento

            UNION ALL

            -- PARTE B: Tareas Extra/Ad-hoc (Asignaciones reales SIN configuración de producto)
            -- Se muestran solo si NO existen en la Parte A (NOT EXISTS)
            SELECT
                ad.id_orden,
                c.status,
                ad.id_departamento,
                dep.departamento AS nombre_departamento,
                ad.fecha_inicio_agregada AS fecha_inicio,
                ad.fecha_terminado_agregada AS fecha_terminado,
                c.fecha_entrega AS fecha_entrega_de_la_orden,
                (SELECT CONCAT(o.fecha_entrega, ' 08:30:00') FROM ordenes o WHERE o._id = ad.id_orden) AS fecha_entrega_orden,
                (SELECT SUM(op.cantidad) FROM ordenes_productos op WHERE op.id_orden = ad.id_orden) AS total_unidades,
                0 AS tiempo_total_orden_depto, -- No hay tiempo estimado configurado
                ofo.orden_fila AS orden_fila_orden,
                dep.orden_proceso AS orden_proceso_departamento,
                ad.numero_de_empleados AS cant_empleados
            FROM
                AssignmentData ad
            JOIN
                ordenes c ON c._id = ad.id_orden
            JOIN
                departamentos dep ON dep._id = ad.id_departamento
            LEFT JOIN
                ordenes_fila_orden ofo ON ofo.id_orden = ad.id_orden
            WHERE
                NOT EXISTS (
                    SELECT 1
                    FROM ordenes_productos a
                    JOIN products_tiempos_de_produccion d ON d.id_product = a.id_woo
                    WHERE a.id_orden = ad.id_orden AND d.id_departamento = ad.id_departamento
                )
        ) AS UnifiedResults
        ORDER BY
            orden_fila_orden ASC,
            id_orden ASC,
            orden_proceso_departamento ASC;
    ";
    // --- FIN DE LA SEGUNDA CORRECCIÓN ---

    $rspRendimientoTiempo = $localConnection->goQuery($sql);
    $object = $rspRendimientoTiempo;

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Resumen de piezas asignadas personalmente a un empleado para una orden específica
  // Fórmula: (cantidad_base + excedente_corte_si_aplica) * (procentaje_comision / 100)
  $app->get('/empleados/mi-asignacion/{id_orden}/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $object = [];

    // Obtener datos del empleado en esta asignación
    $sqlAsignacion = "SELECT
        a.id_departamento,
        a.procentaje_comision,
        dep.departamento
      FROM lotes_detalles_empleados_asignados a
      JOIN departamentos dep ON dep._id = a.id_departamento
      WHERE a.id_orden = {$args['id_orden']}
        AND a.id_empleado = {$args['id_empleado']}
      LIMIT 1";
    $asignacion = $localConnection->goQuery($sqlAsignacion);

    if (empty($asignacion)) {
      $response->getBody()->write(json_encode(['productos' => [], 'msg' => 'Sin asignacion encontrada']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $porcentaje = floatval($asignacion[0]['procentaje_comision']) > 0 ? floatval($asignacion[0]['procentaje_comision']) : 100;
    $id_departamento = intval($asignacion[0]['id_departamento']);

    // Obtener productos base de la orden
    $sqlProductos = "SELECT
        op._id AS id_ordenes_productos,
        op.id_woo,
        op.name AS nombre,
        op.cantidad AS cantidad_base,
        (SELECT nombre FROM sizes WHERE _id = op.id_size) AS talla,
        op.corte,
        op.tela,
        op.precio_unitario
      FROM ordenes_productos op
      JOIN products p ON p._id = op.id_woo
      WHERE op.id_orden = {$args['id_orden']}
        AND (p.fisico = 1 OR p.fisico IS NULL)
        AND (p.es_diseno = 0 OR p.es_diseno IS NULL)";
    $productos = $localConnection->goQuery($sqlProductos);

    // Si es Corte (ID 3), buscar cantidad real cortada en inventario_corte
    // El excedente = cantidad_real_cortada - cantidad_solicitada
    $excedentesMap = [];
    if ($id_departamento === 3) {
      $sqlExc = "SELECT id_ordenes_productos, cantidad AS cantidad_real FROM inventario_corte WHERE id_orden = {$args['id_orden']}";
      $excedentes = $localConnection->goQuery($sqlExc);
      foreach ($excedentes as $exc) {
        $excedentesMap[intval($exc['id_ordenes_productos'])] = floatval($exc['cantidad_real']);
      }
    }


    // Calcular cantidad asignada por producto
    $result = [];
    foreach ($productos as $prod) {
      $id_op = intval($prod['id_ordenes_productos']);
      $cantidad_base = floatval($prod['cantidad_base']);

      // Para Corte: excedentesMap contiene la cantidad REAL cortada (ic.cantidad)
      // Para otros departamentos: excedentesMap estará vacío, se usa la cantidad_base
      if (isset($excedentesMap[$id_op])) {
        $cantidad_real = $excedentesMap[$id_op];
        $excedente = $cantidad_real - $cantidad_base;
        $cantidad_total = $cantidad_real;
      } else {
        $cantidad_real = $cantidad_base;
        $excedente = 0;
        $cantidad_total = $cantidad_base;
      }

      $cantidad_asignada = $cantidad_total * ($porcentaje / 100);

      $result[] = [
        'id_ordenes_productos' => $id_op,
        'nombre' => $prod['nombre'],
        'talla' => $prod['talla'],
        'corte' => $prod['corte'],
        'tela' => $prod['tela'],
        'cantidad_base' => $cantidad_base,
        'excedente' => $excedente,
        'cantidad_total' => $cantidad_total,
        'porcentaje_asignado' => $porcentaje,
        'cantidad_asignada' => round($cantidad_asignada, 0),
      ];
    }

    $object['productos'] = $result;
    $object['departamento'] = $asignacion[0]['departamento'];
    $object['id_departamento'] = $id_departamento;

    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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
                (SELECT tela FROM catalogo_telas WHERE _id = c.id_tela) AS tela_vendedor,
                a.detalle AS detalle_empleado,
                a.detalle_emisor,
                COALESCE(NULLIF(a.detalle, ''), a.detalle_emisor) AS detalle_reposicion,
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
                (SELECT fecha_inicio FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_empleado = {$args['id_empleado']} AND id_departamento = {$args['id_departamento']} AND id_reposicion = a._id LIMIT 1) as fecha_inicio,
                (SELECT fecha_terminado FROM lotes_detalles_empleados_asignados WHERE id_orden = a.id_orden AND id_empleado = {$args['id_empleado']} AND id_departamento = {$args['id_departamento']} AND id_reposicion = a._id LIMIT 1) as fecha_terminado,
                c.corte    
            FROM
                reposiciones a  
            LEFT JOIN ordenes b ON b._id = a.id_orden 
            JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
            LEFT JOIN departamentos d ON d._id = a.id_departamento
            WHERE a.terminada = 0
                AND (
                    a.id_empleado = {$args['id_empleado']} 
                    OR a.id_departamento_solicitante = {$args['id_departamento']}
                    OR (a.id_departamento = {$args['id_departamento']} AND a.id_empleado IS NULL)
                )
                -- AND {$args['orden_proceso']} >= (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento) -- Filtramos que no se incluyan departamentos ateriores al asignado
                -- AND {$args['orden_proceso']} <= (SELECT orden_proceso FROM departamentos WHERE _id = a.id_departamento_solicitante) -- Filtramos que no se incluyan departamentos ateriores al asignado
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
            (z.unidades_produccion + IFNULL((SELECT (lca.cantidad_ajustada - lca.cantidad_solicitada) FROM lotes_corte_ajustes lca WHERE lca.id_ordenes_productos = a._id AND lca.id_orden = a.id_orden LIMIT 1), 0)) AS unidades_solicitadas,
            -- Unidades: para Corte suman el excedente pactado; para otros departamentos solo la cantidad original
            IF({$args['id_departamento']} = 3,
                (a.cantidad + IFNULL((SELECT (lca.cantidad_ajustada - lca.cantidad_solicitada) FROM lotes_corte_ajustes lca WHERE lca.id_ordenes_productos = a._id AND lca.id_orden = a.id_orden LIMIT 1), 0)),
                a.cantidad
            ) AS unidades,
            IF({$args['id_departamento']} = 3,
                (a.cantidad + IFNULL((SELECT (lca.cantidad_ajustada - lca.cantidad_solicitada) FROM lotes_corte_ajustes lca WHERE lca.id_ordenes_productos = a._id AND lca.id_orden = a.id_orden LIMIT 1), 0)),
                a.cantidad
            ) AS piezas_actuales,
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
            (
                -- FIX: Primer departamento donde AUN hay empleados sin terminar.
                -- Solo avanza al siguiente cuando TODOS terminaron (fecha_terminado IS NULL).
                SELECT dep.orden_proceso
                FROM lotes_detalles_empleados_asignados ldea2
                JOIN departamentos dep ON dep._id = ldea2.id_departamento
                WHERE ldea2.id_orden = y.id_orden
                    AND ldea2.fecha_terminado IS NULL
                ORDER BY dep.orden_proceso ASC
                LIMIT 1
            ) AS orden_proceso,
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
            (SELECT tela FROM catalogo_telas WHERE _id = a.id_tela) AS tela_vendedor,
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
            AND y.fecha_terminado IS NULL -- Excluir tareas terminadas
        ORDER BY
            ofo.orden_fila ASC,
            y.id_orden DESC,
            y.progreso ASC; -- El orden del progreso ahora se basa en 'y'
        ";

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
            WHERE /* b.id_empleado = {$args['id_empleado']} AND b.id_departamento = {$args['id_departamento']} AND */(c.status LIKE 'pausada')
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
                a.talla,
                r.unidades unidades_reposicion,
                r.detalle detalle_reposicion,
                a.cantidad,
                a.tela,
                (SELECT tela FROM catalogo_telas WHERE _id = a.id_tela) AS tela_vendedor,
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
  $app->get('/empleados-todos', function (Request $request, Response $response) {
    // $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
    $localConnection = new LocalDB();
    $idEmp = ID_EMPRESA;
    $sql = 'SELECT
            a.id_usuario AS _id,
            a.email AS username,
            a.activo,
            a.password,
            a.nombre,
            a.email,
            a.telefono,
            a.departamento,
            a.comision,
            a.comision_porcentaje,
            a.salario_tipo,
            a.salario_monto,
            a.salario_periodo,
            a.comision_tipo,
            a.acceso,
            a.dni,
            a.fecha_ingreso,
            a.id_seguridad_social,
            -- FNULL(GROUP_CONCAT(b.id_departamento), null) AS departamentos
            IFNULL(CONCAT("[", GROUP_CONCAT(
                DISTINCT CONCAT("{\"id\":", b.id_departamento, ",\"nombre\":\"", c.departamento, "\"}")
                SEPARATOR ","), "]"), "[]") AS departamentos,
            IFNULL(CONCAT("[", GROUP_CONCAT(
                DISTINCT CONCAT("{\"id_carga\":", d.id_carga, ",\"nombre_completo\":\"", d.nombre_completo, "\",\"cedula_o_id\":\"", d.cedula_o_id, "\",\"parentesco\":\"", d.tipo_relacion, "\",\"fecha_nacimiento\":\"", d.fecha_nacimiento, "\",\"es_deducible\":", d.es_deducible_impuesto, "}")
                SEPARATOR ","), "]"), "[]") AS carga_familiar
        FROM
            api_empresas.empresas_usuarios a
        LEFT JOIN api_empresas.empresas_usuarios_departamentos b ON b.id_empleado = a.id_usuario
        LEFT JOIN ' . LOCAL_DB . '.departamentos c ON c._id = b.id_departamento
        LEFT JOIN ' . LOCAL_DB . '.salario_carga_familiar d ON d.id_empleado = a.id_usuario
        WHERE
            a.id_empresa = ' . ID_EMPRESA . ' GROUP BY
            a.id_usuario, a.email, a.password, a.nombre, a.departamento,
            a.telefono, a.comision, a.comision_porcentaje,
            a.salario_tipo, a.salario_monto, a.salario_periodo,
            a.comision_tipo, a.acceso, a.dni, a.fecha_ingreso, a.id_seguridad_social;';
    // $object['sql'] = $sql;
    $items = $localConnection->goQuery($sql);

    // Decodificar el campo `departamentos` y `carga_familiar`
    foreach ($items as &$item) {
      if (!empty($item['departamentos'])) {
        $item['departamentos'] = json_decode($item['departamentos'], true);
      }
      if (isset($item['carga_familiar']) && $item['carga_familiar'] !== null && $item['carga_familiar'] !== '[]') {
        $item['carga_familiar'] = json_decode($item['carga_familiar'], true);
      } else {
        $item['carga_familiar'] = [];
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



  $app->get('/calculo-pago-real/{dias}', function (Request $request, Response $response, array $args) {
    if (!isset($args['dias'])) {
      $data['dias'] = $args['dias'];
    } else {
      $db = new LocalDB();

      $sql = "SELECT 
            AVG(tasa) as promedio_tasa
          FROM metodos_de_pago
          WHERE
              moneda = 'Bolívares'
              AND metodo_pago IN ('Pagomovil', 'Transferencia')
              AND DATE(moment) = CURDATE()";

      $resp = $db->goQuery($sql);

      $precio_hora = 2.5;
      $horas_por_dia = 8;
      $porcentaje_ajuste = 20;

      $ht = floatval($args['dias']) * $horas_por_dia;
      $data['horas_trabajadas'] = $ht;

      $data['dolares'] = $ht * $precio_hora;

      $data['porcentaje_ajuste'] = $porcentaje_ajuste;

      $tasa = floatval($resp[0]['promedio_tasa']);
      $data['tasa_promedio'] = number_format($tasa, 2, '.', '');

      $tasa_ajustada = ($tasa) * $porcentaje_ajuste / 100;

      $ajuste = number_format($tasa_ajustada, 2, '.', '');

      $data['monto_ajuste'] = number_format($tasa_ajustada, 2, '.', '');

      $data['tasa_ajustada'] = number_format(($ajuste + $tasa), 2, '.', '');

      // $total_pago = ($tasa * $ht) + (($tasa* $ht) * $porcentaje_ajuste / 100);
      $total_pago = $data['dolares'] * $data['tasa_ajustada'];

      // $data["bolivares_con descuento"] = number_format(($data['dolares'] * $data["tasa_promedio"]), 2, '.', '');
      $data['bolivares_real'] = number_format($total_pago, 2, '.', '');

      $db->disconnect();
    }

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // POST /categories - Crear nueva categoría
  /* $app->post('/categories', function (Request $request, Response $response) {
      try {
          $json = $request->getBody()->getContents();
          $data = json_decode($json, true);

          if (json_last_error() !== JSON_ERROR_NONE) {
              $response->getBody()->write(json_encode([
                  'success' => false,
                  'message' => 'JSON inválido'
              ]));
              return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
          }

          $nombre = trim($data['nombre'] ?? '');

          if (empty($nombre)) {
              $response->getBody()->write(json_encode([
                  'success' => false,
                  'message' => 'El nombre de la categoría es obligatorio'
              ]));
              return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
          }

          if (!defined('EMPRESA_ID')) {
              $response->getBody()->write(json_encode([
                  'success' => false,
                  'message' => 'No se pudo identificar la empresa'
              ]));
              return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
          }
    }); */

  // --- DEBUG ENDPOINT ---
  $app->get('/debug-efficiency', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $params = $request->getQueryParams();
    $id_orden = isset($params['id_orden']) ? intval($params['id_orden']) : 1;
    $id_empleado = isset($params['id_empleado']) ? intval($params['id_empleado']) : 479;

    $debugData = [];

    // 1. Check Assignments
    $sql = "SELECT * FROM lotes_detalles_empleados_asignados WHERE id_orden = $id_orden AND id_empleado = $id_empleado";
    $debugData['assignments'] = $localConnection->goQuery($sql);

    // 2. Check Departments linked to these assignments
    $sql = "SELECT DISTINCT id_departamento FROM lotes_detalles_empleados_asignados WHERE id_orden = $id_orden AND id_empleado = $id_empleado";
    $debugData['assigned_departments'] = $localConnection->goQuery($sql);

    // 3. Check Standards for this order's products
    $sql = "SELECT ptp.*, op.name as product_name 
              FROM products_tiempos_de_produccion ptp
              JOIN ordenes_productos op ON op.id_woo = ptp.id_product
              WHERE op.id_orden = $id_orden";
    $debugData['standards'] = $localConnection->goQuery($sql);

    // 4. Check Lotes Detalles
    $sql = "SELECT * FROM lotes_detalles WHERE id_ordenes_productos IN (SELECT _id FROM ordenes_productos WHERE id_orden = $id_orden)";
    $debugData['lotes_detalles'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();
    $response->getBody()->write(json_encode($debugData));
    return $response->withHeader('Content-Type', 'application/json');
  });

  // --- NUEVO ENDPOINT: Eficiencia de Insumos (Bulk & Cost-based) ---
  $app->get('/reports/input-efficiency/{id_ordenes}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $ids_ordenes = $args['id_ordenes']; // Comma separated IDs

    // Validate IDs to prevent SQL injection
    $idsArray = explode(',', $ids_ordenes);
    $cleanIds = [];
    foreach ($idsArray as $id) {
      if (is_numeric($id)) {
        $cleanIds[] = intval($id);
      }
    }

    if (empty($cleanIds)) {
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $idsString = implode(',', $cleanIds);

    $sql = "
            SELECT
                COALESCE((
                  SELECT MAX(im.id_insumo) 
                  FROM inventario_movimientos im 
                  LEFT JOIN inventario inv ON inv._id = im.id_insumo 
                  WHERE im.id_orden IN ($idsString) 
                    AND (inv.id_catalogo = cip._id OR im.id_catalogo_insumos_prodcutos = cip._id)
                ), (SELECT MAX(_id) FROM inventario WHERE id_catalogo = cip._id)) AS id_insumo,
                cip._id AS id_insumo_catalogo,
                cip.nombre AS nombre_insumo,
                cip.id_departamento,
                
                -- Consumo Estándar (Meta): SOLO sumamos la meta si hay consumo real registrado para esta orden e insumo.
                -- Esto evita inflar la meta con órdenes en las que el empleado aún no ha trabajado o consumido material.
                SUM(
                  CASE 
                    WHEN (
                      SELECT COUNT(*) 
                      FROM inventario_movimientos im_check 
                      LEFT JOIN inventario inv_check ON inv_check._id = im_check.id_insumo
                      WHERE im_check.id_orden = op.id_orden 
                      AND (im_check.id_catalogo_insumos_prodcutos = cip._id OR inv_check.id_catalogo = cip._id)
                    ) > 0 THEN op.cantidad * pia.cantidad 
                    ELSE 0 
                  END
                ) AS cantidad_estandar,
                
                -- Limpieza de unidad: Evitar 'null' string
                MAX(CASE WHEN pia.unidad IS NULL OR pia.unidad = 'null' THEN 'Und' ELSE pia.unidad END) AS unidad,
                
                -- Rendimiento: Ahora retornamos 1 porque el cálculo real ya viene convertido a la unidad destino (Metros)
                1.0 AS rendimiento, 

                -- Consumo Real: Suma de movimientos registrados
                COALESCE((
                    SELECT SUM(ABS(im_sub.valor_final - im_sub.valor_inicial) * COALESCE(inv_sub.rendimiento, 1))
                    FROM inventario_movimientos im_sub
                    LEFT JOIN inventario inv_sub ON inv_sub._id = im_sub.id_insumo
                    WHERE im_sub.id_orden IN ($idsString)
                      AND (inv_sub.id_catalogo = cip._id OR im_sub.id_catalogo_insumos_prodcutos = cip._id)
                ), 0) AS cantidad_real

            FROM ordenes_productos op
            JOIN product_insumos_asignados pia ON pia.id_product = op.id_woo AND pia.id_talla = op.id_size
            JOIN catalogo_insumos_productos cip ON cip._id = pia.id_catalogo_insumos_productos
            
            WHERE op.id_orden IN ($idsString)
            GROUP BY cip._id, cip.nombre, cip.id_departamento
        ";

    file_put_contents('debug_sql_error.log', "SQL Query:\n" . $sql . "\n", FILE_APPEND);

    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // REPORTE DE TIEMPOS DE FABRICACIÓN
  $app->get('/reports/manufacturing-time', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $localConnection = new LocalDB();

    try {
      $id_orden = isset($params['id_orden']) ? intval($params['id_orden']) : null;
      $id_ordenes = isset($params['id_ordenes']) ? $params['id_ordenes'] : null; // Expecting comma separated string
      $fecha_inicio = isset($params['fecha_inicio']) ? $params['fecha_inicio'] : null;
      $fecha_fin = isset($params['fecha_fin']) ? $params['fecha_fin'] : null;
      $limit = isset($params['limit']) ? intval($params['limit']) : 50;
      $id_empleado = isset($params['id_empleado']) ? intval($params['id_empleado']) : null;

      $whereConditions = [];

      if ($id_orden) {
        $whereConditions[] = "o._id = $id_orden";
      }

      if ($id_ordenes) {
        // Validate that it's a list of integers
        $ids = array_map('intval', explode(',', $id_ordenes));
        $idsStr = implode(',', $ids);
        $whereConditions[] = "o._id IN ($idsStr)";
      }

      if ($fecha_inicio && $fecha_fin) {
        $whereConditions[] = "DATE(o.moment) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
      }

      $whereClause = "";
      if (count($whereConditions) > 0) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
      }

      // Define Real Time Calculation Logic
      if ($id_empleado) {
        $realTimeCalculationTerminado = "
            COALESCE(
                 (SELECT SUM(
                    CASE 
                        WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NOT NULL THEN 
                            TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, sub_ldea.fecha_terminado)
                        ELSE 0 
                    END
                    / 
                    CASE 
                        WHEN sub_ldea.id_lotes_detalles IS NULL THEN 
                            (SELECT COUNT(*) FROM lotes_detalles ld_count WHERE ld_count.id_orden = sub_ldea.id_orden AND ld_count.id_departamento = sub_ldea.id_departamento)
                        ELSE 1
                    END
                 )
                 FROM lotes_detalles_empleados_asignados sub_ldea 
                 WHERE (sub_ldea.id_lotes_detalles = ld._id 
                    OR (sub_ldea.id_lotes_detalles IS NULL AND sub_ldea.id_orden = ld.id_orden AND sub_ldea.id_departamento = ld.id_departamento))
                 AND sub_ldea.id_empleado = $id_empleado
                 ), 
                0
            )
          ";

        $realTimeCalculationEnCurso = "
            COALESCE(
                 (SELECT SUM(
                    CASE 
                        WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NULL THEN 
                            TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, NOW())
                        ELSE 0 
                    END
                    / 
                    CASE 
                        WHEN sub_ldea.id_lotes_detalles IS NULL THEN 
                            (SELECT COUNT(*) FROM lotes_detalles ld_count WHERE ld_count.id_orden = sub_ldea.id_orden AND ld_count.id_departamento = sub_ldea.id_departamento)
                        ELSE 1
                    END
                 )
                 FROM lotes_detalles_empleados_asignados sub_ldea 
                 WHERE (sub_ldea.id_lotes_detalles = ld._id 
                    OR (sub_ldea.id_lotes_detalles IS NULL AND sub_ldea.id_orden = ld.id_orden AND sub_ldea.id_departamento = ld.id_departamento))
                 AND sub_ldea.id_empleado = $id_empleado
                 ), 
                0
            )
          ";
      } else {
        $realTimeCalculationTerminado = "
            (CASE 
                WHEN ld.fecha_inicio IS NOT NULL AND ld.fecha_terminado IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, ld.fecha_inicio, ld.fecha_terminado)
                ELSE 
                    COALESCE(
                         (SELECT SUM(
                            CASE 
                                WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NOT NULL THEN 
                                    TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, sub_ldea.fecha_terminado)
                                ELSE 0 
                            END
                         )
                         FROM lotes_detalles_empleados_asignados sub_ldea 
                         WHERE sub_ldea.id_lotes_detalles = ld._id 
                            OR (sub_ldea.id_lotes_detalles IS NULL AND sub_ldea.id_orden = ld.id_orden AND sub_ldea.id_departamento = ld.id_departamento)), 
                        0
                    )
            END
            / 
            (SELECT COUNT(*) FROM lotes_detalles ld_div WHERE ld_div.id_orden = ld.id_orden AND ld_div.id_departamento = ld.id_departamento))
          ";

        $realTimeCalculationEnCurso = "
            (CASE 
                WHEN ld.fecha_inicio IS NOT NULL AND ld.fecha_terminado IS NULL THEN 
                    TIMESTAMPDIFF(SECOND, ld.fecha_inicio, NOW())
                ELSE 
                    COALESCE(
                         (SELECT SUM(
                            CASE 
                                WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NULL THEN 
                                    TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, NOW())
                                ELSE 0 
                            END
                         )
                         FROM lotes_detalles_empleados_asignados sub_ldea 
                         WHERE sub_ldea.id_lotes_detalles = ld._id 
                            OR (sub_ldea.id_lotes_detalles IS NULL AND sub_ldea.id_orden = ld.id_orden AND sub_ldea.id_departamento = ld.id_departamento)), 
                        0
                    )
            END
            / 
            (SELECT COUNT(*) FROM lotes_detalles ld_div WHERE ld_div.id_orden = ld.id_orden AND ld_div.id_departamento = ld.id_departamento))
          ";
      }

      $sql = "SELECT 
                  o._id AS id_orden,
                  o.id_wp AS id_woocommerce,
                  o.status,
                  c.first_name AS cliente_nombre,
                  c.cedula AS cliente_cedula,
                  op.id_woo AS id_product_woo, -- Agregado para identificar producto en frontend
                  op.name AS producto,
                  op.cantidad,
                  
                  -- Tiempo Real
                  SUM($realTimeCalculationTerminado) AS totalRealTerminadas,
                  SUM($realTimeCalculationEnCurso) AS totalRealEnCurso,
                  (SUM($realTimeCalculationTerminado) + SUM($realTimeCalculationEnCurso)) AS tiempo_total_segundos,
                
                  -- Tiempo Proyectado (Estimado) - Tareas Terminadas
                  (
                      SELECT COALESCE(SUM(ptp.tiempo * op.cantidad), 0)
                      FROM products_tiempos_de_produccion ptp
                      WHERE ptp.id_product = op.id_woo
                      AND ptp.id_departamento IN (
                          SELECT DISTINCT ld_sub.id_departamento
                          FROM lotes_detalles ld_sub
                          " . ($id_empleado ? "
                            JOIN lotes_detalles_empleados_asignados ldea_sub ON 
                                ldea_sub.id_empleado = $id_empleado AND (
                                    ldea_sub.id_lotes_detalles = ld_sub._id 
                                    OR (ldea_sub.id_lotes_detalles IS NULL AND ldea_sub.id_orden = o._id AND ldea_sub.id_departamento = ld_sub.id_departamento)
                                )
                          " : "") . "
                          WHERE ld_sub.id_ordenes_productos = op._id
                          AND ld_sub.fecha_terminado IS NOT NULL
                      )
                  ) AS totalProjectedTerminadas,

                  -- Tiempo Proyectado (Estimado) - Tareas En Curso
                  (
                      SELECT COALESCE(SUM(ptp.tiempo * op.cantidad), 0)
                      FROM products_tiempos_de_produccion ptp
                      WHERE ptp.id_product = op.id_woo
                      AND ptp.id_departamento IN (
                          SELECT DISTINCT ld_sub.id_departamento
                          FROM lotes_detalles ld_sub
                          " . ($id_empleado ? "
                            JOIN lotes_detalles_empleados_asignados ldea_sub ON 
                                ldea_sub.id_empleado = $id_empleado AND (
                                    ldea_sub.id_lotes_detalles = ld_sub._id 
                                    OR (ldea_sub.id_lotes_detalles IS NULL AND ldea_sub.id_orden = o._id AND ldea_sub.id_departamento = ld_sub.id_departamento)
                                )
                          " : "") . "
                          WHERE ld_sub.id_ordenes_productos = op._id
                          AND ld_sub.fecha_terminado IS NULL
                          AND ld_sub.fecha_inicio IS NOT NULL
                      )
                  ) AS totalProjectedEnCurso,

                  -- Tiempo Proyectado Total (Compatibilidad)
                  (
                      SELECT COALESCE(SUM(ptp.tiempo * op.cantidad), 0)
                      FROM products_tiempos_de_produccion ptp
                      WHERE ptp.id_product = op.id_woo
                      AND ptp.id_departamento IN (
                          SELECT DISTINCT ld_sub.id_departamento
                          FROM lotes_detalles ld_sub
                          " . ($id_empleado ? "
                            JOIN lotes_detalles_empleados_asignados ldea_sub ON 
                                ldea_sub.id_empleado = $id_empleado AND (
                                    ldea_sub.id_lotes_detalles = ld_sub._id 
                                    OR (ldea_sub.id_lotes_detalles IS NULL AND ldea_sub.id_orden = o._id AND ldea_sub.id_departamento = ld_sub.id_departamento)
                                )
                          " : "") . "
                          WHERE ld_sub.id_ordenes_productos = op._id
                      )
                  ) AS tiempo_proyectado_segundos,

                  -- Fecha Inicio Primer Proceso (para calculo de tiempo muerto)
                  (
                    SELECT MIN(fecha_inicio)
                    FROM lotes_detalles_empleados_asignados ldea_start
                    WHERE ldea_start.id_orden = o._id
                  ) AS fecha_inicio_primer_proceso
  
              FROM 
                  ordenes o
              LEFT JOIN 
                  customers c ON c._id = o.id_wp
              JOIN 
                  ordenes_productos op ON op.id_orden = o._id
              JOIN 
                  lotes_detalles ld ON ld.id_ordenes_productos = op._id
              $whereClause
              GROUP BY 
                  op._id
              ORDER BY 
                  o._id DESC
              LIMIT $limit";

      $data = $localConnection->goQuery($sql);
      $localConnection->disconnect();

      $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (PDOException $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } catch (Exception $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // REPORTE DE TIEMPOS DE FABRICACIÓN (POST version para muchos IDs)
  $app->post('/reports/manufacturing-time', function (Request $request, Response $response) {
    $body = json_decode($request->getBody()->getContents(), true);
    $localConnection = new LocalDB();

    try {
      $id_ordenes = isset($body['id_ordenes']) ? $body['id_ordenes'] : null; // Array of IDs
      $id_empleado = isset($body['id_empleado']) ? intval($body['id_empleado']) : null;
      $limit = isset($body['limit']) ? intval($body['limit']) : 100;

      // Limit max IDs to prevent heavy queries
      $maxIds = 100;


      if (!$id_ordenes || !is_array($id_ordenes) || count($id_ordenes) === 0) {
        $response->getBody()->write(json_encode(['error' => 'id_ordenes array is required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      // Sanitize and limit IDs
      $ids = array_map('intval', array_slice($id_ordenes, 0, $maxIds));
      $idsStr = implode(',', $ids);

      $whereClause = "WHERE o._id IN ($idsStr)";

      // OPTIMIZACIÓN: Si se filtra por empleado, incluir también las órdenes que terminó HOY
      // para que aparezcan en el reporte de eficiencia aunque ya no estén "activas" en su lista.
      if ($id_empleado) {
        $whereClause = "WHERE (o._id IN ($idsStr) OR o._id IN (
            SELECT DISTINCT id_orden 
            FROM lotes_detalles_empleados_asignados 
            WHERE id_empleado = $id_empleado 
            AND DATE(fecha_terminado) = CURDATE()
            AND id_departamento = (SELECT id_departamento FROM lotes_detalles_empleados_asignados WHERE id_orden IN ($idsStr) LIMIT 1)
        ))";
      }

      // --- OPTIMIZACIÓN: Usar CTE y evitar subconsultas correlacionadas ---

      // Filtro de empleado para el cálculo de tiempo real
      $empleadoCondition = "";
      if ($id_empleado) {
        $empleadoCondition = "AND sub_ldea.id_empleado = $id_empleado";
      }

      $sql = "
        WITH TiemposCalculados AS (
            SELECT 
                sub_ldea.id_orden,
                SUM(
                    CASE 
                        WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NOT NULL AND DATE(sub_ldea.fecha_terminado) = CURDATE() THEN 
                            TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, sub_ldea.fecha_terminado)
                        ELSE 0 
                    END
                ) AS tiempo_terminado,
                SUM(
                    CASE 
                        WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NULL THEN 
                            TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, NOW())
                        ELSE 0 
                    END
                ) AS tiempo_en_curso
            FROM lotes_detalles_empleados_asignados sub_ldea
            WHERE sub_ldea.id_orden IN ($idsStr)
            $empleadoCondition
            GROUP BY sub_ldea.id_orden
        )
        SELECT 
            o._id AS id_orden,
            o.status,
            op.name AS producto,
            op.cantidad,
            
            -- Tiempos Reales (divididos por productos físicos para balancear la suma agrupada)
            COALESCE(tc.tiempo_terminado, 0) / (SELECT COUNT(*) FROM ordenes_productos op_count JOIN products p_count ON p_count._id = op_count.id_woo AND p_count.fisico = 1 WHERE op_count.id_orden = o._id) AS totalRealTerminadas,
            COALESCE(tc.tiempo_en_curso, 0) / (SELECT COUNT(*) FROM ordenes_productos op_count JOIN products p_count ON p_count._id = op_count.id_woo AND p_count.fisico = 1 WHERE op_count.id_orden = o._id) AS totalRealEnCurso,
            
            -- Tiempos Proyectados (Correlacionados por producto y estado real de la tarea)
            (
                SELECT COALESCE(SUM(ptp_sub.tiempo * op.cantidad), 0)
                FROM products_tiempos_de_produccion ptp_sub
                WHERE ptp_sub.id_product = op.id_woo
                AND ptp_sub.id_departamento IN (
                    SELECT DISTINCT ldea_sub.id_departamento
                    FROM lotes_detalles_empleados_asignados ldea_sub
                    WHERE ldea_sub.id_orden = o._id
                    AND ldea_sub.id_empleado = " . ($id_empleado ?: "ldea_sub.id_empleado") . "
                    AND ldea_sub.fecha_terminado IS NOT NULL
                    AND DATE(ldea_sub.fecha_terminado) = CURDATE()
                )
            ) AS totalProjectedTerminadas,

            (
                SELECT COALESCE(SUM(ptp_sub.tiempo * op.cantidad), 0)
                FROM products_tiempos_de_produccion ptp_sub
                WHERE ptp_sub.id_product = op.id_woo
                AND ptp_sub.id_departamento IN (
                    SELECT DISTINCT ldea_sub.id_departamento
                    FROM lotes_detalles_empleados_asignados ldea_sub
                    WHERE ldea_sub.id_orden = o._id
                    AND ldea_sub.id_empleado = " . ($id_empleado ?: "ldea_sub.id_empleado") . "
                    AND ldea_sub.fecha_terminado IS NULL
                    AND ldea_sub.fecha_inicio IS NOT NULL
                )
            ) AS totalProjectedEnCurso,
            
            -- Legacy / Totales
            (COALESCE(tc.tiempo_terminado, 0) + COALESCE(tc.tiempo_en_curso, 0)) / (SELECT COUNT(*) FROM ordenes_productos op_count JOIN products p_count ON p_count._id = op_count.id_woo AND p_count.fisico = 1 WHERE op_count.id_orden = o._id) AS tiempo_total_segundos,
            (
                SELECT COALESCE(SUM(ptp_sub.tiempo * op.cantidad), 0)
                FROM products_tiempos_de_produccion ptp_sub
                WHERE ptp_sub.id_product = op.id_woo
                AND ptp_sub.id_departamento IN (
                    SELECT DISTINCT ldea_sub.id_departamento
                    FROM lotes_detalles_empleados_asignados ldea_sub
                    WHERE ldea_sub.id_orden = o._id
                    AND ldea_sub.id_empleado = " . ($id_empleado ?: "ldea_sub.id_empleado") . "
                )
            ) AS tiempo_proyectado_segundos,

            (
                SELECT MIN(fecha_inicio)
                FROM lotes_detalles_empleados_asignados ldea_start
                WHERE ldea_start.id_orden = o._id
            ) AS fecha_inicio_primer_proceso,
            (
                SELECT IF(COUNT(*) > 0 AND COUNT(*) = SUM(IF(fecha_terminado IS NOT NULL, 1, 0)), 1, 0)
                FROM lotes_detalles_empleados_asignados ldea_check
                WHERE ldea_check.id_orden = o._id
                  AND ldea_check.fecha_inicio IS NOT NULL
                  " . ($id_empleado ? "AND ldea_check.id_empleado = $id_empleado" : "") . "
            ) AS tarea_terminada
        FROM 
            ordenes o
        JOIN 
            ordenes_productos op ON op.id_orden = o._id
        JOIN
            products p_main ON p_main._id = op.id_woo AND p_main.fisico = 1
        LEFT JOIN 
            TiemposCalculados tc ON tc.id_orden = o._id
        $whereClause
        ORDER BY 
            o._id DESC
        LIMIT $limit
      ";

      $data = $localConnection->goQuery($sql);
      $localConnection->disconnect();

      $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (PDOException $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } catch (Exception $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // Obtener IDs de órdenes terminadas HOY por el empleado  
  $app->get('/empleados/terminadas-hoy/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    try {
      $sql = "SELECT DISTINCT
                  id_orden
              FROM lotes_detalles_empleados_asignados
              WHERE id_empleado = {$args['id_empleado']}
                AND id_departamento = {$args['id_departamento']}
                AND DATE(fecha_terminado) = CURDATE()";

      $result = $localConnection->goQuery($sql);
      $ids = [];
      if (is_array($result)) {
        foreach ($result as $row) {
          $ids[] = intval($row['id_orden']);
        }
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode($ids));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if (isset($localConnection))
        $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // Obtener IDs de órdenes completadas pero no pagadas del empleado  
  $app->get('/empleados/unpaid-orders/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    try {
      // Si existe un registro en pagos con fecha_pago NULL, el trabajo está terminado pero no pagado
      $sql = "SELECT DISTINCT
                  p.id_orden
              FROM pagos p
              WHERE p.id_empleado = {$args['id_empleado']}
                AND p.id_departamento = {$args['id_departamento']}
                AND p.fecha_pago IS NULL
              ORDER BY p.id_orden DESC";

      $result = $localConnection->goQuery($sql);

      // Asegurar que siempre devolvemos un array
      $unpaid_orders = is_array($result) ? $result : [];

      $localConnection->disconnect();

      $response->getBody()->write(json_encode($unpaid_orders, JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    } catch (Exception $e) {
      if (isset($localConnection)) {
        $localConnection->disconnect();
      }
      $errorMsg = ['status' => 'error', 'message' => $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

}; // Fin de la función que envuelve las rutas
