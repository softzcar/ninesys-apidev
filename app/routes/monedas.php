<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

  /** * CATALOGO_MONEDAS (Fase 5 del rediseño de monedas/métodos de pago) */

  $app->get('/monedas', function (Request $request, Response $response) {
    $localConnection = new LocalDB();
    $sql = 'SELECT * FROM catalogo_monedas WHERE eliminado = 0 ORDER BY es_base DESC, nombre';
    $rows = $localConnection->goQuery($sql);
    $localConnection->disconnect();

    // goQuery devuelve los valores crudos del driver (ej. "0"/"1" como string
    // en vez de boolean real) -- Vue2 trata el número/string "0" como valor
    // "presente" para atributos booleanos como `disabled`, así que sin este
    // cast explícito el botón "eliminar" queda deshabilitado para TODAS las
    // monedas, no solo la base. Se castean los 3 flags a boolean real.
    $object['data'] = array_map(function ($row) {
      $row['es_base'] = (bool) $row['es_base'];
      $row['activo'] = (bool) $row['activo'];
      $row['eliminado'] = (bool) $row['eliminado'];
      return $row;
    }, $rows);

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Cuántos registros reales dependen de una moneda, para advertir antes de
  // eliminarla (mismo patrón que GET /telas/{id}/uso).
  $app->get('/monedas/{id}/uso', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];
    $localConnection = new LocalDB();
    $sql = 'SELECT
              (SELECT COUNT(*) FROM metodos_de_pago WHERE id_moneda = ?) AS metodos_de_pago,
              (SELECT COUNT(*) FROM caja WHERE id_moneda = ?) AS caja,
              (SELECT COUNT(*) FROM retiros WHERE id_moneda = ?) AS retiros';
    $result = $localConnection->goQuery($sql, [$id, $id, $id]);
    $localConnection->disconnect();

    $uso = $result[0] ?? ['metodos_de_pago' => 0, 'caja' => 0, 'retiros' => 0];
    $uso = array_map('intval', $uso);
    $uso['total'] = array_sum($uso);

    $response->getBody()->write(json_encode($uso, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/monedas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $nombre = trim($data['nombre'] ?? '');
    $simbolo = trim($data['simbolo'] ?? '');
    $idMonedaSoportada = isset($data['id_moneda_soportada']) ? (int) $data['id_moneda_soportada'] : null;
    $reactivarId = (isset($data['reactivar_id']) && $data['reactivar_id'] !== '') ? (int) $data['reactivar_id'] : null;

    if ($codigo === '' || $nombre === '') {
      $response->getBody()->write(json_encode(['error' => 'El código y el nombre de la moneda son obligatorios.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    if ($reactivarId) {
      // Reactivar la moneda existente en vez de crear un registro duplicado --
      // preserva el mismo _id, por lo que todo el historial que ya la
      // referencia (metodos_de_pago, caja, retiros) sigue intacto.
      $localConnection->goQuery(
        'UPDATE catalogo_monedas SET codigo = ?, nombre = ?, simbolo = ?, id_moneda_soportada = ?, eliminado = 0 WHERE _id = ?',
        [$codigo, $nombre, $simbolo, $idMonedaSoportada, $reactivarId]
      );
      $localConnection->disconnect();

      $responseData = ['message' => 'Moneda reactivada exitosamente.', 'id' => $reactivarId];
      $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // Verificar si ya existe una moneda con el mismo código (sin importar
    // mayúsculas/espacios), esté activa o eliminada.
    $existing = $localConnection->goQuery(
      'SELECT _id, codigo, nombre, eliminado FROM catalogo_monedas WHERE UPPER(TRIM(codigo)) = ?',
      [$codigo]
    );

    if ($existing) {
      $match = $existing[0];

      if ((int) $match['eliminado'] === 1) {
        $localConnection->disconnect();
        $object = [
          'eliminado_existente' => true,
          'id' => $match['_id'],
          'codigo' => $match['codigo'],
          'nombre' => $match['nombre'],
        ];
        $response->getBody()->write(json_encode($object));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
      }

      $localConnection->disconnect();
      $object = ['error' => 'Ya existe una moneda activa con el código "' . $match['codigo'] . '"'];
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // es_base nunca se establece por esta vía -- la moneda base se define una
    // sola vez (Fase 2/migración inicial de la empresa), no se reasigna desde
    // el Gestor de Monedas en esta fase.
    $sql = 'INSERT INTO catalogo_monedas (id_moneda_soportada, codigo, nombre, simbolo, es_base) VALUES (?, ?, ?, ?, 0)';
    $result = $localConnection->goQuery($sql, [$idMonedaSoportada, $codigo, $nombre, $simbolo]);
    $newId = $result['insert_id'] ?? null;
    $sql = 'SELECT * FROM catalogo_monedas WHERE eliminado = 0 ORDER BY es_base DESC, nombre';
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $object['id'] = $newId;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/monedas/editar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    // El código (identificador ISO) y es_base no se editan desde aquí -- solo
    // el nombre/símbolo de presentación y si está activa para nuevos pagos.
    $sql = 'UPDATE catalogo_monedas SET nombre = ?, simbolo = ?, activo = ? WHERE _id = ?';
    $activo = isset($data['activo']) ? (int) filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN) : 1;
    $localConnection->goQuery($sql, [trim($data['nombre']), trim($data['simbolo'] ?? ''), $activo, $data['id']]);
    $sql = 'SELECT * FROM catalogo_monedas WHERE eliminado = 0 ORDER BY es_base DESC, nombre';
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Esta página no es un CRUD completo de monedas -- solo permite asignar,
  // desasignar (soft-delete vía /monedas/eliminar) y elegir cuál es la
  // moneda base. Nunca queda la empresa sin moneda base: la base nunca se
  // puede desasignar (ver /monedas/eliminar) y este endpoint exige que el
  // destino ya esté activo antes de convertirlo en base.
  $app->post('/monedas/establecer-base', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = (int) ($data['id'] ?? 0);
    $localConnection = new LocalDB();

    $moneda = $localConnection->goQuery(
      'SELECT _id, es_base, activo, eliminado FROM catalogo_monedas WHERE _id = ?',
      [$id]
    );

    if (empty($moneda)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'La moneda indicada no existe.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if ((int) $moneda[0]['eliminado'] === 1 || (int) $moneda[0]['activo'] !== 1) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'Solo una moneda activa puede convertirse en la moneda base.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    if ((int) $moneda[0]['es_base'] === 1) {
      $localConnection->disconnect();
      $responseData = ['message' => 'Esa moneda ya es la base.'];
      $response->getBody()->write(json_encode($responseData));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $localConnection->beginTransaction();
    try {
      $localConnection->goQuery('UPDATE catalogo_monedas SET es_base = 0 WHERE es_base = 1');
      $localConnection->goQuery('UPDATE catalogo_monedas SET es_base = 1 WHERE _id = ?', [$id]);
      $localConnection->commit();
    } catch (\Throwable $e) {
      $localConnection->rollback();
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'No se pudo establecer la moneda base.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $localConnection->disconnect();
    $responseData = ['message' => 'Moneda base actualizada exitosamente.'];
    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  // Tasa manual de respaldo, para monedas sin estrategia automática de
  // obtención en GET /tasas-cambio (ej. Euro). La moneda base nunca la
  // necesita -- siempre reporta tasa=1 desde ese mismo endpoint.
  $app->post('/monedas/establecer-tasa', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = (int) ($data['id'] ?? 0);
    $tasa = isset($data['tasa']) ? (float) $data['tasa'] : null;

    if (!$id || $tasa === null || $tasa <= 0) {
      $response->getBody()->write(json_encode(['error' => 'Debe indicar una moneda y una tasa mayor a 0.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    $moneda = $localConnection->goQuery('SELECT _id, es_base FROM catalogo_monedas WHERE _id = ?', [$id]);
    if (empty($moneda)) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'La moneda indicada no existe.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if ((int) $moneda[0]['es_base'] === 1) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'La moneda base siempre tiene tasa 1, no se puede editar.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection->goQuery(
      'UPDATE catalogo_monedas SET tasa_manual = ?, tasa_manual_actualizado_en = CURRENT_TIMESTAMP WHERE _id = ?',
      [$tasa, $id]
    );
    $localConnection->disconnect();

    $responseData = ['message' => 'Tasa actualizada exitosamente.'];
    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  });

  $app->post('/monedas/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = (int) $data['id'];
    $localConnection = new LocalDB();

    $existing = $localConnection->goQuery('SELECT es_base FROM catalogo_monedas WHERE _id = ?', [$id]);
    if (!empty($existing) && (int) $existing[0]['es_base'] === 1) {
      $localConnection->disconnect();
      $response->getBody()->write(json_encode(['error' => 'La moneda base de la empresa no puede eliminarse.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'UPDATE catalogo_monedas SET eliminado = 1 WHERE _id = ?';
    $localConnection->goQuery($sql, [$id]);
    $localConnection->disconnect();

    $responseData = ['message' => 'Moneda eliminada exitosamente.'];
    $response->getBody()->write(json_encode($responseData));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  /** * CATALOGO_METODOS_PAGO (Fase 5 del rediseño de monedas/métodos de pago) */

  $app->get('/metodos-pago', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $localConnection = new LocalDB();

    if (!empty($params['id_moneda'])) {
      $sql = 'SELECT * FROM catalogo_metodos_pago WHERE eliminado = 0 AND id_moneda = ? ORDER BY nombre';
      $object['data'] = $localConnection->goQuery($sql, [(int) $params['id_moneda']]);
    } else {
      $sql = 'SELECT * FROM catalogo_metodos_pago WHERE eliminado = 0 ORDER BY id_moneda, nombre';
      $object['data'] = $localConnection->goQuery($sql);
    }
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->get('/metodos-pago/{id}/uso', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];
    $localConnection = new LocalDB();
    $sql = 'SELECT
              (SELECT COUNT(*) FROM metodos_de_pago WHERE id_metodo_pago = ?) AS metodos_de_pago,
              (SELECT COUNT(*) FROM retiros WHERE id_metodo_pago = ?) AS retiros';
    $result = $localConnection->goQuery($sql, [$id, $id]);
    $localConnection->disconnect();

    $uso = $result[0] ?? ['metodos_de_pago' => 0, 'retiros' => 0];
    $uso = array_map('intval', $uso);
    $uso['total'] = array_sum($uso);

    $response->getBody()->write(json_encode($uso, JSON_NUMERIC_CHECK));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/metodos-pago', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $idMoneda = (int) ($data['id_moneda'] ?? 0);
    $codigo = strtolower(trim($data['codigo'] ?? ''));
    $nombre = trim($data['nombre'] ?? '');
    $requiereReferencia = isset($data['requiere_referencia']) ? (int) filter_var($data['requiere_referencia'], FILTER_VALIDATE_BOOLEAN) : 0;
    $esEfectivo = isset($data['es_efectivo']) ? (int) filter_var($data['es_efectivo'], FILTER_VALIDATE_BOOLEAN) : 0;
    $reactivarId = (isset($data['reactivar_id']) && $data['reactivar_id'] !== '') ? (int) $data['reactivar_id'] : null;

    if (!$idMoneda || $codigo === '' || $nombre === '') {
      $response->getBody()->write(json_encode(['error' => 'La moneda, el código y el nombre del método de pago son obligatorios.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $localConnection = new LocalDB();

    if ($reactivarId) {
      $localConnection->goQuery(
        'UPDATE catalogo_metodos_pago SET nombre = ?, requiere_referencia = ?, es_efectivo = ?, eliminado = 0 WHERE _id = ?',
        [$nombre, $requiereReferencia, $esEfectivo, $reactivarId]
      );
      $localConnection->disconnect();

      $responseData = ['message' => 'Método de pago reactivado exitosamente.', 'id' => $reactivarId];
      $response->getBody()->write(json_encode($responseData, JSON_NUMERIC_CHECK));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $existing = $localConnection->goQuery(
      'SELECT _id, nombre, eliminado FROM catalogo_metodos_pago WHERE id_moneda = ? AND LOWER(TRIM(codigo)) = ?',
      [$idMoneda, $codigo]
    );

    if ($existing) {
      $match = $existing[0];

      if ((int) $match['eliminado'] === 1) {
        $localConnection->disconnect();
        $object = [
          'eliminado_existente' => true,
          'id' => $match['_id'],
          'nombre' => $match['nombre'],
        ];
        $response->getBody()->write(json_encode($object));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
      }

      $localConnection->disconnect();
      $object = ['error' => 'Ya existe un método de pago activo llamado "' . $match['nombre'] . '" para esta moneda.'];
      $response->getBody()->write(json_encode($object));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $sql = 'INSERT INTO catalogo_metodos_pago (id_moneda, codigo, nombre, requiere_referencia, es_efectivo) VALUES (?, ?, ?, ?, ?)';
    $result = $localConnection->goQuery($sql, [$idMoneda, $codigo, $nombre, $requiereReferencia, $esEfectivo]);
    $newId = $result['insert_id'] ?? null;
    $sql = 'SELECT * FROM catalogo_metodos_pago WHERE eliminado = 0 ORDER BY id_moneda, nombre';
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $object['id'] = $newId;
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/metodos-pago/editar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $requiereReferencia = isset($data['requiere_referencia']) ? (int) filter_var($data['requiere_referencia'], FILTER_VALIDATE_BOOLEAN) : 0;
    $esEfectivo = isset($data['es_efectivo']) ? (int) filter_var($data['es_efectivo'], FILTER_VALIDATE_BOOLEAN) : 0;
    $activo = isset($data['activo']) ? (int) filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN) : 1;
    $sql = 'UPDATE catalogo_metodos_pago SET nombre = ?, requiere_referencia = ?, es_efectivo = ?, activo = ? WHERE _id = ?';
    $localConnection->goQuery($sql, [trim($data['nombre']), $requiereReferencia, $esEfectivo, $activo, $data['id']]);
    $sql = 'SELECT * FROM catalogo_metodos_pago WHERE eliminado = 0 ORDER BY id_moneda, nombre';
    $object['response'] = json_encode($localConnection->goQuery($sql));
    $localConnection->disconnect();

    $response->getBody()->write(json_encode($object));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  $app->post('/metodos-pago/eliminar', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $localConnection = new LocalDB();
    $sql = 'UPDATE catalogo_metodos_pago SET eliminado = 1 WHERE _id = ?';
    $localConnection->goQuery($sql, [$data['id']]);
    $localConnection->disconnect();

    $responseData = ['message' => 'Método de pago eliminado exitosamente.'];
    $response->getBody()->write(json_encode($responseData));

    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });
};
