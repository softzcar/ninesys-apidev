<?php

/**
 * llamdas a Woocomemrce
 */

use Automattic\WooCommerce\Client;

class WooMe
{ // HOSTINGER
    private $url = "https://nineteengreen.com";
    private $ck = "ck_28dbfd8beb92a9454faf59c2b6da225dee5ad60a";
    private $cs = "cs_dab1e683eff87b602d8b5ba293c57d8faf260de2";

    // private $url = "https://nineteengreen.com";
    // private $ck  = "ck_74ccced3749e1ff1a99564098176048ce550a9e5";
    // private $cs  = "cs_c055e8ad55de8f6e9339aebb98f7ec76fcef8690";

    // CLAVES DE dev.nineteengreen.com
    // private $url = "https://dev.nineteengreen.com";
    // private $ck  = "ck_3142398030de80ac909743b6b2c81cec2a23ab62";
    // private $cs  = "cs_8341cbae22ebbf4ecfe86fac9d2ff6db54042c97";
    private $opt = ['version' => 'wc/v3'];
    private $woocommerce;

    public function __construct()
    {
        $this->woocommerce = new Client($this->url, $this->ck, $this->cs, $this->opt);
    }

    /**
     * INICIO PRODUCTOS
     */
    public function getProductById($id)
    {
        // $woocommerce  = new Client($this->url, $this->ck, $this->cs, $this->opt);
        $endpoint = 'products/' . $id;
        $response = $this->woocommerce->get($endpoint);

        return $response;
    }

    /* function getAllProducts()
    {
    $response = $this->woocommerce->get('products', ['_fields' => 'id,sku,name,stock_quantity,price,regular_price,sale_price,premalink,images,virtual']);
    return json_encode($response);
    } */


    function getAllProducts()
    {
        $endpoint = 'products';
        $page = 1;
        $perPage = 20;
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
                'images' => !empty($product->images) ? $product->images[0]->src : 'https://cdn.nineteengreen.com/images/no-image.png',
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
        // Verificar name
        if (trim(strlen($name)) === 0) {
            $name = "Asigne un nombre";
        }

        // Verificar price
        $regular_price = str_replace(",", ".", $regular_price);
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

        $arrayCat = explode(",", $categories);
        if (count($arrayCat) === 0) {
            $myCategories[] = ['id' => $categories];
        } else {
            foreach ($arrayCat as $key => $value) {
                $myCategories[] = ['id' => $value];
            }
        }

        // PROCESAR LAS TALLAS
        $mySizes = array();

        $arraySiz = explode(",", $sizes);
        if (count($arraySiz) === 0) {
            $mySizes[] = [
                'id' => 2,
                'name' => "Talla",
                'visible' => true,
                'options' => $sizes,
            ];
        } else {
            foreach ($arraySiz as $key => $value) {
                $tmp[] = $value;
            }

            $mySizes[] = [
                'id' => 2,
                'name' => "Talla",
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
            'images' => [
                [
                    'src' => 'https://cdn.nineteengreen.com/images/no-image.png',
                ],
            ],
        ];

        return json_encode($this->woocommerce->post('products', $data));
    }

    public function createProductLite($name, $regular_price, $categories)
    {
        // return "<br>name: " . $name . "price: " . $regular_price;

        // Verificar name
        if (trim(strlen($name)) === 0) {
            $name = "Asigne un nombre";
        }

        // Verificar price
        $regular_price = str_replace(",", ".", $regular_price);
        if (!is_numeric($regular_price)) {
            $regular_price = 0.0;
        }

        // Crear array de categorías, recibimos las categorias separadas con comas
        // Separar los IDs de categoría en un arreglo
        $category_ids = explode(',', $categories);

        // Construir el arreglo de categorías
        $categories_array = array();
        foreach ($category_ids as $category_id) {
            $categories_array[] = array('id' => (int) $category_id);
        }

        foreach ($category_ids as $id) {
            $myCategories[] = ['id' => $id];

            /*            $arrayCategories[] = array(
            'id' => (int)$id
            ); */
        }

        // Crear vector de datos delprodcuto
        $data = [
            'name' => $name,
            'sku' => "",
            'regular_price' => $regular_price,
            'stock_quantity' => 0,
            'categories' => $categories_array,
            'images' => [
                [
                    'src' => 'https://cdn.nineteengreen.com/images/no-image.png',
                ],
            ],
        ];
        return json_encode($this->woocommerce->post('products', $data));
    }

    // Actualizar productos
    public function updateProduct($id, $name, $regular_price, $stock_quantity)
    {
        $data = [
            'name' => $name,
            'stock_quantity' => $stock_quantity,
            'manage_stock' => true,
            'regular_price' => $regular_price,
        ];

        return json_encode($this->woocommerce->put('products/' . $id, $data));
    }


    // Actualizar Stock (Cantidad de prodcuto)
    public function updateProductStock($id, $stock_quantity)
    {
        $data = [
            'stock_quantity' => $stock_quantity,
            'manage_stock' => true,
        ];
        return json_encode($this->woocommerce->put('products/' . $id, $data));
    }

    public function updateProductComision($product_id, $attribute_options)
    {
        $url = "products/" . $product_id;
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
        return json_encode($this->woocommerce->delete('products/' . $id, ['force' => true]));
    }

    // MANEJO DE ATRIBUTOS PARA CORTE
    # BUSCAR PRODUCTOS PARA ASIGNAR SU ATRIBUTO DE COMISIONES
    function getProductsAttr()
    {
        $endpoint = 'products';
        $page = 1;
        $perPage = 20;
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
                'images' => !empty($product->images) ? $product->images[0]->src : 'https://cdn.nineteengreen.com/images/no-image.png',
                'virtual' => $product->virtual,
                'attributes' => $product->attributes,
                'categories' => $product->categories
            ];

            return $formattedProduct;
        }, $allProducts);

        return json_encode($formattedProducts);
    }

    /**
     * FIN PRODUCTOS
     */

    /**
     * INICIO CLIENTES
     */
    public function getCustomerById($id)
    {
        // $woocommerce  = new Client($this->url, $this->ck, $this->cs, $this->opt);
        $endpoint = 'customers/' . $id;
        $response = $this->woocommerce->get($endpoint);

        return $response;
    }

    public function getAllCustomesrs()
    {
        $response = $this->woocommerce->get('customers', ['_fields' => 'id,first_name,last_name,role,username,postcode,address_1,phone,email']);

        $page = 1;
        $perPage = 100;
        $customers = [];

        do {
            $response = $this->woocommerce->get('customers', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($response as $customer) {
                $obj = [
                    'id' => $customer->id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'role' => $customer->role,
                    'username' => $customer->username,
                    'cedula' => $customer->billing->postcode,
                    'address' => $customer->billing->address_1,
                    'phone' => $customer->billing->phone,
                    'email' => $customer->email,
                ];

                $customers[] = $obj;
            }

            $page++;
        } while (count($response) === $perPage);

        return json_encode($customers);
    }


    /* public function getAllCustomesrs()
    {
    $ban = true;
    $page     = 1;
    $endpoint = 'customers?per_page=100&page=';
    $resp     = array();
    while ($ban) {
    $tmp = $this->woocommerce->get($endpoint . $page);
    if (!empty($tmp)) {
    for ($i = 0; $i < count($tmp); $i++) {
    $obj["id"]         = $tmp[$i]->id;
    $obj["first_name"] = $tmp[$i]->first_name;
    $obj["last_name"]  = $tmp[$i]->last_name;
    $obj["role"]       = $tmp[$i]->role;
    $obj["username"]   = $tmp[$i]->username;
    $obj["cedula"]     = $tmp[$i]->billing->postcode;
    $obj["address"]    = $tmp[$i]->billing->address_1;
    $obj["phone"]      = $tmp[$i]->billing->phone;
    $obj["email"]      = $tmp[$i]->email;
    $resp[] = $obj;
    }
    $page++;
    } else {
    $ban = false;
    }
    }
    return json_encode($resp);
    } */

    public function createCustomer($first_name, $last_name, $cedula, $phone, $email, $address)
    {
        // Generar username alearotio
        $bytes = random_bytes(6);
        $token = bin2hex($bytes);
        $username = $first_name . $token;

        // Verificar email
        if ($email === "none") {
            $email = "none_" . $token . "@email.com";
        }

        if ($phone === "none") {
            $phone = "";
        }

        if ($address === "none") {
            $address = "";
        }

        // Generar username alearotio
        $bytes = random_bytes(6);
        $token = bin2hex($bytes);
        $username = $first_name . $token;

        // Crear vector de datos del cliente
        $data = [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username,
            'billing' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'company' => '',
                'address_1' => $address,
                'address_2' => '',
                'city' => 'El Vigía',
                'state' => 'MRD',
                'postcode' => $cedula,
                'country' => 'VE',
                'email' => $email,
                'phone' => $phone,
            ],
            'shipping' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'company' => '',
                'address_1' => 'Venezuela',
                'address_2' => '',
                'city' => 'El Vigía',
                'state' => 'MRD',
                'postcode' => $cedula,
                'country' => 'VE',
            ],
        ];
        // return json_encode($data);
        return json_encode($this->woocommerce->post('customers/', $data));
    }

    public function updateCustomer($id, $first_name, $last_name, $cedula, $phone, $email, $address)
    {
        // Comprobar si el cliente existe
        $customer = $this->woocommerce->get('customers/' . $id);

        if (empty($customer)) {
            // Si el cliente no existe, crearlo
            return $this->createCustomer($first_name, $last_name, $cedula, $phone, $email, $address);
        } else {
            // VERIFICAR QUE TENGAMOS UN EMAIL VALIDO
            $bytes = random_bytes(6);
            $token = bin2hex($bytes);

            // Verificar email
            if ($email === "none") {
                $email = "none_" . $token . "@email.com";
            }

            // Si el cliente existe, actualizar sus datos
            $data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'billing' => [
                    'postcode' => $cedula,
                    'phone' => $phone,
                    'address_1' => $address,
                ],
                'email' => $email,
            ];

            return json_encode($this->woocommerce->put('customers/' . $id, $data));
        }
    }



    /* public function updateCustomer_old($id, $first_name, $last_name, $cedula, $phone, $email, $address)
    {
    $data = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'billing' => [
    'postcode' => $cedula,
    'phone' => $phone,
    'address_1' => $address,
    ],
    'email' => $email,
    ];
    return json_encode($this->woocommerce->put('customers/' . $id, $data));
    } */
    /**
     * FIN CLIENTES
     */

    /**
     * CATEGORIAS
     */
    public function getAllCategories()
    {
        $endpoint = 'products/categories/?per_page=100';
        $response = $this->woocommerce->get($endpoint);

        return json_encode($response);
    }

    public function getCategoryById($id_category)
    {
        $endpoint = 'products/categories/' . $id_category;
        $response = $this->woocommerce->get($endpoint);

        return json_encode($response);
    }
    /**
     * FIN CATEGORIAS
     */

    /**
     * ATRIBUTOS
     */
    public function getAllAttributes()
    {
        $endpoint = 'products/attributes/';
        $response = $this->woocommerce->get($endpoint);

        return json_encode($response);
    }
    /**
     * FIN ATRIBUTOS
     */

    /**
     * TALLAS
     */
    public function getSizes()
    {
        $endpoint = 'products/attributes/1/terms?per_page=40';
        $response = $this->woocommerce->get($endpoint);

        return json_encode($response);
    }
    /**
     * FIN TALLAS
     */

    /**
     * METODOS DE PAGO
     */
    public function getPG()
    {
        $endpoint = 'payment_gateways/';
        $response = $this->woocommerce->get($endpoint);

        return $response;
    }
/**
 * FIN METODOS DE PAGO
 */
}