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
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT 
              a.id_orden orden, 
              b.nombre AS empleado, 
              c.name producto, 
              c.cantidad, 
              c.talla, 
              c.corte, 
              c.tela, 
              TO_CHAR(a.fecha_inicio, 'HH12:MI:SS AM') AS hora,
              TO_CHAR(a.fecha_inicio, 'DD-MM-YYYY') AS fecha
              FROM lotes_detalles a
              JOIN api_empresas.empresas_usuarios b ON a.id_empleado = b.id_usuario
              JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
              WHERE a.progreso = 'en curso' 
              ORDER BY a.fecha_inicio DESC, b.nombre ASC
          ";
    } else {
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
    }
    $object['items'] = $localConnection->goQuery($sql);

    // $sse = new SSE($obj);
    // $events = $sse->SsePrint();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // SSE DATA
  // NEW LIGHTWEIGHT CHECK-VERSION ENDPOINT FOR POLLING
  $app->get('/sse/produccion/check-version', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT CONCAT(
        (SELECT COUNT(*) FROM ordenes WHERE status IN ('activa', 'pausada', 'En espera')),
        '-',
        (SELECT COALESCE(MAX(_id), 0) FROM ordenes WHERE status IN ('activa', 'pausada', 'En espera')),
        '-',
        (SELECT COUNT(*) FROM reposiciones WHERE (aprobada IS NULL OR (aprobada = 0 AND detalle IS NULL)) AND id_empleado IS NULL AND eliminada = 0),
        '-',
        (SELECT COUNT(*) FROM reposiciones WHERE aprobada = 1 AND terminada = 0 AND id_empleado IS NOT NULL AND eliminada = 0)
    ) as checksum";

    $result = $localConnection->goQuery($sql);
    $checksum = !empty($result) ? $result[0]['checksum'] : '';

    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['checksum' => $checksum]));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OPTIMIZED SSE PRODUCCION ENDPOINT
  $app->get('/sse/produccion', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
    $localConnection = new LocalDB();

    // EMPLEADOS ASIGNADOS A TAREAS (Filtro corregido para usar IN)
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
            WHERE ord.status IN ('En espera', 'activa', 'pausada')
        ";
    $obj['emp_asignados'] = $localConnection->goQuery($sql);

    // ITEMS DE LISTA DE PRODUCCIÓN (Subconsulta prog_info acotada a órdenes activas)
    // PG exige GROUP BY estricto: a.* es funcionalmente dependiente de a._id (PK), pero
    // el resto de columnas de otras tablas deben listarse explicitamente (MySQL no lo exige).
    $groupByItems = DB_DRIVER === 'pgsql'
        ? "GROUP BY a._id, f.orden_fila, cus.first_name, cus.last_name, b.prioridad, prog_info.total_departamentos, prog_info.paso_actual, prog_info.departamentos_terminados, d.estatus, n.borrador, c._id, e.nombre"
        : "GROUP BY a._id";
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

            COALESCE(prog_info.departamentos_terminados, 0) AS progreso_paso_valor,
            COALESCE(prog_info.total_departamentos, 0) AS progreso_total_pasos,
            COALESCE(
                ROUND(
                    (prog_info.departamentos_terminados * 100) / NULLIF(prog_info.total_departamentos, 0)
                ), 0
            ) AS progreso_porcentaje

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
            -- Subconsulta acotada a órdenes activas para evitar scan completo de históricos
            SELECT
                ldea.id_orden,
                COUNT(DISTINCT ldea.id_departamento) AS total_departamentos,
                COUNT(DISTINCT CASE WHEN ldea.fecha_terminado IS NOT NULL THEN ldea.id_departamento END) AS departamentos_terminados,
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
            JOIN ordenes ord2 ON ord2._id = ldea.id_orden
            WHERE ord2.status IN ('activa', 'pausada', 'En espera')
            GROUP BY
                ldea.id_orden
        ) AS prog_info ON a._id = prog_info.id_orden
        WHERE
            a.status IN ('activa', 'pausada', 'En espera')
        $groupByItems
        ORDER BY
            f.orden_fila ASC;
    ";
    $obj['items'] = $localConnection->goQuery($sql);

    // ITEMS POR ASIGNAR (Acotados a órdenes activas)
    $sql = "SELECT
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
        JOIN ordenes ord ON ord._id = lot.id_orden
        WHERE lot.id_empleado IS NULL AND ord.status IN ('activa', 'pausada', 'En espera')
        ";
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

    $categoryJoinCond = DB_DRIVER === 'pgsql'
        ? "cat._id::text = ANY(string_to_array(p.category_ids, ','))"
        : "FIND_IN_SET(cat._id, p.category_ids)";
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
        LEFT JOIN categories cat ON $categoryJoinCond
        LEFT JOIN lotes_fisicos c ON c.id_orden = b._id
        LEFT JOIN ordenes a ON
            b.id_orden = a._id
        LEFT JOIN products_comisiones pc ON pc.id_product = b.id_woo
        WHERE
            (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND b.category_name != 'Diseños'
        ORDER BY b._id DESC, c.piezas_actuales DESC";

    $obj['orden_productos'] = $localConnection->goQuery($sql);

    // Bucle redundante PHP eliminado. Se mapea la respuesta directamente si no está vacía.
    if (!empty($obj['orden_productos'])) {
      $sql = "SELECT b._id, b.id_orden, b.id_woo, b.progreso, b.unidades_solicitadas cantidad, b.id_ordenes_productos, b.id_empleado, b.departamento, b.id_departamento, b.unidades_solicitadas, b.comision, 'CARGAR DINAMINAMENTE' detalles, b.fecha_inicio, b.fecha_terminado, b.moment FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
      $obj['lote_detalles'] = $localConnection->goQuery($sql);

      // Optimización de lotes_fisicos: filtrar por productos activos de órdenes activas para evitar scan completo
      $sql = "SELECT DISTINCT lf._id id_lotes_fisicos, lf.piezas_actuales, lf.tela, lf.talla, lf.corte, lf.categoria, lf.moment 
              FROM lotes_fisicos lf
              JOIN ordenes_productos op ON (op.tela = lf.tela AND op.talla = lf.talla AND op.corte = lf.corte)
              JOIN products p ON p._id = op.id_woo
              JOIN ordenes o ON o._id = op.id_orden
              WHERE o.status IN ('activa', 'pausada', 'En espera') AND p.fisico = 1";
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
      if (DB_DRIVER === 'pgsql') {
        $sql = "SELECT
          a._id id_reposicion,
          a.id_orden,
          a.id_departamento_solicitante,
          a.id_departamento,
          c._id id_ordenes_productos,
          b.nombre empleado,
          a.detalle_emisor,
          TO_CHAR(a.moment, 'DD/MM/YYYY') AS fecha,
          TO_CHAR(a.moment, 'HH12:MI AM') AS hora,
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
              (a.aprobada IS NULL OR (a.aprobada = 0 AND a.detalle IS NULL)) AND a.id_empleado IS NULL AND a.eliminada = 0
          ORDER BY d.orden_fila ASC;
          ";
      } else {
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
              (a.aprobada IS NULL OR (a.aprobada = 0 AND a.detalle IS NULL)) AND a.id_empleado IS NULL AND a.eliminada = 0
          ORDER BY d.orden_fila ASC;
          ";
      }
      $obj['reposiciones_solicitadas'] = $localConnection->goQuery($sql);

      // BUSCAR REPOSICIONES EN CURSO (APROBADAS Y NO TERMINADAS)
      if (DB_DRIVER === 'pgsql') {
        $sql = "SELECT
          a._id id_reposicion,
          a.id_orden,
          a.id_departamento_solicitante,
          a.id_departamento,
          dep.departamento AS nombre_departamento,
          c._id id_ordenes_productos,
          b.nombre emisor,
          e.nombre empleado,
          a.detalle_emisor,
          a.detalle,
          TO_CHAR(a.moment, 'DD/MM/YYYY') AS fecha,
          TO_CHAR(a.moment, 'HH12:MI AM') AS hora,
          c.name producto,
          a.unidades,
          c.talla,
          c.corte,
          c.tela
          FROM
              reposiciones a
          LEFT JOIN ordenes_fila_reposiciones d ON d.id_reposicion = a._id
          LEFT JOIN api_empresas.empresas_usuarios b ON b.id_usuario = a.id_empleado_emisor
          LEFT JOIN api_empresas.empresas_usuarios e ON e.id_usuario = a.id_empleado
          LEFT JOIN departamentos dep ON dep._id = a.id_departamento
          JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
          WHERE
              a.aprobada = 1 AND a.terminada = 0 AND a.id_empleado IS NOT NULL AND a.id_empleado <> 0 AND a.eliminada = 0
          ORDER BY d.orden_fila ASC;
          ";
      } else {
        $sql = "SELECT
          a._id id_reposicion,
          a.id_orden,
          a.id_departamento_solicitante,
          a.id_departamento,
          dep.departamento AS nombre_departamento,
          c._id id_ordenes_productos,
          b.nombre emisor,
          e.nombre empleado,
          a.detalle_emisor,
          a.detalle,
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
          LEFT JOIN api_empresas.empresas_usuarios e ON e.id_usuario = a.id_empleado
          LEFT JOIN departamentos dep ON dep._id = a.id_departamento
          JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
          WHERE
              a.aprobada = 1 AND a.terminada = 0 AND a.id_empleado IS NOT NULL AND a.id_empleado <> 0 AND a.eliminada = 0
          ORDER BY d.orden_fila ASC;
          ";
      }
      $obj['reposiciones_en_curso'] = $localConnection->goQuery($sql);

      // Detalles de los productos
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

      // EMPLEADOS (a.password removido por seguridad)
      if (DB_DRIVER === 'pgsql') {
        $sql = "SELECT
              a.id_usuario AS _id,
              a.id_usuario AS acciones,
              a.email AS username,
              a.nombre,
              a.email,
              a.departamento,
              a.comision,
              a.comision_tipo,
              a.acceso,
              COALESCE(deps.departamentos, '[]') AS departamentos
          FROM 
              api_empresas.empresas_usuarios a
          LEFT JOIN (
              SELECT
                  b.id_empleado,
                  COALESCE('[' || string_agg(
                      DISTINCT '{\"id\":' || b.id_departamento || ',\"nombre\":\"' || c.departamento || '\"}',
                      ','
                  ) || ']', '[]') AS departamentos
              FROM
                  api_empresas.empresas_usuarios_departamentos b
              INNER JOIN departamentos c ON c._id = b.id_departamento AND c.eliminado = 0
              GROUP BY
                  b.id_empleado
          ) deps ON deps.id_empleado = a.id_usuario
          WHERE
              a.activo = 1  AND a.id_empresa = " . ID_EMPRESA . ";";
      } else {
        $sql = 'SELECT
              a.id_usuario AS _id,
              a.id_usuario AS acciones,
              a.email AS username,
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
              a.id_usuario, a.email, a.nombre, a.departamento, 
              a.comision, a.comision_tipo, a.acceso;
              ';
      }
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
                                                                                                                                                                                                                                                                                                                                                                        AND a.departamento IN (SELECT departamento FROM departamentos WHERE tipo = 'corte')
                                                                                                                                                                                                                                                                                                                                                                        AND b.corte != 'No aplica'
                                                                                                                                                                                                                                                                                                                                                                        AND (a.progreso = 'por iniciar' OR a.progreso = 'en curso')
                                                                                                                                                                                                                                                                                                                                                                        ORDER BY b.talla ASC, b.corte ASC, b.tela ASC;
                                                                                                                                                                                                                                                                                                                                                                        ";
    $obj[0]['sql'] = $sql;
    $obj[0]['name'] = 'items';

    $sql = "SELECT id_usuario id_empleado, nombre FROM api_empresas.empresas_usuarios WHERE departamento IN (SELECT departamento FROM departamentos WHERE tipo = 'corte') AND activo = 1 AND id_empresa = " . ID_EMPRESA;
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
                GROUP BY c._id, a.id_orden, a._id, a.id_empleado, a.linkdrive, a.codigo_diseno, b.cliente_nombre, b.fecha_inicio, b.status, b.id_wp
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
            a.id_ordenes_productos = ' . $args['id_ordenes_productos'] . ' AND a.id_orden = ' . $args['id_orden'] . ' AND a.eliminada = 0';
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
    
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
      $data = $request->getParsedBody();
      if (empty($data)) {
        parse_str($rawBody, $parsed);
        if (!empty($parsed)) {
          $data = $parsed;
        }
      }
    }

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
      if (DB_DRIVER === 'pgsql') {
        $sql = "INSERT INTO reposiciones_departamentos_excluidos (id_reposicion, id_departamento) VALUES ({$id_reposicion}, {$id_departamento}) ON CONFLICT DO NOTHING";
      } else {
        $sql = "INSERT IGNORE INTO reposiciones_departamentos_excluidos (id_reposicion, id_departamento) VALUES ({$id_reposicion}, {$id_departamento})";
      }
    } else {
      $sql = "DELETE FROM reposiciones_departamentos_excluidos WHERE id_reposicion = {$id_reposicion} AND id_departamento = {$id_departamento}";
    }

    $result = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode(['success' => true, 'excluido' => $excluir, 'result' => $result]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  /**
   * REPORTE DE REPOSICIONES
   * - Si en `estaus_orden` se recibe el parámetro `todas` vamos a mosntrar las TODAS laordenes incuyendo las canceladas
   * - Si no se recibe ningún parámetro vamos a mostrar solo las reposciones de las ordenes activas
   * -
   */
  $app->get('/reposiciones-reporte/{estatus_orden}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    
    $queryParams = $request->getQueryParams();
    $fechaInicio = $queryParams['fecha_inicio'] ?? null;
    $fechaFin = $queryParams['fecha_fin'] ?? null;

    // El status de ordenes NO tiene una convención de mayúsculas consistente
    // ('activa', 'cancelada' Y 'Cancelada', 'En espera' -- verificado con datos
    // reales). Comparar con LOWER() en ambos lados evita que el filtro por
    // defecto ('activa') deje de matchear silenciosamente.
    $estatusOrdenParam = strtolower((string)$args['estatus_orden']);
    $sqlParams = [];
    $whereConditions = [];
    if ($estatusOrdenParam === 'activa') {
      $whereConditions[] = "LOWER(ord.status) IN ('activa', 'pausada', 'en espera', 'terminada')";
    } elseif ($estatusOrdenParam !== 'todas') {
      $whereConditions[] = "LOWER(ord.status) = ?";
      $sqlParams[] = $estatusOrdenParam;
    }

    if (DB_DRIVER === 'pgsql') {
      if ($fechaInicio && $fechaFin && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
        $whereConditions[] = "re.moment::date BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      }
    } else {
      if ($fechaInicio && $fechaFin && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
        $whereConditions[] = "DATE(re.moment) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
      }
    }

    $whereParams = '';
    if (!empty($whereConditions)) {
      $whereParams = "WHERE " . implode(" AND ", $whereConditions);
    }

    if (DB_DRIVER === 'pgsql') {
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
                  re.detalle_emisor detalle_emisor,
                  re.detalle detalle_encargado,
                  COALESCE(
                    SUM((inm.valor_inicial - inm.valor_final) * (inv.costo / NULLIF(inv.cantidad_inicial, 0))), 0
                  ) +
                  COALESCE(
                    (SELECT SUM(
                      t.cantidad * CASE
                        WHEN t.id_color_tinta = 1 THEN (ic.costo / NULLIF(ic.cantidad_inicial, 0))
                        WHEN t.id_color_tinta = 2 THEN (im.costo / NULLIF(im.cantidad_inicial, 0))
                        WHEN t.id_color_tinta = 3 THEN (iy.costo / NULLIF(iy.cantidad_inicial, 0))
                        WHEN t.id_color_tinta = 4 THEN (ik.costo / NULLIF(ik.cantidad_inicial, 0))
                        ELSE 0
                      END
                    )
                    FROM tintas t
                    LEFT JOIN inventario ic ON ic._id = 3
                    LEFT JOIN inventario im ON im._id = 4
                    LEFT JOIN inventario iy ON iy._id = 5
                    LEFT JOIN inventario ik ON ik._id = 6
                    WHERE t.id_orden = re.id_orden 
                      AND t.moment >= re.moment 
                      AND t.moment < COALESCE(
                        (SELECT MIN(re_next.moment) 
                         FROM reposiciones re_next 
                         WHERE re_next.id_orden = re.id_orden 
                           AND re_next.moment > re.moment
                           AND re_next.eliminada = 0), 
                        '9999-12-31 23:59:59'
                      )
                    ), 0
                  ) +
                  COALESCE(
                    (SELECT SUM(monto_pago) FROM pagos WHERE id_reposicion = re._id), 0
                  ) material_consumido,
                  '$' as unidad,
                  TO_CHAR(re.moment, 'DD/MM/YYYY') fecha_creacion,
                  TO_CHAR(re.moment, 'HH12:MI AM') hora_creacion
              FROM
                  reposiciones re
              LEFT JOIN api_empresas.empresas_usuarios em_asignado ON re.id_empleado = em_asignado.id_usuario
              LEFT JOIN ordenes ord On ord._id = re.id_orden
              JOIN api_empresas.empresas_usuarios em_emisor ON re.id_empleado_emisor = em_emisor.id_usuario
              JOIN ordenes_productos op ON op._id = re.id_ordenes_productos 
              LEFT JOIN inventario_movimientos inm ON (inm.id_reposicion = re._id) OR (inm.id_reposicion IS NULL AND inm.id_orden = re.id_orden AND inm.moment >= re.moment AND inm.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = re.id_orden AND re_next.moment > re.moment AND re_next.eliminada = 0), '9999-12-31 23:59:59'))
              LEFT JOIN inventario inv ON inv._id = inm.id_insumo
              {$whereParams}
              GROUP BY re._id, re.id_orden, re.id_empleado, re.id_empleado_emisor, re.id_ordenes_productos, op.id_woo, ord.status, op.name, op.talla, op.corte, op.tela, re.unidades, em_emisor.nombre, em_asignado.nombre, re.detalle_emisor, re.detalle, re.moment
              ORDER BY re.id_orden ASC, re._id ASC;";
    } else {
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
                  re.detalle_emisor detalle_emisor,
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
                    WHERE t.id_orden = re.id_orden 
                      AND t.moment >= re.moment 
                      AND t.moment < COALESCE(
                        (SELECT MIN(re_next.moment) 
                         FROM reposiciones re_next 
                         WHERE re_next.id_orden = re.id_orden 
                           AND re_next.moment > re.moment
                           AND re_next.eliminada = 0), 
                        '9999-12-31 23:59:59'
                      )
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
              LEFT JOIN inventario_movimientos inm ON (inm.id_reposicion = re._id) OR (inm.id_reposicion IS NULL AND inm.id_orden = re.id_orden AND inm.moment >= re.moment AND inm.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = re.id_orden AND re_next.moment > re.moment AND re_next.eliminada = 0), '9999-12-31 23:59:59'))
              LEFT JOIN inventario inv ON inv._id = inm.id_insumo
              {$whereParams}
              GROUP BY re._id
              ORDER BY re.id_orden ASC, re._id ASC;";
    }

    $object = $localConnection->goQuery($sql, $sqlParams);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/reposicion-detalles/{id_reposicion}', function (Request $request, Response $response, array $args) {
    $localConnection = new LocalDB();
    // Casteado a entero: el valor se interpola directo en varias sub-consultas
    // de una UNION grande más abajo (no factible convertir todas a placeholders
    // sin reescribir la consulta completa) -- el cast a int neutraliza cualquier
    // intento de inyección igual de efectivo que un bind, dado que solo se usa
    // en contexto numérico.
    $id_reposicion = (int)$args['id_reposicion'];

    // 1. Obtener datos clave de la reposición
    $sqlRepo = "SELECT id_orden, id_empleado FROM reposiciones WHERE _id = {$id_reposicion}";
    $repoData = $localConnection->goQuery($sqlRepo);

    if (empty($repoData)) {
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $id_orden = (int)$repoData[0]['id_orden'];
    $id_empleado = (int)$repoData[0]['id_empleado'];

    if (DB_DRIVER === 'pgsql') {
      $fechaFormatInm = "TO_CHAR(inm.moment, 'DD/MM/YYYY HH12:MI AM')";
      $fechaFormatT = "TO_CHAR(t.moment, 'DD/MM/YYYY HH12:MI AM')";
      $fechaFormatP = "TO_CHAR(p.moment, 'DD/MM/YYYY HH12:MI AM')";
    } else {
      $fechaFormatInm = "DATE_FORMAT(inm.moment, '%d/%m/%Y %h:%i %p')";
      $fechaFormatT = "DATE_FORMAT(t.moment, '%d/%m/%Y %h:%i %p')";
      $fechaFormatP = "DATE_FORMAT(p.moment, '%d/%m/%Y %h:%i %p')";
    }

    // 2. Consultar desglose de movimientos
    if (DB_DRIVER === 'pgsql') {
      $sql = "SELECT 
                inv.insumo,
                inv.unidad,
                inm.valor_inicial,
                inm.valor_final,
                (inm.valor_inicial - inm.valor_final) as cantidad_consumida,
                (inv.costo / NULLIF(inv.cantidad_inicial, 0)) as costo_unitario,
                ((inm.valor_inicial - inm.valor_final) * (inv.costo / NULLIF(inv.cantidad_inicial, 0))) as costo_total,
                $fechaFormatInm as fecha,
                inv.color,
                COALESCE(inv.id_catalogo, 0) as id_catalogo
              FROM inventario_movimientos inm
              JOIN inventario inv ON inv._id = inm.id_insumo
              WHERE 
                (inm.id_reposicion = {$id_reposicion}) 
                OR 
                (inm.id_reposicion IS NULL AND inm.id_orden = {$id_orden} 
                 AND inm.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})
                 AND inm.moment < COALESCE(
                   (SELECT MIN(re_next.moment) 
                    FROM reposiciones re_next 
                    WHERE re_next.id_orden = {$id_orden} 
                      AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})
                      AND re_next.eliminada = 0), 
                   '9999-12-31 23:59:59'
                 )
                )
              
              UNION ALL

              SELECT 
                'Tinta Cyan' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.cantidad as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.cantidad * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'CYAN' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 3 WHERE t.id_color_tinta = 1 AND t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Magenta' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.cantidad as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.cantidad * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'MAGENTA' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 4 WHERE t.id_color_tinta = 2 AND t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Yellow' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.cantidad as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.cantidad * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'YELLOW' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 5 WHERE t.id_color_tinta = 3 AND t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Black' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.cantidad as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.cantidad * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'BLACK' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 6 WHERE t.id_color_tinta = 4 AND t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                  CONCAT('Mano de Obra - ', u.nombre) as insumo,
                  'UND' as unidad,
                  0 as valor_inicial,
                  0 as valor_final,
                  1 as cantidad_consumida,
                  p.monto_pago as costo_unitario,
                  p.monto_pago as costo_total,
                  $fechaFormatP as fecha,
                  'N/A' as color,
                  9999 as id_catalogo
              FROM pagos p
              JOIN api_empresas.empresas_usuarios u ON u.id_usuario = p.id_empleado
              WHERE p.id_reposicion = {$id_reposicion} AND p.monto_pago > 0
              
              ORDER BY id_catalogo ASC, fecha DESC";
    } else {
      $sql = "SELECT 
                inv.insumo,
                inv.unidad,
                inm.valor_inicial,
                inm.valor_final,
                (inm.valor_inicial - inm.valor_final) as cantidad_consumida,
                (inv.costo / NULLIF(inv.cantidad_inicial, 0)) as costo_unitario,
                ((inm.valor_inicial - inm.valor_final) * (inv.costo / NULLIF(inv.cantidad_inicial, 0))) as costo_total,
                $fechaFormatInm as fecha,
                inv.color,
                COALESCE(inv.id_catalogo, 0) as id_catalogo
              FROM inventario_movimientos inm
              JOIN inventario inv ON inv._id = inm.id_insumo
              WHERE 
                (inm.id_reposicion = {$id_reposicion}) 
                OR 
                (inm.id_reposicion IS NULL AND inm.id_orden = {$id_orden} 
                 AND inm.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})
                 AND inm.moment < COALESCE(
                   (SELECT MIN(re_next.moment) 
                    FROM reposiciones re_next 
                    WHERE re_next.id_orden = {$id_orden} 
                      AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion})
                      AND re_next.eliminada = 0), 
                   '9999-12-31 23:59:59'
                 )
                )
              
              UNION ALL

              SELECT 
                'Tinta Cyan' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.c as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.c * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'CYAN' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 3 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Magenta' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.m as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.m * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'MAGENTA' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 4 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Yellow' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.y as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.y * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'YELLOW' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 5 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                'Tinta Black' as insumo, 'ML' as unidad, 0 as valor_inicial, 0 as valor_final, t.k as cantidad_consumida, (i.costo / NULLIF(i.cantidad_inicial, 0)) as costo_unitario, (t.k * (i.costo / NULLIF(i.cantidad_inicial, 0))) as costo_total, $fechaFormatT as fecha, 'BLACK' as color, COALESCE(i.id_catalogo, 0) as id_catalogo
              FROM tintas t JOIN inventario i ON i._id = 6 WHERE t.id_orden = {$id_orden} AND t.moment >= (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND t.moment < COALESCE((SELECT MIN(re_next.moment) FROM reposiciones re_next WHERE re_next.id_orden = {$id_orden} AND re_next.moment > (SELECT moment FROM reposiciones WHERE _id = {$id_reposicion}) AND re_next.eliminada = 0), '9999-12-31 23:59:59')

              UNION ALL

              SELECT 
                  CONCAT('Mano de Obra - ', u.nombre) as insumo,
                  'UND' as unidad,
                  0 as valor_inicial,
                  0 as valor_final,
                  1 as cantidad_consumida,
                  p.monto_pago as costo_unitario,
                  p.monto_pago as costo_total,
                  $fechaFormatP as fecha,
                  'N/A' as color,
                  9999 as id_catalogo
              FROM pagos p
              JOIN api_empresas.empresas_usuarios u ON u.id_usuario = p.id_empleado
              WHERE p.id_reposicion = {$id_reposicion} AND p.monto_pago > 0
              
              ORDER BY id_catalogo ASC, fecha DESC";
    }

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
      // quote()/quote_literal() ya devuelve el valor con comillas simples incluidas, ej: 'texto'
      $quoteFn = DB_DRIVER === 'pgsql' ? 'quote_literal' : 'quote';
      $escaped_detalle = $localConnection->goQuery("SELECT {$quoteFn}(?) AS q", [$data['detalle']])[0]["q"];
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
      // quote()/quote_literal() ya devuelve el valor con comillas simples incluidas
      $quoteFn = DB_DRIVER === 'pgsql' ? 'quote_literal' : 'quote';
      $values .= $localConnection->goQuery("SELECT {$quoteFn}(?) AS q", [$data['detalle']])[0]["q"] . ",";
      $values .= $id_depto_fin;
      $values .= ')';
    }

    // Limpiamos los quotes simples agregados por SELECT quote(?) para evitar sintaxis inválida de inserción SQL
    $values = str_replace(["'\"'", "\"'\""], ["'", "'"], $values);

    // Atomicidad FK: reposiciones + ordenes_fila_reposiciones en una transacción
    $localConnection->beginTransaction();

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

      $sql_fila_repo = "INSERT INTO ordenes_fila_reposiciones(id_reposicion, orden_fila) VALUES ({$id_reposicion_creada}, {$nextOrdenFilaRepo})";
      $object['sql_orden_fila_reposicion'] = $sql_fila_repo;
      $object['response_orden_fila_reposicion'] = $localConnection->goQuery($sql_fila_repo);
    }

    $localConnection->commit();
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

  $app->post('/produccion/reposicion/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    if (empty($data)) {
      $rawBody = $request->getBody()->getContents();
      $data = json_decode($rawBody, true);
    }
    $localConnection = new LocalDB();

    $id_reposicion = intval($data['id_reposicion']);

    $sql = "UPDATE reposiciones SET eliminada = 1 WHERE _id = {$id_reposicion}";
    $object['response'] = $localConnection->goQuery($sql);

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

      // Misma regla que ya existe en POST /orden/actualizar-estado: una orden
      // cancelada no puede pasar directo a terminada (debe reactivarse primero).
      if ($orden[0]['status'] === 'cancelada') {
        $localConnection->rollback();
        $localConnection->disconnect();
        return ApiResponse::validationError($response, 'No se puede marcar como terminada una orden cancelada. Si desea completar esta orden, reactívela primero.');
      }

      // Una orden ya entregada no puede "retroceder" a terminada por este
      // botón -- sin este guard, volver a pulsar "TERMINAR" sobre una orden
      // ya entregada la downgradeaba silenciosamente.
      if ($orden[0]['status'] === 'entregada') {
        $localConnection->rollback();
        $localConnection->disconnect();
        return ApiResponse::validationError($response, 'Esta orden ya fue entregada al cliente; no se puede regresar a "terminada" desde aquí.');
      }

      // Misma validación que ya existe en POST /orden/actualizar-estado: no
      // permitir terminar una orden con tareas de empleados asignados sin
      // cerrar -- este endpoint (botón "TERMINAR" de Producción) era una
      // segunda vía manual para marcar status='terminada' que no la tenía,
      // dejando un bypass real de la regla de negocio.
      $sqlPendientes = "
          SELECT
              dep.departamento,
              eu.nombre as empleado,
              CASE
                  WHEN ldea.fecha_inicio IS NULL THEN 'Por iniciar'
                  ELSE 'En curso'
              END as status_tarea
          FROM lotes_detalles_empleados_asignados ldea
          JOIN departamentos dep ON ldea.id_departamento = dep._id
          JOIN api_empresas.empresas_usuarios eu ON ldea.id_empleado = eu.id_usuario
          WHERE ldea.id_orden = ?
            AND ldea.fecha_terminado IS NULL
      ";
      $tareasPendientes = $localConnection->goQuery($sqlPendientes, [$id]);

      if (!empty($tareasPendientes)) {
        $localConnection->rollback();
        $localConnection->disconnect();
        return ApiResponse::error($response, 'Existen tareas asignadas inconclusas. Se deben terminar primero o eliminar la asignación.', 400, [
          'tareas_pendientes' => $tareasPendientes
        ]);
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

    // Atomicidad: reasignación múltiple a corte (lotes_detalles) en una transacción
    $localConnection->beginTransaction();
    $object['response_update'] = [];
    foreach ($object['request_data'] as $key => $item) {
      $sql = 'UPDATE lotes_detalles SET id_empleado = ' . $data['id_empleado'] . ', unidades_solicitadas = ' . $object['request_data'][$key]->cantidad . '  WHERE _id = ' . $object['request_data'][$key]->id_lotes_detalles . ';';
      $object['response_update'][] = $localConnection->goQuery($sql);
    }
    $localConnection->commit();

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
    $sql = 'SELECT COUNT(*) cuenta FROM lotes_detalles WHERE id_orden = ? AND departamento = ?';
    $object['sql_empty'] = $sql;
    $cuenta = $localConnection->goQuery($sql, [$data['id_orden'], $data['paso']]);

    $asignados = $cuenta[0]['cuenta'];
    $object['asignados'] = $cuenta[0]['cuenta'];
    $object['empty'] = empty($asignados);

    if (empty($asignados)) {
      $object['nodata'] = true;
    } else {
      // TODO buscar datos para el calculo de pagos
      $sql = 'UPDATE lotes SET paso = ? WHERE _id = ?';
      $object['response_orden'] = json_encode($localConnection->goQuery($sql, [$data['paso'], $data['id_orden']]));
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
                MAX(dep.departamento) AS departamento,
                MAX(dep.orden_proceso) AS orden_proceso,
                MIN(ldea.fecha_inicio) AS fecha_inicio,
                MAX(ldea.fecha_terminado) AS fecha_terminado
              FROM lotes_detalles_empleados_asignados ldea
              JOIN departamentos dep ON dep._id = ldea.id_departamento
              WHERE ldea.id_orden = " . $args['id_orden'] . "
              GROUP BY ldea.id_departamento
              ORDER BY orden_proceso ASC";
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

    // NOTA: "observaciones" ya no es columna de ordenes en ningun motor (MySQL ni Postgres);
    // se movio a su propia tabla ordenes_observaciones (una fila por orden, patron upsert
    // usado en orders.php). Bug preexistente en ambos motores, no especifico de esta migracion.
    $sql = 'SELECT observaciones FROM ordenes_observaciones WHERE id_orden = ' . $args['id'];
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
      $fechaEntregaExpr = DB_DRIVER === 'pgsql' ? 'fecha_entrega::date' : 'DATE(fecha_entrega)';
      $curDateExpr = DB_DRIVER === 'pgsql' ? 'CURRENT_DATE' : 'CURDATE()';
      $sqlSemaforo = "SELECT
          SUM(CASE WHEN status = 'En espera' THEN 1 ELSE 0 END) as por_iniciar,
          SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr < $curDateExpr THEN 1 ELSE 0 END) as retrasado,
          SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr = $curDateExpr THEN 1 ELSE 0 END) as en_el_dia,
          SUM(CASE WHEN status = 'activa' AND $fechaEntregaExpr > $curDateExpr THEN 1 ELSE 0 END) as a_tiempo,
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
