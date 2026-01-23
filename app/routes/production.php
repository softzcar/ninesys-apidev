<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


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
      $campos = '(moment, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor, id_departamento_solicitante, id_departamento)';
      $values = '(';
      $values .= "'" . $now . "',";
      $values .= '' . $producto['id_orden'] . ',';
      $values .= '' . $data['id_empleado'] . ',';
      $values .= '' . $data['id_empleado_emisor'] . ',';
      $values .= '' . $producto['_id'] . ',';
      $values .= '' . $data['cantidad'] . ',';
      $values .= "'" . $data['detalle'] . "',";

      // Añadimos id_departamento_solicitante
      if (isset($data['id_departamento_solicitante'])) {
        $values .= intval($data['id_departamento_solicitante']) . ',';
      } else {
        $values .= 'NULL,';
      }

      // Añadimos id_departamento (visibilidad)
      if (isset($data['id_departamento'])) {
        $values .= intval($data['id_departamento']);
      } else {
        $values .= 'NULL';
      }

      $values .= ')';
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

      // Actualizar estado
      $sql = "UPDATE ordenes SET status = 'terminado' WHERE _id = ?";
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

}; // Fin de la función que envuelve las rutas

