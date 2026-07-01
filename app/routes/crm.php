<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

  // 1. GET /crm/oportunidades - Obtener todas las oportunidades en el embudo
  $app->get('/crm/oportunidades', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $dbEmpresas = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    try {
      $sql = "SELECT o._id, o.id_customer, o.titulo, o.descripcion, o.monto_estimado, o.estado, o.motivo_perdida, o.id_campana, o.moment,
                     CONCAT(c.first_name, ' ', COALESCE(c.last_name, '')) AS cliente_nombre, c.phone AS cliente_telefono, c.email AS cliente_email
              FROM crm_oportunidades o
              LEFT JOIN customers c ON o.id_customer = c._id
              ORDER BY o._id DESC";
      $oportunidades = $localConnection->goQuery($sql);

      $result = [];
      if (!empty($oportunidades) && !isset($oportunidades['status'])) {
        foreach ($oportunidades as $op) {
          $id_oportunidad = intval($op['_id']);
          
          // Obtener los vendedores asignados a esta oportunidad
          $sqlVendedores = "SELECT id_vendedor FROM crm_oportunidades_vendedores WHERE id_oportunidad = ?";
          $vendedoresRaw = $localConnection->goQuery($sqlVendedores, [$id_oportunidad]);
          
          $vendedores = [];
          if (!empty($vendedoresRaw) && !isset($vendedoresRaw['status'])) {
            foreach ($vendedoresRaw as $v) {
              $id_v = intval($v['id_vendedor']);
              $sqlNombre = "SELECT nombre FROM empresas_usuarios WHERE id_usuario = ? LIMIT 1";
              $nombreRaw = $dbEmpresas->goQuery($sqlNombre, [$id_v]);
              $nombre = !empty($nombreRaw) && isset($nombreRaw[0]['nombre']) ? $nombreRaw[0]['nombre'] : "Vendedor {$id_v}";
              
              $vendedores[] = [
                'id_vendedor' => $id_v,
                'nombre' => $nombre
              ];
            }
          }
          $op['vendedores'] = $vendedores;
          $result[] = $op;
        }
      }

      $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
      $dbEmpresas->disconnect();
    }
  });

  // 2. POST /crm/oportunidades/nueva - Registrar una nueva oportunidad de venta
  $app->post('/crm/oportunidades/nueva', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $localConnection->beginTransaction();
      $id_customer = isset($data['id_customer']) ? intval($data['id_customer']) : null;
      $titulo = $data['titulo'] ?? 'Oportunidad sin título';
      $descripcion = $data['descripcion'] ?? null;
      $monto_estimado = isset($data['monto_estimado']) ? floatval($data['monto_estimado']) : 0.00;
      $estado = $data['estado'] ?? 'nuevo_lead';
      $id_campana = (isset($data['id_campana']) && intval($data['id_campana']) > 0) ? intval($data['id_campana']) : null;

      $sql = "INSERT INTO crm_oportunidades (id_customer, titulo, descripcion, monto_estimado, estado, id_campana)
              VALUES (?, ?, ?, ?, ?, ?)";
      $insertRes = $localConnection->goQuery($sql, [$id_customer, $titulo, $descripcion, $monto_estimado, $estado, $id_campana]);
      $id_oportunidad = $insertRes['insert_id'] ?? null;

      if ($id_oportunidad && isset($data['vendedores']) && is_array($data['vendedores'])) {
        foreach ($data['vendedores'] as $id_vendedor) {
          $id_v = intval($id_vendedor);
          $sqlV = "INSERT INTO crm_oportunidades_vendedores (id_oportunidad, id_vendedor) VALUES (?, ?)";
          $localConnection->goQuery($sqlV, [$id_oportunidad, $id_v]);
        }
      }

      $localConnection->commit();
      $response->getBody()->write(json_encode(['success' => true, 'id_oportunidad' => $id_oportunidad]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (Exception $e) {
      if ($localConnection && $localConnection->inTransaction()) {
        $localConnection->rollback();
      }
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 3. PUT /crm/oportunidades/estado - Mover lead en el embudo (Kanban)
  $app->put('/crm/oportunidades/estado', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $id_oportunidad = intval($data['id_oportunidad']);
      $estado = $data['estado'];
      $motivo_perdida = $data['motivo_perdida'] ?? null;

      $sql = "UPDATE crm_oportunidades SET estado = ?, motivo_perdida = ? WHERE _id = ?";
      $localConnection->goQuery($sql, [$estado, $motivo_perdida, $id_oportunidad]);

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 4. GET /customers/orders-local/{id_customer} - Historial de órdenes locales de producción
  $app->get('/customers/orders-local/{id_customer}', function (Request $request, Response $response, array $args) {
    $id_customer = intval($args['id_customer']);
    $localConnection = new LocalDB();

    try {
      $sql = "SELECT o._id, o.status, o.pago_total, o.pago_abono, o.fecha_creacion, o.moment,
                     (SELECT SUM(cantidad) FROM ordenes_productos WHERE id_orden = o._id) AS total_productos
              FROM ordenes o
              WHERE o.id_wp = ?
              ORDER BY o._id DESC";
      $ordenes = $localConnection->goQuery($sql, [$id_customer]);

      $result = [];
      if (!empty($ordenes) && !isset($ordenes['status'])) {
        foreach ($ordenes as $o) {
          $id_orden = intval($o['_id']);
          
          $sqlP = "SELECT id_woo, name, cantidad, precio_unitario, talla, corte, tela
                   FROM ordenes_productos
                   WHERE id_orden = ?";
          $productos = $localConnection->goQuery($sqlP, [$id_orden]);
          $o['productos'] = $productos;
          $result[] = $o;
        }
      }

      $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 5. GET /customers/presupuestos-local/{id_customer} - Historial de presupuestos locales
  $app->get('/customers/presupuestos-local/{id_customer}', function (Request $request, Response $response, array $args) {
    $id_customer = intval($args['id_customer']);
    $localConnection = new LocalDB();

    try {
      $sql = "SELECT _id, status, tipo, cliente_nombre, fecha_creacion, pago_total, moment
              FROM presupuestos
              WHERE id_wp = ?
              ORDER BY _id DESC";
      $presupuestos = $localConnection->goQuery($sql, [$id_customer]);

      $result = [];
      if (!empty($presupuestos) && !isset($presupuestos['status'])) {
        foreach ($presupuestos as $p) {
          $id_presupuesto = intval($p['_id']);
          
          $sqlP = "SELECT id_woo, name, cantidad, precio_unitario, talla, corte, tela
                   FROM presupuestos_productos
                   WHERE id_orden = ?";
          $productos = $localConnection->goQuery($sqlP, [$id_presupuesto]);
          $p['productos'] = $productos;
          $result[] = $p;
        }
      }

      $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 6. GET /crm/notas/{id_customer} - Obtener la bitácora de notas de un cliente
  $app->get('/crm/notas/{id_customer}', function (Request $request, Response $response, array $args) {
    $id_customer = intval($args['id_customer']);
    $localConnection = new LocalDB();
    $dbEmpresas = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    try {
      $sql = "SELECT n._id, n.id_customer, n.id_oportunidad, n.id_usuario_creador, n.nota, n.moment
              FROM crm_notas n
              WHERE n.id_customer = ?
              ORDER BY n._id DESC";
      $notas = $localConnection->goQuery($sql, [$id_customer]);

      $result = [];
      if (!empty($notas) && !isset($notas['status'])) {
        foreach ($notas as $n) {
          $id_u = intval($n['id_usuario_creador']);
          
          $sqlNombre = "SELECT nombre FROM empresas_usuarios WHERE id_usuario = ? LIMIT 1";
          $nombreRaw = $dbEmpresas->goQuery($sqlNombre, [$id_u]);
          $n['creador_nombre'] = !empty($nombreRaw) && isset($nombreRaw[0]['nombre']) ? $nombreRaw[0]['nombre'] : "Usuario {$id_u}";
          $result[] = $n;
        }
      }

      $response->getBody()->write(json_encode($result, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
      $dbEmpresas->disconnect();
    }
  });

  // 7. POST /crm/notas/nueva - Agregar nota a la bitácora
  $app->post('/crm/notas/nueva', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $id_customer = intval($data['id_customer']);
      $id_oportunidad = (isset($data['id_oportunidad']) && intval($data['id_oportunidad']) > 0) ? intval($data['id_oportunidad']) : null;
      $id_usuario_creador = intval($data['id_usuario_creador']);
      $nota = $data['nota'];

      $sql = "INSERT INTO crm_notas (id_customer, id_oportunidad, id_usuario_creador, nota)
              VALUES (?, ?, ?, ?)";
      $localConnection->goQuery($sql, [$id_customer, $id_oportunidad, $id_usuario_creador, $nota]);

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 8. GET /crm/soporte/{id_customer} - Obtener incidencias de soporte
  $app->get('/crm/soporte/{id_customer}', function (Request $request, Response $response, array $args) {
    $id_customer = intval($args['id_customer']);
    $localConnection = new LocalDB();

    try {
      $sql = "SELECT _id, titulo, descripcion, estado, moment
              FROM crm_soporte
              WHERE id_customer = ?
              ORDER BY _id DESC";
      $incidencias = $localConnection->goQuery($sql, [$id_customer]);

      $response->getBody()->write(json_encode($incidencias, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 9. POST /crm/soporte/nueva - Registrar incidencia de soporte
  $app->post('/crm/soporte/nueva', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $id_customer = intval($data['id_customer']);
      $titulo = $data['titulo'];
      $descripcion = $data['descripcion'];
      $estado = $data['estado'] ?? 'abierto';

      $sql = "INSERT INTO crm_soporte (id_customer, titulo, descripcion, estado)
              VALUES (?, ?, ?, ?)";
      $localConnection->goQuery($sql, [$id_customer, $titulo, $descripcion, $estado]);

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 9.5 PUT /crm/soporte/estado - Actualizar estado de soporte (ej: resolver ticket)
  $app->put('/crm/soporte/estado', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $id_incidencia = intval($data['id_incidencia']);
      $estado = $data['estado']; // 'abierto', 'resuelto'

      $sql = "UPDATE crm_soporte SET estado = ? WHERE _id = ?";
      $localConnection->goQuery($sql, [$estado, $id_incidencia]);

      $response->getBody()->write(json_encode(['success' => true]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 10. POST /crm/campanas/enviar - Enviar campaña de WhatsApp a clientes segmentados por producto
  $app->post('/crm/campanas/enviar', function (Request $request, Response $response) {
    $rawBody = $request->getBody()->getContents();
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      $data = $request->getParsedBody() ?? [];
    }
    $localConnection = new LocalDB();

    try {
      $nombre = $data['nombre'] ?? 'Campaña sin nombre';
      $mensaje_plantilla = $data['mensaje_plantilla'];
      $filtro_productos = $data['filtro_productos'] ?? []; // Array de IDs de productos
      
      // Registrar la campaña en la base de datos
      $filtro_json = json_encode($filtro_productos);
      $sqlC = "INSERT INTO crm_campanas (nombre, mensaje_plantilla, filtro_productos) VALUES (?, ?, ?)";
      $resC = $localConnection->goQuery($sqlC, [$nombre, $mensaje_plantilla, $filtro_json]);
      $id_campana = $resC['insert_id'] ?? null;

      if (!$id_campana) {
        throw new Exception("No se pudo registrar la campaña en la base de datos.");
      }

      // Buscar clientes segmentados
      if (!empty($filtro_productos)) {
        // Clientes que compraron estos productos
        $inQuery = implode(',', array_map('intval', $filtro_productos));
        $sqlCl = "SELECT DISTINCT c._id, c.first_name, c.last_name, c.phone
                  FROM customers c
                  JOIN ordenes o ON o.id_wp = c._id
                  JOIN ordenes_productos op ON op.id_orden = o._id
                  WHERE op.id_woo IN ($inQuery) AND c.phone IS NOT NULL AND c.phone != '' AND c.eliminado = 0";
        $clientes = $localConnection->goQuery($sqlCl);
      } else {
        // Si no hay filtro, enviar a todos los clientes registrados
        $sqlCl = "SELECT _id, first_name, last_name, phone FROM customers WHERE phone IS NOT NULL AND phone != '' AND eliminado = 0";
        $clientes = $localConnection->goQuery($sqlCl);
      }

      $whatsAppApiClient = new WhatsAppAPIClient(WS_API_URL);
      $id_empresa = ID_EMPRESA;

      $envios = [];
      if (!empty($clientes) && !isset($clientes['status'])) {
        foreach ($clientes as $c) {
          $id_customer = intval($c['_id']);
          $phone = $c['phone'];
          
          // Reemplazar variables personalizadas
          $first_name = $c['first_name'] ?? '';
          $last_name = $c['last_name'] ?? '';
          $msg = str_replace(
            ['{{first_name}}', '{{last_name}}'],
            [$first_name, $last_name],
            $mensaje_plantilla
          );

          $estado_envio = 'enviado';
          try {
            // Enviar mensaje directo por WhatsApp
            $whatsAppApiClient->sendDirectMessageToNode($id_empresa, $phone, $msg);
          } catch (Exception $e) {
            $estado_envio = 'fallido';
          }

          // Registrar el estado de envío
          $sqlE = "INSERT INTO crm_campanas_envios (id_campana, id_customer, estado_envio) VALUES (?, ?, ?)";
          $localConnection->goQuery($sqlE, [$id_campana, $id_customer, $estado_envio]);

          $envios[] = [
            'id_customer' => $id_customer,
            'estado' => $estado_envio
          ];

          // Pequeña pausa para evitar sobrecargar el enviador/bloqueo de whatsapp
          usleep(200000); // 200 ms
        }
      }

      $response->getBody()->write(json_encode([
        'success' => true,
        'id_campana' => $id_campana,
        'total_clientes' => count($envios),
        'envios' => $envios
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

  // 11. GET /crm/reports/dashboard - Métricas comerciales e ROI de campañas
  $app->get('/crm/reports/dashboard', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $dbEmpresas = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    try {
      // 1. Oportunidades por estado
      $sqlEstado = "SELECT estado, COUNT(*) as cantidad, SUM(monto_estimado) as total_monto
                    FROM crm_oportunidades
                    GROUP BY estado";
      $oportunidadesPorEstado = $localConnection->goQuery($sqlEstado);

      // 2. Tasa de conversión (Ganados vs Perdidos)
      $sqlConversion = "SELECT 
                          SUM(CASE WHEN estado = 'cliente_ganado' THEN 1 ELSE 0 END) as ganados,
                          SUM(CASE WHEN estado = 'cliente_perdido' THEN 1 ELSE 0 END) as perdidos
                        FROM crm_oportunidades
                        WHERE estado IN ('cliente_ganado', 'cliente_perdido')";
      $conversionRaw = $localConnection->goQuery($sqlConversion);
      $conversion = [
        'ganados' => isset($conversionRaw[0]['ganados']) ? intval($conversionRaw[0]['ganados']) : 0,
        'perdidos' => isset($conversionRaw[0]['perdidos']) ? intval($conversionRaw[0]['perdidos']) : 0,
        'tasa_conversion' => 0
      ];
      $totalCerrados = $conversion['ganados'] + $conversion['perdidos'];
      if ($totalCerrados > 0) {
        $conversion['tasa_conversion'] = round(($conversion['ganados'] / $totalCerrados) * 100, 2);
      }

      // 3. Ventas por Vendedor (Oportunidades ganadas)
      $sqlVentasVendedor = "SELECT v.id_vendedor, COUNT(o._id) as cantidad_ganada, SUM(o.monto_estimado) as total_ganado
                            FROM crm_oportunidades o
                            JOIN crm_oportunidades_vendedores v ON v.id_oportunidad = o._id
                            WHERE o.estado = 'cliente_ganado'
                            GROUP BY v.id_vendedor";
      $ventasVendedorRaw = $localConnection->goQuery($sqlVentasVendedor);
      
      $ventasVendedor = [];
      if (!empty($ventasVendedorRaw) && !isset($ventasVendedorRaw['status'])) {
        foreach ($ventasVendedorRaw as $vv) {
          $id_v = intval($vv['id_vendedor']);
          $sqlNombre = "SELECT nombre FROM empresas_usuarios WHERE id_usuario = ? LIMIT 1";
          $nombreRaw = $dbEmpresas->goQuery($sqlNombre, [$id_v]);
          $nombre = !empty($nombreRaw) && isset($nombreRaw[0]['nombre']) ? $nombreRaw[0]['nombre'] : "Vendedor {$id_v}";

          $ventasVendedor[] = [
            'id_vendedor' => $id_v,
            'nombre' => $nombre,
            'cantidad_ganada' => intval($vv['cantidad_ganada']),
            'total_ganado' => floatval($vv['total_ganado'])
          ];
        }
      }

      // 4. Efectividad de Campañas de Marketing
      $sqlCampanas = "SELECT c._id as id_campana, c.nombre, c.moment,
                             (SELECT COUNT(*) FROM crm_campanas_envios WHERE id_campana = c._id) as total_envios,
                             (SELECT COUNT(*) FROM crm_oportunidades WHERE id_campana = c._id AND estado = 'cliente_ganado') as oportunidades_ganadas
                      FROM crm_campanas c
                      ORDER BY c._id DESC
                      LIMIT 10";
      $campanasRaw = $localConnection->goQuery($sqlCampanas);
      
      $campanas = [];
      if (!empty($campanasRaw) && !isset($campanasRaw['status'])) {
        foreach ($campanasRaw as $cam) {
          $total_envios = intval($cam['total_envios']);
          $ganadas = intval($cam['oportunidades_ganadas']);
          $efectividad = 0;
          if ($total_envios > 0) {
            $efectividad = round(($ganadas / $total_envios) * 100, 2);
          }
          $campanas[] = [
            'id_campana' => intval($cam['id_campana']),
            'nombre' => $cam['nombre'],
            'fecha' => $cam['moment'],
            'total_envios' => $total_envios,
            'ganadas' => $ganadas,
            'efectividad' => $efectividad
          ];
        }
      }

      $dashboard = [
        'oportunidades_por_estado' => $oportunidadesPorEstado,
        'conversion' => $conversion,
        'ventas_por_vendedor' => $ventasVendedor,
        'campanas_efectividad' => $campanas
      ];

      $response->getBody()->write(json_encode($dashboard, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
      $dbEmpresas->disconnect();
    }
  });

  // 12. GET /crm/clientes-por-producto/{id_product} - Obtener IDs de clientes que compraron un producto
  $app->get('/crm/clientes-por-producto/{id_product}', function (Request $request, Response $response, array $args) {
    $id_product = intval($args['id_product']);
    $localConnection = new LocalDB();

    try {
      $sql = "SELECT DISTINCT o.id_wp as id_customer
              FROM ordenes o
              JOIN ordenes_productos op ON op.id_orden = o._id
              WHERE op.id_woo = ?";
      $clientes = $localConnection->goQuery($sql, [$id_product]);

      $ids = [];
      if (!empty($clientes) && !isset($clientes['status'])) {
        foreach ($clientes as $c) {
          $ids[] = intval($c['id_customer']);
        }
      }

      $response->getBody()->write(json_encode($ids, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } finally {
      $localConnection->disconnect();
    }
  });

};
