<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


  /** WhsatsApp */

  // GUARDAR DATOS DE LA CONFIGURACIÓN DEL SISTEMA
  // $app->get('/config', function (Request $request, Response $response) {
  $app->post('/config/select-empleados', function (Request $request, Response $response, $args) {
    $datos = $request->getParsedBody();

    /*  if ($datos["estado"] == true) {
       $estado = 1;
     } else {
       $estado = 0;
     } */

    // DETERMINAR QUE DEPARTAMENTO HACE QUE
    $departamento = $datos['departamento'];

    switch ($departamento) {
      case 'Estampado':
        $campo = 'sys_mostrar_rollo_en_empleado_estampado';
        break;
      case 'Corte':
        $campo = 'sys_mostrar_rollo_en_empleado_corte';
        break;
      case 'Costura':
        $campo = 'sys_mostrar_insumo_en_empleado_costura';
        break;
      case 'Limpieza':
        $campo = 'sys_mostrar_insumo_en_empleado_limpieza';
        break;
      case 'Revisión':
        $campo = 'sys_mostrar_insumo_en_empleado_revision';
        break;
      default:
        $campo = 'Unknown';
        break;
    }

    if ($campo != 'Unknown') {
      $localConnection = new LocalDB();
      $sql = 'UPDATE config SET ' . $campo . ' = ' . $datos['estado'] . ' WHERE _id = 1';
      $object['sql'] = $sql;
      $object['response'] = $localConnection->goQuery($sql);
      $localConnection->disconnect();
    } else {
      $object['response'] = 'No existe el departamento ' . $datos['departamento'];
    }

    $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CONVERTIR ORDENES EN JSON
  $app->get('/orders-json/{id_orden}', function (Request $request, Response $response, $args) {
    $localConnection = new LocalDB();

    $sql = "SELECT
                ord._id id,
                ord.cliente_cedula cedula,
                ord.cliente_nombre nombre_completo,
                cus.first_name nombre,
                cus.last_name apellido,
                cus.phone telefono,
                cus.email,
                cus.address,
                ord.fecha_entrega fechaEntrega,
                (
                    SELECT
                        CONCAT(
                            \"[\",
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    \"item\",
                                    orp.id_woo,
                                    \"cod\",
                                    orp._id,
                                    \"producto\",
                                    orp.name,
                                    \"existencia\",
                                    orp.cantidad,
                                    \"talla\",
                                    orp.talla,
                                    \"tela\",
                                    orp.tela,
                                    \"corte\",
                                    orp.corte,                                   
                                    \"precio\",
                                    orp.precio_unitario
                                )
                            ),
                            \"]\"
                        )
                    FROM
                        api_emp_2.ordenes_productos orp
                    WHERE
                        orp.id_orden = ord._id
                ) AS productos,
                'TRAER DESDE EL `ENDPOINT` DEDICADO' obs,
                ord.pago_abono abono,
                '0' descuento,
                ord.pago_total total,
                NULL diseno_grafico,
                NULL diseno_modas,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Zelle' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresZelle,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Zelle' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresZelleDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Panamá' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresPanama,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Panamá' AND moneda = 'Dólares' AND tipo_de_pago = 'Orden nueva') montoDolaresPanamaDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosTransferencia,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Pesos' AND tipo_de_pago = 'Orden nueva') montoPesosTransferenciaDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresEfectivo,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Efectivo' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresEfectivoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Punto' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPunto,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Punto' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPuntoDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Pagomovil' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPagomovil,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Pagomovil' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresPagomovilDetalle,
                (SELECT monto FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Transferencia' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresTransferencia,
                (SELECT detalle FROM api_emp_2.metodos_de_pago WHERE id_orden = ord._id AND metodo_pago = 'Transferencia' AND moneda = 'Bolivares' AND tipo_de_pago = 'Orden nueva') montoBolivaresTransferenciaDetalle,
                NULL descuentoDetalle,
                1 sales_commision,
                NULL diseno_grafico_cantidad
            FROM
                api_emp_2.ordenes ord
            LEFT JOIN api_emp_2.ordenes_productos orp ON orp.id_orden = ord._id
            LEFT JOIN api_emp_2.customers cus ON  cus._id = ord.id_wp
            WHERE ord._id  = {$args['id_orden']} 
            GROUP BY ord._id, ord.cliente_cedula, ord.cliente_nombre, cus.first_name, cus.last_name, cus.phone, cus.email, cus.address, ord.fecha_entrega, ord.observaciones, ord.pago_abono, ord.pago_total;
        ";
    $data['form'] = $localConnection->goQuery($sql);

    foreach ($data['form'] as &$row) {
      if ($row['productos'] !== null) {
        $row['productos'] = json_decode($row['productos'], true);
      }
    }
    // GUARDAR LA ORDEN
    $data['tipo'] = 'Orden';
    $data['id_empleado'] = '1';

    $sql = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES ('" . json_encode($data['form'][0]) . "', " . $data['id_empleado'] . ", '" . $data['tipo'] . "')";
    $object['sql_insert'] = $sql;
    $data['response_INSERT'] = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/send-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $localConnection = new LocalDB();

    try {
      $id_orden = $dataMensaje['id_orden'] ?? null;
      if (!$id_orden) {
        throw new Exception("El 'id_orden' es requerido.", 400);
      }

      // 1. Obtener el teléfono del CLIENTE (la lógica clave)
      $infoSql = 'SELECT c.phone FROM ordenes o JOIN customers c ON o.id_wp = c._id WHERE o._id = ' . intval($id_orden);
      $contactInfo = $localConnection->goQuery($infoSql);

      if (empty($contactInfo) || empty($contactInfo[0]['phone'])) {
        throw new Exception("No se encontró un número de teléfono para el cliente de la orden {$id_orden}.", 404);
      }
      $clientPhone = $contactInfo[0]['phone'];
      $message_to_send = $dataMensaje['mensaje'] ?? '';

      // 2. Instanciar el cliente de la API de WhatsApp
      $whatsAppApiClient = new WhatsAppAPIClient('https://ws.nineteengreen.com/');

      // 3. Llamar a la función para enviar el mensaje directo (el método del endpoint interno)
      $nodeApiResponse = $whatsAppApiClient->sendDirectMessageToNode(
        ID_EMPRESA,
        $clientPhone,
        $message_to_send
      );

      // Determinar el código de estado HTTP basado en la respuesta del servicio
      $status_code = 200;
      if (isset($nodeApiResponse['success']) && $nodeApiResponse['success'] === false) {
        $status_code = 500;
      }
      if (isset($nodeApiResponse['http_code_received'])) {
        $status_code = $nodeApiResponse['http_code_received'];
      }

      $response->getBody()->write(json_encode($nodeApiResponse, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
      return $response->withHeader('Content-Type', 'application/json')->withStatus($status_code);
    } catch (Exception $e) {
      $error_payload = [
        'error' => 'Error en el endpoint /send-message',
        'details' => $e->getMessage()
      ];
      $status_code = $e->getCode() >= 400 ? $e->getCode() : 500;
      $response->getBody()->write(json_encode($error_payload, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
      return $response->withHeader('Content-Type', 'application/json')->withStatus($status_code);
    } finally {
      if (isset($localConnection)) {
        $localConnection->disconnect();
      }
    }
  });

  // OBTENER DEPARTAMENTOS PARA ENVIO DE WHATSAPP
  $app->get('/ws/departamentos', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = "SELECT
            a.id_orden,
            b._id id_departamento,
            b.departamento,
            b.orden_proceso,
            a.progreso
        FROM
            lotes_detalles_empleados_asignados a
        JOIN departamentos b ON
            b._id = a.id_departamento
        JOIN ordenes c ON c._id = a.id_orden
        WHERE
            c.status LIKE 'activa' OR c.status LIKE 'pausada' OR c.status LIKE 'En espera'
        ORDER BY c._id, b.orden_proceso ASC
        ";
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // CONSTRUIR MENSAJES DE WHATSAPP PARA CLIENTES
  $app->post('/ws/build-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $localConnection = new LocalDB();  // Asegúrate de que esta clase y su conexión/desconexión sean manejadas correctamente.

    $result = [];  // Inicializar $result para evitar errores si no se entra en ninguna condición

    // Validar que los datos necesarios están presentes
    if (!isset($dataMensaje['tipo']) || !isset($dataMensaje['id_orden'])) {
      $result['error'] = 'Faltan parámetros requeridos: tipo o id_orden.';
      $localConnection->disconnect();  // Asegúrate de desconectar incluso en caso de error temprano

      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')  // Eliminado el duplicado 'Access-Control-Allow-Headers'
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400)  // Bad Request si faltan parámetros
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }

    $tipo = $dataMensaje['tipo'];
    $id_orden = $dataMensaje['id_orden'];  // Considera sanitizar/validar $id_orden antes de usarlo en SQL

    // Buscar datos de la orden
    // Es MUY RECOMENDABLE usar sentencias preparadas para prevenir inyección SQL
    // Ejemplo conceptual (la implementación exacta depende de tu clase LocalDB):
    // $sql = "SELECT a._id id_orden, ... FROM ordenes a ... WHERE a._id = ?";
    // $orden = $localConnection->goQuery($sql, [$id_orden]);
    $sql = "SELECT
        a._id id_orden,
        a.cliente_nombre,
        b.phone,
        a.pago_descuento,
        a.pago_abono,
        a.pago_total,
        ((a.pago_total -  a.pago_descuento) - a.pago_abono) monto_pendiente,
        DATE_FORMAT(a.fecha_entrega, '%d/%m/%Y') fecha_entrega
        FROM
            ordenes a
        JOIN customers b ON b._id = a.id_wp
        WHERE
            a._id = " . intval($id_orden);  // Sanitización básica, pero sentencias preparadas son mejores.
    $orden_data = $localConnection->goQuery($sql);

    if (empty($orden_data)) {
      $result['error'] = "Orden con ID {$id_orden} no encontrada.";
      $localConnection->disconnect();
      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404)  // Not Found
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }
    $orden = $orden_data[0];  // Asumimos que _id es único y siempre devuelve un solo registro si existe

    // Buscar los productos asociados a la orden
    $sql_products = "SELECT CONCAT('- *',a.name, ':* Talla ', a.talla, ', ', a.cantidad, ' unidades') item_product FROM ordenes_productos a WHERE a.id_orden = " . intval($id_orden) . ' ORDER BY a.name ASC';
    $products_data = $localConnection->goQuery($sql_products);

    // CARGAR DATOS

    // DATOS ORDEN
    $phone = $orden['phone'];

    // PRODUCTOS
    // Usar comillas dobles para que \n se interprete como salto de línea
    $productos_string = '';  // Inicializar como cadena vacía. El primer \n puede venir de la plantilla.
    if (!empty($products_data)) {
      foreach ($products_data as $item) {
        $productos_string .= $item['item_product'] . "\n";  // Usar comillas dobles para el salto de línea
      }
      // Opcional: remover el último \n si no se desea un salto de línea extra al final de la lista
      $productos_string = rtrim($productos_string, "\n");
    } else {
      $productos_string = 'No hay productos detallados para esta orden.';  // Mensaje por si no hay productos
    }

    // Determinar tipo de mensaje
    switch ($tipo) {
      case 'inicio':
        $sql_config = 'SELECT msg_welcome msg FROM config WHERE _id = 1';  // Las comillas simples están bien aquí si no hay \n
        break;

      case 'fin':
        $sql_config = 'SELECT msg_bye msg FROM config WHERE _id = 1';  // Las comillas simples están bien aquí si no hay \n
        break;

      case 'paso':
        $sql_config = "SELECT mensaje msg FROM departamentos WHERE _id = {$dataMensaje['id_departamento']}";
        break;

      case 'custom':
        $sql_config = null;
        break;

      case 'cobro':
        $sql_config = "SELECT
                    a._id id_orden,
                    a.pago_total,        
                    (SUM(b.abono)) total_abonos,
                    (SUM(b.descuento)) total_descuentos,
                    (a.pago_total - (SUM(b.descuento)) - SUM(b.abono))  deuda,
                    (SELECT msg_saldo FROM config WHERE _id = 1) msg
                FROM
                    ordenes a
                    JOIN abonos b ON b.id_orden = a._id    
                WHERE
                    a._id = $id_orden";  // Las comillas simples están bien aquí si no hay \n
        break;
    }

    if ($sql_config !== null) {
      $config_data = $localConnection->goQuery($sql_config);

      if (empty($config_data) || !isset($config_data[0]['msg'])) {
        $result['error'] = 'Mensaje configurado en la base de datos.';
        $localConnection->disconnect();
        return $response
          ->withHeader('Access-Control-Allow-Origin', '*')
          ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500)  // Error del servidor: configuración faltante
          ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
      }
      $mensaje_plantilla = $config_data[0]['msg'];

      // Importante: Si la plantilla `msg_welcome` de la BD también tiene '\n' literales,
      // necesitarás reemplazarlos también.
      // Ejemplo: $mensaje_plantilla = str_replace('\n', "\n", $mensaje_plantilla);
      $reemplazo = [];

      if ($tipo === 'inicio' || $tipo === 'fin' || $tipo === 'paso') {
        $busqueda = ['[CLIENTE]', '[ORDEN_ID]', '[FECHA_ENTREGA]', '[PRODUCTOS]', '[TOTAL_ORDEN]'];
        $reemplazo = [
          "{$orden['cliente_nombre']}",
          "{$orden['id_orden']}",
          "{$orden['fecha_entrega']}",
          $productos_string,
          // CORRECCIÓN: Convertir la cadena a float antes de formatear
          // number_format(floatval($orden['pago_total']), 2, '.', ',')
        ];
      } else if ($tipo === 'cobro') {
        $busqueda = ['[CLIENTE]', '[ORDEN_ID]', '[FECHA_ENTREGA]', '[PRODUCTOS]', '[TOTAL_ORDEN]', '[TOTAL_ABONOS]', '[TOTAL_DESCUENTOS]', '[TOTAL_DEUDA]'];
        $reemplazo = [
          "{$orden['cliente_nombre']}",
          "{$orden['id_orden']}",
          "{$orden['fecha_entrega']}",
          "{$productos_string}",
          "{$config_data[0]['pago_total']}",
          "{$config_data[0]['total_abonos']}",
          "{$config_data[0]['total_descuentos']}",
          "{$config_data[0]['deuda']}",
          $productos_string,
          // CORRECCIÓN: Convertir la cadena a float antes de formatear
          // number_format(floatval($orden['pago_total']), 2, '.', ',')
        ];
      } else if ($tipo === 'custom') {
        $mensaje_plantilla = $dataMensaje['message'];
      }

      $result['msg_ws'] = str_replace($busqueda, $reemplazo, $mensaje_plantilla);
      $result['mensaje_plantilla_original'] = $mensaje_plantilla;  // Para depuración
    } else {
      $result['msg_ws'] = $dataMensaje['message'];
      // Enviar mensaje personalizado
    }

    // Enviar mensaje
    // Considera obtener ID_EMPRESA de una constante o configuración de forma más segura.
    if (!defined('ID_EMPRESA')) {
      // Manejar el caso donde ID_EMPRESA no está definida
      $result['error_envio'] = 'ID_EMPRESA no está definida. No se puede enviar el mensaje.';
      $localConnection->disconnect();
      return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500)
        ->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    }

    // La URL base del API de WhatsApp debería ser idealmente una constante o configuración
    // El constructor de WhatsAppAPIClient debería tomar la URL base del API, no un endpoint específico.
    // $apiBaseUrl = 'https://ws.nineteengreen.com/'; // O 'http://localhost:3000' si es local
    // $msgApi = new WhatsAppAPIClient($apiBaseUrl);

    // Asumiendo que la clase WhatsAppAPIClient fue ajustada como se discutió o que el constructor maneja esto:
    $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/');  // Ajustar según la implementación de tu clase

    $testResp = $msgApi->sendMessageCustom(ID_EMPRESA, $id_orden, $phone, $result['msg_ws']);
    $result['result_msg'] = $testResp;

    $localConnection->disconnect();

    // Verificar datos\
    if (isset($reemplazo)) {
      $result['reemplazo'] = $reemplazo;
    } else {
      $result['reemplazo'] = [];
    }

    // Asegúrate de que Access-Control-Allow-Headers no esté duplicado y contenga todos los necesarios.
    // El segundo 'Access-Control-Allow-Headers' en tu código original hacía que el primero se ignorara.
    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')  // Corregido
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));

    return $response;
  });

  // CONSTRUIR MENSAJES DE WHATSAPP PARA EMPLEADOS (INTERNO)
  $app->post('/ws/build-message/interno', function (Request $request, Response $response, $args) {
    $result = [];  // Inicializar el array de resultado
    try {
      $dataMensaje = $request->getParsedBody();

      // Validar que los datos necesarios están presentes
      if (!isset($dataMensaje['id_destino']) || !isset($dataMensaje['message'])) {
        // Si ID_EMPRESA no viene en el request, debes obtenerlo de alguna otra forma (sesión, constante, etc.)
        // Para este ejemplo, asumiré que lo envías en el request o lo tienes definido como constante.
        // Si viene en el request, asegúrate que Nuxt lo envíe.
        // Por ahora, usaré una constante placeholder o lo tomaré del request.
        // Si lo vas a enviar desde Nuxt, añade 'id_empresa' al URLSearchParams

        $result['error'] = 'Faltan parámetros requeridos (id_destino, message).';
        $response->getBody()->write(json_encode($result));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      // DEFINE O RECUPERA ID_EMPRESA AQUÍ. Ejemplo:
      // $id_empresa_para_node = ID_EMPRESA_CONSTANTE; // Si es una constante global
      // O si lo envías desde Nuxt:
      $id_empresa_para_node = ID_EMPRESA;  // Usa un valor por defecto o maneja error si no está

      $id_destino_empleado = $dataMensaje['id_destino'];
      $message_to_send = $dataMensaje['message'];
      // $id_orden = $dataMensaje['id_orden'] ?? null; // Opcional, si lo necesitas para algo más
      // $id_remitente = $dataMensaje['id_remitente'] ?? null; // Opcional
      // $id_departamento = $dataMensaje['id_departamento'] ?? null; // Opcional

      $localConnection = new LocalDB();  // Asegúrate de que esta clase y su conexión/desconexión sean manejadas correctamente.

      // BUSCAR TELEFONO DEL EMPLEADO AL QUE SE LE ENVIARÁ EL MENSAJE
      // Es importante sanitizar $id_destino_empleado antes de usarlo en una consulta SQL
      $sql = "SELECT nombre, telefono FROM api_empresas.empresas_usuarios WHERE id_usuario = '" . $dataMensaje['id_destino'] . "'";
      $data_emp = $localConnection->goQuery($sql);

      if (empty($data_emp) || !isset($data_emp[0]['telefono'])) {
        $result['error'] = 'No se encontró el teléfono para el empleado destino.';
        $result['id_destino_buscado'] = $id_destino_empleado;
        $response->getBody()->write(json_encode($result));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(404);  // Not Found
      }

      // BUSCAR NOMBRE DEL DEPARTAMENTO
      $sql = "SELECT departamento FROM departamentos WHERE _id = '" . $dataMensaje['id_departamento'] . "'";
      $data_dep = $localConnection->goQuery($sql);

      $localConnection->disconnect();  // Desconectar tan pronto como ya no se necesite

      $phone_destino = $data_emp[0]['telefono'];
      $name_destino = $data_emp[0]['nombre'];
      $departamento = $data_dep[0]['departamento'];

      // Preparar mensaje
      $formatted_msg = "*Mensaje Interno*\nDepartamento: $departamento\nDe: {$dataMensaje['nombre_empleado']}\nPara:$name_destino\n\n$message_to_send";

      // Instanciar el cliente de la API de WhatsApp
      $whatsAppApiClient = new WhatsAppAPIClient('https://ws.nineteengreen.com/');

      // Llamar a la nueva función para enviar el mensaje a través de la API de Node.js
      $nodeApiResponse = $whatsAppApiClient->sendDirectMessageToNode(
        ID_EMPRESA,  // El ID de la empresa que usa tu API de Node.js
        $phone_destino,
        $formatted_msg
      );

      $result['node_api_response'] = $nodeApiResponse;

      // Determinar el código de estado basado en la respuesta de la API de Node
      // Tu API Node devuelve 'success: true/false'. Usemos eso.
      if (isset($nodeApiResponse['success']) && $nodeApiResponse['success'] === true) {
        $httpStatus = 200;
      } elseif (isset($nodeApiResponse['error'])) {  // Si hubo un error en la clase WhatsAppAPIClient o la API Node devolvió error
        $httpStatus = 500;  // Internal Server Error o el código que corresponda
        if (isset($nodeApiResponse['http_code_received']) && $nodeApiResponse['http_code_received']) {
          // Si la clase capturó un código de error HTTP específico de Node, úsalo.
          $httpStatus = $nodeApiResponse['http_code_received'];
        } elseif (isset($nodeApiResponse['details']) && strpos($nodeApiResponse['details'], 'Error HTTP 400') !== false) {
          $httpStatus = 400;  // Bad request a la API de Node
        } elseif (isset($nodeApiResponse['details']) && strpos($nodeApiResponse['details'], 'Error HTTP 503') !== false) {
          $httpStatus = 503;  // Service unavailable (cliente Node no listo)
        }
      } else {
        $httpStatus = 500;  // Respuesta inesperada
      }
    } catch (\Exception $e) {
      // Capturar cualquier excepción inesperada durante el proceso
      $result['error'] = 'Excepción general en el endpoint /ws/build-message/interno.';
      $result['exception_message'] = $e->getMessage();
      $httpStatus = 500;
    }

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($httpStatus);  // Usar el status determinado

    $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));  // JSON_PRETTY_PRINT para depuración

    return $response;
  });

  // GUARDAR MENSAJES DE INICIO Y FIN DE ORDEN
  $app->post('/update-message', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $localConnection = new LocalDB();

    // VERIFICAR EL TIPO DE MENSAJE welcome ó bye
    if ($dataMensaje['tipo'] === 'welcome') {
      $sql = "UPDATE config SET msg_welcome = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    } else if ($dataMensaje['tipo'] === 'bye') {
      $sql = "UPDATE config SET msg_bye = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    } else if ($dataMensaje['tipo'] === 'saldo') {
      $sql = "UPDATE config SET msg_saldo = '{$dataMensaje['mensaje']}' WHERE _id = 1";
    }

    $result = $localConnection->goQuery($sql);

    $localConnection->disconnect();

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));

    return $response;
  });

  // OBTENER DATOS DE LA CONFIGURACIÓN DEL SISTEMA
  $app->get('/config', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT * FROM config';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    if (empty($data)) {
      $response->getBody()->write(json_encode([
        'status' => 'error',
        'message' => 'No hay configuración establecida.',
      ]));
      return $response;
    }

    $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER DATOS DE LA CONFIGURACIÓN DEL SISTEMA EDITADA POR GEMINI
  $app->get('/config_crazy', function (Request $request, Response $response) {
    $id_empresa = isset($request->getHeader('Authorization')[0]) ? (int) $request->getHeader('Authorization')[0] : null;

    if ($id_empresa) {
      $dsn = 'mysql:host=localhost;dbname=api_empresas';
      $user = 'setup_admin';
      $password = 'SetupAdmin2024!';
      $user = 'setup_admin';
      $password = 'SetupAdmin2024!';

      try {
        $pdo = new PDO($dsn, $user, $password, [
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = 'SELECT db_host, db_user, db_password, nombre, db_name FROM empresas WHERE id_empresa = :id_empresa';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_empresa' => $id_empresa]);

        $connectionDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($connectionDetails) {
          if (empty($connectionDetails['db_name'])) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'La base de datos para esta empresa no está configurada.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
          }
          $local_dns = 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name'];
          $local_user = $connectionDetails['db_user'];
          $local_pass = $connectionDetails['db_password'];
          $localConnection = new LocalDB('', $local_dns, $local_user, $local_pass);
        } else {
          $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Empresa no encontrada.']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
      } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos de empresas.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
      }
    } else {
      $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Falta el ID de la empresa en el encabezado de autorización.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'SELECT * FROM config';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    if (isset($data['status']) && $data['status'] === 'error') {
      $response->getBody()->write(json_encode($data));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
    }

    if (empty($data)) {
      $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'No hay configuración establecida.']));
    } else {
      $response->getBody()->write(json_encode($data[0], JSON_NUMERIC_CHECK));
    }

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // OBTENER FECHA Y HORA DE LAS TABLAS MODIFICACAS

  /*
   * $app->get('/check-tables-updates', function (Request $request, Response $response) {
   *     $localConnection = new LocalDB();
   *
   *     $sql = "SELECT TABLE_NAME, UPDATE_TIME
   *     FROM INFORMATION_SCHEMA.TABLES
   *     WHERE TABLE_SCHEMA = '" . EMPRESA_DB . "'
   *     AND TABLE_TYPE = 'BASE TABLE'
   *     AND UPDATE_TIME IS NOT NULL
   *     ORDER BY UPDATE_TIME DESC;";
   *
   *     $data = $localConnection->goQuery($sql);
   *
   *     $localConnection->disconnect();
   *
   *     $response->getBody()->write(json_encode($sql, JSON_NUMERIC_CHECK));
   *     return $response
   *         ->withHeader('Content-Type', 'application/json')
   *         ->withStatus(200);
   * });
   */
  /* $app->post('/send-message', function (Request $request, Response $response, $args) {
      $dataMensaje = $request->getParsedBody();

      // Enviar WhatsApp Aqui
      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'general', $dataMensaje['mensaje']);

      $response = $response
          ->withHeader('Access-Control-Allow-Origin', '*')
          ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);

      $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));

      return $response;
  }); */

  // Endpoint para actualizar la configuración de mensajes (mensaje y enviar_mensaje) de un departamento
  $app->post('/departamentos/editar/settings', function (Request $request, Response $response, $args) {  // Añadimos 'use ($localConnection)' si es una variable externa
    // Obtener los datos del cuerpo de la solicitud POST
    $data = $request->getParsedBody();

    // Crear instancia de la conexión a la base de datos
    $localConnection = new LocalDB();

    // Validar que los datos necesarios estén presentes
    if (!isset($data['id_departamento']) || !isset($data['enviar_mensaje']) || !isset($data['mensaje'])) {
      $response = $response->withStatus(400);  // Bad Request
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Faltan parámetros necesarios (id_departamento, enviar_mensaje, mensaje).',
      ]));
      return $response;
    }

    $id_departamento = $data['id_departamento'];
    // Asegurarse de que enviar_mensaje sea un valor booleano o numérico seguro (0 o 1)
    $enviar_mensaje = filter_var($data['enviar_mensaje'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
    $mensaje = $data['mensaje'];

    // Validar que id_departamento sea un número entero
    if (!filter_var($id_departamento, FILTER_VALIDATE_INT)) {
      $response = $response->withStatus(400);  // Bad Request
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'ID de departamento inválido.',
      ]));
      return $response;
    }

    // *** La conexión a la base de datos ($localConnection) se asume disponible aquí ***
    // *** Eliminada la instanciación: $localConnection = new LocalDB(); ***

    // Preparar la consulta SQL UPDATE
    // Usamos sentencias preparadas para prevenir inyecciones SQL
    $sql = 'UPDATE departamentos SET enviar_mensaje = ?, mensaje = ? WHERE _id = ?';

    try {
      // Ejecutar la consulta preparada usando la conexión disponible
      // Asume que goQuery o un método similar en LocalDB soporta sentencias preparadas
      // Si LocalDB no soporta sentencias preparadas, deberás sanitizar $mensaje y $enviar_mensaje manualmente
      // o adaptar LocalDB. Sanitizar $mensaje es CRUCIAL si no usas preparadas.
      // Ejemplo de sanitización básica (NO RECOMENDADO sobre preparadas):
      // $mensaje_sanitized = $localConnection->escape_string($mensaje);
      // $sql = "UPDATE departamentos SET enviar_mensaje = $enviar_mensaje, mensaje = '$mensaje_sanitized' WHERE _id = $id_departamento";

      // Suponiendo que LocalDB tiene un método para ejecutar consultas preparadas:
      $params = [$enviar_mensaje, $mensaje, $id_departamento];
      $result = $localConnection->goQuery($sql, $params);  // Ajusta el método si es necesario

      // Verificar si la actualización fue exitosa
      // goQuery podría devolver true/false, el número de filas afectadas, etc.
      // Aquí asumimos que si no lanza una excepción, fue exitoso, o que $result indica éxito.
      // Deberías verificar la implementación de goQuery para confirmarlo.
      // Por ejemplo, si goQuery devuelve el número de filas afectadas:
      // if ($result > 0) { ... } else { // No se encontró el departamento o no hubo cambios ... }

      // Para simplificar, asumimos éxito si la consulta se ejecutó sin errores.
      // Una verificación más robusta podría ser necesaria.

      $response = $response->withStatus(200);  // OK
      $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Configuración del departamento actualizada correctamente.',
        'result' => $result,
      ]));
    } catch (\Exception $e) {
      // Capturar excepciones (ej: error de base de datos)
      error_log('Error al actualizar departamento: ' . $e->getMessage());  // Log del error en el servidor
      $response = $response->withStatus(500);  // Internal Server Error
      $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Ocurrió un error al actualizar la configuración del departamento.',
        'error' => $e->getMessage()  // Opcional: incluir mensaje de error detallado (útil en desarrollo)
      ]));
    } finally {
      // *** Eliminada la desconexión: $localConnection->disconnect(); ***
      // La gestión de la conexión (cierre) se asume manejada externamente.
    }

    // Añadir encabezados CORS (aunque el frontend use wildcard, es buena práctica)
    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')  // O el origen(es) específico(s) en producción
      ->withHeader('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-ID-Empresa')  // Asegúrate de incluir todos los headers que tu frontend envía
      ->withHeader('Content-Type', 'application/json');

    return $response;
  });

  // ENVIAR MENSAJES A CLIENTES PASOS DE PRODUCCIÓN
  $app->post('/send-message-produccion', function (Request $request, Response $response, $args) {
    $dataMensaje = $request->getParsedBody();
    $id_dep = $dataMensaje['id_departamento_empelado'];

    $localConnection = new LocalDB();

    // Inicializar variables para evitar errores de undefined
    $testResp = null;
    $msg = ['mensaje' => ''];

    // Buscar estado de enviar_mensaje
    $sql = "SELECT enviar_mensaje FROM departamentos WHERE _id = $id_dep";
    $response_departamentos = $localConnection->goQuery($sql);
    $enviar_mensaje = intval($response_departamentos[0]['enviar_mensaje'] ?? 0);

    // Buscamos el nombre del cliente
    $sql = 'SELECT
                    a.id_wp,
                    b.first_name
                FROM
                    ordenes a
                LEFT JOIN customers b ON b._id = a.id_wp
                WHERE
                    a._id = ' . $dataMensaje['id_orden'];
    $response_client = $localConnection->goQuery($sql);
    $cliente = $response_client[0]['first_name'] ?? 'Cliente';

    if ($dataMensaje['tipo'] == 'terminar') {
      $msg['mensaje'] = 'Hola ' . $cliente . ', *su orden número ' . $dataMensaje['id_orden'] . ' está lista, puede pasar a retir su pedido.*';

      // Enviar mensaje de orden terminada
      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'terminar-produccion', $msg);

    } else if ($dataMensaje['tipo'] == 'paso') {
      $msg['mensaje'] = 'Hola ' . $cliente . ', en este momento su orden número ' . $dataMensaje['id_orden'] . ' se encuentra en el departamento de ' . $dataMensaje['departamento'] . ', ';

      // Enviamos Whastapp
      if ($enviar_mensaje) {
        switch ($dataMensaje['departamento']) {
          case 'Impresión':
            $msg['mensaje'] .= 'Tu diseño personalizado está siendo impreso con los más altos estándares de calidad. Este proceso garantiza colores vibrantes y una gran durabilidad. ¡En breve podrás lucir tu prenda única';
            break;
          case 'Estampado':
            $msg['mensaje'] .= 'En este proceso, se transfiere un diseño a la tela mediante diferentes técnicas como la sublimación, dtf o la impresión digital. Esto permite crear patrones, logos o imágenes personalizadas sobre el tejido';
            break;
          case 'Corte':
            $msg['mensaje'] .= 'Una vez impresa la tela, se corta siguiendo patrones precisos para obtener las piezas que conformarán la prenda. Este proceso se realiza con corte laser de última tecnología que garantizan la precisión de cada corte';
            break;
          case 'Costura':
            $msg['mensaje'] .= ' Las piezas cortadas se unen mediante costura para dar forma a la prenda. Este proceso se realiza con máquinas industriales, dependiendo del tipo de prenda y el nivel de detalle';
            break;
          case 'Limpieza':
            $msg['mensaje'] .= 'Cada prenda se revisa minuciosamente para detectar posibles defectos como costuras mal hechas, hilos sueltos o manchas. Este paso es fundamental para garantizar la calidad del producto final';
            break;
          case 'Revisión':
            $msg['mensaje'] .= 'Se realizan pruebas de resistencia, color y acabado para asegurar que la prenda cumpla con los estándares de calidad establecidos';
            break;
          default:
            $msg['mensaje'] = 'Unknown';
            break;
        }

        if ($msg['mensaje'] != '') {
          $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
          $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'paso-produccion', $msg);
        }
      } else {
        $msg['mensaje'] = '';
        $testResp = ['status' => 'skipped', 'message' => 'Envío de mensaje deshabilitado para este departamento'];
      }
    } elseif ($dataMensaje['tipo'] == 'cobrar') {
      $msg['mensaje'] = 'Hola ' . $cliente . ' le recordamos que tiene una deuda pendiente de *' . $dataMensaje['monto'] . ' USD* de su Orden número *' . $dataMensaje['id_orden'] . '*';

      $msgApi = new WhatsAppAPIClient('https://ws.nineteengreen.com/send-message/' . $dataMensaje['id_orden']);
      $testResp = $msgApi->sendMessage(ID_EMPRESA, $dataMensaje['id_orden'], 'paso-produccion', $msg);
    } else {
      $testResp = ['status' => 'error', 'message' => 'Tipo de mensaje no reconocido: ' . ($dataMensaje['tipo'] ?? 'null')];
    }

    $localConnection->disconnect();

    $response = $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa')
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);

    $response->getBody()->write(json_encode($testResp, JSON_NUMERIC_CHECK));

    return $response;
  });



  // CRUD para Catalogo de Impresoras

  /** * PRUEBAS DE HISTÓRICO */

  /* $app->get('/h/backup/pagos', function (Request $request, Response $response) {
        $sql = "SELECT MAX(_id) + 1 id FROM ordenes";

        $localConnection = new LocalDB();
        $data = $localConnection->goQueryCopy($sql);
        $localConnection->disconnect();

        if (!$data[0]["id"]) {
            $data[0]["id"] = "1";
        }

        $input = str_pad($data[0]["id"], 3, "0", STR_PAD_LEFT);
        // $input = '33';
        // $nextId["crudo"] =  $data[0]["id"];
        $nextId["id"] = str_pad($input, 3, "0", STR_PAD_LEFT);

        $response->getBody()->write(json_encode($nextId, JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }); */
  /** * FIN PRUEBAS DE HISTÓRICO */

  /** * GENERAL */
  $app->get('/next-id-order', function (Request $request, Response $response) {
    $localConnection = new LocalDB();

    $sql = 'SELECT MAX(_id) + 1 id FROM ordenes';
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    if (!$data[0]['id']) {
      $data[0]['id'] = '1';
    }

    // Convertir el ID a string antes de pasarlo a str_pad() para compatibilidad con PHP 8+
    // y eliminar la llamada redundante a str_pad.
    $nextId['id'] = str_pad((string) $data[0]['id'], 3, '0', STR_PAD_LEFT);

    $response->getBody()->write(json_encode($nextId, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** FIN GENRAL */

}; // Fin de la función que envuelve las rutas
