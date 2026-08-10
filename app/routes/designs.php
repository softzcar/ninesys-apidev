<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


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

    $sql = "SELECT a.id_orden, b._id id_revision, a._id id_diseno, b.detalles, b.estatus, b.revision, c.id_wp id_cliente, c.cliente_nombre cliente FROM disenos a JOIN revisiones b ON a._id = b.id_diseno JOIN ordenes c ON c._id = a.id_orden WHERE a.id_empleado = ? AND c.status != 'entregada' AND c.status != 'cancelada' AND c.status != 'terminado'";
    $object['revisiones'] = $localConnection->goQuery($sql, [(int) $args['id_empleado']]);

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
    $sql = "SELECT revision, estatus, id_diseno FROM revisiones WHERE id_orden = ? AND estatus = 'Aprobado'";
    $resp = $localConnection->goQuery($sql, [(int) $args['id_orden']]);
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

    $sql = 'UPDATE disenos SET linkdrive = ? WHERE id_orden = ?';
    $data = $localConnection->goQuery($sql, [$data['url'], $data['id']]);

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
      $localConnection->beginTransaction();
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

      $localConnection->commit();
      $response->getBody()->write(json_encode(['success' => true, 'message' => 'Tipo de diseño actualizado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
      if ($localConnection && $localConnection->inTransaction()) {
        $localConnection->rollback();
      }
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
            a._id = ?';

    $object['data'] = $localConnection->goQuery($sql, [(int) $args['id_orden']]);

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

    // Atomicidad FK: aprobación (revisiones + aprobacion_clientes) en una transacción
    $localConnection->beginTransaction();
    $localConnection->goQuery('UPDATE revisiones SET estatus = ? WHERE id_orden = ?', ['Aprobado', $data['id_orden']]);
    $object = $localConnection->goQuery('INSERT INTO aprobacion_clientes(id_orden, id_diseno) VALUES (?, ?)', [$data['id_orden'], $data['id_diseno']]);
    $localConnection->commit();

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

    // Verificar si el registro de diseños y ajuste ya existe
    $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'ajuste' AND id_diseno = ? ORDER BY tipo ASC";
    $dataRequest = $localConnection->goQuery($sql_tipo, [$data['id_diseno']]);
    if (count($dataRequest) > 0) {
      $ajuste = true;
    } else {
      $ajuste = false;
    }

    $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'personalizacion' AND id_diseno = ? ORDER BY tipo ASC";
    $dataRequest = $localConnection->goQuery($sql_tipo, [$data['id_diseno']]);
    $object['personalizacion'] = count($dataRequest);
    if (count($dataRequest) > 0) {
      $personalizacion = true;
    } else {
      $personalizacion = false;
    }

    $sqlord = 'SELECT id_orden, id_empleado FROM disenos WHERE _id = ?';
    $resultDiseno = $localConnection->goQuery($sqlord, [$data['id_diseno']]);

    // Atomicidad FK: ajustes/personalizaciones + sus pagos en una transacción
    $localConnection->beginTransaction();

    // Guardar cantidades de personalizaciones y ajustes
    if ($ajuste) {
      $localConnection->goQuery(
        "UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ? WHERE id_diseno = ? AND tipo = 'ajuste'",
        [$monto_ajustes, $data['id_diseno']]
      );
    } else {
      $localConnection->goQuery(
        "INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (?, ?, 'ajuste', ?)",
        [$data['id_diseno'], $resultDiseno[0]['id_orden'], $monto_ajustes]
      );
    }

    if ($personalizacion) {
      $localConnection->goQuery(
        "UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ? WHERE id_diseno = ? AND tipo = 'personalizacion'",
        [$monto_personalizacion, $data['id_diseno']]
      );
    } else {
      $localConnection->goQuery(
        "INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (?, ?, 'personalizacion', ?)",
        [$data['id_diseno'], $resultDiseno[0]['id_orden'], $monto_personalizacion]
      );
    }

    // Guardar los pagos
    if (empty($dataRequest)) {
      $localConnection->goQuery(
        "INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (?, ?, 'aprobado', ?, ?, 'ajuste')",
        [$monto_ajustes, $resultDiseno[0]['id_orden'], $comision_ajustes, $resultDiseno[0]['id_empleado']]
      );
      $localConnection->goQuery(
        "INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (?, ?, 'aprobado', ?, ?, 'personalización')",
        [$monto_personalizacion, $resultDiseno[0]['id_orden'], $comision_pesonalizacion, $resultDiseno[0]['id_empleado']]
      );
    } else {
      $localConnection->goQuery(
        "UPDATE pagos SET monto_pago = ?, cantidad = ? WHERE id_empleado = ? AND id_orden = ? AND detalle = 'ajuste'",
        [$comision_ajustes, $monto_ajustes, $resultDiseno[0]['id_empleado'], $resultDiseno[0]['id_orden']]
      );
      $localConnection->goQuery(
        "UPDATE pagos SET monto_pago = ?, cantidad = ? WHERE id_empleado = ? AND id_orden = ? AND detalle = 'personalización'",
        [$comision_pesonalizacion, $monto_personalizacion, $resultDiseno[0]['id_empleado'], $resultDiseno[0]['id_orden']]
      );
    }
    $localConnection->commit();

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener ajustes y personalizaciones de un diseno
  $app->get('/disenos/ajustes-y-personalizaciones/{id_diseno}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = ?';
    $object = $localConnection->goQuery($sql, [(int) $args['id_diseno']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener link de google drive
  $app->get('/disenos/link/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT linkdrive FROM disenos WHERE _id = ?';
    $object = $localConnection->goQuery($sql, [(int) $args['id']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object[0]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener codigo del diseño
  $app->get('/disenos/codigo/{id}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT codigo_diseno FROM disenos WHERE _id = ?';
    $object = $localConnection->goQuery($sql, [(int) $args['id']]);

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

    $sql = 'UPDATE disenos SET codigo_diseno = ? WHERE id_orden = ?';
    $data = $localConnection->goQuery($sql, [$data['cod'], $data['id']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($sql));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/disenador-asignado', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // Atomicidad FK: borrar diseño, revisiones y pagos en una sola transacción
    $localConnection->beginTransaction();

    // ELIMINAR DISEÑO Y REVISIONES
    $sql = 'DELETE FROM revisiones WHERE id_orden =  ' . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'] . ';';
    $object['response_delete_diseno_sql'] = $sql;
    $object['response_delete_diseno'] = $localConnection->goQuery($sql);
    $sql = 'DELETE FROM disenos WHERE _id =  ' . $data['id_diseno'] . ';';
    $object['response_delete_diseno_sql'] .= $sql;
    $object['response_delete_diseno'] = $localConnection->goQuery($sql);

    // ELIMINAR PAGOS
    $sql = 'DELETE FROM pagos WHERE id_empleado =  ' . $data['id_empleado'] . ' AND id_orden = ' . $data['id_orden'] . ';';
    $object['response_delete_pagos_sql'] = $sql;
    $object['response_delete_pagos'] = $localConnection->goQuery($sql);

    $localConnection->commit();
    $localConnection->disconnect();
    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener diseños sin asignar
  $app->get('/disenos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $localConnection_emp = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    // $sql = 'SELECT * FROM  empresas_usuarios a JOIN empresas_usuarios_departamentos WHERE id_empresa = ' . ID_EMPRESA; // IMPORTANTE EL DEPARTAMENTO ID = 9 ESTA ASIGNADO A DISEñO Y EN LA CONFIGURACION DE EMPRESA DEBE SER UN DEPARTAMENTO FIJO

    // DETERMINAR EL ID DEL DEPARTAMENTO DISEÕ DE LA EMPRESA (ESTE PROCEDIMIENTO DEBE SER CAMBIADO POR ASIGANR UN ID FIJO PARA EL DEPARTAMENTO DISEñO IGUAL PARA TODAS LAS EMPRESAS)

    $sql = "SELECT _id id FROM departamentos WHERE departamento LIKE 'Diseño' LIMIT 1";
    $response_id_departamento = $localConnection->goQuery($sql);

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
      WHERE  id_departamento = $depId AND b.activo = 1 AND b.id_empresa = " . ID_EMPRESA;
    $object['empleados'] = $localConnection_emp->goQuery($sql);

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
        LEFT JOIN (
            SELECT id_orden, MIN(_id) as first_id
            FROM disenos
            GROUP BY id_orden
        ) d_first ON b._id = d_first.id_orden
        LEFT JOIN disenos a ON
            a._id = d_first.first_id
        LEFT JOIN customers cus ON
            b.id_wp = cus._id
        LEFT JOIN api_empresas.empresas_usuarios e ON
            e.id_usuario = a.id_empleado
        WHERE
            (b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera')
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
            a.es_diseno = 1
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

    // Atomicidad FK: crear el diseño y su primera revisión en una transacción
    $localConnection->beginTransaction();

    // 3. Crear el nuevo "Proyecto de Diseño" en la tabla `disenos`
    $sqlDiseno = "INSERT INTO disenos (id_orden, id_empleado, id_product, tipo, origen) VALUES ({$id_orden}, {$id_empleado}, {$id_product}, '{$tipo_diseno}', 'agregado_posterior')";
    $resultDiseno = $localConnection->goQuery($sqlDiseno);

    // Verificar si hubo un error en la primera inserción
    if (isset($resultDiseno['status']) && $resultDiseno['status'] === 'error') {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear el proyecto de diseño.', 'details' => $resultDiseno['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 4. Obtener el ID del nuevo registro en `disenos`
    $id_diseno_nuevo = $resultDiseno['insert_id'];

    // Verificar que obtuvimos un ID válido
    if (empty($id_diseno_nuevo) || $id_diseno_nuevo == 0) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo obtener el ID del nuevo diseño.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 5. Crear la primera revisión para este nuevo proyecto en la tabla `revisiones`
    $sqlRevision = "INSERT INTO revisiones (id_orden, id_diseno, id_empleado, id_product, revision, tipo) VALUES ({$id_orden}, {$id_diseno_nuevo}, {$id_empleado}, {$id_product}, 1, '{$tipo_diseno}')";
    $resultRevision = $localConnection->goQuery($sqlRevision);

    // 5b. Obtener el ID de la revisión recién creada -- el frontend lo necesita
    // de inmediato en el mismo flujo de clic (ahora este endpoint se llama al
    // momento de "Enviar Diseño", no al crear el formulario vacío, así que no
    // hay un reload posterior del que depender para enterarse del ID nuevo).
    $id_revision_nuevo = $resultRevision['insert_id'];

    if (empty($id_revision_nuevo) || $id_revision_nuevo == 0) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo obtener el ID de la nueva revisión.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 6. Cerrar la conexión y enviar respuesta exitosa
    $localConnection->commit();
    $localConnection->disconnect();

    $payload = json_encode([
      'success' => true,
      'message' => 'Nuevo proyecto de diseño y su primera revisión han sido creados.',
      'sql_rev' => $sqlRevision,
      'id_diseno_nuevo' => $id_diseno_nuevo,
      'id_revision_nuevo' => $id_revision_nuevo
    ]);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);  // 201 Created
  });

  // Eliminar una revisión de diseño (borrador vacío o diseño ya enviado pero
  // sin aprobar). El diseñador debe poder retirar sus propios envíos mientras
  // trabaja en la orden; una vez aprobado, la revisión queda protegida. La
  // imagen física se elimina desde el frontend directamente contra
  // ninesys-cdn (mismo servicio al que ya sube las imágenes) -- este endpoint
  // solo maneja el registro en la base de datos.
  $app->delete('/disenos/revision/{id_revision}', function (Request $request, Response $response, array $args) {
    $id_revision = (int) $args['id_revision'];
    $localConnection = new LocalDB();

    $revisionRows = $localConnection->goQuery('SELECT id_diseno, estatus FROM revisiones WHERE _id = ?', [$id_revision]);
    if (empty($revisionRows)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'La revisión no existe.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $revision = $revisionRows[0];
    if ($revision['estatus'] === 'Aprobado') {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se puede eliminar un diseño ya aprobado.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    // NUNCA se borra el registro padre en `disenos`, aunque quede sin
    // revisiones -- ese registro es lo que mantiene al diseñador ASIGNADO a
    // la orden (ver el query de `items` en GET /sse/diseno/{id_empleado},
    // filtra por disenos.id_empleado). Borrarlo hacía que la orden
    // desapareciera por completo de la tabla del diseñador tras limpiar sus
    // borradores/envíos -- hallazgo real reportado por el usuario
    // (2026-08-10). Si queda sin revisiones, el diseñador simplemente ve el
    // proyecto vacío y puede volver a subir con "+ Nuevo Diseño".
    $localConnection->goQuery('DELETE FROM revisiones WHERE _id = ?', [$id_revision]);

    $response->getBody()->write(json_encode([
      'success' => true,
      'message' => 'Diseño eliminado correctamente.',
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    $sql = "SELECT a.tipo, a.id_orden, b.email username, b.nombre, b.id_usuario id_empleado FROM disenos a JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario  WHERE (a.tipo = 'modas' OR a.tipo = 'gráfico') AND a.id_empleado > 0 AND b.activo = 1";

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
            a.terminado = 0 AND a.id_orden = ?
        ';

    $object = $localConnection->goQuery($sql, [(int) $args['id_orden']]);

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
    WHERE a.id_empleado = ?
    AND a.terminado = 0
    ORDER BY a.id_orden ASC
    ';
    $object['items'] = $localConnection->goQuery($sql, [(int) $args['id_empleado']]);

    $sql = 'SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ?';
    $object['revisiones'] = $localConnection->goQuery($sql, [(int) $args['id_empleado']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // TODO eliminar ninesys antiguo => Obtener diseños pendientes por diseñador
  $app->get('/disenos/pendientes/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ? AND terminado = 0';

    $disenos = $localConnection->goQuery($sql, [(int) $args['id_empleado']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($disenos));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Obtener diseños terminados por diseñador
  $app->get('/disenos/terminados/{id_empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ? AND terminado = 1';

    $disenos = $localConnection->goQuery($sql, [(int) $args['id_empleado']]);

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

    // Atomicidad FK: crear el diseño y su primera revisión en una transacción
    $localConnection->beginTransaction();

    // 1. Crear el nuevo "Proyecto de Diseño" en la tabla `disenos`
    // Se usa un tipo por defecto que indica que fue asignado pero no definido.
    $sqlDiseno = "INSERT INTO disenos (id_orden, id_empleado, tipo, origen) VALUES ({$id_orden}, {$id_empleado}, 'Diseño Asignado', 'asignado')";
    $resultDiseno = $localConnection->goQuery($sqlDiseno);

    // Verificar si hubo un error en la inserción del diseño
    if (isset($resultDiseno['status']) && $resultDiseno['status'] === 'error') {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear el proyecto de diseño.', 'details' => $resultDiseno['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 2. Obtener el ID del nuevo registro en `disenos`
    $id_diseno_nuevo = $resultDiseno['insert_id'];

    // Verificar que obtuvimos un ID válido
    if (empty($id_diseno_nuevo) || $id_diseno_nuevo == 0) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo obtener el ID del nuevo diseño.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 3. Crear la primera revisión para este nuevo proyecto en la tabla `revisiones`
    $sqlRevision = "INSERT INTO revisiones (id_orden, id_diseno, id_empleado, revision, tipo) VALUES ({$id_orden}, {$id_diseno_nuevo}, {$id_empleado}, 1, 'Diseño Asignado')";
    $resultRevision = $localConnection->goQuery($sqlRevision);

    // Verificar si hubo un error en la inserción de la revisión
    if (isset($resultRevision['status']) && $resultRevision['status'] === 'error') {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Error al crear la revisión inicial.', 'details' => $resultRevision['message']]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // 4. Cerrar la conexión y enviar respuesta exitosa
    $localConnection->commit();
    $localConnection->disconnect();

    $payload = json_encode(['success' => true, 'message' => 'Diseño asignado y primera revisión creada correctamente.', 'id_diseno_nuevo' => $id_diseno_nuevo]);

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // Diseñador dar diseño por terminado
  $app->put('/disenos/close/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = 'UPDATE disenos SET terminado = 1 WHERE id_orden = ? AND id_empleado = ?';
    $asignacion = $localConnection->goQuery($sql, [$args['id_orden'], $args['empleado']]);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($asignacion));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
  /** Fin Diseños */
  $app->get('/disenos/images/{id_orden}', function (Request $request, Response $response, array $args) {
    // Directorio base de imágenes -- id_orden casteado a entero: sin esto,
    // un valor con "../" permitía listar directorios arbitrarios del
    // servidor (path traversal, hallazgo real 2026-08-06).
    $baseDir = 'images/' . ID_EMPRESA . '/orden_' . (int) $args['id_orden'];  // Array para almacenar las URLs de las imágenes
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

    $sql = "SELECT url_image FROM revisiones WHERE _id = ?";
    $data = $localConnection->goQuery($sql, [(int) $args['id_revision']]);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data[0], JSON_NUMERIC_CHECK));
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
              $tarea_a_migrar['id_orden'],
              $tarea_a_migrar['id_woo'],
              $tarea_a_migrar['progreso'],
              $tarea_a_migrar['id_ordenes_productos'],
              $tarea_a_migrar['id_reposicion'],
              $tarea_a_migrar['terminado'],
              $tarea_a_migrar['id_departamento'],
              $tarea_a_migrar['departamento'],
              $tarea_a_migrar['unidades_solicitadas'],
              $tarea_a_migrar['detalles'],
              $tarea_a_migrar['fecha_inicio'],
              $tarea_a_migrar['fecha_terminado'],
              $tarea_a_migrar['moment']
            ]);
            $new_lote_detalle_id = $pdo_nueva->lastInsertId();

            // Insertar en lotes_detalles_empleados_asignados
            $sql_insert_asignado = 'INSERT INTO lotes_detalles_empleados_asignados (id_lotes_detalles, id_orden, id_empleado, id_departamento, procentaje_comision, progreso, terminado, fecha_inicio, fecha_terminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt_insert_asignado = $pdo_nueva->prepare($sql_insert_asignado);
            $stmt_insert_asignado->execute([
              $new_lote_detalle_id,
              $tarea_a_migrar['id_orden'],
              $tarea_a_migrar['id_empleado'],
              $tarea_a_migrar['id_departamento'],
              100.0,
              $tarea_a_migrar['progreso'],
              $tarea_a_migrar['terminado'],
              $tarea_a_migrar['fecha_inicio'],
              $tarea_a_migrar['fecha_terminado']
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

  $app->delete('/disenos/images/{id_orden}/{image_name}', function (Request $request, Response $response, array $args) {
    // Path traversal real (hallazgo 2026-08-06): ni id_orden ni image_name
    // se validaban antes de pasar la ruta construida a unlink() -- un
    // image_name con "../../../algo" permitía BORRAR cualquier archivo del
    // servidor con permisos de escritura del proceso PHP, no solo imágenes.
    // Corregido: id_orden casteado a entero, basename() sobre image_name
    // (descarta cualquier componente de directorio), y verificación de que
    // la ruta resuelta siga dentro del directorio esperado antes de borrar.
    $baseDir = 'images/' . ID_EMPRESA . '/orden_' . (int) $args['id_orden'];
    $imageName = basename($args['image_name']);
    $imagePath = $baseDir . '/' . $imageName;

    $realBase = realpath($baseDir);
    $realImage = realpath($imagePath);
    $dentroDelDirectorioEsperado = $realBase !== false && $realImage !== false
      && strpos($realImage, $realBase . DIRECTORY_SEPARATOR) === 0;

    // Verificar si la imagen existe
    if ($dentroDelDirectorioEsperado && file_exists($imagePath)) {
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
    // id_orden casteado a entero: sin esto, un valor con "../" permitía
    // crear directorios y escribir el archivo subido en una ruta arbitraria
    // del servidor (path traversal, hallazgo real 2026-08-06).
    $id_orden = (int) $args['id_orden'];
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



  /* $app->get('/api/inventario/template-excel', function (Request $request, Response $response) {
    try {
      $localConnection = new LocalDB();

      // Obtener departamentos para la lista de validación
      $departamentos = $localConnection->goQuery('SELECT _id, departamento FROM departamentos');
      if (!is_array($departamentos)) {
        $departamentos = [];
      }

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
      $headersInventario = ['SKU', 'Nombre', 'Cantidad', 'Unidad', 'Costo', 'Rendimiento', 'Departamento'];
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
        if (is_array($rollo) && isset($rollo['sku'])) {  // Add this check
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
  }); */

  // RUTAS DE INVENTARIO Y PRODUCTOS


}; // Fin de la función que envuelve las rutas
