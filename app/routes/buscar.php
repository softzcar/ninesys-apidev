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
            $id_customer = $id_wp[0]['id_wp'];
            $id_customer = $id_wp[0]['id_wp'];

            $woo = new WooMe();
            $data = $woo->getCustomerByIdWP($id_customer);
            $customer = json_decode(json_encode($data), true);
            $object['customer']['data'] = $customer;

            $object['customer']['nombre'] = ($customer[0]['billing_first_name'] ?? '') . ' ' . ($customer[0]['billing_last_name'] ?? '');
            $object['customer']['direccion'] = $customer[0]['billing_address_1'] ?? '';
            $object['customer']['email'] = $customer[0]['billing_email'] ?? '';
            $object['customer']['cedula'] = $customer[0]['billing_postcode'] ?? '';
            $object['customer']['telefono'] = $customer[0]['billing_phone'] ?? '';

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
          JOIN customers b ON a.id_wp = b._id
          LEFT JOIN api_empresas.empresas_usuarios c ON c.id_usuario = a.responsable
          WHERE
            a._id = ' . $id;
            $object['orden'] = $localConnection->goQuery($sql_orden);

            // --- INICIO: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---
            $sql_abonos = 'SELECT SUM(abono) AS total_abonos, SUM(descuento) AS total_descuentos FROM abonos WHERE id_orden = ' . $id;
            $totales_abonos = $localConnection->goQuery($sql_abonos);

            if (isset($object['orden'][0])) {
                $object['orden'][0]['pago_abono'] = (float) ($totales_abonos[0]['total_abonos'] ?? 0);
                $object['orden'][0]['pago_descuento'] = (float) ($totales_abonos[0]['total_descuentos'] ?? 0);
            }
            // --- FIN: CÁLCULO DE ABONOS Y DESCUENTOS ACTUALIZADOS ---

            // Buscar datos del diseño
            $sql = 'SELECT tipo FROM disenos WHERE id_orden = ' . $id;
            $object['diseno'] = $localConnection->goQuery($sql);
            if (empty($object['diseno'])) {
                $object['diseno'][]['tipo'] = 'Ninguno';
            }

            // Buscar datos de productos
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
          LEFT JOIN (
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
          ) AS prices_json ON prices_json.product_id = pr._id -- Unir con la tabla de productos
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
                $data[$key]['prices'] = json_decode($product['prices']);
                $key++;
            }
            $object['productos'] = $data;
            $object['productos_count'] = count($object['productos']);
            $object['conterwoo'] = count($object['productos']);
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
