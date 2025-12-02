<?php
declare(strict_types=1);
// ini_set('implicit_flush', 1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

date_default_timezone_set('America/Caracas');

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {

        // CORS Pre-Flight OPTIONS Request Handler

        return $response;
    });

    // ROOT
    $app->get('/', function (Request $request, Response $response) {

        $array["api"] = "ninesys TESTS";

        $array["version"] = "3.33.1";

        $array["description"] = "Api ninesys";

        $response->getBody()->write(json_encode($array));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /* SSE */
    $app->get('/sse', function (Request $request, Response $response) {
        // Establecer los encabezados SSE
        // $sql = "SELECT _id FROM ordenes WHERE STATUS = 'cancelada'";
        $sql = "SELECT _id, username, acceso, email FROM empleados WHERE acceso = 1";
        $obj[0]["sql"] = $sql;
        $obj[0]["name"] = "data";

        $sql = "SELECT _id, metodo_pago FROM metodos_de_pago";
        $obj[1]["sql"] = $sql;
        $obj[1]["name"] = "metodos";

        $sse = new SSE($obj);
        $events = $sse->SsePrint();
        $response->getBody()->write(json_encode($events));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });


    /* FIN SSE */


    /** * Login */

    $app->post("/login", function (Request $request, Response $response, $args) {

        $datosAcceso = $request->getParsedBody();



        /* if ($datosAcceso['type'] == 'supervisor') {
        $sql = "SELECT nombre, departamento, acceso, email, _id FROM usuarios WHERE nombre = '" . $datosAcceso['username'] . "' AND Password = '" . $datosAcceso['password'] . "'";
        } else {
        $sql = "SELECT username FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND Password = '" . $datosAcceso['password'] . "'";
        } */



        $sql = "SELECT _id, username, departamento, nombre, email, comision, acceso FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND password = '" . $datosAcceso['password'] . "'";

        $localConnection = new LocalDB($sql);



        $data = $localConnection->goQuery();



        if (empty($data)) {

            $data[0] = false;

            $access = false;
        } else {

            $access = true;
        }

        $object['data']['access'] = $access;



        $object['data']['res'] = $data[0];

        if ($access) {

            $object['data']['id_empleado'] = $data[0]['_id'];
            $object['data']['departamento'] = $data[0]['departamento'];
            $object['data']['nombre'] = $data[0]['nombre'];
            $object['data']['username'] = $data[0]['username'];
            $object['data']['email'] = $data[0]['email'];
            $object['data']['comision'] = $data[0]['comision'];
            $object['data']['acceso'] = $data[0]['acceso'];
        } else {

            $object['data']['id_empleado'] = null;
            $object['data']['departamento'] = null;
            $object['data']['nombre'] = null;
            $object['data']['username'] = null;
            $object['data']['email'] = null;
            $object['data']['comision'] = 0;
            $object['data']['acceso'] = 0;
        }

        // TODO ELIMINAR ESTE TIPO DE ACCESO ES DE LA VERSION 1

        $object['dat'] = $object['data']['res'];

        // $response->getBody()->write(json_encode($datosAcceso));

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * NIN LOGIN
     */



    /** * GENERAL */

    $app->get('/next-id-order', function (Request $request, Response $response) {

        $sql = "SELECT MAX(_id) + 1 id FROM ordenes";



        $localConnection = new LocalDB($sql);

        $data = $localConnection->goQuery();



        if (!$data[0]["id"]) {

            $data[0]["id"] = "1";
        }

        $input = str_pad($data[0]["id"], 3, "0", STR_PAD_LEFT);

        // $input = '33';

        // $nextId["crudo"] =  $data[0]["id"];

        $nextId["id"] = str_pad($input, 3, "0", STR_PAD_LEFT);



        $response->getBody()->write(json_encode($nextId));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * FIN GENRAL
     */

    /** * TABLAS */



    // ORDENES ACTIVAS

    $app->get('/table/ordenes-activas/{id_empleado}', function (Request $request, Response $response, array $args) {

        $sql = "SELECT departamento FROM empleados WHERE _id = " . $args["id_empleado"];
        $localConnection = new LocalDB($sql);
        $departamento = $localConnection->goQuery()[0]["departamento"];

        if ($departamento === "Administración") {
            $sql = "SELECT 
        responsable,
        _id orden, 
        _id id_father, 
        _id acc, 
        cliente_nombre, 
        fecha_inicio, 
        fecha_entrega, 
        observaciones obs, 
        status estatus 
        FROM ordenes 
        WHERE 
            (status = 'activa' 
            OR status = 'En espera' 
            OR status = 'terminada' 
            OR status = 'pausada')
        ORDER BY _id DESC
        ";
        } else {
            $sql = "SELECT 
            responsable,
            _id orden, 
            _id id_father, 
            _id acc, 
            cliente_nombre, 
            fecha_inicio, 
            fecha_entrega, 
            observaciones obs, 
            status estatus 
            FROM ordenes 
            WHERE 
            responsable = '" . $args["id_empleado"] . "'  
            AND (status = 'activa' 
            OR status = 'En espera' 
            OR status = 'terminada' 
            OR status = 'pausada')
            AND pago_comision = 'pendiente'
            ORDER BY _id DESC
            ";
        }


        $object["sql"] = $sql;

        // Cabeceras de la tabla

        $object['fields'][0]['key'] = "orden";

        $object['fields'][0]['label'] = "Orden";

        $object['fields'][0]['sortable'] = true;



        $object['fields'][1]['key'] = "estatus";

        $object['fields'][1]['label'] = "Estatus";

        $object['fields'][1]['sortable'] = true;



        $object['fields'][2]['key'] = "fecha_inicio";

        $object['fields'][2]['label'] = "Inicio";

        $object['fields'][2]['sortable'] = true;



        $object['fields'][3]['key'] = "fecha_entrega";

        $object['fields'][3]['label'] = "Entrega";

        $object['fields'][3]['sortable'] = true;



        $object['fields'][4]['key'] = "cliente_nombre";

        $object['fields'][4]['label'] = "Cliente";

        $object['fields'][4]['sortable'] = true;


        $object['fields'][5]['key'] = "id_father";

        $object['fields'][5]['label'] = "Vinculadas";

        $object['fields'][5]['sortable'] = false;


        $object['fields'][6]['key'] = "acc";

        $object['fields'][6]['label'] = "Acciones";

        $object['fields'][6]['sortable'] = false;




        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * FIN TABLAS
     */

    /** * TELAS */

    $app->get('/telas', function (Request $request, Response $response) {

        $sql = "SELECT * FROM catalogo_telas ORDER BY tela";

        // $object['sql']         = $sql;

        $localConnection = new LocalDB($sql);

        $object['data'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /**
     * FIN TELAS
     */



    /**
     * RETIROS
     */



    // Datos para efectuar el cietre de caja

    $app->get('/cierre-de-caja', function (Request $request, Response $response, array $args) {



        /** FONDO */

        $sql = "SELECT dolares, pesos, bolivares FROM caja_fondos ORDER BY _id DESC LIMIT 1";

        $localConnection = new LocalDB($sql);

        $fondo = $localConnection->goQuery();

        $object['data']['fondo'] = $fondo;



        if (empty($fondo)) {

            $fondo[0]["dolares"] = 0;

            $fondo[0]["pesos"] = 0;

            $fondo[0]["bolivares"] = 0;
        }



        // DÓLARES EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["dolares"] . ") monto, moneda, tasa, FORMAT(((SUM(monto) / tasa)) + " . $fondo[0]["dolares"] . ", 'C2') dolares FROM caja WHERE moneda= 'Dólares'";

        $localConnection = new LocalDB($sql);

        $object["data"]["caja"] = $localConnection->goQuery();



        // PESOS EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["pesos"] . ") monto, moneda, tasa, FORMAT((SUM(monto) + " . $fondo[0]["pesos"] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Pesos'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["caja"], $localConnection->goQuery()[0]);



        // BOLIVARES     EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["bolivares"] . ") monto, moneda, tasa, FORMAT((SUM(monto) + " . $fondo[0]["bolivares"] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Bolívares'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["caja"], $localConnection->goQuery()[0]);



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Guardar Cierre de caja

    $app->post("/cierre-de-caja", function (Request $request, Response $response, $args) {

        $datosCierre = $request->getParsedBody();



        // Guardamos el cierre

        $sql = " INSERT INTO caja_cierres (dolares, pesos, bolivares, id_empleado) VALUES (" . $datosCierre["cierreDolaresEfectivo"] . ", " . $datosCierre["cierrePesosEfectivo"] . ", " . $datosCierre["cierreBolivaresEfectivo"] . ", " . $datosCierre["id_empleado"] . ");";

        $sql .= "TRUNCATE caja;";

        $sql .= "TRUNCATE caja_fondos;";

        $sql .= "INSERT INTO caja_fondos (dolares, pesos, bolivares) VALUES (" . $datosCierre["fondoDolares"] . ", " . $datosCierre["fondoPesos"] . ", " . $datosCierre["fondoBolivares"] . ")";

        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);



        $object["response"] = $localConnection->goQuery();



        $response->getBody()->write(json_encode(str_replace("\r", "", $object)));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // REporte de caja

    $app->get('/reporte-de-caja/{inicio}/{fin}', function (Request $request, Response $response, array $args) {



        /** EFECTIVO */

        // Dolares

        $sql = "SELECT SUM(`monto`) monto, 'Dólares' moneda, `tasa`, `metodo_pago` tipo, SUM(`monto`) dolares FROM `metodos_de_pago` WHERE metodo_pago = 'Efectivo' AND `moneda` = 'Dólares' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        $object["data"]["efectivo"] = $localConnection->goQuery();



        // Pesos

        $sql = "SELECT SUM(`monto`) monto, 'Pesos' moneda, `tasa`, `metodo_pago` tipo, SUM(`monto`) / `tasa` dolares FROM `metodos_de_pago` WHERE metodo_pago = 'Efectivo' AND `moneda` = 'Pesos' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["efectivo"], $localConnection->goQuery()[0]);



        // Bolívares

        $sql = "SELECT SUM(`monto`) monto, 'Bolívares' moneda, `tasa`, `metodo_pago` tipo, SUM(`monto`) / `tasa` dolares FROM `metodos_de_pago` WHERE metodo_pago = 'Efectivo' AND `moneda` = 'Bolívares' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["efectivo"], $localConnection->goQuery()[0]);





        /** MONEDA DIGITAL */

        // ZELLE

        $sql = "SELECT SUM(`monto`) monto, `tasa`, FORMAT(SUM(monto) / tasa, 'C2') dolares, `moneda`, 'Zelle' metodo_pago, `tipo_de_pago` FROM `metodos_de_pago` WHERE `metodo_pago` = 'Zelle' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        $object["data"]["digital"] = $localConnection->goQuery();



        // PAGOMOVIL (bOLIVARES)

        $sql = "SELECT SUM(`monto`) monto, `tasa`, FORMAT(SUM(monto) / tasa, 'C2') dolares, `moneda`, 'Pagomovil' metodo_pago, `tipo_de_pago` FROM `metodos_de_pago` WHERE `metodo_pago` = 'Pagomovil' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["digital"], $localConnection->goQuery()[0]);



        // PUNTO (BOLIVARES)

        $sql = "SELECT SUM(`monto`) monto, `tasa`, FORMAT(SUM(monto) / tasa, 'C2') dolares, `moneda`, 'Punto' metodo_pago, `tipo_de_pago` FROM `metodos_de_pago` WHERE `metodo_pago` = 'Punto' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["digital"], $localConnection->goQuery()[0]);



        // TRANSFERENCIA (BOLIVARES)

        $sql = "SELECT SUM(`monto`) monto, `tasa`, FORMAT(SUM(monto) / tasa, 'C2') dolares, `moneda`, 'Transferencia' metodo_pago, `tipo_de_pago` FROM `metodos_de_pago` WHERE `metodo_pago` = 'Punto' AND `moment` >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["digital"], $localConnection->goQuery()[0]);



        /** RETIROS */

        $sql = "SELECT a.monto, a.moneda, a.tasa, FORMAT(a.monto / a.tasa, 'C2') dolares , a.detalle_retiro, b.nombre FROM retiros a JOIN empleados b ON b._id = a.id_empleado WHERE a.moment >= '" . $args["inicio"] . "' AND `moment` < '" . $args["fin"] . "'";

        $localConnection = new LocalDB($sql);
        $object["data"]["retiros"] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // Guardar nuevo retiro

    $app->post('/retiro', function (Request $request, Response $response) {

        $arr = $request->getParsedBody();



        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN

        $sql = "";



        if (intval($arr["montoDolaresEfectivo"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Dólares', 'Efectivo', '" . $arr["montoDolaresEfectivo"] . "', '" . $arr["detalle"] . "', '1');";
        }



        if (intval($arr["montoDolaresZelle"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Dólares', 'Zelle', '" . $arr["montoDolaresZelle"] . "', '" . $arr["detalle"] . "', '1');";
        }



        if (intval($arr["montoDolaresPanama"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Dólares', 'Panamá', '" . $arr["montoDolaresPanama"] . "', '" . $arr["detalle"] . "', '1');";
        }



        if (intval($arr["montoPesosEfectivo"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Pesos', 'Efectivo', '" . $arr["montoPesosEfectivo"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_peso"] . "');";
        }



        if (intval($arr["montoPesosTransferencia"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Pesos', 'Transferencia', '" . $arr["montoPesosTransferencia"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_peso"] . "');";
        }



        if (intval($arr["montoBolivaresEfectivo"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Bolívares', 'Efectivo', '" . $arr["montoBolivaresEfectivo"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        if (intval($arr["montoBolivaresPunto"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Bolívares', 'Punto', '" . $arr["montoBolivaresPunto"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        if (intval($arr["montoBolivaresPagomovil"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Bolívares', 'Pagomovil', '" . $arr["montoBolivaresPagomovil"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        if (intval($arr["montoBolivaresTransferencia"]) > 0) {

            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr["id_empleado"] . "', 'Bolívares', 'Transferencia', '" . $arr["montoBolivaresTransferencia"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        // $sql             = "UPDATE disenos SET linkdrive = '" . $data["url"] . "' WHERE _id = " . $data['id'];

        $localConnection = new LocalDB($sql);

        $data = $localConnection->goQuery();



        $response->getBody()->write(json_encode($sql));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Guardar nuevo abono

    $app->post('/otro-abono', function (Request $request, Response $response) {

        $arr = $request->getParsedBody();



        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN

        $sql = "";



        if (intval($arr["montoDolaresEfectivo"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Dólares', 'Efectivo', '" . $arr["montoDolaresEfectivo"] . "', '" . $arr["detalle"] . "', '1');";

            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $arr["montoDolaresEfectivo"] . "', 'Dólares', 1, 'abono', '" . $arr['id_empleado'] . "');";
        }



        if (intval($arr["montoDolaresZelle"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Dólares', 'Zelle', '" . $arr["montoDolaresZelle"] . "', '" . $arr["detalle"] . "', '1');";
        }



        if (intval($arr["montoDolaresPanama"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Dólares', 'Panamá', '" . $arr["montoDolaresPanama"] . "', '" . $arr["detalle"] . "', '1');";
        }



        if (intval($arr["montoPesosEfectivo"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Pesos', 'Efectivo', '" . $arr["montoPesosEfectivo"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_peso"] . "');";

            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES  ('" . $arr["montoPesosEfectivo"] . "', 'Pesos', '" . $arr["tasa_peso"] . "', 'abono', '" . $arr['id_empleado'] . "');";
        }



        if (intval($arr["montoPesosTransferencia"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Pesos', 'Transferencia', '" . $arr["montoPesosTransferencia"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_peso"] . "');";
        }



        if (intval($arr["montoBolivaresEfectivo"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Bolívares', 'Efectivo', '" . $arr["montoBolivaresEfectivo"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";

            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $arr["montoBolivaresEfectivo"] . "', 'Bolívares', '" . $arr["tasa_dolar"] . "', 'abono', '" . $arr['id_empleado'] . "');";
        }



        if (intval($arr["montoBolivaresPunto"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Bolívares', 'Punto', '" . $arr["montoBolivaresPunto"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        if (intval($arr["montoBolivaresPagomovil"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Bolívares', 'Pagomovil', '" . $arr["montoBolivaresPagomovil"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        if (intval($arr["montoBolivaresTransferencia"]) > 0) {

            $sql .= "INSERT INTO metodos_de_pago (moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr["tipoAbono"] . "', 'Bolívares', 'Transferencia', '" . $arr["montoBolivaresTransferencia"] . "', '" . $arr["detalle"] . "', '" . $arr["tasa_dolar"] . "');";
        }



        // $sql             = "UPDATE disenos SET linkdrive = '" . $data["url"] . "' WHERE _id = " . $data['id'];

        $localConnection = new LocalDB($sql);

        $data = $localConnection->goQuery();



        $response->getBody()->write(json_encode($sql));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obteber Retiros

    $app->get('/retiros/{fecha}', function (Request $request, Response $response, array $args) {

        // Obtener retiros

        $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN empleados b ON a.id_empleado = b._id WHERE a.moment LIKE '" . $args["fecha"] . "%'";

        $localConnection = new LocalDB($sql);

        $object["sql_retiros"] = $sql;

        $object["data"]["retiros"] = $localConnection->goQuery();



        //Obtener monto en cada moneda en eefectivo (caja + caja_fondo)

        /** FONDO */

        $sql = "SELECT dolares, pesos, bolivares FROM caja_fondos ORDER BY _id DESC LIMIT 1";

        $localConnection = new LocalDB($sql);

        $fondo = $localConnection->goQuery();

        $object['data']['fondo'] = $fondo;



        if (empty($fondo)) {

            $fondo[0]["dolares"] = 0;

            $fondo[0]["pesos"] = 0;

            $fondo[0]["bolivares"] = 0;
        }



        // DÓLARES EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["dolares"] . ") monto, moneda, tasa, FORMAT(((SUM(monto) / tasa)) + " . $fondo[0]["dolares"] . ", 'C2') dolares FROM caja WHERE moneda= 'Dólares'";

        $localConnection = new LocalDB($sql);

        $object["data"]["caja"] = $localConnection->goQuery();



        // PESOS EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["pesos"] . ") monto, moneda, tasa, FORMAT((SUM(monto) + " . $fondo[0]["pesos"] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Pesos'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["caja"], $localConnection->goQuery()[0]);



        // BOLIVARES     EN CAJA, 

        $sql = "SELECT (SUM(monto) + " . $fondo[0]["bolivares"] . ") monto, moneda, tasa, FORMAT((SUM(monto) + " . $fondo[0]["bolivares"] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Bolívares'";

        $localConnection = new LocalDB($sql);

        array_push($object["data"]["caja"], $localConnection->goQuery()[0]);



        // Obtener dolares

        $sql = "SELECT SUM(monto/tasa) total  FROM metodos_de_pago WHERE moneda = 'Dólares' AND metodo_pago = 'Efectivo' AND  moment LIKE '" . $args["fecha"] . "%'";

        $object["sql"]["doalres"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["data"]["retiros_total"] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Pagos Ordenes

    $app->get('/pagos-ordenes/{fecha}', function (Request $request, Response $response, array $args) {

        $sql = "SELECT _id, moment, monto, moneda, metodo_pago, id_orden, tasa FROM metodos_de_pago WHERE moment LIKE '" . $args["fecha"] . "%'";

        $localConnection = new LocalDB($sql);

        $object["data"] = $localConnection->goQuery();

        $object["sql"] = $sql;



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /**
     * FIN RETIROS
     */



    /** * Diseños * */
    // REVISAR DISEÑOS APRBADOS Y RECHAZADOS 
    $app->get('/diseno/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT a.id_orden, b._id id_revision, a._id id_diseno, b.detalles, b.estatus, b.revision, c.id_wp id_cliente, c.cliente_nombre cliente FROM disenos a JOIN revisiones b ON a._id = b.id_diseno JOIN ordenes c ON c._id = a.id_orden WHERE a.id_empleado =" . $args["id_empleado"] . " AND c.status != 'entregada' AND c.status != 'cancelada' AND c.status != 'terminado'";
        $object["sqlxxx"] = $sql;
        $localConnection = new localDB($sql);
        $object['revisiones'] = $localConnection->goQuery();

        $object["total_revisiones"] = count($object["revisiones"]);

        // Datos del cliente
        // $woo      = new WooMe();
        // $object["cliente"] = $woo->getCustomerById($args["_empleado"]);

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REVISAR DISEÑO APROBADO
    $app->get('/diseno/aprobado/{id_orden}', function (Request $request, Response $response, array $args) {
        // $sql = "SELECT a.id_orden, b._id id_revision, a._id id_diseno, b.detalles, b.estatus, b.revision, c.id_wp id_cliente, c.cliente_nombre cliente FROM disenos a JOIN revisiones b ON a._id = b.id_diseno JOIN ordenes c ON c._id = a.id_orden WHERE a.id_empleado =" . $args["id_empleado"];
        /* $sql = "SELECT terminado FROM disenos WHERE id_orden = " . $args["id_orden"];
        $localConnection = new localDB($sql);
        $respnse = $localConnection->goQuery(); */

        $sql = "SELECT revision, estatus, id_diseno FROM revisiones WHERE id_orden = " . $args["id_orden"] . " AND estatus = 'Aprobado'";
        $localConnection = new localDB($sql);
        $resp = $localConnection->goQuery();

        if (empty($resp)) {
            $object["aprobado"] = false;
        } else {
            $object["aprobado"] = true;
            $object["data"] = $resp[0];
        }

        // $object["total_revisiones"] = count($object["revisiones"]);

        // Datos del cliente
        // $woo      = new WooMe();
        // $object["cliente"] = $woo->getCustomerById($args["_empleado"]);

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar link de google drive
    $app->post('/disenos/link', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $sql = "UPDATE disenos SET linkdrive = '" . $data["url"] . "' WHERE _id = " . $data['id'];
        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar ajustes y personalizaciones
    $app->post('/diseno/ajustes-y-personalizaciones', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $sql = "";

        // Verificar si el registro de diseños y ajuste ya existe
        $sqlx = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE id_diseno = " . $data["id_diseno"];
        $localConnection = new LocalDB($sqlx);
        $dataRequest = $localConnection->goQuery();

        $comision["ajustes"] = 0.2 * intval($data["ajustes"]);
        $comision["personalizaciones"] = 0.3 * intval($data["personalizaciones"]);

        $sqlord = "SELECT id_orden, id_empleado FROM disenos WHERE _id = " . $data["id_diseno"];
        $localConnection = new localDB($sqlord);
        $resultDiseno = $localConnection->goQuery();

        if (empty($dataRequest)) {
            // Buscar datos para el guardar el pago

            $sql .= "INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (" . $data["ajustes"] . ", " . $resultDiseno[0]["id_orden"] . ", 'aprobado' , " . $comision["ajustes"] . ", " . $resultDiseno[0]["id_empleado"] . ", 'ajuste');";
            $sql .= "INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (" . $data["personalizaciones"] . ", " . $resultDiseno[0]["id_orden"] . ", 'aprobado' , " . $comision["personalizaciones"] . ", " . $resultDiseno[0]["id_empleado"] . ", 'personalización');";

            // buscar comisiones con WOOME
            if (intval($data["ajustes"]) > 0) {
                $sql .= "INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (" . $data["id_diseno"] . ", " . $resultDiseno[0]["id_orden"] . ", 'ajuste', " . $data["ajustes"] . ");";
            }

            if (intval($data["personalizaciones"]) > 0) {
                $sql .= "INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (" . $data["id_diseno"] . ", " . $resultDiseno[0]["id_orden"] . ", 'personalizacion', " . $data["personalizaciones"] . ");";
            }


        } else {
            $values = "monto_pago ='" . $comision["ajustes"] . "'";
            $sql .= "UPDATE pagos SET " . $values . " WHERE id_empleado = " . $resultDiseno[0]["id_empleado"] . " AND id_orden = " . $resultDiseno[0]["id_orden"] . " AND detalle = 'ajuste';";
            $values = "monto_pago ='" . $comision["personalizaciones"] . "'";
            $sql .= "UPDATE pagos SET " . $values . " WHERE id_empleado = " . $resultDiseno[0]["id_empleado"] . " AND id_orden = " . $resultDiseno[0]["id_orden"] . " AND detalle = 'personalización';";

            $sql .= "UPDATE disenos_ajustes_y_personalizaciones SET cantidad = " . $data["ajustes"] . " WHERE id_diseno = " . $data['id_diseno'] . " AND tipo = 'ajuste';";

            $sql .= "UPDATE disenos_ajustes_y_personalizaciones SET cantidad = " . $data["personalizaciones"] . " WHERE id_diseno = " . $data['id_diseno'] . " AND tipo = 'personalizacion';";
        }
        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener ajustes y personalizaciones de un diseno
    $app->get('/disenos/ajustes-y-personalizaciones/{id_diseno}', function (Request $request, Response $response, array $args) {
        // $sql = "SELECT tipo, cantidad FROM disenos_ajustes_y_personalizaciones WHERE id_diseno = " . $args["id_diseno"];

        $sql = "SELECT a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = " . $args["id_diseno"];
        $localConnection = new LocalDB($sql);
        $object = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener link de google drive
    $app->get('/disenos/link/{id}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT linkdrive FROM disenos WHERE _id = " . $args["id"];
        $localConnection = new LocalDB($sql);
        $object = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object[0]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener codigo del diseño
    $app->get('/disenos/codigo/{id}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT codigo_diseno FROM disenos WHERE _id = " . $args["id"];
        $localConnection = new LocalDB($sql);
        $object = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar codigo de diseno

    $app->post('/disenos/codigo', function (Request $request, Response $response) {

        $data = $request->getParsedBody();

        $sql = "UPDATE disenos SET codigo_diseno = '" . $data["cod"] . "' WHERE _id = " . $data['id'];
        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // Obtener diseños sin asignar

    $app->get('/disenos', function (Request $request, Response $response) {
        $object['disenos']['fields'][0]['key'] = "id";
        $object['disenos']['fields'][0]['label'] = "Orden";

        $object['disenos']['fields'][1]['key'] = "tipo";
        $object['disenos']['fields'][1]['label'] = "Tipo";

        $object['disenos']['fields'][2]['key'] = "empleado";
        $object['disenos']['fields'][2]['label'] = "Empleado";

        $object['disenos']['fields'][3]['key'] = "vinculadas";
        $object['disenos']['fields'][3]['label'] = "Vinculadas";

        $object['disenos']['fields'][4]['key'] = "imagen";
        $object['disenos']['fields'][4]['label'] = "Diseño";

        // $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.tipo != 'no' AND a.terminado = 0 AND (b.status 'En espera' OR b.status 'Activa' OR b.status 'Pausada')";
        $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.tipo != 'no' AND a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado'";

        $localConnection = new LocalDB($sql);
        $object['disenos']["items"] = $localConnection->goQuery();

        $localConnection = new LocalDB('SELECT * FROM empleados');
        $object['empleados'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // Todos los diseños asignados

    $app->get('/disenos/asignados', function (Request $request, Response $response) {

        $object['disenos']['fields'][0]['key'] = "id";

        $object['disenos']['fields'][0]['label'] = "Orden";

        // $object['disenos']['fields'][0]['sortable'] = true;



        $object['disenos']['fields'][1]['key'] = "tipo";

        $object['disenos']['fields'][1]['label'] = "Tipo";

        // $object['disenos']['fields'][1]['sortable'] = false;



        $object['disenos']['fields'][2]['key'] = "empleado";

        $object['disenos']['fields'][2]['label'] = "Empleado";

        // $object['disenos']['fields'][2]['sortable'] = false;



        // $sql                        = "SELECT tipo, id_orden id, id_empleado empleado FROM disenos WHERE id_empleado = 0";

        $sql = "SELECT a.tipo, a.id_orden, b.username, b.nombre, b._id id_empleado, FROM disenos a JOIN empleados b ON a.id_empleado = b._id  WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";

        $localConnection = new LocalDB($sql);

        $object['disenos']["items"] = $localConnection->goQuery();



        $localConnection = new LocalDB('SELECT * FROM empleados');

        $object['empleados'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Todos los diseños terminados

    $app->get('/disenos/terminados', function (Request $request, Response $response) {

        $object['fields'][0]['key'] = "orden";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "disenador";
        $object['fields'][2]['label'] = "Diseñador";

        $object['fields'][3]['key'] = "inicio";
        $object['fields'][3]['label'] = "Inicio";

        $object['fields'][4]['key'] = "entrega";
        $object['fields'][4]['label'] = "Entregado";

        $object['fields'][5]['key'] = "tipo";
        $object['fields'][5]['label'] = "Tipo";

        $object['fields'][6]['key'] = "imagen";
        $object['fields'][6]['label'] = "Imagen";

        $sql = "SELECT a.id_orden orden, b.cliente_nombre cliente, c.nombre disenador, b.fecha_inicio inicio, b.fecha_entrega entrega, a.tipo, b._id imagen FROM disenos a JOIN ordenes b ON a.id_orden = b._id JOIN empleados c ON a.id_empleado = c._id WHERE a.terminado = 1";

        $localConnection = new LocalDB($sql);
        $object["items"] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Diseñosasignados a Diseñador

    $app->get('/disenos/asignados/{id_empleado}', function (Request $request, Response $response, array $args) {

        $object['fields'][0]['key'] = "id";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "inicio";
        $object['fields'][2]['label'] = "Inicio";

        $object['fields'][3]['key'] = "revision";
        $object['fields'][3]['label'] = "Revisión";
        $object['fields'][3]['class'] = "text-center";

        $object['fields'][4]['key'] = "tallas_y_personalizacion";
        $object['fields'][4]['label'] = "Tallas y Personalización";
        $object['fields'][4]['class'] = "text-center";

        $object['fields'][5]['key'] = "id_orden";
        $object['fields'][5]['label'] = "Vinculadas";
        $object['fields'][5]['class'] = "text-center";

        $object['fields'][6]['key'] = "codigo_diseno";
        $object['fields'][6]['label'] = "Código Diseño";
        $object['fields'][6]['class'] = "text-center";

        $object['fields'][7]['key'] = "linkdrive";
        $object['fields'][7]['label'] = "Google Drive";
        $object['fields'][7]['class'] = "text-center";

        /* $object['fields'][6]['key']   = "imagen";
        $object['fields'][6]['label'] = "Imagen";
        $object['fields'][6]['class'] = "text-center"; */

        $object['fields'][8]['key'] = "revision";
        $object['fields'][8]['label'] = "Revisiones";
        $object['fields'][8]['class'] = "text-center";

        // CONSULTA PARA COMERCIALIZACION, PASAR A OTRO ENDPOINT
        // $sql = "SELECT a._id linkdrive, a.codigo_diseno, a.id_orden, a.id_orden id, a.id_orden imagen, a.id_orden revision, b.cliente_nombre cliente, b.fecha_inicio inicio, a.tipo, c.estatus FROM disenos a JOIN ordenes b ON b._id = a.id_orden LEFT JOIN revisiones c ON a._id = c.id_diseno WHERE b.responsable =  " . $args["id_empleado"] . " AND terminado = 0 ORDER BY a.id_orden ASC";

        $sql = "SELECT 
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
        WHERE a.id_empleado =    " . $args["id_empleado"] . " 
        AND a.terminado = 0 
        ORDER BY a.id_orden ASC
        ";

        /*         $sql = "SELECT
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
        FROM
        disenos a
        LEFT JOIN revisiones c ON a._id = c.id_diseno
        LEFT JOIN ordenes b ON b._id = a.id_orden
        WHERE
        a.id_empleado = 4
        AND a.terminado = 0
        ORDER BY
        a.id_orden ASC
        "; */

        $object['sql_items'] = $sql;
        $localConnection = new LocalDB($sql);
        $object["items"] = $localConnection->goQuery();


        // $object['sql_disenos'] = $sql;

        $localConnection = new LocalDB($sql);
        $object["items"] = $localConnection->goQuery();

        $sql = "SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = " . $args["id_empleado"];

        $object['sql_revisiones'] = $sql;
        $localConnection = new LocalDB($sql);
        $object["revisiones"] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // TODO eliminar ninesys antiguo => Obtener diseños pendientes por diseñador
    $app->get('/disenos/pendientes/{id_empleado}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";

        $sql = "SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = " . $args["id_empleado"] . " AND terminado = 0";

        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $disenos = $localConnection->goQuery();

        $response->getBody()->write(json_encode($disenos));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obtener diseños terminados por diseñador

    $app->get('/disenos/terminados/{id_empleado}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";



        $sql = "SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = " . $args["id_empleado"] . " AND terminado = 1";

        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $disenos = $localConnection->goQuery();

        $response->getBody()->write(json_encode($disenos));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Asignar diseñador

    $app->put('/disenos/asign/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {



        $sql = "UPDATE disenos SET id_empleado = " . $args['empleado'] . " WHERE id_orden = " . $args['id_orden'];



        $localConnection = new LocalDB($sql);

        $asignacion = $localConnection->goQuery();

        $response->getBody()->write(json_encode($asignacion));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Diseñador dar diseño por terminado

    $app->put('/disenos/close/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {



        $sql = "UPDATE disenos SET terminado = 1 WHERE id_orden = " . $args['id_orden'] . " AND id_empleado = " . $args["empleado"];



        $localConnection = new LocalDB($sql);

        $asignacion = $localConnection->goQuery();

        $response->getBody()->write(json_encode($asignacion));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });
    /**
     * Fin Diseños
     */

    /**
     * PAGOS
     */
    // Terminar planilla de pago
    $app->post("/pagos/terminar-planilla", function (Request $request, Response $response, $args) {
        // $order = $request->getParsedBody();
        $myDate = new CustomTime();
        $now = $myDate->today();

        $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE fecha_pago IS NULL";
        $localConnection = new LocalDB($sql);

        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REALIZAR PAGO A EMPLEADOS
    $app->post("/pagos/pagar-a-empleados", function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();

        $myDate = new CustomTime();
        $now = $myDate->today();

        $listaDeIdPagos = explode(',', $data['id_pagos']);
        $params = "";
        foreach ($listaDeIdPagos as $key => $value) {
            $params .= " _id = " . $value . " OR ";
        }
        $params = substr($params, 0, -4); // Eliminamos el ultimo OR
        $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE " . $params;

        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Lista de pagos semanales
    $app->get('/pagos/semana', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        /* $sql = "SELECT 
        e._id id_pago,
        a._id id_orden,
        a.status,
        a.pago_comision,
        c.nombre nombre,
        c._id id_empleado,
        c.departamento,
        FORMAT(((b.monto/ b.tasa) * 5 /100), 2) comsion_vendedor,
        b.tipo_de_pago,
        b.metodo_pago,
        b.moneda, 
        b.tasa, 
        b.monto monto_pagado, 
        a.pago_total monto_total_orden,                         
        (b.monto / b.tasa) pago,          
        (SELECT (a.pago_total - SUM(monto / tasa)) abonado FROM metodos_de_pago WHERE id_orden = a._id) acumulado,
        DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago  
        FROM ordenes a
        JOIN empleados c ON c._id = a.responsable 
        JOIN metodos_de_pago b ON a._id = b.id_orden 
        JOIN pagos e ON e.id_metodos_de_pago = b._id 
        WHERE (SELECT (a.pago_total - SUM(monto / tasa)) abonado FROM metodos_de_pago WHERE id_orden = a._id) > 0 AND (`status` = 'En espera' OR `status` = 'activa' OR `status` = 'pausada' OR `status` = 'terminada') AND a.pago_comision = 'pendiente'"; */

        $sql = "SELECT
        a._id id_pago,
        a.id_orden,         
        a.id_empleado,
        a.detalle, 
        a.cantidad,
        a.monto_pago pago,
        c.nombre,
        d.status,
        e.tipo_de_pago, 
        DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago 
        FROM pagos a 
        JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
        JOIN empleados c ON a.id_empleado = c._id
        JOIN ordenes d ON a.id_orden = d._id 
        LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago 
        WHERE fecha_pago IS NULL 
        ";

        /* $sql = "SELECT 
        e._id id_pago,
        a._id id_orden,
        a.status,
        a.pago_comision,
        c.nombre nombre,
        c._id id_empleado,
        c.departamento,
        FORMAT(((e.monto_pago/ b.tasa) * 5 /100), 2) comsion_vendedor,
        a.pago_total monto_total_orden,                         
        e.monto_pago pago,                  
        DATE_FORMAT(e.moment, '%d/%m/%Y') fecha_de_pago  
        FROM ordenes a
        JOIN empleados c ON c._id = a.responsable         
        JOIN pagos e ON e.id_metodos_de_pago = b._id 
        WHERE (SELECT (a.pago_total - SUM(monto / tasa)) abonado FROM metodos_de_pago WHERE id_orden = a._id) > 0 AND (`status` = 'En espera' OR `status` = 'activa' OR `status` = 'pausada' OR `status` = 'terminada') AND a.pago_comision = 'pendiente'"; */

        $localConnection = new LocalDB($sql);
        $object['data']["vendedores"] = $localConnection->goQuery();

        // OBTENER PAGOS DE EMPLEADOS
        $sql = 'SELECT
            a._id id_pago,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            d.talla,
            c._id id_empleado,
            c.nombre,
            c.comision,
            c.departamento,
            DATE_FORMAT(b.moment, "%a") dia,
            DATE_FORMAT(b.moment, "%v") semana,
            DATE_FORMAT(b.moment, "%d/%m/%y") fecha,
            b.unidades_solicitadas cantidad,
            a.monto_pago pago,
            a.fecha_pago,
            a.cantidad,
            TIMESTAMPDIFF(MINUTE, b.fecha_inicio, b.fecha_terminado) tiempo_transcurrido
        FROM pagos a
        JOIN lotes_detalles b
            ON a.id_lotes_detalles = b._id
        JOIN empleados c
            ON b.id_empleado = c._id
        JOIN ordenes_productos d
            ON b.id_ordenes_productos = d._id
        WHERE  fecha_pago IS NULL 
        ORDER BY c.nombre ASC;
        ';
        // WHERE YEARWEEK(a.moment) = YEARWEEK(NOW())AND

        $localConnection = new LocalDB($sql);
        $object['data']["empleados"] = $localConnection->goQuery();

        // OBTENER INFORMACION DE DISEÑADORES
        $sql = "SELECT 
            a._id id_pago,
            a.id_orden, 
            a.id_empleado,
            a.detalle detalle_pago,
            e._id id_diseno, 
            b.nombre nombre, 
            b.departamento, 
            a.monto_pago pago,
            a.cantidad,
            c.name producto 
            FROM pagos a   
            JOIN disenos e ON e.id_empleado = a.id_empleado AND e.id_orden = a.id_orden
            JOIN empleados b 
                ON b._id = a.id_empleado 
            JOIN ordenes_productos c 
                ON a.id_orden = c.id_orden AND c.category_name = 'Diseños'
            WHERE a.id_orden IS NOT NULL AND a.fecha_pago IS NULL";
        $localConnection = new LocalDB($sql);
        $object['data']["diseno"] = $localConnection->goQuery();

        foreach ($object['data']["diseno"] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = "SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = " . $value["id_orden"];
            $localConnection = new LocalDB($sqlTMP);
            $tmpResp = $localConnection->goQuery();
            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object["data"]["trabajos_adicionales"][] = $value2;
                }
            }
        }

        $trabajos_adicionales_nuevos = [];

        if (!empty($object["data"]["trabajos_adicionales"])) {
            foreach ($object["data"]["trabajos_adicionales"] as $trabajo_adicional) {
                $existe = false;
                foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                    if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $trabajos_adicionales_nuevos[] = $trabajo_adicional;
                }
            }
            $object["data"]["trabajos_adicionales"] = $trabajos_adicionales_nuevos;
        } else {
            $object["data"]["trabajos_adicionales"] = [];

        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Lista de pagos semanales con filtro de fechas
    $app->post('/pagos/semana', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();

        if ($data["fecha_inicio"] === $data["fecha_fin"]) {
            $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' AND e.fecha_pago IS NOT NULL";
        } else {
            $where = "(DATE(e.moment) BETWEEN '" . $data["fecha_inicio"] . "'
                AND '" . $data["fecha_fin"] . "') AND e.fecha_pago IS NULL";
        }

        // BUSCAR PAGOS DE VENDEDORES
        $sql = "SELECT 
            e._id id_pago,
            a._id id_orden,
            a.status,
            a.pago_comision,
            c.nombre nombre,
            c._id id_empleado,
            c.departamento,
            FORMAT(((b.monto/ b.tasa) * 5 /100), 2) comsion_vendedor,
            b.tipo_de_pago,
            b.metodo_pago,
            b.moneda,            
            b.tasa,
            b.monto monto_pagado,
            a.pago_total monto_total_orden,                         
            (b.monto / b.tasa) pago,            
            (SELECT (a.pago_total - SUM(monto / tasa)) abonado FROM metodos_de_pago WHERE id_orden = a._id) acumulado,
            DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago  
            FROM ordenes a
            JOIN empleados c ON c._id = a.responsable 
            JOIN metodos_de_pago b ON a._id = b.id_orden 
            JOIN pagos e on e.id_empleado = a.responsable 
            WHERE " . $where . " AND (SELECT (a.pago_total - SUM(monto / tasa)) abonado FROM metodos_de_pago WHERE id_orden = a._id) > 0 AND (`status` = 'En espera' OR `status` = 'activa' OR `status` = 'pausada' OR `status` = 'terminada') AND a.pago_comision = 'pendiente'";
        $localConnection = new LocalDB($sql);
        $object["sql"]["vendedores"] = $sql;
        $object['data']["vendedores"] = $localConnection->goQuery();
        // FIN BUSCAR PAGOS DE VENDEDORES

        // OBTENER PAGOS DE EMPLEADOS
        $sql = 'SELECT
            e._id id_pago,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            d.talla,
            c._id id_empleado,
            c.nombre,
            c.comision,
            c.departamento,
            DATE_FORMAT(b.moment, "%a") dia,
            DATE_FORMAT(b.moment, "%v") semana,
            DATE_FORMAT(b.moment, "%d/%m/%y") fecha,
            b.unidades_solicitadas cantidad,
            e.monto_pago pago,
            e.fecha_pago,
            e.cantidad,
            TIMESTAMPDIFF(MINUTE, b.fecha_inicio, b.fecha_terminado) tiempo_transcurrido
        FROM pagos e
        JOIN lotes_detalles b
            ON e.id_lotes_detalles = b._id
        JOIN empleados c
            ON b.id_empleado = c._id
        JOIN ordenes_productos d
            ON b.id_ordenes_productos = d._id
        WHERE ' . $where . ' AND e.fecha_pago IS NULL 
        ORDER BY c.nombre ASC;
        ';

        $localConnection = new LocalDB($sql);
        $object['sql']["empleados"] = $sql;
        $object['data']["empleados"] = $localConnection->goQuery();
        // FIN PAGOS EMPLEADOS

        // OBTENER INFORMACION DE DISEÑADORES
        $sql = "SELECT 
            e._id id_pago,
            e.id_orden, 
            e.id_empleado,
            e.detalle detalle_pago,
            a._id id_diseno, 
            b.nombre nombre, 
            b.departamento, 
            e.monto_pago pago,
            e.cantidad,
            c.name producto 
            FROM pagos e   
            JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
            JOIN empleados b 
                ON b._id = e.id_empleado 
            JOIN ordenes_productos c 
                ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
            WHERE " . $where;
        $localConnection = new LocalDB($sql);
        $object['sql']["diseno"] = $sql;
        $object['data']["diseno"] = $localConnection->goQuery();

        foreach ($object['data']["diseno"] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = "SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = " . $value["id_orden"];
            $localConnection = new LocalDB($sqlTMP);
            $tmpResp = $localConnection->goQuery();
            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object["data"]["trabajos_adicionales"][] = $value2;
                }
            }
        }

        $trabajos_adicionales_nuevos = [];

        if (!empty($object["data"]["trabajos_adicionales"])) {
            foreach ($object["data"]["trabajos_adicionales"] as $trabajo_adicional) {
                $existe = false;
                foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                    if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $trabajos_adicionales_nuevos[] = $trabajo_adicional;
                }
            }
            $object["data"]["trabajos_adicionales"] = $trabajos_adicionales_nuevos;
        } else {
            $object["data"]["trabajos_adicionales"] = [];

        }
        // FIN PAGOS DISEÑADORES

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * FIN PAGOS
     */



    /**
     * PRODUCTOS
     */



    // Obtener todos los productos

    $app->get('/products', function (Request $request, Response $response) {

        $woo = new WooMe();
        $response->getBody()->write($woo->getAllProducts());



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    $app->get('/products/categories/{id_category}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();
        $response->getBody()->write($woo->getCategoryById($args["id_category"]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener todos los productos asignados a una orden
    $app->get('/productos-asignados/{orden}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = " . $args["orden"] . " AND name NOT LIKE 'DISEÑO%'";
        $sql = "SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = " . $args["orden"] . " AND category_name != 'Diseños'";



        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['data'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obtener prodcuto por ID

    $app->get('/products/{id}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();

        $product = $woo->getProductById($args["id"]);

        $response->getBody()->write(json_encode($product));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Crear un nuevo producto

    $app->post('/products/{name}/{sku}/{price}/{stock_quantity}/{categories}/{sizes}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();



        $response->getBody()->write($woo->createProduct($args["name"], $args["sku"], $args["price"], $args["stock_quantity"], $args["categories"], $args["sizes"]));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Crear un nuevo producto lite

    // $app->post('/products/lite/{name}/{sku}/{price}/{stock_quantity}/{categories}/{sizes}

    $app->post('/products/lite/{name}/{price}/{categories}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        // $response->getBody()->write($woo->createProductLite($args["name"], $args["price"], $args["categories"]));
        // $response->getBody()->write($woo->getAllProducts());
        $woo->createProductLite($args["name"], $args["price"], $args["categories"]);
        // $object["data"] = $woo->getAllProducts();

        $response->getBody()->write($woo->getAllProducts());

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // Editar producto

    $app->put('/products/{id}/{name}/{price}/{stock_quantity}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();



        $response->getBody()->write($woo->updateProduct($args["id"], $args["name"], $args["price"], $args["stock_quantity"]));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Actualizar Stock

    $app->put('/products/stock/{id}/{stock_quantity}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();



        $response->getBody()->write($woo->updateProductStock($args["id"], $args["stock_quantity"]));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Eliminar Producto (Usamos el metodo `options` porque noo acepta metodo `delete`  da ERROR 405)

    $app->delete('/products/{id}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();



        $response->getBody()->write($woo->deleteProduct($args["id"]));



        return $response

            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /**
     * FIN PROIDUCTOS
     */

    /**
     * MANEJO DE ATRIBUTOS PARA PRODUCTOS
     */

    // ASIGNAR COMISION APRODUCTO
    $app->get('/product-set-comision/{id}/{comision}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();
        $res = $woo->updateProductComision($args["id"], $args["comision"]);
        $object["res"] = $res;

        $sql1 = "SELECT _id id_lotes_detalles, unidades_solicitadas, id_empleado FROM lotes_detalles WHERE id_woo = " . $args["id"] . " AND departamento = 'Costura' AND fecha_terminado IS NOT NULL";
        $object["sql1"] = $sql1;
        $tmpConnection = new LocalDB($sql1);
        $resp = $tmpConnection->goQuery();

        if (!empty($resp)) {
            foreach ($resp as $row) {
                $nuevo_pago = floatval($args['comision']) * intval($row['unidades_solicitadas']);
                $sql2 = "UPDATE pagos SET monto_pago = " . $nuevo_pago . " WHERE id_empleado = " . $row['id_empleado'] . " AND fecha_pago IS NULL AND id_lotes_detalles = " . $row["id_lotes_detalles"];
                $object["sql2"] = $sql2;
                $tmpConnection = new LocalDB($sql2);
                $tmpConnection->goQuery();
            }
            /* $nuevo_pago = floatval($args['comision']) * intval($resp[0]['unidades_solicitadas']);
            $sql2 = "UPDATE pagos SET monto_pago = " . $nuevo_pago . " WHERE id_empleado = " . $resp['id_empleado'] . " AND fecha_pago IS NULL";
            $tmpConnection = new LocalDB($sql2);
            $resp = $tmpConnection->goQuery(); */
        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENR LOS PARODUCTOS APRA LE MANEJO DE ATRIBUTOS DE COMSIONES
    $app->get('/atributos/comisiones', function (Request $request, Response $response) {

        $woo = new WooMe();
        $object['data'] = json_decode($woo->getProductsAttr());
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /**
     * FIN MANEJO DE ATRIBUTOS PARA PRODUCTOS
     */



    /** * CLIENTES */

    // OBTENER TODOS LOS CLIENTES

    $app->get('/customers', function (Request $request, Response $response) {

        $woo = new WooMe();

        // $woo->getAllCustomesrs();

        $object['data'] = json_decode($woo->getAllCustomesrs());

        $response->getBody()->write(json_encode($object));

        // $response->getBody()->write($woo->getAllCustomesrs());



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    $app->post('/customers', function (Request $request, Response $response) {

        $woo = new WooMe();

        // $woo->getAllCustomesrs();



        // $response->getBody()->write($woo->getAllCustomesrs());

        $response->getBody()->write("OK =>???");



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obtener Cliente por ID

    $app->get('/customers/{id}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();

        $customer = $woo->getCustomerById($args["id"]);



        $response->getBody()->write(json_encode($customer));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Crear un nuevo cliente

    $app->post('/customers/{first_name}/{last_name}/{cedula}/{phone}/{email}/{address}', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();



        /* if ($args["phone"] === "none") {
        $phone = "";
        } else {
        $phone = $args["phone"];
        }
        if ($args["email"] === "none") {
        $email = "";
        } else {
        $email = $args["email"];
        }
        if ($args["address"] === "none") {
        $address = "";
        } else {
        $address = $args["address"];
        }
        */

        $response->getBody()->write(
            $woo->createCustomer(

                $args["first_name"],

                $args["last_name"],

                $args["cedula"],

                $args["phone"],

                $args["email"],

                $args["address"]

            )
        );

        // $response->getBody()->write(json_encode($args));



        return $response

            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Actualizar Cliente

    $app->put('/customers/{id}/{first_name}/{last_name}/{cedula}/{phone}/{email}/{billing_address}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $response->getBody()->write($woo->updateCustomer($args["id"], $args["first_name"], $args["last_name"], $args["cedula"], $args["phone"], $args["email"], $args["billing_address"]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** *  CATEGORIAS */
    $app->get('/categories', function (Request $request, Response $response) {
        $woo = new WooMe();

        $response->getBody()->write($woo->getAllCategories());
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    /**
     * FIN CATEGORIAS
     */



    /** * ATRIBUTOS */



    $app->get('/attributes', function (Request $request, Response $response) {

        $woo = new WooMe();



        $response->getBody()->write($woo->getAllAttributes());



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * FIN ATRINUTOS
     */



    /** * TALLAS */



    $app->get('/sizes', function (Request $request, Response $response) {

        $woo = new WooMe();

        $sizes = json_decode($woo->getSizes());

        $object['data'] = $sizes;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * FIN TALLAS
     */



    /** * ORDENES */

    // Editar orden -> Actualixar datos

    $app->post("/orden/editar", function (Request $request, Response $response) {

        /** opciones de edición
         *   - editar-talla
         *   - editar-cantidad
         *   - editar-corte
         *   - editar-tela
         *   - nuevo-producto
         *   - eliminar-producto
         * */



        $data = $request->getParsedBody();



        switch ($data["accion"]) {

            case 'editar-cantidad':

                $sql = "UPDATE ordenes_productos SET cantidad = " . $data["cantidad"] . " WHERE _id = " . $data["id"];

                $tmpConnection = new LocalDB($sql);

                $resp = $tmpConnection->goQuery();



                // Recalcular nuevo pago_total de la orden

                $sql = "SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = " . $data["id_orden"];



                $tmpConnection = new LocalDB($sql);

                $resp = $tmpConnection->goQuery();

                $object["total_sql"] = $sql;

                $nuevototal = $resp[0]["total"];



                $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data["id_orden"];



                break;



            case 'editar-talla':

                // Guardar nuevos datos

                $sql = "UPDATE ordenes_productos SET precio_unitario = '" . $data["precio"] . "', talla = '" . $data["cantidad"] . "' WHERE _id = " . $data["id"] . ";";

                $tmpConnection = new LocalDB($sql);

                $resp = $tmpConnection->goQuery();



                // Recalcular nuevo pago_total de la orden

                $sql = "SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = " . $data["id_orden"];



                $tmpConnection = new LocalDB($sql);

                $resp = $tmpConnection->goQuery();

                $object["total_sql"] = $sql;

                $nuevototal = $resp[0]["total"];



                // Guardar nuevo pago_total de la orden

                $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data["id_orden"];



                break;



            case 'editar-corte':

                $sql = "UPDATE ordenes_productos SET corte = '" . $data["cantidad"] . "' WHERE _id = " . $data["id"];

                break;



            case 'eliminar-producto':

                $sql = "DELETE FROM ordenes_productos WHERE _id = " . $data["id"];

                break;



            case 'editar-tela':



                // Guardar cambios

                $sql = "UPDATE ordenes_productos SET tela = '" . $data["cantidad"] . "' WHERE _id = " . $data["id"] . ";";

                break;



            case 'nuevo-producto':

                $campos = "(moment, id_orden, id_woo, precio_woo, name, cantidad, talla, corte, tela, precio_unitario)";



                // PREPARAR FECHAS

                $myDate = new CustomTime();

                $now = $myDate->today();



                $values = "(";

                $values .= "'" . $now . "',";

                $values .= "" . $data["id_orden"] . ",";

                $values .= "" . $data["id_woo"] . ",";

                $values .= "" . $data["precio_woo"] . ",";

                $values .= "'" . $data["name"] . "',";

                $values .= "" . $data["cantidad"] . ",";

                $values .= "'" . $data["talla"] . "',";

                $values .= "'" . $data["corte"] . "',";

                $values .= "'" . $data["tela"] . "',";

                $values .= "" . $data["precio_unitario"] . ")";



                $sql = "INSERT INTO ordenes_productos " . $campos . " VALUES " . $values;

                break;



            default:

                # code...

                break;
        }



        $localConnection = new LocalDB($sql);

        $resp = $localConnection->goQuery();

        $object["sql"] = $sql;

        $object["response"] = $resp;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Actualizar estado de la orden

    $app->post("/orden/actualizar-estado", function (Request $request, Response $response, $args) {

        $order = $request->getParsedBody();

        $sql = "UPDATE ordenes SET status = '" . $order["estado"] . "' WHERE _id = " . $order['id'];

        $localConnection = new LocalDB($sql);



        $data = $localConnection->goQuery();



        $response->getBody()->write(json_encode($sql));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Buscar ordenes para asignación

    $app->get('/orden/asignacion/{id}', function (Request $request, Response $response, array $args) {

        $id = $args['id'];



        $sql['detalle_empleados'] = "SELECT `dep_responsable_detalles` responsable, `dep_diseno_detalles` diseno, `dep_corte_detalles` corte, `dep_impresion_detalles` impresion, `dep_estampado_detalles` estampado, `dep_confeccion_detalles` confeccion, `dep_revision_detalles` revision FROM `ordenes` WHERE `_id` = " . $id;



        $sql['orden'] = " SELECT _id, status, cliente_nombre, cliente_cedula, lote_id lote, fecha_inicio, fecha_entrega FROM ordenes WHERE _id = '" . $id . "' ";

        $sql['orden_personas'] = "SELECT * FROM ordenes_personas WHERE id_order = '" . $id . "'";

        $sql['ordeen_personas_productos'] = "SELECT a._id, a.id_orden, a.idp, a.prodcuto, a.cantidad, a.talla, a.tela, a.detalles, b.nombre FROM ordenes_personas_productos a JOIN ordenes_personas b ON a.idp = b.idp WHERE id_orden = '" . $id . "'";

        // $sql['orden_productos']                = "SELECT a._id, a.name FROM products a JOIN ordenes_productos b ON b.name = a.name WHERE b.id_orden = '" . $id . "'";

        // $sql['orden_productos']                = "SELECT _id, id_woo, name FROM ordenes_productos WHERE b.id_orden = '" . $id . "'";

        $sql['orden_productos'] = "SELECT _id, id_woo, name FROM ordenes_productos WHERE id_orden = '" . $id . "'";

        $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['corte'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_corte = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['impresion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_impresion = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['estampado'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_estampado = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['confeccion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_confeccion = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['revision'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_revision = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['responsable'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_responsable = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";

        $sql['orden_productos_cantidad'] = "SELECT a.cantidad, a.prodcuto,. a.idp FROM ordenes_personas_productos a WHERE  id_orden = '" . $id . "'";

        // $sql['lotes_detalles'] = "SELECT * FROM lotes_detalles WHERE  id_orden = '" . $id . "'";

        $sql['lotes_detalles'] = "SELECT producto, unidades_solicitadas, unidades_restantes, departamento, id_orden FROM lotes_detalles WHERE id_orden = " . $id;



        $localConnection = new LocalDB($sql['orden']);

        $object = $localConnection->goQuery()[0];



        $localConnection = new LocalDB($sql['detalle_empleados']);

        $object['detalle_empleados'] = $localConnection->goQuery()[0];



        $localConnection = new LocalDB($sql['orden_productos_cantidad']);

        $object['orden_productos_cantidad'] = $localConnection->goQuery();



        $localConnection = new LocalDB($sql['orden_personas']);

        $object['orden_personas'] = $localConnection->goQuery();



        $localConnection = new LocalDB($sql['ordeen_personas_productos']);

        $object['orden_personas_productos'] = $localConnection->goQuery();



        $localConnection = new LocalDB($sql['orden_productos']);

        $object['orden_productos'] = $localConnection->goQuery();



        // LOTES DETALLES

        $localConnection = new LocalDB($sql['lotes_detalles']);

        $object['lotes_detalles'] = $localConnection->goQuery();



        // EMPLEADOS

        $localConnection = new LocalDB($sql['orden_empleados']['corte']);

        $object['empleados']['corte'] = $localConnection->goQuery();

        if ($object['empleados']['corte'] == null) {

            $object['empleados']['corte'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['impresion']);

        $object['empleados']['impresion'] = $localConnection->goQuery();

        if ($object['empleados']['impresion'] == null) {

            $object['empleados']['impresion'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['estampado']);

        $object['empleados']['estampado'] = $localConnection->goQuery();

        if ($object['empleados']['estampado'] == null) {

            $object['empleados']['estampado'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['confeccion']);

        $object['empleados']['confeccion'] = $localConnection->goQuery();

        if ($object['empleados']['confeccion'] == null) {

            $object['empleados']['confeccion'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['revision']);

        $object['empleados']['revision'] = $localConnection->goQuery();

        if ($object['empleados']['revision'] == null) {

            $object['empleados']['revision'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['responsable']);

        $object['empleados']['responsable'] = $localConnection->goQuery();

        if ($object['empleados']['responsable'] == null) {

            $object['empleados']['responsable'] = "";
        }



        $localConnection = new LocalDB($sql['orden_empleados']['diseno']);

        $object['empleados']['diseno'] = $localConnection->goQuery();

        if ($object['empleados']['diseno'] == null) {

            $object['empleados']['diseno'] = "";
        }



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // BUSCAR ORDEN PPARA EL ABONO

    $app->get('/ordenes/abono/{id}', function (Request $request, Response $response, array $args) {

        $id = $args["id"];



        //  Verificar existencia de la orden

        $sql = "SELECT a.id_orden, SUM(a.abono) abono, SUM(a.descuento) descuento, b.pago_total total, a.moment  FROM abonos a JOIN ordenes b ON a.id_orden = b._id WHERE a.id_orden = " . $args["id"];

        // $sql             = "SELECT _id, pago_total, pago_abono FROM ordenes WHERE _id=" . $args["id"];

        $localConnection = new LocalDB($sql);

        $datosAbono = $localConnection->goQuery();

        $object['data'] = $datosAbono[0];



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // PASARELAS DE PAGO

    $app->get('/metodos-de-pago', function (Request $request, Response $response, array $args) {

        $woo = new WooMe();

        // $object['data'] = json_decode($woo->getPG());

        $object['data'] = $woo->getPG();

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // VERIFICAR SI LA ORDEN SE PUEDE EDITAR DESDE COMERCIALIZACION

    $app->get('/ordenes/verificar-edición/{id}', function (Request $request, Response $response, array $args) {

        $id = $args["id"];



        $sql = "SELECT paso  FROM lotes WHERE id_orden = " . $args["id"];

        $localConnection = new LocalDB($sql);

        $datosAbono = $localConnection->goQuery();

        $object = $datosAbono[0];



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    $app->post("/orden/abono", function (Request $request, Response $response, $args) {
        $datosAbono = $request->getParsedBody();
        // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO 
        $sql = "SELECT responsable FROM ordenes WHERE _id = " . $datosAbono["id"];
        $localConnection = new LocalDB($sql);
        $id_vendedor = $localConnection->goQuery()[0]['responsable'];

        $sql = "SELECT abono FROM ordenes WHERE _id = " . $datosAbono["id"];
        $localConnection = new LocalDB($sql);
        $primerAbono = $localConnection->goQuery();
        $totalAbono = floatval($primerAbono) + floatval($datosAbono["abono"]);

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $values = "'" . $now . "',";
        $values .= "'" . $datosAbono["id"] . "',";
        $values .= "'" . $totalAbono . "',";
        $values .= "'" . $datosAbono["descuento"] . "',";
        $values .= "'" . $datosAbono["empleado"] . "'";

        $sql = "INSERT INTO abonos(moment, id_orden, abono, descuento, id_empleado) VALUES (" . $values . ")";
        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = "";
        if (intval($datosAbono["montoDolaresEfectivo"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Dólares', 'Efectivo', '" . $datosAbono["montoDolaresEfectivo"] . "', '1');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono["montoDolaresEfectivo"] . "', 'Dólares', 1, '" . $datosAbono["tipoAbono"] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono["montoDolaresZelle"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Dólares', 'Zelle', '" . $datosAbono["montoDolaresZelle"] . "', '1');";
        }

        if (intval($datosAbono["montoDolaresPanama"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Dólares', 'Panamá', '" . $datosAbono["montoDolaresPanama"] . "', '1');";
        }

        if (intval($datosAbono["montoPesosEfectivo"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Pesos', 'Efectivo', '" . $datosAbono["montoPesosEfectivo"] . "', '" . $datosAbono["tasa_peso"] . "');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono["montoPesosEfectivo"] . "', 'Pesos', '" . $datosAbono["tasa_peso"] . "', '" . $datosAbono["tipoAbono"] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono["montoPesosTransferencia"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Pesos', 'Transferencia', '" . $datosAbono["montoPesosTransferencia"] . "', '" . $datosAbono["tasa_peso"] . "');";
        }

        if (intval($datosAbono["montoBolivaresEfectivo"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Bolívares', 'Efectivo', '" . $datosAbono["montoBolivaresEfectivo"] . "', '" . $datosAbono["tasa_dolar"] . "');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono["montoBolivaresEfectivo"] . "', 'Bolívares', '" . $datosAbono["tasa_dolar"] . "', '" . $datosAbono["tipoAbono"] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono["montoBolivaresPunto"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Bolívares', 'Punto', '" . $datosAbono["montoBolivaresPunto"] . "', '" . $datosAbono["tasa_dolar"] . "');";
        }

        if (intval($datosAbono["montoBolivaresPagomovil"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Bolívares', 'Pagomovil', '" . $datosAbono["montoBolivaresPagomovil"] . "', '" . $datosAbono["tasa_dolar"] . "');";
        }

        if (intval($datosAbono["montoBolivaresTransferencia"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono["tipoAbono"] . "', '" . $datosAbono["id"] . "', 'Bolívares', 'Transferencia', '" . $datosAbono["montoBolivaresTransferencia"] . "', '" . $datosAbono["tasa_dolar"] . "');";
        }

        $object["SQL_METODOS_PAGO"] = $sql_metodos_pago;
        $localConnectionZ = new localDB($sql_metodos_pago);
        $object['metodos_pago'] = $localConnectionZ->goQuery();

        // OBTENER ULTIMO DE LA TABLA metodos_de_pago
        $sql_max_id = "SELECT MAX(_id) last_id FROM metodos_de_pago";
        $localConnection = new localDB($sql_max_id);
        $last_id_pago = $localConnection->goQuery()[0]["last_id"];

        // GUARDAR PAGO 
        $comision_vendedor = number_format((floatval($datosAbono["abono"]) * 5 / 100), 2);
        // $sql = "INSERT INTO pagos SET detalle = 'Comercialización', estatus = 'aprobado', monto_pago = " . $comision_vendedor . ", id_empleado = " . $id_vendedor . ", id_orden = " . $datosAbono["id"] . ", id_metodos_de_pago = " . $last_id_pago;
        $sql = "INSERT INTO pagos (detalle, estatus, monto_pago, id_empleado, id_orden, id_metodos_de_pago) VALUES ('Comercialización', 'aprobado', " . $comision_vendedor . ", " . $id_vendedor . ", " . $datosAbono["id"] . ", " . $last_id_pago . ")";

        $localConnection = new LocalDB($sql);
        $object["response_SET_pago"] = $localConnection->goQuery();

        $object["sql_insert_pago"] = $sql;

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR OBSERVACIONES DESDE EDITAR EN COMERCIALIZACION
    $app->post("/orden/edit/obs", function (Request $request, Response $response, $args) {
        $datosObs = $request->getParsedBody();

        $sql = "UPDATE ordenes SET observaciones = '" . $datosObs["obs"] . "'  WHERE _id = " . $datosObs["id"];
        $data["sql"] = $sql;
        $localConnection = new LocalDB($sql);
        $data = $localConnection->goQuery();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // BUSCAR DETALLES DEL ABONO
    $app->get('/ordenes/abono-detale/{id}', function (Request $request, Response $response, array $args) {
        $id = $args["id"];

        $object['fields'][0]['key'] = "moment";
        $object['fields'][0]['label'] = "Fecha y hora";
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = "abono";
        $object['fields'][1]['label'] = "Abono";
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = "descuento";
        $object['fields'][2]['label'] = "Descuento";
        $object['fields'][2]['sortable'] = true;
        //  Verificar existencia de la orden
        $sql = "SELECT a.abono abono, a.descuento, a.moment FROM abonos a  WHERE a.id_orden = " . $args["id"];
        $object["sql"] = $sql;
        $localConnection = new LocalDB($sql);
        $datosAbono = $localConnection->goQuery();
        $object['items'] = $datosAbono;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // REPORTE PAGOS DE EMPLEADOS

    $app->get('/reportes/resumen/empleados/{id_empleado}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT departamento FROM empleados WHERE _id = " . $args["id_empleado"];
        $localConnection = new LocalDB($sql);
        $departamento = $localConnection->goQuery()[0]["departamento"];
        $object["departamento"] = $departamento;

        if ($departamento === "Corte" || $departamento === "Costura" || $departamento === "Estampado" || $departamento === "Limpieza" || $departamento === "Revisión" || $departamento === "Impresión") {
            $sql = "SELECT 
                a._id id_lotes_detalles, 
                a.id_orden, 
                a.id_orden detalle,     
                DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
                DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
                DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
                DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,    
                SUBSTR(SEC_TO_TIME(TIME_TO_SEC(TIMEDIFF(fecha_terminado, fecha_inicio))), 4, 5) tiempo_transcurrido,
                a.unidades_solicitadas, 
                b.name producto,
                (a.unidades_solicitadas * c.comision) calculo_pago
            FROM lotes_detalles a 
            JOIN empleados c ON a.id_empleado = c._id
            JOIN ordenes_productos b 
                ON a.id_ordenes_productos = b._id 
            WHERE a.id_empleado = " . $args["id_empleado"] . " 
        AND YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC";

            $localConnection = new LocalDB($sql);
            $ordenes = $localConnection->goQuery();
            $object["ordenes"] = $ordenes;
        }

        if ($departamento === "Comercialización" || $departamento === "Administración") {
            $sql = "SELECT 
                a.id_orden,
                DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
                b.tipo_de_pago,
                a.monto_pago calculo_pago 
            FROM pagos a 
            LEFT JOIN metodos_de_pago b ON b._id = a.id_metodos_de_pago 
            WHERE a.id_empleado = " . $args["id_empleado"] . " AND a.fecha_pago IS NULL";

            $object["sql_commerce"] = $sql;
            $localConnection = new LocalDB($sql);
            $ordenes = $localConnection->goQuery();
            $object["ordenes"] = $ordenes;
        }


        if ($departamento === "Diseño") {
            $sql = "SELECT 
                a._id disenos, 
                b.id_woo,
                a.id_orden, 
                b.cantidad unidades_solicitadas, 
                d.estatus,
                b.name producto,
                b.category_name                
            FROM disenos a 
            JOIN revisiones d ON a._id = d.id_diseno
            JOIN empleados c ON a.id_empleado = c._id
            JOIN ordenes_productos b 
                ON a.id_orden = b.id_orden AND b.category_name = 'Diseños'
            WHERE a.id_empleado = " . $args["id_empleado"] . " 
        AND YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC";

            $localConnection = new LocalDB($sql);
            $ordenes = $localConnection->goQuery();

            // Buscar monto de la comusión del producto en Woocommerce
            $woo = new WooMe();
            foreach ($ordenes as $key => $orden) {
                $woomeResponse = $woo->getProductById($orden["id_woo"]);
                $ordenes[$key]["calculo_pago"] = $woomeResponse->attributes[0]->options[0];
            }
            $object["ordenes"] = $ordenes;
        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });


    // REPORTE SEMANAL DE PAGOS Y ABONOS

    $app->get('/comercializacion/reportes/pagos-abonos', function (Request $request, Response $response, array $args) {

        $object['fields'][0]['key'] = "id_orden";

        $object['fields'][0]['label'] = "Orden";

        // $object['fields'][0]['sortable'] = true;



        $object['fields'][1]['key'] = "moment";

        $object['fields'][1]['label'] = "Fecha y hora";

        // $object['fields'][0]['sortable'] = true;



        $object['fields'][2]['key'] = "abono";

        $object['fields'][2]['label'] = "Abono";

        // $object['fields'][1]['sortable'] = true;



        $object['fields'][3]['key'] = "descuento";

        $object['fields'][3]['label'] = "Descuento";

        // $object['fields'][2]['sortable'] = true;



        $sql = "SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a  WHERE YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC";

        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $datosAbono = $localConnection->goQuery();

        $object['items'] = $datosAbono;



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // BUSCAR ORDEN POR UD

    $app->get('/ordenes/reporte/{id}', function (Request $request, Response $response, array $args) {

        $id = $args["id"];



        //  Verificar existencia de la orden

        $sql = "SELECT _id FROM ordenes WHERE _id=" . $args["id"];

        $localConnection = new LocalDB($sql);

        $resp = $localConnection->goQuery();



        if (!$resp) {

            $object = $resp;
        } else {

            // Buscar datos del cliente en Woocommerce ...

            $sql = "SELECT id_wp FROM ordenes WHERE _id  = " . $id;

            $localConnection = new LocalDB($sql);

            $id_wp = $localConnection->goQuery();

            $id_customer = $id_wp[0]["id_wp"];



            $woo = new WooMe();

            $object = array();



            // Buscar datos de la orden

            $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono FROM ordenes a  WHERE _id =  " . $id;

            $localConnection = new LocalDB($sql);

            $object['orden'] = $localConnection->goQuery();



            // Buscar datos del diseño

            $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;

            $localConnection = new LocalDB($sql);

            $object['diseno'] = $localConnection->goQuery();



            // Buscar datos del cliente

            $object["customer"][0] = $woo->getCustomerById($id_customer);



            // Buscar datos de productos

            $sql = "SELECT _id, name, id_woo cod, cantidad, talla, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = " . $id;

            $localConnection = new LocalDB($sql);

            $object['productos'] = $localConnection->goQuery();
        }



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // BUSCAR ORDEN POR UD

    $app->get('/buscar/{id}', function (Request $request, Response $response, array $args) {

        $id = $args["id"];

        $object = array();



        //  Verificar existencia de la orden

        $sql = "SELECT _id FROM ordenes WHERE _id=" . $id;

        $localConnection = new LocalDB($sql);

        $resp = $localConnection->goQuery();



        if (!$resp) {

            $object = $resp;
        } else {

            // Buscar datos del cliente en Woocommerce ...

            $sql = "SELECT id_wp FROM ordenes WHERE _id  = " . $id;

            $localConnection = new LocalDB($sql);

            $id_wp = $localConnection->goQuery();

            $id_customer = $id_wp[0]["id_wp"];



            $object["id_customer"] = $id_customer;



            $woo = new WooMe();

            // Buscar datos del cliente

            // $object["customer"][0] = $woo->getCustomerById($id_customer);

            $data = $woo->getCustomerById($id_customer);

            $customer = json_decode(json_encode($data), true);



            $object["customer"]["nombre"] = $customer["first_name"] . " " . $customer["last_name"];

            $object["customer"]["direccion"] = $customer["billing"]["address_1"];

            $object["customer"]["email"] = $customer["billing"]["email"];

            $object["customer"]["cedula"] = $customer["billing"]["postcode"];

            $object["customer"]["telefono"] = $customer["billing"]["phone"];



            // Buscar datos de la orden

            $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono, a.pago_descuento FROM ordenes a  WHERE _id =  " . $id;

            $localConnection = new LocalDB($sql);

            $object["orden"] = $localConnection->goQuery();



            // Buscar datos del diseño

            $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;

            $localConnection = new LocalDB($sql);

            $object['diseno'] = $localConnection->goQuery();

            if (empty($object['diseno'])) {
                $object['diseno'][]['tipo'] = "Ninguno";
            }



            // Buscar datos de productos

            $sql = "SELECT _id, name, id_woo cod, cantidad, talla, tela, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = " . $id;

            $localConnection = new LocalDB($sql);

            $object['productos'] = $localConnection->goQuery();
        }



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // ORDENES ACTIVAS, TERMINADAS Y PAUSADAS

    $app->get('/comercializacion/ordenes/reporte', function (Request $request, Response $response, array $args) {

        // BUSCAR ORENES EN CURSO

        $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'activa' OR status = 'pausada' OR status = 'En espera' OR status = 'terminada'  ORDER BY _id DESC";

        $object["sql1"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        $sql = "SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC";

        $object["sql2"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["vinculadas"] = $localConnection->goQuery();



        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = "_id";

        $object['fields'][0]['label'] = "Orden";



        $object['fields'][1]['key'] = "cliente_nombre";

        $object['fields'][1]['label'] = "Cliente";



        $object['fields'][2]['key'] = "status";

        $object['fields'][2]['label'] = "Status";



        $object['fields'][3]['key'] = "vinculada";

        $object['fields'][3]['label'] = "Vinculadas";



        // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));

        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // ORDENES TERMNADAS Y NO ENTREGADAS

    $app->get('/comercializacion/ordenes/reporte/terminadas/{rango}', function (Request $request, Response $response, array $args) {

        $object["rango"] = $args["rango"];



        // PREPARAR FECHAS

        $myDate = new CustomTime($args["rango"]);

        $now = $myDate->today();

        $before = $myDate->before();

        $object["moment-today"] = $now;

        $object["moment-before"] = $before;

        $momentInit = $now;

        $momentEnd = $before;



        // BUSCAR ORENES EN CURSO

        $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'terminada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";

        $object["sql1"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        $sql = "SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC";

        $object["sql2"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["vinculadas"] = $localConnection->goQuery();



        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = "_id";

        $object['fields'][0]['label'] = "Orden";



        $object['fields'][1]['key'] = "cliente_nombre";

        $object['fields'][1]['label'] = "Cliente";



        $object['fields'][2]['key'] = "status";

        $object['fields'][2]['label'] = "Status";



        $object['fields'][3]['key'] = "vinculada";

        $object['fields'][3]['label'] = "Vinculadas";



        // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));

        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // ORDENES ENTREGADAS

    $app->get('/comercializacion/ordenes/reporte/entregadas/{rango}', function (Request $request, Response $response, array $args) {
        $object["rango"] = $args["rango"];

        // PREPARAR FECHAS
        $myDate = new CustomTime($args["rango"]);
        $now = $myDate->today();
        $before = $myDate->before();
        $object["moment-today"] = $now;
        $object["moment-before"] = $before;
        $momentInit = $now;
        $momentEnd = $before;

        // BUSCAR ORENES EN CURSO
        // $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'entregada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
        $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes ORDER BY _id ASC";
        $object["sql1"] = $sql;

        $localConnection = new LocalDB($sql);
        $object["items"] = $localConnection->goQuery();

        $sql = "SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC";
        $object["sql2"] = $sql;

        $localConnection = new LocalDB($sql);
        $object["vinculadas"] = $localConnection->goQuery();

        // CREAR CAMPOS DE LA TABLA
        $object['fields'][0]['key'] = "_id";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente_nombre";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "status";
        $object['fields'][2]['label'] = "Status";

        $object['fields'][3]['key'] = "vinculada";
        $object['fields'][3]['label'] = "Vinculadas";

        // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));
        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CREAR NUEVA ORDEN
    $app->post("/ordenes/nueva", function (Request $request, Response $response, $arg) {
        $newJson = $request->getParsedBody();
        $misProductos = json_decode($newJson['productos'], true);
        // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
        $count = count($misProductos);

        $arr["id_wp"] = json_decode($newJson['id']);
        $arr["nombre"] = json_decode($newJson['nombre']);
        $arr["vinculada"] = json_decode($newJson['vinculada']);
        $arr["apellido"] = json_decode($newJson['apellido']);
        $arr["cedula"] = json_decode($newJson['cedula']);
        $arr["telefono"] = json_decode($newJson['telefono']);
        $arr["email"] = json_decode($newJson['email']);
        $arr["direccion"] = json_decode($newJson['direccion']);
        $arr["fechaEntrega"] = json_decode($newJson['fechaEntrega']);
        $arr["misProductos"] = json_decode($newJson['productos']);
        $arr["obs"] = json_decode($newJson['obs']);
        $arr["total"] = json_decode($newJson['total']);
        $arr["abono"] = json_decode($newJson['abono']);
        $arr["descuento"] = json_decode($newJson['descuento']);
        $arr["descuentoDetalle"] = json_decode($newJson['descuentoDetalle']);
        $arr["diseno_grafico"] = json_decode($newJson['diseno_grafico']);
        $arr["diseno_modas"] = json_decode($newJson['diseno_modas']);
        $arr["responsable"] = json_decode($newJson['responsable']);

        // RECIBIR LOS METODOS DE PAGO
        $arr["montoDolaresEfectivo"] = json_decode($newJson['montoDolaresEfectivo']);
        $arr["montoDolaresEfectivoDetalle"] = json_decode($newJson['montoDolaresEfectivoDetalle']);
        $arr["montoDolaresZelle"] = json_decode($newJson['montoDolaresZelle']);
        $arr["montoDolaresZelleDetalle"] = json_decode($newJson['montoDolaresZelleDetalle']);
        $arr["montoDolaresPanama"] = json_decode($newJson['montoDolaresPanama']);
        $arr["montoDolaresPanamaDetalle"] = json_decode($newJson['montoDolaresPanamaDetalle']);
        $arr["montoPesosEfectivo"] = json_decode($newJson['montoPesosEfectivo']);
        $arr["montoPesosEfectivoDetalle"] = json_decode($newJson['montoPesosEfectivoDetalle']);
        $arr["montoPesosTransferencia"] = json_decode($newJson['montoPesosTransferencia']);
        $arr["montoPesosTransferenciaDetalle"] = json_decode($newJson['montoPesosTransferenciaDetalle']);
        $arr["montoBolivaresEfectivo"] = json_decode($newJson['montoBolivaresEfectivo']);
        $arr["montoBolivaresEfectivoDetalle"] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
        $arr["montoBolivaresPunto"] = json_decode($newJson['montoBolivaresPunto']);
        $arr["montoBolivaresPuntoDetalle"] = json_decode($newJson['montoBolivaresPuntoDetalle']);
        $arr["montoBolivaresPagomovil"] = json_decode($newJson['montoBolivaresPagomovil']);
        $arr["montoBolivaresPagomovilDetalle"] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
        $arr["montoBolivaresTransferencia"] = json_decode($newJson['montoBolivaresTransferencia']);
        $arr["montoBolivaresTransferenciaDetalle"] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
        $arr["tasa_dolar"] = json_decode($newJson['tasa_dolar']);
        $arr["tasa_peso"] = json_decode($newJson['tasa_peso']);

        $arr["hoy"] = date("d/m/Y");
        $object["arr"] = $arr;
        $cliente = $newJson['nombre'] . " " . $newJson['apellido'];

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $sql = "INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (" . $newJson['responsable'] . ", '" . $now . "', " . $arr["descuento"] . ", " . $arr["abono"] . ",  '" . $arr["id_wp"] . "', '" . $arr["cedula"] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date("Y-m-d") . "', '" . $newJson['fechaEntrega'] . "', '" . date("Y-m-d") . "', 'En espera' )";

        $object["sql_nueva_orden"] = $sql;
        $localConnection = new LocalDB($sql);
        $object['nueva_oreden_response'] = json_encode($localConnection->goQuery());

        // Obtenr id de la orden creada
        $localConnection = new LocalDB("SELECT MAX(_id) id FROM ordenes");
        $last = $localConnection->goQuery();
        $last_id = intval($last[0]['id']);

        // Guardar orden vinculada
        $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr["vinculada"] . ", " . $last_id . ")";
        $object["sql_orden_vinculada"] = $sql;
        $localConnection = new LocalDB($sql);
        $object['response_orden_vinculada'] = json_encode($localConnection->goQuery());

        // Crear abono inicial de la orden
        $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson["abono"] . "', '" . $newJson['descuento'] . "');";

        // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
        $pago_vendedor = floatval($newJson["abono"]) * 5 / 100;
        $pago_vendedor = number_format($pago_vendedor, 2);
        $sql .= "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
        $object["sql_nuevo_abono_Y_PAGO_A_VENDEDOR"] = $sql;
        $localConnection = new LocalDB($sql);
        $object['resultado_abono'] = json_encode($localConnection->goQuery());

        // GUARDAR DATOS DE DISEÑO
        $sql_diseno = "";
        if ($newJson["diseno_grafico"] === "true") {
            for ($i = 0; $i < intval($newJson["diseno_grafico_cantidad"]); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
            }
        }

        if ($newJson["diseno_modas"] === "true") {
            for ($i = 0; $i < intval($newJson["diseno_modas_cantidad"]); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
            }
        }

        $guardarDiseno = new LocalDB($sql_diseno);
        $object['miDiseno'] = json_encode($guardarDiseno->goQuery());

        // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
        $sql = "SELECT _id";

        for ($i = 0; $i <= $count; $i++) {
            if (isset($misProductos[$i])) {
                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $decodedObj = $misProductos[$i];

                $woo = new WooMe();
                $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                $tmp = json_decode($data_category);
                $cat_name = $tmp->name;


                $values = "'" . $now . "',";
                $values .= $decodedObj['precio'] . ",";
                $values .= "'" . $decodedObj['precioWoo'] . "',";
                $values .= "'" . $decodedObj['producto'] . "',";
                $values .= $last_id . ",";
                $values .= $decodedObj['cod'] . ",";
                $values .= $decodedObj['cantidad'] . ",";
                $values .= $decodedObj['categoria'] . ",";
                $values .= "'" . $cat_name . "',";
                // $values .= "'" . $tmp["->name"] . "',";

                if (isset($decodedObj['talla'])) {
                    $values .= "'" . $decodedObj['talla'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['corte'])) {
                    $values .= "'" . $decodedObj['corte'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['tela'])) {
                    $values .= "'" . $decodedObj['tela'] . "'";
                } else {
                    $values .= "''";
                }

                $sql2 = "INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (" . $values . ")";
                $object['sql_ordenes_productos'] = $sql2;
                $localConnection2 = new LocalDB($sql2);
                $object["producto_detalle"][] = $localConnection2->goQuery();

                // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
                if ($misProductos[$i] != '') {
                    $sql_order = "SELECT * FROM ordenes WHERE _id = " . $last_id;
                    $localConnectionX = new localDB($sql_order);
                    $myOrder = $localConnectionX->goQuery();
                    $object['myOrder_sql'] = $sql_order;
                    $object['myOrder'] = $myOrder;

                    // Obtenr ultimo ID del producto creado
                    $localConnection_3 = new LocalDB("SELECT MAX(_id) id FROM ordenes_productos");
                    $last_prod = $localConnection_3->goQuery();
                    $last_id_ordenes_productos = intval($last_prod[0]['id']);

                    // PREPARAR FECHAS
                    $myDate = new CustomTime();
                    $now = $myDate->today();

                    // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
                    $myWooId = intval($decodedObj['cod']);
                    if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
                        $sql_lote_detalles = "";

                        // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";

                        // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";

                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";

                        $localConnectionX = new localDB($sql_lote_detalles);
                        $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
                        $object['lote_detalles'][$i] = $localConnectionX->goQuery();
                    }
                }
            }
        }

        // GUARDAR LOTE

        // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
        $sql_verify = "SELECT category_name FROM ordenes_productos WHERE id_orden = " . $last_id;
        $ConnectionVerify = new localDB($sql_verify);
        $resultVerify = $ConnectionVerify->goQuery();

        $guardarLote = true;
        if (!empty($resultVerify)) {
            // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
            if (count($resultVerify) === 1 && $resultVerify[0]["category_name"] === "Diseños") {
                $guardarLote = false;
            }
        }


        $object["guardar_en_lote"] = $guardarLote;

        if ($guardarLote) {
            $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date("Y-m-d") . "', " . $last_id . ", " . $last_id . ", 'producción')";
            $guardarLote = new LocalDB($sql_lote);
            $object['miLote'] = json_encode($guardarLote->goQuery());
        }

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = "";

        if (intval($arr["montoDolaresEfectivo"]) > 0) {

            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr["montoDolaresEfectivo"] . "', '1', '" . $newJson["montoDolaresEfectivoDetalle"] . "');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr["montoDolaresEfectivo"] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', '" . $newJson["montoDolaresEfectivoDetalle"] . "');";
        }

        if (intval($arr["montoDolaresZelle"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr["montoDolaresZelle"] . "', '1', '" . $newJson["montoDolaresZelleDetalle"] . "');";
        }

        if (intval($arr["montoDolaresPanama"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr["montoDolaresPanama"] . "', '1', '" . $newJson["montoDolaresPanamaDetalle"] . "');";
        }

        if (intval($arr["montoPesosEfectivo"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr["montoPesosEfectivo"] . "', '" . $arr["tasa_peso"] . "', '" . $newJson["montoPesosEfectivoDetalle"] . "');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr["montoPesosEfectivo"] . "', 'Pesos', '" . $arr["tasa_peso"] . "', 'orden_nueva', '" . $newJson['responsable'] . "', '" . $newJson["montoPesosEfectivoDetalle"] . "');";
        }

        if (intval($arr["montoPesosTransferencia"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr["montoPesosTransferencia"] . "', '" . $arr["tasa_peso"] . "', '" . $newJson["montoPesosTransferenciaDetalle"] . "');";
        }

        if (intval($arr["montoBolivaresEfectivo"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr["montoBolivaresEfectivo"] . "', '" . $arr["tasa_dolar"] . "', '" . $newJson["montoBolivaresEfectivoDetalle"] . "');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr["montoBolivaresEfectivo"] . "', 'Bolívares', '" . $arr["tasa_dolar"] . "', 'orden_nueva', '" . $newJson['responsable'] . "', '" . $newJson["montoBolivaresEfectivoDetalle"] . "');";
        }

        if (intval($arr["montoBolivaresPunto"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr["montoBolivaresPunto"] . "', '" . $arr["tasa_dolar"] . "', '" . $newJson["montoBolivaresPuntoDetalle"] . "');";
        }

        if (intval($arr["montoBolivaresPagomovil"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr["montoBolivaresPagomovil"] . "', '" . $arr["tasa_dolar"] . "', '" . $newJson["montoBolivaresPagomovilDetalle"] . "');";
        }

        if (intval($arr["montoBolivaresTransferencia"]) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr["montoBolivaresTransferencia"] . "', '" . $arr["tasa_dolar"] . "', '" . $newJson["montoBolivaresTransferenciaDetalle"] . "');";
        }

        $object["SQL_METODOS_PAGO"] = $sql_metodos_pago;
        $localConnectionZ = new localDB($sql_metodos_pago);
        $object['metodos_pago'][$i] = $localConnectionZ->goQuery();
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // FIN CREAR NUEVA ORDEN 

    // CAMBIAR ESTATUS DE LA REVISIÓN
    $app->post('/comercializacion/revisiones-estatus/{estatus}/{id_revision}', function (Request $request, Response $response, array $args) {
        $sql = "UPDATE revisiones SET estatus = '" . $args["estatus"] . "' WHERE _id = " . $args["id_revision"];
        $object["sql"] = $sql;
        $localConnection = new localDB($sql);
        $object['revisiones'] = $localConnection->goQuery();

        // BUSCAR EL ID DE LA ORDEN EN `revisiones`
        $sql = "SELECT id_orden FROM revisiones WHERE _id = " . $args["id_revision"];
        $localConnection = new localDB($sql);
        $miRevision = $localConnection->goQuery();

        // CON EL ID DE LA ORDEN BUSCAMOS EL ID DEL DISEÑADOR EN `disenos` `revisiones`
        $sql = "SELECT id_orden, id_empleado FROM disenos WHERE id_orden = " . $miRevision[0]["id_orden"];
        $localConnection = new localDB($sql);
        $miDiseno = $localConnection->goQuery();

        # VERIFICAR PAGO EXISTENTE
        $sqlPago = "SELECT count(_id) exist FROM pagos WHERE detalle = 'Diseño' AND id_orden = " . $miDiseno[0]["id_orden"] . " AND id_empleado = " . $miDiseno[0]["id_empleado"];
        $localConnection = new localDB($sqlPago);
        $object["pago_exist"] = $localConnection->goQuery()[0];

        // ELIMINAR PAGO A DISEñADOR POR RECHAZO DE PROPUESTA
        if ($args["estatus"] === "Rechazado") {
            $estatusTerminado = 0;
            $sql = "DELETE from pagos WHERE id_empleado = " . $miDiseno[0]["id_empleado"] . " AND id_orden = " . $miDiseno[0]["id_orden"];
            $localConnection = new localDB($sql);
            $miRevision = $localConnection->goQuery();
        }

        // GENERAR PAGO A DISEÑAODRES
        if ($args["estatus"] === "Aprobado") {
            $estatusTerminado = 1;
            $sql = "SELECT _id FROM pagos WHERE detalle = 'Diseño' AND id_empleado = " . $miDiseno[0]["id_empleado"] . " AND id_orden = " . $miRevision[0]["id_orden"];
            $localConnection = new localDB($sql);
            $miPago = $localConnection->goQuery();

            # Buscar el id_woo
            $sqlwoo = "SELECT id_woo FROM ordenes_productos WHERE category_name = 'Diseños' AND id_orden = " . $miDiseno[0]["id_orden"];
            $localConnection = new localDB($sqlwoo);
            $idWoo = $localConnection->goQuery();
            $object["id_woo"] = $idWoo[0]["id_woo"];

            # Buscar en WooMe la comision asociada a el producto $idWoo
            $woo = new WooMe();
            $woomeResponse = $woo->getProductById($idWoo[0]["id_woo"]);

            if (empty($woomeResponse->attributes)) {
                $comision = 0;
            } else {
                $comision = $woomeResponse->attributes[0]->options[0];
            }

            if (empty($miPago)) {
                $sqlPago = "INSERT INTO pagos (cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, " . $miRevision[0]["id_orden"] . ", 'aprobado' , " . $comision . ", " . $miDiseno[0]["id_empleado"] . ", 'Diseño');";
                $localConnection = new localDB($sqlPago);
                $object["resultInsertPago"] = $localConnection->goQuery();
                $object["sql_insert_pago_diseñador"] = $sqlPago;
            } else {
                # UPDATE pagos
                $sqlPago = "UPDATE pagos SET monto_pago = " . $comision . " WHERE id_orden = " . $miRevision[0]["id_orden"] . " AND id_empleado = " . $miDiseno[0]["id_empleado"];
                $localConnection = new localDB($sqlPago);
                $object["resultInsertPago"] = $localConnection->goQuery();

            }
            $object["sql_pago"] = $sqlPago;

            // ejecutar consulta aqoui!!!!
        }

        $sql = "UPDATE disenos SET terminado = " . $estatusTerminado . " WHEN id_orden = " . $miDiseno[0]["id_orden"];
        $localConnection = new localDB($sql);
        $miRevision = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR DETALLES DE LA REVISIÓN
    $app->post('/comercializacion/revisiones-detalles/{id_revision}', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();

        $sql = "UPDATE revisiones SET detalles = '" . htmlspecialchars($data["detalles"]) . "' WHERE _id = " . $args["id_revision"];
        $object["sql"] = $sql;
        $localConnection = new localDB($sql);
        $object['revisiones'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REVISAR REVISIONES PENDIENTES
    $app->get('/comercializacion/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT acceso FROM  empleados  WHERE _id = " . $args["id_empleado"];
        $object["sql_acceso"] = $sql;
        $localConnection = new localDB($sql);
        $miEmpleado = $localConnection->goQuery();

        if ($miEmpleado[0]["acceso"]) {
            # Mostrar todos los registros de revisiones
            $sql = "SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
        } else {
            # Mostrar solo los registros del venededor
            $sql = "SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id AND b.responsable = " . $args["id_empleado"] . " WHERE b.responsable = '" . $args["id_empleado"] . "' AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
        }


        $object["sql"] = $sql;
        $localConnection = new localDB($sql);
        $object['revisiones'] = $localConnection->goQuery();

        $object["total_revisiones"] = count($object["revisiones"]);

        // Datos del cliente
        // $woo      = new WooMe();
        // $object["cliente"] = $woo->getCustomerById($args["_empleado"]);

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * FIN ORDENES
     */



    /** LOTES */

    // Obtener departamento asignado al empleado

    $app->get('/empleado/asignado/{departamento}/{orden}/{item_id}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";



        // $sql = "SELECT dep_" . $args["departamento"] . " dep FROM ordenes WHERE _id = " . $args["orden"];



        // $object['sql']   = $sql;

        // $localConnection = new LocalDB($sql);

        // $tmpEmpleado     = $localConnection->goQuery();



        // $miEmpleado = $tmpEmpleado[0]["dep"];



        // Verificar la asignacion

        $sql = "SELECT id_empleado FROM lotes_detalles  WHERE id_orden = '" . $args["orden"] . "' AND id_ordenes_productos = '" . $args["item_id"] . "' AND departamento = '" . $args["departamento"] . "'";



        // $object['sql2']        = $sql;

        $localConnection = new LocalDB($sql);

        $object["id_empleado"] = $localConnection->goQuery();



        // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));

        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    $app->get('/lotes/en-proceso', function (Request $request, Response $response, array $args) {

        // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE

        $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = "orden";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "prioridad";
        $object['fields'][2]['label'] = "Prioridad";

        $object['fields'][2]['key'] = "paso";
        $object['fields'][2]['label'] = "Progreso";

        $object['fields'][3]['key'] = "inicio";
        $object['fields'][3]['label'] = "Inicio";

        $object['fields'][4]['key'] = "entrega";
        $object['fields'][4]['label'] = "Entrega";

        $object['fields'][5]['key'] = "vinculada";
        $object['fields'][5]['label'] = "Vinculada";

        $object['fields'][6]['key'] = "estatus";
        $object['fields'][6]['label'] = "Estatus";

        $object['fields'][7]['key'] = "detalles";
        $object['fields'][7]['label'] = "Detalles";

        $object['fields'][8]['key'] = "acciones";
        $object['fields'][8]['label'] = "Acciones";

        $go = $object;

        $response->getBody()->write(json_encode($go));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // VERIFICAR CANTIDAD ASIGNADA EN LOTES
    $app->get('/lotes/verificar-cantidad-asignada/{id_ordenes_productos}/{departamento}/{id_orden}', function (Request $request, Response $response, array $args) {

        // -> VERIFICAR EXISTENCIA DEL REGISTRO
        $sql = "SELECT _id FROM lotes_detalles WHERE id_ordenes_productos  = '" . $args["id_ordenes_productos"] . "' AND departamento = '" . $args['departamento'] . "' AND id_orden = " . $args['id_orden'];
        $object["sql_verificar_emp"] = $sql;
        $object["sql1"] = $sql;
        // $localConnection             = new LocalDB($sql);
        //$id_lote_detalles = $localConnection->goQuery()[0]["_id"];

        // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE

        /*  $sql             = "SELECT unidades_solicitadas FROM lotes_detalles WHERE _id = " . $id_lote_detalles;
        $object["sql"] = $sql;
        $localConnection = new LocalDB($sql);
        $object["items"] = $localConnection->goQuery(); */

        $go = $object;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    $app->post("/lotes/empleados/reasignar", function (Request $request, Response $response, $args) {
        $miEmpleado = $request->getParsedBody();
        $object['parsed_body'] = $miEmpleado;

        // ACTUALIZAR LOTES_DETALLES

        // -> VERIFICAR EXISTENCIA DEL REGISTRO
        $sql = "SELECT _id, unidades_solicitadas FROM lotes_detalles WHERE id_ordenes_productos  = " . $miEmpleado["id_ordenes_productos"] . " AND departamento = '" . $miEmpleado['departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'];
        $object["sql_verificar_emp"] = $sql;
        $localConnection = new LocalDB($sql);
        $exist = $localConnection->goQuery();

        $object["count"] = count($exist);

        if ($object["count"]) {
            $nuevaCantiadSolicitada = intval($miEmpleado['cantidad']) + intval($exist[0]["unidades_solicitadas"]);

            if ($miEmpleado["departamento"] === "Corte") {
                $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
                $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
                $values .= "unidades_solicitadas ='" . $nuevaCantiadSolicitada . "'";
            } else {
                $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
                $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
                $values .= "unidades_solicitadas ='" . $miEmpleado['cantidad_orden'] . "'";
            }

            $sql = "UPDATE lotes_detalles SET " . $values . " WHERE departamento = '" . $miEmpleado['departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'] . " AND id_ordenes_productos = " . $miEmpleado["id_ordenes_productos"];
        } else {
            // TODO Verificar si ya hay una asignacion para hacer un `UPDATE` de lo contrario hacer un `INSERT`
            $sql = "SELECT _id FROM lotes_detalles WHERE id_orden = " . $miEmpleado["id_orden"] . " AND id_ordenes_productos = " . $miEmpleado["id_ordenes_productos"] . " AND departamento = '" . $miEmpleado["departamento"] . "'";

            $object["sql_verificacion"] = $sql;
            $localConnection = new LocalDB($sql);
            $verificacion = $localConnection->goQuery();
            $object["verificacion"] = $verificacion;

            if (empty($verificacion)) {
                // BUSCAR CANTIDAD EN `ordenes_productos`
                $sql = "SELECT cantidad FROM ordenes_productos WHERE _id = " . $miEmpleado['id_ordenes_productos'];
                $localConnection = new LocalDB($sql);
                $cantidad_orden = $localConnection->goQuery()[0]["cantidad"];

                $myDate = new CustomTime();
                $now = $myDate->today();

                // ASIGNAR EMPLEADO
                $values = "'" . $now . "',";
                $values .= "'" . $miEmpleado["id_woo"] . "',";
                $values .= "'" . $cantidad_orden . "',";
                $values .= "'" . $miEmpleado["id_orden"] . "',";
                $values .= "'" . $miEmpleado["id_ordenes_productos"] . "',";
                $values .= "'" . $miEmpleado["id_empleado"] . "',";
                $values .= "'" . $miEmpleado["departamento"] . "'";

                $sql = "INSERT INTO lotes_detalles (moment, id_woo, unidades_solicitadas, id_orden, id_ordenes_productos, id_empleado, departamento) VALUES (" . $values . ")";
            } else {
                // Hacer un UPDATE
                $sql = "UPDATE lotes_detalles SET unidades_solicitadas = " . $miEmpleado["cantidad"] . ", id_empleado = " . $miEmpleado["id_empleado"] . " WHERE id_orden = " . $miEmpleado["id_orden"] . " AND id_ordenes_productos = " . $miEmpleado["id_ordenes_productos"] . " AND departamento = '" . $miEmpleado["departamento"] . "'";
            }
        }

        $object["sql_ejecutada"] = $sql;
        $localConnection = new LocalDB($sql);
        $object['asigancion'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        // ACTUALIZAR PAGOS (UNICAMENTE SI AÚN NO SE HA PAGADO -> fechapago = NULL)

        $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "'";

        $sql = "UPDATE pagos SET " . $values . " WHERE departamento = '" . $miEmpleado['departamento'] . "' AND fecha_pago IS NULL AND id_orden = " . $miEmpleado['id_orden'];

        $object['lotes_pagos'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['response_pagos'] = json_encode($localConnection->goQuery());
    });

    $app->post("/lotes/update/cantidad", function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $cantidad_orden = intval($data["cantidad_orden"]);
        $cantidad_a_cortar = intval($data["cantidad_a_cortar"]);

        // -> -> VERIFICAR SI EL REGISTRO EXISTE EN `lotes_fisicos`
        $sql = "SELECT _id,  piezas_actuales FROM lotes_fisicos WHERE tela = '" . $data["tela"] . "' AND talla = '" . $data["talla"] . "' AND corte = '" . $data["corte"] . "' AND categoria = '" . $data["id_category"] . "'";

        $object['sql_count_lotes_fisicos'] = $sql;
        $localConnection = new LocalDB($sql);
        $cantidad_lotes_fisicos = $localConnection->goQuery();
        $object['response_lotes_fisicos'] = $cantidad_lotes_fisicos;

        $last_id_lotes_fisicos = 0;

        if ($cantidad_a_cortar > 0) {
            // GUARDAR EN HISTORICO SOLICITADAS
            $sql = "INSERT INTO lotes_historico_solicitadas (id_orden, id_lotes_fisicos, unidades_produccion) VALUES (" . $data["id_orden"] . ", " . $last_id_lotes_fisicos . ", " . $cantidad_a_cortar . ")";
            $object["SQL_insert_lotes_HISTORICO_SOLICITADAS"] = $sql;
            $localConnection = new LocalDB($sql);
            $object['response_insert_historico_solicitadas'] = $localConnection->goQuery();
        }

        if (empty($cantidad_lotes_fisicos)) {
            $cantidad_unidades = $cantidad_a_cortar - $cantidad_orden;
            $object["dataResp"] = $cantidad_unidades;

            $sql = "INSERT INTO lotes_fisicos (tela, talla, corte, categoria, piezas_actuales) VALUES ('" . $data["tela"] . "', '" . $data["talla"] . "', '" . $data["corte"] . "', '" . $data["id_category"] . "', '" . $cantidad_unidades . "');";
            $localConnection = new LocalDB($sql);
            $object['response_insert_lotes_fidicos'] = $localConnection->goQuery();

            // OBTENER EL ULTIMO ID DE lotes_fisicos
            $localConnection_3 = new LocalDB("SELECT MAX(_id) id FROM lotes_fisicos");
            $last_prod = $localConnection_3->goQuery();
            $last_id_lotes_fisicos = intval($last_prod[0]['id']);
            // TODO ASIGNAT PAGO A CORTE CON LAS UNIDADES SOLICITADAS

        } else {
            // ACTUALIZAR EL REGISTRO EN `lotes_fisicos`
            $cantidad_unidades = (intval($data["cantidad_existencia"]) - $cantidad_orden) + $cantidad_a_cortar;
            // $cantidad_unidades = intval($data["cantidad_existencia"]) + $cantidad_a_cortar;

            $sql = "UPDATE lotes_fisicos SET piezas_actuales = '" . $cantidad_unidades . "' WHERE _id = " . $cantidad_lotes_fisicos[0]["_id"];
            $localConnection = new LocalDB($sql);
            $object["sql_update_lote"] = $sql;
            $object['response_get_lotes_fisicos'] = $localConnection->goQuery();
            $object["dataResp"] = $object['response_lotes_fisicos'][0]["piezas_actuales"];
        }

        // GUARDAR EN lotes_movimientos SIEMPRE!!!
        $sql = "INSERT INTO lotes_movimientos (id_lotes_detalles, id_orden, unidades_existentes, unidades_solicitadas_corte) VALUES (" . $data["id"] . ", " . $data["id_orden"] . ", " . $cantidad_unidades . ", " . $cantidad_a_cortar . ")";
        $localConnection = new LocalDB($sql);
        $object["response_insert_lotes_movimientos"] = $localConnection->goQuery();

        // CONSULTA DE RETORNO DE DATOS.
        if ($last_id_lotes_fisicos > 0) {
            // $last_id_lotes_fisicos = intval($cantidad_lotes_fisicos[0]["_id"]);
        }
        $sql = "SELECT piezas_actuales FROM lotes_fisicos WHERE _id = " . $last_id_lotes_fisicos;
        $localConnection = new LocalDB($sql);
        $cantidad_piezas = $localConnection->goQuery();
        $object["cantidad_piezas"] = $cantidad_piezas;

        $sql = "SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos";
        $localConnection = new LocalDB($sql);
        $object["lotes_fisicos"] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    $app->post("/lotes/update/prioridad", function (Request $request, Response $response) {

        $data = $request->getParsedBody();



        $sql = "UPDATE lotes SET prioridad = '" . $data["prioridad"] . "' WHERE _id = '" . $data["id"] . "'";



        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['response_orden'] = json_encode($localConnection->goQuery());



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /**
     * LOCAL LOTES ACTIVOS
     */



    $app->get('/lotes/activos', function (Request $request, Response $response, array $args) {

        $sql = "SELECT a.lote, a.fecha, a.id_orden, a.paso, b.cliente_nombre FROM lotes a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'pre-order' ORDER BY a.lote DESC";

        $localConnection = new LocalDB($sql);

        $object['lotes'] = $localConnection->goQuery();



        $sql = "SELECT a.id_orden, b.departamento, c.username empleado, b.producto, b.unidades_restantes, b.unidades_solicitadas, b.detalles, a.lote FROM lotes a JOIN lotes_detalles b ON a.id_orden = b.id_orden JOIN empleados c ON b.id_empleado = c._id";

        $localConnection = new LocalDB($sql);

        $object['lotes_detalles'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);



        // ****************************************************************************************************************



        $sql = "SELECT * FROM lotes ORDER BY lote DESC";

        $localConnection = new LocalDB($sql);

        $lotes = $localConnection->goQuery();



        foreach ($lotes as $key => $value) {

            // Formatear Fecha

            $exp = explode("-", $value['fecha']);

            $tmpFecha = $exp[2] . '/' . $exp[1] . '/' . $exp[0];



            // Nombres de Empleados

            $sql2 = "SELECT _id, dep_responsable, dep_diseno, dep_corte, dep_impresion, dep_estampado, dep_confeccion, dep_revision FROM ordenes WHERE _id = " . $value['id_orden'];

            $localConnection2 = new LocalDB($sql2);

            $empleados_id = $localConnection2->goQuery();



            $resp[$key]['_id'] = $value['_id'];

            $resp[$key]['lote'] = $value['lote'];

            $resp[$key]['id_orden'] = $value['_id'];

            $resp[$key]['fecha'] = $tmpFecha;

            $resp[$key]['piezas'] = $value['piezas_actuales'];

            // $resp[$key]['SQLprod'] = $sqlp;

            // $resp[$key]['prod'] = $productos;



            foreach ($empleados_id as $key2 => $empleado) {

                $sql3 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_responsable'];

                $localConnection3 = new LocalDB($sql3);

                $resp[$key]['empleados']->responsable = $localConnection3->goQuery()[0]['username'];



                $sql3 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_diseno'];

                $localConnection3 = new LocalDB($sql3);

                $resp[$key]['empleados']->responsable = $localConnection3->goQuery()[0]['username'];



                $sql4 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_corte'];

                $localConnection4 = new LocalDB($sql4);

                $resp[$key]['empleados']->corte = $localConnection4->goQuery()[0]['username'];



                $sql4 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_impresion'];

                $localConnection4 = new LocalDB($sql4);

                $resp[$key]['empleados']->impresion = $localConnection4->goQuery()[0]['username'];



                $sql4 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_estampado'];

                $localConnection4 = new LocalDB($sql4);

                $resp[$key]['empleados']->estampado = $localConnection4->goQuery()[0]['username'];



                $sql4 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_confeccion'];

                $localConnection4 = new LocalDB($sql4);

                $resp[$key]['empleados']->confeccion = $localConnection4->goQuery()[0]['username'];



                $sql4 = "SELECT username FROM empleados WHERE _id = " . $empleado['dep_revision'];

                $localConnection4 = new LocalDB($sql4);

                $resp[$key]['empleados']->revision = $localConnection4->goQuery()[0]['username'];
            }



            // Productos del lote

            $sqlp = "SELECT name as nombre FROM ordenes_productos WHERE id_orden = " . $value['id_orden'];

            $localConnectionp = new LocalDB($sqlp);

            $productos = $localConnectionp->goQuery();



            foreach ($productos as $keyp => $producto) {

                $resp[$key]['productos'][$keyp] = $producto['nombre'];
            }
        }

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/lotes/fisicos', function (Request $request, Response $response, array $args) {

        $sql = "SELECT a.unidades FROM lotes_fisicos a JOIN inventario b ON a.id_inventario = b._id";

        $localConnection = new LocalDB($sql);
        $object['lotes'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/lotes/existencia/{talla}/{tela}/{corte}/{categoria}', function (Request $request, Response $response, array $args) {

        $sql = "SELECT piezas_actuales FROM lotes_fisicos WHERE talla = '" . $args["talla"] . "' AND tela = '" . $args["tela"] . "' AND corte = '" . $args["corte"] . "' AND categoria = '" . $args["categoria"] . "'";

        $localConnection = new LocalDB($sql);
        $response_lotes = $localConnection->goQuery();

        if (empty($response_lotes)) {
            $cantidad = 0;
        } else {
            $cantidad = $response_lotes[0]["piezas_actuales"];
        }


        $response->getBody()->write(json_encode($cantidad));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * FIN LOTES */



    /**
     * Asignacion
     */



    // Obtener datos para la asignaciond e empelados

    $app->get('/asignacion/ordenes', function (Request $request, Response $response, array $args) {

        $object['fields'][0]['key'] = "orden";

        $object['fields'][0]['label'] = "Orden";

        // $object['fields'][4]['sortable'] = false;



        $object['fields'][1]['key'] = "cliente";

        $object['fields'][1]['label'] = "Cliente";

        // $object['fields'][4]['sortable'] = false;



        $object['fields'][2]['key'] = "inicio";

        $object['fields'][2]['label'] = "Inicio";

        // $object['fields'][4]['sortable'] = false;



        $object['fields'][3]['key'] = "entrega";

        $object['fields'][3]['label'] = "Entrega";

        // $object['fields'][4]['sortable'] = false;



        $object['fields'][4]['key'] = "status";

        $object['fields'][4]['label'] = "Estatus";

        // $object['fields'][4]['sortable'] = false;



        $object['fields'][4]['key'] = "asignar";

        $object['fields'][4]['label'] = "Asignar";

        // $object['fields'][4]['sortable'] = false;



        $sql = "SELECT a._id orden, a._id asignar, a.cliente_nombre cliente, a.fecha_inicio inicio, a.fecha_entrega entrega, a.status estatus, b.terminado FROM `ordenes` a JOIN disenos b ON a._id = b.id_orden WHERE (a.status = 'activa' OR a.status = 'terminada' OR a.status = 'En espera' OR status = 'pausada') AND b.terminado = 1 OR b.tipo = 'no' ORDER BY a._id DESC";



        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();

        $object['data'] = $object["items"];



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obtener empleados de la asignados de la orden

    $app->get('/asignacion/empleados/{orden}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";



        $sql = "SELECT dep_responsable responsable,dep_diseno diseno, dep_jefe_diseno, dep_corte corte,dep_impresion impresion,dep_estampado estampado,dep_confeccion confeccion,dep_revision revision FROM ordenes WHERE _id = " . $args["orden"];



        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['data'] = $localConnection->goQuery();



        $testError['test'] = "estoe s un test";

        $response->getBody()->write(json_encode($testError));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // GUARDAR ASIGNACION

    $app->post("/asignacion/{orden}/{departamento}/{empleado}", function (Request $request, Response $response, $args) {

        // $args = $request->getParsedBody(); // -> orden, departamento, empleado



        // ACTUALIZAR DATOS DE LA ORDEN

        $departamento = "dep_" . $args["departamento"];



        if ($args["empleado"] === "none") {

            $object["sql_orden"] = $sql_ordenes = "UPDATE ordenes SET " . $departamento . " = NULL WHERE _id = " . $args["orden"];

            $object["sql_pagos"] = $sql_pagos = "DELETE FROM pagos WHERE id_orden = " . $args["orden"] . " AND departamento = '" . $args["departamento"] . "';";
        } else {

            // BUSCAR COMISION DEL EMPLEADO PARA LA ORDEN

            $object["sql_orden"] = $sql_ordenes = "UPDATE ordenes SET " . $departamento . " = " . $args["empleado"] . " WHERE _id = " . $args["orden"];

            $object["sql_empelado"] = $sql_comision = "SELECT  comision FROM empleados WHERE _id = " . $args['empleado'];

            $localConnection = new LocalDB($sql_comision);

            $dataEmpleado = $localConnection->goQuery();



            $comision = $dataEmpleado[0]["comision"];

            // PREPARAR FECHAS

            $myDate = new CustomTime();

            $now = $myDate->today();



            // GUARDAR DATOS DEL PAGO

            $values = "'" . $now . "',";

            $values .= $args["empleado"] . ",";

            $values .= $args["orden"] . ",";

            $values .= "'" . $args["departamento"] . "',";

            $values .= "'0000-00-00',";

            $values .= "0,";

            $values .= $comision . ",";

            $values .= "0";



            $object["sql_pagos"] = $sql_pagos = "DELETE FROM pagos WHERE id_orden = " . $args["orden"] . " AND departamento = '" . $args["departamento"] . "';";

            $object["sql_pagos"] .= $sql_pagos = "INSERT INTO pagos (moment, id_empleado, id_orden, departamento, fecha_terminado, dolar,  comision, pago) VALUES (" . $values . ")";
        }



        $localConnection = new LocalDB($sql_ordenes);

        $dataEmpleado = $localConnection->goQuery();



        $localConnection = new LocalDB($sql_pagos);

        $dataEmpleado = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // ELIMINAR ASIGNACION

    $app->post("/asignacion/elimianr/{orden}/{departamento}", function (Request $request, Response $response, $args) {

        // $args = $request->getParsedBody(); // -> orden, departamento, empleado



        // ACTUALIZAR DATOS DE LA ORDEN

        $departamento = "dep_" . $args["departamento"];

        $object["sql_orden"] = $sql = "UPDATE ordenes SET " . $departamento . " = 0 WHERE _id = " . $args["orden"];

        $localConnection = new LocalDB($sql);

        $dataEmpleado = $localConnection->goQuery();



        // BUSCAR COMISION DEL EMPLEADO PARA LA ORDEN

        /* $object["sql_empelado"] = $sql = "SELECT  comision FROM empleados WHERE _id = " . $args['empleado'];
        $localConnection = new LocalDB($sql);
        $dataEmpleado = $localConnection->goQuery();
        $comision = $dataEmpleado[0]["comision"];*/



        // ELIMINAR DATOS DEL PAGO

        $object["sql_pagos"] = $sql = "DELETE FROM PAGOS WHERE id_orden = " . $request['id_orden'] . " AND departamento = " . $args["departamento"];



        $localConnection = new LocalDB($sql);

        $object["dataEmpleado"] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /**
     * Fin asignacion
     */



    /**
     * PRODUCCION
     */



    // SSE DATA
    // SSE PRODUCCION
    $app->get('/sse/produccion', function (Request $request, Response $response, array $args) { // /lotes/en-proceso
        $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $obj[0]["sql"] = $sql;
        $obj[0]["name"] = "items";

        // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
        $sql = "SELECT a._id id_orden, b.departamento FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' GROUP BY a._id, b.departamento";
        $obj[1]["sql"] = $sql;
        $obj[1]["name"] = "pactivos";

        // $sql = "SELECT id_child FROM ordenes_vinculadas WHERE id_father = " . $args["id_orden_father"];
        $sql = "SELECT b.id_father, b.id_child FROM ordenes_vinculadas b JOIN ordenes a ON a._id = b.id_father WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[2]["sql"] = $sql;
        $obj[2]["name"] = "vinculadas";

        $sql = "SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC";
        $obj[3]["sql"] = $sql;
        $obj[3]["name"] = "asignacion";

        $sql = "SELECT b.id_orden, b.paso from lotes b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[4]["sql"] = $sql;
        $obj[4]["name"] = "pasos";

        $sql = "SELECT b._id, b.id_orden, b.id_woo, b.id_category, b.category_name, b.name, b.cantidad, b.talla, b.corte, b.tela, b.precio_unitario, b.precio_woo, b.moment FROM ordenes_productos b JOIN ordenes a ON b.id_orden = a._id WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' AND b.category_name != 'Diseños'";
        $obj[5]["sql"] = $sql;
        $obj[5]["name"] = "orden_productos";

        $sql = "SELECT b._id, b.id_orden, b.id_woo, b.progreso, b.id_ordenes_productos, b.id_empleado, b.departamento, b.unidades_solicitadas, b.comision, b.detalles, b.fecha_inicio, b.fecha_terminado, b.moment FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[6]["sql"] = $sql;
        $obj[6]["name"] = "lote_detalles";

        $sql = "SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos";
        $obj[7]["sql"] = $sql;
        $obj[7]["name"] = "lotes_fisicos";

        // $sql = "SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = " . $args["orden"] . " AND category_name != 'Diseños'";
        $sql = "SELECT b._id, b.id_orden, b._id item, b.id_woo cod, b.name producto, b.cantidad, b.talla, b.tela, b.corte, b.precio_unitario precio, b.precio_woo precioWoo FROM ordenes_productos b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' AND category_name != 'Diseños'";
        $obj[8]["sql"] = $sql;
        $obj[8]["name"] = "reposicion_ordenes_productos";

        $sql = "SELECT _id, _id acciones, username, password, nombre, email, departamento, comision, acceso FROM empleados ORDER BY nombre ASC";
        $obj[9]["sql"] = $sql;
        $obj[9]["name"] = "empleados";

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        // $localConnection = new LocalDB($sql);

        // $object["items"] = $localConnection->goQuery();



        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = "orden";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "prioridad";
        $object['fields'][2]['label'] = "Prioridad";

        $object['fields'][2]['key'] = "paso";
        $object['fields'][2]['label'] = "Progreso";

        $object['fields'][3]['key'] = "inicio";
        $object['fields'][3]['label'] = "Inicio";

        $object['fields'][4]['key'] = "entrega";
        $object['fields'][4]['label'] = "Entrega";

        $object['fields'][5]['key'] = "vinculada";
        $object['fields'][5]['label'] = "Vinculada";

        $object['fields'][6]['key'] = "estatus";
        $object['fields'][6]['label'] = "Estatus";

        $object['fields'][7]['key'] = "detalles";
        $object['fields'][7]['label'] = "Detalles";

        $object['fields'][8]['key'] = "acciones";
        $object['fields'][8]['label'] = "Acciones";




        $response->getBody()->write(json_encode($events));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });
    // SSE CORTE    
    $app->get('/sse/produccion/corte', function (Request $request, Response $response, array $args) { // /lotes/en-proceso
        $sql = "SELECT 
            a._id AS id_lotes_detalles, 
            a.id_orden orden,
            b.name producto,
            b.cantidad, 
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
            a.id_empleado IS NULL 
            AND a.departamento = 'Corte' 
            AND b.corte != 'No aplica' 
        ORDER BY b.talla ASC, b.corte ASC, b.tela ASC;
        ";
        $obj[0]["sql"] = $sql;
        $obj[0]["name"] = "items";

        $sql = "SELECT _id id_empleado, nombre FROM empleados WHERE departamento = 'Corte'";
        $obj[1]["sql"] = $sql;
        $obj[1]["name"] = "empleados";


        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        $response->getBody()->write(json_encode($events));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    // SSE DISENO   
    $app->get('/sse/diseno/{id_empleado}', function (Request $request, Response $response, array $args) { // /lotes/en-proceso
        // $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $sql = "SELECT 
            a.linkdrive, 
            a.codigo_diseno, 
            a.id_orden, 
            a._id id_diseno,
            a._id tallas_y_personalizacion,
            a.id_orden id, 
            a.id_orden imagen, 
            a.id_orden revision, 
            a.linkdrive,
            b.cliente_nombre cliente, 
            b.fecha_inicio inicio, 
            a.tipo,
            c.estatus,
            b.status estatus_orden 
        FROM disenos a 
            LEFT JOIN revisiones c 
            ON a._id = c.id_diseno 
        JOIN ordenes b 
            ON b._id = a.id_orden 
        LEFT JOIN disenos d ON d._id = c.id_diseno
        WHERE a.id_empleado =    " . $args["id_empleado"] . " 
        AND a.terminado = 0 
        AND (b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera')
        ORDER BY a.id_orden ASC
        ";

        $obj[0]["sql"] = $sql;
        $obj[0]["name"] = "items";

        // $sql = "SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = " . $args["id_empleado"];
        // $sql = "SELECT estatus, detalles FROM revisiones WHERE _id = " . $args["id"];
        $sql = "SELECT a._id id_revision, a.id_diseno, a.id_orden, a.revision, a.estatus, a.detalles FROM revisiones a JOIN disenos b ON b.id_orden = a.id_orden WHERE b.id_empleado = " . $args["id_empleado"] . " ORDER BY a._id DESC";
        $obj[1]["sql"] = $sql;
        $obj[1]["name"] = "revisiones";

        $sql = "SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = " . $args["id_empleado"];
        $obj[2]["sql"] = $sql;
        $obj[2]["name"] = "ajustes";

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        // $localConnection = new LocalDB($sql);

        // $object["items"] = $localConnection->goQuery();



        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = "orden";
        $object['fields'][0]['label'] = "Orden";

        $object['fields'][1]['key'] = "cliente";
        $object['fields'][1]['label'] = "Cliente";

        $object['fields'][2]['key'] = "prioridad";
        $object['fields'][2]['label'] = "Prioridad";

        $object['fields'][2]['key'] = "paso";
        $object['fields'][2]['label'] = "Progreso";

        $object['fields'][3]['key'] = "inicio";
        $object['fields'][3]['label'] = "Inicio";

        $object['fields'][4]['key'] = "entrega";
        $object['fields'][4]['label'] = "Entrega";

        $object['fields'][5]['key'] = "vinculada";
        $object['fields'][5]['label'] = "Vinculada";

        $object['fields'][6]['key'] = "estatus";
        $object['fields'][6]['label'] = "Estatus";

        $object['fields'][7]['key'] = "detalles";
        $object['fields'][7]['label'] = "Detalles";

        $object['fields'][8]['key'] = "acciones";
        $object['fields'][8]['label'] = "Acciones";




        $response->getBody()->write(json_encode($events));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });
    // FIN SSE DATA

    // OBTENER PASO DEL LOTE
    $app->get('/lotes/paso-actual/{id_orden}', function (Request $request, Response $response, array $args) {
        // BUSCAR ORENES EN CURSO
        // BUSCAR PASO ACTUAL EN EL LOTE

        $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
        $localConnection = new LocalDB($sql);
        $tmpPaso = $localConnection->goQuery();

        if (!empty($tmpPaso)) {
            $object["paso"] = $tmpPaso[0]["paso"];
        } else {
            $object["paso"] = null;
        }



        $response->getBody()->write(json_encode($object));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    //  REPOSICIONES



    // obtener reposiciones de un item y orden especifico

    $app->get('/reposiciones/{id_ordenes_productos}/{id_orden}', function (Request $request, Response $response, array $args) {
        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";

        $sql = "SELECT c.name producto, a.unidades,  c.talla, c.corte, c.tela, b.nombre empleado, detalle FROM reposiciones a JOIN empleados b ON a.id_empleado = b._id JOIN ordenes_productos c ON a.id_ordenes_productos = c._id WHERE a.id_ordenes_productos = " . $args["id_ordenes_productos"] . " AND a.id_orden = " . $args["id_orden"];

        $object['sql'] = $sql;
        $localConnection = new LocalDB($sql);
        $object['data'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/produccion/reposicion', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        // Buscar datos faltantes
        $sql = "SELECT * FROM ordenes_productos WHERE _id = " . $data["id_ordenes_productos"];
        $localConnection = new LocalDB($sql);
        $producto = $localConnection->goQuery()[0];

        // Buscar id del empelado involucrado en la reposición DEPRECIADO SE SELECCIONARÁ DESDE EL AINTEFAZ PARA FORZASR VERIFICAR QUE SE ESTE CARGTANDO L;ARESPONSABILIDAD A LA PERSONA CORRECTA
        /* $sql                = "SELECT id_empleado id FROM lotes_detalles WHERE id_orden = " . $producto["id_orden"] . " AND  id_ordenes_productos = " .$data["id_ordenes_productos"];
        $object["sql_search_empelado"] = $sql;
        $localConnection    = new LocalDB($sql);
        $id_empleado = $localConnection->goQuery();
        $object["emp"] = $id_empleado; */

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $campos = "(moment, id_orden, id_empleado, id_ordenes_productos, unidades, detalle)";
        $values = "(";
        $values .= "'" . $now . "',";
        $values .= "" . $producto["id_orden"] . ",";
        $values .= "" . $data["id_empleado"] . ",";
        $values .= "" . $producto["_id"] . ",";
        $values .= "" . $producto["cantidad"] . ",";
        $values .= "'" . $data["detalle"] . "')";

        $sql = "INSERT INTO reposiciones " . $campos . " VALUES " . $values;
        $object["sql_insert_reposiciones"] = $sql;
        $localConnection = new LocalDB($sql);
        $object["response"] = $localConnection->goQuery();

        // BUSCAR DEPARTAMENTO DEL EMPLEADO PARA DETERMINAR LOS PASOS INVOLUCRADOS EN LA REPOSICIÓN Y ASIA SIGNARLES COMO TRABAJO LAS PIEZAS EN LOTES DETALLES.
        $sql = "SELECT departamento FROM empleados WHERE _id = " . $data["id_empleado"];
        $object["sql_get_departamento_empleado"] = $sql;
        $localConnection = new LocalDB($sql);
        $departamento = $localConnection->goQuery()[0]["departamento"];

        // DEVOLVER EL PASO A CORTE EN lotes
        // ASIGNAR NUEVAS TAREAS A EMPLEADOS ¿CREAR NUEVOS REGISTROS EN lotes_detalles?

        // -> BUSCAR DATOS EN ordenes_productos
        $sql = "SELECT id_orden, id_woo FROM ordenes_productos WHERE _id = " . $producto["_id"];
        $object["sql_get_idwoo_ordenes_productos"] = $sql;
        $localConnection = new LocalDB($sql);
        $object["result_ordenes_detalles"] = $localConnection->goQuery()[0];
        $id_woo = $object["result_ordenes_detalles"]["id_woo"];
        $object["id_woo"] = $object["result_ordenes_detalles"]["id_woo"];

        // TODO VERIFICAR EXISTENCIA EN LOTE Y NOTIFICAR A JEFE DE PRODUCCION

        // REASIGNAR TRABAJO A EMPLEADOS Y NO SE EXCLUIRÁ AL TRABAJADOR QUE ESTE INVOLUCRADO, ESO SE DECIDIRÁ AL MOMENTO DE SACAR EL REPORTE DE PAGOS
        $sql_lote_detalles = "";
        switch ($departamento) {
            case 'Corte':
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                break;

            case 'Impresión':
                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Corte'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_corte = $localConnection->goQuery()[0]["id_empleado"];

                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_corte . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Impresión', '" . $data["detalle"] . "');";
                break;

            case 'Estampado':
                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Corte'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_corte = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Impresión'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_impresion = $localConnection->goQuery()[0]["id_empleado"];

                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_corte . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_impresion . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Impresión', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Estampado', '" . $data["detalle"] . "');";
                break;

            case 'Costura':
                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Corte'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_corte = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Impresión'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_impresion = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Estampado'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_estampado = $localConnection->goQuery()[0]["id_empleado"];

                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_corte . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_impresion . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Impresión', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_estampado . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Estampado', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Costura', '" . $data["detalle"] . "');";
                break;

            case 'Limpieza':
                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Corte'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_corte = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Impresión'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_impresion = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Estampado'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_estampado = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Costura'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_costura = $localConnection->goQuery()[0]["id_empleado"];

                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_corte . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_impresion . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Impresión', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_estampado . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Estampado', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_costura . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Costura', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Limpieza', '" . $data["detalle"] . "');";
                break;

            case 'Revisión':
                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Corte'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_corte = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Impresión'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_impresion = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Estampado'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_estampado = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Costura'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_costura = $localConnection->goQuery()[0]["id_empleado"];

                $sqlw = "SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = " . $data["id_ordenes_productos"] . " AND id_orden = " . $data["id_orden"] . " AND departamento = 'Limpieza'";
                $localConnection = new LocalDB($sqlw);
                $id_emp_limpieza = $localConnection->goQuery()[0]["id_empleado"];

                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_corte . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Corte', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_impresion . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Impresión', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_estampado . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Estampado', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_costura . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Costura', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $id_emp_limpieza . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Limpieza', '" . $data["detalle"] . "');";
                $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data["id_empleado"] . "', '" . $data["cantidad"] . "', '" . $now . "', '" . $producto["id_orden"] . "', '" . $producto["_id"] . "', '" . $id_woo . "', 'Revisión', '" . $data["detalle"] . "');";
                break;

            default:
                $sql_lote_detalles = "";
                break;
        }

        $object["sql_insert_lotes_detalles"] = $sql_lote_detalles;


        if (!empty($sql_lote_detalles)) {
            $localConnection = new LocalDB($sql_lote_detalles);
            $object["result_insert_lotes_detalles"] = $localConnection->goQuery();
        }

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // TERMINAR CICLO DE PRODUCCION

    $app->post('/produccion/terminar/{id}', function (Request $request, Response $response, array $args) {

        $id = $args['id'];



        $sql = "UPDATE `ordenes` SET `status`='terminado' WHERE `_id` = " . $id;

        $localConnection = new LocalDB($sql);

        $object['response'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    // ASIGNAR VARIAS ORDENES A CORTE A LA VEZ
    $app->post('/produccion/asignar-varias-ordenes-a-corte', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $object["data"] = $data;

        $sql = "SELECTZ * FROM empleados WHERE _id = " . $data["id_empleado"];
        $localConnection = new LocalDB($sql);
        $object['response'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // UPDATE PASO

    $app->post("/produccion/update/paso", function (Request $request, Response $response) {

        $data = $request->getParsedBody();

        // VERIFCAR SI EXISTE PERSONAL ASIGNADO APR ESTE PRODUCTO EN EL LOTE

        $sql = "SELECT COUNT(*) cuenta FROM lotes_detalles WHERE id_orden = " . $data["id_orden"] . " AND departamento = '" . $data["paso"] . "'";

        $localConnection = new LocalDB($sql);

        $object["sql_empty"] = $sql;

        $cuenta = $localConnection->goQuery();

        $asignados = $cuenta[0]["cuenta"];

        $object["asignados"] = $cuenta[0]["cuenta"];

        $object["empty"] = empty($asignados);



        if (empty($asignados)) {

            $object["nodata"] = true;
        } else {



            // TODO buscar datos para el calculo de pagos

            $sql = "UPDATE lotes SET paso = '" . $data["paso"] . "' WHERE _id = '" . $data["id_orden"] . "'";



            $localConnection = new LocalDB($sql);

            $object['response_orden'] = json_encode($localConnection->goQuery());

            $object["nodata"] = false;
        }



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // PROGRESSBAR
    $app->get('/produccion/progressbar/{id_orden}', function (Request $request, Response $response, array $args) {

        // VERIFCAR STATUS DE LA ORDEN
        $sql = "SELECT status from ordenes WHERE _id = " . $args["id_orden"];
        $localConnection = new LocalDB($sql);
        $tmpStatus = $localConnection->goQuery();
        $object["status"] = $tmpStatus[0]["status"];

        // BUSCAR PASO ACTUAL EN EL LOTE
        // $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
        $sql = "SELECT paso from lotes WHERE id_orden = " . $args["id_orden"];
        $localConnection = new LocalDB($sql);
        $tmpPaso = $localConnection->goQuery();
        $object["paso"] = $tmpPaso[0]["paso"];

        // BUSCAR TIPO DE DISEÑO
        $sql = "SELECT tipo FROM disenos WHERE id_orden = " . $args["id_orden"];
        $localConnection = new LocalDB($sql);
        $d = $localConnection->goQuery();

        if (empty($d)) {
            $diseno = 'no';
        } else {
            $diseno = $d[0]["tipo"];
        }

        if ($diseno === "no") {
            $cuentaDisenos = 0;
        } else {
            $cuentaDisenos = 2;
        }
        $object["data"]["cuentaDisenos"] = $cuentaDisenos;

        // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
        $sql = "SELECT `departamento` FROM lotes_detalles WHERE id_orden = " . $args["id_orden"] . " GROUP BY departamento";
        $localConnection = new LocalDB($sql);
        $pActivos = $localConnection->goQuery();
        $object["data"]["pActivos"] = $pActivos;

        switch ($object["paso"]) {
            case 'Producción':
                $x[] = 0;
                break;

            case 'Corte':
                $x[] = 1;
                break;

            case 'Estampado':
                $x[] = 2;
                break;

            case 'Impresión':
                $x[] = 3;
                break;

            case 'Costura':
                $x[] = 4;
                break;

            case 'Limpieza':
                $x[] = 5;
                break;

            case 'Limpieza':
                $x[] = 5;
                break;

            /*  case 'Diseno':
            $x[] = 0;
            break; */

            default:
                $x[] = 0;
                break;
        }

        $pasoActual = max($x);
        $object["data"]["pasoActual"] = $pasoActual;
        $totalPasos = count($pActivos);
        $object["data"]["totalPasos"] = count($pActivos);

        if (!$totalPasos) {
            $totalPasos = 1;
        }

        $object["porcentaje"] = round($pasoActual * 100 / $totalPasos);
        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Detalles para la asignacion de personal V2
    $app->get('/lotes/detalles/v2/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];

        // OBTENER PRODUCTOS DEL LOTE
        $object['fields_orden_productos'][0]['key'] = "name";
        $object['fields_orden_productos'][0]['label'] = "Producto";
        $object['fields_orden_productos'][0]['sortable'] = false;
        $object['fields_orden_productos'][1]['key'] = "corte";
        $object['fields_orden_productos'][1]['label'] = "Corte";
        $object['fields_orden_productos'][1]['sortable'] = false;
        $object['fields_orden_productos'][2]['key'] = "talla";
        $object['fields_orden_productos'][2]['label'] = "Talla";
        $object['fields_orden_productos'][2]['class'] = "text-center";
        $object['fields_orden_productos'][2]['sortable'] = false;
        $object['fields_orden_productos'][3]['key'] = "tela";
        $object['fields_orden_productos'][3]['label'] = "Tela";
        $object['fields_orden_productos'][3]['sortable'] = false;
        $object['fields_orden_productos'][4]['key'] = "cantidad";
        $object['fields_orden_productos'][4]['label'] = "Solicitada";
        $object['fields_orden_productos'][4]['class'] = "text-center";
        $object['fields_orden_productos'][4]['sortable'] = false;
        $object['fields_orden_productos'][6]['key'] = "cantidad_lote";
        $object['fields_orden_productos'][6]['label'] = "Existencia";
        $object['fields_orden_productos'][6]['class'] = "text-center";
        $object['fields_orden_productos'][6]['sortable'] = false;

        // EXCLUIR DISEÑOS FILTRANDO POR NOMBRE
        $sql = "SELECT * FROM ordenes_productos WHERE category_name != 'Diseños' AND id_orden = " . $id;

        $object['query_orden_productos'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['orden_productos'] = $localConnection->goQuery();



        $sql = "SELECT * FROM lotes_detalles WHERE id_orden = " . $id;

        $object['query_lotes_detalle'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['lote_detalles'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    // VERIFICAR SI EXISTE EMPLEADO ASIGNADO PARA ASIGNACION DE EMPLEADOS EN PRDUCCIÓN
    $app->get('/produccion/verificar-asignacion-empleado/{departamento}/{id_orden}/{id_ordenes_productos}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT id_empleado FROM lotes_detalles WHERE id_orden = " . $args["id_orden"] . " AND id_ordenes_productos = " . $args["id_ordenes_productos"] . " AND departamento = '" . $args["departamento"] . "'";
        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $object = $localConnection->goQuery()[0];

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });


    // Detalles para la asignacion de personal

    $app->get('/lotes/detalles/{id}', function (Request $request, Response $response, array $args) {

        $id = $args['id'];



        // OBTENER LOTE

        $sql = "SELECT _id, lote, fecha, id_orden, paso  FROM lotes WHERE _id = " . $id;

        $localConnection = new LocalDB($sql);

        $object['lote'] = $localConnection->goQuery();



        // OBTENER PRODUCTOS DEL LOTE

        $sql = "SELECT _id, name producto FROM ordenes_productos WHERE id_orden = " . $id;

        $localConnection = new LocalDB($sql);

        $object['orden_productos'] = $localConnection->goQuery();



        // OBTENER PAGOS

        $sql = "SELECT * FROM pagos WHERE id_orden = " . $id;

        $localConnection = new LocalDB($sql);

        $object['orden_pagos'] = $localConnection->goQuery();



        // OBTENER DETALLES DEL LOTE

        // $sql = "SELECT b.username empleado, a.departamento, a.producto, a.unidades_restantes, a.unidades_solicitadas, a.detalles FROM lotes_detalles as a JOIN empleados AS b ON a.id_empleado = b._id WHERE a.id_orden = " . $id;

        $sql = "SELECT * FROM lotes_detalles WHERE id_orden = " . $id;

        $localConnection = new LocalDB($sql);

        $object['lote_detalles'] = $localConnection->goQuery();

        $object['lote_detalles_SQL'] = $sql;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // obtener detalles de empleados de la orden

    $app->get('/ordenes/detalles/{id}', function (Request $request, Response $response, array $args) {
        // $sql = "SELECT a.tipo, a.id_orden, a.cliente_nombre, b._id id_empleado FROM disenos a JOIN empleados b ON a.id_empleado = b._id WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";
        // $sql = "SELECT`dep_corte_detalles`, `observaciones` detalle, `dep_impresion_detalles`, `dep_estampado_detalles`, `dep_confeccion_detalles`, `dep_revision_detalles` FROM `ordenes` WHERE _id = " . $args["id"];
        $sql = "SELECT observaciones FROM ordenes WHERE _id = " . $args["id"];
        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);
        $object['detalle'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // obtener ordenes vinculadas

    $app->get('/ordenes/vinculadas/{id_orden_father}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT id_child FROM ordenes_vinculadas WHERE id_father = " . $args["id_orden_father"];
        // $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);
        $vinculadas = $localConnection->goQuery();

        $response->getBody()->write(json_encode($vinculadas));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /**
     * FIN PRODUCCION
     */



    /**
     * TRUNCAR ORDER Y LOTES
     */

    $app->post('/truncate', function (Request $request, Response $response) {
        $sql = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `inventario_movimientos`; TRUNCATE `lotes`; TRUNCATE `lotes_detalles`; TRUNCATE `lotes_fisicos`; TRUNCATE `lotes_historico_solicitadas`; TRUNCATE `lotes_movimientos`; TRUNCATE `ordenes`; TRUNCATE `ordenes_productos`; TRUNCATE `ordenes_vinculadas`; TRUNCATE `pagos`; TRUNCATE `disenos`; TRUNCATE `disenos_ajustes_y_personalizaciones`; TRUNCATE `asistencias`; TRUNCATE `pagos`; TRUNCATE `abonos`; TRUNCATE `retiros`; TRUNCATE `revisiones`; TRUNCATE `reposiciones`; TRUNCATE `metodos_de_pago`; TRUNCATE `caja`; TRUNCATE `caja_fondos`; TRUNCATE `caja_cierres`; TRUNCATE `caja_cierres`; SET FOREIGN_KEY_CHECKS = 1;";

        $localConnection = new LocalDB($sql);
        $object['sql'] = str_ireplace("\n", " ", $sql);
        $object['response'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    /** Revisión */

    // CREAR UNA NUEVA REVISIONrevision
    $app->post('/revision/nuevo', function (Request $request, Response $response) {

        $miRevision = $request->getParsedBody();
        // obtener numero de la última revision
        $sql = "SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = " . $miRevision["id_diseno"] . " AND id_orden = " . $miRevision["id_orden"];

        $object["sql_MAX_REVIEW"] = $sql;
        $localConnection = new LocalDB($sql);
        $tmpRevID = $localConnection->goQuery();

        if ($tmpRevID[0]["revision"] === null) {
            $currID = 1;
        } else {
            $currID = intval($tmpRevID[0]["revision"]) + 1;
        }

        // CREAR REVISION
        $values = "(";
        $values .= "'" . $miRevision['id_diseno'] . "',";
        $values .= "'" . $miRevision['id_orden'] . "',";
        $values .= "'" . $currID . "')";

        $sql = "INSERT INTO revisiones (`id_diseno`, `id_orden`, `revision`) VALUES " . $values;
        $localConnection = new LocalDB($sql);
        $object['response_insert'] = json_encode($localConnection->goQuery());

        $object["sql_insert"] = $sql;

        $sql =
            "SELECT * FROM revisiones WHERE id_diseno = " . $miRevision["id_diseno"] . " AND id_orden = " . $miRevision["id_orden"];
        $localConnection = new LocalDB($sql);
        $tmpRevision = $localConnection->goQuery();

        if (count($tmpRevision) > 0) {
            $object['revision'] = $tmpRevision[0];
        } else {
            $object['revision'] = $tmpRevision;
        }

        $object["sql_get_review"] = $sql;

        // obtener numero de la última revision
        $sql = "SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = " . $miRevision["id_diseno"] . " AND id_orden = " . $miRevision["id_orden"];

        $object["sql_MAX_REVIEW"] = $sql;
        $localConnection = new LocalDB($sql);
        $object["lastId"] = $localConnection->goQuery();

        /* if (count($lastTmp) > 0) {
        $object["lastId"];
        } else {
        $object["lastId"] = 1;
        } */

        $object["image_name"] = $miRevision["id_orden"] . "-" . $miRevision["id_diseno"] . "-" . $object["lastId"][0]["revision"];

        $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = '" . $miRevision["id_empleado"] . "' a.tipo != 'no' AND (a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado')";
        $localConnection = new LocalDB($sql);
        $object["new_data"] = $localConnection->goQuery();


        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DATOS DE LA REVISION DE UN DISEÑO POR SU ID
    $app->get('/revision/diseno/{id}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a._id, a.id_diseno, a.revision, a.detalles, b.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = " . $args["id"];
        $sql = "SELECT _id id_revision, id_diseno, id_orden, revision, estatus, detalles FROM revisiones a WHERE id_orden = " . $args["id"] . " ORDER BY _id DESC";

        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $object = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));
        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    // OBTENER ESTATUS DE LA REVISION
    $app->get('/revisiones/estatus/{id}', function (Request $request, Response $response, array $args) {

        // $sql = "SELECT a._id, a.id_diseno, a.revision, a.detalles, b.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = " . $args["id"];
        //$sql = "SELECT _id id_revision, id_diseno, id_orden, revision, estatus, detalles FROM revisiones a WHERE id_orden = " . $args["id"] . " ORDER BY _id DESC";
        $sql = "SELECT estatus, detalles FROM revisiones WHERE _id = " . $args["id"];

        // $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $object = $localConnection->goQuery()[0];

        $response->getBody()->write(json_encode($object));
        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Datos para la revisiond e trabajos

    $app->get('/revision/trabajos', function (Request $request, Response $response, array $args) {

        // PREPARAR FECHAS

        $myDate = new CustomTime();

        $now = $myDate->today();



        $sql = "SELECT a._id id_lotes_detalles, a.id_orden, b.name producto, b.cantidad, c.nombre empleado, d.estatus, d._id id_pagos, e.status estatus_orden FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id JOIN empleados c ON a.id_empleado = c._id JOIN pagos d ON d.id_lotes_detalles = a._id JOIN ordenes e ON e._id = a.id_orden WHERE (e.status = 'Activa' OR e.status = 'Pausada' OR e.status = 'En espera') AND d.estatus = 'aprobado'";



        $object["sql"] = $sql;



        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Update estatus de pago

    $app->get('/revision/actualizar-estatus-de-pago/{estatus}/{id_pago}', function (Request $request, Response $response, array $args) {



        $sql = "UPDATE pagos SET estatus = '" . $args["estatus"] . "' WHERE _id = " . $args["id_pago"];

        $object["sql"] = $sql;



        $localConnection = new LocalDB($sql);

        $object['save'] = $localConnection->goQuery();

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });

    /** Empleados */

    // Control de de estado del proceso de produccion del empleado

    $app->post('/empleados/registrar-paso/{tipo}/{departamento}/{id_lotes_detalles}', function (Request $request, Response $response, array $args) {
        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();
        $sql = "";
        $object["departamento"] = $args["departamento"];
        $object["tipo"] = $args["tipo"];

        if ($args["tipo"] === "inicio") {
            $campo = "fecha_inicio";
            $progreso = "en curso";

            // REGISTRAR EL PASO ACTUAL EN lotes
            $sql = "SELECT id_orden FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"] . ";";
            $object["sql_total_pendientes"] = $sql;
            $localConnection = new LocalDB($sql);
            $object["id_orden"] = $localConnection->goQuery()[0]["id_orden"];

            $sqln = "UPDATE lotes SET paso = '" . $args["departamento"] . "' WHERE id_orden = " . $object["id_orden"];
            $object["sql_update_lotes"] = $sqln;
            $localConnection = new LocalDB($sqln);
            $object["response_update"] = $localConnection->goQuery();
        }

        if ($args["tipo"] === "fin") {
            $sqle = "SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"];
            $localConnection = new LocalDB($sqle);
            $respLotesDetalles = $localConnection->goQuery();

            $object["resp"] = $respLotesDetalles;

            if ($args["departamento"] === "Costura") {
                $sqlpr = "SELECT id_woo FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"];
                $localConnection = new LocalDB($sqlpr);
                $res_lotes_detalles = $localConnection->goQuery()[0]["id_woo"];

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
                $localConnection = new LocalDB($sqlc);
                $comisionEmpleado = $localConnection->goQuery();
                $object["comision"] = $respLotesDetalles;

                $calculo_pago = floatval($comisionEmpleado[0]["comision"]) * floatval($respLotesDetalles[0]["unidades"]);
                // $monto_pago = number_format($calculo_pago, 2);
                $monto_pago = $calculo_pago;
                $object["monto_pago"] = $monto_pago;
            }

            // GUARDAR PAGO
            $sqlxxx = "SELECT id_empleado FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"];
            $localConnection = new LocalDB($sqlxxx);
            $miEmpleado = $localConnection->goQuery();

            $sql .= "INSERT INTO pagos(cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (" . $respLotesDetalles[0]["unidades"] . ", " . $args["id_lotes_detalles"] . ", 'aprobado' , " . $monto_pago . ", " . $miEmpleado[0]["id_empleado"] . ", '" . $args["departamento"] . "');";
            $campo = "fecha_terminado";
            $progreso = "terminada";
        }

        // ACTUALIZAR DATOS DE INICIO DE TAREA
        $sql .= "UPDATE lotes_detalles SET " . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $args["id_lotes_detalles"];
        // $object["sql"] = $sql;
        $localConnection = new LocalDB($sql);
        $object['items'] = $localConnection->goQuery();
        // $sql = "SELECT `id_empleado` FROM lotes_detalles WHERE _id = 1";
        // $localConnection = new LocalDB($sql);
        // $object['id_empleado'] = $localConnection->goQuery()[0]["id_empleado"];

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Resgistrar pago del empleado en el momento que indica que ha terminado su tarea
    $app->get('/empleados/registrar-pago/{id_lotes_detalles}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT id_empleado FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"];
        $localConnection = new LocalDB($sql);
        $miEmpleado = $localConnection->goQuery();

        $sql = "INSERT INTO pagos(id_lotes_detalles, estatus, id_empleado) VALUES (" . $args["id_lotes_detalles"] . ", 'aprobado', " . $miEmpleado["id_empleado"] . ")";
        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);
        $object['items'] = $localConnection->goQuery();
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener ordenes asociadas a los empleados
    $app->get('/empleados/ordenes-asignadas/{id_empleado}', function (Request $request, Response $response, array $args) {

        $sql = "SELECT c.prioridad, b.unidades_solicitadas, b.unidades_solicitadas piezas_actuales, b.fecha_inicio, b.fecha_terminado, b._id id_lotes_detalles, b.departamento, a.id_orden, a.id_woo, a._id id_ordenes_productos, a.name producto, b.id_empleado, a.talla, a.corte, a.tela, b.departamento, c.prioridad, b.progreso, b.detalles detalles_revision FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos LEFT JOIN lotes c ON c._id = b.id_orden WHERE b.id_empleado = " . $args["id_empleado"] . " AND b.progreso NOT LIKE 'terminada' ORDER BY c.prioridad DESC, b.progreso ASC, b.id_orden ASC";

        // $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);
        $items = $localConnection->goQuery();
        $object['items'] = $items;

        $sql = "SELECT a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = " . $args['id_empleado'] . " AND progreso = 'en curso'";
        // $object['sql_en_curso'] = $sql;
        $localConnection = new LocalDB($sql);
        $object['trabajos_en_curso'] = $localConnection->goQuery();

        // BUSCAR PAGOS EXISTENTES PARA LOS REGISTROS ENCONTRADOS EN EL PASO ANTERIOR
        $object["pagos"] = [];
        if (empty($items)) {
            $object["pagos"] = [];
        } else {
            foreach ($items as $key => $item_lote) {
                $sqlx = "SELECT id_lotes_detalles, monto_pago, estatus, fecha_pago FROM pagos WHERE id_lotes_detalles = " . $item_lote["id_lotes_detalles"];
                // $object["sql_pagos"][] = $sqlx;
                $localConnectionx = new LocalDB($sqlx);
                $tmpPago = $localConnectionx->goQuery();

                if (!empty($tmpPago)) {
                    $object["pagos"][] = $tmpPago;
                }
            }
        }

        $object['fields'][0]['key'] = "nombre";
        $object['fields'][0]['label'] = "Nombre";
        $object['fields'][1]['key'] = "username";
        $object['fields'][1]['label'] = "Usuario";
        $object['fields'][2]['key'] = "departamento";
        $object['fields'][2]['label'] = "Departamento";
        $object['fields'][3]['key'] = "acciones";
        $object['fields'][3]['label'] = "Acciones";

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    // SSE Obtener ordenes asociadas a los empleados
    $app->get('/sse/empleados/ordenes-asignadas/{id_empleado}', function (Request $request, Response $response, array $args) {

        $sql = "SELECT c.prioridad, b.unidades_solicitadas, b.unidades_solicitadas piezas_actuales, b.fecha_inicio, b.fecha_terminado, b._id id_lotes_detalles, b.departamento, a.id_orden, a.id_woo, a._id id_ordenes_productos, a.name producto, b.id_empleado, a.talla, a.corte, a.tela, b.departamento, c.prioridad, b.progreso, b.detalles detalles_revision FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos LEFT JOIN lotes c ON c._id = b.id_orden WHERE b.id_empleado = " . $args["id_empleado"] . " AND b.progreso NOT LIKE 'terminada' ORDER BY c.prioridad DESC, b.progreso ASC, b.id_orden ASC";
        $localConnection = new LocalDB($sql);
        $items = $localConnection->goQuery();

        $obj[0]["sql"] = $sql;
        $obj[0]["name"] = "items";

        $sql = "SELECT a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = " . $args['id_empleado'] . " AND progreso = 'en curso'";
        // $object['sql_en_curso'] = $sql;
        // $object['trabajos_en_curso'] = $localConnection->goQuery();

        $obj[1]["sql"] = $sql;
        $obj[1]["name"] = "trabajos_en_curso";

        // BUSCAR PAGOS EXISTENTES PARA LOS REGISTROS ENCONTRADOS EN EL PASO ANTERIOR
        $object["pagos"] = [];
        if (empty($items)) {
            $object["pagos"] = [];
        } else {
            foreach ($items as $key => $item_lote) {
                $sqlx = "SELECT id_lotes_detalles, monto_pago, estatus, fecha_pago FROM pagos WHERE id_lotes_detalles = " . $item_lote["id_lotes_detalles"];
                $obj[2]["sql"][] = $sqlx;
                $obj[2]["name"] = "pagos";
                // $localConnectionx = new LocalDB($sqlx);
                // $tmpPago = $localConnectionx->goQuery();

                /* if (!empty($tmpPago)) {
                $object["pagos"][] = $tmpPago;
                } */
            }
        }

        $sse = new SSE($obj);
        $sse->SsePrint();

        $object['fields'][0]['key'] = "nombre";
        $object['fields'][0]['label'] = "Nombre";
        $object['fields'][1]['key'] = "username";
        $object['fields'][1]['label'] = "Usuario";
        $object['fields'][2]['key'] = "departamento";
        $object['fields'][2]['label'] = "Departamento";
        $object['fields'][3]['key'] = "acciones";
        $object['fields'][3]['label'] = "Acciones";

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });



    // Obtener todos los empleados

    $app->get('/empleados', function (Request $request, Response $response) {

        $sql = "SELECT _id, _id acciones, username, password, nombre, email, departamento, comision, acceso FROM empleados ORDER BY nombre ASC";

        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();



        // $object['fields'][0]['key']   = "_id";

        // $object['fields'][0]['label'] = "ID";

        $object['fields'][0]['key'] = "nombre";

        $object['fields'][0]['label'] = "Nombre";

        $object['fields'][1]['key'] = "username";

        $object['fields'][1]['label'] = "Usuario";

        $object['fields'][2]['key'] = "departamento";

        $object['fields'][2]['label'] = "Departamento";

        $object['fields'][3]['key'] = "acciones";

        $object['fields'][3]['label'] = "Acciones";



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Nuevo Empleado

    $app->post('/empleados/nuevo', function (Request $request, Response $response) {

        $miEmpleado = $request->getParsedBody();

        $object['miEmpleado'] = $miEmpleado;



        //  $newJson =$request->getParsedBody();

        $miEmpleado = $request->getParsedBody();



        //  $miEmpleado = json_decode($newJson['orden']);



        $object['response'] = $miEmpleado;



        // PREPARAR FECHAS

        $myDate = new CustomTime();

        $now = $myDate->today();



        // Crear estructura de valores para insertar nuevo cliente

        $values = "(";

        $values .= "'" . $now . "',";

        $values .= "'" . $miEmpleado['acceso'] . "',";

        $values .= "'" . $miEmpleado['departamento'] . "',";

        $values .= "'" . $miEmpleado['email'] . "',";

        $values .= "'" . $miEmpleado['nombre'] . "',";

        $values .= "'" . $miEmpleado['password'] . "',";

        $values .= "'" . $miEmpleado['username'] . "')";



        $sql = "INSERT INTO empleados (`moment`, `acceso`, `departamento`, `email`, `nombre`, `password`, `username`) VALUES " . $values;

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Elditar Empleados

    $app->post('/empleados/editar', function (Request $request, Response $response) {

        $miEmpleado = $request->getParsedBody();

        // Crear estructura de valores para insertar nuevo cliente

        $values = "username='" . $miEmpleado['username'] . "',";

        $values = "nombre='" . $miEmpleado['nombre'] . "',";

        $values = "departamento='" . $miEmpleado['departamento'] . "',";

        $values = "acceso='" . $miEmpleado['acceso'] . "',";

        $values .= "password='" . $miEmpleado['password'] . "',";

        $values .= "email='" . $miEmpleado['email'] . "',";

        $values .= "comision='" . $miEmpleado['comision'] . "'";



        $sql = "UPDATE empleados SET " . $values . " WHERE _id = " . $miEmpleado['_id'];

        $object['sql'] = $sql;

        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Eliminar Empleados

    $app->post('/empleados/eliminar', function (Request $request, Response $response) {

        $miEmpleado = $request->getParsedBody();

        $object['miEmpleado'] = $miEmpleado;

        $sql = "DELETE FROM empleados WHERE _id =  " . $miEmpleado['id'];

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Obtener empelados de produccion y diseño y los demas tambien...

    $app->get('/empleados/produccion/asignacion', function (Request $request, Response $response) {

        /* $sql = "SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC";
        $sse = new SSE($sql);
        $events = $sse->getData();
        $response->getBody()->write(json_encode($events)); */


        // $sql = "SELECT _id, username, nombre, comision, departamento FROM empleados WHERE departamento = 'Producción' OR departamento = 'Diseño' ORDER BY nombre";

        $sql = "SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC";
        $localConnection = new LocalDB($sql);
        $object['response'] = $localConnection->goQuery();
        $response->getBody()->write(json_encode($object['response']));

        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /** Fin Empleados */



    /** INSUMOS */

    // OBTENER DETALLES DEL INSUMO

    $app->get('/insumos/{id_insumo}', function (Request $request, Response $response, array $args) {



        $sql = "SELECT * FROM inventario WHERE _id = " . $args['id_insumo'];



        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // NUEVO INSUMO

    $app->post("/insumos/nuevo", function (Request $request, Response $response, $args) {

        $miInsumo = $request->getParsedBody();

        // PREPARAR FECHAS

        $myDate = new CustomTime();

        $now = $myDate->today();



        $values = "(";

        $values .= "'" . $now . "',";

        $values .= "'" . $miInsumo['insumo'] . "',";

        $values .= "'" . $miInsumo['departamento'] . "',";

        $values .= "'" . $miInsumo['unidad'] . "',";

        $values .= "'" . $miInsumo['cantidad'] . "')";



        $sql = "INSERT INTO inventario (moment, insumo, departamento, unidad, cantidad) VALUES " . $values;

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['data'] = json_encode($localConnection->goQuery());



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // EDITAR INSUMO

    $app->post("/insumos/editar", function (Request $request, Response $response, $args) {

        //  $newJson =$request->getParsedBody();

        $miInsumo = $request->getParsedBody();



        //  $miInsumo = json_decode($newJson['orden']);



        $object['response'] = $miInsumo;



        // Crear estructura de valores para insertar nuevo cliente

        $values = "insumo='" . $miInsumo['insumo'] . "',";

        $values .= "unidad='" . $miInsumo['unidad'] . "',";

        $values .= "cantidad='" . $miInsumo['cantidad'] . "',";

        $values .= "departamento='" . $miInsumo['departamento'] . "'";



        $sql = "UPDATE inventario SET " . $values . " WHERE _id = " . $miInsumo['_id'];

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['data'] = json_encode($localConnection->goQuery());



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // OBTENER INSUMOS PERTENECIENTES A UNA ORDEN



    // Eliminar Insumos

    $app->post('/insumos/eliminar', function (Request $request, Response $response) {

        $miEmpleado = $request->getParsedBody();

        $object['miEmpleado'] = $miEmpleado;

        $sql = "DELETE FROM inventario WHERE _id =  " . $miEmpleado['id'];

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Insumos por empleado

    $app->get('/inventario-movimientos/{id_orden}/{id_empleado}', function (Request $request, Response $response, array $args) {



        $sql = "SELECT * FROM ordenes_productos WHERE id_orden = " . $args["id_orden"] . " AND id_empleado = " . $args["id_empleado"];

        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();



        $sql = "SELECT b._id, a._id id_insumo, a.cantidad, a.unidad, a.insumo FROM inventario a JOIN inventario_movimientos b ON a._id = b.id_insumo  WHERE b.id_orden = " . $args["id_orden"] . " AND b.id_empleado = " . $args["id_empleado"];

        $object["sql_01"] = $sql;

        $localConnection = new LocalDB($sql);

        $object['movimientos'] = $localConnection->goQuery();



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Insumos historial por orden (Verificar si se han hecho cambios previamente en el valor de las cantidades)

    $app->get('/inventario/historial/{id_orden}', function (Request $request, Response $response, array $args) {



        $sql = "SELECT id_insumo, valor_inicial, valor_final, departamento FROM inventario_movimientos WHERE id_orden = " . $args["id_orden"];

        $localConnection = new LocalDB($sql);

        $object['items'] = $localConnection->goQuery();

        $object['sql'] = $sql;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Crear nuevo insumo asignado a empleados

    $app->post('/inventario-movimientos/nuevo', function (Request $request, Response $response) {

        $miInsumo = $request->getParsedBody();



        $object["body"] = $miInsumo;

        // Verificar existencia del registro

        $sql = "SELECT _id FROM inventario_movimientos WHERE id_orden = " . $miInsumo["id_orden"] . " AND id_empleado = " . $miInsumo["id_empleado"] . " AND id_producto = " . $miInsumo["id_producto"] . " AND id_insumo = " . $miInsumo["id_insumo"] . " AND departamento = '" . $miInsumo["departamento"] . "'";

        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $object['miinsumo'] = json_encode($localConnection->goQuery());



        if (empty(json_decode($object["miinsumo"]))) {

            $sql = "SELECT cantidad, insumo, unidad FROM inventario WHERE _id = " . $miInsumo["id_insumo"];

            $localConnection = new LocalDB($sql);

            $cantidad = $localConnection->goQuery();

            $object["cantidad_Recuperada"] = $cantidad;



            // PREPARAR FECHAS

            $myDate = new CustomTime();

            $now = $myDate->today();



            $values = "'" . $now . "',";

            $values .= "'" . $miInsumo["departamento"] . "',";

            $values .= $miInsumo["id_empleado"] . ",";

            $values .= $miInsumo["id_insumo"] . ",";

            $values .= $miInsumo["id_orden"] . ",";

            $values .= "'" . $cantidad[0]["cantidad"] . "',";

            $values .= $miInsumo["id_producto"];



            $sql = "INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (" . $values . ")";

            $object["sql"] = $sql;

            $localConnection = new LocalDB($sql);

            $object['insert'] = json_encode($localConnection->goQuery());
        }



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Actualizar cantidad del insumo desde produccion

    $app->post('/inventario-movimientos/update-insumo', function (Request $request, Response $response) {

        $miInsumo = $request->getParsedBody();



        $sql = "UPDATE inventario_movimientos SET valor_inicial = '" . $miInsumo["cantidad"] . "'  WHERE _id =  " . $miInsumo['id_insumo'] . ";";

        $sql .= "UPDATE inventario SET cantidad = '" . $miInsumo["cantidad"] . "' WHERE _id =  " . $miInsumo['id_orden'] . ";";

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Actualizar prioridad del lote

    $app->post('/inventario-movimientos/update-prioridad', function (Request $request, Response $response) {

        $prioridad = $request->getParsedBody();



        $sql = "UPDATE lotes SET prioridad = " . $prioridad["prioridad"] . " WHERE id_orden = " . $prioridad["id"];

        $object['sql'] = $sql;



        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($prioridad));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Eliminar insumo asignado

    $app->post('/inventario-movimientos/eliminar', function (Request $request, Response $response) {

        $data = $request->getParsedBody();



        $sql = "DELETE FROM `inventario_movimientos` WHERE _id = " . $data["id"];

        $localConnection = new LocalDB($sql);

        $object['response'] = json_encode($localConnection->goQuery());

        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Reporte de insumos por número de orden

    $app->get('/insumos/reporte/orden/{id}', function (Request $request, Response $response, array $args) {



        $sql = "SELECT a.id_orden,  b.insumo, a.valor_inicial, a.valor_final, a.id_producto FROM inventario_movimientos a JOIN inventario b ON a.id_insumo = b._id WHERE a.id_orden = " . $args["id"] . " ORDER BY a.id_producto";

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        // $object['fields'][0]['key']    = "id_orden";

        // $object['fields'][0]['label']  = "Orden";

        $object['fields'][0]['key'] = "insumo";

        $object['fields'][0]['label'] = "Insumo";

        $object['fields'][0]['sortable'] = true;



        $object['fields'][1]['key'] = "valor_inicial";

        $object['fields'][1]['label'] = "Valor Inicial";

        // $object['fields'][1]['sortable'] = true;



        $object['fields'][2]['key'] = "valor_final";

        $object['fields'][2]['label'] = "Valor Final";

        // $object['fields'][2]['sortable'] = true;



        $object['fields'][3]['key'] = "id_producto";

        $object['fields'][3]['label'] = "Producto";

        $object['fields'][3]['sortable'] = true;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    // Reporte de insumos por insumo

    $app->get('/insumos/reporte/insumos/{id}', function (Request $request, Response $response, array $args) {



        $sql = "SELECT a.id_orden, b.nombre, c.insumo, a.valor_inicial, a.valor_final, a.moment FROM inventario_movimientos a JOIN empleados b ON a.id_empleado = b._id JOIN inventario c ON a.id_insumo = c._id WHERE a.id_insumo =" . $args["id"] . " ORDER BY c.insumo";

        $object["sql"] = $sql;

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        // $object['fields'][0]['key']    = "id_orden";

        // $object['fields'][0]['label']  = "Orden";

        $object['fields'][0]['key'] = "id_orden";

        $object['fields'][0]['label'] = "Orden";

        // $object['fields'][0]['sortable'] = true;



        $object['fields'][1]['key'] = "valor_inicial";

        $object['fields'][1]['label'] = "Valor Inicial";

        // $object['fields'][1]['sortable'] = true;



        $object['fields'][2]['key'] = "valor_final";

        $object['fields'][2]['label'] = "Valor Final";

        // $object['fields'][2]['sortable'] = true;



        $object['fields'][3]['key'] = "nombre";

        $object['fields'][3]['label'] = "Empleado";



        $object['fields'][4]['key'] = "fecha";

        $object['fields'][4]['label'] = "moment";

        // $object['fields'][3]['sortable'] = true;



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /** FIN INSUMOS */



    /** INVENTARIO */

    $app->get('/inventario', function (Request $request, Response $response, array $args) {



        $sql = "SELECT * FROM inventario ORDER BY insumo ASC;";

        $localConnection = new LocalDB($sql);

        $object["items"] = $localConnection->goQuery();



        $object['fields'][0]['key'] = "insumo";

        $object['fields'][0]['label'] = "NOMBRE";

        $object['fields'][1]['key'] = "departamento";

        $object['fields'][1]['label'] = "DEPARTAMENTO";

        $object['fields'][2]['key'] = "unidad";

        $object['fields'][2]['label'] = "UNIAD";

        $object['fields'][3]['key'] = "cantidad";

        $object['fields'][3]['label'] = "CANTIDAD";

        $object['fields'][4]['key'] = "_id";

        $object['fields'][4]['label'] = "ACCIONES";



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /** FIN INVENTARIO */



    /** ASISTENCIAS */

    $app->get('/asistencias/tabla', function (Request $request, Response $response, array $args) {
        $object['fields'][0]['key'] = "moment";
        $object['fields'][0]['label'] = "ASISTENCIA";
        $object['fields'][1]['key'] = "nombre";
        $object['fields'][1]['label'] = "NOMBRE";
        $object['fields'][2]['key'] = "diarias";
        $object['fields'][2]['label'] = "DIARIAS";
        $object['fields'][3]['key'] = "semanales";
        $object['fields'][3]['label'] = "SEMANA";
        $object['fields'][4]['key'] = "id_empleado";
        $object['fields'][4]['label'] = "ACCIONES";


        // OBTENER TODOS LOS EMPLEADOS

        $sql = "SELECT * FROM empleados ORDER BY nombre ASC";

        $localConnection = new LocalDB($sql);

        $object['empleados'] = $localConnection->goQuery();

        // TODO las dos variables siguinetes estan mal arreglar esto

        $today = null;

        $date = null;



        // OBTENER ASISTENCIAS DIARIAS

        $sql = "SELECT _id, moment, id_empleado, registro, (UNIX_TIMESTAMP(moment) - 3600) AS segundos FROM asistencias WHERE moment LIKE '" . $today . "%'";

        $mod_date = strtotime($date . "+ 0 days");

        $localConnection = new LocalDB($sql);

        $object['diarias'] = $localConnection->goQuery();



        // NUEVO REPORTE

        $sql = "SELECT a.id_empleado, b.username, a.moment, DATE(a.moment) fecha, UNIX_TIMESTAMP(a.moment) - 3600 timestamp, DAYNAME(a.moment) dia, a.registro FROM asistencias a JOIN empleados b ON a.id_empleado = b._id WHERE WEEK(a.moment) = WEEK(NOW());";

        $today . "%'";

        $localConnection = new LocalDB($sql);

        $object['reporte'] = $localConnection->goQuery();



        // ASISTENCIAS SEMANA

        $today = date("Y-m-d", $mod_date);

        // $sem   = $args['semana'];



        $sql = "SELECT

            b._id,

            b.username empleado

        FROM asistencias a

        JOIN empleados b ON b._id = a.id_empleado

        WHERE WEEK(a.moment) = WEEK('" . $today . "')

        GROUP BY b.username

        ORDER BY

            b.username ASC,

            a.moment ASC";



        $today . "%'";

        $localConnection = new LocalDB($sql);

        $object['semana'] = $localConnection->goQuery();



        // ASISTENCIAS DIARIAS

        /* $sql               = "SELECT a.id_empleado, b.username, a.moment, DATE(a.moment) fecha, UNIX_TIMESTAMP(a.moment) - 3600 timestamp, DAYNAME(a.moment) dia, a.registro FROM asistencias a JOIN empleados b ON a.id_empleado = b._id WHERE DATE(a.moment) = CURDATE();";
        $localConnection   = new LocalDB($sql);
        $object['diarias'] = $localConnection->goQuery(); */



        // $sql = "SELECT _id, nombre, _id diarias, _id semanales, _id acciones FROM empleados";

        /* $sql = "SELECT
        a._id id_asistencia,
        b._id id_empleado,
        b.nombre nombre,
        a.registro,
        DATE_FORMAT(a.moment, '%d/%m/%Y') fecha,
        DATE_FORMAT(a.moment, '%H:%i %p') hora,
        DATE_FORMAT(a.moment, '%W') dia,
        UNIX_TIMESTAMP(a.moment) - 3600 timestamp, -- RECALCULAR PORQUE EL SERVIDOR DE SITEGROUND APESTA
        a.moment,
        a.moment diarias,
        a.moment semanales,
        -- WEEK(a.moment) semana,
        WEEK(NOW()) semana_actual
        FROM asistencias a
        JOIN empleados b ON b._id = a.id_empleado
        WHERE WEEK(a.moment) = WEEK(NOW())
        ORDER BY
        b.username ASC,
        a.moment ASC
        ";
        $localConnection = new LocalDB($sql);
        $object['items'] = $localConnection->goQuery(); */



        $response->getBody()->write(json_encode($object));



        return $response

            ->withHeader('Content-Type', 'application/json')

            ->withStatus(200);
    });



    /** FIN ASISTENCIAS */
};