<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** Fin asignacion */

  // =========================================================
  // BATCH: Actualizar orden de filas de órdenes (1 request)
  // =========================================================
  $app->post('/ordenes/actualizar-filas-batch', function (Request $request, Response $response) {
    // Slim no parsea application/json automáticamente; leemos el cuerpo raw
    $rawBody = (string) $request->getBody();
    $ordenes = json_decode($rawBody, true);

    // Fallback: intentar con form-encoded (campo 'ordenes')
    if (empty($ordenes)) {
      $body = $request->getParsedBody();
      if (isset($body['ordenes'])) {
        $ordenes = json_decode($body['ordenes'], true);
      }
    }

    if (empty($ordenes) || !is_array($ordenes)) {
      $response->getBody()->write(json_encode(['success' => false, 'message' => 'Payload vacío o inválido.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    $localConnection->beginTransaction();

    try {
      foreach ($ordenes as $item) {
        $id_orden   = intval($item['id_orden']   ?? 0);
        $orden_fila = intval($item['orden_fila'] ?? 0);

        if ($id_orden <= 0 || $orden_fila <= 0) {
          throw new \Exception("Datos inválidos para id_orden={$id_orden}, orden_fila={$orden_fila}");
        }

        $sql = "UPDATE ordenes_fila_orden SET orden_fila = {$orden_fila} WHERE id_orden = {$id_orden}";
        $localConnection->goQuery($sql);
      }

      $localConnection->commit();
      $localConnection->disconnect();

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (\Throwable $e) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      error_log('Error en /ordenes/actualizar-filas-batch: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['success' => false, 'message' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

  // =========================================================
  // BATCH: Actualizar orden de filas de reposiciones (1 request)
  // =========================================================
  $app->post('/reposiciones/actualizar-filas-batch', function (Request $request, Response $response) {
    // Slim no parsea application/json automáticamente; leemos el cuerpo raw
    $rawBody = (string) $request->getBody();
    $reposiciones = json_decode($rawBody, true);

    // Fallback: intentar con form-encoded (campo 'reposiciones')
    if (empty($reposiciones)) {
      $body = $request->getParsedBody();
      if (isset($body['reposiciones'])) {
        $reposiciones = json_decode($body['reposiciones'], true);
      }
    }

    if (empty($reposiciones) || !is_array($reposiciones)) {
      $response->getBody()->write(json_encode(['success' => false, 'message' => 'Payload vacío o inválido.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();
    $localConnection->beginTransaction();

    try {
      foreach ($reposiciones as $item) {
        $id_reposicion = intval($item['id_reposicion'] ?? 0);
        $orden_fila    = intval($item['orden_fila']    ?? 0);

        if ($id_reposicion <= 0 || $orden_fila <= 0) {
          throw new \Exception("Datos inválidos para id_reposicion={$id_reposicion}, orden_fila={$orden_fila}");
        }

        $sql = "UPDATE ordenes_fila_reposiciones SET orden_fila = {$orden_fila} WHERE id_reposicion = {$id_reposicion}";
        $localConnection->goQuery($sql);
      }

      $localConnection->commit();
      $localConnection->disconnect();

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (\Throwable $e) {
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();
      error_log('Error en /reposiciones/actualizar-filas-batch: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['success' => false, 'message' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
  });

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
    // Modificado para calcular paso y progreso desde lotes_detalles_empleados_asignados
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
            -- Paso calculado desde lotes_detalles_empleados_asignados
            -- Si paso_actual es NULL (todos terminados), mostrar 'Terminado'
            -- Si no hay departamentos asignados, mostrar 'Por asignar'
            CASE 
                WHEN prog_info.total_departamentos = 0 OR prog_info.total_departamentos IS NULL THEN 'Por asignar'
                WHEN prog_info.paso_actual IS NULL THEN 'Terminado'
                ELSE prog_info.paso_actual
            END AS paso,
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
            /* INICIO: COLUMNAS DE PROGRESO DESDE lotes_detalles_empleados_asignados */
            /* ================================================================== */

            COALESCE(prog_info.departamentos_terminados, 0) AS progreso_paso_valor,
            COALESCE(prog_info.total_departamentos, 0) AS progreso_total_pasos,
            COALESCE(
                ROUND(
                    (prog_info.departamentos_terminados * 100) / NULLIF(prog_info.total_departamentos, 0)
                ), 0
            ) AS progreso_porcentaje

            /* ================================================================== */
            /* FIN: COLUMNAS DE PROGRESO                                          */
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
            -- Subconsulta para calcular progreso real desde lotes_detalles_empleados_asignados
            SELECT
                ldea.id_orden,
                COUNT(DISTINCT ldea.id_departamento) AS total_departamentos,
                COUNT(DISTINCT CASE WHEN ldea.fecha_terminado IS NOT NULL THEN ldea.id_departamento END) AS departamentos_terminados,
                -- Departamento actual: el primero (por orden_proceso) que no tiene fecha_terminado
                -- Si todos están terminados, retorna NULL (se maneja arriba con CASE)
                (
                    SELECT dep.departamento
                    FROM lotes_detalles_empleados_asignados ldea2
                    JOIN departamentos dep ON dep._id = ldea2.id_departamento
                    WHERE ldea2.id_orden = ldea.id_orden
                      AND ldea2.fecha_terminado IS NULL
                    ORDER BY dep.orden_proceso ASC
                    LIMIT 1
                ) AS paso_actual
            FROM
                lotes_detalles_empleados_asignados ldea
            GROUP BY
                ldea.id_orden
        ) AS prog_info ON a._id = prog_info.id_orden
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
        cat.nombre as category_name,
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
        LEFT JOIN categories cat ON FIND_IN_SET(cat._id, p.category_ids)
        LEFT JOIN lotes_fisicos c ON c.id_orden = b._id
        LEFT JOIN ordenes a ON
            b.id_orden = a._id
        LEFT JOIN products_comisiones pc ON pc.id_product = b.id_woo
        WHERE
            (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND b.category_name != 'Diseños' -- AND p.fisico = 1
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
        a.id_departamento_solicitante,
        a.id_departamento,
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
            (a.aprobada IS NULL OR (a.aprobada = 0 AND a.detalle IS NULL)) AND a.id_empleado IS NULL
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
        if (isset($item['carga_familiar']) && $item['carga_familiar'] !== null && $item['carga_familiar'] !== '[]') {
          $item['carga_familiar'] = json_decode($item['carga_familiar'], true);
        } else {
          $item['carga_familiar'] = [];
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
                    AND (b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera')
                    AND EXISTS (
                        SELECT 1 
                        FROM ordenes_productos op
                        JOIN products p ON op.id_woo = p._id
                        WHERE op.id_orden = b._id AND p.es_diseno = 1
                    )
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
                AND EXISTS (
                    SELECT 1 
                    FROM ordenes_productos op
                    JOIN products p ON op.id_woo = p._id
                    WHERE op.id_orden = a.id_orden AND p.es_diseno = 1
                )
            ORDER BY
                a.id_orden
            ASC
        ";
    $obj['sql_revisiones'] = $sql;
    $obj['revisiones'] = $localConnection->goQuery($sql);

    $sql = 'SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ' . $args['id_empleado'] . ' AND EXISTS (SELECT 1 FROM ordenes_productos op JOIN products p ON op.id_woo = p._id WHERE op.id_orden = b.id_orden AND p.es_diseno = 1)';
    $obj['ajustes'] = $localConnection->goQuery($sql);

    $sql = 'SELECT pro._id id_producto, pro.product, pro.comision FROM products pro WHERE pro.es_diseno = 1 ORDER BY pro.product ASC;';
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
            COALESCE(NULLIF(a.detalle_emisor, \'\'), a.detalle) AS detalle
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

  // -----------------------------------------------------------------------
  // GET /reposicion/{id_reposicion}/departamentos-cola
  // Devuelve la cadena de departamentos entre inicio y destino de una
  // reposición, marcando cuáles están excluidos.
  // -----------------------------------------------------------------------
  $app->get('/reposicion/{id_reposicion}/departamentos-cola', function (Request $request, Response $response, array $args) {
    $id_reposicion = intval($args['id_reposicion']);
    if ($id_reposicion <= 0) {
      $response->getBody()->write(json_encode(['error' => 'ID inválido']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    // Obtener datos de la reposición (departamento inicio y destino)
    $repoData = $localConnection->goQuery(
      "SELECT id_departamento_solicitante, id_departamento FROM reposiciones WHERE _id = {$id_reposicion}"
    );

    if (empty($repoData)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Reposición no encontrada']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $id_depto_a = intval($repoData[0]['id_departamento_solicitante']);
    $id_depto_b = intval($repoData[0]['id_departamento']);

    // Obtener orden_proceso de ambos departamentos
    $opData = $localConnection->goQuery(
      "SELECT _id, orden_proceso FROM departamentos WHERE _id IN ({$id_depto_a}, {$id_depto_b})"
    );

    $op_a = 0;
    $op_b = 0;
    foreach ($opData as $row) {
      if ((int)$row['_id'] === $id_depto_a) $op_a = (int)$row['orden_proceso'];
      if ((int)$row['_id'] === $id_depto_b) $op_b = (int)$row['orden_proceso'];
    }

    // El inicio siempre es el de menor orden_proceso y el fin el de mayor orden_proceso
    $op_inicio = min($op_a, $op_b);
    $op_fin    = max($op_a, $op_b);
    $id_depto_inicio = ($op_a === $op_inicio) ? $id_depto_a : $id_depto_b;
    $id_depto_fin    = ($op_b === $op_fin) ? $id_depto_b : $id_depto_a;

    // Obtener toda la cadena de departamentos intermedios
    $sql = "SELECT
        d._id AS id_departamento,
        d.departamento,
        d.orden_proceso,
        CASE WHEN rde.id_departamento IS NOT NULL THEN 1 ELSE 0 END AS excluido,
        CASE WHEN d._id = {$id_depto_inicio} THEN 1 ELSE 0 END AS es_inicio,
        CASE WHEN d._id = {$id_depto_fin}    THEN 1 ELSE 0 END AS es_destino
      FROM departamentos d
      LEFT JOIN reposiciones_departamentos_excluidos rde
        ON rde.id_reposicion = {$id_reposicion} AND rde.id_departamento = d._id
      WHERE d.asignar_numero_de_paso = 1
        AND d.orden_proceso >= {$op_inicio}
        AND d.orden_proceso <= {$op_fin}
      ORDER BY d.orden_proceso ASC";

    $departamentos = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($departamentos));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // -----------------------------------------------------------------------
  // POST /reposicion/{id_reposicion}/excluir-departamento
  // Body: { id_departamento: int, excluir: bool }
  // Agrega o elimina una exclusión. Inicio y destino no pueden excluirse.
  // -----------------------------------------------------------------------
  $app->post('/reposicion/{id_reposicion}/excluir-departamento', function (Request $request, Response $response, array $args) {
    $id_reposicion = intval($args['id_reposicion']);
    $data = $request->getParsedBody();
    $id_departamento = intval($data['id_departamento'] ?? 0);
    $excluir = filter_var($data['excluir'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if ($id_reposicion <= 0 || $id_departamento <= 0) {
      $response->getBody()->write(json_encode(['error' => 'Parámetros inválidos']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    // Verificar que la reposición existe y obtener inicio/fin
    $repoData = $localConnection->goQuery(
      "SELECT id_departamento_solicitante, id_departamento FROM reposiciones WHERE _id = {$id_reposicion}"
    );
    if (empty($repoData)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Reposición no encontrada']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $id_depto_inicio = intval($repoData[0]['id_departamento_solicitante']);
    $id_depto_fin    = intval($repoData[0]['id_departamento']);

    // No se puede excluir el departamento de inicio ni el de destino
    if ($id_departamento === $id_depto_inicio || $id_departamento === $id_depto_fin) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se puede excluir el departamento de inicio ni el de destino']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    if ($excluir) {
      $sql = "INSERT IGNORE INTO reposiciones_departamentos_excluidos (id_reposicion, id_departamento) VALUES ({$id_reposicion}, {$id_departamento})";
    } else {
      $sql = "DELETE FROM reposiciones_departamentos_excluidos WHERE id_reposicion = {$id_reposicion} AND id_departamento = {$id_departamento}";
    }

    $result = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['success' => true, 'excluido' => $excluir, 'result' => $result]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->get('/temp-check-repo/{id}', function (Request $request, Response $response, array $args) {
    $id = intval($args['id']);
    $db = new LocalDB();
    $repo = $db->goQuery("SELECT _id, id_orden, id_departamento_solicitante, id_departamento FROM reposiciones WHERE _id = {$id}");
    $deptos = $db->goQuery("SELECT _id, departamento, orden_proceso, asignar_numero_de_paso FROM departamentos ORDER BY orden_proceso ASC");
    $db->disconnect();
    $response->getBody()->write(json_encode(['repo' => $repo, 'departamentos' => $deptos], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
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
                COALESCE(
                  SUM((inm.valor_inicial - inm.valor_final) * (inv.costo / NULLIF(inv.cantidad_inicial, 0))), 0
                ) + 
                COALESCE(
                  (SELECT SUM(
                    (t.c * (ic.costo / NULLIF(ic.cantidad_inicial, 0))) +
                    (t.m * (im.costo / NULLIF(im.cantidad_inicial, 0))) +
                    (t.y * (iy.costo / NULLIF(iy.cantidad_inicial, 0))) +
                    (t.k * (ik.costo / NULLIF(ik.cantidad_inicial, 0)))
                  )
                  FROM tintas t
                  LEFT JOIN inventario ic ON ic._id = 3
                  LEFT JOIN inventario im ON im._id = 4
                  LEFT JOIN inventario iy ON iy._id = 5
                  LEFT JOIN inventario ik ON ik._id = 6
                  WHERE t.id_orden = re.id_orden AND t.moment >= re.moment
                  ), 0
                ) +
                COALESCE(
                  (SELECT SUM(monto_pago) FROM pagos WHERE id_reposicion = re._id), 0
                ) material_consumido,
                '$' as unidad,
                DATE_FORMAT(re.moment, '%d/%m/%Y') fecha_creacion,
                DATE_FORMAT(re.moment, '%h:%i %p') hora_creacion
            FROM
                reposiciones re
            LEFT JOIN api_empresas.empresas_usuarios em_asignado ON re.id_empleado = em_asignado.id_usuario
            LEFT JOIN ordenes ord On ord._id = re.id_orden
            JOIN api_empresas.empresas_usuarios em_emisor ON re.id_empleado_emisor = em_emisor.id_usuario
            JOIN ordenes_productos op ON op._id = re.id_ordenes_productos 
            LEFT JOIN inventario_movimientos inm ON (inm.id_reposicion = re._id) OR (inm.id_reposicion IS NULL AND inm.id_orden = re.id_orden AND inm.moment >= re.moment)
            LEFT JOIN inventario inv ON inv._id = inm.id_producto
            {$whereParams}
            GROUP BY re._id
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

  $app->get('/reposicion-detalles/{id_reposicion}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    $id_reposicion = $args['id_reposicion'];

    // 1. Obtener datos clave de la reposición
    $sqlRepo = "SELECT id_orden, id_empleado FROM reposiciones WHERE _id = {$id_reposicion}";
    $repoData = $localConnection->goQuery($sqlRepo);

    if (empty($repoData)) {
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $id_orden = $repoData[0]['id_orden'];
    $id_empleado = $repoData[0]['id_empleado'];

    // 2. Consultar desglose de movimientos
    $sql = "SELECT 
              inv.insumo,
              inv.unidad,
              inm.valor_inicial,
              inm.valor_final,
              (inm.valor_inicial - inm.valor_final) as cantidad_consumida,
              (inv.costo / NULLIF(inv.cantidad_inicial, 0)) as costo_unitario,
              ((inm.valor_inicial - inm.valor_final) * (inv.costo / NULLIF(inv.cantidad_inicial, 0))) as costo_total,
              DATE_FORMAT(inm.moment, '%d/%m/%Y %h:%i %p') as fecha,
              inv.color,
              COALESCE(inv.id_catalogo, 0) as id_catalogo
            FROM inventario_movimientos inm
            JOIN inventario inv ON inv._id = inm.id_producto
            WHERE 
              (inm.id_reposicion = {$id_reposicion}) 
              OR 
              (inm.id_reposicion IS NULL AND inm.id_orden = {$id_orden} AND inm.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}))
            
            UNION ALL

            SELECT 
              'Tinta Cyan' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.c as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.c * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, DATE_FORMAT(t.moment, '%d/%m/%Y %h:%i %p') as fecha, 'CYAN' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
            FROM tintas t JOIN inventario i ON i._id = 3 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})

            UNION ALL

            SELECT 
              'Tinta Magenta' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.m as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.m * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, DATE_FORMAT(t.moment, '%d/%m/%Y %h:%i %p') as fecha, 'MAGENTA' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
            FROM tintas t JOIN inventario i ON i._id = 4 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})

            UNION ALL

            SELECT 
              'Tinta Yellow' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.y as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.y * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, DATE_FORMAT(t.moment, '%d/%m/%Y %h:%i %p') as fecha, 'YELLOW' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
            FROM tintas t JOIN inventario i ON i._id = 5 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})

            UNION ALL

            SELECT 
              'Tinta Black' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.k as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.k * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, DATE_FORMAT(t.moment, '%d/%m/%Y %h:%i %p') as fecha, 'BLACK' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
            FROM tintas t JOIN inventario i ON i._id = 6 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})

            UNION ALL

            SELECT 
                CONCAT('Mano de Obra - ', u.nombre) as insumo,
                'UND' as unidad,
                0 as valor_inicial,
                0 as valor_final,
                1 as cantidad_consumida,
                p.monto_pago as costo_unitario,
                p.monto_pago as costo_total,
                DATE_FORMAT(p.moment, '%d/%m/%Y %h:%i %p') as fecha,
                'N/A' as color,
                9999 as id_catalogo
            FROM pagos p
            JOIN api_empresas.empresas_usuarios u ON u.id_usuario = p.id_empleado
            WHERE p.id_reposicion = {$id_reposicion} AND p.monto_pago > 0
            
            ORDER BY id_catalogo ASC, fecha DESC";

    $items = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($items));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/produccion/reposicion', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
    $producto = $localConnection->goQuery($sql)[0];
    $id_orden = $producto['id_orden'];

    $myDate = new CustomTime();
    $now = $myDate->today();

    // 1. DETERMINAR SI ES CREADA DIRECTAMENTE POR EL SUPERVISOR
    $es_supervisor = isset($data['creado_por_supervisor']) && $data['creado_por_supervisor'] == '1';

    // 2. OBTENER ORDEN PROCESO ACTUAL DE LA ORDEN PRINCIPAL, ASÍ COMO EL DEPARTAMENTO Y OPERARIO ACTIVOS
    $sqlActiveDepts = "SELECT dep.orden_proceso, dep.departamento, ldea.id_departamento, ldea.id_empleado
                       FROM lotes_detalles_empleados_asignados ldea
                       JOIN departamentos dep ON dep._id = ldea.id_departamento
                       WHERE ldea.id_orden = {$id_orden} AND ldea.fecha_terminado IS NULL
                       ORDER BY dep.orden_proceso ASC LIMIT 1";
    $activeDeptRes = $localConnection->goQuery($sqlActiveDepts);
    
    // Si no hay tareas pendientes de la orden, buscamos el departamento de mayor orden_proceso de la orden ya terminado
    if (empty($activeDeptRes)) {
      $sqlMaxFinished = "SELECT dep.orden_proceso, dep.departamento, ldea.id_departamento, ldea.id_empleado
                         FROM lotes_detalles_empleados_asignados ldea
                         JOIN departamentos dep ON dep._id = ldea.id_departamento
                         WHERE ldea.id_orden = {$id_orden}
                         ORDER BY dep.orden_proceso DESC LIMIT 1";
      $activeDeptRes = $localConnection->goQuery($sqlMaxFinished);
    }

    $orden_proceso_actual_orden = 9999; // Valor alto por defecto si no hay info
    $nombre_departamento_actual = "Finalizado";
    $id_depto_actual_orden = NULL;
    $id_empleado_actual_orden = NULL;

    if (!empty($activeDeptRes)) {
      $orden_proceso_actual_orden = intval($activeDeptRes[0]['orden_proceso']);
      $nombre_departamento_actual = $activeDeptRes[0]['departamento'];
      $id_depto_actual_orden = intval($activeDeptRes[0]['id_departamento']);
      $id_empleado_actual_orden = !empty($activeDeptRes[0]['id_empleado']) ? intval($activeDeptRes[0]['id_empleado']) : NULL;
    }

    // 3. CONSULTAR ORDEN PROCESO DE LOS DEPARTAMENTOS DE LA REPOSICIÓN
    $id_depto_inicio = intval($data['id_departamento']);
    
    // Consultar orden_proceso del departamento de inicio
    $sqlDeptoInicioInfo = "SELECT orden_proceso, departamento FROM departamentos WHERE _id = {$id_depto_inicio}";
    $deptoInicioRes = $localConnection->goQuery($sqlDeptoInicioInfo);
    
    $orden_proceso_inicio = 0;
    $nombre_depto_inicio = "";
    if (!empty($deptoInicioRes)) {
      $orden_proceso_inicio = intval($deptoInicioRes[0]['orden_proceso']);
      $nombre_depto_inicio = $deptoInicioRes[0]['departamento'];
    }

    // 4. VALIDAR LA REGLA DE PROGRESIÓN DE INICIO (Inicio <= orden_proceso_actual_orden)
    if ($orden_proceso_inicio > $orden_proceso_actual_orden) {
      $localConnection->disconnect();
      $errorMsg = "No se puede emitir la reposición iniciando en el departamento de '{$nombre_depto_inicio}'. El paso de inicio no puede ser posterior a la etapa actual de la orden principal ('{$nombre_departamento_actual}').";
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => $errorMsg]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);
    }

    if ($es_supervisor) {
      // Flujo de Supervisor: Aprobación directa, destino y emisor resueltos dinámicamente
      $id_depto_fin = !is_null($id_depto_actual_orden) ? $id_depto_actual_orden : $id_depto_inicio;
      $id_empleado_fin = !is_null($id_empleado_actual_orden) ? $id_empleado_actual_orden : intval($data['id_empleado_supervisor'] ?? 0);
      $id_empleado_inicio = intval($data['id_empleado']);

      $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor, detalle, id_departamento_solicitante, id_departamento)';
      $values = '(';
      $values .= "'" . $now . "',";
      $values .= '1,'; // Reposición aprobada automáticamente
      $values .= $id_orden . ',';
      $values .= $id_empleado_inicio . ','; // Empleado asignado para corregir
      $values .= $id_empleado_fin . ','; // Empleado destinatario del final (quien recibe el material al terminar)
      $values .= $producto['_id'] . ',';
      $values .= intval($data['cantidad']) . ',';
      // quote() de MariaDB ya devuelve el valor con comillas simples incluidas, ej: 'texto'
      $escaped_detalle = $localConnection->goQuery("SELECT quote(?) AS q", [$data['detalle']])[0]["q"];
      $values .= $escaped_detalle . ','; // detalle_emisor
      $values .= $escaped_detalle . ','; // detalle (supervisor direct description fallback)
      $values .= $id_depto_fin . ','; // Destino final
      $values .= $id_depto_inicio; // Paso activo inicial
      $values .= ')';
    } else {
      // Flujo ordinario: Solicitud de empleado
      $id_depto_fin = intval($data['id_departamento_solicitante']);

      // 3b. VALIDAR QUE EL DEPARTAMENTO DE FINALIZACIÓN NO SEA POSTERIOR AL DEPARTAMENTO ACTUAL DE LA ORDEN PRINCIPAL
      $sqlDeptoFinInfo = "SELECT orden_proceso, departamento FROM departamentos WHERE _id = {$id_depto_fin}";
      $deptoFinRes = $localConnection->goQuery($sqlDeptoFinInfo);
      $orden_proceso_fin = 0;
      $nombre_depto_fin = "";
      if (!empty($deptoFinRes)) {
        $orden_proceso_fin = intval($deptoFinRes[0]['orden_proceso']);
        $nombre_depto_fin = $deptoFinRes[0]['departamento'];
      }

      if ($orden_proceso_fin > $orden_proceso_actual_orden) {
        $localConnection->disconnect();
        $errorMsg = "No se puede emitir la reposición finalizando en el departamento de '{$nombre_depto_fin}'. El paso de destino no puede ser posterior a la etapa actual de la orden principal ('{$nombre_departamento_actual}').";
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $errorMsg]));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $campos = '(moment, aprobada, id_orden, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor, id_departamento_solicitante)';
      $values = '(';
      $values .= "'" . $now . "',";
      $values .= 'NULL,';
      $values .= $id_orden . ',';
      $values .= intval($data['id_empleado']) . ',';
      $values .= $producto['_id'] . ',';
      $values .= intval($data['cantidad']) . ',';
      // quote() de MariaDB ya devuelve el valor con comillas simples incluidas
      $values .= $localConnection->goQuery("SELECT quote(?) AS q", [$data['detalle']])[0]["q"] . ",";
      $values .= $id_depto_fin;
      $values .= ')';
    }

    // Limpiamos los quotes simples agregados por SELECT quote(?) para evitar sintaxis inválida de inserción SQL
    $values = str_replace(["'\"'", "\"'\""], ["'", "'"], $values);

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
     * VAMOS A SACAR LA PARTE DE LA CREACIÓN DE LA REPOSICIÓN PUES ESTA LA ESTA HACIENDO EL EMPLEADO
     * AQUI VAMOS A REASIGNAR EMPLEADOS Y DEMÁS COSAS QUE CONLLEVAN LA REPOSICIÓN
     */
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();

    // PREPARAR FECHAS
    $myDate = new CustomTime();
    $now = $myDate->today();

    $object = [];

    // Validar si la reposicion ha sido aprobada
    if ($data['aprobada'] === '0') {
      $sql = "UPDATE reposiciones SET aprobada = 0, detalle = '" . $data['detalle'] . "' WHERE _id = " . $data['id_reposicion'];
      $aprobacion = $localConnection->goQuery($sql);
      $object['resp_reposiciones'] = $aprobacion;
    } else {
      $sql = "UPDATE reposiciones SET aprobada = 1, detalle = '" . $data['detalle'] . "', id_empleado = " . $data['id_empleado'] . ', id_departamento = ' . $data['id_departamento'] . ' WHERE _id = ' . $data['id_reposicion'];
      $aprobacion = $localConnection->goQuery($sql);
      $object['resp_reposiciones'] = $aprobacion;
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
    $id = intval($args['id']);

    // ========== VALIDACIONES ==========
    if ($id <= 0) {
      return ApiResponse::validationError($response, 'ID de orden inválido');
    }

    $localConnection = new LocalDB();

    // ========== INICIAR TRANSACCIÓN ==========
    $localConnection->beginTransaction();

    try {
      // Verificar que la orden existe
      $sqlCheck = "SELECT _id, status FROM ordenes WHERE _id = ?";
      $orden = $localConnection->goQuery($sqlCheck, [$id]);

      if (empty($orden)) {
        throw new \Exception('La orden no existe');
      }

      $depCheck = $localConnection->goQuery("SELECT COUNT(*) cnt FROM inventario_movimientos WHERE id_orden = ?", [$id]);
      $hasMovs = intval($depCheck[0]['cnt'] ?? 0) > 0;
      $metaCheck = $localConnection->goQuery("SELECT COUNT(*) cnt FROM product_insumos_asignados pia JOIN ordenes_productos op ON op.id_woo = pia.id_product AND op.id_size = pia.id_talla WHERE op.id_orden = ?", [$id]);
      $hasMeta = intval($metaCheck[0]['cnt'] ?? 0) > 0;
      if ($hasMeta && !$hasMovs) {
        throw new \Exception('La orden requiere consumo de insumos y no registra movimientos de inventario');
      }
      // Actualizar estado
      $sql = "UPDATE ordenes SET status = 'terminada' WHERE _id = ?";
      $localConnection->goQuery($sql, [$id]);

      // ========== CONFIRMAR TRANSACCIÓN ==========
      $localConnection->commit();
      $localConnection->disconnect();

      return ApiResponse::success($response, 'Orden #' . $id . ' terminada correctamente', [
        'id_orden' => $id,
        'nuevo_status' => 'terminado'
      ]);

    } catch (\Throwable $e) {
      // ========== REVERTIR TRANSACCIÓN ==========
      if ($localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $localConnection->disconnect();

      error_log('Error en /produccion/terminar/' . $id . ': ' . $e->getMessage());

      return ApiResponse::serverError($response, 'Error al terminar la orden: ' . $e->getMessage(), $e);
    }
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

    try {
      // VERIFICAR STATUS DE LA ORDEN
      $sql = 'SELECT status from ordenes WHERE _id = ' . $args['id_orden'];
      $tmpStatus = $localConnection->goQuery($sql);

      if (!empty($tmpStatus)) {
        $object['status'] = $tmpStatus[0]['status'];
      }

      // CALCULAR PROGRESO DESDE lotes_detalles_empleados_asignados
      $sql = "SELECT 
                ldea.id_departamento,
                dep.departamento,
                dep.orden_proceso,
                ldea.fecha_inicio,
                ldea.fecha_terminado
              FROM lotes_detalles_empleados_asignados ldea
              JOIN departamentos dep ON dep._id = ldea.id_departamento
              WHERE ldea.id_orden = " . $args['id_orden'] . "
              GROUP BY ldea.id_departamento
              ORDER BY dep.orden_proceso ASC";
      $departamentos = $localConnection->goQuery($sql);

      $totalDepartamentos = count($departamentos);
      $departamentosTerminados = 0;
      $pasoActual = null;

      foreach ($departamentos as $dep) {
        if ($dep['fecha_terminado'] !== null) {
          $departamentosTerminados++;
        } elseif ($pasoActual === null) {
          // El primer departamento sin fecha_terminado es el paso actual
          $pasoActual = $dep['departamento'];
        }
      }

      // Determinar paso y departamento a mostrar
      if ($totalDepartamentos === 0) {
        $object['paso'] = 'Por asignar';
        $object['departamento'] = 'Por asignar';
        $object['porcentaje'] = 0;
      } elseif ($pasoActual === null) {
        // Todos los departamentos están terminados
        $object['paso'] = 'Terminado';
        $object['departamento'] = 'Terminado';
        $object['porcentaje'] = 100;
      } else {
        $object['paso'] = $pasoActual;
        $object['departamento'] = $pasoActual;
        $object['porcentaje'] = $totalDepartamentos > 0
          ? round(($departamentosTerminados * 100) / $totalDepartamentos)
          : 0;
      }

      $object['data']['totalPasos'] = $totalDepartamentos;
      $object['data']['departamentosTerminados'] = $departamentosTerminados;
      $object['data']['departamentos'] = $departamentos;

      $localConnection->disconnect();

      $response->getBody()->write(json_encode($object));

      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    } catch (PDOException $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    } catch (Exception $e) {
      $localConnection->disconnect();
      $errorMsg = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
      $response->getBody()->write(json_encode($errorMsg));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    }
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

  /**
   * Dashboard de Producción - Estadísticas
   * 
   * Endpoint para obtener métricas del dashboard de producción:
   * 1. Estatus de Órdenes (En espera, Pausadas, Activas)
   * 2. Tiempos de Entrega según semáforo (Por iniciar, Retrasado, En el día, A tiempo) - TODO
   * 3. Órdenes por Departamento (según lotes.paso)
   */
  $app->get('/produccion/dashboard-stats', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    try {
      $finalResponse = [];

      // =====================================================================
      // GRÁFICO 1: ESTATUS DE ÓRDENES
      // =====================================================================
      // Contar órdenes por status (En espera, Pausadas, Activas)
      $sqlEstatus = "SELECT 
          status,
          COUNT(*) as cantidad
        FROM ordenes
        WHERE status IN ('En espera', 'pausada', 'activa')
        GROUP BY status";

      $estatusResult = $localConnection->goQuery($sqlEstatus);

      // Estructurar respuesta
      $finalResponse['estatus_ordenes'] = [
        'en_espera' => 0,
        'pausadas' => 0,
        'activas' => 0
      ];

      if (!empty($estatusResult)) {
        foreach ($estatusResult as $row) {
          if ($row['status'] === 'En espera') {
            $finalResponse['estatus_ordenes']['en_espera'] = (int) $row['cantidad'];
          } elseif ($row['status'] === 'pausada') {
            $finalResponse['estatus_ordenes']['pausadas'] = (int) $row['cantidad'];
          } elseif ($row['status'] === 'activa') {
            $finalResponse['estatus_ordenes']['activas'] = (int) $row['cantidad'];
          }
        }
      }

      // =====================================================================
      // GRÁFICO 2: TIEMPOS DE ENTREGA (SEMÁFORO SIMPLIFICADO)
      // =====================================================================
      // Categoriza órdenes comparando fecha_entrega con HOY
      $sqlSemaforo = "SELECT 
          SUM(CASE WHEN status = 'En espera' THEN 1 ELSE 0 END) as por_iniciar,
          SUM(CASE WHEN status = 'activa' AND DATE(fecha_entrega) < CURDATE() THEN 1 ELSE 0 END) as retrasado,
          SUM(CASE WHEN status = 'activa' AND DATE(fecha_entrega) = CURDATE() THEN 1 ELSE 0 END) as en_el_dia,
          SUM(CASE WHEN status = 'activa' AND DATE(fecha_entrega) > CURDATE() THEN 1 ELSE 0 END) as a_tiempo,
          SUM(CASE WHEN status = 'pausada' THEN 1 ELSE 0 END) as pausadas
        FROM ordenes
        WHERE status IN ('En espera', 'activa', 'pausada')";

      $semaforoResult = $localConnection->goQuery($sqlSemaforo);

      // Estructurar respuesta
      $finalResponse['tiempos_entrega'] = [
        'por_iniciar' => 0,
        'retrasado' => 0,
        'en_el_dia' => 0,
        'a_tiempo' => 0,
        'pausadas' => 0
      ];

      if (!empty($semaforoResult) && isset($semaforoResult[0])) {
        $finalResponse['tiempos_entrega']['por_iniciar'] = (int) ($semaforoResult[0]['por_iniciar'] ?? 0);
        $finalResponse['tiempos_entrega']['retrasado'] = (int) ($semaforoResult[0]['retrasado'] ?? 0);
        $finalResponse['tiempos_entrega']['en_el_dia'] = (int) ($semaforoResult[0]['en_el_dia'] ?? 0);
        $finalResponse['tiempos_entrega']['a_tiempo'] = (int) ($semaforoResult[0]['a_tiempo'] ?? 0);
        $finalResponse['tiempos_entrega']['pausadas'] = (int) ($semaforoResult[0]['pausadas'] ?? 0);
      }

      // =====================================================================
      // GRÁFICO 3: ÓRDENES POR DEPARTAMENTO
      // =====================================================================
      // Usar campo lotes.paso para contar órdenes por departamento
      $sqlDepartamentos = "SELECT 
          l.paso as departamento,
          COUNT(DISTINCT l.id_orden) as cantidad
        FROM lotes l
        JOIN ordenes o ON o._id = l.id_orden
        WHERE o.status IN ('En espera', 'pausada', 'activa')
          AND l.paso IS NOT NULL
          AND l.paso != ''
          AND l.paso != 'Terminado'
          AND l.paso != 'Por asignar'
        GROUP BY l.paso
        ORDER BY cantidad DESC";

      $departamentosResult = $localConnection->goQuery($sqlDepartamentos);

      $finalResponse['ordenes_por_departamento'] = [];
      if (!empty($departamentosResult)) {
        foreach ($departamentosResult as $row) {
          $finalResponse['ordenes_por_departamento'][] = [
            'departamento' => $row['departamento'],
            'cantidad' => (int) $row['cantidad']
          ];
        }
      }

      $localConnection->disconnect();

      $response->getBody()->write(json_encode($finalResponse, JSON_NUMERIC_CHECK));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    } catch (Exception $e) {
      $localConnection->disconnect();

      $errorResponse = [
        'error' => 'Error al obtener estadísticas del dashboard',
        'message' => $e->getMessage()
      ];

      $response->getBody()->write(json_encode($errorResponse));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    }
  });


  /**
   * =================================================================
   * NUEVOS ENDPOINTS PARA FLUJO DE CORTE (EXTRA / STOCK)
   * =================================================================
   */

  /**
   * POST /production/corte/ajuste
   * Registra el ajuste de cantidad a cortar definido por el jefe de producción.
   */
  $app->post('/production/corte/ajuste', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $now = date('Y-m-d H:i:s');

    // Fallback if getParsedBody is empty
    if (empty($data)) {
      $rawInput = file_get_contents('php://input');
      $data = json_decode($rawInput, true);
    }

    if (empty($data['id_orden']) || empty($data['id_ordenes_productos']) || !isset($data['cantidad_ajustada'])) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Faltan datos requeridos.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Modificado para registrar en inventario_corte, que es lo que suma el avance.
    // Lógica: Para este modulo de "Ajuste", asumimos que el valor enviado es el TOTAL acumulado real.
    // Por lo tanto, borramos registros previos de este producto en esta orden para evitar sumas dobles (5 + 6 = 11).
    // Si se quisiera un log histórico, deberíamos manejarlo en otra tabla o con delta.

    // 1. Obtener detalles del producto original
    $sqlProduct = "SELECT talla, tela, corte, name FROM ordenes_productos WHERE _id = ?";
    $productDetails = $localConnection->goQuery($sqlProduct, [$data['id_ordenes_productos']]);

    $talla = null;
    $tela = null;
    $corte = null;

    if (!empty($productDetails)) {
      $talla = $productDetails[0]['talla'];
      $tela = $productDetails[0]['tela'];
      $corte = $productDetails[0]['corte'];
    }

    // 2. Limpiar registros previos del producto en esta orden
    $sqlDelete = "DELETE FROM inventario_corte WHERE id_orden = ? AND id_ordenes_productos = ?";
    $localConnection->goQuery($sqlDelete, [$data['id_orden'], $data['id_ordenes_productos']]);

    // 3. Insertar el nuevo valor total con STATUS 'por_cortar'
    // Se guardan los detalles técnicos (talla, tela, corte) para que el cortador sepa qué hacer.
    $sql = "INSERT INTO inventario_corte (id_orden, id_ordenes_productos, id_empleado_corte, cantidad, talla, tela, corte, estado, moment)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'por_cortar', ?)";
    $params = [
      $data['id_orden'],
      $data['id_ordenes_productos'],
      $data['id_empleado_ajuste'] ?? 0, // ID del empleado que solicita/ajusta
      $data['cantidad_ajustada'], // La cantidad reportada
      $talla,
      $tela,
      $corte,
      $now
    ];

    $result = $localConnection->goQuery($sql, $params);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Ajuste guardado correctamente.', 'data' => $result]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  /**
  /**
   * POST /production/corte/crear-orden-stock-manual
   * Guarda los datos de una nueva orden interna (Stock) vinculada a la orden original con productos manuales
   * en la tabla ordenes_tmp para que sea aprobada posteriormente.
   */
  $app->post('/production/corte/crear-orden-stock-manual', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    if (is_null($data)) {
      $data = json_decode($request->getBody()->getContents(), true);
    }

    $localConnection = new LocalDB();

    $id_orden_original = $data['id_orden_original'] ?? null;
    $items_manual = $data['form']['productos'] ?? ($data['items'] ?? []);
    $id_empleado = $data['id_empleado'] ?? 1;

    if (empty($id_orden_original) || empty($items_manual)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Datos incompletos para crear orden de stock.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Extraer y convertir el 'form' a JSON simulando la estructura comercial
    $form_json = json_encode($data['form'] ?? []);

    // Guardar en la tabla ordenes_tmp
    // Nota: El campo 'tipo' es varchar(11), por lo que usamos 'Produccion' (10 chars) o 'Corte' (5 chars).
    $sqlInsert = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES (?, ?, ?)";
    $resInsert = $localConnection->goQuery($sqlInsert, [$form_json, $id_empleado, 'Produccion']);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode([
      'status' => 'success',
      'message' => 'Orden guardada para aprobación correctamente.',
      'insert_id' => $resInsert['insert_id'] ?? null
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  /**
   * POST /production/corte/finalizar-tarea
   * Finaliza la tarea de corte, registrando las piezas físicas en inventario_corte.
   */
  $app->post('/production/corte/finalizar-tarea', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $now = date('Y-m-d H:i:s');

    // Data esperada: id_orden, id_lotes_detalles, id_ordenes_productos, id_empleado, cantidad_cortada, ...

    // 1. Insertar en inventario_corte
    // Nota: Recibimos un array de cortes o uno por uno? Asumiremos uno por uno o un array "cortes".
    // Si es uno por uno:
    $sqlInv = "INSERT INTO inventario_corte (id_orden, id_ordenes_productos, id_empleado_corte, cantidad, talla, tela, corte, fecha_corte, estado, moment)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)";

    $paramsInv = [
      $data['id_orden'],
      $data['id_ordenes_productos'],
      $data['id_empleado'],
      $data['cantidad_cortada'],
      $data['talla'],
      $data['tela'],
      $data['corte'], // Tipo de corte
      $now,
      $now
    ];
    $localConnection->goQuery($sqlInv, $paramsInv);

    // 2. Marcar tarea como terminada en lotes_detalles_empleados_asignados
    // Reutilizamos la lógica existente o llamamos al endpoint existente internamente?
    // Mejor hacemos el UPDATE directo aquí para asegurar atomicidad o consistencia con este flujo.

    // Buscar si existe asignación
    $sqlFindAssign = "SELECT _id FROM lotes_detalles_empleados_asignados 
                      WHERE id_orden = ? AND id_empleado = ? AND id_departamento = ? AND (progreso = 'en curso' OR progreso = 'por iniciar')";
    // Necesitamos saber el ID departamento de Corte. Asumimos que viene en $data o lo buscamos.
    $id_depto_corte = $data['id_departamento'];

    $assign = $localConnection->goQuery($sqlFindAssign, [$data['id_orden'], $data['id_empleado'], $id_depto_corte]);

    if (!empty($assign)) {
      $sqlUpdate = "UPDATE lotes_detalles_empleados_asignados SET progreso = 'terminada', fecha_terminado = ? WHERE _id = ?";
      $localConnection->goQuery($sqlUpdate, [$now, $assign[0]['_id']]);
    }

    // 3. Verificar si todos los items de la orden en Corte están listos para avanzar el Lote?
    // Esta logica ya suele estar en "registrar-paso-empleado". 
    // Por ahora solo registramos el inventario y terminamos la tarea individual. 
    // El frontend podría llamar luego a verificar el estado global o usamos la misma logica de "registrar-paso-empleado" si se requiere.
    // Para simplificar, asumimos que este endpoint es solo para el registro físico y tarea individual.

    $localConnection->disconnect();
    $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Corte registrado e inventario actualizado.']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  /**
   * Obtener detalles de productos de una orden para Corte
   */
  $app->get('/production/corte/orden-detalles/{id}', function (Request $request, Response $response, array $args) {
    $id_orden = intval($args['id']);
    $localConnection = new LocalDB();

    try {
      // Consultar productos de la orden (Solo físicos y no diseños)
      // Se incluye la cantidad real cortada desde inventario_corte
      $sql = "SELECT 
                op._id, 
                op.name, 
                op.talla, 
                op.cantidad, 
                op.corte, 
                op.tela,
                COALESCE(SUM(ic.cantidad), 0) as cantidad_cortada
              FROM ordenes_productos op
              LEFT JOIN products p ON op.id_woo = p._id
              LEFT JOIN inventario_corte ic ON ic.id_ordenes_productos = op._id
              WHERE op.id_orden = $id_orden 
              AND p.fisico = 1 
              AND (p.es_diseno = 0 OR p.es_diseno IS NULL)
              GROUP BY op._id";
      $productos = $localConnection->goQuery($sql);

      // Buscar piezas cortadas previamente disponibles
      foreach ($productos as &$prod) {
        $talla = $prod['talla'];
        $tela = $prod['tela'];
        $corte = $prod['corte'];

        $sqlDisp = "SELECT ic.id_orden, (ic.cantidad - op.cantidad) as cantidad, ic.moment 
                    FROM inventario_corte ic
                    JOIN ordenes_productos op ON ic.id_ordenes_productos = op._id
                    JOIN ordenes o ON ic.id_orden = o._id
                    WHERE ic.talla = ? 
                      AND ic.tela = ? 
                      AND ic.corte = ? 
                      AND ic.id_orden <> ?
                      AND ic.cantidad > op.cantidad
                      AND o.status <> 'cancelada'
                    
                    UNION ALL
                    
                    SELECT id_orden, cantidad, moment 
                    FROM inventario_corte 
                    WHERE talla = ? 
                      AND tela = ? 
                      AND corte = ? 
                      AND estado = 'disponible'
                      AND id_orden <> ?
                    
                    ORDER BY moment ASC";
        $disp = $localConnection->goQuery($sqlDisp, [
          $talla,
          $tela,
          $corte,
          $id_orden,
          $talla,
          $tela,
          $corte,
          $id_orden
        ]);
        $prod['disponibles'] = $disp ?: [];
      }
      unset($prod); // Romper referencia

      $response->getBody()->write(json_encode([
        'productos' => $productos ?: []
      ]));

      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode([
        'error' => $e->getMessage()
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });
}; // Fin de la función que envuelve las rutas
