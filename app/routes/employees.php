<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {


    // Obtener los empleados activos
    $app->get('/empleados', function (Request $request, Response $response) {
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
        $idEmp = ID_EMPRESA;
        $sql = 'SELECT
            a.id_usuario AS _id,
            a.email AS username,
            a.password,
            a.nombre,
            a.email,
            a.telefono,
            a.departamento,
            a.comision,
            a.comision_porcentaje,
            a.salario_tipo,
            a.salario_monto,
            a.salario_periodo,
            a.comision_tipo,
            a.acceso,
            a.dni,
            a.fecha_ingreso,
            a.id_seguridad_social,
            up.ultima_semana_pagada,
            up.ultimo_anio_pagado,
            DATE_FORMAT(fr.ultima_fecha_pago, "%d/%m/%Y") ultima_fecha_pago,
            IFNULL(deps.departamentos, "[]") AS departamentos,
            IFNULL(carga.carga_familiar, "[]") AS carga_familiar
        FROM
            api_empresas.empresas_usuarios a
        LEFT JOIN (
            SELECT
                p1.id_empleado,
                ps1.numero_semana AS ultima_semana_pagada,
                YEAR(p1.moment) AS ultimo_anio_pagado
            FROM ' . LOCAL_DB . '.pagos p1
            JOIN ' . LOCAL_DB . '.pagos_salarios ps1 ON ps1.id_pago = p1._id
            INNER JOIN (
                SELECT id_empleado, MAX(moment) as max_m
                FROM ' . LOCAL_DB . '.pagos
                GROUP BY id_empleado
            ) m ON m.id_empleado = p1.id_empleado AND m.max_m = p1.moment
        ) up ON up.id_empleado = a.id_usuario
        LEFT JOIN (
            SELECT
                pa.id_empleado,
                MAX(pa.fecha_pago) AS ultima_fecha_pago
            FROM ' . LOCAL_DB . '.pagos pa
            LEFT JOIN ' . LOCAL_DB . '.pagos_salarios ps ON ps.id_pago = pa._id
            WHERE pa.fecha_pago IS NOT NULL
              AND (WEEK(pa.fecha_pago, 1) = WEEK(NOW(), 1) OR ps.numero_semana = WEEK(NOW(), 1))
              AND YEAR(pa.fecha_pago) = YEAR(NOW())
            GROUP BY pa.id_empleado
        ) fr ON fr.id_empleado = a.id_usuario
        LEFT JOIN (
            SELECT
                b.id_empleado,
                IFNULL(CONCAT("[", GROUP_CONCAT(
                    DISTINCT CONCAT("{\"id\":", b.id_departamento, ",\"nombre\":\"", c.departamento, "\"}")
                    SEPARATOR ","), "]"), "[]") AS departamentos
            FROM
                api_empresas.empresas_usuarios_departamentos b
            LEFT JOIN ' . LOCAL_DB . '.departamentos c ON c._id = b.id_departamento
            GROUP BY
                b.id_empleado
        ) deps ON deps.id_empleado = a.id_usuario
        LEFT JOIN (
            SELECT
                d.id_empleado,
                IFNULL(CONCAT("[", GROUP_CONCAT(
                    DISTINCT CONCAT("{\"id_carga\":", d.id_carga, ",\"nombre_completo\":\"", d.nombre_completo, "\",\"cedula_o_id\":\"", d.cedula_o_id, "\",\"parentesco\":\"", d.tipo_relacion, "\",\"fecha_nacimiento\":\"", d.fecha_nacimiento, "\",\"es_deducible\":", d.es_deducible_impuesto, "}")
                    SEPARATOR ","), "]"), "[]") AS carga_familiar
            FROM
                ' . LOCAL_DB . '.salario_carga_familiar d
            GROUP BY
                d.id_empleado
        ) carga ON carga.id_empleado = a.id_usuario
        WHERE
            a.activo = 1 AND a.id_empresa = ' . ID_EMPRESA . ';';
        // $object['sql'] = $sql; // Removido para producción
        $items = $localConnection->goQuery($sql);

        if (isset($items['status']) && $items['status'] === 'error') {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode([
                'statusCode' => 500,
                'error' => [
                    'type' => 'SERVER_ERROR',
                    'description' => $items['message']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Decodificar el campo `departamentos` y `carga_familiar`
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

        $object['items'] = $items;

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][0]['sortable'] = true;
        $object['fields'][1]['key'] = 'username';
        $object['fields'][1]['label'] = 'Usuario';
        $object['fields'][1]['sortable'] = true;
        $object['fields'][2]['key'] = 'departamentos';
        $object['fields'][2]['label'] = 'Departamentos';
        $object['fields'][3]['key'] = 'acciones';
        $object['fields'][3]['label'] = 'Acciones';

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });


    // Nuevo Empleado
    $app->post('/empleados/activacion', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Actualizar estado del empleado
        $sql = "UPDATE api_empresas.empresas_usuarios SET activo = '{$miEmpleado['activo']}' WHERE id_usuario = {$miEmpleado['id_empleado']}";
        $object['response'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Nuevo Empleado
    $app->post('/empleados/nuevo', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();

        /* $response->getBody()->write(json_encode($miEmpleado));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); */

        // ////////////////////////////////

        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Validar que el email no exista
        $checkEmailSql = "SELECT COUNT(*) as count FROM api_empresas.empresas_usuarios WHERE email = '" . $miEmpleado['email'] . "'";
        $emailCheck = $localConnection->goQuery($checkEmailSql);

        if (isset($emailCheck[0]['count']) && $emailCheck[0]['count'] > 0) {
            $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => 'El email ya se encuentra registrado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear estructura de valores para insertar nuevo empleado
        $comision = 0;
        $comision_porcentaje = 0;

        // Lógica para manejar diferentes tipos de comisión
        if ($miEmpleado['comsion_tipo'] === 'fija') {
            $comision = $miEmpleado['comision'];
        } elseif ($miEmpleado['comsion_tipo'] === 'porcentaje') {
            $comision_porcentaje = $miEmpleado['comision_porcentaje'];
        }
        // Para 'variable' no se actualiza ningún campo de comisión

        $values = '(';
        $values .= "'" . $now . "',";
        $values .= "'" . $miEmpleado['acceso'] . "',";
        $values .= "'" . $comision . "',";
        $values .= "'" . $miEmpleado['comsion_tipo'] . "',";
        $values .= "'" . $comision_porcentaje . "',";
        $values .= "'" . $miEmpleado['email'] . "',";
        $values .= "'" . $miEmpleado['telefono'] . "',";
        $values .= "'" . $miEmpleado['nombre'] . "',";
        $values .= "'" . ID_EMPRESA . "',";
        $values .= "'" . $miEmpleado['password'] . "',";
        $values .= "'" . $miEmpleado['salario_tipo'] . "',";
        $values .= "'" . $miEmpleado['salario'] . "',";
        $values .= "'" . $miEmpleado['periodo_pago'] . "',";
        $values .= "'" . $miEmpleado['id_legal'] . "',";
        $values .= "'" . $miEmpleado['fecha_ingreso'] . "',";
        $values .= "'" . $miEmpleado['id_seguridad_social'] . "')";

        $sql = 'INSERT INTO api_empresas.empresas_usuarios (`moment`, `acceso`, `comision`, `comision_tipo`, `comision_porcentaje`, `email`, `telefono`, `nombre`, `id_empresa`, `password`, `salario_tipo`, `salario_monto`, `salario_periodo`, `dni`, `fecha_ingreso`, `id_seguridad_social`) VALUES ' . $values;
        $object['response'] = $localConnection->goQuery($sql);
        $lastInsert = $object['response']['insert_id'];

        // Guardar departamentos asignados al empleado
        $sql = 'DELETE FROM api_empresas.empresas_usuarios_departamentos WHERE id_empleado = ' . $lastInsert;
        $object['response_delete'] = $localConnection->goQuery($sql);

        $departamentos = explode(',', $miEmpleado['departamentos']);
        $sql = '';
        foreach ($departamentos as $id) {
            $sql .= "INSERT INTO api_empresas.empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES ({$lastInsert}, {$id});";
        }
        $object['response_deps'] = $localConnection->goQuery($sql);

        // Procesar carga familiar si existe
        if (isset($miEmpleado['dependientes_json']) && !empty($miEmpleado['dependientes_json'])) {
            $localConnection = new LocalDB();

            $dependientes = json_decode($miEmpleado['dependientes_json'], true);

            if (is_array($dependientes) && count($dependientes) > 0) {
                // Insertar cada dependiente en la tabla salario_carga_familiar
                foreach ($dependientes as $dependiente) {
                    $dep_values = '(';
                    $dep_values .= "'" . $lastInsert . "',";  // id_usuario
                    $dep_values .= "'" . $dependiente['nombre_completo'] . "',";
                    $dep_values .= "'" . $dependiente['cedula_o_id'] . "',";
                    $dep_values .= "'" . $dependiente['parentesco'] . "',";
                    $dep_values .= "'" . $dependiente['fecha_nacimiento'] . "',";
                    $dep_values .= "'" . ($dependiente['es_deducible'] ? 1 : 0) . "')";

                    $sql_dep = 'INSERT INTO salario_carga_familiar (`id_usuario`, `nombre_completo`, `cedula_o_id`, `parentesco`, `fecha_nacimiento`, `es_deducible`) VALUES ' . $dep_values;
                    $object['response_dependientes'][] = $localConnection->goQuery($sql_dep);
                }
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Elditar Empleados
    $app->post('/empleados/editar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Lógica para manejar diferentes tipos de comisión
        $comision = 0;
        $comision_porcentaje = 0;

        // Validar que el email no exista en otro usuario
        if (isset($miEmpleado['email']) && !empty($miEmpleado['email'])) {
            $checkEmailSql = "SELECT COUNT(*) as count FROM api_empresas.empresas_usuarios WHERE email = '" . $miEmpleado['email'] . "' AND id_usuario != " . $miEmpleado['_id'];
            $emailCheck = $localConnection->goQuery($checkEmailSql);

            if (isset($emailCheck[0]['count']) && $emailCheck[0]['count'] > 0) {
                $localConnection->disconnect();
                $response->getBody()->write(json_encode(['error' => 'El email ya se encuentra registrado en otro usuario.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        if ($miEmpleado['comsion_tipo'] === 'fija') {
            $comision = $miEmpleado['comision'];
        } elseif ($miEmpleado['comsion_tipo'] === 'porcentaje') {
            $comision_porcentaje = $miEmpleado['comision_porcentaje'];
        }
        // Para 'variable' no se actualiza ningún campo de comisión

        // Actualizar empleado
        $values = "nombre='" . $miEmpleado['nombre'] . "',";
        // $values .= "departamento='" . $miEmpleado['departamento'] . "',";
        $values .= "acceso='" . $miEmpleado['acceso'] . "',";
        $values .= "password='" . $miEmpleado['password'] . "',";
        $values .= "email='" . $miEmpleado['email'] . "',";
        $values .= "telefono='" . $miEmpleado['telefono'] . "',";
        $values .= "comision_tipo='" . $miEmpleado['comsion_tipo'] . "',";
        $values .= "comision='" . $comision . "',";
        $values .= "comision_porcentaje='" . $comision_porcentaje . "',";
        $values .= "salario_tipo='" . $miEmpleado['salario_tipo'] . "',";
        $values .= "salario_monto='" . $miEmpleado['salario'] . "',";
        $values .= "salario_periodo='" . $miEmpleado['periodo_pago'] . "',";
        $values .= "dni='" . $miEmpleado['id_legal'] . "',";
        $values .= "fecha_ingreso='" . $miEmpleado['fecha_ingreso'] . "',";
        $values .= "id_seguridad_social='" . $miEmpleado['id_seguridad_social'] . "'";

        $sql = 'UPDATE api_empresas.empresas_usuarios SET ' . $values . ' WHERE id_usuario = ' . $miEmpleado['_id'];
        $object['response'] = json_encode($localConnection->goQuery($sql));

        // Limpiar registros anteriores
        $sql = "DELETE FROM api_empresas.empresas_usuarios_departamentos WHERE id_empleado = {$miEmpleado['_id']};";

        $object['response_delete'] = json_encode($localConnection->goQuery($sql));

        // Insertar nuevas asiganciones de departamentos
        $misDeps = explode(',', $miEmpleado['departamentos']);

        if (count($misDeps) > 0) {
            $sql = '';
            // if ($dep != 0) {
            foreach ($misDeps as $id_dep) {
                $sql .= "INSERT INTO api_empresas.empresas_usuarios_departamentos (id_empleado, id_departamento) VALUES ({$miEmpleado['_id']}, {$id_dep});";
            }
            // }
        }
        $object['sql_update'] = $sql;
        $object['response_update'] = json_encode($localConnection->goQuery($sql));

        $localConnection = new LocalDB();

        // Procesar carga familiar - Eliminar registros anteriores y agregar nuevos
        if (isset($miEmpleado['dependientes_json'])) {
            // Eliminar dependientes anteriores
            $sql_delete_dep = "DELETE FROM salario_carga_familiar WHERE id_usuario = {$miEmpleado['_id']}";
            $object['response_delete_dependientes'] = $localConnection->goQuery($sql_delete_dep);

            // Agregar dependientes nuevos si existen
            $dependientes = json_decode($miEmpleado['dependientes_json'], true);
            if (is_array($dependientes) && count($dependientes) > 0) {
                foreach ($dependientes as $dependiente) {
                    $dep_values = '(';
                    $dep_values .= "'" . $miEmpleado['_id'] . "',";  // id_usuario
                    $dep_values .= "'" . $dependiente['nombre_completo'] . "',";
                    $dep_values .= "'" . $dependiente['cedula_o_id'] . "',";
                    $dep_values .= "'" . $dependiente['parentesco'] . "',";
                    $dep_values .= "'" . $dependiente['fecha_nacimiento'] . "',";
                    $dep_values .= "'" . ($dependiente['es_deducible'] ? 1 : 0) . "')";

                    $sql_dep = 'INSERT INTO salario_carga_familiar (`id_usuario`, `nombre_completo`, `cedula_o_id`, `parentesco`, `fecha_nacimiento`, `es_deducible`) VALUES ' . $dep_values;
                    $object['response_dependientes'][] = $localConnection->goQuery($sql_dep);
                }
            }
        }

        $localConnection->disconnect();

        // $response->getBody()->write(json_encode($object));
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Eliminar Empleados
    $app->post('/empleados/eliminar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();
        $localConnection2 = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // 1. VERIFICAR ASIGNACIÓN DE EMPLEADOS `Si ya tiene registrado pagos no se puede eliminar`

        // 1. SOLO DESVINCULAR EMPLEADO (No borrar ni desactivar)

        // Eliminar asignación de tareas 
        $sql = "UPDATE lotes_detalles_empleados_asignados SET id_empleado = NULL WHERE id_empleado = {$miEmpleado['id']}";
        $object['response_lotes_detalles'] = json_encode($localConnection->goQuery($sql));

        // Eliminar de departamentos del usuario
        $sql = 'DELETE FROM empresas_usuarios_departamentos WHERE id_empleado = ' . $miEmpleado['id'] . ';';
        $object['response_departamentos'] = json_encode($localConnection2->goQuery($sql));

        // Ya no se toca la tabla empresas_usuarios ni física ni lógicamente

        $object['message'] = "El empleado ha sido desvinculado de tareas y departamentos. El registro de usuario permanece intacto.";
        $eliminado = false;
        $object['eliminado'] = $eliminado;

        $localConnection->disconnect();
        $localConnection2->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener empelados de produccion y diseño y los demas tambien...
    $app->get('/empleados/produccion/asignacion', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC';
        $object['response'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object['response']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** ASISTENCIAS */

    // Crear nuevo registro en la tabla de asistencias
    $app->post('/asistencias', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'INSERT INTO `asistencias`(`id_empleado`, `registro`, `moment`) VALUES (' . $data['id_empleado'] . ",'" . $data['registro'] . "','" . $data['moment'] . "')";
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/asistencias/semanal', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = "SELECT 
        a._id id_asistencias,
        e._id id_empleado,
        e.nombre AS empleado,
        DATE_FORMAT(a.moment, '%H:%i') AS hora,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        CASE 
        WHEN DAYOFWEEK(a.moment) = 2 THEN 'L'
        WHEN DAYOFWEEK(a.moment) = 3 THEN 'M'
        WHEN DAYOFWEEK(a.moment) = 4 THEN 'X'
        WHEN DAYOFWEEK(a.moment) = 5 THEN 'J'
        WHEN DAYOFWEEK(a.moment) = 6 THEN 'V'
        WHEN DAYOFWEEK(a.moment) = 7 THEN 'S'
        WHEN DAYOFWEEK(a.moment) = 1 THEN 'D'
        END AS dia,
        CASE 
        WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
        WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
        WHEN a.registro = 'entrada_tarde' THEN 'Entrada tarde'
        WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
        END AS registro
        FROM 
        asistencias a
        JOIN 
        empleados e ON a.id_empleado = e._id
        WHERE 
        YEARWEEK(a.moment) = YEARWEEK(NOW())
        ORDER BY 
        e.nombre ASC,
        a.moment ASC;
        ";
        $object['data_semana'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REPORTE DE ASISTENCIAS POR FECHA UNICA
    $app->get('/asistencias/tabla/{fecha}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][1]['key'] = 'moment';
        $object['fields'][1]['label'] = 'Entrada Mañana';
        $object['fields'][2]['key'] = 'moment';
        $object['fields'][2]['label'] = 'Salida Mañana';
        $object['fields'][3]['key'] = 'moment';
        $object['fields'][3]['label'] = 'Entrada Tarde';
        $object['fields'][4]['key'] = 'moment';
        $object['fields'][4]['label'] = 'Salida Tarde';

        // OBTENER TODOS LOS EMPLEADOS
        $sql = 'SELECT * FROM empleados ORDER BY nombre ASC';
        $object['empleados'] = $localConnection->goQuery($sql);

        // TODO las dos variables siguinetes estan mal arreglar esto
        $today = null;
        $date = null;
        // $myDate = new CustomTime();
        // $now = $myDate->today();
        // $fecha = explode(' ', $now); // La fecha la recibimos por args
        $fecha = $args['fecha'];

        // OBTENER ASISTENCIAS DIARIAS
        // $sql = "SELECT a._id id_empleado, b._id id_asistencia, a.nombre, DATE_FORMAT(b.moment, '%h:%i %p') AS hora, DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha, b.registro, b.detalle FROM empleados a LEFT JOIN asistencias b ON b.id_empleado = a._id WHERE b.moment LIKE '" . $fecha . "%' OR a._id > 0;";
        $sql = "SELECT
        a._id id_empleado,
        b._id id_asistencia,
        a.nombre,
        DATE_FORMAT(b.moment, '%h:%i %p') AS hora,
        DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha,
        b.registro,
        b.detalle
        FROM
        empleados a
        LEFT JOIN asistencias b ON
        b.id_empleado = a._id
        WHERE
        (a._id > 0  AND b.moment LIKE '" . $fecha . "%') OR (a._id > 0 AND b.moment IS NULL)
         ORDER BY a.nombre ASC;";
        $object['sql_diarias'] = $sql;
        $mod_date = strtotime($date . '+ 0 days');
        $object['diarias'] = $localConnection->goQuery($sql);

        // NUEVO REPORTE
        $sql = 'SELECT a.id_empleado, b.username, a.moment, DATE(a.moment) fecha, UNIX_TIMESTAMP(a.moment) - 3600 timestamp, DAYNAME(a.moment) dia, a.registro FROM asistencias a JOIN empleados b ON a.id_empleado = b._id WHERE WEEK(a.moment) = WEEK(NOW());';

        $today . "%'";
        $object['reporte'] = $localConnection->goQuery($sql);

        // ASISTENCIAS SEMANA
        $today = date('Y-m-d', $mod_date);

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

        $object['semana'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/asistencias/reporte/resumen/{fecha_inicio}/{fecha_fin}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields_resumen'][0]['key'] = 'nombre';
        $object['fields_resumen'][0]['label'] = 'Nombre';
        $object['fields_resumen'][1]['key'] = 'horas_trabajadas';
        $object['fields_resumen'][1]['label'] = 'Horas Trabajadas';
        $object['fields_resumen'][2]['key'] = 'acciones';
        $object['fields_resumen'][2]['label'] = 'Acciones';

        $object['fields_detallado'][0]['key'] = 'nombre';
        $object['fields_detallado'][0]['label'] = 'Nombre';
        $object['fields_detallado'][1]['key'] = 'registro';
        $object['fields_detallado'][1]['label'] = 'Registro';
        $object['fields_detallado'][2]['key'] = 'hora';
        $object['fields_detallado'][2]['label'] = 'Hora';
        $object['fields_detallado'][3]['key'] = 'fecha';
        $object['fields_detallado'][3]['label'] = 'Fecha';

        // REPORTE RESUMEN
        $sql = "SELECT
            a.id_empleado,
            a.id_empleado acciones,
            b.nombre, 
            ROUND(
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_manana' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_manana' THEN a.moment END)
                                       ) / 60.0,
                         0
                         )
                  +
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_tarde' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_tarde' THEN a.moment END)
                                       ) / 60.0,
                         0
                         ),
                  2
                  ) AS horas_trabajadas
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            GROUP BY a.id_empleado;
            ";
        $object['resumen'] = $localConnection->goQuery($sql);

        // REPORTE DETALLADO
        $sql = "SELECT
            b._id id_empleado,
            b.nombre,
            DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
            DATE_FORMAT(a.moment, '%h:%i %p') AS hora,
            CASE 
            WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
            WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
            WHEN a.registro = 'entrada_tarde' THEN 'Entrada Tarde'
            WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
            ELSE a.registro 
            END AS registro 
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            ORDER BY b.nombre ASC, a.moment ASC,
            FIELD(a.registro, 'entrada_manana', 'salida_manana', 'entrada_tarde', 'salida_tarde');
            ";
        $object['detallado'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /* FIN ASISTENCIAS */

    // =================================================================
    // DASHBOARD EMPLEADOS (ESTADÍSTICAS UNIFICADAS)
    // =================================================================
    $app->get('/empleados/dashboard-stats/{id_empleado}/{id_departamento}', function (Request $request, Response $response, array $args) {
        $id_empleado = intval($args['id_empleado']);
        $id_departamento = intval($args['id_departamento']);

        $localConnection = new LocalDB();
        $finalResponse = [];

        try {
            // =================================================================
            // LÓGICA ESPECÍFICA PARA DISEÑO (ID 7) E IMPRESIÓN (ID 1)
            // =================================================================
            if ($id_departamento == 7 || $id_departamento == 1) {
                // Departamento de Diseño
                if ($id_departamento == 7) {
                    // 1. STATUS DISEÑOS
                    $sqlTerminadas = "SELECT COUNT(*) as count FROM disenos WHERE id_empleado = $id_empleado AND terminado = 1";
                    $terminadasResult = $localConnection->goQuery($sqlTerminadas);
                    $terminadas = !empty($terminadasResult) && !isset($terminadasResult['status']) ? $terminadasResult[0]['count'] : 0;

                    $sqlPendientes = "SELECT COUNT(*) as count FROM disenos WHERE id_empleado = $id_empleado AND terminado = 0";
                    $pendientesResult = $localConnection->goQuery($sqlPendientes);
                    $pendientes = !empty($pendientesResult) && !isset($pendientesResult['status']) ? $pendientesResult[0]['count'] : 0;
                } else {
                    // Departamento de Impresión (ID: 1) - Usar lógica estándar de órdenes
                    $sqlTerminadas = "SELECT COUNT(DISTINCT a.id_orden) as count
                        FROM lotes_detalles_empleados_asignados a
                        WHERE a.id_empleado = $id_empleado 
                        AND a.id_departamento = $id_departamento 
                        AND a.fecha_terminado IS NOT NULL";

                    $terminadasResult = $localConnection->goQuery($sqlTerminadas);
                    $terminadas = !empty($terminadasResult) ? $terminadasResult[0]['count'] : 0;

                    $sqlPendientes = "SELECT COUNT(DISTINCT a.id_orden) as count
                        FROM lotes_detalles_empleados_asignados a
                        JOIN ordenes ord ON ord._id = a.id_orden
                        WHERE a.id_empleado = $id_empleado 
                        AND a.id_departamento = $id_departamento 
                        AND a.fecha_terminado IS NULL
                        AND (ord.status LIKE 'En espera' OR ord.status LIKE 'activa' OR ord.status LIKE 'pausada')";

                    $pendientesResult = $localConnection->goQuery($sqlPendientes);
                    $pendientes = !empty($pendientesResult) ? $pendientesResult[0]['count'] : 0;
                }

                $finalResponse['status'] = [
                    'terminadas' => $terminadas,
                    'activas' => 0,  // Diseño e Impresión no diferencian activas de pendientes
                    'pendientes' => $pendientes
                ];

                // REPOSICIONES
                $sqlReposicionesTerminadas = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 1";
                $reposicionesTerminadasResult = $localConnection->goQuery($sqlReposicionesTerminadas);
                $reposiciones_terminadas = !empty($reposicionesTerminadasResult) && !isset($reposicionesTerminadasResult['status']) ? $reposicionesTerminadasResult[0]['count'] : 0;

                $sqlReposicionesEnCurso = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r LEFT JOIN lotes_detalles_empleados_asignados ldea ON ldea.id_reposicion = r._id WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 0 AND ldea.fecha_inicio IS NOT NULL";
                $reposicionesEnCursoResult = $localConnection->goQuery($sqlReposicionesEnCurso);
                $reposiciones_en_curso = !empty($reposicionesEnCursoResult) && !isset($reposicionesEnCursoResult['status']) ? $reposicionesEnCursoResult[0]['count'] : 0;

                $sqlReposicionesPendientes = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 0 AND NOT EXISTS (SELECT 1 FROM lotes_detalles_empleados_asignados ldea2 WHERE ldea2.id_reposicion = r._id AND ldea2.fecha_inicio IS NOT NULL)";
                $reposicionesPendientesResult = $localConnection->goQuery($sqlReposicionesPendientes);
                $reposiciones_pendientes = !empty($reposicionesPendientesResult) && !isset($reposicionesPendientesResult['status']) ? $reposicionesPendientesResult[0]['count'] : 0;

                $finalResponse['reposiciones'] = [
                    'terminadas' => $reposiciones_terminadas,
                    'en_curso' => $reposiciones_en_curso,
                    'pendientes' => $reposiciones_pendientes
                ];

                // 2. EFICIENCIA (No aplica para diseño ni impresión, retornamos 0)
                $finalResponse['eficiencia'] = [
                    'tiempo_real' => 0,
                    'tiempo_estimado' => 0
                ];

                // 3. PAGOS SEMANALES (Sin JOIN a lotes, usando 'moment' de pagos)
                $sqlPagos = "SELECT 
                        SUM(p.monto_pago) as total_pagado,
                        DATE_FORMAT(p.moment, '%W') as dia,
                        DATE(p.moment) as fecha
                    FROM pagos p
                    WHERE p.id_empleado = $id_empleado
                    AND p.estatus = 'aprobado'
                    AND YEARWEEK(p.moment, 1) = YEARWEEK(NOW(), 1)
                    GROUP BY DATE(p.moment)";

                $pagosResult = $localConnection->goQuery($sqlPagos);
                $finalResponse['pagos_semana'] = $pagosResult;

            } else {
                // =================================================================
                // LÓGICA ESTÁNDAR (PRODUCCIÓN)
                // =================================================================

                // 1. ESTADO DE ÓRDENES
                // Terminadas: Fecha terminado no es nula
                $sqlTerminadas = "SELECT COUNT(DISTINCT a.id_orden) as count
                    FROM lotes_detalles_empleados_asignados a
                    WHERE a.id_empleado = $id_empleado 
                    AND a.id_departamento = $id_departamento 
                    AND a.fecha_terminado IS NOT NULL";

                $terminadasResult = $localConnection->goQuery($sqlTerminadas);
                $terminadas = !empty($terminadasResult) ? $terminadasResult[0]['count'] : 0;

                // Activas: Tienen fecha_inicio pero no fecha_terminado (en curso)
                $sqlActivas = "SELECT COUNT(DISTINCT a.id_orden) as count
                    FROM lotes_detalles_empleados_asignados a
                    JOIN ordenes ord ON ord._id = a.id_orden
                    WHERE a.id_empleado = $id_empleado 
                    AND a.id_departamento = $id_departamento 
                    AND a.fecha_inicio IS NOT NULL
                    AND a.fecha_terminado IS NULL
                    AND (ord.status LIKE 'activa' OR ord.status LIKE 'pausada')";

                $activasResult = $localConnection->goQuery($sqlActivas);
                $activas = !empty($activasResult) ? $activasResult[0]['count'] : 0;

                // Pendientes: No tienen fecha_inicio (no han empezado)
                $sqlPendientes = "SELECT COUNT(DISTINCT a.id_orden) as count
                    FROM lotes_detalles_empleados_asignados a
                    JOIN ordenes ord ON ord._id = a.id_orden
                    WHERE a.id_empleado = $id_empleado 
                    AND a.id_departamento = $id_departamento 
                    AND a.fecha_inicio IS NULL
                    AND a.fecha_terminado IS NULL
                    AND ord.status LIKE 'En espera'";

                $pendientesResult = $localConnection->goQuery($sqlPendientes);
                $pendientes = !empty($pendientesResult) ? $pendientesResult[0]['count'] : 0;


                $finalResponse['status'] = [
                    'terminadas' => $terminadas,
                    'activas' => $activas,
                    'pendientes' => $pendientes
                ];

                // REPOSICIONES
                $sqlReposicionesTerminadas = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 1";
                $reposicionesTerminadasResult = $localConnection->goQuery($sqlReposicionesTerminadas);
                $reposiciones_terminadas = !empty($reposicionesTerminadasResult) && !isset($reposicionesTerminadasResult['status']) ? $reposicionesTerminadasResult[0]['count'] : 0;

                $sqlReposicionesEnCurso = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r LEFT JOIN lotes_detalles_empleados_asignados ldea ON ldea.id_reposicion = r._id WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 0 AND ldea.fecha_inicio IS NOT NULL";
                $reposicionesEnCursoResult = $localConnection->goQuery($sqlReposicionesEnCurso);
                $reposiciones_en_curso = !empty($reposicionesEnCursoResult) && !isset($reposicionesEnCursoResult['status']) ? $reposicionesEnCursoResult[0]['count'] : 0;

                $sqlReposicionesPendientes = "SELECT COUNT(DISTINCT r._id) as count FROM reposiciones r WHERE r.id_empleado = $id_empleado AND r.id_departamento = $id_departamento AND r.terminada = 0 AND NOT EXISTS (SELECT 1 FROM lotes_detalles_empleados_asignados ldea2 WHERE ldea2.id_reposicion = r._id AND ldea2.fecha_inicio IS NOT NULL)";
                $reposicionesPendientesResult = $localConnection->goQuery($sqlReposicionesPendientes);
                $reposiciones_pendientes = !empty($reposicionesPendientesResult) && !isset($reposicionesPendientesResult['status']) ? $reposicionesPendientesResult[0]['count'] : 0;

                $finalResponse['reposiciones'] = [
                    'terminadas' => $reposiciones_terminadas,
                    'en_curso' => $reposiciones_en_curso,
                    'pendientes' => $reposiciones_pendientes
                ];

                // 2. EFICIENCIA DE TIEMPO
                $sqlEficiencia = "
                    WITH TiemposReales AS (
                        SELECT 
                            sub_ldea.id_orden,
                            SUM(
                                CASE 
                                    WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NOT NULL THEN 
                                        TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, sub_ldea.fecha_terminado)
                                    WHEN sub_ldea.fecha_inicio IS NOT NULL AND sub_ldea.fecha_terminado IS NULL THEN 
                                        TIMESTAMPDIFF(SECOND, sub_ldea.fecha_inicio, NOW())
                                    ELSE 0 
                                END
                            ) AS tiempo_real_orden
                        FROM lotes_detalles_empleados_asignados sub_ldea
                        WHERE sub_ldea.id_empleado = $id_empleado 
                          AND sub_ldea.id_departamento = $id_departamento
                          AND sub_ldea.fecha_inicio IS NOT NULL
                        GROUP BY sub_ldea.id_orden
                    ),
                    TiemposEstimados AS (
                        SELECT 
                            op.id_orden,
                            SUM(ptp.tiempo * op.cantidad) AS tiempo_estimado_orden
                        FROM ordenes_productos op
                        INNER JOIN lotes_detalles_empleados_asignados ldea ON ldea.id_orden = op.id_orden
                        INNER JOIN products p ON p._id = op.id_woo AND p.fisico = 1
                        INNER JOIN products_tiempos_de_produccion ptp 
                            ON ptp.id_product = op.id_woo 
                            AND ptp.id_departamento = $id_departamento
                        WHERE ldea.id_empleado = $id_empleado
                          AND ldea.id_departamento = $id_departamento
                          AND ldea.fecha_inicio IS NOT NULL
                        GROUP BY op.id_orden
                    )
                    SELECT 
                        COALESCE(SUM(tr.tiempo_real_orden), 0) AS total_tiempo_real,
                        COALESCE(SUM(te.tiempo_estimado_orden), 0) AS total_tiempo_estimado
                    FROM (
                        SELECT DISTINCT id_orden 
                        FROM lotes_detalles_empleados_asignados
                        WHERE id_empleado = $id_empleado
                          AND id_departamento = $id_departamento
                          AND fecha_inicio IS NOT NULL
                    ) ordenes
                    LEFT JOIN TiemposReales tr ON tr.id_orden = ordenes.id_orden
                    LEFT JOIN TiemposEstimados te ON te.id_orden = ordenes.id_orden
                ";

                $eficienciaResult = $localConnection->goQuery($sqlEficiencia);
                $finalResponse['eficiencia'] = [
                    'tiempo_real' => !empty($eficienciaResult) ? (float) $eficienciaResult[0]['total_tiempo_real'] : 0,
                    'tiempo_estimado' => !empty($eficienciaResult) ? (float) $eficienciaResult[0]['total_tiempo_estimado'] : 0,
                ];

                // 3. PAGOS DE LA SEMANA
                $sqlPagos = "SELECT 
                        SUM(p.monto_pago) as total_pagado,
                        DATE_FORMAT(ldea.fecha_terminado, '%W') as dia,
                        DATE(ldea.fecha_terminado) as fecha
                    FROM pagos p
                    JOIN lotes_detalles_empleados_asignados ldea ON p.id_lotes_detalles = ldea._id
                    WHERE p.id_empleado = $id_empleado
                    AND p.estatus = 'aprobado'
                    AND YEARWEEK(ldea.fecha_terminado, 1) = YEARWEEK(NOW(), 1)
                    GROUP BY DATE(ldea.fecha_terminado)";

                $pagosResult = $localConnection->goQuery($sqlPagos);
                $finalResponse['pagos_semana'] = $pagosResult;
            }

            $localConnection->disconnect();
            $response->getBody()->write(json_encode($finalResponse, JSON_NUMERIC_CHECK));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            if ($localConnection)
                $localConnection->disconnect();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

}; // Fin de la función que envuelve las rutas
