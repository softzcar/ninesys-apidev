<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

  // Función para obtener la respuesta de /buscar
  function obtenerRespuestaBuscar($id, $email = null): array
  {
    $object = array();
    $localConnection = new LocalDB();

    // Verificar existencia de la orden
    $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
    $resp = $localConnection->goQuery($sql);

    if (!$resp) {
      $object = $resp;
    } else {
      // Buscar datos del cliente en Woocommerce
      $sql = 'SELECT id_wp FROM ordenes WHERE _id = ' . $id;
      $id_wp = $localConnection->goQuery($sql);
      $id_customer = $id_wp[0]['id_wp'] ?? null;

      $object['customer']['data'] = [];
      $object['customer']['nombre'] = '';
      $object['customer']['direccion'] = '';
      $object['customer']['email'] = '';
      $object['customer']['cedula'] = '';
      $object['customer']['telefono'] = '';

      if (!empty($id_customer) && is_numeric($id_customer) && $id_customer > 0) {
        $woo = new WooMe();
        $data = $woo->getCustomerByIdWP($id_customer);
        $customer = json_decode(json_encode($data), true);
        $object['customer']['data'] = $customer;

        if (isset($customer[0])) {
          $object['customer']['nombre'] = ($customer[0]['billing_first_name'] ?? '') . ' ' . ($customer[0]['billing_last_name'] ?? '');
          $object['customer']['direccion'] = $customer[0]['billing_address_1'] ?? '';
          $object['customer']['email'] = $customer[0]['billing_email'] ?? '';
          $object['customer']['cedula'] = $customer[0]['billing_postcode'] ?? '';
          $object['customer']['telefono'] = $customer[0]['billing_phone'] ?? '';
        }
      }

      // Buscar datos de la orden!
      // CONSULTA CORREGIDA: Se eliminaron las líneas comentadas que causaban el error.
      $sql_orden = 'SELECT
            a._id,
            a.status,
            a.cliente_nombre,
            c.nombre AS vendedor,
            b.cedula,
            a.fecha_inicio,
            a.fecha_entrega,
            a.pago_total
          FROM
            ordenes a
          LEFT JOIN customers b ON a.id_wp = b._id
          LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = a.responsable
          WHERE
            a._id = ' . $id;
      $object['orden'] = $localConnection->goQuery($sql_orden);

      // --- INICIO: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---
      if (DB_DRIVER === 'pgsql') {
        $sql_abonos = "SELECT
                          SUM(abono) AS total_abonos,
                          SUM(descuento) AS total_descuentos,
                          SUM(nota_credito) AS total_notas_credito,
                          STRING_AGG(CASE WHEN descuento > 0 THEN detalle ELSE NULL END, ', ') AS descuento_detalle
                       FROM abonos
                       WHERE id_orden = " . $id;
      } else {
        $sql_abonos = "SELECT
                          SUM(abono) AS total_abonos,
                          SUM(descuento) AS total_descuentos,
                          SUM(nota_credito) AS total_notas_credito,
                          GROUP_CONCAT(CASE WHEN descuento > 0 THEN detalle ELSE NULL END SEPARATOR ', ') AS descuento_detalle
                       FROM abonos
                       WHERE id_orden = " . $id;
      }
      $totales_abonos = $localConnection->goQuery($sql_abonos);

      if (isset($object['orden'][0])) {
        $object['orden'][0]['pago_abono'] = (float) ($totales_abonos[0]['total_abonos'] ?? 0);
        $object['orden'][0]['pago_descuento'] = (float) ($totales_abonos[0]['total_descuentos'] ?? 0);
        $object['orden'][0]['pago_nota_credito'] = (float) ($totales_abonos[0]['total_notas_credito'] ?? 0);
        $object['orden'][0]['descuento_detalle'] = $totales_abonos[0]['descuento_detalle'] ?? '';
      }
      // --- FIN: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---

      // --- INICIO: CONSULTA DE MÉTODOS DE PAGO ---
      $sql_metodos_pago = 'SELECT moneda, metodo_pago, detalle, monto, tasa FROM metodos_de_pago WHERE id_orden = ' . $id;
      $registros_metodos_pago = $localConnection->goQuery($sql_metodos_pago);

      // Inicializar todos los campos de métodos de pago en 0
      $metodos_pago = [
        'montoDolaresEfectivo' => 0,
        'montoDolaresEfectivoDetalle' => '',
        'montoDolaresZelle' => 0,
        'montoDolaresZelleDetalle' => '',
        'montoDolaresPanama' => 0,
        'montoDolaresPanamaDetalle' => '',
        'montoPesosEfectivo' => 0,
        'montoPesosEfectivoDetalle' => '',
        'montoPesosTransferencia' => 0,
        'montoPesosTransferenciaDetalle' => '',
        'montoBolivaresEfectivo' => 0,
        'montoBolivaresEfectivoDetalle' => '',
        'montoBolivaresPunto' => 0,
        'montoBolivaresPuntoDetalle' => '',
        'montoBolivaresPagomovil' => 0,
        'montoBolivaresPagomovilDetalle' => '',
        'montoBolivaresTransferencia' => 0,
        'montoBolivaresTransferenciaDetalle' => '',
      ];

      // Mapeo de moneda + metodo_pago a nombres de campos del frontend
      $mapeo_campos = [
        'Dólares_Efectivo' => 'montoDolaresEfectivo',
        'Dólares_Zelle' => 'montoDolaresZelle',
        'Dólares_Panamá' => 'montoDolaresPanama',
        'Pesos_Efectivo' => 'montoPesosEfectivo',
        'Pesos_Transferencia' => 'montoPesosTransferencia',
        'Bolívares_Efectivo' => 'montoBolivaresEfectivo',
        'Bolívares_Punto' => 'montoBolivaresPunto',
        'Bolívares_Pagomovil' => 'montoBolivaresPagomovil',
        'Bolívares_Transferencia' => 'montoBolivaresTransferencia',
      ];

      // Transformar registros al formato del frontend
      if ($registros_metodos_pago && is_array($registros_metodos_pago)) {
        foreach ($registros_metodos_pago as $registro) {
          $clave = $registro['moneda'] . '_' . $registro['metodo_pago'];

          if (isset($mapeo_campos[$clave])) {
            $campo_monto = $mapeo_campos[$clave];
            $campo_detalle = $campo_monto . 'Detalle';

            // Para todas las monedas, guardamos el monto en la moneda original
            // El frontend se encarga de hacer las conversiones si es necesario
            $metodos_pago[$campo_monto] += (float) $registro['monto'];

            // Concatenar detalles si hay múltiples pagos del mismo tipo
            if (!empty($registro['detalle'])) {
              if (!empty($metodos_pago[$campo_detalle])) {
                $metodos_pago[$campo_detalle] .= ', ' . $registro['detalle'];
              } else {
                $metodos_pago[$campo_detalle] = $registro['detalle'];
              }
            }
          }
        }
      }

      // Agregar métodos de pago al objeto de respuesta
      $object['metodos_pago'] = $metodos_pago;
      // --- FIN: CONSULTA DE MÉTODOS DE PAGO ---

      // Buscar datos del diseño
      $sql = 'SELECT tipo FROM disenos WHERE id_orden = ' . $id;
      $object['diseno'] = $localConnection->goQuery($sql);
      if (empty($object['diseno'])) {
        $object['diseno'][]['tipo'] = 'Ninguno';
      }

      // Buscar datos de productos
      if (DB_DRIVER === 'pgsql') {
        $sqlPricesJoin = "LEFT JOIN (
            -- Subconsulta derivada para agrupar los precios por producto
            SELECT
              pp.id_product AS product_id,
              json_agg(json_build_object('id', pp._id, 'price', pp.price, 'description', pp.descripcion)) AS prices
            FROM
              products_prices pp
            GROUP BY
              pp.id_product
          ) AS prices_json ON prices_json.product_id = pr._id -- Unir con la tabla de productos";
      } else {
        $sqlPricesJoin = 'LEFT JOIN (
            -- Subconsulta derivada para agrupar los precios por producto
            SELECT
              pp.id_product AS product_id,
              CONCAT(
                "[",
                GROUP_CONCAT(
                  JSON_OBJECT(
                    "id",
                    pp._id,
                    "price",
                    pp.price,
                    "description",
                    pp.descripcion
                  )
                ),
                "]"
              ) AS prices
            FROM
              products_prices pp
            GROUP BY
              pp.id_product
          ) AS prices_json ON prices_json.product_id = pr._id -- Unir con la tabla de productos';
      }

      $sql = 'SELECT
            op._id,
            op.name,
            pr.sku AS sku,
            pr._id AS cod,
            pr.fisico AS producto_fisico,
            op.id_woo,
            op.cantidad,
            op.id_size AS id_talla,
            s.nombre AS talla,
            op.id_tela,
            prices_json.prices, -- Aquí usamos el alias de la subconsulta derivada
            op.tela,
            op.id_tela,
            op.corte,
            op.precio_unitario AS precio,
            (SELECT attribute_name FROM products_attributes WHERE _id = op.id_products_attributes) atributo_nombre,
            op.id_products_attributes AS atributo -- Añadir el atributo del producto
          FROM
            ordenes_productos op
          LEFT JOIN
            products pr ON pr._id = op.id_woo
          LEFT JOIN
            sizes s ON s._id = op.id_size -- Unir directamente con sizes para la talla
          ' . $sqlPricesJoin . '
          WHERE
            op.id_orden = ' . $id;

      $tmpProducts = $localConnection->goQuery($sql);

      // ATRIBUTOS DE PRODUCTOS
      $sqlAttr = "SELECT id_product, attribute_value, attribute_price FROM products_attributes_values WHERE id_orden = {$id}";
      $object['atributos_prodcutos'] = $localConnection->goQuery($sqlAttr);

      // PARSEAR PRODUCTOS
      $data = [];
      $key = 0;
      foreach ($tmpProducts as $product) {
        $data[$key]['_id'] = intval($product['_id']);
        $data[$key]['name'] = $product['name'];
        $data[$key]['cod'] = $product['cod'];
        $data[$key]['producto_fisico'] = $product['producto_fisico'];
        $data[$key]['id_woo'] = $product['id_woo'];
        $data[$key]['cantidad'] = $product['cantidad'];
        $data[$key]['id_tela'] = $product['id_tela'];
        $data[$key]['id_talla'] = $product['id_talla'];
        $data[$key]['talla'] = $product['talla'];
        $data[$key]['tela'] = $product['tela'];
        $data[$key]['corte'] = $product['corte'];
        $data[$key]['precio'] = $product['precio'];
        $data[$key]['atributo'] = $product['atributo'];
        $data[$key]['atributo_nombre'] = $product['atributo_nombre'];
        // Manejar el caso donde prices puede ser NULL (productos sin precios asociados)
        $data[$key]['prices'] = !empty($product['prices']) ? json_decode($product['prices']) : [];
        $key++;
      }
      $object['productos'] = $data;
      $object['productos_count'] = count($object['productos']);
      $object['conterwoo'] = count($object['productos']);

      // Consultar datos de auditoría (cancelaciones y terminaciones manuales)
      $sqlAuditoria = "SELECT accion, id_admin, nombre_admin, motivo, fecha 
                       FROM ordenes_auditoria 
                       WHERE id_orden = " . $id . " 
                       ORDER BY fecha DESC 
                       LIMIT 1";
      $auditoria = $localConnection->goQuery($sqlAuditoria);
      $object['auditoria'] = !empty($auditoria) ? $auditoria[0] : null;
    }

    $localConnection->disconnect();

    $contentType = 'application/json';
    return array('object' => $object, 'contentType' => $contentType);
  }

  $app->get('/buscar/{id}[/{email}]', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $email = isset($args['email']) ? $args['email'] : null;

    $result = obtenerRespuestaBuscar($id, $email);
    $response->getBody()->write(json_encode($result['object'], JSON_NUMERIC_CHECK));

    return $response
      ->withHeader('Content-Type', $result['contentType'])
      ->withStatus(200);
  });
};
