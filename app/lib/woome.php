<?php

/**
 * llamdas a Woocomemrce
 */

use Automattic\WooCommerce\Client;

class WooMe
{  // HOSTINGER

  private $url = 'https://demotienda.nineteencustom.com';
  private $ck = 'ck_28dbfd8beb92a9454faf59c2b6da225dee5ad60a';
  private $cs = 'cs_dab1e683eff87b602d8b5ba293c57d8faf260de2';
  private $opt = ['version' => 'wc/v3', 'verify_ssl' => false];
  private $woocommerce;
  private $pdo;

  public function __construct()
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
  }

  public function goQueryProducts($sql)
  {
    $this->connectToWordpressDB();

    $mat = array();
    try {
      $res = $this->pdo->prepare($sql);
      $res->execute();

      $data = $res->fetchAll(PDO::FETCH_ASSOC);
      $mat = $data;
    } catch (PDOException $e) {
      $mat['status'] = 'error';
      $mat['message'] = $e->getMessage();
    }

    $key = 0;
    foreach ($mat as $product) {
      $ptoducts[$key]['ID'] = $product['product_id'];
      $ptoducts[$key]['Nombre'] = $product['product_name'];
      $ptoducts[$key]['Categoría'] = $product['category'];
      $ptoducts[$key]['Atributos'] = unserialize($product['attributes']);
      $ptoducts[$key]['Existencia'] = $product['stock_quantity'];

      $key++;
    }

    return $ptoducts;
  }

  public function getAllProducts()
  {
    // $sql = "SELECT _id cod, sku, product `name`, stock_quantity, price, category_ids categories FROM products";

    /* $sql = "SELECT
        p._id AS cod,
        p.sku,
        p.product AS `name`,
        p.stock_quantity,
        p.price,
        CONCAT(
            '[',
            GROUP_CONCAT(
                JSON_OBJECT(
                    'id', c._id,
                    'name', c.nombre
                )
            ),
            ']'
        ) AS categories
    FROM
        products p
    LEFT JOIN
        categories c ON FIND_IN_SET(c._id, p.category_ids)
    GROUP BY
        p._id, p.sku, p.product, p.stock_quantity, p.price;
    "; */

    $sql = 'SELECT
            p._id AS cod,
            p.sku, 
            p.product AS `name`,
            p.stock_quantity,
            p.comision,            
            p.price,
            p.fisico producto_fisico,
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
            ) AS prices,
            
            CONCAT(
                "[",
                GROUP_CONCAT(
                    JSON_OBJECT(
                        "id_products_conisiones",
                        pc._id,
                        "id_product",
                        pc.id_product,
                        "comision",
                        pc.comision,
                        "id_departamento",
                        pc.id_departamento
                    )
                ),
                "]"
            ) AS comisiones,
            
            CONCAT(
                "[",
                GROUP_CONCAT(
                    JSON_OBJECT("id", c._id, "name", c.nombre)
                ),
                "]"
            ) AS categories
        FROM
            products p
        LEFT JOIN products_prices pp ON
            pp.id_product = p._id
        LEFT JOIN products_comisiones pc ON
            pc.id_product = p._id
        LEFT JOIN categories c ON
            FIND_IN_SET(c._id, p.category_ids)
        GROUP BY
            p._id,
            p.sku,
            p.product,
            p.stock_quantity,
            p.price;';

    $localConnection = new LocalDB();
    $products = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // PARSEAR CATERGORIAS
    $key = 0;
    foreach ($products as $product) {
      $data[$key]['cod'] = intval($product['cod']);
      $data[$key]['sku'] = $product['sku'];
      $data[$key]['name'] = $product['name'];
      $data[$key]['comision'] = $product['comision'];
      $data[$key]['stock_quantity'] = $product['stock_quantity'];
      // $data[$key]['regular_price'] = $product['price'];
      $data[$key]['regular_price'] = 0;
      $data[$key]['prices'] = json_decode($product['prices']);
      $data[$key]['producto_fisico'] = json_decode($product['producto_fisico']);
      $data[$key]['comisiones'] = json_decode($product['comisiones']);
      $data[$key]['categories'] = json_decode($product['categories'], true);

      $key++;
    }

    return json_encode($data);
  }

  public function getAllProducts_wc()
  {
    $sql = "SELECT
        p.ID AS id,
        p.post_title AS name,
        CONCAT('[',
               GROUP_CONCAT(
                            IF(t.term_id <> 2,
                               JSON_OBJECT(
                                           'id', t.term_id,
                                           'name', t.name,
                                           'slug', t.slug
                                           ),
                               NULL
                               )
                            ),
               ']'
               ) AS categories, 
        a.meta_value AS attributes,
        IFNULL(m.meta_value, 0) AS stock_quantity,
        m_sku.meta_value AS sku,
        m_price.meta_value AS price,
        m_regular_price.meta_value AS regular_price,
        m_sale_price.meta_value AS sale_price,
        CONCAT('https://nineteengreen.com/', p.post_name) AS permalink
        FROM
        wp_posts p
        LEFT JOIN
        wp_term_relationships tr ON p.ID = tr.object_id
        LEFT JOIN
        wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        LEFT JOIN
        wp_terms t ON tt.term_id = t.term_id
        LEFT JOIN
        wp_postmeta a ON p.ID = a.post_id AND a.meta_key = '_product_attributes'
        LEFT JOIN
        wp_postmeta m ON p.ID = m.post_id AND m.meta_key = '_stock'
        LEFT JOIN
        wp_postmeta m_sku ON p.ID = m_sku.post_id AND m_sku.meta_key = '_sku'
        LEFT JOIN
        wp_postmeta m_price ON p.ID = m_price.post_id AND m_price.meta_key = '_price'
        LEFT JOIN
        wp_postmeta m_regular_price ON p.ID = m_regular_price.post_id AND m_regular_price.meta_key = '_regular_price'
        LEFT JOIN
        wp_postmeta m_sale_price ON p.ID = m_sale_price.post_id AND m_sale_price.meta_key = '_sale_price'
        WHERE
        p.post_type = 'product' AND p.post_title != 'AUTO-DRAFT' AND p.post_title IS NOT NULL 
        GROUP BY
        p.ID, p.post_title, a.meta_value, m.meta_value, m_sku.meta_value, m_price.meta_value, m_regular_price.meta_value, m_sale_price.meta_value, p.post_name
        ";

    $this->connectToWordpressDB();

    $mat = array();
    try {
      $res = $this->pdo->prepare($sql);
      $res->execute();

      $data = $res->fetchAll(PDO::FETCH_ASSOC);
      $mat = $data;
    } catch (PDOException $e) {
      $mat['status'] = 'error';
      $mat['message'] = $e->getMessage();
    }

    $key = 0;
    foreach ($mat as $product) {
      $products[$key]['cod'] = intval($product['id']);
      $products[$key]['sku'] = $product['sku'];
      $products[$key]['name'] = $product['name'];
      $products[$key]['stock_quantity'] = $product['stock_quantity'];
      $products[$key]['price'] = $product['price'];
      $products[$key]['regular_price'] = $product['regular_price'];
      $products[$key]['permalink'] = $product['permalink'];
      $products[$key]['attributes'] = unserialize($product['attributes']);
      $products[$key]['categories'] = json_decode($product['categories'], true);

      $key++;
    }

    return $products;
  }

  public function getOrdersCount($customer_email)
  {
    $sql = "SELECT
        COUNT(a.ID) total_ordenes
        FROM
        wp_postmeta b
        RIGHT JOIN wp_posts a ON
        a.ID = b.post_id
        WHERE
        a.post_type = 'shop_order' AND b.meta_value = '$customer_email'    
        ";

    $this->connectToWordpressDB();

    $mat = array();
    try {
      $res = $this->pdo->prepare($sql);
      $res->execute();

      $data = $res->fetchAll(PDO::FETCH_ASSOC);
      $mat = $data;
    } catch (PDOException $e) {
      $mat['status'] = 'error';
      $mat['message'] = $e->getMessage();
    }

    return intval($mat[0]['total_ordenes']);
  }

  private function connectToWordpressDB()
  {
    try {
      $this->pdo = new PDO(
        LOCAL_DSN_NINETEEN,
        LOCAL_USER,
        LOCAL_PASS,
        array(
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
        )
      );
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
      die('Database connection failed: ' . $e->getMessage());
    }
  }

  /**
   * Enviar correos
   */
  public function enviarCorreoElectronico($order_id, $html_message)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    // Obtén el correo electrónico del cliente desde la API de WooCommerce
    $dataOrder = $this->getOrderById($order_id);
    $customer_email = $dataOrder->billing->email;

    // Dirección de correo del remitente
    $from_email = 'nineteenventas@gmail.com';

    // Asunto del correo
    $subject = 'Correo de pruebas con formato html';

    // Encabezados para el formato HTML
    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
    $headers .= "From: $from_email" . "\r\n";

    // Envía el correo electrónico utilizando la función mail()
    $mail_sent = mail($customer_email, $subject, $html_message, $headers);

    if ($mail_sent) {
      return $html_message;  // Éxito
    } else {
      return false;  // Error
    }
  }

  public function sendMail($order_id, $html_message)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    // Preparar los datos para agregar la nota
    $data = [
      'note' => $html_message,
      'customer_note' => true
    ];

    // Llama a la API de WooCommerce para agregar la nota
    $response = $this->woocommerce->post('orders/' . $order_id . '/notes', $data);

    // Verifica la respuesta de la API
    if (!empty($response->id)) {
      return true;  // Éxito al agregar la nota y enviar el correo
    } else {
      return false;  // Error al agregar la nota a la orden
    }
  }

  public function sendMail_old_1($order_id, $mensaje)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $dataOrder = $this->getOrderById($order_id);

    $email = $dataOrder->billing->email;

    /* $data = [
    'note' => $mensaje,
    'customer_note' => true
    ];
    // Llama a la API de WooCommerce para agregar la nota y enviar el correo electrónico
    $response = $this->woocommerce->post('orders/' . $order_id . '/notes', $data);
    // Verifica la respuesta y devuelve un indicador de éxito o error
    if (!empty($response->id)) {
    return true; // Éxito
    } else {
    return false; // Error
} */
  }

  public function getOrderById($id_order)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $order = 'orders/' . $id_order;
    $myOrder = $this->woocommerce->get($order);

    return $myOrder;
  }

  /**
   * INICIO ORDENES
   */
  /* public function createOrder($order_data, $newJson)
  {
      $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
      // PREPARAR DATOS PARA LA CREACIÓN DE LA ORDEN ...
      $customer_data = $this->getCustomerById($order_data["id_wp"]);

      // PROCESAR LAS PRODUCTOS
      if (count(json_decode($newJson['productos'], true)) === 0) {
          $arrayProducts[] = [];
      } else {
          foreach (json_decode($newJson['productos'], true) as $key => $producto) {
              $cantidad = intval($producto["cantidad"]);
              // $codigo = intval($producto["cod"]);
              $arrayProducts[] = [
                  'product_id' => $producto["cod"],
                  'quantity' => $cantidad
              ];
          }
      }

      // Crear vector de datos delprodcuto
      $data = [
          'payment_method' => 'usd',
          'payment_method_title' => 'Dollars',
          'set_paid' => false,
          'billing' => [
              'first_name' => $customer_data->first_name,
              'last_name' => $customer_data->last_name,
              'address_1' => $customer_data->billing->address_1,
              'address_2' => '',
              'city' => 'Mérida',
              'state' => 'VE',
              'postcode' => '5145',
              'country' => 'VE',
              'email' => $customer_data->email,
              'customer_id' => $customer_data->id,
              'phone' => $customer_data->billing->phone
          ],
          'shipping' => [
              'first_name' => $customer_data->first_name,
              'last_name' => $customer_data->last_name,
              'address_1' => 'Venezuela',
              'address_2' => '',
              'city' => 'Mérida',
              'state' => 'VE',
              'postcode' => '5145',
              'country' => 'VE'
          ],
          'line_items' => $arrayProducts,
          'shipping_lines' => [
              [
                  'method_id' => 'flat_rate',
                  'method_title' => 'Flat Rate',
                  'total' => '0.00'
              ]
          ]
      ];

      // return $data;
      $myOrder = $this->woocommerce->post('orders/', $data);
      return $myOrder;
      // return $order_data;
  } */
  public function updateOrderStatus($id, $status)
  {
    $sql = "UPDATE
            `ordenes`
        SET
            `status` = '" . $status . "'
        WHERE
            _id = " . $id . ';';

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  /** FIN ORDENES */

  /**
   * INICIO PRODUCTOS
   */
  public function getProductById($id)
  {
    $sql = 'SELECT * FROM products WHERE _id = ' . $id;
    $localConnection = new LocalDB();
    $product = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return $product;
  }

  public function getProductSKU($id)
  {
    $sql = 'SELECT sku FROM products WHERE _id = ' . $id;
    $localConnection = new LocalDB();
    $product = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($product);
  }

  function getAllProductsOld()
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $endpoint = 'products';
    $page = 1;
    $perPage = 80;
    $allProducts = array();

    do {
      $response = $this->woocommerce->get($endpoint, ['per_page' => $perPage, 'page' => $page, 'fields' => 'id, sku, name, stock_quantity, price, regular_price, sale_price, permalink, images, virtual']);

      if (empty($response)) {
        break;
      }

      $allProducts = array_merge($allProducts, $response);

      $page++;
    } while (count($response) === $perPage);

    $formattedProducts = array_map(function ($product) {
      $formattedProduct = [
        'cod' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'stock_quantity' => $product->stock_quantity,
        'price' => $product->price,
        'regular_price' => $product->regular_price,
        'sale_price' => $product->sale_price,
        'permalink' => $product->permalink,
        'virtual' => $product->virtual,
        'attributes' => $product->attributes,
        'categories' => $product->categories
      ];

      return $formattedProduct;
    }, $allProducts);

    return json_encode($formattedProducts);
  }

  public function createProduct($name, $sku, $regular_price, $stock_quantity, $categories, $sizes)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    // Verificar name
    if (trim(strlen($name)) === 0) {
      $name = 'Asigne un nombre';
    }

    // Verificar price
    $regular_price = str_replace(',', '.', $regular_price);
    if (!is_numeric($regular_price)) {
      $regular_price = 0.0;
    }

    // Verificar Cantidad
    if (is_numeric($stock_quantity)) {
      $stock_quantity = intval($stock_quantity);
    } else {
      $stock_quantity = 0;
    }

    // PROCESAR LAS CATEGORIAS
    $myCategories = array();

    $arrayCat = explode(',', $categories);
    if (count($arrayCat) === 0) {
      $myCategories[] = ['id' => $categories];
    } else {
      foreach ($arrayCat as $key => $value) {
        $myCategories[] = ['id' => $value];
      }
    }

    // PROCESAR LAS TALLAS
    $mySizes = array();

    $arraySiz = explode(',', $sizes);
    if (count($arraySiz) === 0) {
      $mySizes[] = [
        'id' => 2,
        'name' => 'Talla',
        'visible' => true,
        'options' => $sizes,
      ];
    } else {
      foreach ($arraySiz as $key => $value) {
        $tmp[] = $value;
      }

      $mySizes[] = [
        'id' => 2,
        'name' => 'Talla',
        'visible' => true,
        'options' => $tmp,
      ];
    }

    // Crear vector de datos delprodcuto
    $data = [
      'name' => $name,
      'sku' => $sku,
      'stock_quantity' => $stock_quantity,
      'manage_stock' => true,
      'regular_price' => $regular_price,
      'type' => 'simple',
      'description' => '',
      'short_description' => '',
      'categories' => $myCategories,
      'attributes' => $mySizes,
    ];

    return json_encode($this->woocommerce->post('products', $data));
  }

  public function createProductLite($name, $pricesDat, $category, $sku)
  {
    // CREAR EL NUEVO PRODUCTO
    $sql = "INSERT INTO `products`(
            `product`,
            `sku`,
            `category_ids`
        )
        VALUES(
            '" . $name . "',
            '" . $sku . "',
            '" . $category . "'
        );";
    $localConnection = new LocalDB();
    $localConnection->goQuery($sql);

    $sql = 'SELECT MAX(_id) id from products';
    $requestNewProduct['product'] = $localConnection->goQuery($sql);

    $newID = $requestNewProduct['product'][0]['id'];

    // ASIGNAR PRECIOS
    $prices = json_decode($pricesDat, true);
    if (!empty($prices)) {
      $sql = 'INSERT INTO products_prices (id_product, price, `descripcion`) VALUES ';
      $values = [];
      foreach ($prices as $price) {
        $values[] = "($newID, {$price['price']}, '{$price['descripcion']}')";
      }
      $sql .= implode(', ', $values) . ';';

      // Ejecutar la consulta
      $localConnection->goQuery($sql);
    } else {
      $sql = 'NO HAY PRECIOS PARA PROCESAR';
    }

    $sqlCreate = $sql;

    $sql = 'SELECT * FROM products WHERE _id = ' . $newID;
    $resp['product'] = $localConnection->goQuery($sql);

    $sql = 'SELECT * FROM products_prices WHERE id_product = ' . $newID;
    $resp['prices'] = $localConnection->goQuery($sql);

    // Desconectar
    $localConnection->disconnect();

    return json_encode($sqlCreate);
  }

  /* public function createProductLite($name, $prices, $category, $sku, $stock_quantity)
  {
      // CREAR EL NUEVO PRODUCTO
      $sql = "INSERT INTO `products`(
          `product`,
          `sku`,
          `category_ids`
      )
      VALUES(
          '" . $name . "',
          '" . $sku . "',
          '" . $category . "'
      )";

      $sql .= "SELECT MAX(_id) from products";
      $localConnection = new LocalDB();

      $requestNewProduct['product'] = $localConnection->goQuery($sql);

      $newID = $requestNewProduct[0]['_id'];

      // ASIGNAR PRECIOS
      if (!empty($prices)) {
          $count = count($prices);
          $sql = '';
          for ($i = 0; $i <= $count; $i++) {
              $sql .= "INSERT INTO product_prices (id_product, price, `description`) VALUES ($count, $prices[$i]['price'], '$prices[0]['descripcion']');";            }
          }
          $requestNewProduct['prices'] = $localConnection->goQuery($sql);
      }
      $localConnection->disconnect();

      return json_encode($data);
  } */

  // Actualizar productos
  public function updateProduct($id, $name, $regular_price, $stock_quantity, $sku, $category)
  {
    $sql = 'SELECT _id id, nombre `name` FROM categories';

    $localConnection = new LocalDB();
    $response = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($response);
  }

  public function updateProduct_wc($id, $name, $regular_price, $stock_quantity, $sku, $category)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);

    $categories_array[] = array('id' => (int) $category);

    $data = [
      'name' => $name,
      'regular_price' => $regular_price,
      'stock_quantity' => $stock_quantity,
      'sku' => $sku,
      'manage_stock' => true,
      'stock_status' => 'instock',
      'categories' => $categories_array,
    ];

    return json_encode($this->woocommerce->put('products/' . $id, $data));
  }

  public function updateProductQuantity($id, $stock_quantity)
  {
    $sql = 'UPDATE
            `products`
        SET   
            `stock_quantity` = ' . $stock_quantity . '
        WHERE
            _id = ' . $id;

    $localConnection = new LocalDB();
    $response = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($response);
  }

  public function updateProductQuantity_wc($id, $stock_quantity)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $data = [
      'stock_quantity' => $stock_quantity,
      'manage_stock' => true,
      'stock_status' => 'instock',
    ];

    return json_encode($this->woocommerce->put('products/' . $id, $data));
  }

  // Actualizar Stock (Cantidad de prodcuto)
  public function updateProductStock($id, $stock_quantity)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $data = [
      'stock_quantity' => $stock_quantity,
      'manage_stock' => true,
    ];
    return json_encode($this->woocommerce->put('products/' . $id, $data));
  }

  public function updateProductComision($product_id, $attribute_options)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $url = 'products/' . $product_id;
    $existing_attributes = $this->woocommerce->get($url);

    if (empty($existing_attributes->attributes)) {
      // Guardar nuevos atributos
      $data = [
        'attributes' => [
          [
            'id' => 2,
            'name' => 'Comisión',
            'slug' => 'comision',
            'position' => 2,
            'visible' => true,
            'options' => [$attribute_options],
            'variation' => false,
          ]
        ]
      ];
      $res = $this->woocommerce->put($url, $data);
    } else {
      // Eliminar atributos anteriores
      $data = [
        'attributes' => []
      ];
      $this->woocommerce->put($url, $data);

      // Guardar nuevos atributos
      $data = [
        'attributes' => [
          [
            'id' => 2,
            'name' => 'Comisión',
            'position' => 2,
            'visible' => true,
            'variation' => true,
            'options' => [$attribute_options],
          ]
        ],
      ];
      $res = $this->woocommerce->put($url, $data);
    }
    return json_encode($res);
  }

  // Eliminar Producto
  public function deleteProduct($id)
  {
    $sql = 'DELETE FROM products WHERE -id = ' . $id;

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  // MANEJO DE ATRIBUTOS PARA CORTE
  // BUSCAR PRODUCTOS PARA ASIGNAR SU ATRIBUTO DE COMISIONES
  /* function getProductsAttr()
  {
      $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
      $endpoint = 'products';
      $page = 1;
      $perPage = 100;
      $allProducts = array();

      do {
          $response = $this->woocommerce->get($endpoint, ['per_page' => $perPage, 'page' => $page, 'fields' => 'id, sku, name, stock_quantity, price, regular_price, sale_price, permalink, images, virtual']);

          if (empty($response)) {
              break;
          }

          $allProducts = array_merge($allProducts, $response);

          $page++;
      } while (count($response) === $perPage);

      $formattedProducts = array_map(function ($product) {
          $formattedProduct = [
              'cod' => $product->id,
              'name' => $product->name,
              'permalink' => $product->permalink,
              'virtual' => $product->virtual,
              'attributes' => $product->attributes,
              'categories' => $product->categories
          ];

          return $formattedProduct;
      }, $allProducts);

      return json_encode($formattedProducts);
  } */

  /** FIN PRODUCTOS */

  /**
   * INICIO CLIENTES
   */
  public function getCustomerById($id)
  {
    $sql = 'SELECT * FROM customers WHERE _id = ' . $id . ';';

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  public function getCustomerByIdWP($id)
  {
    $sql = 'SELECT 
        _id id, 
        first_name billing_first_name, 
        last_name billing_last_name, 
        cedula billing_postcode, 
        phone billing_phone, 
        phone, 
        null billing_city, 
        null billing_state, 
        `address` billing_address_1, 
        email billing_email 
        FROM customers WHERE _id = ' . $id;

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // return json_encode($data);
    return $data;

    /**
     * ANTERIOR
     */

    /* $sql = "SELECT
    u.user_login AS username,
    um.meta_value AS billing_first_name,
    um2.meta_value AS billing_last_name,
    um3.meta_value AS billing_email,
    um4.meta_value AS billing_phone,
    um5.meta_value AS billing_address_1,
    um6.meta_value AS billing_city,
    um7.meta_value AS billing_state,
    um8.meta_value AS billing_postcode
    FROM
    wp_users u
    INNER JOIN wp_usermeta um ON u.ID = um.user_id AND um.meta_key = 'billing_first_name'
    INNER JOIN wp_usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'billing_last_name'
    INNER JOIN wp_usermeta um3 ON u.ID = um3.user_id AND um3.meta_key = 'billing_email'
    INNER JOIN wp_usermeta um4 ON u.ID = um4.user_id AND um4.meta_key = 'billing_phone'
    INNER JOIN wp_usermeta um5 ON u.ID = um5.user_id AND um5.meta_key = 'billing_address_1'
    INNER JOIN wp_usermeta um6 ON u.ID = um6.user_id AND um6.meta_key = 'billing_city'
    INNER JOIN wp_usermeta um7 ON u.ID = um7.user_id AND um7.meta_key = 'billing_state'
    INNER JOIN wp_usermeta um8 ON u.ID = um8.user_id AND um8.meta_key = 'billing_postcode'
    WHERE u.ID = " . $id . "
    ";
    $this->connectToWordpressDB();

    $response = array();
    try {
        $res = $this->pdo->prepare($sql);
        $res->execute();

        $data = $res->fetchAll(PDO::FETCH_ASSOC);
        $response = $data;
    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }

    return $response; */
  }

  public function getAllCustomesrs()
  {
    $sql = 'SELECT _id id, first_name, last_name, username, cedula, phone, address, email FROM customers';

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  public function createCustomerNeneteen()
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    // Crear vector de datos del cliente
    $data = [
      'email' => 'nineteengreen@gmail.com',
      'first_name' => 'Nineteen',
      'last_name' => 'Sport',
      'username' => 'nineteensport',
      'billing' => [
        'first_name' => 'Nineteen',
        'last_name' => 'Sport',
        'company' => 'Nineteengreen',
        'address_1' => '13 entre calles 1 y 3 local 1-54, El Vigía',
        'address_2' => '',
        'city' => 'El Vigía',
        'state' => 'MRD',
        'postcode' => '5145',
        'country' => 'VE',
        'email' => 'nineteengreen@gmail.com',
        'phone' => '0414-0326592',
      ],
      'shipping' => [
        'first_name' => 'Nineteen',
        'last_name' => 'Sport',
        'company' => '',
        'address_1' => 'Venezuela',
        'address_2' => '',
        'city' => 'El Vigía',
        'state' => 'MRD',
        'postcode' => '5145',
        'country' => 'VE',
      ],
      'meta_data' => [
        [
          'key' => 'sales_commission',
          'value' => false,
        ],
      ],
    ];
    return json_encode($this->woocommerce->post('customers/', $data));
  }

  public function updateCustomerNine($customerID)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    // Crear vector de datos para actualizar el cliente
    $data = [
      'meta_data' => [
        [
          'key' => 'sales_commission',
          'value' => 'false',
        ],
      ],
    ];

    // Realizar la solicitud de actualización del cliente
    $response = $this->woocommerce->put('customers/' . $customerID, $data);

    // Verificar si la solicitud fue exitosa y devolver la respuesta
    if ($response && !isset($response->code)) {
      return json_encode($response);
    } else {
      return json_encode($response);
    }
  }

  public function getCustomerByEmail($email)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $endpoint = 'customers';
    $response = $this->woocommerce->get($endpoint, ['email' => $email]);

    if (!empty($response)) {
      return $response[0];  // Devuelve el primer cliente encontrado
    } else {
      return null;  // No se encontró ningún cliente con el correo electrónico dado
    }
  }

  public function createCustomer($first_name, $last_name, $cedula, $phone, $email, $address)
  {
    $localConnection = new LocalDB();

    $sql = "SELECT _id FROM customers WHERE phone = '" . trim($phone) . "';";
    $exist = $localConnection->goQuery($sql);
    $myCount = count($exist);

    if ($myCount === 0) {
      $bytes = random_bytes(6);
      $token = bin2hex($bytes);

      $username = $first_name . $token;

      if ($email === 'none') {
        $email = 'none_' . $token . '@email.com';
      }

      if ($phone === 'none') {
        $phone = null;
      }

      if ($address === 'none') {
        $address = null;
      }

      $sql = "INSERT INTO `customers`(    
                `first_name`,
                `last_name`,
                `username`,
                `cedula`,
                `address`,
                `phone`,
                `email`
            )
            VALUES(
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $username . "',
                '" . $cedula . "',
                '" . $address . "',
                '" . $phone . "',
                '" . $email . "'
                )";
      $response['resp_insert'] = $localConnection->goQuery($sql);
    } else {
      $obj['msg'] = 'El cliente ya existe';
      $response = $obj;
    }

    $localConnection->disconnect();
    return json_encode($response);
  }

  public function updateCustomer($id, $first_name, $last_name, $cedula, $phone, $email, $address)
  {
    $bytes = random_bytes(6);
    $token = bin2hex($bytes);
    $localConnection = new LocalDB();

    // Verificar si el cliente existe
    $sql = 'SELECT count(_id) existe FROM customers WHERE _id = ' . $id . ';';
    $result = $localConnection->goQuery($sql);

    // Si el cliente existe, actualiza la información
    if ($result[0]['existe'] > 0) {
      // VERIFICAR QUE TENGAMOS UN EMAIL VALIDO
      if ($email === 'none') {
        $email = 'none_' . $token . '@email.com';
      }
      $sql = "UPDATE customers SET 
                        first_name = '" . $first_name . "', 
                        last_name = '" . $last_name . "', 
                        cedula = '" . $cedula . "', 
                        phone = '" . $phone . "', 
                        email = '" . $email . "', 
                        address = '" . $address . "' 
                    WHERE _id = " . $id . ';';
    }
    // Si el cliente no existe, inserta un nuevo registro
    else {
      $username = $first_name . $token;
      $sql = 'INSERT INTO customers 
                (_id, username, first_name, last_name, cedula, phone, email, address) 
            VALUES 
                (' . $id . ", '" . $username . "', '" . $first_name . "', '" . $last_name . "', '" . $cedula . "', '" . $phone . "', '" . $email . "', '" . $address . "');";
    }
    $sql .= 'SELECT * FROM customers WHERE _id = ' . $id;

    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  public function deleteCustomer($id)
  {
    $sql = 'DELETE FROM customers WHERE -id = ' . $id;

    $localConnection = new LocalDB();
    $response = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($response);
  }

  /** FIN CLIENTES */

  /**
   * CATEGORIAS
   */
  public function getAllCategories()
  {
    $sql = 'SELECT _id id, nombre `name` FROM categories';

    $localConnection = new LocalDB();
    $data = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($data);
  }

  public function getAllCategories_WC()
  {
    $sql = "SELECT *
            FROM wp_terms AS t
            JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'product_cat';
        ";

    $this->connectToWordpressDB();
    $mat = array();
    try {
      $res = $this->pdo->prepare($sql);
      $res->execute();
      $data = $res->fetchAll(PDO::FETCH_ASSOC);
      $mat = $data;
    } catch (PDOException $e) {
      $mat['status'] = 'error';
      $mat['message'] = $e->getMessage();
    }
    /* $key = 0;
    foreach ($mat as $customer) {
        $customers[$key]["id"] = intval($customer['id']);
        $customers[$key]["first_name"] = $customer['first_name'];
        $customers[$key]["last_name"] = $customer['last_name'];
        if (isset($customer['role'])) {
            $customers[$key]["role"] = unserialize($customer['role']);
        } else {
            $customers[$key]["role"] = [];
        }
        $customers[$key]["username"] = $customer['username'];
        $customers[$key]["cedula"] = $customer['cedula'];
        $customers[$key]["address"] = $customer['address'];
        $customers[$key]["phone"] = $customer['phone'];
        $customers[$key]["email"] = $customer['email'];
        $customers[$key]["sales_commission"] = $customer['sales_commission'];

        $key++;
    } */
    // return json_encode($customers);
    return json_encode($mat);
  }

  public function getCategoryById($id_category)
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $endpoint = 'products/categories/' . $id_category;
    $response = $this->woocommerce->get($endpoint);

    return json_encode($response);
  }

  /** FIN CATEGORIAS */

  /**
   * ATRIBUTOS
   */
  public function getAllAttributes()
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $endpoint = 'products/attributes/';
    $response = $this->woocommerce->get($endpoint);

    return json_encode($response);
  }

  /** FIN ATRIBUTOS */

  /**
   * TALLAS
   */
  public function getSizes()
  {
    $sql = 'SELECT _id, nombre `name` FROM sizes';
    $localConnection = new LocalDB();
    $sizes = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    return json_encode($sizes);
  }

  /** FIN TALLAS */

  /**
   * METODOS DE PAGO
   */
  public function getPG()
  {
    $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    $endpoint = 'payment_gateways/';
    $response = $this->woocommerce->get($endpoint);

    return $response;
  }

  /**
   * FIN METODOS DE PAGO
   */
}
